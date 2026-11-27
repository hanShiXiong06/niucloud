<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\support;

/** Separate the vendor document from proof of allocation to the original purchase. Never sum local balances here. */
final class ManualReceipt
{
    public static function normalize(array $reply, array $prior, bool $payment): array
    {
        $s = (string)($reply['status'] ?? 'unknown');
        if (!in_array($s, ['waiting','linked','confirmed','blocked','unknown'], true)) $s='unknown';
        $no = trim((string)($reply['external_no'] ?? ''));
        $id = max(0,(int)($reply['vendor_task_id'] ?? 0));
        $oldNo = (string)($prior['external_no'] ?? ''); $oldId = (int)($prior['vendor_task_id'] ?? 0);
        $conflict = ($oldNo!=='' && $no!=='' && $oldNo!==$no) || ($oldId && $id && $oldId!==$id);
        if ($conflict) {
            $s='unknown'; $id=$oldId; $no=$oldNo;
            $reply=['reason'=>'RECEIPT_CONFLICT','message'=>'回执与原关联不一致，请核对原单，不能重新发送'];
        }
        if ($s==='confirmed' && ($no==='' || !$id)) $s='unknown';
        if ($s==='linked' && !$id) $s='unknown';
        $receipt=['status'=>$s,'vendor_task_id'=>$id ?: $oldId,'external_no'=>$no!==''?$no:$oldNo,
            'reason'=>mb_substr((string)($reply['reason']??''),0,100),
            'message'=>mb_substr((string)($reply['message']??''),0,255)];
        if ($payment) {
            $a=(array)($reply['allocation']??[]);
            $verified=!$conflict && $s==='confirmed' && ($a['verified']??false)===true && in_array($a['status']??'', ['unsettled','partial','settled'],true)
                && is_string($a['applied_amount']??null) && preg_match('/^\d+\.\d{2}$/D',$a['applied_amount'])
                && is_string($a['remaining_amount']??null) && preg_match('/^\d+\.\d{2}$/D',$a['remaining_amount'])
                && (($a['status']==='settled') === ($a['remaining_amount']==='0.00'))
                && (($a['status']==='unsettled') === ($a['applied_amount']==='0.00'));
            $receipt['allocation']=$verified ? array_intersect_key($a,array_flip(['verified','status','applied_amount','remaining_amount']))
                : ['status'=>'pending','verified'=>false,'applied_amount'=>null,'remaining_amount'=>null];
        }
        return $receipt;
    }

    public static function state(string $vendorStatus): string
    {
        return ['waiting'=>'waiting_source','linked'=>'linked','confirmed'=>'succeeded','blocked'=>'blocked','unknown'=>'unverified'][$vendorStatus] ?? 'unverified';
    }
}
