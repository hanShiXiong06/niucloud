<?php
declare(strict_types=1);

namespace addon\recycle\app\service\core\recycle_order;

use core\base\BaseCoreService;
use think\facade\Log;

/**
 * 回收订单事件核心服务
 * Class CoreRecycleOrderEventService
 * @package addon\recycle\app\service\core\recycle_order
 */
class CoreRecycleOrderEventService extends BaseCoreService
{
    /**
     * 订单创建后事件
     * @param array $data
     * @return void
     */
    public static function orderCreateAfter(array $data): void
    {
        try {
            Log::info('回收订单创建后事件', $data);
            
            // 触发通知
            if (!empty($data['order_id']) && !empty($data['site_id'])) {
                $notifyService = new CoreRecycleOrderNotifyService();
                $notifyService->orderAddNotify($data);
            }
            
            // 可以在这里添加其他业务逻辑
            // 例如：库存更新、积分奖励、统计更新等
            
        } catch (\Exception $e) {
            Log::error('订单创建后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单签收后事件
     * @param array $data
     * @return void
     */
    public static function orderSignAfter(array $data): void
    {
        try {
            Log::info('回收订单签收后事件', $data);
            
            // 触发通知
            if (!empty($data['order_id']) && !empty($data['site_id'])) {
                $notifyService = new CoreRecycleOrderNotifyService();
                $notifyService->orderSignNotify($data);
            }
            
            // 可以在这里添加其他业务逻辑
            // 例如：自动开始质检、发送短信通知等
            
        } catch (\Exception $e) {
            Log::error('订单签收后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单质检完成后事件
     * @param array $data
     * @return void
     */
    public static function orderCheckAfter(array $data): void
    {
        try {
            Log::info('回收订单质检完成后事件', $data);
            
            // 可以在这里添加业务逻辑
            // 例如：自动定价、发送质检报告等
            
        } catch (\Exception $e) {
            Log::error('订单质检完成后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单定价后事件
     * @param array $data
     * @return void
     */
    public static function orderPriceAfter(array $data): void
    {
        try {
            Log::info('回收订单定价后事件', $data);
            
            // 可以在这里添加业务逻辑
            // 例如：发送定价通知、等待用户确认等
            
        } catch (\Exception $e) {
            Log::error('订单定价后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单支付后事件
     * @param array $data
     * @return void
     */
    public static function orderPayAfter(array $data): void
    {
        try {
            Log::info('回收订单支付后事件', $data);
            
            // 触发通知
            if (!empty($data['order_id']) && !empty($data['site_id'])) {
                $notifyService = new CoreRecycleOrderNotifyService();
                $notifyService->orderPayNotify($data);
            }
            
            // 可以在这里添加其他业务逻辑
            // 例如：更新用户余额、发送支付凭证等
            
        } catch (\Exception $e) {
            Log::error('订单支付后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单完成后事件
     * @param array $data
     * @return void
     */
    public static function orderCompleteAfter(array $data): void
    {
        try {
            Log::info('回收订单完成后事件', $data);
            
            // 可以在这里添加业务逻辑
            // 例如：统计更新、用户评价提醒等
            
        } catch (\Exception $e) {
            Log::error('订单完成后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单关闭后事件
     * @param array $data
     * @return void
     */
    public static function orderCloseAfter(array $data): void
    {
        try {
            Log::info('回收订单关闭后事件', $data);
            
            // 可以在这里添加业务逻辑
            // 例如：退回设备、发送关闭通知等
            
        } catch (\Exception $e) {
            Log::error('订单关闭后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单取消后事件
     * @param array $data
     * @return void
     */
    public static function orderCancelAfter(array $data): void
    {
        try {
            Log::info('回收订单取消后事件', $data);
            
            // 可以在这里添加业务逻辑
            // 例如：库存回滚、发送取消通知等
            
        } catch (\Exception $e) {
            Log::error('订单取消后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 设备质检完成后事件
     * @param array $data
     * @return void
     */
    public static function deviceCheckAfter(array $data): void
    {
        try {
            Log::info('回收设备质检完成后事件', $data);
            
            // 可以在这里添加业务逻辑
            // 例如：自动定价、发送质检结果等
            
        } catch (\Exception $e) {
            Log::error('设备质检完成后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 设备定价后事件
     * @param array $data
     * @return void
     */
    public static function devicePriceAfter(array $data): void
    {
        try {
            Log::info('回收设备定价后事件', $data);
            
            // 可以在这里添加业务逻辑
            // 例如：检查订单是否可以进入下一状态等
            
        } catch (\Exception $e) {
            Log::error('设备定价后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 设备退回后事件
     * @param array $data
     * @return void
     */
    public static function deviceReturnAfter(array $data): void
    {
        try {
            Log::info('回收设备退回后事件', $data);
            
            // 可以在这里添加业务逻辑
            // 例如：检查是否需要关闭订单、发送退回通知等
            
        } catch (\Exception $e) {
            Log::error('设备退回后事件处理失败：' . $e->getMessage(), $data);
        }
    }
} 