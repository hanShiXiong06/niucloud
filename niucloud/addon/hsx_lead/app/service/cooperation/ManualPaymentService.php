<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\dict\member\MemberAccountTypeDict;
use app\dict\pay\PayDict;
use app\service\core\member\CoreMemberAccountService;
use app\service\core\pay\CorePayService;
use app\service\core\paytype\CoreBalanceService;
use core\exception\CommonException;
use think\facade\{Db,Log};

/** An attested real WeChat receipt funds and consumes the original payer's
 * balance atomically. This is never a client-supplied payment-success flag. */
final class ManualPaymentService
{
    public const SCHEMA = 'hsx.lead.manual.receipt.v1';
    public const BALANCE_MODE = 'balance_recharge_v1';
    public const RECHARGE_TYPE = 'hsx_lead_manual_receipt_recharge';
    public const METHODS = ['bank' => '银行转账', 'wechat' => '微信转账', 'alipay' => '支付宝转账', 'cash' => '现金', 'other' => '其他'];
    private const TABLE = 'hsx_lead_manual_receipt';
    /** PHP call-stack capability. No request flag can authorize offline delivery. */
    private static array $delivery = [];
    /** Bounded synchronous read scopes, never a cross-request/site cache. */
    private static array $pendingScopes = [];
    private ?\Closure $providerFactory;

    public function __construct(private Context $context, ?callable $providerFactory = null)
    {
        $this->providerFactory = $providerFactory === null ? null : \Closure::fromCallable($providerFactory);
    }

    public function preview(int $id, array $input = []): array
    {
        $this->permission();
        if (array_diff(array_keys($input), ['amount', 'request_id']))throw new CommonException('收款预览参数无效，请刷新后核对');
        $row = Store::row('coop_order', $this->context->site, $id);
        self::purpose($row);
        $amount = self::money($input['amount'] ?? '99.00');
        $message = '确认已收到线下微信款后，系统将为原付款会员充值，并立即以余额支付本订单；两步同时成功或同时回滚。';
        $allowed = $row['status'] === 'unpaid';
        $rule = Data::decode($row['rule_json']);
        if (!$allowed)$message = $row['status'] === 'closed' ? '订单已关闭，不能收款、充值或重新开通；不会新建或重开订单。' : '原订单已收款，不能再次确认收款。';
        elseif (!empty($rule['requires_manual_review'])) {
            $allowed = false;
            $message = '原支付或权益尚待核对，请先处理原订单。';
        } elseif (PaymentOrderLifecycle::expiresAt($row) <= time()) {
            $allowed = false;
            $message = '原订单已超过支付有效期，不能确认收款；请先由客户重新下单。';
        }
        if (isset($input['amount']) && $amount !== (string) $row['amount']) {
            $allowed = false;
            $message = '实收金额必须与订单应付金额一致；此处不会改价、补款或只支付部分金额。';
        }
        $query = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('kind', 'payment')
            ->where(function ($query) use ($id): void {$query->where('order_id', $id)->whereOr('target_order_id', $id);});
        if (isset($input['request_id']) && $input['request_id'] !== '')$query->where('request_id', self::request($input['request_id']));
        $existing = $query->order('id desc')->find();
        if ($existing) {
            $allowed = false;
            $message = '已有实际到账记录，请核对原记录，不要再次收款。';
        }
        $payer = Db::name('member')->where('site_id', $this->context->site)->where('member_id', (int) $row['member_id'])->field('member_id,member_no,nickname,mobile')->find();
        return ['order' => PaymentService::presentOrder($row), 'payer' => $payer ? ['member_id' => (int) $payer['member_id'], 'member_no' => (string) $payer['member_no'], 'nickname' => (string) $payer['nickname'], 'mobile' => (string) $payer['mobile']] : null, 'default_amount' => '99.00', 'order_amount' => (string) $row['amount'], 'amount_matches' => $amount === (string) $row['amount'], 'settlement_mode' => self::BALANCE_MODE, 'source_fingerprint' => self::fingerprint($row),
            'methods' => [['value' => 'wechat', 'label' => '线下微信收款']],
            'membership_costs' => ['service_amount' => (string) ($rule['service_amount'] ?? '0.00'), 'deposit_amount' => (string) ($rule['deposit_amount'] ?? '0.00'), 'experience_amount' => (string) ($rule['experience_amount'] ?? '0.00')],
            'can_confirm' => $allowed,
            'receipt' => $existing ? $this->present($existing) : null, 'message' => $message];
    }

    public function confirm(int $id, array $input): array
    {
        $this->permission();
        $this->outsideTransaction();
        $data = $this->input($input);
        try {
            $receipt = Db::transaction(function () use ($id, $data): array {
                PromotionService::lockSite($this->context->site);
                $source = Store::row('coop_order', $this->context->site, $id, true);
                self::purpose($source);
                $old = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('kind', 'payment')->where('request_id', $data['request_id'])->lock(true)->find();
                if ($old) {
                    if ((int) $old['order_id'] !== $id || !hash_equals((string) (Data::decode($old['snapshot_json'])['input_fingerprint'] ?? ''), $data['input_fingerprint']))throw new CommonException('该收款请求已用于其他内容，请核对原收款记录；未重复入账');
                    return $old;
                }
                $this->assertOriginalPayable($source);
                if (!hash_equals(self::fingerprint($source), $data['expected_fingerprint']))throw new CommonException('原订单费用或权益已变化，请刷新核对后再登记实际到账');
                if ($data['amount'] !== (string) $source['amount'])throw new CommonException('实收金额必须与订单应付金额一致；未登记收款、未充值，也未修改订单价格。');
                $data['account_label'] = '线下微信收款·管理员'.$this->context->uid;
                $data['external_no'] = 'SYS-MANUAL-'.substr(hash('sha256', $this->context->site.':'.$data['request_id']), 0, 40);
                $data['reference_hash'] = self::referenceHash('payment', 'wechat', $data['account_label'], $data['external_no']);
                $same = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('reference_hash', $data['reference_hash'])->lock(true)->find();
                if ($same) {
                    if ($same['kind'] === 'payment' && (int) $same['order_id'] === $id && hash_equals((string) (Data::decode($same['snapshot_json'])['input_fingerprint'] ?? ''), $data['input_fingerprint']))return $same;
                    throw new CommonException('这笔到账流水已登记，请核对原记录，不能重复用于其他订单');
                }
                $active = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('kind', 'payment')
                    ->where(function ($query) use ($id): void {$query->where('order_id', $id)->whereOr('target_order_id', $id);})->lock(true)->find();
                if ($active)throw new CommonException('该订单已有线下收款记录，请继续原记录；不能再次登记收款');
                $rule = Data::decode($source['rule_json']);
                $snapshot = ['schema' => self::SCHEMA, 'settlement_mode' => self::BALANCE_MODE, 'acknowledged' => true, 'input_fingerprint' => $data['input_fingerprint'], 'source_fingerprint' => $data['expected_fingerprint'],
                    'source' => ['order_id' => $id, 'merchant_id' => (int) $source['merchant_id'], 'member_id' => (int) $source['member_id'], 'purpose' => $source['purpose'],
                        'amount' => (string) $source['amount'], 'level_code' => (string) ($rule['level_code'] ?? ''), 'cycle' => (string) ($rule['cycle'] ?? ''), 'buy_experience' => ($rule['buy_experience'] ?? false) === true]];
                $now = time();
                $row = ['site_id' => $this->context->site, 'kind' => 'payment', 'order_id' => $id, 'target_order_id' => $id, 'member_id' => (int) $source['member_id'], 'source_receipt_id' => 0,
                    'out_trade_no' => '', 'refund_no' => '', 'request_id' => $data['request_id'], 'method' => $data['method'], 'account_label' => $data['account_label'], 'external_no' => $data['external_no'],
                    'reference_hash' => $data['reference_hash'], 'amount' => $data['amount'], 'received_at' => $now, 'operator_uid' => $this->context->uid, 'status' => 'recorded', 'reason' => $data['reason'],
                    'message' => '实际到账已登记，正在核对原支付及业务入账；请勿再次收款。', 'snapshot_json' => Data::json($snapshot), 'create_time' => $now, 'update_time' => $now];
                $new = (int) Db::name(self::TABLE)->insertGetId($row);
                $this->context->audit('manual_receipt', $new, 'recorded', ['order_id' => $id, 'amount' => $data['amount'], 'method' => $data['method']]);
                return $row + ['id' => $new];
            });
        } catch (CommonException $error) {
            throw $error;
        } catch (\Throwable $error) {
            Log::warning('人工到账登记未完成：site='.$this->context->site.', order='.$id.', type='.get_class($error));
            throw new CommonException('实际到账登记未完成，请保留原凭据并稍后重试同一请求；未确认入账，请勿重复收款。');
        }
        return $this->retry((int) $receipt['id']);
    }

    public function retry(int $id): array
    {
        $this->permission();
        $this->outsideTransaction();
        $receipt = $this->row($id);
        if ($receipt['status'] === 'posted')return $this->present($receipt);
        $order = Store::row('coop_order', $this->context->site, (int) $receipt['order_id']);
        if ($order['status'] === 'closed')return $this->present($receipt);
        return $this->locked((int) $receipt['order_id'], function () use ($id): array {
            try {
                $this->advance($id);
            } catch (\Throwable $error) {
                $message = $error instanceof CommonException ? $error->getMessage() : '实际到账已记录，业务入账尚未完成，请稍后核对原记录；请勿再次收款。';
                preg_match('/SQLSTATE\[([A-Z0-9]+)\]/', $error->getMessage(), $sqlState);
                Log::warning('人工实际收款待入账：site='.$this->context->site.', receipt='.$id.', type='.get_class($error).', source='.basename($error->getFile()).':'.$error->getLine().', sqlstate='.($sqlState[1] ?? ''));
                $this->attention($id, $message);
            }
            return $this->present($this->row($id));
        });
    }

    public function read(int $id): array
    {
        $this->permission();
        return $this->present($this->row($id));
    }

    /** Complete only an already-attested receipt's local recharge and payment;
     * never initiate an external transfer or accept a new scheduler receipt. */
    public static function tick(int $limit = 10): array
    {
        $result = ['examined' => 0, 'posted' => 0, 'attention' => 0, 'blocked' => 0, 'available' => true];
        try {
            $rows = Db::name(self::TABLE)->where('kind', 'payment')->whereIn('status', ['recorded', 'processing', 'attention'])
                ->order('update_time asc,id asc')->limit(max(1, min(50, $limit)))->select()->toArray();
        } catch (\Throwable $error) {
            if (str_contains($error->getMessage(), '42S02') || str_contains($error->getMessage(), '1146')) {
                $result['available'] = false;
                return $result;
            }throw $error;
        }
        foreach ($rows as $row) {
            $result['examined']++;
            $original = Store::query('coop_order', (int) $row['site_id'])->where('id', (int) $row['order_id'])->find();
            if (!$original || $original['status'] === 'closed') {
                $result['blocked']++;
                continue;
            }
            try {
                // The actor comes only from the immutable server-created
                // attestation. Current role/status/all-data permission is
                // checked afresh; a task never grants itself administrator.
                $context = new Context((int) $row['site_id'], (int) $row['operator_uid']);
                $service = new self($context);
                $service->permission();
                $service->snapshot($row);
            } catch (\Throwable $error) {
                $result['blocked']++;
                Db::name(self::TABLE)->where('site_id', (int) $row['site_id'])->where('id', (int) $row['id'])->where('status', '<>', 'posted')
                    ->update(['status' => 'attention', 'message' => '原确认人的财务权限或原到账凭据已变化，自动入账已暂停，请有权限管理员核对。', 'update_time' => time()]);
                Log::warning('人工收款自动补偿已暂停：site='.(int) $row['site_id'].', receipt='.(int) $row['id'].', type='.get_class($error));
                continue;
            }
            try {
                $restored = $service->retry((int) $row['id']);
                $result[$restored['status'] === 'posted' ? 'posted' : 'attention']++;
            } catch (\Throwable $error) {
                $result['blocked']++;
                // Contention or an unavailable local dependency is not proof
                // that the original operator lost permission or money moved.
                Db::name(self::TABLE)->where('site_id', (int) $row['site_id'])->where('id', (int) $row['id'])->where('status', '<>', 'posted')
                    ->update(['status' => 'attention', 'message' => '原收款记录暂不能继续入账，款项凭据已保留，稍后将继续核对；请勿重复收款。', 'update_time' => time()]);
                Log::warning('人工收款补偿等待重试：site='.(int) $row['site_id'].', receipt='.(int) $row['id'].', type='.get_class($error));
            }
        }
        return $result;
    }

    private function advance(int $id): void
    {
        $receipt = $this->row($id);
        if ($receipt['status'] === 'posted')return;
        $snapshot = $this->snapshot($receipt);
        $target = (int) $receipt['order_id'];
        if (($snapshot['settlement_mode'] ?? '') !== self::BALANCE_MODE)throw new CommonException('原线下收款记录需要平台核对，不能改用新余额收款流程；未重复充值。');
        $order = Store::row('coop_order', $this->context->site, $target);
        $this->identity($order, $snapshot['source']);
        if ($order['status'] === 'closed')throw new CommonException('原订单已关闭，不能继续收款、充值或重新开通。');
        // External provider reconciliation must commit before any independent
        // local receipt-delivery transaction; never roll a real online receipt
        // back merely because this distinct offline receipt cannot be applied.
        $this->reconcile($order, $receipt);
        $order = Store::row('coop_order', $this->context->site, (int) $order['id']);
        if ($order['status'] === 'paid') {
            $current = $this->row($id);
            if ($current['out_trade_no'] !== '' && hash_equals((string) $order['out_trade_no'], (string) $current['out_trade_no'])) {
                $this->finish($id, $order);
                return;
            }
            throw new CommonException('原订单已通过其他付款收款，本笔线下到账已保留待核对；未再次开通或分佣，请处理多收款项。');
        }
        $receipt = $this->row($id);
        if (Rules::cents((string) $order['amount']) !== Rules::cents((string) $receipt['amount'])) {
            throw new CommonException('实际到账与原订单应付金额不同，不能充值并支付；未修改或重建订单。');
        }
        $target = (int) $order['id'];
        $this->locked($target, fn () => $this->deliver($id, $target));
    }

    private function reconcile(array $order, array $receipt): void
    {
        $site = $this->context->site;
        $rows = Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', $order['id'])->order('id')->select()->toArray();
        foreach ($rows as $pay) {
            if ($pay['main_type'] !== PayDict::MEMBER || (int) $pay['main_id'] !== (int) $order['member_id'] || Rules::cents((string) $pay['money']) !== Rules::cents((string) $order['amount']))throw new CommonException('原支付的身份或金额与订单不一致，实际到账已保留，请核对原记录。');
            if ((int) $pay['status'] === (int) PayDict::STATUS_CANCEL)continue;
            if ((int) $pay['status'] === (int) PayDict::STATUS_FINISH) {
                PaymentService::confirm(['site_id' => $site, 'trade_type' => PaymentService::TRADE_TYPE, 'trade_id' => (int) $order['id'], 'out_trade_no' => $pay['out_trade_no']]);
                return;
            }
            if (PaymentService::hasUnconfirmedBalanceDebit($site, $pay))throw new CommonException('原订单存在余额扣款，实际到账已保留，请先核对原扣款；未重复入账。');
            if ((int) $pay['status'] === (int) PayDict::STATUS_ING) {
                if (!PaymentGatewayReconciler::supports($pay))throw new CommonException('原付款渠道尚不能核实关闭，实际到账已保留，请核对原付款。');
                $result = (new PaymentGatewayReconciler($this->providerFactory))->reconcile($site, $pay);
                if ($result['state'] === 'paid')return;
                if ($result['state'] !== 'closed')throw new CommonException('原线上付款结果尚未明确，线下到账已登记；未重复开通，请稍后核对入账。');
            } elseif ((int) $pay['status'] !== (int) PayDict::STATUS_WAIT)throw new CommonException('原收银台状态尚待核对，实际到账已保留，未重复入账。');
        }
    }

    private function deliver(int $id, int $target): void
    {
        // A competing administrator may briefly hold one of the same rows.
        // MySQL rolls the complete local transaction back on 40001. Retry only
        // that bounded local unit, never the external provider reconciliation.
        for ($attempt = 0;$attempt < 3;$attempt++) {
            try {
                $this->deliverTransaction($id, $target);
                return;
            } catch (\Throwable $error) {
                if ($attempt === 2 || !str_contains($error->getMessage(), 'SQLSTATE[40001]') || Db::connect()->getPdo()->inTransaction())throw $error;
            }
        }
    }

    private function deliverTransaction(int $id, int $target): void
    {
        Db::transaction(function () use ($id, $target): void {
            $this->permission();
            $site = $this->context->site;
            PromotionService::lockSite($site);
            $order = Store::row('coop_order', $site, $target, true);
            $receipt = $this->row($id, true);
            $snapshot = $this->snapshot($receipt);
            if ($receipt['status'] === 'posted')return;
            if ((int) $receipt['target_order_id'] !== $target || Rules::cents((string) $receipt['amount']) !== Rules::cents((string) $order['amount']))throw new CommonException('实际到账与最终订单金额不一致，未开通权益，请核对。');
            $this->identity($order, $snapshot['source']);
            PaymentService::assertReplacementChain($site, $order, true);
            if ($order['status'] === 'paid') {
                $this->finish($id, $order);
                return;
            }
            if (!hash_equals((string) ($snapshot['source_fingerprint'] ?? ''), self::fingerprint($order)))throw new CommonException('原订单费用或权益已变化，不能按旧收款登记充值，请平台核对。');
            $key = $site.':'.$target;
            self::$delivery[$key] = (self::$delivery[$key] ?? 0) + 1;
            try {
                (new PaymentService(new Context($site, 0, (int) $order['member_id'])))->assertPayable($target);
                $pays = Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', $target)->where('status', '<>', PayDict::STATUS_CANCEL)->lock(true)->select()->toArray();
                if (count($pays) > 1)throw new CommonException('最终订单存在多笔未取消流水，实际到账已保留，请先核对。');
                $core = new CorePayService();
                if ($pays) {
                    $pay = $pays[0];
                    if ((int) $pay['status'] !== (int) PayDict::STATUS_WAIT)throw new CommonException('原付款尚未核实关闭，未重复入账。');
                    $model = $core->findPayInfoByOutTradeNo($site, (string) $pay['out_trade_no']);
                } else {
                    $model = $core->createByTrade($site, PaymentService::TRADE_TYPE, $target);
                    $pay = $model->toArray();
                }
                $no = (string) $pay['out_trade_no'];
                $voucher = 'manual-receipt:'.$id;
                if ($pay['main_type'] !== PayDict::MEMBER || (int) $pay['main_id'] !== (int) $order['member_id'] || Rules::cents((string) $pay['money']) !== Rules::cents((string) $receipt['amount']))throw new CommonException('原支付身份或金额不符，未充值或扣款。');
                $this->assertOriginalPayable($order);
                $member = Db::name('member')->where('site_id', $site)->where('member_id', (int) $order['member_id'])->lock(true)->find();
                if (!$member)throw new CommonException('原付款会员不存在或不属于本站，未充值或扣款。');
                $before = Rules::cents((string) $member['balance']);
                $cents = Rules::cents((string) $receipt['amount']);
                if ($before < 0 || $before + $cents > 9999999999)throw new CommonException('会员余额超出可安全入账范围，未充值或扣款。');
                if (Db::name('member_account_log')->where('site_id', $site)->where('member_id', (int) $order['member_id'])->where('account_type', MemberAccountTypeDict::BALANCE)
                    ->where(function ($query) use ($voucher, $no): void {$query->where('related_id', $voucher)->whereOr('related_id', $no);})->lock(true)->find())throw new CommonException('原充值或消费账目已存在，请核对原付款；未重复充值。');
                $model->save(['channel' => 'pc']);
                $rechargeId = (int) (new CoreMemberAccountService())->addLog($site, (int) $order['member_id'], MemberAccountTypeDict::BALANCE, $receipt['amount'], self::RECHARGE_TYPE, '管理员确认线下微信收款，充值后支付原订单', $voucher);
                (new CoreBalanceService())->pay(['site_id' => $site, 'out_trade_no' => $no, 'money' => $receipt['amount']]);
                $debits = Db::name('member_account_log')->where('site_id', $site)->where('member_id', (int) $order['member_id'])->where('account_type', MemberAccountTypeDict::BALANCE)->where('from_type', 'order')->where('related_id', $no)->lock(true)->select()->toArray();
                if (count($debits) !== 1 || self::ledgerCents((string) $debits[0]['account_data']) !== -$cents)throw new CommonException('未找到唯一余额消费账目，充值与消费已撤销。');
                $after = (string) Db::name('member')->where('site_id', $site)->where('member_id', (int) $order['member_id'])->value('balance');
                if (Rules::cents($after) !== $before)throw new CommonException('充值与消费后余额不一致，整笔操作已撤销。');
                $snapshot['core_payment'] = ['out_trade_no' => $no, 'type' => PayDict::BALANCEPAY, 'order_fingerprint' => self::fingerprint($order), 'merchant_id' => (int) $order['merchant_id'], 'member_id' => (int) $order['member_id'], 'amount' => (string) $order['amount'], 'voucher' => $voucher,
                    'recharge_log_id' => $rechargeId, 'debit_log_id' => (int) $debits[0]['id'], 'member_balance_before' => Rules::amount($before), 'member_balance_after' => $after];
                Db::name(self::TABLE)->where('site_id', $site)->where('id', $id)->update(['out_trade_no' => $no, 'status' => 'processing', 'snapshot_json' => Data::json($snapshot), 'update_time' => time()]);
                // The core balance driver really consumed the funded balance.
                // Do not label a synthetic offline callback as a WeChat payment.
                $core->paySuccess($site, ['out_trade_no' => $no, 'type' => PayDict::BALANCEPAY, 'trade_no' => $receipt['external_no'], 'voucher' => $voucher]);
                PaymentService::confirm(['site_id' => $site, 'trade_type' => PaymentService::TRADE_TYPE, 'trade_id' => $target, 'out_trade_no' => $no]);
                $this->finish($id, Store::row('coop_order', $site, $target, true));
            } finally {
                if (--self::$delivery[$key] === 0)unset(self::$delivery[$key]);
            }
        });
    }

    private function finish(int $id, array $order): void
    {
        $receipt = $this->row($id, true);
        $pay = Db::name('pay')->where('site_id', $this->context->site)->where('out_trade_no', $receipt['out_trade_no'])->find();
        if (!$pay || (int) $pay['status'] !== (int) PayDict::STATUS_FINISH || !in_array($pay['type'], [PayDict::OFFLINEPAY, PayDict::BALANCEPAY], true))throw new CommonException('实际到账记录尚未关联有效支付流水，请核对后重试。');
        self::assertReceipt($this->context->site, $order, $pay, true);
        $rule = Data::decode($order['rule_json']);
        $posted = $order['status'] === 'paid' && hash_equals((string) $receipt['out_trade_no'], (string) $order['out_trade_no']) && empty($rule['requires_manual_review']);
        if (!$posted)throw new CommonException('原订单或权益未完成入账，本次充值和余额消费均已撤销；原实际收款登记保留待核对。');
        Db::name(self::TABLE)->where('site_id', $this->context->site)->where('id', $id)->update(['status' => $posted ? 'posted' : 'attention', 'message' => $posted ? '已核实线下收款，业务入账已完成。' : '已核实线下收款，业务或权益仍需核对；请勿再次收款。', 'update_time' => time()]);
        $this->context->audit('manual_receipt', $id, $posted ? 'posted' : 'delivery_attention', ['order_id' => (int) $order['id'], 'amount' => $receipt['amount'], 'out_trade_no' => $receipt['out_trade_no']]);
    }

    public static function assertPaymentReceipt(int $site, array $order, array $pay): void
    {
        self::assertReceipt($site, $order, $pay, true);
    }

    public static function assertReceipt(int $site, array $order, array $pay, bool $lock = false): array
    {
        if ($site <= 0 || (int) ($order['site_id'] ?? 0) !== $site || (int) ($pay['site_id'] ?? 0) !== $site || !in_array($pay['type'] ?? '', [PayDict::OFFLINEPAY, PayDict::BALANCEPAY], true) || ($pay['trade_type'] ?? '') !== PaymentService::TRADE_TYPE || (int) $pay['trade_id'] !== (int) $order['id'])throw new CommonException('人工收款凭据与业务不符，未确认支付成功');
        $query = Db::name(self::TABLE)->where('site_id', $site)->where('kind', 'payment')->where('target_order_id', (int) $order['id'])->where('out_trade_no', (string) $pay['out_trade_no']);
        if ($lock)$query->lock(true);
        $rows = $query->limit(2)->select()->toArray();
        if (count($rows) !== 1)throw new CommonException('缺少唯一人工到账凭据，未开通权益或增加收益');
        $row = $rows[0];
        $snapshot = Data::decode($row['snapshot_json']);
        $binding = $snapshot['core_payment'] ?? [];
        if (($snapshot['schema'] ?? '') !== self::SCHEMA || ($snapshot['acknowledged'] ?? false) !== true || !in_array($row['status'], ['recorded', 'processing', 'posted', 'attention'], true)
            || (int) $row['operator_uid'] <= 0 || (int) $row['received_at'] <= 0 || (int) $row['received_at'] > time() + 60 || $row['external_no'] === '' || $row['account_label'] === ''
            || !isset(self::METHODS[$row['method']]) || (int) $row['member_id'] !== (int) $order['member_id'] || (int) $pay['main_id'] !== (int) $row['member_id'] || $pay['main_type'] !== PayDict::MEMBER
            || Rules::cents((string) $row['amount']) !== Rules::cents((string) $order['amount']) || Rules::cents((string) $pay['money']) !== Rules::cents((string) $row['amount'])
            || !hash_equals(self::referenceHash('payment', $row['method'], $row['account_label'], $row['external_no']), (string) $row['reference_hash'])
            || ($binding['out_trade_no'] ?? '') !== (string) $pay['out_trade_no'] || (int) ($binding['merchant_id'] ?? 0) !== (int) $order['merchant_id'] || (int) ($binding['member_id'] ?? 0) !== (int) $row['member_id']
            || ($binding['amount'] ?? '') !== (string) $row['amount'] || !hash_equals((string) ($binding['order_fingerprint'] ?? ''), self::fingerprint($order))
            || ($pay['voucher'] ?? '') !== ($binding['voucher'] ?? null) || ($pay['trade_no'] ?? '') !== $row['external_no'])throw new CommonException('人工到账凭据的金额、归属或流水不一致，未开通权益或增加收益');
        if ($pay['type'] === PayDict::BALANCEPAY) {
            if (($snapshot['settlement_mode'] ?? '') !== self::BALANCE_MODE || ($binding['type'] ?? '') !== PayDict::BALANCEPAY || $row['method'] !== 'wechat' || $binding['voucher'] !== 'manual-receipt:'.$row['id'])throw new CommonException('人工余额收款缺少充值及消费证明，未确认支付成功');
            $recharge = (int) ($binding['recharge_log_id'] ?? 0);
            $debit = (int) ($binding['debit_log_id'] ?? 0);
            if ($recharge <= 0 || $debit <= 0 || $recharge === $debit)throw new CommonException('人工余额收款缺少两条独立账户账目，未确认支付成功');
            $logs = Db::name('member_account_log')->where('site_id', $site)->where('member_id', (int) $row['member_id'])->where('account_type', MemberAccountTypeDict::BALANCE)->whereIn('id', [$recharge, $debit])->column('*', 'id');
            $a = $logs[$recharge] ?? [];
            $b = $logs[$debit] ?? [];
            $amount = Rules::cents((string) $row['amount']);
            $before = Rules::cents($binding['member_balance_before'] ?? '');
            if (count($logs) !== 2 || ($a['from_type'] ?? '') !== self::RECHARGE_TYPE || ($a['related_id'] ?? '') !== $binding['voucher'] || Rules::cents((string) ($a['account_data'] ?? '0.00')) !== $amount
                || ($b['from_type'] ?? '') !== 'order' || ($b['related_id'] ?? '') !== $pay['out_trade_no'] || self::ledgerCents((string) ($b['account_data'] ?? '0.00')) !== -$amount
                || Rules::cents((string) ($a['account_sum'] ?? '0.00')) !== $before + $amount || Rules::cents((string) ($b['account_sum'] ?? '0.00')) !== $before
                || Rules::cents($binding['member_balance_after'] ?? '') !== $before)throw new CommonException('人工收款充值与消费账目不完整或不匹配，未确认支付成功');
        } elseif (isset($snapshot['settlement_mode']))throw new CommonException('人工收款的资金路径与支付类型不一致，未确认支付成功');
        return $row;
    }

    /** Ordinary online balance payments need no manual evidence. A persisted
     * receipt or explicit internal marker must always pass the full proof. */
    public static function hasPaymentReceipt(int $site, int $orderId, string $number): bool
    {
        try {
            return Db::name(self::TABLE)->where('site_id', $site)->where('kind', 'payment')->where('target_order_id', $orderId)->where('out_trade_no', $number)->value('id') !== null;
        } catch (\Throwable $error) {
            if (str_contains($error->getMessage(), '42S02') || str_contains($error->getMessage(), '1146'))return false;
            throw $error;
        }
    }

    public static function assertNoPendingReceipt(int $site, int $orderId): void
    {
        if (isset(self::$delivery[$site.':'.$orderId]))return;
        // Old deployments may not yet have applied this optional feature's
        // explicit table upgrade. Ordinary online payments remain unchanged;
        // manual confirmation itself never creates the table implicitly.
        try {
            $exists = Db::name(self::TABLE)->where('site_id', $site)->where('kind', 'payment')->whereIn('status', ['recorded', 'processing', 'attention'])
                ->where(function ($query) use ($orderId): void {$query->where('order_id', $orderId)->whereOr('target_order_id', $orderId);})->value('id');
        } catch (\Throwable $error) {
            if (str_contains($error->getMessage(), '42S02') || str_contains($error->getMessage(), '1146'))return;
            throw $error;
        }
        if ($exists !== null)throw new CommonException('已登记本单线下到账，正在核对入账；请勿再次在线付款，请联系平台核对原收款记录。');
    }

    /** Payer-facing projection: no receipt id, bank account, reference, operator
     * or internal review reason ever leaves this helper. */
    public static function pendingMany(int $site, array $orderIds): array
    {
        if ($site <= 0)throw new CommonException('收款展示所属站点无效');
        $ids = array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn (int $id): bool => $id > 0)));
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            try {
                $rows = Db::name(self::TABLE)->where('site_id', $site)->where('kind', 'payment')->whereIn('status', ['recorded', 'processing', 'attention'])
                    ->where(function ($query) use ($chunk): void {$query->whereIn('order_id', $chunk)->whereOr('target_order_id', 'in', $chunk);})
                    ->field('id,order_id,target_order_id,status')->order('id desc')->select()->toArray();
            } catch (\Throwable $error) {
                if (str_contains($error->getMessage(), '42S02') || str_contains($error->getMessage(), '1146'))return [];
                throw $error;
            }
            $wanted = array_fill_keys($chunk, true);
            foreach ($rows as $row)foreach ([(int) $row['order_id'], (int) $row['target_order_id']] as $id) {
                if (!isset($wanted[$id]) || isset($out[$id]))continue;
                $out[$id] = ['manual_payment_status' => $row['status'], 'manual_payment_message' => $row['status'] === 'attention' ? '线下到账已登记，业务仍需平台核对；请勿再次付款。' : '线下到账已登记，正在核对入账；请勿再次付款。', 'can_pay' => false];
            }
        }
        return $out;
    }

    public static function pending(int $site, int $orderId): array
    {
        foreach (array_reverse(self::$pendingScopes[$site] ?? []) as $scope)if (isset($scope['ids'][$orderId]))return $scope['rows'][$orderId] ?? [];
        return self::pendingMany($site, [$orderId])[$orderId] ?? [];
    }

    /** List presenters share one bounded query, including known empty results. */
    public static function withPendingScope(int $site, array $ids, callable $operation)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));
        self::$pendingScopes[$site][] = ['ids' => array_fill_keys($ids, true), 'rows' => self::pendingMany($site, $ids)];
        try {
            return $operation();
        } finally {
            array_pop(self::$pendingScopes[$site]);
            if (!self::$pendingScopes[$site])unset(self::$pendingScopes[$site]);
        }
    }

    public static function referenceHash(string $kind, string $method, string $account, string $external): string
    {
        if (!in_array($kind, ['payment', 'refund'], true) || !isset(self::METHODS[$method]))throw new CommonException('收付款凭据类型无效');
        return hash('sha256', Data::json([$kind, $method, mb_strtolower((string) preg_replace('/\s+/u', '', trim($account))), mb_strtoupper((string) preg_replace('/\s+/u', '', trim($external)))]));
    }

    private function input(array $input): array
    {
        if (array_diff(array_keys($input), ['request_id', 'amount', 'confirm', 'expected_fingerprint']))throw new CommonException('人工收款包含无效字段，请刷新后核对');
        if (!in_array($input['confirm'] ?? null, [true, 1, '1'], true))throw new CommonException('请先确认已核实实际到账；系统不会代为转账');
        $out = ['request_id' => self::request($input['request_id'] ?? null), 'amount' => self::money($input['amount'] ?? null)];
        $out['method'] = 'wechat';
        $out['reason'] = '管理员确认已收到线下微信款，充值并支付原订单';
        $fingerprint = $input['expected_fingerprint'] ?? null;
        if (!is_string($fingerprint) || !preg_match('/^[a-f0-9]{64}$/D', $fingerprint))throw new CommonException('请先读取原订单费用，再核实实际到账');
        $out['expected_fingerprint'] = $fingerprint;
        $out['input_fingerprint'] = hash('sha256', Data::json(array_diff_key($out, ['request_id' => true])));
        return $out;
    }

    private static function money($value): string
    {
        $cents = Rules::cents($value);
        if ($cents < 1 || $cents > 9999999999)throw new CommonException('实际到账金额须为0.01至99,999,999.99元');
        return Rules::amount($cents);
    }
    private static function ledgerCents(string $value): int
    {
        return str_starts_with($value, '-') ? -Rules::cents(substr($value, 1)) : Rules::cents($value);
    }
    private static function request($value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $value))throw new CommonException('收款请求标识无效，请保留原表单重新核对');
        return $value;
    }
    private static function fingerprint(array $row): string
    {
        $rule = Data::decode($row['rule_json']);
        if ($row['purpose'] === 'commission')return hash('sha256', Data::json([
            (int) $row['site_id'], (int) $row['id'], (int) $row['merchant_id'], (int) $row['member_id'], (int) $row['lead_id'],
            (string) $row['amount'], (string) $row['fee_amount'], (string) $row['net_amount'],
            array_intersect_key($rule, array_flip(['purpose', 'business_type', 'commission_minimum', 'fee_percent', 'fee_minimum', 'fee_confirmed'])),
        ]));
        // The grant starts at the verified framework receipt time. Preserve
        // the original quoted period for a stable receipt proof after grant.
        foreach (['start', 'end'] as $part)if (isset($rule['quoted_period_'.$part.'_at']))$rule['period_'.$part.'_at'] = $rule['quoted_period_'.$part.'_at'];
        return MembershipCostService::pricingFingerprint($row, $rule);
    }
    private static function purpose(array $row): void
    {
        if (!in_array($row['purpose'], ['membership', 'commission'], true))throw new CommonException('此业务不支持人工确认收款');
    }
    private function permission(): void
    {
        Store::permission($this->context, 'finance');
        $this->context->permission('hsx_lead_cooperation_order_manual_receipt');
        if (!$this->context->ownership()->scope()['all_data'])throw new CommonException('确认收款需要全站财务业务查看权限');
        // A manual feature needs its explicit evidence schema. Keep this check
        // after authorization and outside ordinary online-payment guards. No
        // schema is created, cached or repaired by a customer/admin request.
        $table = (string) Db::name(self::TABLE)->getTable();
        $ready = Db::query('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?', [$table]);
        if ((int) ($ready[0]['n'] ?? 0) !== 1)throw new CommonException('人工收款结构未就绪，请管理员先执行 php addon/hsx_lead/scripts/upgrade-manual-receipts.php --check，备份后执行 --apply，再重试。');
    }
    private function assertOriginalPayable(array $order): void
    {
        if ($order['status'] !== 'unpaid')throw new CommonException($order['status'] === 'closed' ? '订单已关闭，不能收款、充值、重新开通或新建替换订单。' : '原订单已收款，不能再次确认收款。');
        if (PaymentOrderLifecycle::expiresAt($order) <= time())throw new CommonException('原订单已超过支付有效期，不能确认收款；请先由客户重新下单。');
        if (!empty(Data::decode($order['rule_json'])['requires_manual_review']))throw new CommonException('原支付或权益尚待核对，不能继续收款或充值。');
        if ($order['purpose'] === 'membership')EntityMembershipService::assertOrderPayable($this->context->site, $order);
    }
    private function outsideTransaction(): void
    {
        if (Db::connect()->getPdo()->inTransaction())throw new CommonException('实际收款核对不能嵌套在其他事务中，请先提交原业务操作');
    }
    private function row(int $id, bool $lock = false): array
    {
        $query = Db::name(self::TABLE)->where('site_id', $this->context->site)->where('kind', 'payment')->where('id', $id);
        if ($lock)$query->lock(true);
        $row = $query->find();
        if (!$row)throw new CommonException('实际收款记录不存在或不属于本站');
        return $row;
    }
    private function snapshot(array $row): array
    {
        $value = Data::decode($row['snapshot_json']);
        if (($value['schema'] ?? '') !== self::SCHEMA || ($value['acknowledged'] ?? false) !== true || !is_array($value['source'] ?? null) || (int) $value['source']['order_id'] !== (int) $row['order_id'] || (int) $value['source']['member_id'] !== (int) $row['member_id'])throw new CommonException('原人工收款凭据不完整，请核对，未重复入账');
        return $value;
    }
    private function identity(array $order, array $source): void
    {
        if ((int) $order['site_id'] !== $this->context->site || (int) $order['member_id'] !== (int) $source['member_id'] || (int) $order['merchant_id'] !== (int) $source['merchant_id'] || $order['purpose'] !== $source['purpose'])throw new CommonException('原订单与收款登记归属不一致，实际到账已保留，请核对');
    }
    private function attention(int $id, string $message): void
    {
        Db::transaction(function () use ($id, $message): void {
            $row = $this->row($id, true);
            if ($row['status'] === 'posted')return;
            Db::name(self::TABLE)->where('site_id', $this->context->site)->where('id', $id)->update(['status' => 'attention', 'message' => mb_substr($message, 0, 500), 'update_time' => time()]);
            $this->context->audit('manual_receipt', $id, 'attention', ['order_id' => (int) $row['order_id'], 'target_order_id' => (int) $row['target_order_id'], 'amount' => $row['amount']]);
        });
    }
    private function locked(int $orderId, callable $operation)
    {
        $connection = Db::connect();
        $scope = (string) $connection->getConfig('database').':'.(string) $connection->getConfig('prefix');
        $key = 'lead_coop_pay:'.substr(hash('sha256', $scope.':'.$this->context->site.':'.$orderId), 0, 40);
        $lock = $connection->query('SELECT GET_LOCK(:lock_key,3) AS acquired', ['lock_key' => $key], true);
        if ((int) ($lock[0]['acquired'] ?? 0) !== 1)throw new CommonException('该订单正在核对付款，请稍后读取原收款记录，不要重复收款');
        try {
            return $operation();
        } finally {
            try {
                $connection->query('SELECT RELEASE_LOCK(:lock_key) AS released', ['lock_key' => $key], true);
            } catch (\Throwable $ignored) {
            }
        }
    }
    private function present(array $row): array
    {
        $source = Store::row('coop_order', $this->context->site, (int) $row['order_id']);
        $target = (int) $row['target_order_id'] > 0 ? Store::row('coop_order', $this->context->site, (int) $row['target_order_id']) : $source;
        $snapshot = Data::decode($row['snapshot_json']);
        $mode = (string) ($snapshot['settlement_mode'] ?? '');
        $binding = $snapshot['core_payment'] ?? [];
        $closed = $source['status'] === 'closed';
        $labels = ['recorded' => '已登记，待入账', 'processing' => '正在核对入账', 'posted' => '已确认收款', 'attention' => '到账已记录，待核对'];
        return ['id' => (int) $row['id'], 'status' => $row['status'], 'status_label' => $labels[$row['status']] ?? '待核对', 'message' => $closed && $row['status'] !== 'posted' ? '原订单已关闭，本笔登记仅供核对；不会充值、扣款或新建替换订单。' : $row['message'],
            'order_id' => (int) $row['order_id'], 'target_order_id' => (int) $row['target_order_id'], 'amount' => (string) $row['amount'], 'settlement_mode' => $mode, 'method' => $row['method'], 'method_label' => $mode === self::BALANCE_MODE ? '线下微信收款后余额支付' : (self::METHODS[$row['method']] ?? '待核对'),
            'account_label' => $row['account_label'], 'external_no' => $mode === self::BALANCE_MODE ? '' : $row['external_no'], 'registration_no' => $mode === self::BALANCE_MODE ? $row['external_no'] : '',
            'received_at' => (int) $row['received_at'], 'operator_uid' => (int) $row['operator_uid'], 'reason' => $row['reason'], 'out_trade_no' => $row['out_trade_no'], 'request_id' => $row['request_id'],
            'recharge_log_id' => (int) ($binding['recharge_log_id'] ?? 0), 'debit_log_id' => (int) ($binding['debit_log_id'] ?? 0), 'balance_before' => (string) ($binding['member_balance_before'] ?? ''), 'balance_after' => (string) ($binding['member_balance_after'] ?? ''),
            'can_retry' => $row['status'] !== 'posted' && !$closed && $mode === self::BALANCE_MODE, 'order' => PaymentService::presentOrder($target), 'source_order' => PaymentService::presentOrder($source)];
    }
}
