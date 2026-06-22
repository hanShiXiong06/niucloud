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
