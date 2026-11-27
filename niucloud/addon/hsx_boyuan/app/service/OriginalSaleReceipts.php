<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\Problem;
use think\facade\Db;

/** Only saved, account-scoped evidence. Opening the drawer never calls a provider. */
final class OriginalSaleReceipts
{
    public static function confirmedLines(string $no,array $snapshot,array $receipt,array $proof): array
    {
        if($no==='' || ($receipt['created']??false)!==true || ($proof['verified']??false)!==true
            || ($proof['verification_level']??null)!=='wholesale_records_matched' || ($proof['external_no']??null)!==$no
            || ($receipt['external_no']??null)!==$no || empty($receipt['external_id']) || $receipt['external_id']!==($proof['external_id']??null)) return [];
        $origin=array_column($snapshot['lines']??[],null,'source_line_id');
        $created=array_column($receipt['lines']??[],null,'source_line_id'); $confirmed=[];
        foreach($proof['lines']??[] as $line) {
            $ref=$line['source_line_id']??''; $original=$origin[$ref]??[]; $received=$created[$ref]??[];
            if(isset($confirmed[$ref]) || empty($line['external_line_id']) || $line['external_line_id']!==($received['external_line_id']??null)
                || empty($line['imei']) || $line['imei']!==($original['imei']??null) || $line['imei']!==($received['imei']??null)
                || empty($line['device_ref']) || $line['device_ref']!==($original['device_ref']??null) || $line['device_ref']!==($received['device_ref']??null)) return [];
            $confirmed[$ref]=array_merge($line,['document_no'=>$no]);
        }
        return count($confirmed)===count($origin) && count($confirmed)===count($created) ? array_values($confirmed) : [];
    }

    /** Only mark a task as already displayed when every verified line is visible on this exact original. */
    public static function displayed(array $row,array $devices): bool
    {
        if(($row['status']??'')!=='succeeded' || ($row['tone']??'')!=='success' || empty($row['external_no'])
            || empty($row['task_id']) || empty($row['verified_device_receipts'])) return false;
        $shown=array_column($devices,null,'item_id');
        foreach($row['verified_device_receipts'] as $receipt) {
            if(!preg_match('/^sale_line:([1-9][0-9]*)$/D',(string)($receipt['source_line_id']??''),$m))return false;
            $item=$shown[$m[1]]??null;
            if(!$item || !$item['imei'] || $item['imei']!==($receipt['imei']??null) || 'asset:'.$item['asset_id']!==($receipt['device_ref']??null)
                || $item['sale_no']!==$row['external_no'] || $item['sale_no']!==($receipt['document_no']??null))return false;
        }
        return true;
    }

    public static function devices(int $site,array $config,array $items,array $verifiedSale,bool $allowPurchase): array
    {
        $mode=$config['mode']; $key=$config['profiles'][$mode]['connection_key'];
        $refs=[]; foreach($items as $item) if((int)($item['origin_purchase_item_id']??0)>0) $refs[]='purchase_line:'.$item['origin_purchase_item_id'];
        $bindings=$tasks=[];
        if($allowPurchase && $refs) {
            $bindings=Db::name('hsx_boyuan_binding')->where('site_id',$site)->where('connection_key',$key)
                ->where('kind','line')->whereIn('local_ref',array_unique($refs))->select()->toArray();
            $ids=array_unique(array_column($bindings,'task_id'));
            if($ids) $tasks=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('mode',$mode)->where('connection_key',$key)
                ->where('kind','purchase')->where('status','succeeded')->whereIn('id',$ids)
                ->field('id,external_no,request_json,result_json,resolution')->select()->toArray();
            $bindings=array_column($bindings,null,'local_ref'); $tasks=array_column($tasks,null,'id');
        }
        return self::project($items,$verifiedSale,$allowPurchase,$bindings,$tasks);
    }

    public static function project(array $items,array $sales,bool $allowPurchase,array $bindings,array $tasks): array
    {
        $out=[]; $byLine=[]; foreach($sales as $sale) $byLine[$sale['source_line_id']][]=$sale;
        foreach($items as $item) {
            $itemId=(int)($item['id']??0); $asset=(int)($item['asset_id']??0); $imei=(string)($item['imei']??'');
            if($itemId<=0 || $asset<=0 || $imei==='') continue;
            $ref='purchase_line:'.(int)($item['origin_purchase_item_id']??0); $pno=''; $sno='';
            $line=['device_ref'=>'asset:'.$asset,'imei'=>$imei,'inbound_line_ref'=>$ref];
            $binding=$bindings[$ref]??[]; $purchase=$tasks[$binding['task_id']??0]??[];
            if($allowPurchase && $purchase && ($purchase['resolution']??'')!=='source_changed'
                && !str_starts_with((string)($purchase['resolution']??''),'[source_invalidated]')) {
                try { WholesaleSyncService::matchInboundReceipt($line,$binding,$purchase); $pno=(string)$purchase['external_no']; }
                catch(Problem|\JsonException $ignored) { /* A different or incomplete receipt is not proof. */ }
            }
            $matches=$byLine['sale_line:'.$itemId]??[];
            if(count($matches)===1) {
                $sale=$matches[0];
                if(($sale['device_ref']??'')===$line['device_ref']
                    && ($sale['imei']??'')===$imei && !empty($sale['external_line_id']) && ($item['status']??'')==='sold')
                    $sno=(string)$sale['document_no'];
            }
            $out[]=['item_id'=>$itemId,'asset_id'=>$asset,'imei'=>$imei,'purchase_no'=>$pno,'sale_no'=>$sno];
        }
        return $out;
    }
}
