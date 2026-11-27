<?php
declare(strict_types=1);

namespace addon\tk_jhkd\app\service\core;

/** Pure payload builder. Only original, paid receipt facts may identify a WeChat order. */
class WeappDeliveryPayload
{
    public static function orderKey(array $pay): array
    {
        if ((int)($pay['status'] ?? -1) !== 2 || (string)($pay['channel'] ?? '') !== 'weapp') {
            throw new \DomainException('original_receipt_not_paid_weapp');
        }
        $type = (string)($pay['type'] ?? '');
        if (!in_array($type, ['wechatpay', 'fastpay_wechatpay'], true)) {
            throw new \DomainException('original_receipt_not_wechat');
        }
        $transactionId = trim((string)($pay['trade_no'] ?? ''));
        // Both installed drivers were source-verified: callback transaction_id -> pay.trade_no.
        // Do not extend this allowlist to another aggregator without verifying its receipt mapping.
        if (preg_match('/^\d{20,64}$/D', $transactionId)) {
            return ['order_number_type' => 2, 'transaction_id' => $transactionId];
        }
        $merchant = trim((string)($pay['mch_id'] ?? ''));
        $outTradeNo = trim((string)($pay['out_trade_no'] ?? ''));
        if (preg_match('/^\d{5,32}$/D', $merchant) && preg_match('/^[A-Za-z0-9_\-*]{1,64}$/D', $outTradeNo)) {
            return ['order_number_type' => 1, 'mchid' => $merchant, 'out_trade_no' => $outTradeNo];
        }
        throw new \DomainException('original_wechat_order_identity_missing');
    }

    public static function shipping(array $pay, string $openid, string $description, ?int $now = null): array
    {
        $openid = trim($openid);
        if (!preg_match('/^[A-Za-z0-9_\-]{1,128}$/D', $openid)) {
            throw new \DomainException('payer_weapp_openid_missing');
        }
        $description = trim($description);
        if ($description === '' || !preg_match('/^(.{1,100})/us', $description, $match)) {
            throw new \DomainException('shipping_description_invalid');
        }
        return [
            'order_key' => self::orderKey($pay),
            'logistics_type' => 3, // Keep the plugin's virtual delivery semantics.
            'delivery_mode' => 1,
            'shipping_list' => [['item_desc' => $match[1]]],
            'upload_time' => date('c', $now ?? time()),
            'payer' => ['openid' => $openid],
            'is_all_delivered' => true,
        ];
    }

    public static function receive(array $pay, ?int $now = null): array
    {
        $key = self::orderKey($pay);
        $data = ['received_time' => $now ?? time()];
        if ($key['order_number_type'] === 2) {
            $data['transaction_id'] = $key['transaction_id'];
        } else {
            $data['merchant_id'] = $key['mchid'];
            $data['merchant_trade_no'] = $key['out_trade_no'];
        }
        return $data;
    }
}
