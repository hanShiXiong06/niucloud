<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\dict\pay\PayDict;
use app\service\core\pay\CorePayService;
use core\exception\CommonException;
use think\facade\{Db,Log};

/** Cancelling a payment never cancels a delivered lead or creates a refund. */
final class PaymentOrderLifecycle
{
    public const UNPAID_TIMEOUT_SECONDS = 120;
    private ?\Closure $providerFactory;
    public function __construct(private Context $context, ?callable $providerFactory = null)
    {
        $this->providerFactory = $providerFactory === null ? null : \Closure::fromCallable($providerFactory);
    }

    private function own(): void
    {
        if ($this->context->memberId <= 0 || !Db::name('member')->where('site_id', $this->context->site)->where('member_id', $this->context->memberId)->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->find())throw new CommonException('请先登录有效会员账号');
    }
    public function orders(array $params = []): array
    {
        $this->own();
        $query = Store::query('coop_order', $this->context->site)->where('member_id', $this->context->memberId);
        foreach (['status' => ['unpaid', 'paid', 'closed'], 'purpose' => ['membership', 'commission']] as $key => $allowed) {
            $value = $params[$key] ?? '';
            if ($value !== '') {
                if (!is_string($value) || !in_array($value, $allowed, true))throw new CommonException('付款订单筛选条件无效');
                $query->where($key, $value);
            }
        }
        OrderPaymentReadService::filter($query, $params, $this->context->site);
        $page = Data::page($query, $params);
        $page['data'] = ManualPaymentService::withPendingScope($this->context->site, array_column($page['data'], 'id'), fn (): array => array_map([PaymentService::class, 'presentOrder'], $page['data']));
        $page['filters'] = OrderPaymentReadService::options();
        return $page;
    }
    public function close(int $id, array $input = [], bool $admin = false): array
    {
        if (!in_array($input['confirm'] ?? null, [true, 1, '1'], true))throw new CommonException('请确认关闭这笔未支付订单');
        if ($admin) {
            Store::permission($this->context, 'finance');
            $this->context->permission('hsx_lead_cooperation_order_close');
            if (!$this->context->ownership()->scope()['all_data'])throw new CommonException('关闭支付单还需要全站业务查看权限');
        } else $this->own();
        $row = Store::row('coop_order', $this->context->site, $id);
        if (!$admin && (int) $row['member_id'] !== $this->context->memberId)throw new CommonException('只能取消本人创建的付款订单');
        $reason = $admin ? Data::text($input, 'reason', 500, true) : '会员取消未支付订单';
        return self::closeSafely($this->context->site, $id, $admin ? 'admin' : 'member', $reason, $this->context->uid, $this->context->memberId, $this->providerFactory);
    }
    /** A customer resuming an expired quote uses the same safe timeout path as the schedule. */
    public function expireOwn(int $id): array
    {
        $this->own();
        $row = Store::row('coop_order', $this->context->site, $id);
        if ((int) $row['member_id'] !== $this->context->memberId)throw new CommonException('只能核对本人创建的付款订单');
        return self::closeSafely($this->context->site, $id, 'timeout', '未支付超时自动关闭', 0, $this->context->memberId, $this->providerFactory);
    }
    public static function expiresAt(array $row): int
    {
        // Use order creation, not update_time: opening the cashier or checking a
        // payment must not extend its life. The signed membership quote retains
        // its own maximum validity and cannot be revived by creating an order.
        $created = (int) ($row['create_time'] ?? 0);
        $deadline = $created + self::UNPAID_TIMEOUT_SECONDS;
        $rule = Data::decode($row['rule_json'] ?? null);
        if (($row['purpose'] ?? '') === 'membership')return min($deadline, (int) ($rule['quoted_at'] ?? $created) + 600);
        return $deadline;
    }
    public static function decorate(array $raw, array $presented): array
    {
        $rule = Data::decode($raw['rule_json'] ?? null);
        $closure = $rule['closure'] ?? [];
        $check = $rule['close_check'] ?? [];
        $reason = '';
        $gatewayCheck = false;
        if (($raw['status'] ?? '') !== 'unpaid')$reason = $raw['status'] === 'paid' ? '已支付订单不能取消，请联系平台处理退款' : '订单已关闭';
        elseif (!empty($rule['requires_manual_review']))$reason = '原支付仍待平台核对，不能直接关闭';
        else foreach (Db::name('pay')->where('site_id', (int) $raw['site_id'])->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', (int) $raw['id'])->whereNotIn('status', [PayDict::STATUS_WAIT, PayDict::STATUS_CANCEL])->select()->toArray() as $pay) {
            if ((int) $pay['status'] === (int) PayDict::STATUS_ING && PaymentGatewayReconciler::supports($pay))$gatewayCheck = true;
            else {
                $reason = '原收银台已收款或状态待核对，不能直接关闭';
                break;
            }
        }
        return $presented + ['can_close' => $reason === '', 'close_reason' => $reason, 'close_requires_check' => $gatewayCheck, 'close_action_label' => $gatewayCheck ? '核对后取消' : '取消订单', 'can_pay' => $reason === '' && !$gatewayCheck && ($raw['status'] ?? '') === 'unpaid' && self::expiresAt($raw) > time(),
            'close_result' => $raw['status'] === 'closed' ? 'closed' : (string) ($check['state'] ?? ''), 'close_message' => (string) ($check['message'] ?? ''),
            'expires_at' => self::expiresAt($raw), 'close_at' => (int) ($closure['at'] ?? 0), 'closure_reason' => (string) ($closure['reason'] ?? ''), 'closed_by' => (string) ($closure['by'] ?? '')];
    }
    private static function closeSafely(int $site, int $id, string $by, string $reason, int $operatorUid = 0, int $operatorMemberId = 0, ?callable $providerFactory = null): array
    {
        $connection = Db::connect();
        if ($connection->getPdo()->inTransaction())throw new CommonException('付款关闭需要独立执行，请结束当前业务操作后重试');
        $scope = (string) $connection->getConfig('database').':'.(string) $connection->getConfig('prefix');
        $key = 'lead_coop_pay:'.substr(hash('sha256', $scope.':'.$site.':'.$id), 0, 40);
        $locked = $connection->query('SELECT GET_LOCK(:lock_key, 3) AS acquired', ['lock_key' => $key], true);
        if ((int) ($locked[0]['acquired'] ?? 0) !== 1)throw new CommonException('该笔支付正在处理，请稍后刷新，不要重复操作');
        try {
            $snapshot = self::closeSnapshot($site, $id, $by, false);
            if (isset($snapshot['result']))return $snapshot['result'];
            $recovered = self::recoverFinished($site, $id, $snapshot);
            if ($recovered !== null)return $recovered;
            if ($snapshot['recover_receipt'] && !array_filter($snapshot['pays'], static fn (array $pay): bool => (int) $pay['status'] === (int) PayDict::STATUS_ING && PaymentGatewayReconciler::supports($pay)))return self::closeCheck($site, $id, 'paid_pending', '渠道已返回收款，但本地成功凭据仍待核对；未取消或再次扣款');
            foreach ($snapshot['pays'] as $original) {
                // Release site/order row locks before each potentially slow
                // channel request. The same-order advisory lock remains held.
                $fresh = self::closeSnapshot($site, $id, $by);
                if (isset($fresh['result']))return $fresh['result'];
                $recovered = self::recoverFinished($site, $id, $fresh);
                if ($recovered !== null)return $recovered;
                self::assertSameCloseBusiness($snapshot['row'], $fresh['row']);
                $pay = null;
                foreach ($fresh['pays'] as $candidate)if ((int) $candidate['id'] === (int) $original['id']) {
                    $pay = $candidate;
                    break;
                }
                if ($pay === null || (string) $pay['out_trade_no'] !== (string) $original['out_trade_no'] || (string) $pay['type'] !== (string) $original['type'] || (string) $pay['channel'] !== (string) $original['channel'])throw new CommonException('原付款流水已变化，未关闭业务，请刷新核对');
                if ((int) $pay['status'] === (int) PayDict::STATUS_CANCEL)continue;
                if ($fresh['recover_receipt'] && (int) $pay['status'] !== (int) PayDict::STATUS_ING)continue;
                if ((int) $pay['status'] === (int) PayDict::STATUS_WAIT) {
                    $closed = PaymentCloseScope::run($site, (string) $pay['out_trade_no'], static fn () => (new CorePayService())->close($site, (string) $pay['out_trade_no']));
                    $result = ['state' => $closed === true ? 'closed' : 'uncertain', 'message' => '原支付尚未确认关闭，请稍后核对，不要重新下单'];
                } else {
                    $reconciler = new PaymentGatewayReconciler($providerFactory);
                    $result = $fresh['recover_receipt'] ? $reconciler->recoverPaid($site, $pay) : $reconciler->reconcile($site, $pay);
                }
                // A callback may have won while HTTP was in flight. Its local
                // receipt takes precedence over a stale cancel/query response.
                $after = self::closeSnapshot($site, $id, $by);
                if (isset($after['result']))return $after['result'];
                self::assertSameCloseBusiness($snapshot['row'], $after['row']);
                $recovered = self::recoverFinished($site, $id, $after);
                if ($recovered !== null)return $recovered;
                if ($result['state'] !== 'closed')return self::closeCheck($site, $id, (string) $result['state'], (string) $result['message'], (string) $pay['out_trade_no']);
            }
            return Db::transaction(function () use ($site, $id, $by, $reason, $operatorUid, $operatorMemberId, $snapshot): array {
                $fresh = self::closeSnapshot($site, $id, $by);
                if (isset($fresh['result']))return $fresh['result'];
                $row = $fresh['row'];
                self::assertSameCloseBusiness($snapshot['row'], $row);
                foreach ($fresh['pays'] as $pay)if ((int) $pay['status'] !== (int) PayDict::STATUS_CANCEL)throw new CommonException('原支付未全部确认关闭，当前未关闭业务');
                $rule = $fresh['rule'];
                $rule['closure'] = ['at' => time(), 'by' => $by, 'reason' => $reason, 'operator_uid' => $operatorUid, 'operator_member_id' => $operatorMemberId];
                $rule['close_check'] = ['at' => time(), 'state' => 'closed', 'message' => '原付款已核实关闭'];
                Store::query('coop_order', $site)->where('id', $id)->update(['status' => 'closed', 'rule_json' => Data::json($rule), 'update_time' => time()]);
                Data::audit($site, $operatorUid, $operatorMemberId, 'coop_order', $id, 'closed', ['purpose' => $row['purpose'], 'by' => $by, 'reason' => $reason]);
                return PaymentService::presentOrder(Store::row('coop_order', $site, $id));
            });
        } finally {
            try {
                $connection->query('SELECT RELEASE_LOCK(:lock_key) AS released', ['lock_key' => $key], true);
            } catch (\Throwable $ignored) {
            }
        }
    }

    /** Local evidence only. This transaction never initializes a channel. */
    private static function closeSnapshot(int $site, int $id, string $by, bool $allowPaid = true): array
    {
        return Db::transaction(function () use ($site, $id, $by, $allowPaid): array {
            PromotionService::lockSite($site);
            $row = Store::row('coop_order', $site, $id, true);
            if ($row['status'] === 'closed')return ['result' => PaymentService::presentOrder($row)];
            if ($row['status'] === 'paid') {
                if (!$allowPaid)throw new CommonException('原订单已收款或状态已变化，不能取消，请刷新核对');
                $shown = PaymentService::presentOrder($row);
                $pending = !empty($shown['requires_manual_review']);
                return ['result' => array_replace($shown, ['close_result' => $pending ? 'paid_pending' : 'paid', 'close_message' => $pending ? '原单已收款，业务结果仍待平台核对；订单未取消' : '已核实原单收款，订单未取消'])];
            }
            if ($row['status'] !== 'unpaid')throw new CommonException('原订单已收款或状态已变化，不能取消，请刷新核对');
            $rule = Data::decode($row['rule_json']);
            $recoverReceipt = $by === 'timeout' && ($rule['requires_manual_review'] ?? '') === 'gateway_payment_receipt_review';
            if (!empty($rule['requires_manual_review']) && !$recoverReceipt)throw new CommonException('原支付仍待平台核对，未关闭订单');
            if (!in_array($row['purpose'], ['membership', 'commission'], true))throw new CommonException('未知付款业务，未执行关闭');
            if ($by === 'timeout' && self::expiresAt($row) > time())return ['result' => PaymentService::presentOrder($row)];
            $pays = Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', $id)->order('id')->lock(true)->select()->toArray();
            $knownNo = (string) $row['out_trade_no'] === '';
            foreach ($pays as $pay) {
                if ($pay['main_type'] !== PayDict::MEMBER || (int) $pay['main_id'] !== (int) $row['member_id'] || Rules::cents((string) $pay['money']) !== Rules::cents((string) $row['amount']))throw new CommonException('原支付流水归属或金额异常，未关闭订单');
                if (!in_array((int) $pay['status'], [(int) PayDict::STATUS_WAIT, (int) PayDict::STATUS_CANCEL, (int) PayDict::STATUS_ING, (int) PayDict::STATUS_FINISH], true))throw new CommonException('原收银台状态待核对；当前未关闭业务，请勿另开付款');
                if (PaymentService::hasUnconfirmedBalanceDebit($site, $pay))throw new CommonException('发现本次付款的账户扣款记录，未关闭订单，请核对到账情况');
                if ((string) $pay['out_trade_no'] === (string) $row['out_trade_no'])$knownNo = true;
            }
            if (!$knownNo)throw new CommonException('原付款流水去向待核实，未关闭订单');
            return ['row' => $row, 'rule' => $rule, 'pays' => $pays, 'recover_receipt' => $recoverReceipt];
        });
    }

    private static function assertSameCloseBusiness(array $original, array $current): void
    {
        foreach (['id', 'site_id', 'member_id', 'merchant_id', 'purpose', 'lead_id', 'amount', 'fee_amount', 'net_amount', 'create_time'] as $key)if ((string) ($original[$key] ?? '') !== (string) ($current[$key] ?? ''))throw new CommonException('订单归属、金额或期限已变化，未关闭业务，请刷新核对');
        if ($current['purpose'] === 'membership' && !hash_equals(MembershipCostService::pricingFingerprint($original, Data::decode($original['rule_json'])), MembershipCostService::pricingFingerprint($current, Data::decode($current['rule_json']))))throw new CommonException('原会员付款合同已变化，未关闭业务，请刷新核对');
    }

    private static function recoverFinished(int $site, int $id, array $snapshot): ?array
    {
        $finished = array_values(array_filter($snapshot['pays'], static fn (array $pay): bool => (int) $pay['status'] === (int) PayDict::STATUS_FINISH));
        if (!$finished)return null;
        $open = array_filter($snapshot['pays'], static fn (array $pay): bool => (int) $pay['status'] !== (int) PayDict::STATUS_CANCEL);
        if (count($finished) !== 1 || count($open) !== 1) {
            Db::transaction(function () use ($site, $id): void {
                PromotionService::lockSite($site);
                $row = Store::row('coop_order', $site, $id, true);
                if ($row['status'] !== 'unpaid')return;
                $rule = Data::decode($row['rule_json']);
                $rule['requires_manual_review'] = 'multiple_payment_receipts';
                Store::query('coop_order', $site)->where('id', $id)->update(['rule_json' => Data::json($rule)]);
            });
            return self::closeCheck($site, $id, 'paid_pending', '发现多笔未取消的付款流水且已有收款，未关闭或重复入账，请平台核对');
        }
        $paid = $finished[0];
        if ($snapshot['recover_receipt'])Db::transaction(function () use ($site, $id): void {
            PromotionService::lockSite($site);
            $row = Store::row('coop_order', $site, $id, true);
            $rule = Data::decode($row['rule_json']);
            if (($rule['requires_manual_review'] ?? '') === 'gateway_payment_receipt_review') {
                unset($rule['requires_manual_review']);
                Store::query('coop_order', $site)->where('id', $id)->update(['rule_json' => Data::json($rule)]);
            }
        });
        // confirm owns its own short financial transaction and post-commit
        // notification. Never run it under a surrounding close transaction.
        try {
            PaymentService::confirm(['site_id' => $site, 'trade_type' => PaymentService::TRADE_TYPE, 'trade_id' => $id, 'out_trade_no' => $paid['out_trade_no']]);
        } catch (\Throwable $error) {
            return self::closeCheck($site, $id, 'paid_pending', '原单已收款，业务结果尚未确认；订单未取消，请勿再次付款', (string) $paid['out_trade_no']);
        }
        $row = Store::row('coop_order', $site, $id);
        $pending = $row['status'] !== 'paid' || !empty(Data::decode($row['rule_json'])['requires_manual_review']);
        return self::closeCheck($site, $id, $pending ? 'paid_pending' : 'paid', $pending ? '原单已收款，业务结果仍待平台核对；订单未取消，请勿再次付款' : '已核实原单收款并补齐业务处理；订单未取消', (string) $paid['out_trade_no']);
    }
    private static function closeCheck(int $site, int $id, string $state, string $message, string $no = ''): array
    {
        return Db::transaction(function () use ($site, $id, $state, $message, $no): array {
            PromotionService::lockSite($site);
            $row = Store::row('coop_order', $site, $id, true);
            $rule = Data::decode($row['rule_json']);
            if ($row['status'] === 'closed')return PaymentService::presentOrder($row);
            if ($row['status'] === 'paid' && empty($rule['requires_manual_review'])) {
                $state = 'paid';
                $message = '已核实原单收款，订单未取消';
            }
            $rule['close_check'] = ['at' => time(), 'state' => $state, 'message' => $message];
            if ($state === 'paid_pending') {
                if (empty($rule['requires_manual_review']))$rule['requires_manual_review'] = 'gateway_payment_receipt_review';
                if ($row['out_trade_no'] === '' && $no !== '')$row['out_trade_no'] = $no;
            }
            Store::query('coop_order', $site)->where('id', $id)->update(['rule_json' => Data::json($rule), 'out_trade_no' => $row['out_trade_no'], 'update_time' => time()]);
            Data::audit($site, 0, 0, 'coop_order', $id, 'close_reconciled', ['state' => $state, 'message' => $message]);
            return PaymentService::presentOrder(Store::row('coop_order', $site, $id));
        });
    }
    /** Bounded framework schedule entry; reconciles original trades, never refunds. */
    public static function tick(int $siteLimit = 5, int $orderLimit = 50, ?callable $providerFactory = null): array
    {
        $now = time();
        $query = Db::name('hsx_lead_coop_order')->where('status', 'unpaid')
            ->whereIn('purpose', ['membership', 'commission'])
            ->where('create_time', '<=', $now - self::UNPAID_TIMEOUT_SECONDS);
        $sites = (clone $query)->group('site_id')->orderRaw('MIN(update_time) ASC')->limit(max(1, min(20, $siteLimit)))->column('site_id');
        $result = ['examined' => 0, 'closed' => 0, 'reconciled' => 0, 'blocked' => 0];
        foreach ($sites as $site) {
            $site = (int) $site;
            $ids = (clone $query)->where('site_id', $site)->order('update_time,id')->limit(max(1, min(100, $orderLimit)))->column('id');
            foreach ($ids as $id) {
                $result['examined']++;
                try {
                    $row = self::closeSafely($site, (int) $id, 'timeout', '未支付超时自动关闭', 0, 0, $providerFactory);
                    if ($row['status'] === 'closed')$result['closed']++;
                    elseif ($row['status'] === 'paid' && empty($row['requires_manual_review']))$result['reconciled']++;
                    else $result['blocked']++;
                } catch (\Throwable$error) {
                    $result['blocked']++;
                    $ruleRow = Store::query('coop_order', $site)->where('id', $id)->where('status', 'unpaid')->find();
                    if ($ruleRow) {
                        Store::query('coop_order', $site)->where('id', $id)->where('status', 'unpaid')->update(['update_time' => time()]);
                    }Log::warning('商户协作超时支付单未关闭，需核对原收银台：site='.$site.', order='.(int) $id.', type='.get_class($error));
                }
            }
        }return $result;
    }
}
