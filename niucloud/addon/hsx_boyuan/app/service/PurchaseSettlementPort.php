<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\{Contract,Problem};
use think\facade\Db;

/** No public API, configuration switch or HTTP-provided evidence. This port reads a single verified batch. */
class PurchaseSettlementPort
{
    /** The only future integration point: return an authenticated read-only normalized evidence reader.
     * No official allocation query has been established. Never return a payment-create response here.
     */
    protected static function reader(): ?\Closure { return null; }

    final public static function forVoucher(array $row): array
    {
        $reader=static::reader();
        if ($reader===null) return (new PurchaseAllocationEvidence())->lookup();
        try {
            $expected=Db::transaction(static fn()=>self::freeze($row));
            return (new PurchaseAllocationEvidence($reader))->lookup($expected);
        } catch (\Throwable $ignored) {
            return ['status'=>'pending','verified'=>false,'reason'=>'source_unverified','currency'=>null,'applied_amount'=>null,'remaining_amount'=>null,'allocations'=>[]];
        }
    }

    private static function freeze(array $row): array
    {
        $site=(int)($row['site_id']??0); $id=(int)($row['id']??0); $mode=$row['mode']??null; $key=Contract::id($row['connection_key']??null);
        self::check($site>0 && $id>0 && in_array($mode,['test','production'],true));
        $scope=['site_id'=>$site,'mode'=>$mode,'connection_key'=>$key];
        $tasks=static fn()=>Db::name('hsx_boyuan_task')->where($scope);
        // Re-read local persisted values; the argument is an identity, not a source of evidence.
        $voucher=$tasks()->where(['id'=>$id,'kind'=>PaymentSyncService::KIND])->find();
        self::check($voucher && PaymentSyncService::isRegistered($voucher));
        $source=Contract::decode($voucher['snapshot_json']);
        self::check(!empty($source['settlement_id']) && $voucher['fact_key']==='purchase-payment:settlement:'.$source['settlement_id']
            && ($source['settlement_no']??null)===$voucher['business_no']);
        $parent=$tasks()->where(['id'=>(int)($source['source_task_id']??0),'kind'=>'purchase','status'=>'succeeded','side_effect'=>'applied'])->find();
        self::check($parent && empty($parent['resolution']));
        $s=Contract::decode($parent['snapshot_json']); $req=Contract::decode($parent['request_json']); $pr=Contract::decode($parent['result_json']);
        $no=Contract::id($parent['external_no']); $external=Contract::id($pr['external_id']??null); $company=Contract::id($req['selection']['company_id']??null);
        self::check(($s['kind']??null)==='purchase' && ($parent['fact_key']??null)===($s['source_document_id']??'').':'.($s['batch_ref']??'')
            && ($s['source_document_no']??null)===$parent['business_no'] && ($req['schema']??null)===PurchaseSyncService::SCHEMA
            && ($req['stage']??null)==='frozen' && ($req['biz']['head']['cash_term']??null)===0 && ($pr['external_no']??null)===$no
            && ($pr['verified']??null)===true && ($pr['verification_level']??null)==='purchase_imei_records_matched'
            && ($pr['evidence']['record_is_effective']??null)===true && ($pr['evidence']['company_id']??null)===$company);
        $lines=$payables=$devices=$principal=$externalLines=[];
        foreach ($s['lines']??[] as $line) {
            $lineId=Contract::id($line['source_line_id']??null); $dev=Contract::id($line['device_ref']??null); $amount=$line['amount']??null;
            self::check(preg_match('/^purchase_line:[1-9]\d*$/D',$lineId) && preg_match('/^asset:([1-9]\d*)$/D',$dev,$m)
                && Contract::cents($amount)>0 && !isset($lines[$lineId]) && !isset($devices[$dev]));
            $lines[$lineId]=['source_line_id'=>$lineId,'device_ref'=>$dev,'amount'=>$amount]; $devices[$dev]=true;
        }
        self::check(count($lines)>0 && count($lines)<=100 && count($s['financial_items']??[])===count($lines) && count($pr['lines']??[])===count($lines));
        foreach ($s['financial_items'] as $item) {
            $l=$item['source_line_id']??''; $f=$item['source_financial_item_id']??'';
            self::check(is_string($f) && preg_match('/^payable:([1-9]\d*)$/D',$f,$m) && isset($lines[$l]) && !isset($lines[$l]['payable_id'])
                && !isset($payables[(int)$m[1]]) && ($item['financial_item_type']??null)==='payable'
                && ($item['source_document_id']??null)===$s['source_document_id'] && ($item['device_ref']??null)===$lines[$l]['device_ref']
                && ($item['amount']??null)===$lines[$l]['amount']);
            $pid=(int)$m[1]; $payables[$pid]=$l; $lines[$l]['payable_id']=$pid; $principal[$pid]=Contract::cents($item['amount']);
        }
        foreach ($pr['lines'] as $verified) {
            $l=$verified['source_line_id']??''; $el=Contract::id($verified['external_line_id']??null);
            self::check(isset($lines[$l]) && !isset($lines[$l]['external_line_id']) && !isset($externalLines[$el])
                && ($verified['device_ref']??null)===$lines[$l]['device_ref'] && ($verified['external_document_id']??null)===$external);
            $lines[$l]['external_line_id']=$el; $externalLines[$el]=true;
        }
        $total=Contract::amount(array_sum($principal));
        $expected=['site_id'=>$site,'mode'=>$mode,'connection_key'=>$key,'company_id'=>$company,
            'source_document_id'=>$s['source_document_id'],'source_document_no'=>$parent['business_no'],'external_purchase_id'=>$external,'external_purchase_no'=>$no,
            'purchase_amount'=>$total,'currency'=>'CNY','lines'=>array_values($lines),'payments'=>[]];
        $settlements=self::localVouchers($site,$payables,$lines);
        self::check(isset($settlements[(int)($source['settlement_id']??0)]));
        $numbers=array_map(static fn($sid)=>'purchase-payment:settlement:'.$sid,array_keys($settlements));
        $registered=$tasks()->where('kind',PaymentSyncService::KIND)->whereIn('fact_key',$numbers)->select()->toArray();
        self::check(count($registered)===count($settlements));
        $seen=[]; $includesOriginal=false;
        foreach ($registered as $task) {
            [$payment,$account]=self::matchedPayment($task,$parent,$expected,$settlements,$payables,$lines);
            $sid=$payment['settlement_id'];
            self::check(!isset($seen[$sid]) && (!isset($expected['account_id']) || $account===$expected['account_id']));
            $seen[$sid]=true; $expected['account_id']=$account; $expected['payments'][]=$payment;
            if ((int)$task['id']===$id) $includesOriginal=true;
        }
        self::check($includesOriginal);
        return $expected;
    }

    /** Include every confirmed outgoing payment to any payable in this batch, not just the triggering voucher. */
    private static function localVouchers(int $site,array $payables,array $lines): array
    {
        $pids=array_keys($payables); $links=Db::name('erp_settlement_link')->alias('l')
            ->leftJoin([Db::name('erp_settlement')->getTable()=>'s'],'s.site_id=l.site_id AND s.id=l.settlement_id')
            ->where(['l.site_id'=>$site,'l.target_type'=>'payable'])->whereIn('l.target_id',$pids)
            ->field('l.target_id,l.applied_amount,s.id,s.settlement_no,s.amount,s.status,s.cash_direction,s.settlement_type,s.confirmed_at')->select()->toArray();
        $settlements=$sums=[];
        foreach ($links as $link) {
            self::check(!empty($link['id'])); if ($link['status']!=='confirmed') continue;
            $pid=(int)$link['target_id']; $sid=(int)$link['id']; $applied=Contract::cents($link['applied_amount']);
            self::check($applied>0 && $link['cash_direction']==='out' && $link['settlement_type']==='payment'
                && (int)$link['confirmed_at']>0 && !isset($settlements[$sid]['links'][$pid]));
            $settlements[$sid]['number']=$link['settlement_no']; $settlements[$sid]['amount']=$link['amount']; $settlements[$sid]['links'][$pid]=$link['applied_amount'];
            $sums[$pid]=($sums[$pid]??0)+$applied;
        }
        $current=Db::name('erp_payable')->where('site_id',$site)->whereIn('id',$pids)->field('id,source_type,source_id,amount,settled_amount,status')->select()->toArray();
        self::check(count($current)===count($pids));
        foreach ($current as $item) {
            $pid=(int)$item['id']; $line=$lines[$payables[$pid]];
            self::check(in_array($item['status'],['pending','partial','settled'],true) && $item['source_type']==='purchase_asset'
                && 'asset:'.$item['source_id']===$line['device_ref'] && $item['amount']===$line['amount'] && Contract::cents($item['settled_amount'])===($sums[$pid]??0));
        }
        return $settlements;
    }

    private static function matchedPayment(array $task,array $parent,array $expected,array $local,array $payables,array $lines): array
    {
        $s=Contract::decode($task['snapshot_json']); $r=Contract::decode($task['result_json']); $q=Contract::decode($task['request_json']);
        $sid=(int)($s['settlement_id']??0); $company=$expected['company_id']; $h=$r['response']['head']??[];
        // big_gsdm is the ledger. The payment account_id is a cash account and gsdm a company code.
        $book=Contract::id($h['big_gsdm']??null);
        self::check(isset($local[$sid]) && PaymentSyncService::isRegistered($task) && empty($task['resolution'])
            && $task['fact_key']==='purchase-payment:settlement:'.$sid && $task['business_no']===$local[$sid]['number']
            && ($s['kind']??null)===PaymentSyncService::KIND && ($s['scope']??null)==='single_purchase_batch' && ($s['currency']??null)==='CNY'
            && ($s['settlement_no']??null)===$task['business_no'] && ($s['amount']??null)===$local[$sid]['amount']
            && (int)($s['source_task_id']??0)===(int)$parent['id'] && ($s['source_snapshot_hash']??null)===hash('sha256',$parent['snapshot_json'])
            && ($s['source_request_hash']??null)===hash('sha256',$parent['request_json'])
            && ($q['stage']??null)==='frozen' && ($q['schema']??null)===PaymentSyncService::SCHEMA && ($q['connection_key']??null)===$expected['connection_key']
            && ($q['source_snapshot_hash']??null)===$s['source_snapshot_hash'] && (int)($q['source_settlement_id']??0)===$sid
            && (int)($q['source_task_id']??0)===(int)$parent['id'] && ($q['company_id']??null)===$company
            && (!isset($q['target_account']) || $q['target_account']===$book)
            && ($q['amount']??null)===$s['amount'] && ($r['amount']??null)===$s['amount'] && ($r['created']??null)===true && ($r['payment_registered']??null)===true
            && ($r['verification_level']??null)==='payment_create_receipt_matched' && ($r['external_no']??null)===$task['external_no']
            && ($h['bill_id']??null)===$task['external_no'] && ($h['id']??null)===($r['external_id']??null)
            && ($h['company_id']??null)===$company && ($h['bill_type']??null)==='PAYMENT');
        foreach (['source_document_id','source_document_no','external_purchase_id','external_purchase_no','company_id'] as $field) self::check(($s[$field]??null)===$expected[$field]);
        $applied=[]; $applications=[];
        foreach ($s['lines']??[] as $item) {
            $pid=(int)($item['payable_id']??0); $l=$payables[$pid]??'';
            self::check(isset($lines[$l],$local[$sid]['links'][$pid]) && !isset($applied[$pid])
                && $item['source_line_id']===$l && $item['device_ref']===$lines[$l]['device_ref'] && $item['external_line_id']===$lines[$l]['external_line_id']
                && $item['external_document_id']===$expected['external_purchase_id'] && $item['payable_amount']===$lines[$l]['amount']
                && $item['amount']===$local[$sid]['links'][$pid]);
            $applied[$pid]=Contract::cents($item['amount']); $applications[]=['payable_id'=>$pid,'amount'=>$item['amount']];
        }
        self::check(count($applied)===count($local[$sid]['links']) && array_sum($applied)===Contract::cents($s['amount']));
        return [['settlement_id'=>$sid,'settlement_no'=>$task['business_no'],'external_payment_id'=>Contract::id($r['external_id']??null),
            'external_payment_no'=>Contract::id($task['external_no']??null),'voucher_amount'=>$s['amount'],'applications'=>$applications],$book];
    }

    private static function check(bool $valid): void
    {
        if (!$valid) throw new Problem('SETTLEMENT_SOURCE_UNVERIFIED','采购批次或完整付款分摊证据不足');
    }
}
