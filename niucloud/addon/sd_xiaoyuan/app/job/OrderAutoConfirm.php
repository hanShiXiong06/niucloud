<?php

namespace addon\sd_xiaoyuan\app\job;

use addon\sd_xiaoyuan\app\dict\order\OrderDict;
use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\service\core\OrderService;
use app\model\site\Site;

/**
 * 订单自动确认收货任务
 */
class OrderAutoConfirm
{
    public function fire()
    {
        $sites = (new Site())->field('site_id')->select();
        foreach ($sites as $site) {
            $siteId = (int)($site['site_id'] ?? 0);
            if ($siteId <= 0) continue;

            $list = (new Order())->where([
                ['site_id', '=', $siteId],
                ['status', '=', OrderDict::STATUS_WAIT_USER_CONFIRM],
                ['auto_confirm_time', '>', 0],
                ['auto_confirm_time', '<=', time()]
            ])->field('id')->select()->toArray();

            if (empty($list)) continue;

            $service = new OrderService();
            foreach ($list as $item) {
                try {
                    $service->autoConfirmCompleted((int)$item['id'], $siteId);
                } catch (\Throwable $e) {
                    trace('sd_xiaoyuan auto confirm failed: ' . $e->getMessage(), 'error');
                }
            }
        }
    }
}
