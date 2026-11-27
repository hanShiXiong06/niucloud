<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\virtualpay;

use addon\hsx_lead\app\service\cooperation\{MembershipVirtualPayAdapter,Rules};
use addon\hsx_lead\app\support\Data;
use app\dict\pay\{OnlinePayDict,PayDict,RefundDict};
use app\service\core\pay\{CorePayService,CoreRefundService};
use core\exception\CommonException;
use think\facade\Db;

/** Provider facts only. Existing verified PaySuccess/RefundSuccess hooks own rights and commissions. */
final class VirtualPayOrderService
{
    public const TYPE = 'hsx_lead_virtualpay';
    private ?\Closure $settingsProvider;
    private ?\Closure $sessionProvider;
    private ?\Closure $tokenProvider;
    private object $http;
    private MembershipVirtualPayAdapter $businessAdapter;

    /** PHP-only test doubles; never selected by request fields or persisted configuration. */
    public function __construct(?callable $settingsProvider = null, ?callable $sessionProvider = null, ?object $http = null, ?callable $tokenProvider = null)
    {
        $this->settingsProvider = $settingsProvider === null ? null : \Closure::fromCallable($settingsProvider);
        $this->sessionProvider = $sessionProvider === null ? null : \Closure::fromCallable($sessionProvider);
        $this->tokenProvider = $tokenProvider === null ? null : \Closure::fromCallable($tokenProvider);
        $this->http = $http ?? new VirtualPayHttpClient();
        $this->businessAdapter = new MembershipVirtualPayAdapter();
    }

    /** One native request per immutable trade. A cancelled sheet is not proof
     * that this merchant order number can be submitted as a new native order. */
    public function prepare(int $site, string $number, string $loginCode): array
    {
        self::tradeNumber($number);
        if ($loginCode === '' || strlen($loginCode) > 256 || preg_match('/[\x00-\x20\x7f]/', $loginCode))throw new CommonException('请重新发起微信登录后付款');
        // An explicit payment attempt must belong to the current member even
        // before a provider query can settle an existing original trade.
        [$preflightPay] = $this->original($site, $number);
        if ((int) request()->memberId() <= 0 || (int) request()->memberId() !== (int) $preflightPay['main_id'])throw new CommonException('只能使用本人账号发起会员付款');
        $settings = $this->settings($site, true);
        $existing = self::orders($site)->where('out_trade_no', $number)->find();
        if ($existing) {
            $result = $this->query($site, $number);
            if (($result['status'] ?? '') === OnlinePayDict::SUCCESS)throw new CommonException('原微信付款已成功，请返回查看会员权益，无需重复支付');
            if (($result['status'] ?? '') === OnlinePayDict::CLOSED)throw new CommonException('原微信付款已确认关闭，请返回订单重新确认费用');
            throw new CommonException('原微信付款尚未确认关闭，请稍后核对原订单；未重复发起扣款');
        }
        $session = $this->sessionProvider ? ($this->sessionProvider)($site, $loginCode) : VirtualPaySettings::session($site, $loginCode);
        if (!is_array($session)
            || !is_string($session['openid'] ?? null)
            || $session['openid'] === ''
            || !is_string($session['session_key'] ?? null)
            || $session['session_key'] === '')throw new CommonException('微信支付身份未核实，请重新登录后重试');
        $row = Db::transaction(function () use ($site, $number, $settings, $session): array {
            [$pay,$business] = $this->original($site, $number, true);
            $this->businessAdapter->assertCanPrepare($site, $pay, $business);
            if (in_array((int) $pay['status'], [(int) PayDict::STATUS_FINISH, (int) PayDict::STATUS_CANCEL], true))throw new CommonException('本次付款已完成、关闭或报价已过期，请刷新订单');
            $member = Db::name('member')->where('site_id', $site)->where('member_id', (int) $pay['main_id'])->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->lock(true)->find();
            if (!$member
                || empty($member['weapp_openid'])
                || !hash_equals((string) $member['weapp_openid'], $session['openid']))throw new CommonException('付款微信身份与当前会员不一致，未创建虚拟支付');
            if ((int) request()->memberId() > 0 && (int) request()->memberId() !== (int) $pay['main_id'])throw new CommonException('不能替其他会员发起此项付款');
            $product = $this->businessAdapter->product($business, $settings);
            $snapshot = $this->businessAdapter->fingerprint($business);
            $row = self::orders($site)->where('out_trade_no', $number)->lock(true)->find();
            if ($row) {
                self::assertSettings($row, $settings);
                if ($row['snapshot_hash'] !== $snapshot
                    || (int) $row['member_id'] !== (int) $pay['main_id']
                    || !hash_equals($row['openid'], $session['openid'])
                    || (int) $row['amount_cents'] !== Rules::cents((string) $pay['money'])
                    || $row['product_id'] !== (string) $product['product_id']
                    || !in_array($row['status'], ['prepared', 'pending'], true))throw new CommonException('原虚拟支付资料或金额已变化，请先核对原单');
                return $row;
            }
            $now = time();
            $values = ['site_id' => $site, 'pay_id' => (int) $pay['id'], 'order_id' => (int) $business['id'], 'member_id' => (int) $pay['main_id'],
                'app_id' => $settings['app_id'], 'offer_id' => $settings['offer_id'], 'env' => (int) $settings['env'], 'out_trade_no' => $number, 'openid' => $session['openid'],
                'product_id' => (string) $product['product_id'], 'amount_cents' => Rules::cents((string) $pay['money']), 'quantity' => 1,
                'attach' => 'vp_'.hash('sha256', $site.'|'.$number.'|'.$snapshot), 'snapshot_hash' => $snapshot, 'status' => 'prepared',
                'next_check_at' => $now + 300, 'create_time' => $now, 'update_time' => $now];
            $values['id'] = (int) self::orders($site)->insertGetId($values);
            return $values;
        });
        return ['client_action' => 'wechat_virtual_payment', 'pay_data' => VirtualPayProtocol::payData($settings, array_replace($row, ['amount_cents' => (int) $row['amount_cents'], 'quantity' => 1]), $session['session_key'])];
    }

    /** A typed provider result is retained; unknown results must not authorize closure or fulfillment. */
    public function query(int $site, string $number): array
    {
        $row = self::order($site, $number);
        $settings = $this->settings($site, false);
        self::assertSettings($row, $settings);
        // A verified receipt is committed before running native business
        // listeners. Retrying local delivery must not need another successful
        // external query, nor create another payment attempt.
        if ($row['status'] === 'receipt_pending' && self::hasReceiptFact($row))return $this->deliverReceipt($site, $row);
        if (self::hasReceiptFact($row)) {
            [$knownPay,$knownBusiness] = $this->original($site, $number);
            if ((int) $knownPay['status'] === (int) PayDict::STATUS_FINISH
                && $knownBusiness['status'] === 'unpaid'
                && empty(Data::decode($knownBusiness['rule_json'])['requires_manual_review']))return $this->deliverReceipt($site, $row);
        }
        try {
            $remote = $this->remote($site, $settings, $row);
            self::assertQuery($row, $remote);
        } catch (\Throwable $error) {
            $row = self::order($site, $number);
            if ($row['status'] === 'receipt_pending' && self::hasReceiptFact($row))return $this->deliverReceipt($site, $row);
            if (self::hasReceiptFact($row) && $this->verifiedReceipt($site, $row, (string) $row['wx_order_id'])) {
                self::orders($site)->where('id', $row['id'])->where('status', 'delivery_pending')->update([
                        'next_check_at' => time() + 300,
                        'last_message' => '会员权益已开通，渠道发货状态稍后自动核对',
                        'update_time' => time()
                    ]);
                return $this->receiptResult($site, self::order($site, $number));
            }
            throw $error;
        }
        $status = (int) $remote['status'];
        // HTTP may race an authenticated callback on another worker. Never
        // apply an older NOTPAY/CLOSED response over its durable cash receipt.
        $snapshotHadReceipt = self::hasReceiptFact($row);
        $row = self::order($site, $number);
        if (!$snapshotHadReceipt && self::hasReceiptFact($row) && $row['status'] !== 'receipt_pending')return $this->receiptResult($site, $row);
        if ($row['status'] === 'receipt_pending' && self::hasReceiptFact($row)) {
            if (in_array($status, [5, 7, 8], true)) {
                $this->attention($site, (int) $row['id'], '原收款已核实，渠道退款状态需核对，暂不发放会员权益');
                return ['status' => 'UNKNOWN', 'virtual_state' => 'refund_review', 'receipt_received' => true, 'delivery_pending' => false];
            }
            return $this->deliverReceipt($site, $row);
        }
        // A delayed/replayed provider state cannot undo a verified local
        // receipt or authorize another deduction. Refunds remain independent.
        if ($status !== 2 && $this->verifiedReceipt($site, $row, (string) ($row['wx_order_id'] ?? ''))) {
            if (in_array($status, [1, 6], true))$this->attention($site, (int) $row['id'], '已核实收款与服务商后续状态不一致，请核对原单');
            if ($status === 4)self::orders($site)->where('id', $row['id'])->where('status', 'delivery_pending')->update([
                    'status' => 'paid',
                    'last_provider_status' => 4,
                    'next_check_at' => 0,
                    'last_message' => '收款、权益及渠道发货均已核实',
                    'update_time' => time()
                ]);
            elseif ($row['status'] === 'delivery_pending')self::orders($site)->where('id', $row['id'])->where('status', 'delivery_pending')->update([
                    'last_provider_status' => $status,
                    'next_check_at' => time() + 300,
                    'update_time' => time()
                ]);
            return $this->receiptResult($site, self::order($site, $number), 'local_verified_receipt', in_array($status, [
                        5,
                        7,
                        8
                    ], true) ? 'refund_review' : null);
        }
        if (in_array($status, [2, 4], true)) {
            self::assertPaidQuery($row, $remote);
            return $this->confirmPayment($site, $row, (string) $remote['wx_order_id'], (int) $remote['order_type'], isset($remote['paid_time']) ? self::integer($remote['paid_time']) : time(), $status);
        }
        if ($status === 6) {
            $receipt = Db::transaction(function () use ($site, $row): ?array {
                $current = self::order($site, $row['out_trade_no'], true);
                $pay = self::payment($site, $row['out_trade_no'], true);
                if (self::hasReceiptFact($current) || (int) $pay['status'] === (int) PayDict::STATUS_FINISH)return $current;
                if ($current['status'] !== 'paid')self::orders($site)->where('id', $current['id'])->update([
                        'status' => 'closed',
                        'last_provider_status' => 6,
                        'last_message' => '服务商已确认关闭',
                        'update_time' => time()
                    ]);
                return null;
            });
            if ($receipt !== null)return $receipt['status'] === 'receipt_pending' ? $this->deliverReceipt($site, $receipt) : $this->receiptResult($site, $receipt);
            return ['status' => OnlinePayDict::CLOSED, 'virtual_state' => 'closed'];
        }
        self::orders($site)->where('id', $row['id'])->update(['last_provider_status' => $status, 'next_check_at' => time() + 300, 'update_time' => time()]);
        if ($status === 1) {
            self::orders($site)->where('id', $row['id'])->whereIn('status', ['prepared', 'pending'])->update(['status' => 'pending']);
            return ['status' => OnlinePayDict::NOTPAY, 'virtual_state' => 'pending', 'can_resume_same_trade' => false];
        }
        return ['status' => 'UNKNOWN', 'virtual_state' => in_array($status, [5, 7, 8], true) ? 'refund_review' : 'unknown'];
    }

    /** No documented cash-order close API is invented. Only a confirmed CLOSED authorizes local cancellation. */
    public function close(int $site, string $number): bool
    {
        $result = $this->query($site, $number);
        return ($result['status'] ?? '') === OnlinePayDict::CLOSED;
    }

    /** The incoming event must already have passed message-push signature/AES/AppID checks. */
    public function handleEvent(int $site, array $event): void
    {
        $kind = (string) ($event['Event'] ?? '');
        if ($kind === 'xpay_goods_deliver_notify') {
            $row = self::order($site, self::scalar($event, 'OutTradeNo', 32));
            $settings = $this->settings($site, false);
            self::assertSettings($row, $settings);
            self::assertEventIdentity($row, $event);
            $goods = $event['GoodsInfo'] ?? null;
            $wx = $event['WeChatPayInfo'] ?? null;
            if (!is_array($goods)
                || (string) ($goods['ProductId'] ?? '') !== $row['product_id']
                || self::integer($goods['Quantity'] ?? null) !== 1
                || !is_string($goods['Attach'] ?? null)
                || !hash_equals($row['attach'], $goods['Attach']))throw new CommonException('虚拟支付回调商品与原单不一致');
            // Official enterprise virtual-payment notifications may omit
            // WeChatPayInfo for non-WeChat channels, including Apple IAP.
            // Its absence is never evidence of payment: query the frozen
            // original instead. A supplied receipt must still match exactly.
            if ($wx !== null && !is_array($wx))throw new CommonException('虚拟支付回调收款资料格式异常');
            $callbackNumber = is_array($wx) && $wx !== [] ? self::scalar($wx, 'MchOrderNo', 128) : null;
            if (isset($goods['ActualPrice']) && self::integer($goods['ActualPrice']) !== (int) $row['amount_cents'])throw new CommonException('虚拟支付回调金额与原单不一致');
            if (isset($goods['OrigPrice']) && self::integer($goods['OrigPrice']) !== (int) $row['amount_cents'])throw new CommonException('虚拟支付回调原价不匹配');
            // After the complete receipt+rights transaction committed, the
            // provider can replay delivery while already reporting status 4,
            // or while its query endpoint is unavailable. Acknowledge only
            // that exact frozen receipt without a new provider request. Only
            // interrupted local delivery may run the idempotent hooks again.
            $knownNumber = $callbackNumber ?? ((int) $row['order_type'] === 7 ? (string) ($row['wx_order_id'] ?? '') : '');
            if ($knownNumber !== '' && self::hasReceiptFact($row) && hash_equals((string) $row['wx_order_id'], $knownNumber)) {
                if (isset($event['OrderType']) && self::integer($event['OrderType']) !== (int) $row['order_type'])throw new CommonException('重复回调终端与原收款不一致');
                [, $knownBusiness] = $this->original($site, $row['out_trade_no']);
                $fulfilled = $knownBusiness['status'] === 'paid'
                    && $row['status'] === 'paid'
                    && hash_equals((string) $knownBusiness['out_trade_no'], $row['out_trade_no'])
                    && empty(Data::decode($knownBusiness['rule_json'])['requires_manual_review']);
                $returned = (int) self::refunds($site)->where('virtual_order_id', $row['id'])->where('status', 'success')->sum('amount_cents') >= (int) $row['amount_cents'];
                if (!in_array($row['status'], ['receipt_pending', 'delivery_pending'], true)
                    && ($fulfilled || $returned)
                    && $this->verifiedReceipt($site, $row, $knownNumber))return;
                $result = $this->deliverReceipt($site, $row, true);
                if (($result['status'] ?? '') !== OnlinePayDict::SUCCESS)throw new CommonException('原收款已核实，会员权益尚未确认完成，未确认渠道发货');
                return;
            }
            $remote = $this->remote($site, $settings, $row);
            self::assertQuery($row, $remote);
            self::assertPaidQuery($row, $remote);
            if ($callbackNumber !== null && (string) $remote['wx_order_id'] !== $callbackNumber)throw new CommonException('虚拟支付回调与查单凭据不一致');
            if ($callbackNumber === null
                && ((int) $remote['order_type'] !== 7 || !isset($remote['paid_time'])))throw new CommonException('苹果付款通知的原渠道收款凭据或时间尚未核实');
            if (isset($event['OrderType']) && self::integer($event['OrderType']) !== (int) $remote['order_type'])throw new CommonException('虚拟支付回调与查单终端不一致');
            $result = $this->confirmPayment($site, $row, (string) $remote['wx_order_id'], (int) $remote['order_type'], isset($remote['paid_time']) ? self::integer($remote['paid_time']) : time(), (int) $remote['status'], true);
            if (($result['status'] ?? '') !== OnlinePayDict::SUCCESS)throw new CommonException('渠道收款已核实，会员权益尚未确认完成，未确认渠道发货');
            return;
        }
        if ($kind === 'xpay_refund_notify') {
            $this->refundEvent($site, $event);
            return;
        }
        throw new CommonException('当前虚拟支付事件尚未接入，不自动确认业务');
    }

    public function refund(int $site, array $params): array
    {
        $number = (string) ($params['out_trade_no'] ?? '');
        $row = self::order($site, $number);
        $settings = $this->settings($site, false);
        self::assertSettings($row, $settings);
        $refundNo = (string) ($params['refund_no'] ?? '');
        $amount = Rules::cents((string) ($params['money'] ?? ''));
        if ($amount <= 0 || $refundNo === '' || strlen($refundNo) > 64)throw new CommonException('虚拟支付退款金额或退款编号无效');
        if ((int) $row['order_type'] === 7)throw new CommonException('本单由苹果渠道收款，请由用户在App Store申请退款');
        $record = Db::transaction(function () use ($site, $row, $refundNo, $amount): array {
            $order = self::order($site, $row['out_trade_no'], true);
            $pay = self::payment($site, $row['out_trade_no'], true);
            $refund = $this->businessAdapter->frameworkRefund($site, $pay, $refundNo, $amount, true);
            $old = self::refunds($site)->where('framework_refund_no', $refundNo)->lock(true)->find();
            if ($old) {
                if ((int) $old['virtual_order_id'] !== (int) $row['id'] || (int) $old['amount_cents'] !== $amount)throw new CommonException('原退款金额或关联已固定');
                return $old;
            }
            if (!in_array($refund['status'], [RefundDict::WAIT, RefundDict::DEALING], true))throw new CommonException('原退款已结束，不能重新提交');
            $other = self::refunds($site)->where('virtual_order_id', $order['id'])->whereIn('status', [
                    'prepared',
                    'dispatching',
                    'refunding',
                    'unknown',
                    'attention',
                    'reconciling'
                ])->lock(true)->find();
            if ($other)throw new CommonException('原支付仍有退款处理中，请先核对原退款');
            $used = (int) self::refunds($site)->where('virtual_order_id', $order['id'])->where('status', 'success')->sum('amount_cents');
            if ($used + $amount > (int) $order['amount_cents'])throw new CommonException('虚拟支付累计退款超过原实付');
            $now = time();
            $values = [
                'site_id' => $site,
                'virtual_order_id' => $order['id'],
                'framework_refund_no' => $refundNo,
                'refund_order_id' => 'vr'.substr(hash('sha256', $site.'|'.$refundNo), 0, 30),
                'amount_cents' => $amount,
                'status' => 'prepared',
                'source_kind' => 'merchant',
                'create_time' => $now,
                'update_time' => $now
            ];
            $values['id'] = (int) self::refunds($site)->insertGetId($values);
            return $values;
        });
        if ($record['status'] !== 'prepared')return self::refundResult($record);
        $remote = $this->remote($site, $settings, $row);
        self::assertQuery($row, $remote);
        if (!in_array((int) ($remote['order_type'] ?? -1), [0], true)
            || !in_array((int) $remote['status'], [2, 3, 4], true))throw new CommonException('原虚拟支付收款或退款终端尚未核实');
        $left = self::integer($remote['left_fee'] ?? null);
        if ($left < $amount || $left > (int) $row['amount_cents'])throw new CommonException('服务商剩余可退金额与本次退款不符');
        $payload = [
            'openid' => $row['openid'],
            'order_id' => $row['out_trade_no'],
            'refund_order_id' => $record['refund_order_id'],
            'left_fee' => $left,
            'refund_fee' => $amount,
            'biz_meta' => 'vr_'.$record['id'],
            'refund_reason' => 3,
            'req_from' => 1,
            'env' => (int) $row['env']
        ];
        $hash = hash('sha256', Data::json($payload));
        $claimed = Db::transaction(function () use ($site, $record, $hash): bool {
            $current = self::refunds($site)->where('id', $record['id'])->lock(true)->find();
            if (!$current || $current['status'] !== 'prepared')return false;
            self::refunds($site)->where('id', $record['id'])->update([
                    'status' => 'dispatching',
                    'request_hash' => $hash,
                    'requested_at' => time(),
                    'update_time' => time()
                ]);
            return true;
        });
        if (!$claimed)return $this->refundState($site, $number, $refundNo);
        try {
            $response = $this->http->refund($this->withToken($site, $settings), $payload);
            if (!is_array($response) || ($response['errcode'] ?? null) !== 0)throw new CommonException('虚拟支付退款响应尚未核实');
            if (($response['refund_order_id'] ?? '') !== $record['refund_order_id']
                || ($response['pay_order_id'] ?? '') !== $number
                || empty($response['refund_wx_order_id']))throw new CommonException('虚拟支付退款返回关联不一致');
            self::refunds($site)->where('id', $record['id'])->where('status', 'dispatching')->update([
                    'status' => 'refunding',
                    'wx_refund_id' => self::scalar($response, 'refund_wx_order_id', 128),
                    'last_message' => '服务商已受理，尚未确认退款完成',
                    'update_time' => time()
                ]);
        } catch (\Throwable $error) {
            self::refunds($site)->where('id', $record['id'])->where('status', 'dispatching')->update([
                    'status' => 'unknown',
                    'last_message' => '退款提交结果待核实，不自动重新提交',
                    'update_time' => time()
                ]);
        }
        return $this->refundState($site, $number, $refundNo);
    }

    /** Reads the persisted original refund only; does not invent the undocumented refund-query ID mapping. */
    public function refundState(int $site, string $number, ?string $refundNo): array
    {
        $order = self::order($site, $number);
        if (!$refundNo)return ['status' => RefundDict::DEALING, 'refund_no' => '', 'message' => '请核对原退款单号'];
        $row = self::refunds($site)->where('virtual_order_id', $order['id'])->where('framework_refund_no', $refundNo)->find();
        return $row ? self::refundResult($row) : ['status' => RefundDict::DEALING, 'refund_no' => $refundNo, 'message' => '虚拟支付原退款尚未登记，请平台核对'];
    }

    /** Bounded original-payment queries and replay of already authenticated
     * external refund facts. Never submits a payment or provider refund. */
    public function tick(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $result = [
            'examined' => 0,
            'paid' => 0,
            'closed' => 0,
            'pending' => 0,
            'attention' => 0,
            'receipt_pending' => 0,
            'delivery_pending' => 0,
            'refund_recovered' => 0,
            'refund_review' => 0
        ];
        $duePayments = static fn (int $count): array => Db::name('hsx_lead_virtual_order')->whereIn('status', [
                'prepared',
                'pending',
                'receipt_pending',
                'delivery_pending'
            ])->where('next_check_at', '<=', time())->order('next_check_at,id')->limit($count)->field('id,site_id,out_trade_no')->select()->toArray();
        // A large refund backlog must not starve paid users waiting for their
        // entitlement receipt. A one-item run prefers a due payment; larger
        // runs reserve at least half their bound for payment queries.
        $single = $limit === 1 ? $duePayments(1) : null;
        $refundBudget = $limit === 1 ? ($single ? 0 : 1) : intdiv($limit, 2);
        // Uses the existing (status,next_check_at,id) order index. Unknown
        // merchant refund dispatches are NOT polled with invented API fields.
        $external = $refundBudget > 0 ? Db::name('hsx_lead_virtual_order')->where('status', 'attention')->where('next_check_at', '>', 0)->where('next_check_at', '<=', time())->order('next_check_at,id')->limit($refundBudget)->field('id,site_id,out_trade_no')->select()->toArray() : [];
        foreach ($external as $row) {
            $site = (int) $row['site_id'];
            $facts = self::refunds($site)->where('virtual_order_id', $row['id'])->where('source_kind', 'platform')->where('status', 'reconciling')->where('finish_time', '>', 0)->order('id')->limit($refundBudget - $result['examined'])->select()->toArray();
            foreach ($facts as $fact) {
                $result['examined']++;
                $status = $this->recoverExternalRefund($site, $row['out_trade_no'], (int) $fact['id'], (int) $fact['amount_cents']);
                $result[$status === 'success' ? 'refund_recovered' : ($status === 'attention' ? 'refund_review' : 'attention')]++;
            }
            $this->scheduleExternalRecovery($site, (int) $row['id']);
            if ($result['examined'] >= $refundBudget)break;
        }
        $rows = $single ?? $duePayments($limit - $result['examined']);
        foreach ($rows as $row) {
            $result['examined']++;
            try {
                $state = $this->query((int) $row['site_id'], $row['out_trade_no']);
                $key = ($state['status'] ?? '') === OnlinePayDict::SUCCESS ? 'paid' : (($state['status'] ?? '') === OnlinePayDict::CLOSED ? 'closed' : (($state['status'] ?? '') === OnlinePayDict::NOTPAY ? 'pending' : 'attention'));
                $result[$key]++;
                if (in_array($state['virtual_state'] ?? '', ['receipt_pending', 'delivery_pending'], true))$result[$state['virtual_state']]++;
            } catch (\Throwable $error) {
                $result['attention']++;
                self::orders((int) $row['site_id'])->where('id', $row['id'])->whereIn('status', [
                        'prepared',
                        'pending',
                        'receipt_pending',
                        'delivery_pending'
                    ])->update([
                        'next_check_at' => time() + 300,
                        'last_message' => '查询暂未获得完整凭据，原收款事实保留，未重复扣款或发放权益',
                        'update_time' => time()
                    ]);
            }
        }
        return $result;
    }

    private function refundEvent(int $site, array $event): void
    {
        $number = self::scalar($event, 'MchOrderId', 32);
        $order = self::order($site, $number);
        $settings = $this->settings($site, false);
        self::assertSettings($order, $settings);
        self::assertEventIdentity($order, $event);
        $refundId = self::scalar($event, 'MchRefundId', 32);
        $wxRefund = self::scalar($event, 'WxRefundId', 128);
        $amount = self::integer($event['RefundFee'] ?? null);
        $code = self::integer($event['RetCode'] ?? null);
        if ($amount <= 0
            || $amount > (int) $order['amount_cents']
            || (string) ($event['WxOrderId'] ?? '') !== $order['wx_order_id'])throw new CommonException('虚拟支付退款回调原单或金额不对应');
        $externalId = Db::transaction(function () use ($site, $order, $refundId, $wxRefund, $amount, $code): int {
            $this->businessAdapter->lockSite($site);
            $locked = self::order($site, $order['out_trade_no'], true);
            $refund = self::refunds($site)->where('refund_order_id', $refundId)->lock(true)->find();
            if (!$refund) {
                // A verified external refund is a financial fact, not a new
                // outbound refund request. Its exact original is reconciled
                // through the native successful-refund hooks below.
                if ($code === 0) {
                    if (!$this->recordedReceipt($site, $locked))throw new CommonException('外部退款尚未对应本地已核实收款');
                    $this->assertExternalRefundTotal($site, $locked, 0, $amount);
                }
                $id = (int) self::refunds($site)->insertGetId([
                        'site_id' => $site,
                        'virtual_order_id' => $locked['id'],
                        'framework_refund_no' => null,
                        'refund_order_id' => $refundId,
                        'wx_refund_id' => $wxRefund,
                        'amount_cents' => $amount,
                        'status' => $code === 0 ? 'reconciling' : 'failed',
                        'source_kind' => 'platform',
                        'last_message' => $code === 0 ? '渠道退款已核实，正在同步本地权益' : '外部退款未成功，不回退已付业务',
                        'finish_time' => $code === 0 ? time() : 0,
                        'create_time' => time(),
                        'update_time' => time()
                    ]);
                if ($code === 0)$this->persistExternalRecovery($site, $locked);
                return $code === 0 ? $id : 0;
            }
            if ((int) $refund['virtual_order_id'] !== (int) $locked['id']
                || (int) $refund['amount_cents'] !== $amount
                || ($refund['wx_refund_id'] !== null && !hash_equals((string) $refund['wx_refund_id'], $wxRefund)))throw new CommonException('虚拟支付退款回调与原申请不一致');
            if ($refund['status'] === 'success') {
                if ($code !== 0)throw new CommonException('成功退款收到相反结果，请核对原退款');
                return 0;
            }
            if ($refund['source_kind'] !== 'merchant' || empty($refund['framework_refund_no'])) {
                if ((int) $refund['finish_time'] > 0 && $code !== 0)throw new CommonException('外部成功退款收到相反结果，请核对原回执');
                if ($code === 0) {
                    if (!$this->recordedReceipt($site, $locked))throw new CommonException('外部退款尚未对应本地已核实收款');
                    $this->assertExternalRefundTotal($site, $locked, (int) $refund['id'], $amount);
                    self::refunds($site)->where('id', $refund['id'])->update([
                            'status' => 'reconciling',
                            'finish_time' => (int) $refund['finish_time'] > 0 ? (int) $refund['finish_time'] : time(),
                            'update_time' => time()
                        ]);
                    $this->persistExternalRecovery($site, $locked);
                    return (int) $refund['id'];
                }
                return 0;
            }
            $framework = $this->businessAdapter->frameworkRefund($site, self::payment($site, $locked['out_trade_no'], true), (string) $refund['framework_refund_no'], $amount, true);
            if ($code !== 0) {
                self::refunds($site)->where('id', $refund['id'])->update([
                        'status' => 'failed',
                        'wx_refund_id' => $wxRefund,
                        'last_message' => '服务商确认退款未完成',
                        'finish_time' => time(),
                        'update_time' => time()
                    ]);
                if (in_array($framework['status'], [RefundDict::WAIT, RefundDict::DEALING], true))(new CoreRefundService())->refundFail($site, ['refund_no' => $framework['refund_no'], 'fail_reason' => '虚拟支付服务商确认退款失败']);
                return 0;
            }
            if (!in_array($framework['status'], [
                        RefundDict::WAIT,
                        RefundDict::DEALING,
                        RefundDict::SUCCESS
                    ], true))throw new CommonException('原退款已关闭或失败，退款成功需平台核对');
            if ($framework['status'] !== RefundDict::SUCCESS) {
                $done = (new CoreRefundService())->refundSuccess($site, [
                        'out_trade_no' => $locked['out_trade_no'],
                        'refund_no' => $framework['refund_no'],
                        'trade_type' => $framework['trade_type'],
                        'trade_id' => $framework['trade_id']
                    ]);
                if ($done !== true)throw new CommonException('业务退款回退尚未完成');
            }
            self::refunds($site)->where('id', $refund['id'])->update([
                    'status' => 'success',
                    'wx_refund_id' => $wxRefund,
                    'last_message' => '已核实原退款成功',
                    'finish_time' => time(),
                    'update_time' => time()
                ]);
            return 0;
        });
        // The authenticated financial fact is committed before any business
        // hook runs. An ACK is now safe even if the native hook needs retry:
        // the indexed local recovery record survives that failed transaction.
        if ($externalId > 0)$this->recoverExternalRefund($site, $order['out_trade_no'], $externalId, $amount);
    }

    private function assertExternalRefundTotal(int $site, array $order, int $except, int $amount): void
    {
        $completed = 0;
        foreach (self::refunds($site)->where('virtual_order_id', $order['id'])->where('id', '<>', $except)->lock(true)->select()->toArray() as $previous) {
            if ($previous['status'] === 'success'
                || ($previous['source_kind'] === 'platform' && (int) $previous['finish_time'] > 0 && in_array($previous['status'], ['attention', 'reconciling'], true)))$completed += (int) $previous['amount_cents'];
        }
        if ($completed + $amount > (int) $order['amount_cents'])throw new CommonException('外部退款累计金额超过原实付，未变更业务');
    }

    /** Called only while recording a verified external success, under site
     * and original-order locks. Pause dependent rights until replay commits. */
    private function persistExternalRecovery(int $site, array $order): void
    {
        $this->businessAdapter->externalRefundPending($site, self::payment($site, $order['out_trade_no'], true));
        self::orders($site)->where('id', $order['id'])->update([
                'status' => 'attention',
                'next_check_at' => time(),
                'last_message' => '渠道外部退款已核实，正在同步本地权益',
                'update_time' => time()
            ]);
    }

    private function recoverExternalRefund(int $site, string $number, int $refundId, int $amount): string
    {
        try {
            Db::transaction(function () use ($site, $number, $refundId, $amount): void {
                $this->businessAdapter->lockSite($site);
                $order = self::order($site, $number, true);
                $this->completeExternalRefund($site, $order, $refundId, $amount);
            });
        } catch (\Throwable $error) {
            self::refunds($site)->where('id', $refundId)->where('source_kind', 'platform')->where('status', 'reconciling')->update(['last_message' => '渠道退款已核实，本地权益处理稍后自动核对；不会再次退款', 'update_time' => time()]);
        }
        $order = self::order($site, $number);
        $this->scheduleExternalRecovery($site, (int) $order['id']);
        return (string) (self::refunds($site)->where('id', $refundId)->value('status') ?? 'reconciling');
    }

    private function scheduleExternalRecovery(int $site, int $orderId): void
    {
        Db::transaction(function () use ($site, $orderId): void {
            $this->businessAdapter->lockSite($site);
            $order = self::orders($site)->where('id', $orderId)->lock(true)->find();
            if (!$order)return;
            $pending = self::refunds($site)->where('virtual_order_id', $orderId)->where('source_kind', 'platform')->where('status', 'reconciling')->where('finish_time', '>', 0)->lock(true)->find();
            $update = ['next_check_at' => $pending ? time() + 300 : 0, 'update_time' => time()];
            if ($pending) {
                $update['status'] = 'attention';
                $update['last_message'] = '渠道退款已核实，正在等待本地权益恢复；不会再次退款';
            }
            self::orders($site)->where('id', $orderId)->update($update);
        });
    }

    /** Only authenticated original-route refund facts enter this path. Never
     * calls a gateway refund or credits a balance. The native receipt and all
     * rights/commission listeners commit in this same transaction. */
    private function completeExternalRefund(int $site, array $order, int $refundId, int $amount): void
    {
        if (!$this->verifiedReceipt($site, $order, (string) $order['wx_order_id']))throw new CommonException('外部退款尚未对应本地已核实收款');
        $record = self::refunds($site)->where('id', $refundId)->where('virtual_order_id', $order['id'])->lock(true)->find();
        if (!$record
            || $record['source_kind'] !== 'platform'
            || (int) $record['amount_cents'] !== $amount
            || empty($record['wx_refund_id']))throw new CommonException('外部退款关联凭据不完整');
        if ($record['status'] === 'success')return;
        $this->assertExternalRefundTotal($site, $order, $refundId, $amount);
        $result = $this->businessAdapter->completeExternalRefund($site, self::payment($site, $order['out_trade_no'], true), $record);
        if (($result['projection'] ?? '') === 'additional_receipt') {
            self::refunds($site)->where('id', $refundId)->update([
                    'status' => 'success',
                    'framework_refund_no' => null,
                    'last_message' => '已核实额外款原路退款；未改变原订单权益或收益',
                    'finish_time' => (int) $record['finish_time'],
                    'update_time' => time()
                ]);
            self::orders($site)->where('id', $order['id'])->update([
                    'status' => $result['requires_review'] ? 'attention' : 'paid',
                    'next_check_at' => 0,
                    'last_message' => '额外款退款事实已记录，原会员权益未改变',
                    'update_time' => time()
                ]);
            return;
        }
        if ($result['status'] === 'attention') {
            $this->attention($site, (int) $order['id'], '已收到外部成功退款，业务与推广暂停待平台核对');
            self::refunds($site)->where('id', $refundId)->update(['status' => 'attention', 'last_message' => '外部退款已核实，分项或原退款状态待平台核对', 'update_time' => time()]);
            return;
        }
        self::refunds($site)->where('id', $refundId)->update(['framework_refund_no' => $result['refund_no'], 'update_time' => time()]);
        self::refunds($site)->where('id', $refundId)->update([
                'status' => 'success',
                'last_message' => '已核实渠道外部退款，并回退原订单权益；已发推广收益按原台账核对追偿',
                'finish_time' => time(),
                'update_time' => time()
            ]);
        $review = $result['requires_review'];
        self::orders($site)->where('id', $order['id'])->update([
                'status' => $review ? 'attention' : 'paid',
                'last_message' => $review ? '渠道外部退款已核实，原业务仍有待核对事项' : '渠道外部退款与原会员权益已同步',
                'update_time' => time()
            ]);
    }

    /** Commit authenticated provider facts independently from fallible native
     * listeners. Existing source fields and its provider-ID unique index form
     * the durable receipt; no client can set this state. */
    private function confirmPayment(int $site, array $snapshot, string $providerNumber, int $orderType, int $paidAt, int $providerStatus = 2, bool $deliveryCallback = false): array
    {
        if (!in_array($orderType, [0, 7], true)
            || $providerNumber === ''
            || strlen($providerNumber) > 128
            || preg_match('/[\x00-\x20\x7f]/', $providerNumber)
            || $paidAt <= 0
            || $paidAt > time() + 60)throw new CommonException('虚拟支付收款凭据不完整');
        if (Db::connect()->getPdo()->inTransaction())throw new CommonException('已核实收款需要独立入账，请稍后自动核对原单');
        $receipt = Db::transaction(function () use ($site, $snapshot, $providerNumber, $orderType, $paidAt, $providerStatus): array {
            $row = self::order($site, $snapshot['out_trade_no'], true);
            [$pay,$business] = $this->original($site, $row['out_trade_no'], true);
            if ((int) $row['pay_id'] !== (int) $pay['id']
                || (int) $row['order_id'] !== (int) $business['id']
                || (int) $row['member_id'] !== (int) $pay['main_id']
                || (int) $row['amount_cents'] !== Rules::cents((string) $pay['money'])
                || $row['snapshot_hash'] !== $this->businessAdapter->fingerprint($business))throw new CommonException('虚拟支付收款与冻结原单不一致');
            if ($row['wx_order_id'] !== null && !hash_equals((string) $row['wx_order_id'], $providerNumber))throw new CommonException('原单已关联另一笔服务商收款，未重复发放权益');
            $duplicate = Db::name('hsx_lead_virtual_order')->where('app_id', $row['app_id'])->where('env', $row['env'])->where('wx_order_id', $providerNumber)->where('id', '<>', $row['id'])->find();
            if ($duplicate)throw new CommonException('同一服务商收款已关联另一原单');
            if (self::hasReceiptFact($row)) {
                if ((int) $row['order_type'] !== $orderType)throw new CommonException('已核实原收款终端不一致，未改写原交易');
                $paidAt = (int) $row['paid_at']; // The first verified clock is immutable on replay.
            }
            $update = [
                'wx_order_id' => $providerNumber,
                'order_type' => $orderType,
                'last_provider_status' => $providerStatus,
                'paid_at' => $paidAt,
                'update_time' => time()
            ];
            if (!self::hasReceiptFact($row)
                || $row['status'] === 'receipt_pending')$update += [
                    'status' => 'receipt_pending',
                    'next_check_at' => time(),
                    'last_message' => '渠道收款已核实，正在同步原付款及会员权益'
                ];
            self::orders($site)->where('id', $row['id'])->update($update);
            return array_replace($row, $update);
        });
        return $this->deliverReceipt($site, $receipt, $deliveryCallback);
    }

    /** Local replay only. A listener failure can roll back its own native
     * transaction, but never the independently committed provider receipt. */
    private function deliverReceipt(int $site, array $snapshot, bool $deliveryCallback = false): array
    {
        try {
            Db::transaction(function () use ($site, $snapshot, $deliveryCallback): void {
                $this->businessAdapter->lockSite($site);
                $row = self::order($site, $snapshot['out_trade_no'], true);
                [$pay,$business] = $this->original($site, $row['out_trade_no'], true);
                if (!self::hasReceiptFact($row)
                    || (int) $row['pay_id'] !== (int) $pay['id']
                    || (int) $row['order_id'] !== (int) $business['id']
                    || (int) $row['member_id'] !== (int) $pay['main_id']
                    || (int) $row['amount_cents'] !== Rules::cents((string) $pay['money'])
                    || !hash_equals($row['snapshot_hash'], $this->businessAdapter->fingerprint($business)))throw new CommonException('已核实收款未匹配原冻结业务，未发放权益');
                if (self::refunds($site)->where('virtual_order_id', $row['id'])->where('source_kind', 'platform')->where('finish_time', '>', 0)->whereIn('status', [
                            'reconciling',
                            'attention',
                            'success'
                        ])->lock(true)->find()) {
                    self::orders($site)->where('id', $row['id'])->update([
                            'status' => 'attention',
                            'next_check_at' => time() + 300,
                            'last_message' => '已核实原路退款，暂停未完成的权益发放，正在同步原退款',
                            'update_time' => time()
                        ]);
                    return;
                }
                $this->businessAdapter->beforeReceiptDelivery($site, $pay, $business);
                if ((int) $pay['status'] === (int) PayDict::STATUS_FINISH) {
                    if ($pay['type'] !== self::TYPE
                        || !hash_equals((string) $pay['trade_no'], (string) $row['wx_order_id'])
                        || (int) $pay['pay_time'] !== (int) $row['paid_at'])throw new CommonException('原付款流水与已核实渠道收款不一致');
                } else (new CorePayService())->paySuccess($site, [
                        'out_trade_no' => $row['out_trade_no'],
                        'type' => self::TYPE,
                        'trade_no' => $row['wx_order_id'],
                        'pay_time' => (int) $row['paid_at']
                    ]);
                $review = $this->businessAdapter->confirm($site, $business, $row['out_trade_no']);
                $delivered = $deliveryCallback || (int) $row['last_provider_status'] === 4;
                self::orders($site)->where('id', $row['id'])->update([
                        'status' => $review ? 'attention' : ($delivered ? 'paid' : 'delivery_pending'),
                        'last_message' => $review ? '已核实收款，原业务状态需平台核对' : ($delivered ? '收款、权益及渠道发货均已核实' : '收款及会员权益已同步，正在核对渠道发货状态'),
                        'next_check_at' => $review || $delivered ? 0 : time() + 300,
                        'update_time' => time()
                    ]);
            });
        } catch (\Throwable $error) {
            self::orders($site)->where('id', $snapshot['id'])->whereIn('status', [
                    'prepared',
                    'pending',
                    'receipt_pending',
                    'paid',
                    'delivery_pending'
                ])->update([
                    'status' => 'receipt_pending',
                    'next_check_at' => time() + 60,
                    'last_message' => '渠道收款已保存，原权益稍后自动恢复；不会再次扣款',
                    'update_time' => time()
                ]);
            return [
                'status' => 'UNKNOWN',
                'virtual_state' => 'receipt_pending',
                'receipt_received' => true,
                'delivery_pending' => true,
                'query_source' => 'persisted_receipt'
            ];
        }
        return $this->receiptResult($site, self::order($site, $snapshot['out_trade_no']), 'persisted_receipt');
    }

    private static function hasReceiptFact(array $row): bool
    {
        return is_string($row['wx_order_id'] ?? null)
            && $row['wx_order_id'] !== ''
            && (int) ($row['paid_at'] ?? 0) > 0
            && in_array((int) ($row['order_type'] ?? -1), [0, 7], true);
    }

    /** A successful incoming refund can overtake native business delivery.
     * Preserve it against the already committed exact provider receipt, then
     * hold delivery; do not erase it just because native hooks had failed. */
    private function recordedReceipt(int $site, array $row): bool
    {
        if (!self::hasReceiptFact($row))return false;
        [$pay,$business] = $this->original($site, $row['out_trade_no'], true);
        if ((int) $row['pay_id'] !== (int) $pay['id']
            || (int) $row['order_id'] !== (int) $business['id']
            || (int) $row['member_id'] !== (int) $pay['main_id']
            || (int) $row['amount_cents'] !== Rules::cents((string) $pay['money'])
            || !hash_equals($row['snapshot_hash'], $this->businessAdapter->fingerprint($business)))throw new CommonException('渠道退款未对应原已核实收款来源');
        return true;
    }

    private function receiptResult(int $site, array $row, string $source = 'persisted_receipt', ?string $state = null): array
    {
        $received = self::hasReceiptFact($row) && $this->verifiedReceipt($site, $row, (string) $row['wx_order_id']);
        $review = $received && $this->businessAdapter->requiresReview($site, (int) $row['order_id']);
        return [
            'status' => $received && !$review ? OnlinePayDict::SUCCESS : 'UNKNOWN',
            'virtual_state' => $state ?? ($review ? 'attention' : (string) $row['status']),
            'receipt_received' => self::hasReceiptFact($row),
            'delivery_pending' => in_array($row['status'], ['receipt_pending', 'delivery_pending'], true),
            'query_source' => $source
        ];
    }

    private function verifiedReceipt(int $site, array $snapshot, string $providerNumber): bool
    {
        if ($providerNumber === '' || empty($snapshot['wx_order_id']))return false;
        return Db::transaction(function () use ($site, $snapshot, $providerNumber): bool {
            $row = self::order($site, $snapshot['out_trade_no'], true);
            [$pay,$business] = $this->original($site, $row['out_trade_no'], true);
            if ((int) $pay['status'] !== (int) PayDict::STATUS_FINISH)return false;
            if (!hash_equals((string) $row['wx_order_id'], $providerNumber)
                || (string) $pay['type'] !== self::TYPE
                || !hash_equals((string) $pay['trade_no'], $providerNumber)
                || (int) $row['pay_id'] !== (int) $pay['id']
                    || (int) $row['order_id'] !== (int) $business['id']
                    || (int) $row['member_id'] !== (int) $pay['main_id']
                || (int) $row['amount_cents'] !== Rules::cents((string) $pay['money'])
                    || !hash_equals($row['snapshot_hash'], $this->businessAdapter->fingerprint($business)))
                    throw new CommonException('重复通知未匹配原已验证付款凭据，未更改业务');
            return true;
        });
    }

    private function settings(int $site, bool $enabled): array
    {
        return $this->settingsProvider ? ($this->settingsProvider)($site, $enabled) : VirtualPaySettings::get($site, $enabled);
    }
    private function withToken(int $site, array $settings): array
    {
        $settings['access_token'] = $this->tokenProvider ? ($this->tokenProvider)($site) : VirtualPaySettings::accessToken($site);
        return $settings;
    }
    private function remote(int $site, array $settings, array $row): array
    {
        return $this->http->query($this->withToken($site, $settings), ['openid' => $row['openid'], 'out_trade_no' => $row['out_trade_no']]);
    }
    private function attention(int $site, int $id, string $message): void
    {
        self::orders($site)->where('id', $id)->update(['status' => 'attention', 'last_message' => $message, 'update_time' => time()]);
    }
    private static function orders(int $site)
    {
        if ($site <= 0)throw new CommonException('虚拟支付站点无效');
        return Db::name('hsx_lead_virtual_order')->where('site_id', $site);
    }
    private static function refunds(int $site)
    {
        if ($site <= 0)throw new CommonException('虚拟支付站点无效');
        return Db::name('hsx_lead_virtual_refund')->where('site_id', $site);
    }
    private static function order(int $site, string $number, bool $lock = false): array
    {
        self::tradeNumber($number);
        $q = self::orders($site)->where('out_trade_no', $number);
        if ($lock)$q->lock(true);
        $row = $q->find();
        if (!$row)throw new CommonException('虚拟支付原单不存在或不属于本站');
        return $row;
    }
    private static function payment(int $site, string $number, bool $lock = false): array
    {
        $q = Db::name('pay')->where('site_id', $site)->where('out_trade_no', $number);
        if ($lock)$q->lock(true);
        $rows = $q->limit(2)->select()->toArray();
        if (count($rows) !== 1)throw new CommonException('原付款流水未唯一匹配');
        return $rows[0];
    }
    private function original(int $site, string $number, bool $lock = false): array
    {
        $pay = self::payment($site, $number, $lock);
        return [$pay, $this->businessAdapter->resolve($site, $pay, $lock)];
    }
    private static function assertSettings(array $row, array $settings): void
    {
        if ((string) ($settings['app_id'] ?? '') !== $row['app_id']
            || (string) ($settings['offer_id'] ?? '') !== $row['offer_id']
            || (int) ($settings['env'] ?? -1) !== (int) $row['env'])throw new CommonException('虚拟支付账户或环境与原单不一致，未改写原交易');
    }
    private static function assertEventIdentity(array $row, array $event): void
    {
        if (!is_string($event['OpenId'] ?? null) || !hash_equals($row['openid'], $event['OpenId']))throw new CommonException('虚拟支付回调用户与原单不一致');
        foreach (['AppId' => 'app_id', 'OfferId' => 'offer_id'] as $field => $column)if (isset($event[$field])
            && (string) $event[$field] !== $row[$column])throw new CommonException('虚拟支付回调账户不一致');
        if (isset($event['Env']) && self::integer($event['Env']) !== (int) $row['env'])throw new CommonException('虚拟支付回调环境不一致');
    }
    private static function assertQuery(array $row, array $remote): void
    {
        if (!is_int($remote['status'] ?? null)
            || !isset($remote['order_id'])
            || !is_string($remote['order_id'])
            || $remote['order_id'] !== $row['out_trade_no'])throw new CommonException('服务商查单与原单未唯一对应');
        if (isset($remote['env_type']) && self::integer($remote['env_type']) !== ((int) $row['env'] === 0 ? 1 : 2))throw new CommonException('服务商查单环境不一致');
        // requestVirtualPayment signs attach; its documented echo belongs to
        // GoodsInfo.Attach in the authenticated delivery event (checked above).
        // query_order.biz_meta is not a documented alias for that value. Do not
        // make this unrelated metadata an identity gate or overwrite attach.
        // The original account/env/OpenID query, exact order id, paid amount,
        // provider receipt uniqueness and frozen local snapshot remain required.
    }
    private static function assertPaidQuery(array $row, array $remote): void
    {
        if (!in_array($remote['status'] ?? null, [2, 4], true)
            || !in_array($remote['order_type'] ?? null, [0, 7], true)
            || self::integer($remote['order_fee'] ?? null) !== (int) $row['amount_cents']
            || self::integer($remote['paid_fee'] ?? null) !== (int) $row['amount_cents']
            || !is_string($remote['wx_order_id'] ?? null)
            || $remote['wx_order_id'] === '')throw new CommonException('服务商查单缺少一致的收款金额或交易凭据，未开通权益');
    }
    private static function refundResult(array $row): array
    {
        return [
            'refund_no' => (string) ($row['framework_refund_no'] ?? ''),
            'status' => $row['status'] === 'success' ? RefundDict::SUCCESS : ($row['status'] === 'failed' ? RefundDict::FAIL : RefundDict::DEALING),
            'virtual_state' => $row['status'],
            'fail_reason' => $row['status'] === 'failed' ? '服务商已确认退款失败' : ''
        ];
    }
    private static function scalar(array $data, string $key, int $max): string
    {
        $value = $data[$key] ?? null;
        if (!is_string($value) || $value === '' || strlen($value) > $max || preg_match('/[\x00-\x20\x7f]/', $value))throw new CommonException('虚拟支付凭据字段不完整');
        return $value;
    }
    private static function integer($value): int
    {
        if (is_string($value) && preg_match('/^(0|[1-9][0-9]{0,17})$/D', $value))$value = (int) $value;
        if (!is_int($value) || $value < 0)throw new CommonException('虚拟支付金额或状态须为有效整数');
        return $value;
    }
    private static function tradeNumber(string $number): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,31}$/D', $number))throw new CommonException('虚拟支付订单编号须为8至32位字母或数字');
    }
}
