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

            if (!empty($data['order_id']) && !empty($data['site_id'])) {
                $notifyService = new CoreRecycleOrderNotifyService();
                $notifyService->orderAddNotify($data);
            }

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

            if (!empty($data['order_id']) && !empty($data['site_id'])) {
                $notifyService = new CoreRecycleOrderNotifyService();
                $notifyService->orderSignNotify($data);
            }

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
        } catch (\Exception $e) {
            Log::error('订单质检完成后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单开始质检后事件
     * @param array $data
     * @return void
     */
    public static function orderStartCheckAfter(array $data): void
    {
        try {
            Log::info('回收订单开始质检后事件', $data);
        } catch (\Exception $e) {
            Log::error('订单开始质检后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单完成质检后事件
     * @param array $data
     * @return void
     */
    public static function orderCompleteCheckAfter(array $data): void
    {
        try {
            Log::info('回收订单完成质检后事件', $data);
        } catch (\Exception $e) {
            Log::error('订单完成质检后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单定价后事件 - 通知用户确认
     * @param array $data
     * @return void
     */
    public static function orderSetPriceAfter(array $data): void
    {
        try {
            Log::info('回收订单定价后事件', $data);

            // 定价后通知用户来确认
            if (!empty($data['order_id']) && !empty($data['site_id'])) {
                $notifyService = new CoreRecycleOrderNotifyService();
                $notifyService->orderAgreeNotify($data);
            }

        } catch (\Exception $e) {
            Log::error('订单定价后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单调整价格后事件
     * @param array $data
     * @return void
     */
    public static function orderAdjustPriceAfter(array $data): void
    {
        try {
            Log::info('回收订单调整价格后事件', $data);

            // 重新定价后也通知用户确认
            if (!empty($data['order_id']) && !empty($data['site_id'])) {
                $notifyService = new CoreRecycleOrderNotifyService();
                $notifyService->orderAgreeNotify($data);
            }

        } catch (\Exception $e) {
            Log::error('订单调整价格后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单强制确认后事件
     * @param array $data
     * @return void
     */
    public static function orderForceConfirmAfter(array $data): void
    {
        try {
            Log::info('回收订单强制确认后事件', $data);
        } catch (\Exception $e) {
            Log::error('订单强制确认后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单定价后事件（兼容旧名称）
     * @param array $data
     * @return void
     */
    public static function orderPriceAfter(array $data): void
    {
        self::orderSetPriceAfter($data);
    }

    /**
     * 订单支付/打款后事件
     * 注意：FlowDict 中配置的 event_after 为 'orderPaymentAfter'
     * @param array $data
     * @return void
     */
    public static function orderPaymentAfter(array $data): void
    {
        try {
            Log::info('回收订单打款后事件（orderPaymentAfter）', $data);

            if (!empty($data['order_id']) && !empty($data['site_id'])) {
                $notifyService = new CoreRecycleOrderNotifyService();
                $notifyService->orderPayNotify($data);
            }

        } catch (\Exception $e) {
            Log::error('订单打款后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单支付后事件（兼容旧名称）
     * @param array $data
     * @return void
     */
    public static function orderPayAfter(array $data): void
    {
        self::orderPaymentAfter($data);
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
        } catch (\Exception $e) {
            Log::error('设备退回后事件处理失败：' . $e->getMessage(), $data);
        }
    }
}
