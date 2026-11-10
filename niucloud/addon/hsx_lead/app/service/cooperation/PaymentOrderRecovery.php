<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\dict\pay\PayDict;
use core\exception\CommonException;
use think\facade\Db;

/** Recover an original cashier attempt, never create a charge or replace a business order. */
final class PaymentOrderRecovery
{
    private ?\Closure $providerFactory;
    /** Provider injection is PHP-only for isolated tests, never a request argument. */
    public function __construct(private Context $context, ?callable $providerFactory = null)
    {
        $this->providerFactory = $providerFactory === null ? null : \Closure::fromCallable($providerFactory);
    }

    public function resume(int $id): array
    {
        $payments = new PaymentService($this->context);
        $payments->order($id); // Ownership must be established before any provider query.
        $site = $this->context->site;
        $connection = Db::connect();
        $scope = (string)$connection->getConfig('database') . ':' . (string)$connection->getConfig('prefix');
        $key = 'lead_coop_pay:' . substr(hash('sha256', $scope . ':' . $site . ':' . $id), 0, 40);
        $locked = $connection->query('SELECT GET_LOCK(:lock_key, 3) AS acquired', ['lock_key' => $key], true);
        if ((int)($locked[0]['acquired'] ?? 0) !== 1) throw new CommonException('正在核对这笔付款，请稍后重试，不会重新下单');
        try {
            return Db::transaction(function () use ($site, $id, $payments): array {
                PromotionService::lockSite($site);
                $row = Store::row('coop_order', $site, $id, true);
                if ((int)$row['member_id'] !== $this->context->memberId) throw new CommonException('只能核对本人创建的付款订单');
                if ($row['status'] !== 'unpaid') return $this->result($row, $row['status'] === 'paid' ? 'paid' : 'closed', '原付款单已关闭，请核对订单结果');
                $rule = Data::decode($row['rule_json']);
                if (!empty($rule['requires_manual_review'])) return $this->result($row, 'attention', PaymentService::reviewMessage($rule));
                if (!empty($rule['pricing_intent'])) return $this->result($row, 'uncertain', '平台正在调整本单金额，请稍后刷新原付款单');
                if (!in_array($row['purpose'], ['membership', 'commission'], true)) throw new CommonException('未知付款业务，未重新发起付款');

                $pays = Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)
                    ->where('trade_id', $id)->where('status', '<>', PayDict::STATUS_CANCEL)->order('id')->lock(true)->select()->toArray();
                foreach ($pays as $pay) {
                    if (($pay['main_type'] ?? '') !== PayDict::MEMBER || (int)$pay['main_id'] !== (int)$row['member_id']
                        || Rules::cents((string)$pay['money']) !== Rules::cents((string)$row['amount'])) {
                        throw new CommonException('原支付归属或金额不一致，当前未重新发起付款');
                    }
                }
                if (count($pays) > 1) return $this->result($row, 'uncertain', '发现多笔原付款记录，暂不重复付款，请核对原付款结果');
                foreach ($pays as $pay) {
                    if ((int)$pay['status'] === (int)PayDict::STATUS_FINISH) {
                        try { PaymentService::confirm(['site_id' => $site, 'trade_type' => PaymentService::TRADE_TYPE, 'trade_id' => $id, 'out_trade_no' => $pay['out_trade_no']]); }
                        catch (\Throwable $error) { return $this->result(Store::row('coop_order', $site, $id, true), 'paid_pending', '已确认收款，正在恢复会员权益，请稍后重试核对，不要再次付款'); }
                        return $this->result(Store::row('coop_order', $site, $id, true), 'paid', '支付已确认，权益已更新');
                    }
                }
                foreach ($pays as $pay) {
                    if (PaymentService::hasUnconfirmedBalanceDebit($site,$pay)) {
                        return $this->result($row,'attention','发现本次付款的账户扣款记录，请联系平台核对，暂勿重复支付');
                    }
                }
                // A persisted successful receipt wins over quote expiration.
                // Otherwise the established timeout path must prove that the
                // original is unpaid AND channel-closed before releasing it.
                if (PaymentOrderLifecycle::expiresAt($row) <= time()) {
                    return (new PaymentOrderLifecycle($this->context, $this->providerFactory))->expireOwn($id);
                }
                foreach ($pays as $pay) {
                    if ((int)$pay['status'] === (int)PayDict::STATUS_WAIT) continue;
                    if ((int)$pay['status'] !== (int)PayDict::STATUS_ING) return $this->result($row, 'uncertain', '原支付结果尚未确认，请稍后重试核对，不要重新下单');
                    $result = (new PaymentGatewayReconciler($this->providerFactory))->reconcile($site, $pay);
                    if ($result['state'] !== 'closed') {
                        return $this->result(Store::row('coop_order', $site, $id, true), (string)$result['state'],
                            $result['state'] === 'uncertain' ? '暂未取得明确的支付结果。原付款单已保留，稍后点击“核对并继续付款”即可重试。' : (string)$result['message']);
                    }
                }
                $row = Store::row('coop_order', $site, $id, true);
                try { $payments->assertPayable($id); }
                catch (CommonException $error) { return $this->result($row, 'blocked', $error->getMessage()); }
                return $this->result($row, 'ready', '上次付款尚未完成，可继续支付当前订单，不会重复创建开通订单。');
            });
        } finally {
            try { $connection->query('SELECT RELEASE_LOCK(:lock_key) AS released', ['lock_key' => $key], true); }
            catch (\Throwable $ignored) { /* The connection also releases advisory locks on close. */ }
        }
    }

    private function result(array $row, string $state, string $message): array
    {
        $presented = PaymentService::presentOrder($row);
        // A matching receipt is payment evidence, not proof that its business
        // delivery succeeded. confirm() deliberately preserves real money when
        // a blocked merchant or conflicting grant still needs review.
        if ($presented['requires_manual_review']) {
            $state = 'attention';
            $message = $presented['review_message'] ?: '原款项已收到，业务结果待平台核对，请勿再次付款';
        } elseif ($state === 'paid') {
            if ($presented['status'] !== 'paid') {
                $state = 'paid_pending';
                $message = '已核实原单收款，业务结果尚未完成，请稍后核对，不要再次付款';
            } else {
                $message = $presented['purpose'] === 'membership' ? '支付已确认，会员权益已更新' : '支付已确认，协作付佣已完成';
            }
        }
        $presented['payment_recovery'] = ['state' => $state, 'message' => $message];
        if (!in_array($state, ['ready', 'paid'], true)) $presented['can_pay'] = false;
        return $presented;
    }
}
