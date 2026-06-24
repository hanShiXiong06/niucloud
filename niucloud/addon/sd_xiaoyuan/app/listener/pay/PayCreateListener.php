<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\listener\pay;

use addon\sd_xiaoyuan\app\dict\order\OrderDict;
use addon\sd_xiaoyuan\app\model\order\Order;
use addon\sd_xiaoyuan\app\model\Task;
use app\dict\pay\PayDict;
use core\exception\CommonException;

/**
 * 支付单据创建监听器
 */
class PayCreateListener
{
    /**
     * 支付创建事件
     */
    public function handle(array $params)
    {
        $trade_type = $params['trade_type'] ?? '';
        $site_id = (int)($params['site_id'] ?? 0);
        $trade_id = (int)($params['trade_id'] ?? 0);

        if ($trade_type == 'sd_xiaoyuan_order') {
            if ($trade_id <= 0) {
                throw new CommonException('订单不存在');
            }
            $order = (new Order())->where([
                ['id', '=', $trade_id],
                ['site_id', '=', $site_id],
            ])->find();
            if (empty($order)) {
                throw new CommonException('订单不存在');
            }
            if ((int)$order['pay_status'] === 1) {
                throw new CommonException('订单已支付');
            }
            if ((int)$order['status'] !== OrderDict::STATUS_WAIT_PAY) {
                throw new CommonException('当前订单状态不支持支付');
            }
            $money = floatval($order['actual_fee'] ?? 0);
            $totalFee = floatval($order['total_fee'] ?? 0);
            $ext = [];
            if (!empty($order['ext'])) {
                $ext = json_decode((string)$order['ext'], true);
                if (!is_array($ext)) {
                    $ext = [];
                }
            }
            if ($money <= 0 && ($totalFee > 0 || !empty($ext['use_card']))) {
                return [
                    'main_type' => PayDict::MEMBER,
                    'main_id' => $order['member_id'],
                    'money' => 0,
                    'status' => PayDict::STATUS_FINISH,
                    'trade_type' => $trade_type,
                    'trade_id' => $trade_id,
                    'body' => '校园帮订单支付(次卡抵扣)',
                ];
            }
            if ($money <= 0) {
                $money = $totalFee;
            }
            if ($money <= 0) {
                throw new CommonException('订单金额异常');
            }
            return [
                'main_type' => PayDict::MEMBER,
                'main_id' => $order['member_id'],
                'money' => $money,
                'trade_type' => $trade_type,
                'trade_id' => $trade_id,
                'body' => '校园帮订单支付',
            ];
        }

        if ($trade_type == 'sd_xiaoyuan_tip') {
            if ($trade_id <= 0) {
                throw new CommonException('小费订单不存在');
            }
            $tipOrder = (new \addon\sd_xiaoyuan\app\model\TipOrder())->where([
                ['id', '=', $trade_id],
                ['site_id', '=', $site_id],
            ])->find();
            if (empty($tipOrder)) {
                throw new CommonException('小费订单不存在');
            }
            if ((int)$tipOrder['pay_status'] === 1) {
                throw new CommonException('订单已支付');
            }
            $money = floatval($tipOrder['amount'] ?? 0);
            if ($money <= 0) {
                throw new CommonException('订单金额异常');
            }
            return [
                'main_type' => PayDict::MEMBER,
                'main_id' => $tipOrder['member_id'],
                'money' => $money,
                'trade_type' => $trade_type,
                'trade_id' => $trade_id,
                'body' => '校园帮小费',
            ];
        }

        if ($trade_type == 'sd_xiaoyuan_task') {
            if ($trade_id <= 0) {
                throw new CommonException('任务不存在');
            }
            $task = (new Task())->where([
                ['id', '=', $trade_id],
                ['site_id', '=', $site_id],
            ])->find();
            if (empty($task)) {
                throw new CommonException('任务不存在');
            }
            if ((int)$task['pay_status'] === 1) {
                throw new CommonException('任务已支付');
            }
            $money = floatval($task['total_amount'] ?? 0);
            if ($money <= 0) {
                throw new CommonException('订单金额异常');
            }
            return [
                'main_type' => PayDict::MEMBER,
                'main_id' => $task['member_id'],
                'money' => $money,
                'trade_type' => $trade_type,
                'trade_id' => $trade_id,
                'body' => '校园帮任务支付',
            ];
        }

        if ($trade_type == 'sd_xiaoyuan_card') {
            if ($trade_id <= 0) {
                throw new CommonException('次卡订单不存在');
            }
            $cardOrder = (new \addon\sd_xiaoyuan\app\model\CardOrder())->where([
                ['id', '=', $trade_id],
                ['site_id', '=', $site_id],
            ])->find();
            if (empty($cardOrder)) {
                throw new CommonException('次卡订单不存在');
            }
            if ((int)$cardOrder['pay_status'] === 1) {
                throw new CommonException('订单已支付');
            }
            $money = floatval($cardOrder['price'] ?? 0);
            if ($money <= 0) {
                throw new CommonException('订单金额异常');
            }
            return [
                'main_type' => PayDict::MEMBER,
                'main_id' => $cardOrder['member_id'],
                'money' => $money,
                'trade_type' => $trade_type,
                'trade_id' => $trade_id,
                'body' => $cardOrder['card_type'] === 'RUNNER' ? '接单员入驻费' : '校园帮次卡购买',
            ];
        }

        if ($trade_type == 'sd_xiaoyuan_house') {
            if ($trade_id <= 0) {
                throw new CommonException('房屋订单不存在');
            }
            $houseOrder = (new \addon\sd_xiaoyuan\app\model\HouseOrder())->where([
                ['id', '=', $trade_id],
                ['site_id', '=', $site_id],
            ])->find();
            if (empty($houseOrder)) {
                throw new CommonException('房屋订单不存在');
            }
            if ((int)$houseOrder['status'] === 1) {
                throw new CommonException('订单已支付');
            }
            $house = (new \addon\sd_xiaoyuan\app\model\House())->where('id', $houseOrder['house_id'])->find();
            $money = floatval($house['deposit'] ?? 0);
            if ($money <= 0) {
                throw new CommonException('订单金额异常');
            }
            return [
                'main_type' => PayDict::MEMBER,
                'main_id' => $houseOrder['member_id'],
                'money' => $money,
                'trade_type' => $trade_type,
                'trade_id' => $trade_id,
                'body' => '房屋租赁押金支付',
            ];
        }

        return null;
    }
}
