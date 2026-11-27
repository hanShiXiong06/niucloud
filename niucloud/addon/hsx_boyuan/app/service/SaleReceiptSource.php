<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\gateway\WholesaleGateway;
use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;

/** The financial fact is the original cashier's confirmed voucher, not the employee pressing sync. */
final class SaleReceiptSource
{
    public static function refs(array $event): array
    {
        $v=$event['payload']??[]; $s=(int)($v['settlement_id']??0); $targets=$v['targets']??[];
        if(($event['event_name']??'')!=='erp.settlement.completed.v1' || ($event['aggregate_type']??'')!=='settlement'
            || $s<1 || (int)($event['aggregate_id']??0)!==$s || ($v['cash_direction']??'')!=='in' || ($v['settlement_type']??'')!=='receipt'
            || !is_array($targets) || count($targets)!==1) throw new Problem('RECEIPT_SCOPE','只可同步一张已确认、完全分配给同一原销售应收的收款凭证；不能拆分或重复登记');
        $t=$targets[0]??[]; $sale=(int)($t['source_id']??0);$ar=(int)($t['target_id']??0);
        if($sale<1 || $ar<1 || ($t['source_type']??'')!=='sale' || ($t['target_type']??'')!=='receivable'
            || ($t['source_document_ref']??'')!=='sale:'.$sale || !empty($t['source_line_ref'])
            || self::money($v['amount']??null)!==self::money($t['applied_amount']??null))
            throw new Problem('RECEIPT_SOURCE','原收款及其销售应收分配不匹配；不推送部分或跨单金额');
        return [$s,$sale,$ar];
    }

    private static function money($v):string
    {
        if((!is_string($v) && !is_float($v) && !is_int($v)) || !preg_match('/^(0|[1-9]\d{0,9})(?:\.\d{1,2})?$/D',(string)$v) || (float)$v<=0)
            throw new Problem('RECEIPT_AMOUNT','已确认凭证金额必须为正数且最多两位小数');
        [$w,$d]=array_pad(explode('.',(string)$v),2,'');return $w.'.'.str_pad($d,2,'0');
    }

    public static function snapshot(int $site,string $key,array $e,bool $lock):array
    {
        [$id,$sale,$ar]=self::refs($e);$p=$e['payload'];$t=$p['targets'][0];
        $q=static fn(string $n)=>Db::name($n)->where('site_id',$site)->lock($lock);
        $v=$q('erp_settlement')->where('id',$id)->find();$a=$q('erp_receivable')->where('id',$ar)->find();
        $o=$q('erp_sale_order')->where('id',$sale)->find();
        $links=$q('erp_settlement_link')->where('settlement_id',$id)->select()->toArray();$l=$links[0]??[];
        if(!$v || !$a || !$o || count($links)!==1 || ($l['target_type']??'')!=='receivable' || (int)$l['target_id']!==$ar
            || $v['status']!=='confirmed' || $v['cash_direction']!=='in' || $v['settlement_type']!=='receipt'
            || (int)$v['operator_uid']<1 || (int)$v['capital_account_id']<1 || (int)$v['capital_account_id']!==(int)$p['capital_account_id']
            || (int)$v['confirmed_at']<=0 || (int)$v['confirmed_at']!==(int)$p['confirmed_at'] || $v['settlement_no']!==$p['settlement_no']
            || (int)$v['party_id']<1 || (int)$v['party_id']!==(int)$p['party_id'] || (int)$o['party_id']!==(int)$v['party_id'] || (int)$a['party_id']!==(int)$v['party_id']
            || $o['status']!=='completed' || $o['settle_method']!=='挂账' || !in_array($o['sale_channel_key']?:$o['sale_channel'],['erp_peer','wholesale','批发'],true)
            || $a['source_type']!=='sale' || (int)$a['source_id']!==$sale || $a['receivable_no']!==$t['target_no']
            || $a['source_no']!==$o['sale_no'] || $t['source_no']!==$o['sale_no'] || !in_array($a['status'],['settled','partial'],true)
            || self::money($v['amount'])!==self::money($p['amount']) || self::money($l['applied_amount'])!==self::money($v['amount'])
            || Contract::cents($a['settled_amount'])<Contract::cents($v['amount']))
            throw new Problem('RECEIPT_CHANGED','本地收款凭证、原销售或应收分配有变化，请核对原单；不创建其他凭证');
        $parent=$q('hsx_boyuan_task')->where(['connection_key'=>$key,'kind'=>WholesaleSyncService::KIND,'mode'=>'test',
            'fact_key'=>'sale:'.$sale.':wholesale-credit','business_no'=>$o['sale_no']])->find();
        if(!$parent || $parent['status']!=='succeeded' || $parent['side_effect']!=='applied' || !empty($parent['resolution']))
            throw new Problem('RECEIPT_SALE_UNVERIFIED','请先在同一博远测试账套核验原挂账出库单与串号');
        $s=Contract::decode($parent['snapshot_json']);$r=Contract::decode($parent['request_json']);$result=Contract::decode($parent['result_json']);
        $proof=$result['verification']??[];$no=$parent['external_no'];$lines=OriginalSaleReceipts::confirmedLines($no,$s,$result['receipt']??[],$proof);
        $head=$r['biz']['head']??[];
        if(!$lines || ($s['source_document_id']??'')!=='sale:'.$sale || ($s['source_document_no']??'')!==$o['sale_no'] || ($s['party']['ref']??'')!=='party:'.$v['party_id']
            || ($r['schema']??'')!==WholesaleGateway::SCHEMA || ($head['cash_term']??null)!==0 || empty($r['company_id']) || $r['company_id']!==($proof['company_id']??null) || empty($head['cust_id']))
            throw new Problem('RECEIPT_SALE_UNVERIFIED','博远销售回执没有对上原客户、公司及逐台串号，不能登记收款');
        (new WholesaleSyncService())->checkOriginalLines($site,$key,$s,false);
        return ['event_id'=>$e['event_id'],'source_task_id'=>(int)$parent['id'],'source_snapshot_hash'=>hash('sha256',$parent['snapshot_json']),
            'sale_id'=>$sale,'sale_no'=>$o['sale_no'],'sale_external_no'=>$no,'sale_external_id'=>$proof['external_id'],'company_id'=>$r['company_id'],'customer_id'=>$head['cust_id'],
            'lines'=>$lines,'receivable_id'=>$ar,'receivable_no'=>$a['receivable_no'],'settlement_id'=>$id,'settlement_no'=>$v['settlement_no'],'amount'=>$v['amount'],
            'operator_uid'=>(int)$v['operator_uid'],'capital_account_id'=>(int)$v['capital_account_id'],'confirmed_at'=>(int)$v['confirmed_at']];
    }
}
