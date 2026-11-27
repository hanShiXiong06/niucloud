<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\service\virtualpay;

use core\exception\CommonException;

/** Pure wire contract. Amounts enter as integer fen; no business receipt is inferred here. */
final class VirtualPayProtocol
{
    public const QUERY_PATH = '/xpay/query_order';
    public const REFUND_PATH = '/xpay/refund_order';
    private const MAX_CENTS = 9999999999; // Existing DECIMAL(10,2) business ledger capacity.

    public static function payData(array $settings, array $order, string $sessionKey): array
    {
        $offer = self::text($settings['offer_id'] ?? null, 32, '虚拟支付OfferID');
        if (!preg_match('/^[0-9]+$/D', $offer))throw new CommonException('虚拟支付OfferID格式错误');
        $appKey = self::secret($settings['app_key'] ?? null, '虚拟支付签名凭据');
        $sessionKey = self::secret($sessionKey, '微信登录会话');
        $quantity = $order['quantity'] ?? 1;
        if ($quantity !== 1)throw new CommonException('当前虚拟权益订单必须按一份完整权益计价');
        $sign = [
            'offerId' => $offer,
            'buyQuantity' => 1,
            'env' => self::environment($settings['env'] ?? null),
            'currencyType' => 'CNY',
            'productId' => self::productId($order['product_id'] ?? null),
            'goodsPrice' => self::cents($order['amount_cents'] ?? null),
            'outTradeNo' => self::orderId($order['out_trade_no'] ?? null),
            'attach' => self::text($order['attach'] ?? '', 1024, '虚拟支付业务关联', true),
        ];
        $json = self::json($sign);
        return [
            'mode' => 'short_series_goods',
            'signData' => $json,
            'paySig' => hash_hmac('sha256', 'requestVirtualPayment&'.$json, $appKey),
            'signature' => hash_hmac('sha256', $json, $sessionKey),
        ];
    }

    public static function queryBody(string $openid, int $env, string $outTradeNo): array
    {
        return ['openid' => self::openid($openid), 'env' => self::environment($env), 'order_id' => self::orderId($outTradeNo)];
    }

    /** The exact JSON bytes returned here must be the bytes written to HTTP. */
    public static function signedBody(string $path, array $payload, string $appKey): array
    {
        self::validateRequest($path, $payload);
        $raw = self::json($payload);
        return ['body' => $raw, 'pay_sig' => hash_hmac('sha256', $path.'&'.$raw, self::secret($appKey, '虚拟支付签名凭据'))];
    }

    /** Keep documented fields/statuses intact. In particular 5/8 are not generic refund success. */
    public static function normalizeQuery(array $response): array
    {
        if (!isset($response['errcode']) || $response['errcode'] !== 0 || !is_array($response['order'] ?? null))
            throw new CommonException('虚拟支付查询尚未取得可核实的订单结果');
        $order = $response['order'];
        if (!is_int($order['status'] ?? null) || !in_array($order['status'], range(0, 10), true))
            throw new CommonException('虚拟支付订单状态格式异常，未确认到账');
        if (isset($order['order_type']) && (!is_int($order['order_type']) || !in_array($order['order_type'], [0, 1, 7, 8], true)))
            throw new CommonException('虚拟支付订单类型无法核实，未确认到账');
        foreach (['order_fee', 'left_fee', 'paid_fee', 'goods_price'] as $field) {
            if (array_key_exists($field, $order) && (!is_int($order[$field]) || $order[$field] < 0 || $order[$field] > self::MAX_CENTS))
                throw new CommonException('虚拟支付查询金额格式异常，未确认到账');
        }
        return $order;
    }

    public static function validateRequest(string $path, array $payload): void
    {
        if (!in_array($path, [self::QUERY_PATH, self::REFUND_PATH], true))throw new CommonException('未支持的虚拟支付接口');
        $common = ['openid', 'env', 'order_id', 'wx_order_id'];
        $refund = ['refund_order_id', 'left_fee', 'refund_fee', 'biz_meta', 'refund_reason', 'req_from'];
        if (array_diff(array_keys($payload), $path === self::QUERY_PATH ? $common : array_merge($common, $refund)))
            throw new CommonException('虚拟支付请求含未支持字段');
        self::openid($payload['openid'] ?? null);
        self::environment($payload['env'] ?? null);
        $hasOrder = array_key_exists('order_id', $payload);
        $hasWx = array_key_exists('wx_order_id', $payload);
        if ($hasOrder === $hasWx)throw new CommonException('虚拟支付必须提供唯一原订单编号');
        if ($hasOrder)self::orderId($payload['order_id']);
        if ($hasWx)self::providerId($payload['wx_order_id']);
        if ($path === self::QUERY_PATH)return;
        self::refundId($payload['refund_order_id'] ?? null);
        $left = self::cents($payload['left_fee'] ?? null);
        $fee = self::cents($payload['refund_fee'] ?? null);
        if ($fee > $left)throw new CommonException('退款金额超过渠道当前可退金额');
        self::text($payload['biz_meta'] ?? null, 1024, '退款业务关联', true);
        if (!is_int($payload['refund_reason'] ?? null) || !in_array($payload['refund_reason'], range(0, 5), true))
            throw new CommonException('虚拟支付退款原因无效');
        if (!is_int($payload['req_from'] ?? null) || !in_array($payload['req_from'], [1, 2, 3], true))
            throw new CommonException('虚拟支付退款来源无效');
    }

    public static function environment($value): int
    {
        if (!is_int($value) || !in_array($value, [0, 1], true))throw new CommonException('请明确虚拟支付正式或测试环境');
        return $value;
    }

    public static function cents($value): int
    {
        if (!is_int($value) || $value <= 0 || $value > self::MAX_CENTS)throw new CommonException('虚拟支付金额须为有效的整数分');
        return $value;
    }

    public static function orderId($value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,31}$/D', $value))
            throw new CommonException('虚拟支付订单编号须为8至32位字母数字或连接符，且不得以下划线开头');
        return $value;
    }

    public static function refundId($value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]{8,32}$/D', $value))throw new CommonException('虚拟支付退款编号格式错误');
        return $value;
    }

    public static function productId($value): string
    {
        $value = self::text($value, 20, '虚拟支付商品编号');
        if (!preg_match('/^[A-Za-z0-9_]+$/D', $value))throw new CommonException('虚拟支付商品编号仅支持字母、数字和下划线，不支持短横线');
        return $value;
    }

    public static function openid($value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $value))throw new CommonException('微信支付身份格式错误');
        return $value;
    }

    private static function providerId($value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/D', $value))throw new CommonException('虚拟支付渠道订单编号格式错误');
        return $value;
    }

    private static function secret($value, string $label): string
    {
        return self::text($value, 512, $label);
    }

    private static function text($value, int $max, string $label, bool $empty = false): string
    {
        if (!is_string($value) || (!$empty && $value === '') || strlen($value) > $max || preg_match('/[\x00-\x1f\x7f]/', $value))
            throw new CommonException($label.'未就绪或格式无效');
        return $value;
    }

    private static function json(array $value): string
    {
        try {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            throw new CommonException('虚拟支付请求无法编码，未提交渠道');
        }
    }
}
