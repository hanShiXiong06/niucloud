<?php
declare(strict_types=1);

namespace addon\hsx_ysepay\core\pay;

use addon\hsx_ysepay\core\support\YsepayClient;
use app\dict\pay\OnlinePayDict;
use app\dict\pay\PayDict;
use app\dict\pay\RefundDict;
use app\model\pay\Pay;
use app\model\pay\Refund;
use core\exception\PayException;
use core\pay\BasePay;
use think\facade\Log;

class Ysepay extends BasePay
{
    private YsepayClient $client;

    protected function initialize(array $config = [])
    {
        $this->config = $config;
        $this->client = new YsepayClient($config);
    }

    public function pay(array $params): array
    {
        return $this->accessMode() === 'js_pay'
            ? $this->jsPay($params)
            : $this->cashierPay($params);
    }

    private function cashierPay(array $params): array
    {
        $channel = (string)($params['channel'] ?? '');
        $business = array_filter([
            'orderId' => (string)$params['out_trade_no'],
            'msgCode' => (string)($this->config['msg_code'] ?? 'S3001'),
            'mercId' => (string)$this->config['merc_id'],
            'busiCode' => $this->businessCode(),
            'shopDate' => $this->shopDate((string)$params['out_trade_no']),
            'amount' => $this->toCent((float)$params['money']),
            'paymentValidTime' => max(1, min(30, (int)($this->config['payment_valid_time'] ?? 30))),
            'currency' => 'CNY',
            'note' => mb_substr((string)($params['body'] ?? '订单支付'), 0, 120),
            'backUrl' => (string)($this->config['notify_url'] ?? ''),
            'limitPay' => (string)($this->config['limit_pay'] ?? '0'),
            'payMode' => $this->payMode($channel),
            'storeId' => trim((string)($this->config['store_id'] ?? '')),
            'isFastPay' => (string)($this->config['is_fast_pay'] ?? '01'),
            'h5Join' => trim((string)($this->config['h5_join'] ?? '')),
            'appType' => trim((string)($this->config['app_type'] ?? '')),
            'mercHomeUrl' => trim((string)($params['refund_url'] ?? '')),
        ], static fn($value): bool => $value !== '' && $value !== null);

        $response = $this->client->request(
            '/openapi/order/createOrder',
            'order.createOrder',
            (string)($this->config['preorder_version'] ?? '6.3'),
            $business
        );
        if (!$this->client->businessSucceeded($response)) {
            throw new PayException('银盛支付预下单失败：' . $this->client->businessMessage($response));
        }

        $data = $this->unwrapData((array)($response['_business'] ?? []));
        $url = trim((string)($data['payUrl'] ?? ''));
        if ($url === '') {
            throw new PayException('银盛支付预下单未返回收银台地址');
        }

        $result = [
            'url' => $url,
            'open_type' => 'external',
        ];
        if ($channel === 'weapp' && !empty($data['appId'])) {
            $data['isFastPay'] = (string)($this->config['is_fast_pay'] ?? '01');
            $result['mini_program'] = [
                'appId' => (string)$data['appId'],
                'path' => 'pages/index/index',
                'envVersion' => (string)($this->config['mini_program_env'] ?? 'release'),
                'extraData' => [
                    'businessData' => [
                        'data' => $data,
                        'resultCode' => 0,
                        'message' => '下单成功',
                        'suffixCode' => 0,
                        'rpcError' => false,
                    ],
                ],
            ];
        }
        return $result;
    }

    private function jsPay(array $params): array
    {
        $channel = (string)($params['channel'] ?? '');
        if (!in_array($channel, ['wechat', 'weapp'], true)) {
            throw new PayException('银盛 JS 支付仅支持微信公众号和微信小程序，请切换为聚合收银台模式');
        }

        $openid = trim((string)($params['openid'] ?? ''));
        if ($openid === '') {
            throw new PayException('当前用户缺少微信 OpenID，无法发起银盛 JS 支付');
        }

        $appId = trim((string)($channel === 'weapp'
            ? ($this->config['weapp_app_id'] ?? '')
            : ($this->config['wechat_app_id'] ?? '')));
        if ($appId === '') {
            throw new PayException(
                $channel === 'weapp'
                    ? '请先配置银盛支付微信小程序 AppID'
                    : '请先配置银盛支付微信公众号 AppID'
            );
        }

        $clientIp = $this->clientIp();
        $business = array_filter([
            'orderId' => (string)$params['out_trade_no'],
            'shopDate' => $this->shopDate((string)$params['out_trade_no']),
            'note' => mb_substr((string)($params['body'] ?? '订单支付'), 0, 125),
            'amount' => (string)$this->toCent((float)$params['money']),
            'currency' => 'CNY',
            'payeeMercId' => (string)$this->config['merc_id'],
            'payeeMercName' => trim((string)($this->config['merc_name'] ?? '')),
            'timeOutExpress' => (string)max(1, min(1440, (int)($this->config['payment_valid_time'] ?? 30))),
            'extraCommonParam' => (string)$params['out_trade_no'],
            'busiCode' => $this->businessCode(),
            'subOpenId' => $openid,
            'isMiniPg' => $channel === 'weapp' ? '1' : '2',
            'appId' => $appId,
            'limitCreditPay' => (string)($this->config['limit_credit_pay'] ?? '0'),
            'allowRepeatPay' => (string)($this->config['allow_repeat_pay'] ?? 'N'),
            'notifyUrl' => (string)($this->config['notify_url'] ?? ''),
            'srcIP' => $clientIp,
            'payerIP' => $clientIp,
            'msgCode' => (string)($this->config['msg_code'] ?? 'S3001'),
        ], static fn($value): bool => $value !== '' && $value !== null);

        $response = $this->client->request(
            '/openapi/unify/basePay/scan/weChatPay/js',
            'unify.basePay.scan.weChatPay.js',
            (string)($this->config['js_pay_version'] ?? '1.3'),
            $business,
            $this->jsGatewayBaseUrl()
        );
        if (!$this->client->businessSucceeded($response)) {
            throw new PayException('银盛 JS 支付下单失败：' . $this->client->businessMessage($response));
        }

        $data = $this->unwrapData((array)($response['_business'] ?? []));
        $jsapi = $data['jsapiPayInfo'] ?? [];
        if (is_string($jsapi)) {
            $jsapi = json_decode($jsapi, true);
        }
        if (!is_array($jsapi)) {
            $jsapi = [];
        }
        foreach (['timeStamp', 'nonceStr', 'package', 'signType', 'paySign'] as $field) {
            if (trim((string)($jsapi[$field] ?? '')) === '') {
                throw new PayException('银盛 JS 支付未返回完整的微信预支付参数');
            }
        }

        return [
            'jsapi' => [
                'timeStamp' => (string)$jsapi['timeStamp'],
                'nonceStr' => (string)$jsapi['nonceStr'],
                'package' => (string)$jsapi['package'],
                'signType' => (string)$jsapi['signType'],
                'paySign' => (string)$jsapi['paySign'],
            ],
        ];
    }

    public function web(array $params)
    {
        return $this->pay($params);
    }

    public function wap(array $params)
    {
        return $this->pay($params);
    }

    public function app(array $params)
    {
        return $this->pay($params);
    }

    public function mini(array $params)
    {
        return $this->pay($params);
    }

    public function mp(array $params)
    {
        return $this->pay($params);
    }

    public function scan(array $params)
    {
        return $this->pay($params);
    }

    public function pos(array $params)
    {
        throw new PayException('银盛聚合收银台暂不支持付款码支付');
    }

    public function close(string $out_trade_no): bool
    {
        $response = $this->client->request(
            '/openapi/unify/trade/closeOrder',
            'unify.trade.closeOrder',
            '1.0',
            [
                'orderId' => $out_trade_no,
                'shopDate' => $this->shopDate($out_trade_no),
            ],
            $this->queryGatewayBaseUrl()
        );
        if ($this->client->businessSucceeded($response)) {
            return true;
        }
        $subCode = (string)($response['subCode'] ?? '');
        return in_array($subCode, ['B043001', 'B029901'], true)
            && str_contains($this->client->businessMessage($response), '关闭');
    }

    public function refund(array $params): array
    {
        $outTradeNo = (string)$params['out_trade_no'];
        $refundNo = (string)$params['refund_no'];
        $response = $this->client->request(
            '/openapi/unify/trade/refund',
            'unify.trade.refund',
            '1.0',
            array_filter([
                'origOrderId' => $outTradeNo,
                'shopDate' => $this->shopDate($outTradeNo),
                'refundAmount' => $this->toCent((float)$params['money']),
                'refundReason' => mb_substr((string)($params['reason'] ?? '订单退款'), 0, 120),
                'refundOrderId' => $refundNo,
                'notifyUrl' => (string)($this->config['notify_url'] ?? ''),
            ], static fn($value): bool => $value !== ''),
            $this->queryGatewayBaseUrl()
        );

        if ($this->client->businessSucceeded($response)) {
            return [
                'status' => RefundDict::DEALING,
                'refund_no' => $refundNo,
                'out_trade_no' => $outTradeNo,
            ];
        }
        return [
            'status' => RefundDict::FAIL,
            'refund_no' => $refundNo,
            'out_trade_no' => $outTradeNo,
            'fail_reason' => $this->client->businessMessage($response),
        ];
    }

    public function getOrder(array $params): array
    {
        $outTradeNo = (string)$params['out_trade_no'];
        $response = $this->client->request(
            '/openapi/unify/online/trade/order/query',
            'unify.online.trade.order.query',
            '1.1',
            [
                'orderId' => $outTradeNo,
                'shopDate' => $this->shopDate($outTradeNo),
            ],
            $this->queryGatewayBaseUrl()
        );
        if (!$this->client->businessSucceeded($response)) {
            return [];
        }
        $data = $this->unwrapData((array)($response['_business'] ?? []));
        return [
            'status' => $this->paymentStatus((string)($data['tradeStatus'] ?? '')),
            'trade_no' => (string)($data['tradeSn'] ?? ''),
            'mch_id' => (string)($data['mercId'] ?? $this->config['merc_id']),
        ];
    }

    public function getRefund(string $out_trade_no, ?string $refund_no): array
    {
        $refundNo = (string)$refund_no;
        try {
            $response = $this->client->request(
                '/openapi/unify/trade/refund/query',
                'unify.trade.refund.query',
                '1.1',
                [
                    'origOrderId' => $out_trade_no,
                    'refundOrderId' => $refundNo,
                    'refundQueryType' => '1',
                ],
                $this->queryGatewayBaseUrl()
            );
        } catch (\Throwable $e) {
            return [
                'status' => RefundDict::DEALING,
                'refund_no' => $refundNo,
                'out_trade_no' => $out_trade_no,
                'fail_reason' => '',
            ];
        }
        if (!$this->client->businessSucceeded($response)) {
            return [
                'status' => RefundDict::DEALING,
                'refund_no' => $refundNo,
                'out_trade_no' => $out_trade_no,
                'fail_reason' => '',
            ];
        }
        $data = $this->unwrapData((array)($response['_business'] ?? []));
        return [
            'status' => $this->refundStatus(
                (string)($data['refundState'] ?? ''),
                (string)($data['fundsState'] ?? '')
            ),
            'refund_no' => $refundNo,
            'out_trade_no' => $out_trade_no,
            'fail_reason' => (string)($data['failReason'] ?? ''),
        ];
    }

    public function notify(string $action, callable $callback)
    {
        try {
            $notification = $this->client->parseNotification((string)request()->getContent());
            $business = $this->unwrapData((array)$notification['business']);
            $notifiedMerchantId = trim((string)($business['payeeMercId'] ?? $business['mercId'] ?? ''));
            if ($notifiedMerchantId !== ''
                && $notifiedMerchantId !== (string)$this->config['merc_id']) {
                throw new PayException('银盛支付通知收款商户号不匹配');
            }

            if ($action === 'refund') {
                $refund = $this->resolveRefundNotification($business);
                $refundNo = (string)$refund->refund_no;
                $outTradeNo = (string)$refund->out_trade_no;
                $notifiedOutTradeNo = trim((string)($business['origOrderId'] ?? ''));
                if ($notifiedOutTradeNo !== '' && $notifiedOutTradeNo !== $outTradeNo) {
                    throw new PayException('银盛退款通知原支付单号与本地退款单不匹配');
                }
                if (isset($business['refundAmount'])
                    && $this->toCent((float)$refund->money) !== (int)$business['refundAmount']) {
                    throw new PayException('银盛退款通知金额与本地退款单不一致');
                }
                if ($this->refundAlreadyFinal($refundNo)) {
                    return $this->successResponse();
                }
                $callbackResult = $callback($outTradeNo, [
                    'status' => (string)($business['tradeStatus'] ?? '') === 'TRADE_SUCCESS'
                        ? RefundDict::SUCCESS
                        : $this->refundStatus(
                            (string)($business['refundState'] ?? ''),
                            (string)($business['fundsState'] ?? '')
                        ),
                    'refund_no' => $refundNo,
                    'out_trade_no' => $outTradeNo,
                    'fail_reason' => (string)($business['failReason'] ?? ''),
                ]);
            } else {
                $outTradeNo = (string)($business['orderId'] ?? '');
                if ($this->paymentAlreadyFinished($outTradeNo)) {
                    return $this->successResponse();
                }
                if (!in_array((string)($business['tradeStatus'] ?? ''), ['00', '01', '02', 'TRADE_SUCCESS'], true)) {
                    throw new PayException('银盛支付通知交易状态不是成功');
                }
                $this->assertNotificationAmount($outTradeNo, $business);
                $callbackResult = $callback($outTradeNo, [
                    'mch_id' => (string)$this->config['merc_id'],
                    'trade_no' => (string)($business['tradeSn'] ?? ''),
                    'status' => OnlinePayDict::SUCCESS,
                    'result' => $business,
                ]);
            }
            return $callbackResult === true ? $this->successResponse() : $this->failResponse();
        } catch (\Throwable $e) {
            Log::write(sprintf(
                '[hsx_ysepay] site=%d action=%s notify_failed=%s',
                (int)($this->config['site_id'] ?? 0),
                $action,
                str_replace(["\r", "\n"], ' ', $e->getMessage())
            ), 'error');
            return $this->failResponse();
        }
    }

    public function transfer(array $params)
    {
        throw new PayException('银盛支付插件暂未启用企业付款能力');
    }

    public function getTransfer(string $transfer_no, $out_transfer_no = '')
    {
        return [];
    }

    public function transferCancel(array $params)
    {
        return false;
    }

    private function accessMode(): string
    {
        return (string)($this->config['access_mode'] ?? 'js_pay') === 'cashier'
            ? 'cashier'
            : 'js_pay';
    }

    private function businessCode(): string
    {
        $businessCode = trim((string)($this->config['busi_code'] ?? ''));
        if ($businessCode === '') {
            throw new PayException('请先配置银盛支付业务代码');
        }
        return $businessCode;
    }

    private function payMode(string $channel): string
    {
        $configured = trim((string)($this->config['pay_mode'] ?? ''));
        if ($configured !== '') {
            return $configured;
        }
        return [
            'weapp' => '29',
            'wechat' => '28',
            'app' => '29',
            'h5' => '26',
            'pc' => '26',
        ][$channel] ?? '26';
    }

    private function paymentStatus(string $status): string
    {
        if (in_array($status, ['00', '01', '02'], true)) {
            return OnlinePayDict::SUCCESS;
        }
        if (in_array($status, ['93', '95', '97', '99'], true)) {
            return OnlinePayDict::CLOSED;
        }
        if (in_array($status, ['80', '81'], true)) {
            return OnlinePayDict::REFUND;
        }
        if (in_array($status, ['50', '98'], true)) {
            return OnlinePayDict::PAYERROR;
        }
        return $status === '11' ? OnlinePayDict::USERPAYING : OnlinePayDict::NOTPAY;
    }

    private function refundStatus(string $refundState, string $fundsState): string
    {
        if ($refundState === '00' && ($fundsState === '' || $fundsState === '00')) {
            return RefundDict::SUCCESS;
        }
        if (in_array($refundState, ['96', '97', '98', '99'], true)
            || in_array($fundsState, ['97', '98', '99'], true)) {
            return RefundDict::FAIL;
        }
        return RefundDict::DEALING;
    }

    private function unwrapData(array $business): array
    {
        foreach (['data', 'businessData'] as $field) {
            if (isset($business[$field]) && is_array($business[$field])) {
                return $this->unwrapData($business[$field]);
            }
        }
        return $business;
    }

    private function shopDate(string $orderNo): string
    {
        return preg_match('/^\d{8}/', $orderNo, $matches) ? $matches[0] : date('Ymd');
    }

    private function toCent(float $amount): int
    {
        return (int)round($amount * 100);
    }

    private function queryGatewayBaseUrl(): string
    {
        $custom = trim((string)($this->config['query_gateway_base_url'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        return (string)($this->config['environment'] ?? 'sandbox') === 'production'
            ? 'https://ysgate.ysepay.com'
            : 'https://appdev.ysepay-test.com';
    }

    private function jsGatewayBaseUrl(): string
    {
        $custom = trim((string)($this->config['js_gateway_base_url'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }
        return (string)($this->config['environment'] ?? 'sandbox') === 'production'
            ? 'https://ysgate.ysepay.com'
            : 'https://appdev.ysepay-test.com';
    }

    private function clientIp(): string
    {
        $ip = trim((string)request()->ip());
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? $ip : '';
    }

    private function assertNotificationAmount(string $outTradeNo, array $business): void
    {
        $amount = $business['amount'] ?? $business['totalAmount'] ?? null;
        if ($amount === null || $amount === '') {
            return;
        }
        $pay = Pay::where([
            ['site_id', '=', (int)($this->config['site_id'] ?? 0)],
            ['out_trade_no', '=', $outTradeNo],
        ])->field('money')->findOrEmpty();
        if (!$pay->isEmpty() && $this->toCent((float)$pay->money) !== (int)$amount) {
            throw new PayException('银盛支付通知金额与本地支付单不一致');
        }
    }

    private function paymentAlreadyFinished(string $outTradeNo): bool
    {
        return (int)Pay::where([
            ['site_id', '=', (int)($this->config['site_id'] ?? 0)],
            ['out_trade_no', '=', $outTradeNo],
        ])->value('status') === (int)PayDict::STATUS_FINISH;
    }

    private function resolveRefundNotification(array $business): Refund
    {
        $siteId = (int)($this->config['site_id'] ?? 0);
        $notificationOrderId = trim((string)($business['refundOrderId'] ?? $business['orderId'] ?? ''));
        if ($notificationOrderId === '') {
            throw new PayException('银盛退款通知缺少订单号');
        }

        $refund = Refund::where([
            ['site_id', '=', $siteId],
            ['refund_no', '=', $notificationOrderId],
        ])->field('refund_no,out_trade_no,money,status')->findOrEmpty();
        if (!$refund->isEmpty()) {
            return $refund;
        }

        $outTradeNo = trim((string)($business['origOrderId'] ?? $business['orderId'] ?? ''));
        $refunds = Refund::where([
            ['site_id', '=', $siteId],
            ['out_trade_no', '=', $outTradeNo],
        ])->whereIn('status', [RefundDict::WAIT, RefundDict::DEALING])
            ->field('refund_no,out_trade_no,money,status')
            ->order('id desc')
            ->limit(2)
            ->select();
        if ($refunds->count() !== 1) {
            throw new PayException(
                $refunds->isEmpty()
                    ? '银盛退款通知对应的本地退款单不存在'
                    : '银盛退款通知对应多笔处理中退款，无法安全确认'
            );
        }
        return $refunds->first();
    }

    private function refundAlreadyFinal(string $refundNo): bool
    {
        $status = (string)Refund::where([
            ['site_id', '=', (int)($this->config['site_id'] ?? 0)],
            ['refund_no', '=', $refundNo],
        ])->value('status');
        return in_array($status, [RefundDict::SUCCESS, RefundDict::FAIL, RefundDict::CANCEL], true);
    }

    private function successResponse()
    {
        return response('success', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    private function failResponse()
    {
        return response('fail', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
