<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\order;

use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\model\order\Order;
use app\dict\pay\PayDict;
use app\model\pay\Pay;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/** 商城支付及改价的公共约束。只在 phone_shop 内使用，无数据库结构升级。 */
class CoreOrderPaymentGuardService
{
    public function withOrderLock(int $siteId, int $orderId, callable $action)
    {
        if ($siteId <= 0 || $orderId <= 0) throw new CommonException('订单信息不完整，请刷新后重试');
        $connection = Db::connect();
        $scope = (string)$connection->getConfig('database') . ':' . (string)$connection->getConfig('prefix');
        $key = 'phone_shop_pay:' . substr(hash('sha256', $scope . ':' . $siteId . ':' . $orderId), 0, 40);
        // 命名锁不创建表、不包住支付事务；支付单必须在调用支付网关前可被回调读取。
        try {
            $locked = $connection->query('SELECT GET_LOCK(:lock_key, 3) AS acquired', ['lock_key' => $key], true);
        } catch (\Throwable $e) {
            Log::error('[phone_shop 支付保护不可用] site_id=' . $siteId . ', order_id=' . $orderId . ': ' . $e->getMessage());
            throw new CommonException('订单付款保护暂不可用，请联系管理员检查数据库命名锁支持');
        }
        if ((int)($locked[0]['acquired'] ?? 0) !== 1) {
            throw new CommonException('订单正在支付或调整价格，请稍后刷新再试');
        }
        try {
            return $action();
        } finally {
            try {
                $connection->query('SELECT RELEASE_LOCK(:lock_key) AS released', ['lock_key' => $key], true);
            } catch (\Throwable $e) {
                // 不用释放锁的异常覆盖原付款结果；连接关闭后 MySQL 自动释放命名锁。
                Log::error('[phone_shop 支付锁释放失败] site_id=' . $siteId . ', order_id=' . $orderId . ': ' . $e->getMessage());
            }
        }
    }

    public function assertPayable(int $siteId, int $orderId, $expectedMoney = ''): void
    {
        // 无论支付单是新建还是复用，都重新检查订单状态、线上支付开关及 B/C 端规则。
        $orderPayment = $this->getOrderPayment($siteId, $orderId);
        $money = (string)$orderPayment['money'];
        if ($expectedMoney !== '' && (!is_scalar($expectedMoney)
            || !preg_match('/^\d+(?:\.\d{1,2})?$/D', (string)$expectedMoney)
            || bccomp((string)$expectedMoney, $money, 2) !== 0)) {
            throw new CommonException('支付金额已变化，请关闭收银台，刷新订单并确认新金额后再付款');
        }

        // 旧端不传展示金额也不能绕过服务端金额一致性检查。
        $payments = (new Pay())->where([
            ['site_id', '=', $siteId], ['trade_type', '=', OrderDict::TYPE],
            ['trade_id', '=', $orderId], ['status', '<>', PayDict::STATUS_CANCEL],
        ])->master()->select();
        foreach ($payments as $payment) {
            if (bccomp((string)$payment['money'], $money, 2) !== 0) {
                throw new CommonException('支付单金额与订单不一致，请联系商家核对后重新发起支付，当前尚未扣款');
            }
        }
    }

    /** 支付创建事件和付款前复核共用业务规则，事件监听器不承担业务实现。 */
    public function getOrderPayment(int $siteId, int $orderId): array
    {
        $order = (new Order())->where([
            ['site_id', '=', $siteId], ['order_id', '=', $orderId],
        ])->master()->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('SHOP_ORDER_NOT_FOUND');
        $orderInfo = $order->toArray();
        if ($orderInfo['status'] != OrderDict::WAIT_PAY) throw new CommonException('SHOP_ONLY_WAIT_PAY_CAN_BE_PAY');
        if (bccomp((string)$orderInfo['order_money'], '0', 2) <= 0) {
            throw new CommonException('该订单无需发起线上支付，请刷新订单状态');
        }
        $payState = (new CoreOrderConfigService())->getOrderOnlinePayState($orderInfo);
        if (empty($payState['can_online_pay'])) throw new CommonException($payState['online_pay_disabled_reason']);
        return [
            'main_type' => PayDict::MEMBER, 'main_id' => $orderInfo['member_id'],
            'money' => $orderInfo['order_money'], 'trade_type' => OrderDict::TYPE,
            'trade_id' => $orderId, 'body' => $orderInfo['body'],
        ];
    }
}
