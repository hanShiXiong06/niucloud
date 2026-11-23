<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\listener;

use addon\hsx_boyuan\app\service\{ConfigService,DocumentResultService,OriginalPurchaseReceipts,OriginalSaleReceipts,OriginalSaleCashReceipts,PermissionService,SaleReceiptSyncService,WholesaleSyncService};

final class PurchaseActions
{
    public function handle(array $event): array
    {
        $site=(int)($event['site_id']??0);
        $sale=isset($event['sale_id']); $kind=$sale?'sale':'purchase'; $id=(int)($event[$kind.'_id']??0);
        if ($site<=0 || (int)request()->siteId()!==$site || $id<=0) return [];
        $supported=true;
        if($sale) $supported=in_array($event['sale_channel']??'',['erp_peer','wholesale','批发'],true) && ($event['settle_method']??'')==='挂账';
        else foreach ($event['items']??[] as $item) if (($item['item_type']??'device')==='standard') $supported=false;
        try {
            $configService=new ConfigService();
            if (!$configService->installed($site)) return [];
            $config=$configService->get($site); $p=$config['profiles'][$config['mode']];
            if (!in_array($config['mode'],['test','production'],true) || ($p['protocol']??'')!=='openapi_v153' || empty($p['connection_key'])) return [];
            $acl=new PermissionService(); $acl->assertApi('get','boyuan/tasks/<id>');
        } catch (\Throwable $e) { return []; }
        $out=(new DocumentResultService())->rows($site,$kind,$id,(string)($event['business_no']??''),$config,false,$sale);
        $hasTasks=!empty($out[0]['task_id']);
        if(!$supported && !$hasTasks) return [];
        $can=static function($method,$route) use($acl):bool {try {$acl->assertApi($method,$route);return true;}catch(\core\exception\CommonException $ignored){return false;}};
        $configRead=$can('get','boyuan/config');$listRead=$can('get','boyuan/tasks');
        foreach($out as &$row) {
            if(!$configRead || !$listRead) $row['mobile_path']='';
        }
        unset($row);
        $actions=[];
        if(!$sale && ($hasTasks || !empty($config['enabled'])) && !empty($p['probe_at'])
            && $can('get','boyuan/logs') && $can('get','erp/finance/payable/lists') && $can('get','erp/purchase/lists'))
            $actions[]=['key'=>'boyuan_exchange','label'=>'查看本单往来','pc_route'=>'/hsx_boyuan/business','mobile_path'=>'','query'=>['purchase_no'=>(string)$event['business_no']]];
        if(!$sale && !$hasTasks && $supported && ($event['status']??'')==='completed' && !empty($config['enabled']) && !empty($config['purchase_enabled']) &&
            empty($config['paused']) && $can('post','boyuan/tasks/<id>/action')) $actions[]=['key'=>'boyuan_purchase','label'=>'核对并同步博远',
            'pc_route'=>'/hsx_boyuan/purchase','mobile_path'=>$configRead && $listRead ? '/addon/hsx_boyuan/pages/workbench/index':'',
            'query'=>['detail_extension'=>'hsx_boyuan','purchase_source_id'=>$id]];
        if($sale && !$hasTasks && $supported && ($event['status']??'')==='completed' && WholesaleSyncService::dispatchEnabled($config,$p)
            && $can('get','erp/sale/lists') && $can('post','boyuan/wholesale/<id>/submit')
            && (new WholesaleSyncService())->canPrepareSource($site,$id,(string)($event['business_no']??''))) {
            $actions[]=['key'=>'boyuan_sale','label'=>'准备博远出库','pc_route'=>'/hsx_boyuan/wholesale','mobile_path'=>''];
        }
        if(!$sale && $supported && !empty($p['probe_at']) && $can('get','erp/finance/payable/lists')) {
            try {
                $out=array_merge($out,(new OriginalPurchaseReceipts())->paymentRows($site,$id,(string)($event['business_no']??''),$config));
            } catch(\Throwable $e) {
                \think\facade\Log::warning('hsx_boyuan original purchase payment read failed',['site_id'=>$site,'purchase_id'=>$id,'error_type'=>get_class($e)]);
                $out[]=['key'=>'boyuan_original_payment_unavailable','provider_label'=>'博远付款回执','status'=>'unavailable','tone'=>'warning','status_label'=>'暂时无法读取',
                    'next_step'=>'请刷新原单后再核对；读取失败不代表没有付款，请勿重付。'];
            }
        }
        $result=['actions'=>$actions,'results'=>$out];
        if($sale && $supported && ($hasTasks || !empty($config['enabled'])) && $can('get','erp/sale/lists')) {
            $allowPurchase=$can('get','erp/purchase/lists');
            try {
                $lines=OriginalSaleReceipts::devices($site,$config,$event['items']??[],array_merge([], ...array_column($out,'verified_device_receipts')),$allowPurchase);
                $receiptLabel='';
                if($config['mode']==='test' && $can('get','erp/finance/receivable/lists')) {
                    $confirmed=false;foreach($out as $row)if(!empty($row['verified_device_receipts'])){$confirmed=true;break;}
                    $canPrepare=$confirmed && $can('post','erp/finance/receivable/<id>/confirm_receipt') && $can('post','boyuan/receipt-tasks/<id>/submit')
                        && SaleReceiptSyncService::enabled($site,$config,$p);
                    $cash=OriginalSaleCashReceipts::rows($site,$id,(string)$event['business_no'],$config,$canPrepare);
                    $result['results']=array_merge($result['results'],$cash);$receiptLabel=OriginalSaleCashReceipts::label($cash);
                }
                if($lines) $result['sale_evidence']=['provider_label'=>'博远','show_purchase'=>$allowPurchase,'devices'=>$lines,
                    'receipt_label'=>$receiptLabel,'allocation_label'=>'待核对'];
                foreach($result['results'] as &$entry) if(OriginalSaleReceipts::displayed($entry,$lines))
                    $entry['query']+=['sale_record_role'=>'displayed_serial_receipt','original_sale_id'=>$id,'displayed_task_id'=>(int)$entry['task_id']];
                unset($entry);
            } catch(\Throwable $e) {
                \think\facade\Log::warning('hsx_boyuan original sale receipts read failed',['site_id'=>$site,'sale_id'=>$id,'error_type'=>get_class($e)]);
                $result['results'][]=['key'=>'boyuan_sale_evidence_unavailable','provider_label'=>'博远设备回执','status'=>'unavailable','tone'=>'warning',
                    'status_label'=>'待核对','next_step'=>'暂时无法读取设备回执，请刷新原单核对；不要重复出库或收款。'];
            }
        }
        return $result;
    }
}
