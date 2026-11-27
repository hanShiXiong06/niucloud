<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\pay;

use addon\hsx_lead\app\service\cooperation\{EntityMembershipService,MembershipCostService,MembershipDepositService,OrderPaymentReadService,PaymentService,PromotionService,Rules,Store};
use app\dict\pay\{PayDict,RefundDict};
use core\exception\CommonException;
use think\facade\Db;

/** Only a successful matching framework refund can revoke paid subject rights. */
final class MembershipRefundSuccess
{
    public function handle(array $event): bool
    {
        if (($event['trade_type'] ?? '') !== PaymentService::TRADE_TYPE) return true;
        $site = (int) ($event['site_id'] ?? 0);
        $number = (string) ($event['refund_no'] ?? '');
        if ($site <= 0 || $number === '') throw new CommonException('主体会员退款核对缺少真实退款凭据');
        return Db::transaction(function () use ($site, $number): bool {
            PromotionService::lockSite($site);
            $refund = Db::name('pay_refund')->where('site_id', $site)->where('refund_no', $number)->where('status', RefundDict::SUCCESS)->lock(true)->find();
            if (!$refund) throw new CommonException('未找到成功退款凭据，未变更主体会员权益');
            if ($refund['type'] === PayDict::BALANCEPAY) {
                $balanceSource = Store::query('coop_order', $site)->where('id', (int) $refund['trade_id'])->where('out_trade_no', $refund['out_trade_no'])->lock(true)->find();
                if ($balanceSource) {
                    $balancePay = OrderPaymentReadService::payment($balanceSource, true);
                    if ($balancePay)OrderPaymentReadService::assertManualSource($balanceSource, $balancePay);
                }
            }
            if ($refund['type'] === PayDict::OFFLINEPAY) {
                $manualSource = Store::query('coop_order', $site)->where('id', (int) $refund['trade_id'])->where('out_trade_no', $refund['out_trade_no'])->lock(true)->find();
                if (!$manualSource)throw new CommonException('原线下退款业务单无法核对，未变更主体权益');
                \addon\hsx_lead\app\service\cooperation\ManualRefundService::assertRefundReceipt($site, $manualSource, $refund);
            }
            // A closed late-collected purchase has no grant to revoke, but its
            // financial lock may be resolved after complete original refunds.
            $closedPay = Db::name('pay')->where('site_id', $site)->where('out_trade_no', $refund['out_trade_no'])->where('trade_type', PaymentService::TRADE_TYPE)->where('status', PayDict::STATUS_FINISH)->lock(true)->find();
            if ($closedPay) {
                $closed = Store::query('coop_order', $site)->where('id', (int) $closedPay['trade_id'])->where('purpose', 'membership')->where('status', 'closed')->lock(true)->find();
                if ($closed) {
                    $closedRule = \addon\hsx_lead\app\support\Data::decode($closed['rule_json']);
                    if (($closedRule['requires_manual_review'] ?? '') === 'membership_closed_receipt_conflict') {
                        MembershipCostService::receiptRefund($site, $closed, $closedPay, true);
                        EntityMembershipService::resolveClosedReceipt($site, $closed);
                        return true;
                    }
                }
            }
            $source = Store::query('coop_order', $site)->where('out_trade_no', $refund['out_trade_no'])->where('purpose', 'membership')->where('status', 'paid')->lock(true)->find();
            if (!$source) return true;
            $pay = Db::name('pay')->where('site_id', $site)->where('out_trade_no', $refund['out_trade_no'])->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', $source['id'])->where('status', PayDict::STATUS_FINISH)->lock(true)->find();
            if (!$pay
                || $pay['main_type'] !== PayDict::MEMBER
                || (int) $pay['main_id'] !== (int) $source['member_id']
                || Rules::cents((string) $pay['money']) !== Rules::cents((string) $source['amount'])) throw new CommonException('原会员收款无法核对，未变更主体权益，请平台核对');
            if (Rules::cents((string) $refund['money']) === 0) return true;
            if (Rules::cents((string) $refund['money']) > Rules::cents((string) $pay['money'])) throw new CommonException('主体会员退款金额异常，未变更权益');
            EntityMembershipService::refund($site, $source, $refund);
            MembershipDepositService::refundConfirmed($site, $source);
            return true;
        });
    }
}
