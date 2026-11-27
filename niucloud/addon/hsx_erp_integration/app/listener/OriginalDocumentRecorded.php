<?php
declare(strict_types=1);
namespace addon\hsx_erp_integration\app\listener;

use addon\hsx_erp_integration\app\service\DomainCaptureService;

final class OriginalDocumentRecorded
{
    public function handle(array $event = []): array
    {
        try {
            return (new DomainCaptureService())->accept($event);
        } catch (\Throwable $e) {
            // Return rather than interrupt sibling consumers; ERP will retain this outbox for replay.
            $message = $e instanceof \DomainException || $e instanceof \InvalidArgumentException
                ? $e->getMessage() : '原单未能安全存入集成中心，请检查插件升级和连接配置';
            return ['consumer'=>'hsx_erp_integration', 'status'=>'failed', 'message'=>$message . '；无需重复办理采购或付款'];
        }
    }
}
