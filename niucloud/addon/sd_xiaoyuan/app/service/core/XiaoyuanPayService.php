<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\listener\pay\PayCreateListener;
use addon\sd_xiaoyuan\app\listener\pay\PaySuccessListener;
use addon\sd_xiaoyuan\app\model\order\Order;
use app\dict\pay\PayDict;
use app\dict\pay\PaySceneDict;
use app\service\core\pay\CorePayChannelService;
use app\service\core\pay\CorePayService;
use core\exception\PayException;

/**
 * 校园帮支付服务（直接调 PayCreateListener，避免其它插件 return true 被框架当成支付数据）
 */
class XiaoyuanPayService extends CorePayService
{
    /**
     * 根据业务单创建支付单
     */
    public function createByTrade($site_id, $trade_type, $trade_id)
    {
        $site_id = (int)$site_id;
        $trade_id = (int)$trade_id;
        $trade_type = (string)$trade_type;

        $eventResults = event('PayCreate', [
            'site_id' => $site_id,
            'trade_type' => $trade_type,
            'trade_id' => $trade_id,
        ]);
        trace('sd_xiaoyuan PayCreate event结果: ' . json_encode($eventResults, JSON_UNESCAPED_UNICODE), 'info');

        $data = (new PayCreateListener())->handle([
            'site_id' => $site_id,
            'trade_type' => $trade_type,
            'trade_id' => $trade_id,
        ]);
        trace('sd_xiaoyuan PayCreateListener结果: ' . json_encode($data, JSON_UNESCAPED_UNICODE), 'info');

        if (!is_array($data) || !isset($data['money'])) {
            trace('sd_xiaoyuan 支付数据无效 site_id=' . $site_id . ' trade_type=' . $trade_type . ' trade_id=' . $trade_id, 'error');
            throw new PayException('PAY_NOT_FOUND_TRADE');
        }

        if (isset($data['status']) && $data['money'] == 0) {
            $data['status'] = PayDict::STATUS_FINISH;
            $data['status_name'] = PayDict::getStatus()[$data['status']] ?? '';
            $data['type'] = PayDict::BALANCEPAY;
            $data['type_name'] = PayDict::getPayType()[$data['type']]['name'] ?? '';
            return $data;
        }

        $out_trade_no = $this->create(
            $site_id,
            (string)$data['main_type'],
            (int)$data['main_id'],
            (float)$data['money'],
            (string)$data['trade_type'],
            (int)$data['trade_id'],
            (string)$data['body']
        );
        trace('sd_xiaoyuan 创建支付单成功 out_trade_no=' . $out_trade_no, 'info');
        return $this->findPayInfoByOutTradeNo($site_id, $out_trade_no);
    }

    public function getInfoByTrade(int $site_id, string $trade_type, string $trade_id, string $channel, string $scene = '')
    {
        $pay = $this->findPayInfoByTrade($site_id, $trade_type, $trade_id);
        $zeroPay = false;
        if ($pay->isEmpty()) {
            $pay = $this->createByTrade($site_id, $trade_type, $trade_id);
            if (is_array($pay) && isset($pay['money']) && (float)$pay['money'] == 0) {
                $zeroPay = true;
            }
        }
        if (!is_array($pay)) {
            $pay = $pay->toArray();
        } else {
            $pay['trade_type'] = $pay['trade_type'] ?? $trade_type;
            $pay['trade_id'] = $pay['trade_id'] ?? (int)$trade_id;
        }
        if ($zeroPay && (int)($pay['status'] ?? 0) === PayDict::STATUS_FINISH) {
            $this->finishZeroPay($site_id, $trade_type, (int)$trade_id);
        }
        if (!empty($pay)) {
            $pay_type_list = (new CorePayChannelService())->getAllowPayTypeByChannel($site_id, $channel, $pay['trade_type']);
            if (!empty($pay_type_list) && !empty($pay_type_list[PayDict::FRIENDSPAY]) && $scene == PaySceneDict::FRIENDSPAY) {
                $pay['config'] = $pay_type_list[PayDict::FRIENDSPAY]['config'];
                unset($pay_type_list[PayDict::FRIENDSPAY]);
            }
            $pay['pay_type_list'] = array_values($pay_type_list);
        }
        return $pay;
    }

    public function confirmCardOrderPay(int $site_id, int $order_id): void
    {
        $this->finishZeroPay($site_id, 'sd_xiaoyuan_order', $order_id);
    }

    private function finishZeroPay(int $site_id, string $trade_type, int $trade_id): void
    {
        if ($trade_type !== 'sd_xiaoyuan_order') {
            return;
        }
        $order = (new Order())->where([
            ['id', '=', $trade_id],
            ['site_id', '=', $site_id],
        ])->find();
        if (empty($order) || (int)$order['pay_status'] === 1) {
            return;
        }
        (new PaySuccessListener())->handle([
            'trade_type' => $trade_type,
            'trade_id' => $trade_id,
            'site_id' => $site_id,
            'main_id' => (int)$order['member_id'],
        ]);
    }
}
