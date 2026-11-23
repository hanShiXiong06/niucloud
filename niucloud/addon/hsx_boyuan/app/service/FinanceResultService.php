<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\Contract;
use think\facade\Db;

/** Local read model for ERP finance detail. Never queries the provider or changes an accounting state. */
final class FinanceResultService
{
    public function links(int $site,string $direction,array $facts,array $c,bool $includeOriginalPayment=false): array
    {
        if($site<=0 || !in_array($direction,['payable','receivable'],true)) return [];
        $ap=$direction==='payable'; $kind=$ap?'purchase':'sale'; $p=$c['profiles'][$c['mode']];
        $result=[]; $cache=[]; $settlementKeys=[];
        foreach($facts as $f) foreach($f['settlements']??[] as $sid) if((int)$sid>0) $settlementKeys[]='purchase-payment:settlement:'.(int)$sid;
        $payments=[];
        if($ap && $settlementKeys) {
            $payments=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('mode',$c['mode'])->where('connection_key',$p['connection_key'])
                ->where('kind',PaymentSyncService::KIND)->whereIn('fact_key',array_unique($settlementKeys))
                ->field('id,fact_key,kind,business_no,status,external_no,snapshot_json,request_json,result_json,update_at')->select()->toArray();
            $payments=array_column($payments,null,'fact_key');
        }
        foreach($facts as $financeId=>$f) {
            $id=(int)$f['document_id'];$no=(string)$f['number']; if($id<=0 || $no==='') continue;
            $key=$id.':'.$no;
            $rows=$cache[$key]??= (new DocumentResultService())->rows($site,$kind,$id,$no,$c,true);
            $matched=[];$verifiedPurchaseNos=[];
            foreach($rows as $doc) {
                if(empty($doc['task_id'])) continue;
                if($ap && ($doc['device_lines']['purchase_line:'.$f['line_id']]??null)!=='asset:'.$f['asset_id']) continue;
                $parent=(int)$doc['task_id'];$matched[$parent]=$doc['snapshot_hash'];
                if($ap && $doc['status']==='succeeded' && !empty($doc['external_no'])) $verifiedPurchaseNos[$parent]=(string)$doc['external_no'];
                unset($doc['device_lines'],$doc['snapshot_hash']);
                $doc['provider_label']=$ap?'博远采购单':'博远销售单';
                if($ap) $doc['trace_ref']='T'.$site.'-'.$parent;
                $doc['receipt_details']=self::details($doc['provider_result']??[]); unset($doc['provider_result']);
                $result[$financeId][]=$doc;
            }
            // A paused connection can read its history. A completely disabled, unused integration has no UI.
            if(!$matched && empty($c['enabled']) && !($ap && $includeOriginalPayment && !empty($f['settlements']))) continue;
            if(!$matched) $result[$financeId][]=self::note($kind.'_none',$ap?'博远采购单':'博远销售单','尚无关联单号');
            if(!$ap) {
                // ERP's receivable detail deliberately passes no voucher IDs. Join via the original receivable;
                // an empty hint does not mean that finance has not already registered a receipt.
                $cash=OriginalSaleCashReceipts::rows($site,$id,$no,$c,false,(int)$financeId);
                if($cash)$result[$financeId]=array_merge($result[$financeId]??[],$cash);
                else $result[$financeId][]=self::note('receipt_none','博远收款单','暂无收款回执');
                continue; // A sale receipt is not proof of a cash receipt.
            }
            $sids=array_values(array_unique(array_map('intval',$f['settlements']??[])));
            foreach($sids as $sid) {
                $payment=$payments['purchase-payment:settlement:'.$sid]??null;
                if(!$payment) {
                    $row=self::missingPayment($sid,$c);
                    // Match by this payable's original device line, never by purchase number, latest task or amount.
                    // If the line does not have exactly one task, leave it informational. Capture itself
                    // checks every allocation of the voucher; it cannot register a partial/mixed voucher.
                    if(count($matched)===1) {
                        $parent=(int)array_key_first($matched);
                        $row['task_id']=$parent;
                        $row['query']=self::paymentPanel($parent,$sid);
                    }
                    $result[$financeId][]=$row; continue;
                }
                $src=Contract::decode($payment['snapshot_json']);$res=Contract::decode($payment['result_json']);$pid=(int)($src['source_task_id']??0);
                if(!isset($matched[$pid]) || $matched[$pid]!==($src['source_snapshot_hash']??'') || ($src['source_document_id']??'')!=='purchase:'.$id ||
                    ($src['source_document_no']??'')!==$no || (int)($src['settlement_id']??0)!==(int)$sid || ($src['settlement_no']??'')!==$payment['business_no'] ||
                    (Contract::decode($payment['request_json'])['schema']??'')!==PaymentSyncService::SCHEMA) {
                    $result[$financeId][]=self::unmatchedPayment($sid);continue;
                }
                $found=false;
                foreach($src['lines']??[] as $line) {
                    if((int)($line['payable_id']??0)!==(int)$financeId || ($line['source_line_id']??'')!=='purchase_line:'.$f['line_id'] || ($line['device_ref']??'')!=='asset:'.$f['asset_id']) continue;
                    if(isset($f['applications'][$sid]) && (($line['amount']??null)!==$f['applications'][$sid]['amount'] ||
                        ($src['settlement_no']??null)!==$f['applications'][$sid]['number'] || ($src['amount']??null)!==$f['applications'][$sid]['voucher_amount'])) continue;
                    $registered=PaymentSyncService::isRegistered($payment) && ($res['created']??false)===true && ($res['payment_registered']??false)===true && ($res['verification_level']??'')==='payment_create_receipt_matched'
                        && $payment['external_no']!=='' && ($res['external_no']??null)===$payment['external_no'];
                    [$state,$label]=$registered?['registered','已登记']:match($payment['status']){
                        'awaiting_confirmation'=>['awaiting_confirmation','待提交'],'pending','processing'=>['processing','处理中'],default=>['needs_review','待核对']};
                    $row=self::note('payment_'.$payment['id'],'博远付款单',$label,$state);
                    if($state==='needs_review') $row['issue_code']=$res['last_issue']['code']??'';
                    if($registered){
                        $row['external_no']=$payment['external_no'];$row['tone']='success';
                        // This projection reads only the payment creation receipt. Neither a flag
                        // (including an absent legacy flag) nor a payment bill verifies an allocation.
                        $po=$verifiedPurchaseNos[$pid]??'';
                        $row['pc_next_step']=($po!=='' && $po===($src['external_purchase_no']??null) ? '对应采购单 '.$po.'。' : '').'博远原采购核销：待核对。';
                        $head=$res['provider_result']??[];
                        if(is_array($head) && ($head['bill_id']??null)===$row['external_no'] && is_string($res['external_id']??null) && ($head['id']??null)===$res['external_id'])
                            $row['receipt_details']=self::details(\addon\hsx_boyuan\app\support\ReceiptFacts::head($head));
                    }
                    $row['next_step']=$registered?'仅表示博远已登记付款，不代表银行转账或指定核销。':'没有确认付款回执，请查看原任务。';
                    $row['task_id']=$pid;$row['query']=self::paymentPanel($pid,$sid,(int)$payment['id']);
                    $row['payment_task_id']=(int)$payment['id'];$row['trace_ref']='T'.$site.'-'.$payment['id'];$row['settlement_id']=$sid;$row['event_at']=(int)$payment['update_at'];
                    $result[$financeId][]=$row;$found=true;break;
                }
                if(!$found) $result[$financeId][]=self::unmatchedPayment($sid);
            }
            if(!$sids) {
                $note=self::note('payment_none','博远付款单','未关联本站付款流水');
                $note['next_step']='按本机原应付核对本站付款记录，不能用采购单号代替付款回执。';
                $result[$financeId][]=$note;
            }
        }
        return $result;
    }

    private static function missingPayment(int $sid,array $c):array
    {
        $row=self::note('payment_none_'.$sid,'博远付款单','本站已登记，未建博远付款任务','not_dispatched');
        $row['settlement_id']=$sid;
        $row['pc_next_step']='博远原采购核销：待核对。请先查这张已有凭证，不要重复付款。';
        $row['next_step']='本站付款已登记，不要再向客户打款。请先核实原采购，再从原单同步这张已有凭证。';
        return $row;
    }

    private static function paymentPanel(int $purchaseTask,int $settlement,int $paymentTask=0):array
    {
        return ['detail_extension'=>'hsx_boyuan','kind'=>'payment','purchase_task_id'=>$purchaseTask,
            'settlement_id'=>$settlement,'payment_task_id'=>$paymentTask];
    }
    private static function unmatchedPayment(int $sid):array
    {
        $row=self::note('payment_mismatch_'.$sid,'博远付款单','付款任务关联待核对','needs_review');
        $row['tone']='warning';$row['settlement_id']=$sid;
        $row['next_step']='已有付款任务，但原采购、设备或核销明细未能匹配，请查原任务，不要重提或重复付款。';
        return $row;
    }

    private static function details(array $head): array
    {
        $out=[];
        foreach(['bill_type'=>'单据类型','business_date'=>'业务日期','audit_status'=>'审核状态（博远值）','amounts'=>'回执金额','un_settle_amounts'=>'回执未结金额'] as $field=>$label)
            if(isset($head[$field]) && is_scalar($head[$field])) $out[]=['label'=>$label,'value'=>(string)$head[$field]];
        if(isset($head['cash_term']) && in_array($head['cash_term'],[0,1],true)) $out[]=['label'=>'单据结算方式','value'=>$head['cash_term']===1?'现结':'挂账'];
        return $out;
    }

    private static function note(string $key,string $label,string $labelStatus,string $status='not_recorded'): array
    {
        return ['key'=>'boyuan_'.$key,'provider_label'=>$label,'status'=>$status,'status_label'=>$labelStatus,'tone'=>'info','external_no'=>'','next_step'=>'','task_id'=>0,'pc_route'=>'','query'=>[]];
    }
}
