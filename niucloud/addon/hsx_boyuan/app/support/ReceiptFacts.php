<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;

/** Persist only business receipt fields, not the transport envelope, credentials or personal data. */
final class ReceiptFacts
{
    /** Last business issue only. Never persist exception traces, raw HTTP or a history of attempts here. */
    public static function issue(\Throwable $error,string $stage): array
    {
        $code=$error instanceof Problem?$error->reason:'LOCAL_ERROR';
        return ['code'=>preg_match('/^[A-Z][A-Z0-9_]{0,79}$/D',$code)?$code:'UNCLASSIFIED',
            'stage'=>in_array($stage,['prepare','submit','verify'],true)?$stage:'prepare'];
    }

    public static function head(array $head): array
    {
        $out=[];
        $fields=['id'=>36,'bill_id'=>32,'bill_type'=>24,'business_date'=>20];
        if(($head['bill_type']??'')==='PUR') $fields+=['sales_id'=>36,'sales_name'=>32];
        foreach($fields as $key=>$max) {
            $v=$head[$key]??null;
            if(is_string($v) && $v!=='' && strlen($v)<=$max && !preg_match('/[[:cntrl:]]/',$v)) $out[$key]=$v;
        }
        foreach(['audit_status','cash_term'] as $key) {
            $v=$head[$key]??null;
            if((is_int($v)||is_string($v)) && preg_match('/^\d{1,3}$/D',(string)$v)) $out[$key]=(int)$v;
        }
        foreach(['amounts','un_settle_amounts'] as $key) {
            $v=$head[$key]??null;
            if((is_string($v)||is_int($v)||is_float($v)) && preg_match('/^-?\d{1,12}(?:\.\d{1,2})?$/D',(string)$v)) $out[$key]=(string)$v;
        }
        return $out; // Omitted / null is unknown, not zero. Returned amounts are not local ledger balances.
    }

    /** Only call with matched rows for the same purchase; never substitute names or IDs from the request. */
    public static function observedBuyer(array $sent,array $rows): array
    {
        $ids=[]; $names=[]; $missing=!$rows; $conflict=false;
        foreach($rows as $row) {
            $actual=self::head(['bill_type'=>'PUR']+array_intersect_key($row,['sales_id'=>true,'sales_name'=>true]));
            foreach(['sales_id','sales_name'] as $key) {
                $value=$actual[$key]??'';
                if($value==='') {
                    if($key==='sales_id') $missing=true;
                    if(isset($row[$key]) && $row[$key]!=='') $conflict=true; // malformed, not a positive observation
                    continue;
                }
                if($key==='sales_id') $ids[]=$value; else $names[]=$value;
                if(($sent[$key]??'')!=='' && $sent[$key]!==$value) $conflict=true;
            }
        }
        $ids=array_unique($ids); $names=array_unique($names);
        return ['status'=>$conflict || count($ids)>1 || count($names)>1?'mismatch':($missing?'not_returned':'matched'),
            'sales_id'=>count($ids)===1?reset($ids):'','sales_name'=>count($names)===1?reset($names):''];
    }

    /** Second whitelist at the storage/read boundary. Do not preserve the full vendor search rows. */
    public static function buyerEvidence(array $evidence): array
    {
        if(($evidence['source']??'')!=='purchase_details' || !in_array($evidence['status']??'', ['matched','mismatch','not_returned'],true)) return [];
        $fields=self::head(['bill_type'=>'PUR']+array_intersect_key($evidence,['sales_id'=>true,'sales_name'=>true]));
        $status=$evidence['status'];
        if($status==='matched' && !isset($fields['sales_id'])) $status='not_returned';
        return ['source'=>'purchase_details','status'=>$status,'sales_id'=>$fields['sales_id']??'','sales_name'=>$fields['sales_name']??''];
    }

    public static function purchaseBuyerReceipt(array $sent,array $result): array
    {
        $info=['receipt_status'=>'not_sent','receipt_source'=>'','returned_name'=>''];
        if(empty($result['external_no'])) return $info;
        if(empty($sent['sales_id'])) return array_replace($info,['receipt_status'=>'not_supplied']);
        $create=self::observedBuyer($sent,[(array)($result['provider_result']??[])]);
        $read=self::buyerEvidence((array)($result['evidence']['pricing_buyer']??[]));
        $previous=self::buyerEvidence((array)($result['evidence']['previous_buyer_conflict']??[]));
        if(($read['status']??'not_returned')==='not_returned' && ($previous['status']??'')==='mismatch') $read=$previous;
        if($read && self::observedBuyer($sent,[$read])['status']==='mismatch') $read['status']='mismatch';
        // Two disagreeing receipts must never be presented as "matched". An absent detail field
        // is not disagreement, and also doesn't turn a create-head echo into independent proof.
        $useRead=$read && ($read['status']==='mismatch' || ($create['status']!=='mismatch' && ($read['status']==='matched' || $create['status']==='not_returned')));
        $chosen=$useRead?$read:$create;
        $status=$chosen['status'];
        if(($result['last_issue']['stage']??'')==='verify' && ($result['last_issue']['code']??'')==='PURCHASE_BUYER_MISMATCH')
            return ['receipt_status'=>'mismatch','receipt_source'=>'purchase_details','returned_name'=>''];
        return ['receipt_status'=>$status,'receipt_source'=>$useRead?'purchase_details':(!empty($result['provider_result'])?'create_head':''),
            'returned_name'=>$chosen['sales_name']];
    }
}
