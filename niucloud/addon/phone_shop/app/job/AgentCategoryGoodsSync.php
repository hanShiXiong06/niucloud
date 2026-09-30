<?php
declare(strict_types=1);

namespace addon\phone_shop\app\job;

use addon\phone_shop\app\service\core\agent\RefDataSyncService;
use core\base\BaseJob;
use think\facade\Log;
use think\facade\Db;

class AgentCategoryGoodsSync extends BaseJob
{
    public function doJob(int $masterSiteId, int $agentSiteId): void
    {
        $service = new RefDataSyncService();
        $cursor = 0;
        do {
            $active = Db::name('phone_shop_agent')->where([
                ['master_site_id', '=', $masterSiteId], ['agent_site_id', '=', $agentSiteId],
                ['status', '=', 1], ['subscribe_category', '=', 1], ['ref_sync_mode', '=', 'realtime'],
            ])->count();
            if (!$active) return;
            $batch = $service->syncBatch('goods_category', $masterSiteId, $agentSiteId, $cursor);
            $cursor = $batch['cursor'];
            if ($batch['failed'] > 0) Log::write('[phone_shop 商品分类跟随] ' . json_encode($batch['errors'], JSON_UNESCAPED_UNICODE));
        } while (!$batch['done']);
    }
}
