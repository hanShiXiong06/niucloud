<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

use think\facade\Log;

/** A diagnostic copy only. Never mutate the signed request, business response or dispatch decision. */
final class InterfaceTrace
{
    private const FIELDS=[
        'service','consumer','_input_charset','sign_type','version','timestamp','page','page_no','page_size','limit','cursor','next_cursor','has_more',
        'total','total_count','total_page','total_rows','total_results','record_count','count','rows','qty','quantity','price','unit_price','total_price',
        'business_date','business_date_start','business_date_end','register_date_start','register_date_end','cash_term','scope_flag','seq_no','inout_tag','contain_sub',
        'bill_type','manual_number','number','bill_no','third_no','business_no','external_no','source_document_no','device_ref','warehouse_ref','sku_ref','product_ref',
        'payment_method_ref','id','gsdm','big_gsdm','code','return_code','error_code','status','audit_status','success','state','resource','kind','memory','color',
        'category_name','product_name','product_full_name','brand_name','warehouse_name','full_name','name','pur_way_name','pay_way_name','cash_name','business_time','create_time','update_time',
    ];
    public function __construct(private int $site, private string $mode, private array $context, private \Closure $window, private ?\Closure $journal=null, private ?\Closure $detailWriter=null) {}

    public static function task(array $row): array
    {
        return ['task_id'=>(int)$row['id'],'kind'=>(string)$row['kind'],
            'event_id'=>(string)($row['event_id']??''),
            'business_no'=>($row['kind']??'')==='party_resolve'?'':(string)($row['business_no']??'')];
    }

    private function base(string $window): array
    {
        $r=['site_id'=>$this->site,'mode'=>$this->mode,'window_id'=>$window];
        foreach(['task_id','sync_id','operator_uid','source_id'] as $k) if(!empty($this->context[$k])) $r[$k]=(int)$this->context[$k];
        foreach(['kind','resource','business_no','event_name','event_id'] as $k) {
            $v=$this->context[$k]??null;
            if(is_string($v) && preg_match('/^[a-zA-Z0-9_.:-]{1,128}$/D',$v) && !preg_match('/^1[3-9][0-9]{9}$/D',$v)) $r[$k]=$v;
        }
        $r['trace_id']=isset($r['task_id'])?'T'.$this->site.'-'.$r['task_id']:(isset($r['sync_id'])?'S'.$this->site.'-'.$r['sync_id']:'R'.bin2hex(random_bytes(8)));
        return $r;
    }

    public function local(string $outcome,string $code='',?array $input=null,?array $result=null): void
    {
        try {
            if(!($window=($this->window)())) return;
            $entry=$this->base($window)+['stage'=>'local','outcome'=>$outcome,'code'=>$code];
            if($input!==null) $entry['event_input']=$this->snapshot($input,[]);
            if($result!==null) $entry['listener_result']=$this->snapshot($result,[]);
            $this->write($entry);
        } catch(\Throwable $ignored) {}
    }

    public function begin(string $method,string $operation,array $body,array $secrets=[]): ?array
    {
        try {
            $window=($this->window)();
            if(!$window && !$this->journal) return null;
            $meta=$this->base($window)+['call_id'=>bin2hex(random_bytes(8)),'method'=>$method,'operation'=>$operation,'sent_ms'=>(int)round(microtime(true)*1000)];
            if($operation==='openapi_other') unset($body['service']);
            elseif(!str_starts_with($operation,'bridge') && is_string($body['service']??null) && preg_match('/^boyuan(?:\.[a-z0-9]+){2,6}$/D',$body['service']))
                $meta['service']=$body['service']; // Known method on both sides of the pair, never an arbitrary URL.
            $payload=$this->snapshot($body,$secrets,$window==='');
            $meta['request_detail']=$this->storeDetail($meta,'request',$payload,$body,$secrets);
            $this->write($meta+['stage'=>'request','payload'=>$payload]);
            return [$meta,hrtime(true),$payload];
        } catch(\Throwable $ignored) { return null; }
    }

    /** Only closed workflow markers join the diagnostic window, not free-text operator notes or provider errors. */
    public function taskState(array $state): void
    {
        try {
            if(!($window=($this->window)())) return;
            $values=['status'=>$state['outcome'],'kind'=>$state['action']]
                + array_intersect_key($state,array_flip(['code','external_no','cursor','rows']));
            $this->write($this->base($window)+['stage'=>'task','result'=>$this->snapshot($values,[])]);
        } catch(\Throwable $ignored) {}
    }

    public function end(array $attempt,string $delivery,int $status,int $errno,?array $response,string $bodyState,string $reason,array $secrets=[]): void
    {
        try {
            [$meta,$start]=$attempt;
            $record=$meta+['stage'=>'result','finished_ms'=>(int)round(microtime(true)*1000),'delivery'=>$delivery,'http_status'=>$status?:null,'curl_errno'=>$errno?:null,
                'elapsed_ms'=>(int)round((hrtime(true)-$start)/1000000),'body_state'=>$bodyState,'error_code'=>$reason?:null];
            // Always close an already logged attempt; after stop/expiry only its non-sensitive status is retained.
            if($response!==null && ($meta['window_id']==='' || ($this->window)()===$meta['window_id'])) {
                $record['payload']=$this->snapshot($response,$secrets,$meta['window_id']==='');
                $record['response_detail']=$this->storeDetail($meta,'response',$record['payload'],$response,$secrets);
                if(isset($response['return_code'])) $record['gateway_reply']=$response['return_code']==='SUCCESS'?'accepted_envelope':'reported_error';
            }
            $this->write($record,$attempt[2]??[]);
        } catch(\Throwable $ignored) {}
    }

    private function write(array $record,?array $request=null): void
    {
        // The journal is a scoped PHP file log, not a task table. A reader never opens the mixed application log.
        if($this->journal && isset($record['call_id'])) {
            try { ($this->journal)($request!==null?$record+['request_payload'=>$request]:$record); }
            catch(\Throwable $e) { try { Log::warning('hsx_boyuan journal unavailable; site='.$this->site.'; type='.get_class($e)); } catch(\Throwable $ignored) {} }
        }
        if($record['window_id']!=='') Log::write('hsx_boyuan trace '.Contract::json($record),'info');
    }

    /** Preserve useful business data and key names. Unknown scalars/free text are not a safe logging contract. */
    private function storeDetail(array $meta,string $side,array $preview,array $source,array $secrets): bool
    {
        if(!$this->detailWriter) return false;
        $brief=Contract::json($preview);
        if(!str_contains($brief,'"_omitted"') && !str_contains($brief,'[截断]') && !str_contains($brief,'[未展开]')) return false;
        try { ($this->detailWriter)($meta,$side,$this->snapshot($source,$secrets,false,true));return true; }
        catch(\Throwable $e) { try { Log::warning('hsx_boyuan journal detail unavailable; site='.$this->site.'; type='.get_class($e)); } catch(\Throwable $ignored) {} return false; }
    }

    private function snapshot(array $data,array $secrets,bool $compact=false,bool $expanded=false): array
    {
        $left=$expanded?100000:($compact?85:120); $budget=$expanded?2097152:($compact?4500:8000); $listLimit=$expanded?100000:($compact?2:5);
        // Preserve the envelope even when a page of records precedes the return code in vendor JSON.
        $data=array_intersect_key($data,array_flip(['service','return_code','return_msg','code','error_code']))+$data;
        $walk=function($v,string $key,int $depth) use (&$walk,&$left,&$budget,$secrets,$listLimit,$expanded) {
            if(--$left<0 || $depth>($expanded?16:8) || $budget<100) return '[截断]';
            $k=strtolower(trim($key));
            if(preg_match('/secret|token|password|passwd|credential|authorization|cookie|cipher|(?:^|_)sign$|app_?id|app_?sec|phone|mobile|help_code|address|email|contact|bank|id_card|identity_card|payee|qr_?code|receipt_image|pic_url|^(?:real_name|creater_name|creator_name|login_name|remark)$/i',$k)) return '[隐藏]';
            if(preg_match('/imei|serial|^(?:sn|sn_code)$/i',$k) && !in_array($k,['imei_count','imei_len'],true)) return is_scalar($v) && strlen((string)$v)>4 ? '***'.preg_replace('/[^a-zA-Z0-9]/','',substr((string)$v,-4)):'[隐藏]';
            if(is_string($v) && in_array($k,['biz','biz_content','api_response'],true)) {
                if(strlen($v)>($expanded?2097152:131072) || !is_array($parsed=json_decode($v,true,24))) return '[未展开]';
                $v=$parsed;
            }
            if(is_array($v)) {
                $out=[]; $n=0;
                foreach($v as $field=>$item) {
                    if($left<1 || $budget<100 || (is_int($field) && $n>=$listLimit) || (!$expanded && $n>=50)) { $out['_omitted']=count($v)-$n; break; }
                    $n++; $name=is_int($field)?$field:(preg_match('/^[a-zA-Z_][a-zA-Z0-9_ ]{0,63}$/D',(string)$field)?trim((string)$field):'_field_'.$n);
                    if(is_string($name)) foreach($secrets as $secret) if(is_string($secret) && $secret!=='' && str_contains($name,$secret)) { $name='_field_'.$n; break; }
                    $budget-=strlen((string)$name)+8;
                    $out[$name]=$walk($item,is_int($field)?$k:(string)$name,$depth+1);
                }
                return $out;
            }
            if($v===null || is_bool($v) || $v==='') return $v;
            $s=(string)$v;
            if(preg_match('/^(?:return_msg|message|msg|error_msg)$/D',$k)) {
                // Never copy vendor free text (some gateways echo the request). Recognize common messages instead.
                foreach(['创建人为空','制单人为空','签名错误','库存不足','未授权','无权限','参数错误'] as $hint) if(str_contains($s,$hint)) return $hint;
                if(in_array(strtolower(trim($s)),['成功','操作成功','ok','success'],true)) return trim($s);
                return '[详见业务结果或错误码]';
            }
            foreach($secrets as $secret) if(is_string($secret) && $secret!=='' && str_contains($s,$secret)) return '[隐藏]';
            if(!in_array($k,self::FIELDS,true) && !preg_match('/^(?:[a-z_]+_id|is_[a-z_]+|(?:[a-z_]+_)?amounts?)$/D',$k)) return '[省略]';
            if(in_array($k,['full_name','name'],true) && !in_array($this->context['resource']??'',['categories','products','brands','warehouses'],true)) return '[隐藏]';
            if(strlen($s)>180 || preg_match('/https?:|[\r\n@]|(?:^|\D)(?:\+?86[- ]?)?1[3-9][0-9]{9}(?!\d)/i',$s)) return '[隐藏]';
            if(!preg_match('/^[\p{L}\p{N}\p{Zs}._:+\/-]{1,100}$/uD',$s)) return '[省略]';
            $budget-=strlen($s)+4;
            return $v;
        };
        return $walk($data,'',0);
    }
}
