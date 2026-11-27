<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\listener;

use addon\hsx_erp_integration\app\service\OriginalDocumentResults;

final class OriginalFinanceLinks
{
    public function handle(array $event): array
    {
        try {
            $site=(int)($event['site_id']??0);
            if (($event['direction']??'')!=='payable' || empty($event['facts'])) return [];
            $service=new OriginalDocumentResults();
            if (!$service->readPermissions($site)) return [];
            return $service->payables($site,$event['facts']);
        } catch (\Throwable $e) {
            \think\facade\Log::warning('ERP本机应付桥接回执不可读',['site_id'=>$site??0,'type'=>get_class($e)]);
            return [];
        }
    }
}
