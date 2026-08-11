<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use core\base\BaseCoreService;
use think\facade\Log;

/**
 * 回收订单事件核心服务
 * Class CoreRecycleOrderEventService
 * @package addon\hsx_recycle\app\service\core\recycle_order
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

                // 按打印场景配置自动触发云打印机（如订单签收凭证）。
                // 触发结果（含成功/跳过/失败）由打印框架写入 recycle_print_task / recycle_print_log，即触发日志。
                try {
                    (new \addon\hsx_recycle\app\service\admin\printer\RecyclePrintTriggerService())
                        ->auto('order.signed', [
                            'order_id' => $data['order_id'],
                            'biz_id'   => $data['order_id'],
                        ]);
                } catch (\Throwable $e) {
                    Log::error('订单签收后自动打印触发失败：' . $e->getMessage(), $data);
                }
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

        // 打款即完成，触发订单完成事件
        self::orderCompleteAfter($data);
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

            // 事实来源二选一：ERP 安装时必须等 ERP 实际结清事件；
            // 未安装 ERP 时，才由回收插件本地打款完成事件发布事实。
            // 这样既不会提前累计，也不会因两边都回调而重复计算。
            $siteId = self::resolveEventSiteId($data);
            if ($siteId > 0 && (new RecycleErpCapabilityService())->isPaymentManaged($siteId)) {
                Log::info('回收订单营销事实等待 ERP 结清事件', [
                    'order_id' => (int)($data['order_id'] ?? 0),
                    'site_id' => $siteId,
                ]);
            } else {
                $data['source_plugin'] = 'hsx_recycle';
                self::emitMarketingFact($data, false);
            }

            // 订单完成奖励积分
            $rewardPoint = self::giveOrderRewardPoint($data);

            // 发送奖励通知
            if ($rewardPoint > 0 && !empty($data['site_id'])) {
                $notifyService = new CoreRecycleOrderNotifyService();
                $notifyService->orderRewardNotify([
                    'order_id'     => $data['order_id'],
                    'site_id'      => $data['site_id'],
                    'reward_point' => $rewardPoint,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('订单完成后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 仅补发“设备完成回收”营销事实。
     *
     * ERP 财务结算属于跨插件异步回写：订单和设备已经在事务内结清，
     * 这里不能再次触发打款通知或旧积分奖励，只向营销中心发布幂等事实。
     * 未安装营销插件时事件无人消费，不影响回收和 ERP 的结算主流程。
     */
    public static function marketingDeliveryFactAfter(array $data): void
    {
        try {
            Log::info('回收订单营销事实补发', $data);
            $data['source_plugin'] = (string)($data['source_plugin'] ?? 'hsx_erp');
            self::emitMarketingFact($data, false);
        } catch (\Throwable $e) {
            // 营销是旁路能力，失败由日志和事实补偿处理，不能反向破坏已完成的财务结算。
            Log::error('回收订单营销事实补发失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 订单完成奖励积分
     * @param array $data
     * @return int 实际发放的积分数，0 表示未发放
     */
    private static function giveOrderRewardPoint(array $data): int
    {
        try {
            $order_id = $data['order_id'] ?? 0;
            if (empty($order_id)) {
                return 0;
            }

            // 获取订单信息
            $order = (new \addon\hsx_recycle\app\model\order\RecycleOrder())
                ->where(['id' => $order_id])
                ->findOrEmpty()
                ->toArray();

            if (empty($order) || empty($order['member_id'])) {
                return 0;
            }

            // 获取奖励配置
            $config = (new \app\service\core\sys\CoreConfigService())->getConfig($order['site_id'], 'recycle_order_reward');
            if (empty($config) || empty($config['value'])) {
                return 0;
            }

            $reward_config = $config['value'];

            // 检查是否启用
            if (empty($reward_config['is_enable']) || $reward_config['is_enable'] != 1) {
                return 0;
            }

            // 检查积分数量
            $reward_point = intval($reward_config['reward_point'] ?? 0);
            if ($reward_point <= 0) {
                return 0;
            }

            // 检查奖励次数限制
            $reward_times = intval($reward_config['reward_times'] ?? 1);
            if ($reward_times <= 0) {
                return 0;
            }

            // 统计用户已获得奖励的次数
            $rewarded_count = (new \app\model\member\MemberAccountLog())
                ->where([
                    ['site_id', '=', $order['site_id']],
                    ['member_id', '=', $order['member_id']],
                    ['account_type', '=', \app\dict\member\MemberAccountTypeDict::POINT],
                    ['from_type', '=', 'recycle_order_reward']
                ])
                ->count();

            // 判断是否已达到奖励次数上限
            if ($rewarded_count >= $reward_times) {
                Log::write('订单完成奖励积分跳过: 用户已达到奖励次数上限 (已奖励' . $rewarded_count . '次，上限' . $reward_times . '次)');
                return 0;
            }

            // 发放积分
            Log::write('订单完成奖励积分开始: 订单ID=' . $order_id . ', 会员ID=' . $order['member_id'] . ', 积分=' . $reward_point . ', 当前第' . ($rewarded_count + 1) . '次奖励');
            (new \app\service\core\member\CoreMemberAccountService())->addLog(
                $order['site_id'],
                $order['member_id'],
                \app\dict\member\MemberAccountTypeDict::POINT,
                $reward_point,
                'recycle_order_reward',
                '订单完成奖励' . $reward_point . '积分',
                $order_id
            );
            Log::write('订单完成奖励积分成功');
            return $reward_point;
        } catch (\Exception $e) {
            Log::error('订单完成奖励积分失败：' . $e->getMessage());
            return 0;
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
            // 如果该订单曾形成营销事实，关闭时用原 event_id 冲红；未形成事实则营销中心安全忽略。
            self::emitMarketingFact($data, true);
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
            $deviceId = (int)($data['device_id'] ?? $data['id'] ?? 0);
            if ($deviceId > 0) self::emitMarketingDeviceFact($deviceId, true);
        } catch (\Exception $e) {
            Log::error('设备退回后事件处理失败：' . $e->getMessage(), $data);
        }
    }

    /**
     * 回收插件 -> 营销中心 v1 契约。
     * 完成与冲红均使用稳定 event_id，保证定时任务、重复回调不会重复累计或重复扣减。
     */
    private static function emitMarketingFact(array $data, bool $reversal): void
    {
        $orderId = (int)($data['order_id'] ?? 0);
        if ($orderId <= 0) return;
        $order = (new \addon\hsx_recycle\app\model\order\RecycleOrder())
            ->where('id', '=', $orderId)
            ->field('id,site_id,member_id,order_no,complete_at,create_at')
            ->findOrEmpty();
        if ($order->isEmpty() || (int)$order['site_id'] <= 0 || (int)$order['member_id'] <= 0) return;
        $devices = $order->devices()->select()->toArray();
        foreach ($devices as $device) {
            // 正向事实只统计最终由商家收购的设备，已退回设备不进入任务。
            if (!$reversal && (int)($device['status'] ?? 0) === \addon\hsx_recycle\app\dict\order\RecycleOrderDict::DEVICE_STATUS_RETURNED) continue;
            self::emitMarketingDeviceFact(
                (int)$device['id'],
                $reversal,
                $order->toArray(),
                $device,
                (string)($data['source_plugin'] ?? 'hsx_recycle')
            );
        }
    }

    /** 每台设备独立成事实，才能准确处理部分退货与成交价区间。 */
    private static function emitMarketingDeviceFact(
        int $deviceId,
        bool $reversal,
        array $order = [],
        array $device = [],
        string $sourcePlugin = 'hsx_recycle'
    ): void
    {
        if ($deviceId <= 0) return;
        if ($device === []) {
            $device = (new \addon\hsx_recycle\app\model\order\RecycleDevice())->where('id', '=', $deviceId)->findOrEmpty()->toArray();
        }
        if (!$device) return;
        if ($order === []) {
            $order = (new \addon\hsx_recycle\app\model\order\RecycleOrder())
                ->where('id', '=', (int)($device['order_id'] ?? 0))->findOrEmpty()->toArray();
        }
        if (!$order || (int)($order['site_id'] ?? 0) <= 0 || (int)($order['member_id'] ?? 0) <= 0) return;
        $siteId = (int)$order['site_id'];
        $originalEventId = 'hsx_recycle:device_delivered:' . $siteId . ':' . $deviceId;
        $amount = (float)($device['pay_amount'] ?? 0);
        if ($amount <= 0) $amount = (float)($device['final_price'] ?? 0);
        if ($amount <= 0) $amount = (float)($device['initial_price'] ?? 0);
        event('HsxMarketingFactRecorded', [
            'contract_version' => 'v1',
            'event_id' => $reversal ? $originalEventId . ':reversed' : $originalEventId,
            'event_name' => $reversal ? 'hsx.recycle.device.delivered.reversed.v1' : 'hsx.recycle.device.delivered.v1',
            'source_plugin' => $sourcePlugin, 'fact_key' => 'recycle_device_delivered',
            'fact_type' => $reversal ? 'reversal' : 'original', 'direction' => $reversal ? -1 : 1,
            'reversal_of_event_id' => $reversal ? $originalEventId : '',
            'site_id' => $siteId, 'member_id' => (int)$order['member_id'],
            'business_type' => 'recycle_device', 'business_id' => (string)$deviceId,
            'business_no' => (string)($order['order_no'] ?? ''), 'quantity' => 1,
            'device_id' => $deviceId, 'imei' => (string)($device['imei'] ?? ''),
            'amount' => $amount, 'final_price' => (float)($device['final_price'] ?? 0),
            'pay_amount' => (float)($device['pay_amount'] ?? 0),
            'occurred_at' => (int)($order['complete_at'] ?? 0) ?: time(),
        ]);
    }

    /** 兼容部分旧调用未携带 site_id 的情况。 */
    private static function resolveEventSiteId(array $data): int
    {
        $siteId = (int)($data['site_id'] ?? 0);
        if ($siteId > 0) return $siteId;
        $orderId = (int)($data['order_id'] ?? 0);
        if ($orderId <= 0) return 0;
        return (int)(new \addon\hsx_recycle\app\model\order\RecycleOrder())
            ->where('id', '=', $orderId)
            ->value('site_id');
    }
}
