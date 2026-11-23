<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\listener;

use addon\hsx_erp_integration\app\service\OriginalDocumentResults;

final class OriginalPurchaseActions
{
    public function handle(array $event): array
    {
        try {
            $site=(int)($event['site_id']??0); $sale=isset($event['sale_id']); $id=(int)($event[$sale?'sale_id':'purchase_id']??0); $no=(string)($event['business_no']??'');
            if ($id<1 || $no==='') return [];
            $service=new OriginalDocumentResults(); $auth=$service->readPermissions($site);
            if (!$auth) return [];
            $rows=$sale ? $service->sale($site,$id,$no) : $service->purchase($site,$id,$no,in_array('erp/finance/payable/lists',$auth,true));
            $out=$rows ? ['results'=>$rows] : [];
            if(!$sale && !in_array('purchase',array_column($rows,'business_type'),true)
                && (new \addon\hsx_erp_integration\app\service\OriginalCaptureRecovery())->available($site,$id))
                $out['actions'][]=['key'=>'integration_capture_purchase','label'=>'关联这张原采购','pc_route'=>'/hsx_erp_integration/task','mobile_path'=>'',
                    'query'=>['detail_extension'=>'hsx_erp_integration','purchase_source_id'=>$id]];
            return $out;
        } catch (\Throwable $e) {
            \think\facade\Log::warning('ERP原单桥接回执不可读',['site_id'=>$site??0,'type'=>get_class($e)]);
            return [];
        }
    }
}
