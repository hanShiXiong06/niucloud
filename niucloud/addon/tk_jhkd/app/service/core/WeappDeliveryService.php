<?php
declare(strict_types=1);

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\model\order\Order;
use addon\tk_jhkd\app\model\order\OrderAdd;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use app\model\member\Member;
use app\model\pay\Pay;
use app\service\core\weapp\CoreWeappService;
use core\base\BaseApiService;
use think\facade\Db;
use think\facade\Log;

/** Pure WeChat reporting is deferred; charging, refunds and courier state remain untouched. */
class WeappDeliveryService extends BaseApiService
{
    public function uploadShippingInfo($params)
    {
        return DeferredWeappDelivery::enqueue('order', is_array($params) ? $params : []);
    }

    public function uploadShippingInfoAdd($params)
    {
        return DeferredWeappDelivery::enqueue('add', is_array($params) ? $params : []);
    }

    public function notifyConfirmReceive($params = [])
    {
        return DeferredWeappDelivery::enqueue('receive', is_array($params) ? $params : []);
    }

    /** Called only after the HTTP response is finished. Re-read committed original receipt facts. */
    public function runDeferred(string $operation, array $params): array
    {
        $context = [
            'site_id' => (int)($params['site_id'] ?? 0),
            'order_id' => (string)($params['order_id'] ?? ''),
            'out_trade_no' => (string)($params['out_trade_no'] ?? ''),
            'operation' => $operation,
        ];
        $sent = false;
        try {
            if (!in_array($operation, ['order', 'add', 'receive'], true) || $context['site_id'] <= 0) {
                return $this->record($context, 'blocked', 'invalid_reporting_context');
            }
            if ($this->transactionOpen()) return $this->record($context, 'blocked', 'payment_transaction_not_committed');
            $settings = $this->settings($context['site_id']);
            if ((int)($settings['is_shipping'] ?? 0) !== 1) return $this->record($context, 'skipped', 'reporting_not_enabled');
            $facts = $this->loadContext($operation, $params);
            $pay = $facts['pay'];
            $context['pay_id'] = (int)($pay['id'] ?? 0);
            $context['out_trade_no'] = (string)$pay['out_trade_no'];
            $context['order_id'] = (string)($facts['order']['order_id'] ?? $context['order_id']);
            $payload = $operation === 'receive'
                ? WeappDeliveryPayload::receive($pay)
                : WeappDeliveryPayload::shipping($pay, (string)($facts['openid'] ?? ''), (string)$facts['description']);
            $identity = implode('|', [$context['site_id'], $operation, $pay['out_trade_no']]);
            $guard = $this->guard();
            $claim = $guard->claim($identity);
            if ($claim['status'] !== 'acquired') {
                return $this->record($context, $claim['status'] === 'done' ? 'duplicate' : $claim['status'],
                    $claim['status'] === 'unknown' ? 'previous_result_unknown_do_not_resend' : 'reporting_safety_gate');
            }
            // Persist unknown before invoking any mutation. If persistence fails, never send.
            $guard->beforeSend($identity, $claim['token']);
            $sent = true;
            $endpoint = $operation === 'receive' ? 'wxa/sec/order/notify_confirm_receive' : 'wxa/sec/order/upload_shipping_info';
            $result = $this->postWechat($context['site_id'], $endpoint, $payload);
            if (!array_key_exists('errcode', $result) || !preg_match('/^-?\d+$/D', (string)$result['errcode'])) {
                $guard->complete($identity, $claim['token'], 'unknown');
                return $this->record($context, 'unknown', 'unconfirmed_wechat_response_do_not_resend');
            }
            $code = (int)$result['errcode'];
            if ($code !== 0) {
                $guard->complete($identity, $claim['token'], 'rejected');
                return $this->record($context, 'rejected', 'wechat_explicit_rejection', $code);
            }
            $guard->complete($identity, $claim['token'], 'done');
            $outcome = $this->record($context, 'done', 'wechat_confirmed', 0);
            if ($operation !== 'receive') $this->configureMessagePath($context);
            return $outcome;
        } catch (\DomainException $error) {
            // Only our fixed reason codes may leave the process; an SDK DomainException is not trusted.
            $known = ['original_receipt_not_paid_weapp', 'original_receipt_not_wechat', 'original_wechat_order_identity_missing',
                'payer_weapp_openid_missing', 'shipping_description_invalid', 'original_receipt_number_missing',
                'original_receipt_not_found', 'receipt_business_mismatch', 'receipt_order_not_found', 'receipt_order_mismatch',
                'business_order_number_missing', 'business_order_not_found', 'original_paid_receipt_not_unique',
                'delivery_order_not_unique', 'delivery_member_mismatch', 'supplement_receipt_contract_not_verified'];
            $reason = in_array($error->getMessage(), $known, true) ? $error->getMessage() : 'local_safety_prerequisite_failed';
            return $this->record($context, $sent ? 'unknown' : 'blocked', $sent ? 'transport_result_unknown_do_not_resend' : $reason);
        } catch (\Throwable $error) {
            // SDK exception messages/URLs can contain access tokens: never write them here.
            return $this->record($context, $sent ? 'unknown' : 'blocked', $sent
                ? 'transport_or_state_result_unknown_do_not_resend' : 'local_safety_prerequisite_failed');
        }
    }

    protected function settings(int $siteId): array
    {
        return (array)(new CommonService())->getConfig($siteId);
    }

    protected function transactionOpen(): bool
    {
        $connection = Db::connect();
        $pdo = $connection->getPdo();
        // A fresh HttpEnd process may not have opened PDO yet; false does not mean a failed receipt.
        if (!$pdo) $pdo = $connection->connect();
        return $pdo->inTransaction();
    }

    protected function guard(): RedisWeappDeliveryGuard
    {
        return new RedisWeappDeliveryGuard();
    }

    /** All ORM reads use the primary connection and explicit tenant/business keys. */
    protected function loadContext(string $operation, array $params): array
    {
        $siteId = (int)$params['site_id'];
        $payFields = 'id,site_id,status,trade_type,trade_id,out_trade_no,trade_no,mch_id,channel,type,main_id,main_type';
        if ($operation === 'receive') {
            $outTradeNo = trim((string)($params['out_trade_no'] ?? ''));
            if ($outTradeNo === '') throw new \DomainException('original_receipt_number_missing');
            $pay = (new Pay())->master(true)->where('site_id', $siteId)->where('out_trade_no', $outTradeNo)
                ->field($payFields)->findOrEmpty()->toArray();
            if (!$pay) throw new \DomainException('original_receipt_not_found');
            $isAdd = (string)$pay['trade_type'] === $this->addTradeType();
            if (!$isAdd && (string)$pay['trade_type'] !== 'jhkdOrderPay') throw new \DomainException('receipt_business_mismatch');
            $model = $isAdd ? new OrderAdd() : new Order();
            $order = $model->master(true)->where('site_id', $siteId)->where('id', (int)$pay['trade_id'])->findOrEmpty()->toArray();
            if (!$order) throw new \DomainException('receipt_order_not_found');
            if (!empty($params['order_id']) && (string)$order['order_id'] !== (string)$params['order_id']) throw new \DomainException('receipt_order_mismatch');
            return ['pay' => $pay, 'order' => $order];
        }
        $orderNo = trim((string)($params['order_id'] ?? ''));
        if ($orderNo === '') throw new \DomainException('business_order_number_missing');
        $isAdd = $operation === 'add';
        $model = $isAdd ? new OrderAdd() : new Order();
        $order = $model->master(true)->where('site_id', $siteId)->where('order_id', $orderNo)->findOrEmpty()->toArray();
        if (!$order || (int)($order['id'] ?? 0) <= 0) throw new \DomainException('business_order_not_found');
        $query = (new Pay())->master(true)->where('site_id', $siteId)
            ->where('trade_type', $isAdd ? $this->addTradeType() : 'jhkdOrderPay')->where('trade_id', (int)$order['id'])
            ->where('status', 2);
        if ($isAdd) {
            if (empty($order['out_trade_no'])) throw new \DomainException('original_receipt_number_missing');
            $query->where('out_trade_no', (string)$order['out_trade_no']);
        } elseif (!empty($order['out_trade_no'])) {
            $query->where('out_trade_no', (string)$order['out_trade_no']);
        }
        $receipts = $query->field($payFields)->limit(2)->select()->toArray();
        if (count($receipts) !== 1) throw new \DomainException('original_paid_receipt_not_unique');
        $description = '快递运费补差价订单';
        if (!$isAdd) {
            // This installed delivery table has NO site_id; ownership is established above via Order.
            $deliveries = (new OrderDelivery())->master(true)->where('order_id', $orderNo)->limit(2)->select()->toArray();
            if (count($deliveries) !== 1) throw new \DomainException('delivery_order_not_unique');
            $delivery = $deliveries[0];
            if ((int)($delivery['member_id'] ?? 0) > 0 && (int)$delivery['member_id'] !== (int)$order['member_id']) throw new \DomainException('delivery_member_mismatch');
            $description = '聚合快递代下单-物品:' . (string)($delivery['goods'] ?? '') . '-' . (string)($delivery['weight'] ?? '') . 'kg';
        }
        // main_id is the persisted payer (CorePayService may change it for another payer).
        $memberId = (string)($receipts[0]['main_type'] ?? '') === 'member' && (int)($receipts[0]['main_id'] ?? 0) > 0
            ? (int)$receipts[0]['main_id'] : (int)($order['member_id'] ?? 0);
        $member = (new Member())->master(true)->where('site_id', $siteId)->where('member_id', $memberId)
            ->field('weapp_openid')->findOrEmpty()->toArray();
        return ['pay' => $receipts[0], 'order' => $order, 'openid' => (string)($member['weapp_openid'] ?? ''), 'description' => $description];
    }

    protected function addTradeType(): string
    {
        return 'jhkdOrderAddPay';
    }

    protected function postWechat(int $siteId, string $endpoint, array $payload): array
    {
        $app = CoreWeappService::app($siteId);
        $config = $app->getConfig();
        $retry = $config->get('http.retry', false);
        $maxRetries = $config->get('http.max_retries', 2);
        try {
            // Create a fresh client, not the SDK's possibly cached retry-enabled getClient().
            // Keep this application's original access-token provider/cache, including authorizer tokens.
            $config->set('http.retry', false);
            $config->set('http.max_retries', 0);
            return $app->createClient()->postJson($endpoint, [
                'json' => $payload, 'timeout' => 5.0, 'max_duration' => 8.0,
            ])->toArray();
        } finally {
            $config->set('http.retry', $retry);
            $config->set('http.max_retries', $maxRetries);
        }
    }

    protected function configureMessagePath(array $context): void
    {
        try {
            $result = $this->postWechat((int)$context['site_id'], 'wxa/sec/order/set_msg_jump_path', ['path' => 'addon/tk_jhkd/pages/orderlist']);
            if (!isset($result['errcode']) || (string)$result['errcode'] !== '0') $this->record($context, 'warning', 'message_jump_path_not_confirmed');
        } catch (\Throwable $error) {
            $this->record($context, 'warning', 'message_jump_path_request_failed');
        }
    }

    protected function record(array $context, string $status, string $reason, ?int $errcode = null): array
    {
        $data = array_merge($context, ['status' => $status, 'reason' => $reason]);
        if ($errcode !== null) $data['errcode'] = $errcode;
        try {
            Log::write('聚合快递微信发货上报 ' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), in_array($status, ['done', 'duplicate', 'skipped'], true) ? 'info' : 'warning');
        } catch (\Throwable $ignored) {
            // A logging sink failure must not reinterpret the persisted WeChat outcome.
        }
        return $data;
    }
}
