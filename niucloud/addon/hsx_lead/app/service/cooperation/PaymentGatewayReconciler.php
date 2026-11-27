<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\support\Data;
use app\dict\pay\{OnlinePayDict,PayDict};
use app\service\core\pay\{CorePayEventService,CorePayService};
use think\facade\Db;

/** Strict plugin-owned cancellation; never CorePayService::check's NOTPAY loop
 * or CorePayService::close's ambiguous ING fallback. The factory is a PHP-only
 * sender double for isolated tests, never a request argument or site setting.
 */
final class PaymentGatewayReconciler
{
    private ?\Closure $providerFactory;
    public function __construct(?callable $providerFactory = null)
    {
        $this->providerFactory = $providerFactory === null ? null : \Closure::fromCallable($providerFactory);
    }
    public static function supports(array $pay): bool
    {
        return in_array($pay['type'] ?? '', [PayDict::WECHATPAY, PayDict::ALIPAY, \addon\hsx_lead\app\service\virtualpay\VirtualPaySettings::TYPE], true) && in_array($pay['channel'] ?? '', ['weapp', 'wechat', 'h5', 'pc', 'app'], true) && is_string($pay['out_trade_no'] ?? null) && $pay['out_trade_no'] !== '';
    }
    /** Foreground polling and operator checks never initiate a channel close. */
    public function check(int $site, array $pay): array
    {
        if ((int) ($pay['site_id'] ?? 0) !== $site || !self::supports($pay))return ['state' => 'uncertain', 'message' => '原付款渠道信息待核对，未发起扣款或关闭订单'];
        try {
            $provider = $this->providerFactory ? ($this->providerFactory)($site, (string) $pay['channel'], (string) $pay['type']) : (new CorePayEventService())->init($site, (string) $pay['channel'], (string) $pay['type']);
            $result = $provider->getOrder((string) $pay['out_trade_no']);
            $status = is_array($result) ? ($result['status'] ?? '') : '';
            if (in_array($status, [OnlinePayDict::SUCCESS, OnlinePayDict::TRADE_FINISHED], true))return $this->paid($site, $pay);
            if (is_array($result) && !empty($result['receipt_received']))return ['state' => 'paid_pending', 'message' => '已核实收款，正在恢复开通；请勿重复付款'];
            if ($status === OnlinePayDict::CLOSED)return $this->closed($site, $pay);
            return ['state' => 'uncertain', 'message' => '本次付款尚未确认完成，系统会继续核对；未重新扣款或关闭订单'];
        } catch (\Throwable $error) {
            return ['state' => 'uncertain', 'message' => '暂时无法核对支付渠道，原单已保留，请稍后重试'];
        }
    }
    public function reconcile(int $site, array $pay): array
    {
        if ((int) ($pay['site_id'] ?? 0) !== $site || !self::supports($pay))return ['state' => 'uncertain', 'message' => '原支付渠道信息不完整，请由平台核对，未关闭订单'];
        try {
            $provider = $this->providerFactory ? ($this->providerFactory)($site, (string) $pay['channel'], (string) $pay['type']) : (new CorePayEventService())->init($site, (string) $pay['channel'], (string) $pay['type']);
            $result = $provider->getOrder((string) $pay['out_trade_no']);
            $status = is_array($result) ? ($result['status'] ?? '') : '';
            if (in_array($status, [OnlinePayDict::SUCCESS, OnlinePayDict::TRADE_FINISHED], true))return $this->paid($site, $pay);
            if (is_array($result) && !empty($result['receipt_received']))return ['state' => 'paid_pending', 'message' => '已核实收款，正在恢复开通；请勿重复付款'];
            if ($status === OnlinePayDict::CLOSED)return $this->closed($site, $pay);
            if ($status !== OnlinePayDict::NOTPAY)return ['state' => 'uncertain', 'message' => '支付渠道尚未确认未付款或已关闭，请稍后核对原单，不要重复付款'];
            // The virtual driver currently has no verified native close API:
            // its close() only queries this same original again. Do not claim
            // cancellation was requested or hold the transaction for three
            // provider reads. NOTPAY is not evidence that a late receipt is
            // impossible, so preserve the original and never create a retry.
            if (($pay['type'] ?? '') === \addon\hsx_lead\app\service\virtualpay\VirtualPaySettings::TYPE)
                return ['state' => 'uncertain', 'message' => '本次付款尚未完成，订单未关闭。可返回继续使用；如已扣款，请核对付款结果，勿重复付款。'];
            // NOTPAY authorizes a close request, not a local cancellation. Even
            // close=true is not final evidence; a payer can win this race.
            try {
                $provider->close((string) $pay['out_trade_no']);
            } catch (\Throwable $ignored) {
            }
            $after = $provider->getOrder((string) $pay['out_trade_no']);
            $status = is_array($after) ? ($after['status'] ?? '') : '';
            if (in_array($status, [OnlinePayDict::SUCCESS, OnlinePayDict::TRADE_FINISHED], true))return $this->paid($site, $pay);
            if ($status === OnlinePayDict::CLOSED)return $this->closed($site, $pay);
            return ['state' => 'uncertain', 'message' => '已请求核对取消，但渠道尚未确认关闭；请稍后刷新原单，不要重新下单'];
        } catch (\Throwable $error) {
            return ['state' => 'uncertain', 'message' => '支付渠道暂未返回可核实的结果；原单保留，请稍后刷新核对'];
        }
    }
    /** Revisit an acknowledged paid result after a local hook failed. This is
     * query-only: even a contradictory NOTPAY/CLOSED cannot authorize a close. */
    public function recoverPaid(int $site, array $pay): array
    {
        if ((int) ($pay['site_id'] ?? 0) !== $site || !self::supports($pay))return ['state' => 'paid_pending', 'message' => '原收款渠道凭据仍待核对，请勿再次付款'];
        try {
            $provider = $this->providerFactory ? ($this->providerFactory)($site, (string) $pay['channel'], (string) $pay['type']) : (new CorePayEventService())->init($site, (string) $pay['channel'], (string) $pay['type']);
            $result = $provider->getOrder((string) $pay['out_trade_no']);
            if (is_array($result) && in_array($result['status'] ?? '', [OnlinePayDict::SUCCESS, OnlinePayDict::TRADE_FINISHED], true))return $this->paid($site, $pay);
        } catch (\Throwable $error) {
        }
        return ['state' => 'paid_pending', 'message' => '原单曾返回收款，当前业务仍待核对；未关闭或再次扣款'];
    }
    public function recoverLocalReceipt(int $site, array $pay): array
    {
        $current = Db::name('pay')->where('site_id', $site)->where('id', (int) ($pay['id'] ?? 0))->find();
        if (!$current || (int) $current['status'] !== (int) PayDict::STATUS_FINISH
            || $current['trade_type'] !== PaymentService::TRADE_TYPE)
                return ['state' => 'uncertain', 'message' => '本地尚无已确认收款凭据'];
        return $this->paid($site, $current);
    }
    private function closed(int $site, array $pay): array
    {
        return Db::transaction(function () use ($site, $pay): array {
            PromotionService::lockSite($site);
            // A local successful receipt always wins over an obsolete close query.
            $current = Db::name('pay')->where('site_id', $site)->where('id', (int) $pay['id'])->lock(true)->find();
            if (!$current)return ['state' => 'uncertain', 'message' => '原支付记录已变化，未关闭业务'];
            if ((int) $current['status'] === (int) PayDict::STATUS_FINISH)return $this->paid($site, $current);
            if ((int) $current['status'] !== (int) PayDict::STATUS_CANCEL && PaymentCloseScope::run($site, (string) $pay['out_trade_no'], static fn () => (new CorePayService())->payClose($site, ['out_trade_no' => $pay['out_trade_no'], 'reason' => '渠道已确认关闭'])) !== true)return ['state' => 'uncertain', 'message' => '渠道已关闭，本地付款状态仍待核对'];
            return ['state' => 'closed', 'message' => '支付渠道已确认关闭'];
        });
    }
    private function paid(int $site, array $pay): array
    {
        try {
            $current = Db::name('pay')->where('site_id', $site)->where('id', (int) $pay['id'])->lock(true)->find();
            if (!$current)return ['state' => 'paid_pending', 'message' => '渠道已收款，原付款记录待平台核对；请勿再次付款'];
            // Only the virtual driver holds and validates the signed source
            // receipt. A status string alone must never mint a native receipt.
            if (($pay['type'] ?? '') === \addon\hsx_lead\app\service\virtualpay\VirtualPaySettings::TYPE
                && (int) $current['status'] !== (int) PayDict::STATUS_FINISH)
                    return ['state' => 'paid_pending', 'message' => '原渠道收款仍在恢复中，请稍后核对；不会再次扣款'];
            $order = Store::row('coop_order', $site, (int) $pay['trade_id'], true);
            $rule = Data::decode($order['rule_json']);
            if ($order['status'] === 'unpaid' && ($rule['requires_manual_review'] ?? '') === 'gateway_payment_receipt_review') {
                unset($rule['requires_manual_review']);
                Store::query('coop_order', $site)->where('id', $order['id'])->update(['rule_json' => Data::json($rule)]);
            }
            if ((int) $current['status'] !== (int) PayDict::STATUS_FINISH)(new CorePayService())->paySuccess($site, ['out_trade_no' => $pay['out_trade_no'], 'type' => $pay['type']]);
            // The registered PaySuccess hook normally confirms this. Calling
            // its idempotent business service also handles a missing hook cache.
            PaymentService::confirm(['site_id' => $site, 'trade_type' => PaymentService::TRADE_TYPE, 'trade_id' => (int) $pay['trade_id'], 'out_trade_no' => $pay['out_trade_no']]);
            $order = Store::row('coop_order', $site, (int) $pay['trade_id'], true);
            if ($order['status'] !== 'paid' || !empty(Data::decode($order['rule_json'])['requires_manual_review']))return ['state' => 'paid_pending', 'message' => '原单已收款，业务结果仍待平台核对；未取消订单，请勿再次付款'];
            return ['state' => 'paid', 'message' => '已核实原单收款，订单没有取消，请查看付款结果'];
        } catch (\Throwable $error) {
            // Return, never throw: an outer cancellation transaction must not
            // roll back a successful receipt already acknowledged by the core.
            return ['state' => 'paid_pending', 'message' => '渠道已收款，业务结果待平台核对；订单未取消，请勿再次付款'];
        }
    }
}
