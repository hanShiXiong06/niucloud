<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\job\order_event;

use addon\hsx_recycle\app\dict\order\RecycleReturnOrderDict;
use addon\hsx_recycle\app\model\order\RecycleReturnOrder;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleReturnOrderService;
use core\base\BaseJob;
use think\facade\Log;

/** 发出满 72 小时自动完成；不把超时完成描述为物流已签收。 */
class ReturnOrderAutoComplete extends BaseJob
{
    public function doJob($data = null)
    {
        $completed = 0;
        $failed = 0;
        $cursor = 0;
        $cutoff = time() - 72 * 60 * 60;
        try {
            do {
                // update_at 在确认发货事务内写入；不能用建单时间提前完成刚发出的退回单。
                $orders = (new RecycleReturnOrder())->where('status', RecycleReturnOrderDict::ORDER_STATUS_RETURNING)
                    ->where('delete_at', 0)->where('update_at', '>', 0)->where('update_at', '<=', $cutoff)
                    ->where('id', '>', $cursor)->order('id')->limit(100)->select();
                foreach ($orders as $order) {
                    $cursor = (int)$order['id'];
                    try {
                        (new RecycleReturnOrderService())->updateStatus($cursor, RecycleReturnOrderDict::ORDER_STATUS_COMPLETED, [
                            'site_id' => (int)$order['site_id'],
                            'operator_uid' => 0,
                            'operator_name' => '系统自动',
                            'auto_complete' => true,
                        ]);
                        $completed++;
                    } catch (\Throwable $e) {
                        $failed++;
                        Log::error('退回自动完成失败：' . $order['order_no'] . '；' . $e->getMessage());
                    }
                }
            } while (count($orders) === 100);
            Log::info("退回72小时自动完成：成功{$completed}单，失败{$failed}单");
            return $failed === 0;
        } catch (\Throwable $e) {
            Log::error('退回自动完成任务异常：' . $e->getMessage());
            return false;
        }
    }
}
