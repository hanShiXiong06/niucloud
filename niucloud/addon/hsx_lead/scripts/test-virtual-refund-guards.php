<?php
declare(strict_types=1);
/** Isolated PHP 8 test: no framework bootstrap, database, network or real funds. */
namespace { if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
} }
namespace core\exception { class CommonException extends \RuntimeException
{
} }
namespace app\dict\pay {
    final class PayDict
    {
        public const STATUS_FINISH = 2, MEMBER = 'member', BALANCEPAY = 'balancepay', WECHATPAY = 'wechatpay', ALIPAY = 'alipay', OFFLINEPAY = 'offlinepay';
    }
    final class RefundDict
    {
        public const WAIT = 'wait', DEALING = 'dealing', SUCCESS = 'success', FAIL = 'fail', CANCEL = 'cancel', BACK = 'back';
    }
}
namespace think\facade {
    final class Collection
    {
        public function __construct(private array $rows)
        {
        } public function toArray(): array
        {
            return $this->rows;
        }
    }
    final class Query
    {
        private array $filters = [];
        private int $limit = PHP_INT_MAX;
        public function __construct(private string $table)
        {
        }
        public function where($key, $value = null, $third = null): self
        {
            if ($key instanceof \Closure) {
                $key(new self($this->table));
                return $this;
            }$expected = func_num_args() === 3 ? $third : $value;
            $this->filters[] = static fn (array $r): bool => (string) ($r[$key] ?? '') === (string) $expected;
            return $this;
        }
        public function whereOr(...$args): self
        {
            return $this;
        }
        public function whereIn(string $key, array $values): self
        {
            $this->filters[] = static fn (array $r): bool => in_array($r[$key] ?? null, $values, true);
            return $this;
        }
        public function whereLike(string $key, string $value): self
        {
            $text = str_replace('%', '', $value);
            $this->filters[] = static fn (array $r): bool => strpos((string) ($r[$key] ?? ''), $text) !== false;
            return $this;
        }
        public function lock(bool $lock): self
        {
            return $this;
        }
        public function order(...$args): self
        {
            return $this;
        }
        public function field(...$args): self
        {
            return $this;
        }
        public function limit(int $limit): self
        {
            $this->limit = $limit;
            return $this;
        }
        public function select(): Collection
        {
            $rows = Db::$rows[$this->table] ?? [];
            foreach ($this->filters as $f)$rows = array_values(array_filter($rows, $f));
            return new Collection(array_slice($rows, 0, $this->limit));
        }
        public function find(): ?array
        {
            return $this->select()->toArray()[0] ?? null;
        }
        public function column(string $key): array
        {
            return array_column($this->select()->toArray(), $key);
        }
        public function update(array $data): int
        {
            Db::$writes++;
            throw new \RuntimeException('Unexpected write: '.$this->table);
        }
        public function insertGetId(array $data): int
        {
            Db::$writes++;
            throw new \RuntimeException('Unexpected insert: '.$this->table);
        }
    }
    final class Db
    {
        public static array $rows = [ ], $reads = [];
        public static int $writes = 0;
        public static bool $virtualTable = true;
        public static function name(string $table): Query
        {
            self::$reads[] = $table;
            if ($table === 'hsx_lead_virtual_order' && !self::$virtualTable)throw new \RuntimeException('optional table missing');
            return new Query($table);
        }
        public static function transaction(callable $fn)
        {
            return $fn();
        }
    }
}
namespace addon\hsx_lead\app\service {
    final class Context
    {
        public function __construct(public int $site, public int $uid = 1, public int $memberId = 12)
        {
        }
        public function permission(string $key): void
        {
        }
        public function ownership(): object
        {
            return new class {
                public function scope(): array
                {
                    return ['all_data' => true];
                }public function allows(string $key): bool
                {
                    return true;
                }
            };
        }
        public function audit(...$args): void
        {
            \think\facade\Db::$writes++;
            throw new \RuntimeException('Unexpected audit');
        }
    }
}
namespace addon\hsx_lead\app\support {
    final class Data
    {
        public static function decode($value): array
        {
            return is_array($value) ? $value : (json_decode((string) $value, true) ?: []);
        }
        public static function json(array $value): string
        {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        public static function text(array $input, string $key, int $length, bool $required = false): string
        {
            return (string) ($input[$key] ?? '');
        }
        public static function query(string $table, int $site)
        {
            return \think\facade\Db::name('hsx_lead_'.$table)->where('site_id', $site);
        }
        public static function audit(...$args): void
        {
            \think\facade\Db::$writes++;
            throw new \RuntimeException('Unexpected audit');
        }
    }
}
namespace addon\hsx_lead\app\service\virtualpay { final class VirtualPayOrderService
{
    public const TYPE = 'hsx_lead_virtualpay';
} }
namespace addon\hsx_lead\app\service\cooperation {
    use think\facade\Db;
    final class Rules
    {
        public static function cents($n): int
        {
            return (int) round((float) $n * 100);
        }public static function amount(int $n): string
        {
            return number_format($n / 100, 2, '.', '');
        }
    }
    final class PaymentService
    {
        public const TRADE_TYPE = 'hsx_lead_cooperation';
        // Mirrors the real PaymentService::receiptMatches (shared receipt check used by refund code).
        public static function receiptMatches(?array $pay, array $order): bool
        {
            return $pay !== null && $pay !== [] && (int) $pay['status'] === (int) \app\dict\pay\PayDict::STATUS_FINISH && (string) $pay['main_type'] === \app\dict\pay\PayDict::MEMBER && (int) $pay['main_id'] === (int) $order['member_id'] && Rules::cents((string) $pay['money']) === Rules::cents((string) $order['amount']);
        }
    }
    final class PromotionService
    {
        public static function lockSite(int $site): void
        {
        }
    }
    final class Store
    {
        public static function query(string $table, int $site)
        {
            return Db::name('hsx_lead_'.$table)->where('site_id', $site);
        }
        public static function row(string $table, int $site, int $id, bool $lock = false): array
        {
            $r = self::query($table, $site)->where('id', $id)->find();
            if (!$r)throw new \RuntimeException('row missing');
            return $r;
        }
        public static function permission(...$args): void
        {
        }
        public static function requestId(array $input): string
        {
            return (string) $input['request_id'];
        }
    }
    final class OrderPaymentReadService
    {
        public static function payment(array $order, bool $lock = false): ?array
        {
            return Db::name('pay')->where('site_id', $order['site_id'])->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', $order['id'])->where('out_trade_no', $order['out_trade_no'])->find();
        }
        public static function hasTable(string $table): bool
        {
            return true;
        }
        public static function assertManualSource(array $order, array $pay): void
        {
        }
    }
    final class EntityMembershipService
    {
        public static function assertSnapshot(array $order, array $rule): void
        {
        }
    }
    final class MembershipCostService
    {
        public const SCHEMA = 'test.cost.v1';
        public static function balances(int $site, int $merchant, bool $lock = false): array
        {
            return ['balance' => '100.00', 'review_required' => false];
        }
        public static function refundedComponents(array $order, array $rule, array $refunds): array
        {
            return ['review' => false, 'pending' => false, 'deposit' => 0, 'service' => 0, 'experience' => 0, 'quantity' => 0];
        }
        public static function flag($value, string $label): bool
        {
            return (bool) $value;
        }
    }
    final class TeamService
    {
        public static function resolve($context): array
        {
            return ['id' => 9, 'member_id' => 12];
        }public static function assertOwner(...$args): array
        {
            return ['id' => 9, 'member_id' => 12];
        }
    }
    final class ManualRefundService
    {
        public static function metadata(...$args): array
        {
            return ['manual_refund_required' => false, 'manual_refund_receipt_id' => 0, 'can_manual_refund' => false];
        }
    }
    final class FrameworkRefundReservation
    {
        public static function create(...$args): array
        {
            Db::$writes++;
            throw new \RuntimeException('Unexpected refund reservation');
        }
    }
}
namespace {
    use addon\hsx_lead\app\service\Context;
    use addon\hsx_lead\app\service\cooperation\{PaymentRefundService,MembershipDepositService,MembershipCostService,PaymentService};
    use addon\hsx_lead\app\support\Data;
    use core\exception\CommonException;
    use think\facade\Db;
    require dirname(__DIR__).'/app/service/cooperation/PaymentRefundService.php';
    require dirname(__DIR__).'/app/service/cooperation/MembershipDepositService.php';
    $passed = 0;
    function ok(bool $value, string $label): void
    {
        global $passed;
        if (!$value)throw new \RuntimeException('FAIL '.$label);
        $passed++;
        echo 'PASS '.$label.PHP_EOL;
    }
    function denied(callable $call, string $message, string $label): void
    {
        try {
            $call();
        } catch (CommonException$error) {
            if ($error->getMessage() !== $message)throw new \RuntimeException('FAIL '.$label.': '.$error->getMessage());
            ok(true, $label);
            return;
        }throw new \RuntimeException('FAIL '.$label.' did not stop');
    }
    function invoke(object|string $target, string $method, array $args = [])
    {
        $r = new ReflectionMethod($target, $method);
        $r->setAccessible(true);
        return $r->invokeArgs(is_object($target) ? $target : null, $args);
    }
    function setup(int $type = 7, string $payment = 'hsx_lead_virtualpay'): array
    {
        $rule = ['cost_schema' => MembershipCostService::SCHEMA, 'deposit_amount' => '100.00', 'service_amount' => '0.00', 'experience_amount' => '0.00', 'experience_quantity' => '0.00'];
        $order = ['id' => 45, 'site_id' => 100005, 'member_id' => 12, 'merchant_id' => 9, 'purpose' => 'membership', 'status' => 'paid', 'out_trade_no' => 'vp-original-123', 'amount' => '100.00', 'order_no' => 'LC-test', 'rule_json' => Data::json($rule)];
        $pay = ['id' => 8, 'site_id' => 100005, 'trade_type' => PaymentService::TRADE_TYPE, 'trade_id' => '45', 'out_trade_no' => $order['out_trade_no'], 'main_type' => 'member', 'main_id' => 12, 'status' => 2, 'type' => $payment, 'money' => '100.00'];
        $virtual = ['id' => 3, 'site_id' => 100005, 'order_id' => 45, 'pay_id' => 8, 'member_id' => 12, 'out_trade_no' => $order['out_trade_no'], 'amount_cents' => 10000, 'order_type' => $type, 'status' => 'paid', 'wx_order_id' => 'wx-original-123'];
        Db::$rows = ['pay' => [$pay], 'hsx_lead_coop_order' => [$order], 'hsx_lead_virtual_order' => [$virtual], 'member' => [['site_id' => 100005, 'member_id' => 12, 'status' => 1, 'is_del' => 0, 'delete_time' => 0]]];
        Db::$reads = [];
        Db::$writes = 0;
        Db::$virtualTable = true;
        return [$order, $pay];
    }
    $ios = PaymentRefundService::APPLE_REFUND_MESSAGE;
    [$order,$pay] = setup();
    denied(fn () => PaymentRefundService::assertRefundRoute($order, $pay), $ios, 'Apple original receipt requires App Store before refund');
    ok(Db::$writes === 0, 'guard does not manufacture refund rows or balance');
    $summary = PaymentRefundService::summary($order, $pay);
    ok(!$summary['can_request_refund'] && $summary['refund_block_reason'] === $ios, 'payment summary exposes actionable Apple guidance');
    $service = new PaymentRefundService(new Context(100005));
    $detail = invoke($service, 'present', [$order, true]);
    ok(!$detail['can_request'] && !$detail['can_review'] && !$detail['can_continue'] && $detail['message'] === $ios, 'admin presentation disables Apple refund actions');
    denied(fn () => $service->apply(45, ['request_id' => 'test-request', 'reason' => '申请退回押金', 'confirm' => true], true), $ios, 'admin apply stops before creating request');
    ok(Db::$writes === 0, 'blocked application writes no request or audit');
    denied(fn () => invoke($service, 'reserveIncome', [$order, []]), $ios, 'promotion reservation cannot run for Apple payment');
    $request = ['order_id' => 45, 'request_id' => 'test-request', 'reason' => '申请退回押金', 'requested_at' => 123, 'status' => 'pending'];
    $rule = Data::decode($order['rule_json']);
    $rule['order_refund'] = $request;
    $order['rule_json'] = Data::json($rule);
    Db::$rows['hsx_lead_coop_order'] = [$order];
    $version = invoke(PaymentRefundService::class, 'requestVersion', [$order, $request]);
    denied(fn () => $service->review(45, ['decision' => 'approve', 'reason' => '平台审核退款', 'request_version' => $version]), $ios, 'approval stops before refund or commission reservation');
    $rule['order_refund']['status'] = 'approved';
    Db::$rows['hsx_lead_coop_order'][0]['rule_json'] = Data::json($rule);
    denied(fn () => invoke($service, 'dispatch', [45]), $ios, 'existing approved request cannot dispatch Apple refund');
    ok(Db::$writes === 0, 'all Apple payment entry points leave financial records untouched');
    [$order,$pay] = setup(0);
    PaymentRefundService::assertRefundRoute($order, $pay);
    $left = invoke(PaymentRefundService::class, 'remaining', [$order]);
    ok($left['amount'] === 10000, 'Android virtual original can use existing refund calculation');
    $generic = '原虚拟支付收款凭据尚未核实，请平台核对；未发起退款';
    foreach (['site_id' => 100006, 'order_id' => 46, 'pay_id' => 99, 'out_trade_no' => 'other-original', 'member_id' => 99, 'amount_cents' => 9999, 'order_type' => null, 'status' => 'prepared', 'wx_order_id' => ''] as $key => $bad) {
        [$order,$pay] = setup();
        Db::$rows['hsx_lead_virtual_order'][0][$key] = $bad;
        denied(fn () => PaymentRefundService::assertRefundRoute($order, $pay), $generic, 'mismatched virtual '.$key.' is not an Apple or Android authorization');
    }
    [$order,$pay] = setup();
    Db::$rows['hsx_lead_virtual_order'][] = Db::$rows['hsx_lead_virtual_order'][0];
    denied(fn () => PaymentRefundService::assertRefundRoute($order, $pay), $generic, 'ambiguous provider receipts fail closed');
    [$order,$pay] = setup();
    Db::$virtualTable = false;
    denied(fn () => PaymentRefundService::assertRefundRoute($order, $pay), $generic, 'missing virtual table sanitizes virtual refund error');
    foreach (['balancepay', 'wechatpay', 'alipay', 'offlinepay'] as $type) {
        [$order,$pay] = setup(7, $type);
        Db::$virtualTable = false;
        PaymentRefundService::assertRefundRoute($order, $pay);
        $summary = PaymentRefundService::summary($order, $pay);
        ok(!in_array('hsx_lead_virtual_order', Db::$reads, true) && $summary['can_request_refund'], 'ordinary '.$type.' refund is independent of virtual schema');
    }
    [$order,$pay] = setup();
    $deposit = new MembershipDepositService(new Context(100005));
    $summary = $deposit->summary();
    ok(!$summary['can_apply'] && $summary['blocking_reasons'][0]['title'] === $ios, 'deposit exit summary discloses Apple restriction');
    denied(fn () => $deposit->apply(['request_id' => 'exit-test', 'confirm' => true, 'reason' => '正常退出']), $ios, 'deposit exit application stops before suspending merchant');
    denied(fn () => invoke($deposit, 'reserveRefund', [$order, '100.00']), $ios, 'deposit reservation cannot create Apple refund receipt');
    $r = ['id' => 45, 'merchant_id' => 9, 'member_id' => 12, 'request_id' => 'deposit-test', 'status' => 'pending', 'amount' => '100.00', 'reason' => '正常退出', 'created_at' => 123, 'sources' => [['order_id' => 45, 'amount' => '100.00', 'refund_no' => '', 'status' => 'reserved', 'dispatch_started_at' => 0]]];
    $rule = Data::decode($order['rule_json']);
    $rule['membership_deposit_return'] = $r;
    $order['rule_json'] = Data::json($rule);
    Db::$rows['hsx_lead_coop_order'] = [$order];
    $shown = invoke($deposit, 'present', [$order, $r, false]);
    ok(!$shown['can_review'] && !$shown['can_continue'] && $shown['message'] === $ios && $shown['sources'][0]['message'] === $ios, 'deposit details and per-source row show Apple guidance');
    $version = invoke(MembershipDepositService::class, 'version', [$r]);
    denied(fn () => $deposit->review(45, ['decision' => 'approve', 'reason' => '审核退出', 'request_version' => $version]), $ios, 'deposit review stops before any source refund reservation');
    $r['status'] = 'approved';
    $r['sources'][0]['refund_no'] = 'RF-test';
    $rule['membership_deposit_return'] = $r;
    Db::$rows['hsx_lead_coop_order'][0]['rule_json'] = Data::json($rule);
    denied(fn () => invoke($deposit, 'dispatch', [45]), $ios, 'deposit dispatch checks original route before framework receipt');
    ok(Db::$writes === 0, 'Apple exit paths create no refund, balance, audit or exit-state writes');
    [$order,$pay] = setup(0);
    $sources = invoke($deposit, 'sources', [9, false]);
    ok($sources === [['order_id' => 45, 'amount_cents' => 10000]], 'Android deposit sources remain eligible');
    [$order,$pay] = setup(7, 'balancepay');
    Db::$virtualTable = false;
    $sources = invoke($deposit, 'sources', [9, false]);
    ok(count($sources) === 1 && !in_array('hsx_lead_virtual_order', Db::$reads, true), 'ordinary deposit source needs no virtual-payment table');
    echo '完成：'.$passed.'项退款入口隔离检查；未连接数据库、网络或真实账户。'.PHP_EOL;
}
