<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\job\schedule;

use addon\hsx_erp\app\service\admin\ErpIntegrationService;
use core\base\BaseJob;
use think\facade\Log;

final class DomainEventRetry extends BaseJob
{
    public function doJob(array $params = []): array
    {
        try {
            $result = (new ErpIntegrationService())->retryPendingDomainEvents(
                (int)($params['site_id'] ?? 0),
                (int)($params['limit'] ?? 100)
            );
            if ((int)$result['failed'] > 0 || (int)$result['dead'] > 0) {
                Log::warning('ERP跨插件事件补偿仍有失败', $result);
            }
            return $result;
        } catch (\Throwable $e) {
            Log::error('ERP跨插件事件补偿失败：' . $e->getMessage());
            return ['scanned' => 0, 'done' => 0, 'failed' => 1, 'dead' => 0, 'message' => $e->getMessage()];
        }
    }
}
