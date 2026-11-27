<?php
declare(strict_types=1);

namespace core\base { class BaseApiService {} }
namespace think\facade {
    class Db { public static function connect() { return new class { public function getPdo() { return $GLOBALS['pdo_fresh'] ? false : $this->connect(); } public function connect() { return new class { public function inTransaction(): bool { return (bool)$GLOBALS['transaction_open']; } }; } }; } }
    class Log { public static function write($message, $level = 'info'): void { $GLOBALS['logs'][] = [$message, $level]; } }
}
namespace TestWeapp {
    class Result { private array $rows; public function __construct(array $rows) { $this->rows = $rows; } public function toArray(): array { return $this->rows; } }
    class Query {
        protected string $table; private array $where = []; private int $max = 100;
        public function __construct() { $this->table = static::TABLE; }
        public function master($value) { $GLOBALS['reads'][] = [$this->table, 'master', $value]; return $this; }
        public function where($field, $value) { $this->where[$field] = $value; return $this; }
        public function field($fields) { return $this; }
        public function limit($limit) { $this->max = (int)$limit; return $this; }
        private function rows(): array { $GLOBALS['reads'][] = [$this->table, $this->where]; return array_slice(array_values(array_filter($GLOBALS['rows'][$this->table], function ($row) { foreach ($this->where as $field => $value) if (!array_key_exists($field, $row) || (string)$row[$field] !== (string)$value) return false; return true; })), 0, $this->max); }
        public function select() { return new Result($this->rows()); }
        public function findOrEmpty() { return new Result($this->rows()[0] ?? []); }
    }
    class MemoryRedis {
        public array $states = []; public array $leases = []; public array $expiries = []; public string $fail = '';
        public function eval($script, $args, $numberOfKeys) {
            if ($this->fail === 'all') throw new \RuntimeException('fake redis offline');
            [$state, $lease, $token] = $args;
            if (strpos($script, "return 'acquired'") !== false) {
                if (isset($this->states[$state])) return $this->states[$state];
                if (isset($this->leases[$lease])) return 'busy';
                $this->leases[$lease] = $token; return 'acquired';
            }
            if (strpos($script, "'EXISTS'") !== false) {
                if ($this->fail === 'before') throw new \RuntimeException('fake state persistence failure');
                if (($this->leases[$lease] ?? '') !== $token || isset($this->states[$state])) return 0;
                $this->states[$state] = 'unknown'; return 1;
            }
            if ($this->fail === 'complete') throw new \RuntimeException('fake completion persistence failure');
            if (($this->leases[$lease] ?? '') !== $token || ($this->states[$state] ?? '') !== 'unknown') return 0;
            $this->states[$state] = $args[3]; if ($args[3] === 'rejected') $this->expiries[$state] = 300;
            unset($this->leases[$lease]); return 1;
        }
    }
}
namespace addon\tk_jhkd\app\model\order {
    class Order extends \TestWeapp\Query { public const TABLE = 'order'; }
    class OrderAdd extends \TestWeapp\Query { public const TABLE = 'add'; }
}
namespace addon\tk_jhkd\app\model\orderdelivery { class OrderDelivery extends \TestWeapp\Query { public const TABLE = 'delivery'; } }
namespace app\model\pay { class Pay extends \TestWeapp\Query { public const TABLE = 'pay'; } }
namespace app\model\member { class Member extends \TestWeapp\Query { public const TABLE = 'member'; } }
namespace addon\tk_jhkd\app\service\core { class CommonService { public function getConfig($site): array { return ['is_shipping' => $GLOBALS['enabled']]; } } }
namespace app\service\core\weapp {
    class CoreWeappService {
        public static function app($site) {
            return new class($site) {
                private int $site; private $config;
                public function __construct($site) { $this->site = (int)$site; $this->config = new class { private array $data = ['http.retry' => true, 'http.max_retries' => 2]; public function get($key, $default = null) { return $this->data[$key] ?? $default; } public function set($key, $value): void { $this->data[$key] = $value; } }; }
                public function getConfig() { return $this->config; }
                public function createClient() { if ($this->config->get('http.retry') !== false || $this->config->get('http.max_retries') !== 0) throw new \RuntimeException('SDK retries must be disabled before client creation'); return $this; }
                public function postJson($endpoint, $options) {
                    $GLOBALS['requests'][] = [$this->site, $endpoint, $options];
                    if (str_contains($endpoint, 'set_msg_jump_path')) $result = ['errcode' => 0];
                    else { if ($GLOBALS['transport_failure']) throw new \RuntimeException('https://example.invalid/?access_token=SECRET&openid=oSensitivePayer'); $result = $GLOBALS['wechat_result']; }
                    return new class($result) { private array $result; public function __construct($result) { $this->result = $result; } public function toArray(): array { return $this->result; } };
                }
            };
        }
    }
}
namespace {
    use addon\tk_jhkd\app\service\core\RedisWeappDeliveryGuard;
    use addon\tk_jhkd\app\service\core\WeappDeliveryPayload;
    use addon\tk_jhkd\app\service\core\WeappDeliveryService;

    require __DIR__ . '/../app/service/core/WeappDeliveryPayload.php';
    require __DIR__ . '/../app/service/core/RedisWeappDeliveryGuard.php';
    require __DIR__ . '/../app/service/core/WeappDeliveryService.php';
    class Service extends WeappDeliveryService {
        protected function guard(): RedisWeappDeliveryGuard { return new RedisWeappDeliveryGuard($GLOBALS['redis'], 'isolated-test:'); }
    }
    $checks = 0;
    function check($condition, string $label): void { $GLOBALS['checks']++; if (!$condition) throw new \RuntimeException($label); }
    function resetFixture(): void {
        $GLOBALS['transaction_open'] = false; $GLOBALS['pdo_fresh'] = false; $GLOBALS['enabled'] = 1; $GLOBALS['logs'] = []; $GLOBALS['reads'] = []; $GLOBALS['requests'] = [];
        $GLOBALS['transport_failure'] = false; $GLOBALS['wechat_result'] = ['errcode' => 0, 'errmsg' => 'ok']; $GLOBALS['redis'] = new \TestWeapp\MemoryRedis();
        $GLOBALS['rows'] = [
            'order' => [['id' => 10, 'site_id' => 100005, 'order_id' => 'JH20261111000000000001', 'out_trade_no' => 'PAY202611110001', 'member_id' => 75]],
            'add' => [['id' => 11, 'site_id' => 100005, 'order_id' => 'ADD20261111000000000001', 'out_trade_no' => 'PAY202611110002', 'member_id' => 75]],
            'delivery' => [['order_id' => 'JH20261111000000000001', 'member_id' => 75, 'goods' => '手机', 'weight' => '2']],
            'member' => [['site_id' => 100005, 'member_id' => 75, 'weapp_openid' => 'oSensitivePayer']],
            'pay' => [['id' => 40, 'site_id' => 100005, 'status' => 2, 'trade_type' => 'jhkdOrderPay', 'trade_id' => 10,
                'out_trade_no' => 'PAY202611110001', 'trade_no' => '4200001234202611111234567890', 'mch_id' => '', 'channel' => 'weapp', 'type' => 'fastpay_wechatpay', 'main_id' => 75, 'main_type' => 'member']],
        ];
    }
    function shippingCalls(): array { return array_values(array_filter($GLOBALS['requests'], fn($item) => str_contains($item[1], 'upload_shipping_info') || str_contains($item[1], 'notify_confirm_receive'))); }
    function run(string $operation = 'order', array $params = []): array { return (new Service())->runDeferred($operation, $params + ['site_id' => 100005, 'order_id' => 'JH20261111000000000001']); }

    resetFixture();
    $pay = $GLOBALS['rows']['pay'][0];
    check(WeappDeliveryPayload::orderKey($pay) === ['order_number_type' => 2, 'transaction_id' => $pay['trade_no']], 'verified fastpay mapping uses original WeChat transaction id');
    $fallback = $pay; $fallback['trade_no'] = ''; $fallback['mch_id'] = '1900001234';
    check(WeappDeliveryPayload::orderKey($fallback) === ['order_number_type' => 1, 'mchid' => '1900001234', 'out_trade_no' => $pay['out_trade_no']], 'fallback uses original receipt merchant only');
    $fallback['mch_id'] = ''; try { WeappDeliveryPayload::orderKey($fallback); throw new \RuntimeException('missing identity accepted'); } catch (\DomainException $error) { check(true, 'empty original merchant/transaction id blocked'); }
    $result = run(); check($result['status'] === 'done', 'errcode 0 confirmed');
    $call = shippingCalls()[0]; $payload = $call[2]['json'];
    check($payload['order_key'] === ['order_number_type' => 2, 'transaction_id' => $pay['trade_no']], 'no current/default merchant in shipping identity');
    check($payload['logistics_type'] === 3 && $payload['delivery_mode'] === 1, 'existing virtual delivery semantics retained');
    check($payload['payer']['openid'] === 'oSensitivePayer' && $payload['shipping_list'][0]['item_desc'] === '聚合快递代下单-物品:手机-2kg', 'payer and description populated');
    check(isset($call[2]['timeout'], $call[2]['max_duration']), 'bounded client options');
    check(run()['status'] === 'duplicate' && count(shippingCalls()) === 1, 'done is not sent again');
    check(!str_contains(json_encode($GLOBALS['logs']), 'oSensitivePayer'), 'payer not leaked in application logs');
    foreach ($GLOBALS['reads'] as $read) if (($read[1] ?? '') === 'master') check($read[2] === true, 'primary reads');

    resetFixture(); $GLOBALS['transport_failure'] = true;
    check(run()['status'] === 'unknown', 'transport timeout unknown');
    check(in_array('unknown', $GLOBALS['redis']->states, true), 'unknown persisted before transport');
    check(run()['status'] === 'unknown' && count(shippingCalls()) === 1, 'unknown never blindly retried');
    check(!str_contains(json_encode($GLOBALS['logs']), 'SECRET'), 'transport credentials not leaked');
    resetFixture(); $GLOBALS['wechat_result'] = ['errmsg' => 'missing code'];
    check(run()['status'] === 'unknown', 'missing errcode is not success');
    resetFixture(); $GLOBALS['wechat_result'] = ['errcode' => 40013];
    check(run()['status'] === 'rejected', 'explicit WeChat error not reported done');
    check(run()['status'] === 'rejected' && count(shippingCalls()) === 1, 'rejection cooldown prevents immediate repeated callback reporting');
    check(in_array(300, $GLOBALS['redis']->expiries, true), 'explicit rejection only uses bounded cooldown');

    foreach (['all', 'before'] as $failure) {
        resetFixture(); $GLOBALS['redis']->fail = $failure;
        check(run()['status'] === 'blocked' && count(shippingCalls()) === 0, 'redis failure before transport blocks mutation');
    }
    resetFixture(); $GLOBALS['redis']->fail = 'complete';
    check(run()['status'] === 'unknown' && in_array('unknown', $GLOBALS['redis']->states, true), 'failed completion persistence cannot appear done');
    resetFixture(); $GLOBALS['transaction_open'] = true;
    check(run()['status'] === 'blocked' && !$GLOBALS['reads'] && !shippingCalls(), 'uncommitted payment transaction not read/reported');
    resetFixture(); $GLOBALS['pdo_fresh'] = true;
    check(run()['status'] === 'done', 'fresh unopened PDO is connected and checked, not misreported blocked');
    resetFixture(); $GLOBALS['enabled'] = 0;
    check(run()['status'] === 'skipped' && !shippingCalls(), 'disabled configuration respected');
    foreach (['status' => 0, 'trade_id' => 99, 'site_id' => 99999, 'trade_type' => 'otherBusiness', 'type' => 'balancepay', 'channel' => 'h5'] as $field => $value) {
        resetFixture(); $GLOBALS['rows']['pay'][0][$field] = $value;
        check(run()['status'] === 'blocked' && !shippingCalls(), 'wrong original payment facts blocked: ' . $field);
    }
    resetFixture(); $GLOBALS['rows']['pay'][] = $GLOBALS['rows']['pay'][0];
    check(run()['status'] === 'blocked' && !shippingCalls(), 'ambiguous original receipt blocked');
    resetFixture(); $GLOBALS['rows']['member'][0]['weapp_openid'] = '';
    check(run()['status'] === 'blocked' && !shippingCalls(), 'missing payer blocked');
    resetFixture(); $GLOBALS['rows']['delivery'][0]['member_id'] = 76;
    check(run()['status'] === 'blocked' && !shippingCalls(), 'delivery member mismatch blocked');
    resetFixture(); $GLOBALS['rows']['pay'][0]['main_id'] = 76;
    $GLOBALS['rows']['member'][] = ['site_id' => 100005, 'member_id' => 76, 'weapp_openid' => 'oActualOriginalPayer'];
    check(run()['status'] === 'done' && shippingCalls()[0][2]['json']['payer']['openid'] === 'oActualOriginalPayer', 'original pay.main_id identifies actual payer rather than order owner');
    resetFixture();
    check(run('receive', ['out_trade_no' => 'PAY202611110001'])['status'] === 'done', 'receive reminder uses original paid receipt');
    $receive = shippingCalls()[0];
    check(str_contains($receive[1], 'notify_confirm_receive') && $receive[2]['json']['transaction_id'] === $pay['trade_no'], 'receive key is original transaction id');
    check(!isset($receive[2]['json']['merchant_id']), 'receive never sends an empty guessed merchant');
    check(run('receive', ['out_trade_no' => 'PAY202611110001'])['status'] === 'duplicate' && count(shippingCalls()) === 1, 'receive deduplicated separately');
    resetFixture(); $GLOBALS['rows']['pay'][0]['trade_type'] = 'otherBusiness';
    check(run('receive', ['out_trade_no' => 'PAY202611110001'])['status'] === 'blocked' && !shippingCalls(), 'receive cannot bind another business');
    resetFixture();
    $addPay = $GLOBALS['rows']['pay'][0]; $addPay['id'] = 41; $addPay['trade_id'] = 11; $addPay['trade_type'] = 'jhkdOrderAddPay'; $addPay['out_trade_no'] = 'PAY202611110002';
    $GLOBALS['rows']['pay'][] = $addPay;
    check(run('add', ['order_id' => 'ADD20261111000000000001'])['status'] === 'done', 'supplement paid original receipt matched');
    check(shippingCalls()[0][2]['json']['shipping_list'][0]['item_desc'] === '快递运费补差价订单', 'supplement virtual description retained');
    resetFixture(); $GLOBALS['rows']['add'][0]['out_trade_no'] = 'PAY202611110001';
    check(run('add', ['order_id' => 'ADD20261111000000000001'])['status'] === 'blocked' && !shippingCalls(), 'supplement cannot accidentally reuse original-order receipt');
    resetFixture(); $before = $GLOBALS['rows']; run();
    check($GLOBALS['rows'] === $before, 'reporting does not mutate billing/order/member facts');
    check(!str_contains(file_get_contents(__DIR__ . '/../app/service/core/WeappDeliveryService.php'), 'sleep('), 'no synchronous sleep');
    check(!str_contains(file_get_contents(__DIR__ . '/../app/service/core/WeappDeliveryService.php'), 'PayChannelService'), 'no default/current merchant lookup');
    echo "PASS {$checks} isolated WeChat-reporting checks; fake ORM/Redis/network only.\n";
}
