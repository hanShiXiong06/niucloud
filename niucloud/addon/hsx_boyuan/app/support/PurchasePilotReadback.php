<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

/** Read-only resolution for a production PUR dialect: the independent purchase list omits head_id,
 * but the CREATE line contains its head_id and is itself referenced by both later reads.
 * The operator cannot supply or replace these facts. No guessed head/line identifiers. */
final class PurchasePilotReadback
{
    public static function saved(int $site,array $task,array $request,array $prior,?string $root=null): array
    {
        if($site!==ProductionPurchasePilot::SITE || ($task['mode']??'')!=='production' || empty($task['operation_id']) ||
            ProductionPurchasePilot::operation($request['lines'][0]['imei']??null)!==$task['operation_id'] ||
            (int)($task['id']??0)<1 || empty($task['connection_key'])) self::missing();
        $key=$task['connection_key'];$trace='T'.$site.'-'.$task['id'];$root=$root??ExchangeJournal::root();
        $seen=[];$cursor='';
        for($i=0;$i<12;$i++) {
            $page=ExchangeJournal::forTask($root,$site,'production',$key,$trace,$cursor);
            foreach ($page['rows'] as $r) {
                if(($r['service']??'')!=='boyuan.biz.bill.purchase.generate') continue;
                $call=$r['call_id']; if(isset($seen[$call])) continue;
                if((int)($r['task_id']??0)!==(int)$task['id'] || ($r['stage']??null)!=='result' || ($r['delivery']??null)!=='response_received'
                    || ($r['gateway_reply']??'')!=='accepted_envelope' || (int)($r['http_status']??0)!==200) self::missing();
                $seen[$call]=$r;
            }
            $cursor=$page['next'];if($cursor==='') break;
        }
        if($cursor!=='' || count($seen)!==1) self::missing();$r=reset($seen);
        $body=!empty($r['response_detail'])?ExchangeJournal::detail($root,$site,'production',$key,$r['journal_day'],$r['call_id'],'response')['payload']:($r['payload']??null);
        if(!is_array($body) || ($body['return_code']??null)!=='SUCCESS') self::missing();
        return self::receipt($request,$prior,$task['external_no']??'',(array)($body['api_response']['result']??[]));
    }

    public static function receipt(array $request,array $prior,string $no,array $body): array
    {
        $h=$body['head']??[];$r=$body['product_details']['add_details']??[];$f=$request['biz']['head']??[];$lines=$request['lines']??[];$p=$prior['provider_result']??[];
        if(($prior['created']??false)!==true || $no==='' || ($prior['external_no']??null)!==$no || ($p['bill_id']??null)!==$no || ($p['bill_type']??null)!=='PUR'
            || !self::uuid($prior['external_id']??null) || ($p['id']??null)!==$prior['external_id'] || ($h['id']??null)!==$p['id'] || ($h['bill_id']??null)!==$no
            || ($h['bill_type']??null)!=='PUR' || ($h['big_gsdm']??null)!==ProductionPurchasePilot::ACCOUNT || ($h['third_no']??null)!==($request['third_no']??null)
            || empty($f['third_no']) || $f['third_no']!==$h['third_no'] || ($h['business_date']??null)!==($f['business_date']??null) ||
            ($h['supplier_id']??null)!==($f['supplier_id']??null) || ($h['warehouse_id']??null)!==($f['warehouse_id']??null)
            || empty($request['selection']['company_id']) || ($h['company_id']??null)!==$request['selection']['company_id']
            || ($h['cash_term']??null)!==0 || !self::one($h['amounts']??null) || !is_array($r) || array_keys($r)!==[0] || array_keys($lines)!==[0]) self::missing();
        $r=$r[0];$line=$lines[0];$imei=$line['imei']??null;
        if (ProductionPurchasePilot::operation($imei)===null || (ProductionPurchasePilot::pricerOperation(ProductionPurchasePilot::operation($imei)) &&
            !ProductionPurchasePilot::pricerIntent($request,$imei))) self::missing();
        if(!self::uuid($r['id']??null) || ($r['head_id']??null)!==$h['id'] || ($r['bill_id']??null)!==$no || ($r['big_gsdm']??null)!==$h['big_gsdm']
            || ($r['company_id']??null)!==$h['company_id'] || ($r['warehouse_id']??null)!==$h['warehouse_id'] || ($r['product_id']??null)!==($line['product_id']??null)
            || ($line['imei']??null)!==$imei || !in_array($r['imei']??null,[$imei,'***'.substr($imei,-4)],true)
            || !self::one($r['qty']??null) || !self::one($r['unit_price']??null) || !self::one($r['amounts']??null)
            || !in_array($r['seq_no']??null,[1,'1'],true)) self::missing();
        // A masked IMEI is not an identity proof: the subsequent serial query MUST match all 15 digits.
        return ['head_id'=>$h['id'],'line_id'=>$r['id'],'bill_id'=>$no,'company_id'=>$h['company_id'],'seq_no'=>1,'imei'=>$imei,'operation_id'=>ProductionPurchasePilot::operation($imei)];
    }
    private static function one($v): bool { return in_array($v,[1,1.0,'1','1.0','1.00'],true); }
    private static function uuid($v): bool { return is_string($v) && preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/Di',$v)===1; }
    private static function missing(): void { throw new Problem('PURCHASE_CREATE_LINK_UNVERIFIABLE','创建回执和查询之间缺少唯一、匹配的正式采购单头/行证据。仅保留原单号，不重建','unknown'); }
}
