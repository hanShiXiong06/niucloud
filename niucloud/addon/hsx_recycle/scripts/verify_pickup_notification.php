<?php
declare(strict_types=1);

/**
 * 原生通知回归：真实 NoticeService/Notice job/NoticeData/Weapp/Wechat，
 * 仅替换数据库、队列驱动和 TemplateLoader 网络边界，不连接数据库或发送微信消息。
 * 小程序使用真实插件固定字典；公众号 fixture 仅测管道，不代表已核实公众号模板。
 * 微信网络被替换，因此本测试不证明当前账号可获取模板或客户实际收到消息。
 */
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace core\base {
    class BaseCoreService { public $model; public function __construct() {} }
    class BaseJob {
        public static function dispatch(array $params, bool $is_async = true) {
            if (\think\facade\Db::$depth !== 0) throw new \RuntimeException('dispatch inside transaction');
            if (\pickup_test\Store::$queueError) throw new \RuntimeException('test queue unavailable');
            \pickup_test\Store::$dispatches[] = ['class' => static::class, 'params' => $params, 'async' => $is_async];
            if (\pickup_test\Store::$hook) {
                $hook = \pickup_test\Store::$hook; \pickup_test\Store::$hook = null; $hook();
            }
            if ($is_async && \pickup_test\Store::$deferQueue) {
                \pickup_test\Store::$jobs[] = ['class' => static::class, 'params' => $params];
                return 'test-job-' . count(\pickup_test\Store::$jobs);
            }
            return (new static())->doJob(...array_values($params));
        }
        public static function drain(): void {
            while ($entry = array_shift(\pickup_test\Store::$jobs)) {
                (new $entry['class']())->doJob(...array_values($entry['params']));
            }
        }
    }
}
namespace core\exception {
    class CommonException extends \RuntimeException {}
    class NoticeException extends \RuntimeException {}
}
namespace pickup_test {
    class Store {
        public static array $orders = [], $logs = [], $frameworkLogs = [], $notices = [], $members = [], $calls = [], $responses = [], $events = [], $dispatches = [], $jobs = [], $reads = [];
        public static bool $failLogs = false, $failFrameworkLogs = false, $queueError = false, $deferQueue = false, $configError = false;
        public static $hook = null;
    }
    class Row {
        public int $id;
        public function __construct(private array $row) { $this->id = (int)($row['id'] ?? 0); }
        public function toArray(): array { return $this->row; }
        public function isEmpty(): bool { return !$this->row; }
    }
    class Query {
        private array $filters = [];
        public function __construct(private string $table) {}
        public function where($field, $op = null, $value = null): self {
            if (is_array($field)) $this->filters = array_merge($this->filters, $field);
            else $this->filters[] = func_num_args() === 2 ? [$field, '=', $op] : [$field, $op, $value];
            return $this;
        }
        public function lock(bool $lock): self {
            if ($lock && \think\facade\Db::$depth === 0) throw new \RuntimeException('lock outside transaction');
            return $this;
        }
        public function field(string $fields): self { return $this; }
        public function order(string $order): self { return $this; }
        private function matches(array $row): bool {
            foreach ($this->filters as [$field, $op, $value]) {
                if ($op !== '=') throw new \RuntimeException('unexpected query operator: ' . $op);
                if (($row[$field] ?? null) != $value) return false;
            }
            return true;
        }
        public function findOrEmpty(): Row {
            if ($this->table === 'logs' && Store::$failLogs) throw new \RuntimeException('test log DB unavailable');
            foreach (Store::${$this->table} as $row) if ($this->matches($row)) return new Row($row);
            return new Row([]);
        }
        public function update(array $data): void {
            if (Store::$failLogs) throw new \RuntimeException('test log DB unavailable');
            foreach (Store::$logs as &$row) if ($this->matches($row)) $row = array_merge($row, $data);
            unset($row);
        }
    }
}
namespace think\facade {
    class Db {
        public static int $depth = 0;
        public static function transaction(callable $callback) {
            self::$depth++;
            try { return $callback(); } finally { self::$depth--; }
        }
    }
    class Log { public static function error(...$args): void {} public static function write(...$args): void {} }
}
namespace addon\hsx_recycle\app\model\order {
    class RecycleOrder {
        public static function where(...$args): \pickup_test\Query { return (new \pickup_test\Query('orders'))->where(...$args); }
    }
    class RecycleNoticeLog {
        public static function where(...$args): \pickup_test\Query { return (new \pickup_test\Query('logs'))->where(...$args); }
        public static function create(array $data): \pickup_test\Row {
            if (\pickup_test\Store::$failLogs) throw new \RuntimeException('test log DB unavailable');
            $data['id'] = count(\pickup_test\Store::$logs) + 1;
            \pickup_test\Store::$logs[] = $data;
            return new \pickup_test\Row($data);
        }
    }
}
namespace app\model\sys { class SysNotice {} }
namespace app\service\core\notice {
    class CoreNoticeLogService {
        public function add(int $site, array $data): void {
            if (\pickup_test\Store::$failFrameworkLogs) throw new \RuntimeException('test framework log unavailable');
            \pickup_test\Store::$frameworkLogs[] = ['site_id' => $site] + $data;
        }
    }
    class CoreNoticeService {
        public function getInfo(int $site, string $key): array {
            \pickup_test\Store::$reads[] = ['site_id' => $site, 'key' => $key];
            if (\pickup_test\Store::$configError) throw new \RuntimeException('test config unavailable');
            return \pickup_test\Store::$notices[$site] ?? [];
        }
    }
}
namespace app\service\core\member {
    class CoreMemberService {
        public function getInfoByMemberId(int $site, int $member): array { return \pickup_test\Store::$members[$site][$member] ?? []; }
    }
}
namespace app\service\core\weapp {
    class CoreWeappConfigService { public function getWeappConfig(int $site): array { return ['app_id' => 'test-app-' . $site]; } }
}
namespace core\template {
    class TemplateLoader {
        public function __construct(private string $channel, private array $options) {}
        public function send(array $params) {
            if (\think\facade\Db::$depth !== 0) throw new \RuntimeException('network inside transaction');
            \pickup_test\Store::$calls[] = ['site' => $this->options['site_id'], 'channel' => $this->channel, 'params' => $params];
            $response = \pickup_test\Store::$responses[$this->channel] ?? true;
            if ($response instanceof \Throwable) throw $response;
            return $response;
        }
    }
}
namespace {
    use pickup_test\Store;
    use addon\hsx_recycle\app\dict\notice\PickupNoticeTemplate as Definition;
    use addon\hsx_recycle\app\service\core\recycle_order\PickupNoticeConfigService as Config;
    use addon\hsx_recycle\app\service\core\recycle_order\CoreRecyclePickupNotifyService as Notify;

    $addonRoot = dirname(__DIR__);
    $appRoot = dirname(__DIR__, 3) . '/app';
    require $appRoot . '/dict/notice/NoticeTypeDict.php';
    require $appRoot . '/listener/notice_template/BaseNoticeTemplate.php';
    require $appRoot . '/job/notice/Notice.php';
    require $appRoot . '/service/core/notice/NoticeService.php';
    require $appRoot . '/listener/notice/Weapp.php';
    require $appRoot . '/listener/notice/Wechat.php';
    require $addonRoot . '/app/dict/notice/PickupNoticeTemplate.php';
    require $addonRoot . '/app/service/core/recycle_order/PickupNoticeConfigService.php';
    require $addonRoot . '/app/listener/notice_template/PickupUpdate.php';
    require $addonRoot . '/app/service/core/recycle_order/CoreRecycleNoticeLogService.php';
    require $addonRoot . '/app/service/core/recycle_order/CoreRecyclePickupNotifyService.php';
    set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) return false;
        throw new \ErrorException($message, 0, $severity, $file, $line);
    });
    function get_wap_domain(int $site): string { return 'https://site-' . $site . '.example.test'; }
    function event(string $name, array $params): array {
        Store::$events[] = ['name' => $name, 'params' => $params];
        if ($name === 'NoticeData') return [(new \addon\hsx_recycle\app\listener\notice_template\PickupUpdate())->handle($params)];
        if ($name === 'Notice') return [
            (new \app\listener\notice\Weapp())->handle($params), (new \app\listener\notice\Wechat())->handle($params),
        ];
        throw new \RuntimeException('unexpected event: ' . $name);
    }
    function fixture(string $channel): array {
        $content = [
            ['回收订单编号', '{order_no}', 'character_string1'], ['预约状态', '{state_name}', 'phrase2'],
            ['快递公司', '{carrier_name}', 'thing3'], ['温馨提示', '{message}', 'thing4'],
        ];
        return $channel === 'weapp'
            ? Definition::WEAPP
            : ['temp_key' => 'TEST_ONLY_NOT_REAL', 'keyword_name_list' => ['回收订单编号', '预约状态', '快递公司', '温馨提示'], 'content' => $content, 'tips' => 'TEST ONLY'];
    }
    function configuredNotice(): array {
        return ['key' => Definition::KEY, 'async' => true, 'receiver_type' => 1,
            'is_weapp' => 1, 'is_wechat' => 0, 'is_sms' => 0,
            'weapp_template_id' => 'test-private-weapp', 'wechat_template_id' => 'test-private-wechat',
            'weapp' => fixture('weapp'), 'wechat' => fixture('wechat')];
    }
    function resetState(): void {
        Store::$orders = [
            ['id' => 10, 'site_id' => 1, 'member_id' => 100, 'order_no' => 'R10'],
            ['id' => 20, 'site_id' => 2, 'member_id' => 200, 'order_no' => 'R20'],
        ];
        Store::$logs = Store::$frameworkLogs = Store::$calls = Store::$responses = Store::$events = Store::$dispatches = Store::$jobs = Store::$reads = [];
        Store::$failLogs = Store::$failFrameworkLogs = Store::$queueError = Store::$deferQueue = Store::$configError = false;
        Store::$hook = null;
        Store::$members = [1 => [100 => ['weapp_openid' => 'owner-100-weapp', 'wx_openid' => 'owner-100-wechat']],
            2 => [200 => ['weapp_openid' => 'owner-200-weapp', 'wx_openid' => 'owner-200-wechat']]];
        Store::$notices = [1 => configuredNotice(), 2 => configuredNotice()];
    }
    function pickup(string $state = 'confirmed', array $changes = []): array {
        return array_merge(['state' => $state, 'carrier_name' => '测试快递', 'pickup_time' => '2026-10-01 14:00-16:00',
            'courier_name' => '测试员', 'courier_phone' => '13800000000', 'tracking_no' => 'TEST123', 'attempt_id' => 'attempt-1'], $changes);
    }
    $count = 0; $failures = [];
    function check(bool $ok, string $message): void { $GLOBALS['count']++; if (!$ok) $GLOBALS['failures'][] = $message; }
    function rejects(callable $fn, string $message): void { try { $fn(); check(false, $message); } catch (\Throwable $e) { check(true, $message); } }
    function response(int $index = 0): array { return json_decode(Store::$logs[$index]['response_data'] ?? '{}', true) ?: []; }

    resetState(); $service = new Notify(); $config = new Config(); $ready = $config->get(1);
    check($ready['weapp']['catalog_ready'] && $ready['weapp']['template_ready'] && $ready['weapp']['ready'], 'native catalog plus site private id and switch yields ready');
    check(!$ready['wechat']['ready'] && $ready['wechat']['template_ready'], 'disabled native channel remains disabled');
    check($ready['switch_managed_by_notice'] && $ready['management']['notice'] === '/setting/notice/template', 'readiness links to native notice settings');
    check($ready['management']['weapp'] === '/channel/weapp/message' && $ready['management']['wechat'] === '/channel/wechat/message', 'template acquisition uses native channel pages');
    check(!method_exists($config, 'save') && !method_exists($config, 'normalize') && !method_exists($config, 'templateContent'), 'no parallel template mapping CRUD remains');
    check(!array_key_exists('template_id', $ready['weapp']) && !array_key_exists('content', $ready['weapp']), 'read-only summary is not a parallel template editor');
    check(Store::$reads[0] === ['site_id' => 1, 'key' => Definition::KEY], 'native notice lookup uses explicit tenant and fixed key');
    rejects(fn() => $config->get(0), 'invalid site cannot read config');
    foreach (['weapp', 'wechat'] as $channel) {
        check(Definition::isReady($channel, fixture($channel)), 'valid native catalog fixture: ' . $channel);
        check(!Definition::isReady($channel, []), 'empty catalog rejected: ' . $channel);
        $bad = fixture($channel); $bad['content'] = static fn() => [];
        check(!Definition::isReady($channel, $bad), 'dynamic mapping rejected: ' . $channel);
        $bad = fixture($channel); array_pop($bad['content']);
        check(!Definition::isReady($channel, $bad), 'keyword count mismatch rejected: ' . $channel);
        $bad = fixture($channel); $bad['content'][0] = ['incomplete'];
        check(!Definition::isReady($channel, $bad), 'malformed content tuple rejected: ' . $channel);
        $bad = fixture($channel); $bad['content'][0][2] = [];
        check(!Definition::isReady($channel, $bad), 'non-string keyword rejected: ' . $channel);
        $bad = fixture($channel); $bad[$channel === 'weapp' ? 'tid' : 'temp_key'] = [];
        check(!Definition::isReady($channel, $bad), 'non-scalar catalog id rejected: ' . $channel);
        $dict = require $addonRoot . '/app/dict/notice/' . $channel . '.php';
        $registered = $dict[Definition::KEY] ?? null;
        check($registered === null || Definition::isReady($channel, $registered), 'acquisition never includes incomplete pickup definition: ' . $channel);
        check(isset($dict['recycle_order_add']), 'existing order notification preserved: ' . $channel);
    }
    check(!Definition::isReady('sms', fixture('weapp')) && Definition::channel('sms') === [], 'unknown channel is not registered');
    $weappDict = require $addonRoot . '/app/dict/notice/weapp.php';
    check(($weappDict[Definition::KEY] ?? []) === Definition::WEAPP, 'pickup is registered in native mini-program channel catalog');
    check(Definition::WEAPP['tid'] === $weappDict['recycle_order_add']['tid'] && Definition::WEAPP['tid'] === '30171', 'reuse existing recycle template number, not an invented public id');
    check(Definition::WEAPP['kid_list'] === [1, 2, 4], 'pickup selects its own fixed order/status/tip keyword combination');
    $existingFields = array_column($weappDict['recycle_order_add']['content'], 0, 2);
    foreach (Definition::WEAPP['content'] as $field) {
        check(($existingFields[$field[2]] ?? null) === $field[0], 'selected keyword matches existing recycle definition: ' . $field[2]);
    }
    check(Definition::channel('wechat') === [], 'unverified official-account definition stays unregistered');
    resetState(); Store::$notices[1]['weapp_template_id'] = ''; unset(Store::$notices[1]['wechat']);
    $unfetched = $config->get(1);
    check($unfetched['weapp']['catalog_ready'] && !$unfetched['weapp']['template_ready'] && !$unfetched['weapp']['ready'], 'catalog registration alone does not imply private template or send readiness');
    check(!$unfetched['wechat']['catalog_ready'], 'unverified channel readiness is explicit');
    $dictionary = require $addonRoot . '/app/dict/notice/notice.php';
    check($dictionary[Definition::KEY]['receiver_type'] === 1 && $dictionary[Definition::KEY]['async'] === true, 'business notice is native queued member notification');
    check($dictionary[Definition::KEY]['variable'] === Definition::VARIABLES, 'fixed business variables registered in native list');
    $eventConfig = require $addonRoot . '/app/event.php';
    $listener = new \addon\hsx_recycle\app\listener\notice_template\PickupUpdate();
    check(in_array(get_class($listener), $eventConfig['listen']['NoticeData'], true), 'NoticeData hook registered');
    check($listener->handle(['key' => 'recycle_order_add']) === null, 'listener ignores unrelated notices');
    rejects(fn() => $listener->handle(['key' => Definition::KEY, 'site_id' => 2, 'data' => ['order_id' => 10, 'state' => 'confirmed']]), 'listener rejects cross-site order');
    rejects(fn() => $listener->handle(['key' => Definition::KEY, 'site_id' => 1, 'data' => ['order_id' => 10, 'state' => ['confirmed']]]), 'listener rejects malformed state');
    rejects(fn() => $listener->handle(['key' => Definition::KEY, 'site_id' => 1, 'data' => []]), 'listener rejects missing order');
    $trusted = $listener->handle(['key' => Definition::KEY, 'site_id' => 1, 'data' => [
        'order_id' => 10, 'state' => 'confirmed', 'member_id' => 200, 'order_no' => 'FORGED', 'state_name' => 'FORGED', 'carrier_name' => [],
        'weapp_state' => 'FORGED', 'weapp_tip' => 'FORGED',
    ]]);
    check($trusted['to']['member_id'] === 100 && $trusted['vars']['order_no'] === 'R10' && $trusted['vars']['state_name'] === '预约成功', 'recipient and identity are derived from trusted data');
    check($trusted['vars']['carrier_name'] === '' && $trusted['vars']['message'] !== '', 'invalid scalar normalized and fallback message supplied');
    check($trusted['vars']['weapp_state'] === Definition::WEAPP_STATES['confirmed'] && $trusted['vars']['weapp_tip'] === Definition::WEAPP_TIPS['confirmed'], 'short template fields are generated, not caller controlled');
    check($trusted['vars']['__weapp_page'] === Definition::DETAIL_PAGE . '?id=10', 'mini-program page is actual order');
    check($trusted['vars']['__wechat_page'] === 'https://site-1.example.test/' . Definition::DETAIL_PAGE . '?id=10', 'H5 page uses tenant domain');
    Store::$orders[0]['member_id'] = 0;
    rejects(fn() => $listener->handle(['key' => Definition::KEY, 'site_id' => 1, 'data' => ['order_id' => 10, 'state' => 'confirmed']]), 'order without member cannot resolve recipient');

    resetState(); $service->notify(1, 10, pickup());
    check(count(Store::$dispatches) === 1 && Store::$dispatches[0]['class'] === \app\job\notice\Notice::class, 'pickup hands work to real framework Notice job');
    check(array_column(Store::$events, 'name') === ['NoticeData', 'Notice'], 'real job calls both native hooks in order');
    check(count(Store::$calls) === 1 && Store::$calls[0]['channel'] === 'weapp', 'native sender executes enabled channel');
    check(Store::$calls[0]['params']['openid'] === 'owner-100-weapp', 'native sender uses order owner');
    check(Store::$calls[0]['params']['data']['character_string1']['value'] === 'R10', 'native sender resolves fixed variables');
    check(Store::$calls[0]['params']['page'] === Definition::DETAIL_PAGE . '?id=10', 'native sender receives order deep link');
    check(count(Store::$logs) === 1 && Store::$logs[0]['status'] === 4, 'business record means framework handoff not delivery success');
    check(response()['dispatch_submitted'] === true && response()['send_accepted'] === null && response()['customer_read'] === null, 'handoff does not pretend provider acceptance or customer read');
    check(count(Store::$frameworkLogs) === 1 && Store::$frameworkLogs[0]['key'] === Definition::KEY && Store::$frameworkLogs[0]['receiver'] === 'owner-100-weapp', 'real native listener writes framework record');
    check(!isset(Store::$frameworkLogs[0]['params']['receipt']), 'no fabricated provider receipt');
    $service->notify(1, 10, pickup('confirmed', ['message' => 'replay changed description', 'member_id' => 200]));
    check(count(Store::$dispatches) === 1 && count(Store::$logs) === 1, 'free text changes do not defeat dedupe');
    $service->notify(1, 10, pickup('confirmed', ['attempt_id' => 'attempt-2']));
    check(count(Store::$dispatches) === 2, 'new stable attempt can notify again');
    foreach (Definition::STATES as $state => $label) {
        resetState(); $service->notify(1, 10, pickup($state, ['message' => str_repeat('外部技术错误', 40)]));
        $fields = Store::$calls[0]['params']['data'] ?? [];
        check(count(Store::$calls) === 1 && ($fields['phrase2']['value'] ?? '') === Definition::WEAPP_STATES[$state], 'native lifecycle state: ' . $state);
        check(array_keys($fields) === ['character_string1', 'phrase2', 'thing4'], 'native sender uses exactly the acquired keywords: ' . $state);
        check(mb_strlen($fields['phrase2']['value'] ?? '') <= 5 && mb_strlen($fields['thing4']['value'] ?? '') <= 20 && ($fields['thing4']['value'] ?? '') !== '', 'short fields fit template limits: ' . $state);
        check(!str_contains($fields['thing4']['value'], '外部技术错误') && !str_contains($fields['thing4']['value'], '13800000000'), 'raw provider errors and courier phone stay out of short template: ' . $state);
    }
    resetState(); $service->notify(1, 10, pickup('confirmed', ['carrier_name' => '顺丰']));
    check(str_starts_with(Store::$calls[0]['params']['data']['thing4']['value'], '顺丰：'), 'carrier included when complete tip fits');
    resetState(); $service->notify(1, 10, pickup('failed', ['carrier_name' => str_repeat('超长名称', 20)]));
    check(Store::$calls[0]['params']['data']['thing4']['value'] === Definition::WEAPP_TIPS['failed'], 'long carrier omitted rather than truncating customer action');
    resetState(); unset(Store::$notices[1]['wechat']); $service->notify(1, 10, pickup());
    check(count(Store::$calls) === 1 && Store::$calls[0]['channel'] === 'weapp', 'unregistered disabled official-account channel does not block mini-program');
    resetState(); Store::$hook = fn() => $service->notify(1, 10, pickup()); $service->notify(1, 10, pickup());
    check(count(Store::$dispatches) === 1 && count(Store::$logs) === 1, 'pending claim prevents reentrant duplicate');
    resetState(); Store::$deferQueue = true; $service->notify(1, 10, pickup());
    check(count(Store::$jobs) === 1 && Store::$calls === [] && Store::$frameworkLogs === [], 'queued-not-consumed creates no transport or fictitious receipt');
    check(Store::$logs[0]['status'] === 4, 'queued record remains handoff status');
    \core\base\BaseJob::drain();
    check(count(Store::$calls) === 1 && count(Store::$frameworkLogs) === 1 && Store::$jobs === [], 'queue consumption runs actual hooks and native log');

    resetState(); $service->notify(2, 10, pickup());
    check(Store::$dispatches === [] && Store::$logs === [], 'cross-site call cannot create handoff or recipient log');
    $service->notify(2, 20, pickup());
    check(Store::$calls[0]['site'] === 2 && Store::$calls[0]['params']['openid'] === 'owner-200-weapp', 'other tenant uses own credentials and recipient');
    check(Store::$frameworkLogs[0]['site_id'] === 2, 'native receipt stored under correct tenant');
    resetState();
    foreach (['accepted', 'pending', 'requesting', 'unknown'] as $state) $service->notify(1, 10, pickup($state));
    $service->notify(1, 10, ['state' => []]); $service->notify(0, 10, pickup());
    check(Store::$dispatches === [] && Store::$logs === [], 'intermediate malformed states do not announce success');
    resetState(); Store::$notices[1]['is_weapp'] = 0; $service->notify(1, 10, pickup());
    check(Store::$dispatches === [] && Store::$logs[0]['status'] === 3, 'disabled native notice skips rather than sends or fails');
    $service->notify(1, 10, pickup()); check(count(Store::$logs) === 1, 'disabled repeat does not flood logs');
    foreach (['weapp_template_id', 'weapp'] as $missing) {
        resetState(); unset(Store::$notices[1][$missing]); $service->notify(1, 10, pickup());
        check(Store::$dispatches === [] && Store::$logs[0]['status'] === 2, 'missing native prerequisite blocks dispatch: ' . $missing);
        check(Store::$logs[0]['fail_reason'] !== '', 'missing prerequisite has actionable reason: ' . $missing);
    }
    resetState(); Store::$notices[1]['is_wechat'] = 1; unset(Store::$notices[1]['wechat']); $service->notify(1, 10, pickup());
    check(Store::$dispatches === [], 'one malformed enabled channel blocks unsafe native all-channel dispatch');
    resetState(); Store::$queueError = true; $before = Store::$orders; $service->notify(1, 10, pickup());
    check(Store::$calls === [] && Store::$logs[0]['status'] === 2, 'queue failure recorded without claiming sent');
    check(Store::$orders === $before, 'queue failure leaves persisted business data untouched');
    $service->notify(1, 10, pickup()); check(count(Store::$logs) === 1, 'failed dispatch not auto-retried');
    resetState(); Store::$configError = true; $service->notify(1, 10, pickup());
    check(Store::$dispatches === [] && Store::$logs[0]['status'] === 2, 'config failure recorded and non-blocking');
    resetState(); Store::$failLogs = true; $service->notify(1, 10, pickup());
    check(Store::$dispatches === [], 'dedupe persistence failure stops dispatch safely');

    resetState(); Store::$members[1][100] = []; $service->notify(1, 10, pickup());
    check(Store::$calls === [] && Store::$frameworkLogs === [], 'native sender without openid does not call provider');
    check(Store::$logs[0]['status'] === 4 && response()['send_accepted'] === null, 'missing authorization never means confirmed delivery');
    resetState(); Store::$notices[1]['is_wechat'] = 1;
    Store::$responses['weapp'] = new \core\exception\NoticeException('43101 test subscriber refused');
    $service->notify(1, 10, pickup());
    check(count(Store::$calls) === 2 && count(Store::$frameworkLogs) === 2, 'async channel failure does not stop other enabled channel');
    check(str_contains(Store::$frameworkLogs[0]['result'] ?? '', '43101') && !isset(Store::$frameworkLogs[1]['result']), 'native logs record each channel separately');
    check(Store::$logs[0]['status'] === 4, 'dispatch status stays separate from channel result');
    check(Store::$calls[1]['params']['openid'] === 'owner-100-wechat' && Store::$calls[1]['params']['url'] === 'https://site-1.example.test/' . Definition::DETAIL_PAGE . '?id=10', 'native Wechat sender preserves recipient and tenant H5 link');
    check(Store::$calls[1]['params']['miniprogram'] === ['appid' => 'test-app-1', 'pagepath' => Definition::DETAIL_PAGE . '?id=10'], 'native Wechat sender attaches correct tenant mini-program');
    resetState(); Store::$failFrameworkLogs = true; $service->notify(1, 10, pickup()); $service->notify(1, 10, pickup());
    check(count(Store::$calls) === 1 && count(Store::$logs) === 1, 'uncertain post-send log error never auto-retries delivery');

    $routeSource = file_get_contents($addonRoot . '/app/adminapi/route/route.php');
    check(str_contains($routeSource, "get('pickup_notice/config'") && !str_contains($routeSource, "put('pickup_notice/config'"), 'readiness GET only, no parallel template write API');
    check(str_contains($routeSource, 'AdminCheckToken::class') && str_contains($routeSource, 'AdminCheckRole::class'), 'readiness remains authenticated and role-checked');
    $notifySource = file_get_contents($addonRoot . '/app/service/core/recycle_order/CoreRecyclePickupNotifyService.php');
    check(str_contains($notifySource, 'NoticeService::send(') && !str_contains($notifySource, 'RecyclePickupMessage'), 'business dispatch calls native entry not custom sender');
    check(!is_file($addonRoot . '/app/service/core/recycle_order/RecyclePickupMessage.php'), 'custom SDK sender removed');
    check(!str_contains(file_get_contents($addonRoot . '/app/service/core/recycle_order/PickupNoticeConfigService.php'), 'CoreConfigService'), 'no separate config persistence source');
    if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
    echo "PASS {$count} checks (real native notice pipeline; mocked DB/queue/provider boundary; no real delivery)\n";
}
