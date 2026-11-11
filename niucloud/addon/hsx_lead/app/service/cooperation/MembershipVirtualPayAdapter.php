<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\virtualpay\VirtualPaySettings;
use addon\hsx_lead\app\support\Data;
use app\dict\pay\{PayDict, RefundDict};
use app\service\core\pay\CoreRefundService;
use core\exception\CommonException;
use think\facade\Db;

/** The cooperation membership side of virtual payment.
 *
 * The channel owns authenticated payment/refund facts and their retry state.
 * This adapter owns the original membership contract, financial allocations
 * and verification of native PaySuccess/RefundSuccess business delivery.
 * It never requests a WeChat session, payment, query or refund.
 */
final class MembershipVirtualPayAdapter
{
    /** Business policy chooses one rail; the channel settings only supply
     * account configuration and never decide which business may use it. */
    public static function usesVirtual(int $site, array $order, string $terminal = 'weapp'): bool
    {
        return $terminal === 'weapp' && ($order['purpose'] ?? '') === 'membership'
            && VirtualPaySettings::get($site, false)['payment_mode'] === 'virtual';
    }

    /** Cashier presentation consumes an already owned original business. A
     * failed virtual attempt never silently falls back to ordinary payment. */
    public static function channels(int $site, ?array $order, array $list, string $terminal): array
    {
        if ($order === null) return [];
        $virtual = self::usesVirtual($site, $order, $terminal);
        $allowed = $virtual ? [VirtualPaySettings::TYPE] : ['wechatpay', 'alipay', 'balancepay'];
        $result = [];
        foreach ($list as $item) {
            if (!is_array($item) || !in_array($item['key'] ?? '', $allowed, true)) continue;
            if (($item['key'] ?? '') === VirtualPaySettings::TYPE) {
                $settings = VirtualPaySettings::get($site, false);
                if ($terminal !== 'weapp' || !$settings['enabled']) continue;
                $item['name'] = '微信支付';
                $item['client_action'] = VirtualPaySettings::CLIENT_ACTION;
            }
            $result[] = $item;
        }
        return $result;
    }

    public function lockSite(int $site): void
    {
        PromotionService::lockSite($site);
    }

    /** Resolve one original, tenant-scoped business from a unique native pay. */
    public function resolve(int $site, array $pay, bool $lock = false): array
    {
        if ((int)($pay['site_id'] ?? 0) !== $site
            || ($pay['trade_type'] ?? '') !== PaymentService::TRADE_TYPE
            || ($pay['main_type'] ?? '') !== PayDict::MEMBER
            || (int)($pay['main_id'] ?? 0) <= 0
            || !in_array((string)($pay['type'] ?? ''), ['', VirtualPaySettings::TYPE], true)) {
            throw new CommonException('此付款业务或原支付方式不支持虚拟支付');
        }
        $business = Store::row('coop_order', $site, (int)$pay['trade_id'], $lock);
        if ($business['purpose'] !== 'membership'
            || (int)$business['member_id'] !== (int)$pay['main_id']
            || Rules::cents((string)$business['amount']) !== Rules::cents((string)$pay['money'])
            || Rules::cents((string)$pay['money']) <= 0) {
            throw new CommonException('虚拟支付仅用于一致的会员权益付款');
        }
        MembershipCostService::assertSnapshot($business, Data::decode($business['rule_json']));
        return $business;
    }

    public function assertCanPrepare(int $site, array $pay, array $business): void
    {
        (new PaymentService(new Context($site, 0, (int)$pay['main_id'])))->assertPayable((int)$business['id'], true);
        if ($business['status'] !== 'unpaid' || PaymentOrderLifecycle::expiresAt($business) <= time()) {
            throw new CommonException('本次付款已完成、关闭或报价已过期，请刷新订单');
        }
    }

    public function product(array $business, array $settings): array
    {
        return MembershipVirtualProductService::product($business, $settings);
    }

    /** The native hook activates a term at paid_at. Replay still compares the
     * originally signed quotation rather than that later activated period. */
    public function fingerprint(array $business): string
    {
        $rule = Data::decode($business['rule_json']);
        if (isset($rule['quoted_period_start_at'], $rule['quoted_period_end_at'])) {
            $rule['period_start_at'] = $rule['quoted_period_start_at'];
            $rule['period_end_at'] = $rule['quoted_period_end_at'];
        }
        return MembershipCostService::pricingFingerprint($business, $rule);
    }

    /** Native PaySuccess normally delivers this; retain an idempotent explicit
     * verification/recovery when hook cache or prior delivery was interrupted. */
    public function confirm(int $site, array $business, string $number): bool
    {
        PaymentService::confirm(['site_id' => $site, 'trade_type' => PaymentService::TRADE_TYPE,
            'trade_id' => (int)$business['id'], 'out_trade_no' => $number]);
        return $this->requiresReview($site, (int)$business['id']);
    }

    public function requiresReview(int $site, int $id): bool
    {
        $current = Store::row('coop_order', $site, $id);
        return $current['status'] !== 'paid' || !empty(Data::decode($current['rule_json'])['requires_manual_review']);
    }

    /** A native original-route refund is not authorized by a provider number
     * alone; validate its member business, exact amount and original payment. */
    public function frameworkRefund(int $site, array $pay, string $refundNo, int $amount, bool $lock = false): array
    {
        $business = $this->resolve($site, $pay, $lock);
        $query = Db::name('pay_refund')->where('site_id', $site)->where('refund_no', $refundNo);
        if ($lock) $query->lock(true);
        $refund = $query->find();
        if ((int)$pay['status'] !== (int)PayDict::STATUS_FINISH || !$refund
            || $refund['out_trade_no'] !== $pay['out_trade_no'] || $refund['type'] !== VirtualPaySettings::TYPE
            || Rules::cents((string)$refund['money']) !== $amount
            || (string)$refund['trade_type'] !== PaymentService::TRADE_TYPE
            || (int)$refund['trade_id'] !== (int)$business['id']) {
            throw new CommonException('虚拟支付退款与原收款不对应');
        }
        return $refund;
    }

    /** Persist only a reversible business hold before the channel commits its
     * verified external refund fact. No native refund hook runs in this step. */
    public function externalRefundPending(int $site, array $pay): void
    {
        $business = $this->resolve($site, $pay, true);
        $rule = Data::decode($business['rule_json']);
        if (empty($rule['requires_manual_review'])) {
            $rule['requires_manual_review'] = 'virtual_external_refund_pending';
            Store::query('coop_order', $site)->where('id', $business['id'])->update([
                'rule_json' => Data::json($rule), 'update_time' => time()]);
        }
    }

    /** Project an already authenticated external-route refund into the native
     * receipt and existing RefundSuccess hooks. This never submits a refund.
     * Caller holds the original channel/business transaction and preserves the
     * durable provider fact independently when any hook throws.
     *
     * @return array{status:string,refund_no:string,requires_review:bool}
     */
    public function completeExternalRefund(int $site, array $pay, array $fact): array
    {
        $business = $this->resolve($site, $pay, true);
        $amount = (int)$fact['amount_cents'];
        if ((int)$pay['status'] !== (int)PayDict::STATUS_FINISH || $amount <= 0
            || $amount > Rules::cents((string)$pay['money']) || empty($fact['wx_refund_id']) || (int)$fact['id'] <= 0) {
            throw new CommonException('外部退款关联凭据不完整');
        }
        $rule = Data::decode($business['rule_json']);
        $originals = Db::name('pay_refund')->where('site_id', $site)
            ->where('out_trade_no', $pay['out_trade_no'])->lock(true)->select()->toArray();
        foreach ($originals as $original) {
            if ($original['type'] !== VirtualPaySettings::TYPE || $original['trade_type'] !== PaymentService::TRADE_TYPE
                || (int)$original['trade_id'] !== (int)$business['id']) {
                throw new CommonException('原生退款归属与外部退款不一致');
            }
        }
        $parts = MembershipCostService::refundedComponents($business, $rule, $originals);
        $remaining = Rules::cents((string)$business['amount']) - $parts['total'];
        if ($amount > $remaining) throw new CommonException('已确认退款累计超过原实付，未重复回退权益');
        $pure = Rules::cents($rule['deposit_amount']) === 0 && Rules::cents($rule['experience_amount']) === 0;
        $processable = $business['status'] === 'paid' || ($business['status'] === 'closed' && $amount === $remaining);
        if ($parts['pending'] || $parts['review'] || !$processable || (!$pure && $amount !== $remaining)) {
            $this->externalRefundReview($site, $business, $fact);
            return ['status' => 'attention', 'refund_no' => '', 'requires_review' => true];
        }
        $allocation = ['deposit' => '0.00', 'service' => Rules::amount($amount), 'experience' => '0.00', 'quantity' => '0.00'];
        if (!$pure) {
            foreach (['deposit' => 'deposit_amount', 'service' => 'service_amount', 'experience' => 'experience_amount', 'quantity' => 'experience_quantity'] as $key => $field) {
                $allocation[$key] = Rules::amount(Rules::cents($rule[$field]) - $parts[$key]);
            }
        }
        $reserved = FrameworkRefundReservation::create($site, $business, Rules::amount($amount), '已核实微信原路外部退款');
        $rule['membership_refund_allocations'][$reserved['refund_no']] = $allocation;
        if (in_array($rule['requires_manual_review'] ?? '', ['virtual_external_refund_pending', 'virtual_external_refund_review'], true)) {
            unset($rule['requires_manual_review']);
        }
        Store::query('coop_order', $site)->where('id', $business['id'])->update([
            'rule_json' => Data::json($rule), 'update_time' => time()]);
        Db::name('pay_refund')->where('site_id', $site)->where('id', $reserved['id'])->where('status', RefundDict::WAIT)->update([
            'refund_type' => RefundDict::BACK, 'pay_refund_no' => $fact['wx_refund_id'], 'refund_time' => time()]);
        $done = (new CoreRefundService())->refundSuccess($site, ['out_trade_no' => $pay['out_trade_no'],
            'refund_no' => $reserved['refund_no'], 'trade_type' => PaymentService::TRADE_TYPE, 'trade_id' => (int)$business['id']]);
        if ($done !== true) throw new CommonException('外部退款已核实，权益回退尚未完成，请稍后核对');
        Data::audit($site, 0, (int)$business['member_id'], 'coop_order', (int)$business['id'], 'virtual_external_refund_confirmed', [
            'virtual_refund_id' => (int)$fact['id'], 'refund_no' => $reserved['refund_no'],
            'amount_cents' => $amount, 'out_trade_no' => $pay['out_trade_no']]);
        $current = Store::row('coop_order', $site, (int)$business['id']);
        return ['status' => 'success', 'refund_no' => $reserved['refund_no'],
            'requires_review' => !empty(Data::decode($current['rule_json'])['requires_manual_review'])];
    }

    private function externalRefundReview(int $site, array $business, array $fact): void
    {
        $rule = Data::decode($business['rule_json']);
        if (empty($rule['requires_manual_review']) || ($rule['requires_manual_review'] ?? '') === 'virtual_external_refund_pending') {
            $rule['requires_manual_review'] = 'virtual_external_refund_review';
            Store::query('coop_order', $site)->where('id', $business['id'])->update([
                'rule_json' => Data::json($rule), 'update_time' => time()]);
        }
        $recorded = Data::query('audit', $site)->where('entity_type', 'coop_order')->where('entity_id', $business['id'])
            ->where('action', 'virtual_external_refund_review')->whereLike('detail_json', '%"virtual_refund_id":' . (int)$fact['id'] . ',%')->find();
        if (!$recorded) {
            Data::audit($site, 0, (int)$business['member_id'], 'coop_order', (int)$business['id'], 'virtual_external_refund_review', [
                'virtual_refund_id' => (int)$fact['id'], 'amount_cents' => (int)$fact['amount_cents'], 'out_trade_no' => $business['out_trade_no']]);
        }
    }
}
