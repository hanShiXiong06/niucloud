<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\service;

use addon\hsx_erp_integration\app\support\{BridgeFact,ConnectionFields};
use app\service\admin\auth\AuthService;
use app\service\core\site\CoreSiteService;
use think\facade\Db;

/** Staff-only manual-task projection. Optional local-receipt read, no outbound call, dispatch or state change. */
final class OriginalDocumentResults
{
    private ?LocalOriginalReceiptView $live=null;
    public function readPermissions(int $site): array
    {
        if ($site<1 || (int)request()->siteId()!==$site || (int)request()->uid()<1
            || !in_array('hsx_erp_integration',(new CoreSiteService())->getAddonKeysBySiteId($site),true)) return [];
        $get=(new AuthService())->getAuthApiList()['get']??[];
        return in_array('integration/task/<id>',$get,true) ? $get : [];
    }

    public function purchase(int $site,int $id,string $no,bool $withPayments): array
    {
        $tasks=$this->tasks($site,'purchase',[$id]);
        $rows=[];
        foreach ($tasks as $task) if ($row=self::match($site,$task,['document_id'=>$id,'number'=>$no])) $rows[$row['key']]=$row;
        if (!$withPayments) return array_values($rows);
        $table=static fn(string $name)=>Db::name($name)->getTable();
        $links=Db::name('erp_purchase_order')->alias('o')
            ->join([$table('erp_purchase_item')=>'i'],'i.site_id=o.site_id AND i.purchase_order_id=o.id')
            ->join([$table('erp_asset')=>'a'],'a.site_id=i.site_id AND a.id=i.asset_id AND a.purchase_item_id=i.id AND a.purchase_order_id=o.id')
            ->join([$table('erp_payable')=>'p'],"p.site_id=o.site_id AND p.party_id=o.party_id AND p.source_type='purchase_asset' AND p.source_id=a.id AND (p.asset_id=0 OR p.asset_id=a.id)")
            ->join([$table('erp_settlement_link')=>'l'],"l.site_id=p.site_id AND l.target_type='payable' AND l.target_id=p.id")
            ->join([$table('erp_settlement')=>'s'],'s.site_id=p.site_id AND s.id=l.settlement_id AND s.party_id=p.party_id')
            ->where(['o.site_id'=>$site,'o.id'=>$id,'o.purchase_no'=>$no,'i.item_type'=>'device','s.status'=>'confirmed','s.cash_direction'=>'out','s.settlement_type'=>'payment'])
            ->where('p.status','<>','void')->where('s.confirmed_at','>',0)->where('l.applied_amount','>',0)
            ->field('i.id line_id,a.id asset_id,p.id payable_id,s.id settlement_id,s.settlement_no,s.amount voucher_amount,l.applied_amount')->select()->toArray();
        $bySettlement=[];
        foreach ($links as $link) $bySettlement[(int)$link['settlement_id']][]=$link;
        $existing=$this->tasks($site,'payment',array_keys($bySettlement));
        foreach ($existing as $task) {
            foreach ($bySettlement[(int)$task['source_id']] as $link) {
                $fact=['document_id'=>$id,'number'=>$no,'settlements'=>[(int)$link['settlement_id']]]+$link;
                if ($row=self::match($site,$task,$fact)) { $rows[$row['key']]=$row; break; }
            }
        }
        foreach(array_diff(array_keys($bySettlement),array_column($existing,'source_id')) as $sid) {
            if($row=$this->recoverablePayment($site,$id,(int)$sid)) $rows[$row['key']]=$row;
        }
        return array_values($rows);
    }

    public function sale(int $site,int $id,string $no): array
    {
        $rows=[];
        foreach ($this->tasks($site,'sale',[$id]) as $task) {
            if ($row=self::match($site,$task,['document_id'=>$id,'number'=>$no])) $rows[]=$row;
        }
        return $rows;
    }

    public function payables(int $site,array $facts): array
    {
        $purchases=$payments=$out=[];
        foreach ($facts as $pid=>$f) {
            if ((int)$pid<1 || (int)($f['asset_id']??0)<1 || (int)($f['line_id']??0)<1 || (int)($f['document_id']??0)<1) continue;
            $purchases[(int)$f['document_id']][(int)$pid]=$f;
            foreach ($f['settlements']??[] as $sid) if ((int)$sid>0) $payments[(int)$sid][(int)$pid]=$f;
        }
        foreach (['purchase'=>$purchases,'payment'=>$payments] as $kind=>$group) {
            $existing=$this->tasks($site,$kind,array_keys($group));
            foreach ($existing as $task) {
                foreach ($group[(int)$task['source_id']] as $pid=>$f) {
                    $f['payable_id']=$pid;
                    if ($row=self::match($site,$task,$f)) $out[$pid][]=$row;
                }
            }
            if($kind==='payment') foreach(array_diff(array_keys($group),array_column($existing,'source_id')) as $sid) {
                $bySource=[];
                foreach($group[$sid] as $pid=>$fact) {
                    $p=(int)$fact['document_id'];
                    if(!array_key_exists($p,$bySource)) $bySource[$p]=$this->recoverablePayment($site,$p,(int)$sid);
                    if($bySource[$p]) $out[$pid][]=$bySource[$p];
                }
            }
        }
        return $out;
    }

    private function recoverablePayment(int $site,int $purchase,int $sid): ?array
    {
        if(!(new OriginalCaptureRecovery())->available($site,$sid,'payment',$purchase)) return null;
        return ['key'=>'integration_recover_payment_'.$sid,'task_id'=>0,'business_type'=>'payment','provider_label'=>'ERP 集成 · 本站已有付款凭证',
            'status'=>'not_linked','status_label'=>'待关联','tone'=>'info','external_no'=>'','pc_route'=>'',
            'next_step'=>'财务已在本站登记。只关联这张凭证，不需要再次付款。','query'=>['detail_extension'=>'hsx_erp_integration',
                'original_source_id'=>$sid,'settlement_id'=>$sid,'purchase_source_id'=>$purchase,'kind'=>'payment','action_label'=>'关联已有付款凭证']];
    }

    private function tasks(int $site,string $kind,array $ids): array
    {
        if (!$ids) return [];
        $tasks=Db::name('erp_integration_sync_task')->where('site_id',$site)->where('business_type',$kind)
            ->where('source_type',$kind==='payment'?'erp_settlement':'erp_'.$kind)->whereIn('source_id',$ids)
            ->whereNotNull('fact_key')->whereNotNull('target_key')->order('id')
            ->field('id,site_id,source_id,source_type,source_no,business_type,origin_event_id,fact_key,target_key,status,payload,receipt_json,external_doc_no,connection_id,error_code')
            ->select()->toArray();
        $this->live??=new LocalOriginalReceiptView();
        return array_map(fn($t)=>$this->live->read($site,$t),$tasks);
    }

    /** Identity is matched against the captured event, not the name or amount of a sibling task. */
    public static function match(int $site,array $task,array $f): ?array
    {
        $e=ConnectionFields::values($task['payload']??null); $kind=$task['business_type']??null;
        if ($site<1 || $site!==(int)($task['site_id']??0) || $site!==(int)($e['site_id']??0) || (int)($task['id']??0)<1
            || empty($task['target_key']) || empty($task['origin_event_id']) || $task['origin_event_id']!==($e['event_id']??null)
            || !in_array($kind,['purchase','payment','sale'],true) || BridgeFact::kind($e)!==$kind) return null;
        try { $captured=BridgeFact::project($e,$kind); } catch (\InvalidArgumentException $ignored) { return null; }
        foreach (['source_type','source_no','fact_key'] as $field) if (($task[$field]??null)!==$captured[$field]) return null;
        $id=(int)$task['source_id']; $po=(int)($f['document_id']??0); $p=$e['payload']; $matched=false;
        if ($id!==$captured['source_id'] || $po<1 || empty($f['number'])) return null;
        if ($kind!=='payment') {
            if ($id!==$po || $task['source_no']!==$f['number']) return null;
            if (!isset($f['payable_id'])) $matched=true;
            else foreach ($p['lines']??[] as $line) if (($line['source_line_id']??'')==='purchase_line:'.$f['line_id']
                && ($line['device_ref']??'')==='asset:'.$f['asset_id']) { $matched=true; break; }
        } else {
            if (!in_array($id,$f['settlements']??[],true) || ($p['cash_direction']??'')!=='out' || empty($p['confirmed_at'])) return null;
            if (isset($f['settlement_no']) && ($f['settlement_no']!==$task['source_no'] || self::cents($p['amount']??null)!==self::cents($f['voucher_amount']))) return null;
            foreach ($p['targets']??[] as $target) {
                $amount=self::cents($target['applied_amount']??null);
                if (($target['target_type']??'')==='payable' && ($target['source_type']??'')==='purchase_asset'
                    && (int)($target['target_id']??0)===(int)$f['payable_id'] && (int)($target['source_id']??0)===(int)$f['asset_id']
                    && ($target['source_document_ref']??'')==='purchase:'.$po && ($target['source_line_ref']??'')==='purchase_line:'.$f['line_id']
                    && $amount!==null && $amount>0 && (!isset($f['applied_amount']) || $amount===self::cents($f['applied_amount']))) { $matched=true; break; }
            }
        }
        if (!$matched) return null;
        $receipt=ConnectionFields::values($task['receipt_json']??null); $status=$task['status']??'';
        $confirmed=$status==='succeeded' && ($receipt['status']??'')==='confirmed' && !empty($receipt['vendor_task_id'])
            && !empty($receipt['external_no']) && $receipt['external_no']===($task['external_doc_no']??null);
        if ($status==='succeeded' && !$confirmed) $status='unverified';
        $issued=$status==='linked' && ($receipt['status']??'')==='linked' && !empty($receipt['vendor_task_id'])
            && !empty($receipt['external_no']) && $receipt['external_no']===($task['external_doc_no']??null);
        [$label,$tone]=match($status) {
            'succeeded'=>[match($kind){ 'purchase'=>'采购回执已核实','sale'=>'出库回执已核实',default=>'付款单已登记' },'success'],
            'awaiting_confirmation'=>['待人工关联','warning'], 'linked'=>[$issued?'已取得单号，待核对':'已关联原任务','info'], 'waiting_source'=>['等待原单','info'],
            'blocked','failed'=>['需人工处理','warning'], default=>['待核对','warning'],
        };
        $payment=$kind==='payment'; $allocation=$receipt['allocation']??[];
        $note=$payment ? '这里只跟踪已有付款登记，不代表再次出款或本机应付已结清。' : ($kind==='sale'?'出库回执不代表已收款。':'在原单内查看或核对本次采购批次。');
        if ($payment && $confirmed) $note=($allocation['verified']??false)===true
            ? '付款凭证已登记；任务内可查看其采购批次核销证据，批次金额不能直接视为本机结清。'
            : '已登记付款凭证；尚无核销到原采购的证据，不代表本机已结清。';
        return ['key'=>'integration_manual_'.$task['id'],'task_id'=>(int)$task['id'],'business_type'=>$kind,'provider_label'=>'ERP 集成 · '.($payment?'付款 '.$task['source_no']:($kind==='sale'?'批发出库':'采购批次')).' · 连接 #'.(int)$task['connection_id'].' / 任务 #'.$task['id'],
            'purchase_target'=>$kind==='purchase'?$task['target_key']:'',
            'status'=>$status,'status_label'=>$label,'tone'=>$tone,'next_step'=>$note,'external_no'=>$confirmed||$issued?$receipt['external_no']:'',
            'pc_route'=>'','query'=>['detail_extension'=>'hsx_erp_integration','kind'=>$kind]+($payment?['settlement_id'=>$id]:[])
                +($kind==='sale' && $confirmed?['sale_record_role'=>'bridge_delivery','original_sale_id'=>$id]:[])
                +($confirmed && !empty($task['receipt_adapter'])?['receipt_adapter'=>$task['receipt_adapter'],'receipt_vendor_task_id'=>(int)$receipt['vendor_task_id']]:[])];
    }

    private static function cents($value): ?int
    {
        if (!is_scalar($value) || is_bool($value) || !preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/D',(string)$value,$m)) return null;
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }
}
