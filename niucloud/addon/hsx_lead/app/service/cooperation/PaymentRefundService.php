<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use addon\hsx_lead\app\service\virtualpay\VirtualPayOrderService;
use app\dict\member\MemberAccountTypeDict;
use app\dict\pay\{PayDict,RefundDict};
use app\service\core\member\CoreMemberAccountService;
use app\service\core\pay\CoreRefundService;
use core\exception\CommonException;
use think\facade\Db;

/** One reviewed, full remaining original-route refund. Never accepts a client amount or recipient. */
final class PaymentRefundService
{
    public const KEY = 'order_refund';
    public const RESERVE_TYPE = 'hsx_lead_refund_reserve';
    public const RELEASE_TYPE = 'hsx_lead_refund_release';
    public const LABELS = [
        'none' => '未申请退款',
        'pending' => '退款待审核',
        'approved' => '已审核待提交',
        'refunding' => '原路退款处理中',
        'partial_refunded' => '部分已退',
        'refunded' => '已退款',
        'rejected' => '退款未通过',
        'cancelled' => '申请已撤回',
        'attention' => '退款待核对',
        'failed' => '渠道退款失败'
    ];
    private const ACTIVE = ['pending', 'approved', 'refunding', 'attention'];
    public const APPLE_REFUND_MESSAGE = '本单由苹果渠道收款，请在 App Store 申请退款';
    private ?\Closure $gatewayFactory;
    public function __construct(private Context $context, ?callable $gatewayFactory = null)
    {
        $this->gatewayFactory = $gatewayFactory === null ? null : \Closure::fromCallable($gatewayFactory);
    }
    private function gateway(): object
    {
        return $this->gatewayFactory ? ($this->gatewayFactory)() : new CoreRefundService();
    }

    /** Metadata has no business mutation, callback, provider lookup or secret data. */
    public static function summary(array $order, ?array $pay = null): array
    {
        $rule = Data::decode($order['rule_json'] ?? null);
        $r = is_array($rule[self::KEY] ?? null) ? $rule[self::KEY] : [];
        $status = (string) ($r['status'] ?? 'none');
        if (!isset(self::LABELS[$status]))$status = 'attention';
        $out = [
            'refund_status' => $status,
            'refund_status_name' => self::LABELS[$status],
            'refunded_amount' => '0.00',
            'refundable_amount' => '0.00',
            'can_request_refund' => false,
            'refund_block_reason' => '',
            'refund_request_id' => (string) ($r['request_id'] ?? '')
        ];
        if (($order['status'] ?? '') !== 'paid' && !(($order['status'] ?? '') === 'closed' && !empty($order['out_trade_no']))) {
            $out['refund_block_reason'] = '仅已收款订单可申请退款，未付款请取消订单';
            return $out;
        }
        try {
            self::assertRefundRoute($order, $pay);
        } catch (CommonException$error) {
            $out['refund_block_reason'] = $error->getMessage();
            return $out;
        }
        try {
            $costs = self::remaining($order, false, $pay);
            $out['refunded_amount'] = Rules::amount($costs['refunded']);
            $out['refundable_amount'] = Rules::amount($costs['amount']);
            $out['refund_block_reason'] = $costs['pending'] ? '原支付已有退款处理中，请先核对原退款' : ($costs['amount'] <= 0 ? '原订单已全部退还' : '');
        } catch (\Throwable $error) {
            $out['refund_block_reason'] = '原付款或退款明细尚未核实，请平台核对';
            return $out;
        }
        if (in_array($status, self::ACTIVE, true))$out['refund_block_reason'] = '已有退款申请，请查看原申请进度';
        if ($status === 'refunded')$out['refund_block_reason'] = '本笔退款已完成';
        if ($status === 'none' && $costs['refunded'] > 0) {
            $status = $costs['amount'] > 0 ? 'partial_refunded' : 'refunded';
            $out['refund_status'] = $status;
            $out['refund_status_name'] = self::LABELS[$status];
        }
        if (($order['status'] ?? '') === 'closed')$out['refund_block_reason'] = '关闭订单的实际收款请由平台核对处理';
        $out['can_request_refund'] = $out['refund_block_reason'] === '';
        return $out;
    }

    public function detail(int $id, bool $admin = false): array
    {
        $order = $this->allowed($id, $admin, 'detail');
        return $this->present($order, $admin);
    }
    public function apply(int $id, array $input, bool $admin = false): array
    {
        $this->allowed($id, $admin, $admin ? 'create' : 'detail');
        if ($admin)$this->allowed($id, true, 'review');
        $request = Store::requestId($input);
        $reason = Data::text($input, 'reason', 500, true);
        if (mb_strlen($reason) < 4)throw new CommonException('请填写至少4个字的退款原因');
        if (!in_array($input['confirm'] ?? null, [true, 1, '1'], true))throw new CommonException('请确认申请原路退还本单剩余全部实付金额');
        if (array_key_exists('amount', $input)
            || array_key_exists('member_id', $input)
            || array_key_exists('refund_no', $input))throw new CommonException('退款金额和原付款人由系统核定，请勿提交自定义退款资料');
        Db::transaction(function () use ($id, $request, $reason, $admin): void {
            PromotionService::lockSite($this->context->site);
            $order = $this->allowed($id, $admin, $admin ? 'create' : 'detail', true);
            self::assertRefundRoute($order, null, true);
            $rule = Data::decode($order['rule_json']);
            $old = $rule[self::KEY] ?? [];
            if (($old['request_id'] ?? '') === $request) {
                if (($old['reason'] ?? '') !== $reason)throw new CommonException('原退款申请内容已固定，请核对原申请');
                return;
            }
            foreach ($rule['order_refund_history'] ?? [] as $previous)if (($previous['request_id'] ?? '') === $request)throw new CommonException('这是已处理的原退款申请，请刷新后查看，不会重新发起');
            if (in_array($old['status'] ?? '', self::ACTIVE, true) || ($old['status'] ?? '') === 'refunded')throw new CommonException('本单已有退款申请，请先查看原申请');
            $costs = self::assertRemaining($order, $admin);
            $this->assertNoDepositExit($order);
            self::assertDepositPrerequisite($order, $costs);
            if ($old) {
                $history = $rule['order_refund_history'] ?? [];
                $history[] = $old;
                $rule['order_refund_history'] = $history;
            }
            $rule[self::KEY] = ['request_id' => $request, 'status' => 'pending', 'reason' => $reason, 'amount' => Rules::amount($costs['amount']), 'components' => $costs['components'],
                'requested_at' => time(), 'requester_member_id' => $this->context->memberId, 'requester_uid' => $this->context->uid, 'review_at' => 0, 'review_reason' => '', 'review_uid' => 0, 'refund_no' => '', 'refund_record_id' => 0, 'dispatch_started_at' => 0, 'reservations' => [], 'message' => '等待平台审核，尚未发起渠道退款'];
            self::save($order, $rule);
            $this->audit($id, 'requested', ['reason' => $reason, 'amount' => $rule[self::KEY]['amount']]);
        });
        $detail = $this->detail($id, $admin);
        if ($admin
            && $detail['status'] === 'pending')return $this->review($id, [
                'decision' => 'approve',
                'request_version' => $detail['request_version'],
                'reason' => $reason
            ]);
        return $detail;
    }
    public function cancel(int $id, array $input): array
    {
        Db::transaction(function () use ($id, $input): void {
            PromotionService::lockSite($this->context->site);
            $order = $this->allowed($id, false, 'detail', true);
            $rule = Data::decode($order['rule_json']);
            $r = $rule[self::KEY] ?? [];
            $this->version($r, $input);
            if (($r['status'] ?? '') === 'cancelled')return;
            if (($r['status'] ?? '') !== 'pending')throw new CommonException('退款已进入审核处理，不能撤回，请刷新核对');
            $r['status'] = 'cancelled';
            $r['message'] = '申请已撤回，未发起退款';
            $rule[self::KEY] = $r;
            self::save($order, $rule);
            $this->audit($id, 'cancelled', []);
        });
        return $this->detail($id);
    }
    public function review(int $id, array $input): array
    {
        $this->allowed($id, true, 'review');
        $decision = Data::text($input, 'decision', 10, true);
        if (!in_array($decision, ['approve', 'reject'], true))throw new CommonException('请选择审核通过或拒绝');
        $dispatch = Db::transaction(function () use ($id, $input, $decision): bool {
            PromotionService::lockSite($this->context->site);
            $order = $this->allowed($id, true, 'review', true);
            $rule = Data::decode($order['rule_json']);
            $r = $rule[self::KEY] ?? [];
            $this->version($r, $input);
            if (($r['status'] ?? '') !== 'pending')return false;
            $reason = Data::text($input, 'reason', 500, true);
            if (mb_strlen($reason) < 4)throw new CommonException('请填写至少4个字的审核说明');
            $r['review_uid'] = $this->context->uid;
            $r['review_at'] = time();
            $r['review_reason'] = $reason;
            if ($decision === 'reject') {
                $r['status'] = 'rejected';
                $r['message'] = '退款申请未通过，未发起退款';
                $rule[self::KEY] = $r;
                self::save($order, $rule);
                $this->audit($id, 'rejected', ['reason' => $reason]);
                return false;
            }
            self::assertRefundRoute($order, null, true);
            $costs = self::assertRemaining($order, true);
            $this->assertNoDepositExit($order);
            self::assertDepositPrerequisite($order, $costs);
            if (Rules::cents($r['amount']) !== $costs['amount']
                || $r['components'] !== $costs['components'])throw new CommonException('申请后的已退金额发生变化，请撤回原申请并重新核对');
            $reservations = $this->reserveIncome($order, $r);
            $reserved = FrameworkRefundReservation::create($this->context->site, $order, $r['amount'], '订单退款：'.$r['reason']);
            $r = array_replace($r, [
                    'status' => 'approved',
                    'refund_no' => $reserved['refund_no'],
                    'refund_record_id' => $reserved['id'],
                    'reservations' => $reservations,
                    'message' => '已核对并预留分账资金，待提交原路退款'
                ]);
            if ($order['purpose'] === 'membership')$rule['membership_refund_allocations'][$r['refund_no']] = [
                'deposit' => $costs['components']['deposit'],
                'service' => $costs['components']['service'],
                'experience' => $costs['components']['experience'],
                'quantity' => $costs['components']['quantity']
            ];
            $rule[self::KEY] = $r;
            self::save($order, $rule);
            $this->audit($id, 'approved', ['reason' => $reason, 'amount' => $r['amount'], 'refund_no' => $r['refund_no']]);
            return true;
        });
        if ($dispatch)$this->dispatch($id);
        return $this->detail($id, true);
    }
    /** Explicit admin continuation is allowed only before the durable dispatch marker. */
    public function continue(int $id, array $input): array
    {
        $this->context->permission('hsx_lead_cooperation_order_refund_continue');
        $order = $this->allowed($id, true, 'review');
        $this->version(Data::decode($order['rule_json'])[self::KEY] ?? [], $input);
        $this->dispatch($id);
        return $this->detail($id, true);
    }
    private function dispatch(int $id): void
    {
        $number = Db::transaction(function () use ($id): string {
            PromotionService::lockSite($this->context->site);
            $order = Store::row('coop_order', $this->context->site, $id, true);
            $rule = Data::decode($order['rule_json']);
            $r = $rule[self::KEY] ?? [];
            if (($r['status'] ?? '') !== 'approved' || !empty($r['dispatch_started_at']))return '';
            self::assertRefundRoute($order, null, true);
            $refund = self::receipt($order, $r, true);
            if ($refund['status'] !== RefundDict::WAIT || $refund['refund_type'] !== '')throw new CommonException('原退款状态已变化，只能核对原单，不能重复提交');
            if ($refund['type'] === PayDict::OFFLINEPAY) {
                $r['manual_refund_required'] = true;
                $r['message'] = '退款已审核，待平台实际转账后确认；系统不会自动转账';
                $rule[self::KEY] = $r;
                self::save($order, $rule);
                return '';
            }
            $r['dispatch_started_at'] = time();
            $r['status'] = 'refunding';
            $r['message'] = '已提交原路退款，等待渠道确认';
            $rule[self::KEY] = $r;
            self::save($order, $rule);
            return $r['refund_no'];
        });
        if ($number === '')return;
        try {
            $this->gateway()->refund($this->context->site, $number, '', RefundDict::BACK, PayDict::USER, $this->context->uid);
            $this->reconcile($id);
        } catch (\Throwable $error) {
            $this->attention($id, '退款提交结果待核对，请刷新原退款，不会重复提交');
        }
    }
    /** Refresh can query only the exact persisted original refund; it can never submit a refund. */
    public function refresh(int $id, bool $admin = false): array
    {
        $this->allowed($id, $admin, 'refresh');
        $this->recover($id);
        return $this->detail($id, $admin);
    }
    private function recover(int $id): void
    {
        $order = Store::row('coop_order', $this->context->site, $id);
        $r = Data::decode($order['rule_json'])[self::KEY] ?? [];
        if (!empty($r['refund_no']) && in_array($r['status'] ?? '', ['approved', 'refunding', 'attention'], true)) {
            $manual = self::receipt($order, $r);
            if (ManualRefundService::resume($this->context->site, $order, $manual))return;
        }
        if (!empty($r['refund_no']) && !empty($r['dispatch_started_at']) && in_array($r['status'] ?? '', ['refunding', 'attention'], true)) {
            $refund = self::receipt($order, $r);
            if (in_array($refund['status'], [RefundDict::WAIT, RefundDict::DEALING], true))try {
                if (!$this->recoverBalance($order, $r))$this->gateway()->check($this->context->site, ['out_trade_no' => $refund['out_trade_no'], 'refund_no' => $refund['refund_no']]);
            } catch (\Throwable$error) {
                $this->attention($id, '原退款结果尚未确认，请稍后核对，未再次退款');
            }
            $this->reconcile($id);
        }
    }
    /** Trusted bounded scheduler: query only durable submitted originals, never create or dispatch. */
    public static function tick(int $limit = 20): array
    {
        ManualRefundService::tick($limit);
        $rows = Db::name('hsx_lead_coop_order')->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(rule_json) THEN rule_json ELSE '{}' END,'$.order_refund.status')) IN ('refunding','attention')")
            ->whereRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(rule_json,'$.order_refund.dispatch_started_at')) AS UNSIGNED)>0")->order('update_time,id')->limit(max(1, min(50, $limit)))->select()->toArray();
        $out = ['examined' => 0, 'refunded' => 0, 'failed' => 0, 'attention' => 0];
        foreach ($rows as $row) {
            $out['examined']++;
            $service = new self(new Context((int) $row['site_id'], 0, (int) $row['member_id']));
            try {
                $service->recover((int) $row['id']);
            } catch (\Throwable$error) {
                $service->attention((int) $row['id'], '原退款仍待核对，计划任务没有重新提交');
            }$current = Store::row('coop_order', (int) $row['site_id'], (int) $row['id']);
            $status = Data::decode($current['rule_json'])[self::KEY]['status'] ?? 'attention';
            $out[in_array($status, ['refunded', 'failed'], true) ? $status : 'attention']++;
            Store::query('coop_order', (int) $row['site_id'])->where('id', $row['id'])->update(['update_time' => time()]);
        }return $out;
    }
    private function reconcile(int $id): void
    {
        Db::transaction(function () use ($id): void {
            PromotionService::lockSite($this->context->site);
            $order = Store::row('coop_order', $this->context->site, $id, true);
            $rule = Data::decode($order['rule_json']);
            $r = $rule[self::KEY] ?? [];
            if (empty($r['refund_no']))return;
            $refund = self::receipt($order, $r, true);
            if ($refund['status'] === RefundDict::SUCCESS) {
                // A receipt can be persisted before a business callback throws. Replay all existing idempotent listeners, not just this service.
                if ((new CoreRefundService())->refundSuccess($this->context->site, [
                            'out_trade_no' => $refund['out_trade_no'],
                            'refund_no' => $r['refund_no'],
                            'trade_type' => PaymentService::TRADE_TYPE,
                            'trade_id' => (string) $id
                        ]) !== true)throw new CommonException('原退款业务回调尚未核实，请平台核对');
                return;
            }
            if (in_array($refund['status'], [RefundDict::FAIL, RefundDict::CANCEL], true) && ($r['status'] ?? '') !== 'failed') {
                self::releaseIncome($order, $r);
                $r['status'] = 'failed';
                $r['message'] = '渠道已确认退款失败，预留收益已释放；核对原因后可重新申请';
                $rule[self::KEY] = $r;
                self::save($order, $rule);
                $this->audit($id, 'failed', ['refund_no' => $r['refund_no']]);
            }
        });
    }
    public static function confirmed(array $event): bool
    {
        if (($event['trade_type'] ?? '') !== PaymentService::TRADE_TYPE)return true;
        $site = (int) ($event['site_id'] ?? 0);
        $number = (string) ($event['refund_no'] ?? '');
        if ($site <= 0 || $number === '')throw new CommonException('订单退款回调缺少可核对的凭据');
        return Db::transaction(function () use ($site, $number, $event): bool {
            PromotionService::lockSite($site);
            $refund = Db::name('pay_refund')->where('site_id', $site)->where('refund_no', $number)->where('status', RefundDict::SUCCESS)->lock(true)->find();
            if (!$refund)throw new CommonException('尚无真实成功退款凭据，未变更订单');
            $order = Store::query('coop_order', $site)->where('id', (int) $refund['trade_id'])->where('out_trade_no', $refund['out_trade_no'])->lock(true)->find();
            if (!$order)return true;
            $rule = Data::decode($order['rule_json']);
            $r = $rule[self::KEY] ?? [];
            if (($r['refund_no'] ?? '') !== $number) {
                foreach ($rule['order_refund_history'] ?? [] as $previous)if (($previous['refund_no'] ?? '') === $number) {
                    self::receipt($order, $previous, true);
                    self::conflictingReceipt($order, $rule, $number);
                    break;
                }
                return true;
            }
            self::receipt($order, $r, true);
            if ((int) ($event['trade_id'] ?? 0) !== (int) $order['id'])throw new CommonException('退款回调业务不匹配');
            if (($r['status'] ?? '') === 'refunded')return true;
            if (($r['status'] ?? '') === 'failed' || !empty($r['late_success_review'])) {
                self::conflictingReceipt($order, $rule, $number);
                return true;
            }
            if (!in_array($r['status'] ?? '', ['refunding', 'attention'], true)
                || empty($r['dispatch_started_at']))throw new CommonException('退款未进入提交状态，不能确认业务完成');
            foreach ($r['reservations'] ?? [] as $reservation)self::assertReservation($site, $r, $reservation);
            $r['status'] = 'refunded';
            $r['refunded_at'] = time();
            $r['message'] = '渠道已确认原路退款成功';
            $rule[self::KEY] = $r;
            if ($order['purpose'] === 'commission' && $order['status'] === 'paid') {
                $lead = Store::row('collaboration', $site, (int) $order['lead_id'], true);
                if ((int) $lead['payment_order_id'] !== (int) $order['id'])throw new CommonException('退款与原协作工单不一致，请平台核对');
                // A refunded settlement is closed, not another payable deal.
                $leadRule = Data::decode($lead['rule_json']);
                $leadRule['commission_refund'] = ['order_id' => (int) $order['id'], 'refund_no' => $number, 'amount' => $r['amount'], 'at' => time()];
                Store::query('collaboration', $site)->where('id', $lead['id'])->update([
                        'status' => 'cancelled',
                        'rule_json' => Data::json($leadRule),
                        'update_time' => time()
                    ]);
                Data::audit($site, 0, 0, 'collaboration', (int) $lead['id'], 'commission_refunded', [
                        'order_id' => (int) $order['id'],
                        'refund_no' => $number,
                        'amount' => $r['amount']
                    ]);
                unset($rule['requires_manual_review']);
            } elseif ($order['purpose'] === 'commission' && $order['status'] === 'closed') {
                self::closedReceipt($order, true);
                $rule['closed_payment_refund_status'] = 'refunded';
                unset($rule['requires_manual_review']);
            }
            self::save($order, $rule);
            foreach ($r['reservations'] ?? [] as $reservation)if (($reservation['kind'] ?? '') === 'promotion')Db::name('hsx_lead_promotion_reward')->where('site_id', $site)->where('id', $reservation['reward_id'])->update([
                    'status' => 'held',
                    'review_required' => 0,
                    'reason' => '原支付已退款，原推广奖励已从可用佣金退回',
                    'update_time' => time()
                ]);
            Data::audit($site, 0, 0, 'coop_order', (int) $order['id'], 'order_refund_confirmed', ['refund_no' => $number, 'amount' => $r['amount']]);
            return true;
        });
    }

    private function allowed(int $id, bool $admin, string $action, bool $lock = false): array
    {
        if ($admin) {
            Store::permission($this->context, 'finance');
            $this->context->permission('hsx_lead_cooperation_order_refund'.($action === 'create' ? '' : '_'.$action));
            if (!$this->context->ownership()->scope()['all_data'])throw new CommonException('退款处理需要全站财务管理权限');
        } else if ($this->context->memberId <= 0
            || !Db::name('member')->where('site_id', $this->context->site)->where('member_id', $this->context->memberId)->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->find())throw new CommonException('请先登录有效会员账号');
        $order = Store::row('coop_order', $this->context->site, $id, $lock);
        if (!$admin && (int) $order['member_id'] !== $this->context->memberId)throw new CommonException('只能查看或申请本人付款单的退款');
        return $order;
    }
    private static function remaining(array $order, bool $lock = false, ?array $knownPay = null): array
    {
        if (!OrderPaymentReadService::hasTable('pay_refund'))throw new CommonException('框架退款表未就绪');
        if ((string) ($order['out_trade_no'] ?? '') === '')throw new CommonException('原支付流水缺失');
        $pay = $knownPay ?? OrderPaymentReadService::payment($order, $lock);
        if (!PaymentService::receiptMatches($pay, $order))throw new CommonException('原支付身份、金额或状态不一致');
        self::assertRefundRoute($order, $pay, $lock);
        if (!in_array($pay['type'], [
                    PayDict::BALANCEPAY,
                    PayDict::WECHATPAY,
                    PayDict::ALIPAY,
                    PayDict::OFFLINEPAY,
                    VirtualPayOrderService::TYPE
                ], true))throw new CommonException('原支付方式不支持此处原路退款，请平台人工核对');
        OrderPaymentReadService::assertManualSource($order, $pay);
        $q = Db::name('pay_refund')->where('site_id', (int) $order['site_id'])->where('out_trade_no', $order['out_trade_no']);
        if ($lock)$q->lock(true);
        $refunds = $q->select()->toArray();
        $refunded = 0;
        $pending = false;
        $seen = [];
        foreach ($refunds as $refund) {
            $no = (string) $refund['refund_no'];
            if (isset($seen[$no]))throw new CommonException('退款凭据重复，需人工核对');
            $seen[$no] = true;
            if ($refund['trade_type'] !== PaymentService::TRADE_TYPE
                || (string) $refund['trade_id'] !== (string) $order['id'])throw new CommonException('退款归属不一致');
            if ($refund['status'] === RefundDict::SUCCESS)$refunded += Rules::cents((string) $refund['money']);
            if (in_array($refund['status'], [RefundDict::WAIT, RefundDict::DEALING], true))$pending = true;
        }
        $amount = Rules::cents((string) $order['amount']) - $refunded;
        if ($amount < 0)throw new CommonException('原单累计退款超额');
        $components = ['deposit' => '0.00', 'service' => '0.00', 'experience' => '0.00', 'quantity' => '0.00'];
        if ($order['purpose'] === 'membership') {
            $rule = Data::decode($order['rule_json']);
            EntityMembershipService::assertSnapshot($order, $rule);
            $parts = MembershipCostService::refundedComponents($order, $rule, $refunds);
            if ($parts['review'])throw new CommonException('原会员分项退款尚未核实');
            foreach ([
                    'deposit',
                    'service',
                    'experience'
                ] as $key)$components[$key] = Rules::amount(Rules::cents((string) $rule[$key.'_amount']) - $parts[$key]);
            $components['quantity'] = Rules::amount(Rules::cents((string) $rule['experience_quantity']) - $parts['quantity']);
        }
        return ['amount' => $amount, 'refunded' => $refunded, 'pending' => $pending, 'components' => $components];
    }
    /** Only the exact original virtual receipt can select an Apple refund route.
     * Ordinary payments never touch the optional virtual-payment tables. No
     * request, reservation, balance credit or provider call is made here. */
    public static function assertRefundRoute(array $order, ?array $pay = null, bool $lock = false): void
    {
        $pay = $pay ?? OrderPaymentReadService::payment($order, $lock);
        if (!$pay || ($pay['type'] ?? '') !== VirtualPayOrderService::TYPE)return;
        $message = '原虚拟支付收款凭据尚未核实，请平台核对；未发起退款';
        $site = (int) ($order['site_id'] ?? 0);
        $id = (int) ($order['id'] ?? 0);
        $number = (string) ($order['out_trade_no'] ?? '');
        if ($site <= 0
            || $id <= 0
            || (int) ($order['member_id'] ?? 0) <= 0
            || $number === ''
            || (int) ($pay['site_id'] ?? 0) !== $site
            || ($pay['trade_type'] ?? '') !== PaymentService::TRADE_TYPE
            || (string) ($pay['trade_id'] ?? '') !== (string) $id || ($pay['out_trade_no'] ?? '') !== $number || (int) ($pay['id'] ?? 0) <= 0
            || (int) ($pay['status'] ?? -1) !== (int) PayDict::STATUS_FINISH || ($pay['main_type'] ?? '') !== PayDict::MEMBER
            || (int) ($pay['main_id'] ?? 0) !== (int) ($order['member_id'] ?? 0)
                || Rules::cents((string) $pay['money']) !== Rules::cents((string) $order['amount']))throw new CommonException($message);
        try {
            $query = Db::name('hsx_lead_virtual_order')->where('site_id', $site)->where('order_id', $id)->where('out_trade_no', $number)->where('pay_id', (int) $pay['id']);
            if ($lock)$query->lock(true);
            $rows = $query->limit(2)->select()->toArray();
        } catch (\Throwable$error) {
            throw new CommonException($message);
        }
        if (count($rows) !== 1)throw new CommonException($message);
        $row = $rows[0];
        if ((int) ($row['site_id'] ?? 0) !== $site || (int) ($row['order_id'] ?? 0) !== $id || ($row['out_trade_no'] ?? '') !== $number
            || (int) ($row['pay_id'] ?? 0) !== (int) $pay['id'] || (int) ($row['member_id'] ?? 0) !== (int) $order['member_id']
            || (int) ($row['amount_cents'] ?? 0) !== Rules::cents((string) $order['amount']) || !in_array($row['status'] ?? '', ['paid', 'attention'], true)
            || !is_string($row['wx_order_id'] ?? null) || $row['wx_order_id'] === '')throw new CommonException($message);
        if (!in_array($row['order_type'] ?? null, [0, '0', 7, '7'], true))throw new CommonException($message);
        if ((int) $row['order_type'] === 7)throw new CommonException(self::APPLE_REFUND_MESSAGE);
    }
    private static function assertRemaining(array $order, bool $admin = false): array
    {
        if ((Data::decode($order['rule_json'])['requires_manual_review'] ?? '') === 'refund_receipt_after_failure_review')throw new CommonException('原失败退款出现成功回执，请平台先核对实际退款和已释放收益，不能再发起');
        if (!in_array($order['purpose'], ['membership', 'commission'], true)
            || ($order['status'] !== 'paid' && !($admin && $order['status'] === 'closed')))throw new CommonException('仅已确认收款的会员或协作订单可退款');
        if ($order['status'] === 'closed')self::closedReceipt($order, true);
        $costs = self::remaining($order, true);
        if ($costs['pending'] || $costs['amount'] <= 0)throw new CommonException($costs['pending'] ? '原支付已有待处理退款，未再发起' : '原支付已全部退还');
        return $costs;
    }
    private static function assertDepositPrerequisite(array $order, array $costs): void
    {
        if ($order['status'] !== 'paid' || $order['purpose'] !== 'membership' || Rules::cents($costs['components']['deposit']) === 0)return;
        $blocks = MembershipDepositService::blockingFor((int) $order['site_id'], (int) $order['merchant_id'], true);
        if ($blocks)throw new CommonException($blocks[0]['title'].'；请先完成后再退还押金');
    }
    /** Exceptional closed orders may have one real late receipt. Multiple receipts are never guessed. */
    private static function closedReceipt(array $order, bool $lock = false): array
    {
        $rule = Data::decode($order['rule_json']);
        if ($order['status'] !== 'closed'
            || !in_array($rule['requires_manual_review'] ?? '', ['membership_closed_receipt_conflict', 'commission_closed_receipt_conflict'], true))throw new CommonException('该关闭订单没有可核对的单笔晚收款异常');
        $q = Db::name('pay')->where('site_id', (int) $order['site_id'])->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', (string) $order['id'])->where('status', PayDict::STATUS_FINISH);
        if ($lock)$q->lock(true);
        $rows = $q->select()->toArray();
        if (count($rows) !== 1)throw new CommonException('关闭订单存在多笔收款或原收款不唯一，请平台逐笔核对，未发起退款');
        $pay = $rows[0];
        if ($pay['out_trade_no'] !== $order['out_trade_no']
            || $pay['main_type'] !== PayDict::MEMBER
            || (int) $pay['main_id'] !== (int) $order['member_id']
            || Rules::cents((string) $pay['money']) !== Rules::cents((string) $order['amount']))throw new CommonException('关闭订单的原收款身份或金额不一致');
        return $pay;
    }
    public static function adminSummary(array $order): array
    {
        if ($order['status'] !== 'closed')return [];
        $out = ['can_admin_request_refund' => false];
        $r = Data::decode($order['rule_json'])[self::KEY] ?? [];
        try {
            self::closedReceipt($order);
            $costs = self::remaining($order);
            $out['refunded_amount'] = Rules::amount($costs['refunded']);
            $out['refundable_amount'] = Rules::amount($costs['amount']);
            if (!in_array($r['status'] ?? '', self::ACTIVE, true)
                && ($r['status'] ?? '') !== 'refunded'
                && !$costs['pending']
                && $costs['amount'] > 0)$out['can_admin_request_refund'] = true;
        } catch (\Throwable$ignored) {
        }
        return $out;
    }
    private function assertNoDepositExit(array $order, bool $lock = true): void
    {
        if ($order['purpose'] !== 'membership')return;
        $q = Store::query('coop_order', $this->context->site)->where('merchant_id', $order['merchant_id'])->where('purpose', 'membership');
        if ($lock)$q->lock(true);
        foreach ($q->column('rule_json') as $json) {
            $exit = Data::decode($json)['membership_deposit_return'] ?? [];
            if (in_array($exit['status'] ?? '', ['pending', 'approved', 'refunding', 'attention'], true))throw new CommonException('门店正在办理退出退押金，请先完成或处理原退出申请');
        }
    }
    private function reserveIncome(array $order, array $r): array
    {
        self::assertRefundRoute($order, null, true);
        $site = $this->context->site;
        $reservations = [];
        if ($order['purpose'] === 'commission') {
            $logs = Db::name('member_account_log')->where('site_id', $site)->where('account_type', MemberAccountTypeDict::COMMISSION)->where('from_type', PaymentService::INCOME_TYPE)->where('related_id', (string) $order['id'])->lock(true)->select()->toArray();
            if (count($logs) > 1)throw new CommonException('原发布方收益流水不唯一，未发起退款');
            if ($logs) {
                $log = $logs[0];
                $lead = Store::row('collaboration', $site, (int) $order['lead_id'], true);
                $rule = Data::decode($order['rule_json']);
                $source = $rule['publisher_income'] ?? null;
                $merchant = Data::row('merchant', $site, (int) $lead['publisher_id']);
                $expectedMember = is_array($source) ? (int) ($source['member_id'] ?? 0) : (int) $merchant['member_id'];
                if ((int) $lead['receiver_id'] !== (int) $order['merchant_id']
                    || (int) $lead['payment_order_id'] !== (int) $order['id']
                    || (int) $lead['publisher_id'] === (int) $lead['receiver_id']
                    || (int) $log['member_id'] !== $expectedMember
                    || Rules::cents((string) $log['account_data']) !== Rules::cents((string) $order['net_amount'])
                    || (is_array($source) && ((int) ($source['merchant_id'] ?? 0) !== (int) $lead['publisher_id'] || (int) ($source['account_log_id'] ?? 0) !== (int) $log['id'])))throw new CommonException('原发布方主体、收益金额或入账凭据不符，未发起退款');
                $reservations[] = [
                    'kind' => 'publisher',
                    'member_id' => (int) $log['member_id'],
                    'amount' => (string) $order['net_amount'],
                    'source_log_id' => (int) $log['id']
                ];
            } elseif (empty(Data::decode($order['rule_json'])['requires_manual_review'])
                && Rules::cents((string) $order['net_amount']) > 0)throw new CommonException('原发布方入账记录缺失，请先人工核对');
        }
        foreach (Db::name('hsx_lead_promotion_reward')->where('site_id', $site)->where('coop_order_id', $order['id'])->lock(true)->select()->toArray() as $reward) {
            PromotionService::assertRewardSnapshot($site, $order, $reward);
            if ((int) $reward['account_log_id'] <= 0) {
                if ($reward['status'] === 'settled')throw new CommonException('原推广奖励流水缺失，未发起退款');
                continue;
            }
            $logs = Db::name('member_account_log')->where('site_id', $site)->where('account_type', MemberAccountTypeDict::COMMISSION)->where('from_type', PromotionService::INCOME_TYPE)->where('related_id', (string) $reward['id'])->lock(true)->select()->toArray();
            if (count($logs) !== 1
                || (int) $logs[0]['id'] !== (int) $reward['account_log_id']
                || (int) $logs[0]['member_id'] !== (int) $reward['provider_member_id']
                || Rules::cents((string) $logs[0]['account_data']) !== Rules::cents((string) $reward['amount']))throw new CommonException('原推广奖励与佣金流水不符，未发起退款');
            $reservations[] = [
                'kind' => 'promotion',
                'reward_id' => (int) $reward['id'],
                'member_id' => (int) $reward['provider_member_id'],
                'amount' => (string) $reward['amount'],
                'source_log_id' => (int) $logs[0]['id']
            ];
        }
        $totals = [];
        foreach ($reservations as $reservation)$totals[$reservation['member_id']] = ($totals[$reservation['member_id']] ?? 0) + Rules::cents($reservation['amount']);
        ksort($totals);
        foreach ($totals as $member => $amount) {
            $row = Db::name('member')->where('site_id', $site)->where('member_id', $member)->lock(true)->find();
            if (!$row || Rules::cents((string) $row['commission']) < $amount)throw new CommonException('分账收益已提现、使用或余额不足，请先由平台核对追回；当前未发起渠道退款');
        }
        foreach ($reservations as $index => $reservation) {
            $key = self::ledgerKey((int) $order['id'], $r['request_id'], $index);
            $reservation['ledger_key'] = $key;
            $reservation['reserve_log_id'] = (int) (new CoreMemberAccountService())->addLog($site, $reservation['member_id'], MemberAccountTypeDict::COMMISSION, '-'.$reservation['amount'], self::RESERVE_TYPE, '订单退款预留分账收益 '.$order['order_no'], $key);
            $reservations[$index] = $reservation;
            if ($reservation['kind'] === 'promotion')Db::name('hsx_lead_promotion_reward')->where('site_id', $site)->where('id', $reservation['reward_id'])->update([
                    'status' => 'held',
                    'review_required' => 0,
                    'reason' => '原支付退款处理中，已预留原推广收益',
                    'update_time' => time()
                ]);
        }
        return $reservations;
    }
    private static function assertReservation(int $site, array $r, array $reservation): void
    {
        $logs = Db::name('member_account_log')->where('site_id', $site)->where('account_type', MemberAccountTypeDict::COMMISSION)->where('from_type', self::RESERVE_TYPE)->where('related_id', $reservation['ledger_key'])->lock(true)->select()->toArray();
        if (count($logs) !== 1
            || (int) $logs[0]['id'] !== (int) $reservation['reserve_log_id']
            || (int) $logs[0]['member_id'] !== (int) $reservation['member_id']
            || bccomp((string) $logs[0]['account_data'], '-'.$reservation['amount'], 2) !== 0)throw new CommonException('原退款分账预留凭据异常，请平台核对');
        if (Db::name('member_account_log')->where('site_id', $site)->where('account_type', MemberAccountTypeDict::COMMISSION)->where('from_type', self::RELEASE_TYPE)->where('related_id', $reservation['ledger_key'])->count())throw new CommonException('原退款分账已释放，不能直接确认退款，请平台核对');
    }
    private static function releaseIncome(array $order, array $r): void
    {
        $site = (int) $order['site_id'];
        foreach ($r['reservations'] ?? [] as $reservation) {
            self::assertReservation($site, $r, $reservation);
            (new CoreMemberAccountService())->addLog($site, (int) $reservation['member_id'], MemberAccountTypeDict::COMMISSION, $reservation['amount'], self::RELEASE_TYPE, '原退款失败释放预留收益 '.$order['order_no'], $reservation['ledger_key']);
            if ($reservation['kind'] === 'promotion')Db::name('hsx_lead_promotion_reward')->where('site_id', $site)->where('id', $reservation['reward_id'])->update([
                    'status' => 'settled',
                    'review_required' => 0,
                    'reason' => '原退款失败，已归还预留推广收益',
                    'update_time' => time()
                ]);
        }
    }
    private static function receipt(array $order, array $r, bool $lock = false): array
    {
        $q = Db::name('pay_refund')->where('site_id', (int) $order['site_id'])->where('id', (int) ($r['refund_record_id'] ?? 0))->where('refund_no', (string) ($r['refund_no'] ?? ''));
        if ($lock)$q->lock(true);
        $refund = $q->find();
        if (!$refund
            || $refund['trade_type'] !== PaymentService::TRADE_TYPE
            || (string) $refund['trade_id'] !== (string) $order['id']
            || $refund['out_trade_no'] !== $order['out_trade_no']
            || Rules::cents((string) $refund['money']) !== Rules::cents((string) $r['amount'])
            || !in_array($refund['refund_type'], ($refund['type'] ?? '') === PayDict::OFFLINEPAY ? ['', RefundDict::OFFLINE] : ['', RefundDict::BACK], true))throw new CommonException('原退款凭据与本订单不符，未重复发起');
        $pay = OrderPaymentReadService::payment($order, $lock);
        if (!$pay
            || (int) $pay['status'] !== (int) PayDict::STATUS_FINISH
            || $pay['type'] !== $refund['type']
            || $pay['channel'] !== $refund['channel']
            || (int) $pay['main_id'] !== (int) $order['member_id']
            || $pay['main_type'] !== PayDict::MEMBER
            || Rules::cents((string) $pay['money']) !== Rules::cents((string) $order['amount']))throw new CommonException('原退款与已收款凭据不一致');
        OrderPaymentReadService::assertManualSource($order, $pay);
        if ($pay['type'] === PayDict::OFFLINEPAY
            && $refund['status'] === RefundDict::SUCCESS)ManualRefundService::assertRefundReceipt((int) $order['site_id'], $order, $refund);
        return $refund;
    }
    /** Shared by privileged manual-transfer registration and exact-proof compensation only. */
    public static function manualConfirmation(int $site, array $order, array $refund, bool $mark): bool
    {
        $rule = Data::decode($order['rule_json']);
        $r = $rule[self::KEY] ?? [];
        if (($r['refund_no'] ?? '') !== $refund['refund_no'])return false;
        if (!in_array($r['status'] ?? '', ['approved', 'refunding', 'attention', 'refunded'], true)
            || ($refund['type'] ?? '') !== PayDict::OFFLINEPAY)throw new CommonException('原线下退款尚未审核通过');
        self::receipt($order, $r, true);
        if (($r['status'] ?? '') === 'refunded') {
            if ($refund['status'] !== RefundDict::SUCCESS)throw new CommonException('原退款成功状态不一致');
            return true;
        }
        foreach ($r['reservations'] ?? [] as $reservation)self::assertReservation($site, $r, $reservation);
        if ($mark) {
            $r['status'] = 'refunding';
            $r['dispatch_started_at'] = ($r['dispatch_started_at'] ?? 0) ?: time();
            $r['manual_refund_required'] = true;
            $r['message'] = '实际退款凭据已登记，正在更新业务结果';
            $rule[self::KEY] = $r;
            self::save($order, $rule);
        }
        return true;
    }
    /** Balance driver may have credited once before an interrupted business callback. Verify that exact ledger; never refund twice. */
    private function recoverBalance(array $order, array $r): bool
    {
        $refund = self::receipt($order, $r);
        if ($refund['type'] !== PayDict::BALANCEPAY || $refund['refund_type'] !== RefundDict::BACK)return false;
        $logs = Db::name('member_account_log')->where('site_id', $this->context->site)->where('account_type', MemberAccountTypeDict::BALANCE)->where('from_type', 'order_refund')->where('related_id', $refund['refund_no'])->select()->toArray();
        if (!$logs)return false;
        if (count($logs) !== 1
            || (int) $logs[0]['member_id'] !== (int) $order['member_id']
            || Rules::cents((string) $logs[0]['account_data']) !== Rules::cents((string) $refund['money']))throw new CommonException('原余额退款流水不符，请平台核对');
        return (new CoreRefundService())->refundSuccess($this->context->site, [
                'out_trade_no' => $refund['out_trade_no'],
                'refund_no' => $refund['refund_no'],
                'trade_type' => PaymentService::TRADE_TYPE,
                'trade_id' => (string) $order['id']
            ]) === true;
    }
    private function attention(int $id, string $message): void
    {
        Db::transaction(function () use ($id, $message): void {
            PromotionService::lockSite($this->context->site);
            $order = Store::row('coop_order', $this->context->site, $id, true);
            $rule = Data::decode($order['rule_json']);
            $r = $rule[self::KEY] ?? [];
            if (!in_array($r['status'] ?? '', ['approved', 'refunding', 'attention'], true))return;
            $r['status'] = 'attention';
            $r['message'] = $message;
            $rule[self::KEY] = $r;
            self::save($order, $rule);
            $this->audit($id, 'attention', ['message' => $message]);
        });
    }
    private function present(array $order, bool $admin): array
    {
        $rule = Data::decode($order['rule_json']);
        $r = is_array($rule[self::KEY] ?? null) ? $rule[self::KEY] : [];
        $summary = self::summary($order);
        if ($admin) {
            $extra = self::adminSummary($order);
            if (!empty($extra['can_admin_request_refund'])) {
                $summary['can_request_refund'] = true;
                $summary['refund_block_reason'] = '';
                $summary['refundable_amount'] = $extra['refundable_amount'];
            }
        }$status = (string) ($r['status'] ?? 'none');
        if (!isset(self::LABELS[$status]))$status = 'attention';
        $logs = Data::query('audit', $this->context->site)->where('entity_type', 'coop_order')->where('entity_id', $order['id'])->whereLike('action', 'order_refund_%')->order('id', 'desc')->limit(30)->select()->toArray();
        $shown = [];
        foreach ($logs as $log) {
            $detail = Data::decode($log['detail_json'] ?? $log['detail'] ?? null);
            $shown[] = [
                'id' => (int) $log['id'],
                'action' => (string) $log['action'],
                'action_label' => [
                    'order_refund_requested' => '申请退款',
                    'order_refund_approved' => '审核通过',
                    'order_refund_rejected' => '审核拒绝',
                    'order_refund_cancelled' => '撤回申请',
                    'order_refund_failed' => '渠道退款失败',
                    'order_refund_attention' => '原退款待核对',
                    'order_refund_confirmed' => '渠道已确认退款'
                ][(string) $log['action']] ?? '退款处理',
                'operator_name' => (int) $log['operator_uid'] > 0 ? '平台管理员' : ((int) $log['operator_member_id'] > 0 ? '付款会员' : '系统'),
                'create_time' => (int) $log['create_time'],
                'detail' => ['reason' => (string) ($detail['reason'] ?? ''), 'message' => (string) ($detail['message'] ?? '')]
            ];
        }
        $out = [
            'order_id' => (int) $order['id'],
            'order_no' => (string) $order['order_no'],
            'purpose' => (string) $order['purpose'],
            'amount' => (string) ($r['amount'] ?? $summary['refundable_amount']),
            'status' => $status,
            'status_name' => self::LABELS[$status],
            'reason' => (string) ($r['reason'] ?? ''),
            'review_reason' => (string) ($r['review_reason'] ?? ''),
            'request_version' => $r ? self::requestVersion($order, $r) : '',
            'refund_no' => (string) ($r['refund_no'] ?? ''),
            'requested_at' => (int) ($r['requested_at'] ?? 0),
            'review_at' => (int) ($r['review_at'] ?? 0),
            'refunded_at' => (int) ($r['refunded_at'] ?? 0),
            'can_request' => $summary['can_request_refund'],
            'block_reason' => $summary['refund_block_reason'],
            'can_review' => $admin && $status === 'pending',
            'can_cancel' => !$admin && $status === 'pending',
            'can_refresh' => !empty($r['dispatch_started_at']) && in_array($status, ['refunding', 'attention'], true),
            'can_continue' => $admin && $status === 'approved' && empty($r['dispatch_started_at']),
            'components' => $r['components'] ?? ['deposit' => '0.00', 'service' => '0.00', 'experience' => '0.00', 'quantity' => '0.00'],
            'message' => (string) ($r['message'] ?? ''),
            'logs' => $shown
        ];
        if (!$r && $summary['can_request_refund'])try {
            $out['components'] = self::remaining($order)['components'];
        } catch (\Throwable$ignored) {
        }
        if ($out['can_request'] && $order['purpose'] === 'membership' && $order['status'] === 'paid')try {
            $this->assertNoDepositExit($order, false);
            if (Rules::cents($out['components']['deposit']) > 0) {
                $blocks = MembershipDepositService::blockingFor((int) $order['site_id'], (int) $order['merchant_id']);
                if ($blocks)throw new CommonException($blocks[0]['title'].'；请先处理后退还押金');
            }
        } catch (CommonException$error) {
            $out['can_request'] = false;
            $out['block_reason'] = $error->getMessage();
        }
        $refund = null;
        if (!empty($r['refund_no']))try {
            $refund = self::receipt($order, $r);
        } catch (\Throwable$ignored) {
        }
        $manual = ManualRefundService::metadata($order, $refund, $admin
            && in_array($status, ['approved', 'refunding', 'attention'], true)
            && $this->context->ownership()->allows('hsx_lead_cooperation_order_manual_refund'));
        $out = array_replace($out, $manual);
        if ($manual['manual_refund_required']) {
            $out['can_continue'] = false;
            $out['can_refresh'] = in_array($status, ['approved', 'refunding', 'attention'], true);
            if ($status === 'approved')$out['status_name'] = '待人工退款';
        }
        try {
            self::assertRefundRoute($order);
        } catch (CommonException$error) {
            $out['can_request'] = false;
            $out['can_review'] = false;
            $out['can_continue'] = false;
            $out['can_manual_refund'] = false;
            $out['block_reason'] = $error->getMessage();
            $out['message'] = $error->getMessage();
        }
        return $out;
    }
    private static function requestVersion(array $order, array $r): string
    {
        return hash('sha256', Data::json([(int) $order['site_id'], (int) $order['id'], (string) ($r['request_id'] ?? ''), (int) ($r['requested_at'] ?? 0)]));
    }
    private function version(array $r, array $input): void
    {
        $token = $input['request_version'] ?? null;
        if (!$r
            || !is_string($token)
            || !preg_match('/^[a-f0-9]{64}$/D', $token)
            || (int) ($r['order_id'] ?? 0) <= 0
            || !hash_equals(self::requestVersion(['site_id' => $this->context->site, 'id' => (int) $r['order_id']], $r), $token))throw new CommonException('退款申请版本已变化，请重新读取后处理');
    }
    private static function save(array $order, array $rule): void
    {
        if (isset($rule[self::KEY]))$rule[self::KEY]['order_id'] = (int) $order['id'];
        Store::query('coop_order', (int) $order['site_id'])->where('id', $order['id'])->update(['rule_json' => Data::json($rule), 'update_time' => time()]);
    }
    private function audit(int $id, string $action, array $detail): void
    {
        Data::audit($this->context->site, $this->context->uid, $this->context->memberId, 'coop_order', $id, 'order_refund_'.$action, $detail);
    }
    private static function ledgerKey(int $id, string $request, int $index): string
    {
        return 'LR'.substr(hash('sha256', $id.':'.$request.':'.$index), 0, 30);
    }
    private static function conflictingReceipt(array $order, array $rule, string $number): void
    {
        if (isset($rule['refund_conflicting_receipts'][$number]))return;
        $r = $rule[self::KEY] ?? [];
        $r['status'] = 'attention';
        $r['late_success_review'] = true;
        $r['message'] = '原失败退款出现成功回执，请平台核对实际退款与已释放收益，未再次提交退款';
        $rule[self::KEY] = $r;
        $rule['requires_manual_review'] = 'refund_receipt_after_failure_review';
        $rule['refund_conflicting_receipts'][$number] = ['refund_no' => $number, 'seen_at' => time()];
        self::save($order, $rule);
        Data::audit((int) $order['site_id'], 0, 0, 'coop_order', (int) $order['id'], 'order_refund_attention', ['refund_no' => $number, 'message' => $r['message']]);
    }
}
