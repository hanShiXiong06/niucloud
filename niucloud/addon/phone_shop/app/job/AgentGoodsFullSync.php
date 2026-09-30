<?php
declare(strict_types=1);

namespace addon\phone_shop\app\job;

use addon\phone_shop\app\service\core\agent\GoodsDistributionService;
use addon\phone_shop\app\service\core\agent\AgentSyncMonitorService;
use core\base\BaseJob;
use think\facade\Log;

/** 站点关系首次建立、恢复或改价后的全货盘同步。 */
class AgentGoodsFullSync extends BaseJob
{
    public function doJob(int $masterSiteId, int $agentSiteId, int $syncRunId = 0): array
    {
        $monitor = new AgentSyncMonitorService();
        try {
            if ($syncRunId > 0) $monitor->running($syncRunId);
            $report = (new GoodsDistributionService())->distributeAllToAgentWithReport(
                $masterSiteId,
                $agentSiteId,
                0,
                $syncRunId > 0 ? static function (array $progress) use ($monitor, $syncRunId) {
                    $monitor->progress($syncRunId, $progress);
                } : null
            );
            if ($syncRunId > 0) $monitor->finish($syncRunId, $report);
            return $report;
        } catch (\Throwable $e) {
            if ($syncRunId > 0) $monitor->fail($syncRunId, $e);
            Log::write("[phone_shop 全站跟随任务] {$masterSiteId}->{$agentSiteId}失败: " . $e->getMessage());
            throw $e;
        }
    }
}
