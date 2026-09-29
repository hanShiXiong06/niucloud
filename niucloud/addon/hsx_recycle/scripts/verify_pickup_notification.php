<?php
declare(strict_types=1);

// 执行真实通知/配置/回收日志服务，仅替换数据库与微信 SDK；不会连接数据库、微信或真实用户。
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace core\base { class BaseCoreService { public function __construct() {} } }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace pickup_test {
    class Store {
        public static array $orders = [], $logs = [], $config = [], $notices = [], $members = [], $calls = [], $responses = [];
        public static bool $failLogs = false;
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
            foreach ($this->filters as [$field, $op, $value]) if (($row[$field] ?? null) != $value) return false;
            return true;
        }
        public function findOrEmpty(): Row {
            if ($this->table === 'logs' && Store::$failLogs) throw new \RuntimeException('log DB unavailable');
            foreach (Store::${$this->table} as $row) if ($this->matches($row)) return new Row($row);
            return new Row([]);
        }
        public function update(array $data): void {
            if (Store::$failLogs) throw new \RuntimeException('log DB unavailable');
            foreach (Store::$logs as &$row) if ($this->matches($row)) $row = array_merge($row, $data);
            unset($row);
        }
    }
    class Client {
        public function __construct(private int $site, private string $channel) {}
        public function postJson(string $url, array $body) {
            if (\think\facade\Db::$depth !== 0) throw new \RuntimeException('network inside transaction');
            Store::$calls[] = ['site' => $this->site, 'channel' => $this->channel, 'url' => $url, 'body' => $body];
            if (Store::$hook) { $hook = Store::$hook; Store::$hook = null; $hook(); }
            $result = Store::$responses[$this->channel] ?? ['errcode' => 0, 'msgid' => 'test-receipt'];
            if ($result instanceof \Throwable) throw $result;
            return $result;
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
    class Log { public static function error(...$args): void {} }
}
namespace addon\hsx_recycle\app\model\order {
    class RecycleOrder {
        public static function where(...$args): \pickup_test\Query { return (new \pickup_test\Query('orders'))->where(...$args); }
    }
    class RecycleNoticeLog {
        public static function where(...$args): \pickup_test\Query { return (new \pickup_test\Query('logs'))->where(...$args); }
        public static function create(array $data): \pickup_test\Row {
            if (\pickup_test\Store::$failLogs) throw new \RuntimeException('log DB unavailable');
            $data['id'] = count(\pickup_test\Store::$logs) + 1;
            \pickup_test\Store::$logs[] = $data;
            return new \pickup_test\Row($data);
        }
    }
}
namespace app\service\core\sys {
    class CoreConfigService {
        public function getConfigValue(int $site, string $key): array { return \pickup_test\Store::$config[$site][$key] ?? []; }
        public function setConfig(int $site, string $key, array $value): bool { \pickup_test\Store::$config[$site][$key] = $value; return true; }
    }
}
namespace app\service\core\notice {
    class CoreNoticeService {
        public function getInfo(int $site, string $key): array {
            return \pickup_test\Store::$notices[$site] ?? [];
        }
        public function edit(int $site, string $key, array $data): bool {
            \pickup_test\Store::$notices[$site] = $data;
            return true;
        }
    }
    class NoticeService {
        public static function send(...$args) { throw new \RuntimeException('unchecked generic transport must not be used'); }
    }
}
namespace app\service\core\member {
    class CoreMemberService {
        public function getInfoByMemberId(int $site, int $member): array { return \pickup_test\Store::$members[$site][$member] ?? []; }
    }
}
namespace app\service\core\weapp {
    class CoreWeappService { public static function appApiClient(int $site): \pickup_test\Client { return new \pickup_test\Client($site, 'weapp'); } }
}
namespace app\service\core\wechat {
    class CoreWechatService { public static function appApiClient(int $site): \pickup_test\Client { return new \pickup_test\Client($site, 'wechat'); } }
}
namespace {
    use pickup_test\Store;
    use addon\hsx_recycle\app\service\core\recycle_order\PickupNoticeConfigService as Config;
    use addon\hsx_recycle\app\service\core\recycle_order\CoreRecyclePickupNotifyService as Notify;
    require dirname(__DIR__) . '/app/service/core/recycle_order/PickupNoticeConfigService.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/CoreRecycleNoticeLogService.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/RecyclePickupMessage.php';
    require dirname(__DIR__) . '/app/service/core/recycle_order/CoreRecyclePickupNotifyService.php';

    function get_wap_domain(int $site): string { return 'https://site-' . $site . '.example.test'; }
    function channel(int $enabled = 1, string $keyword = 'character_string1'): array {
        return ['enabled' => $enabled, 'template_id' => 'test_template_not_real', 'content' => [
            ['label' => '订单编号', 'variable' => 'order_no', 'keyword' => $keyword],
            ['label' => '预约状态', 'variable' => 'state_name', 'keyword' => 'phrase2'],
        ]];
    }
    function resetState(): void {
        Store::$orders = [['id' => 10, 'site_id' => 1, 'member_id' => 100, 'order_no' => 'R10'], ['id' => 20, 'site_id' => 2, 'member_id' => 200, 'order_no' => 'R20']];
        Store::$logs = Store::$calls = Store::$responses = Store::$notices = Store::$config = [];
        Store::$failLogs = false; Store::$hook = null;
        Store::$members = [1 => [100 => ['weapp_openid' => 'owner-100-weapp', 'wx_openid' => 'owner-100-wechat']], 2 => [200 => ['weapp_openid' => 'owner-200-weapp']]];
        (new Config())->save(1, ['weapp' => channel(), 'wechat' => channel(0)]);
    }
    function pickup(string $state = 'confirmed', array $changes = []): array {
        return array_merge(['state' => $state, 'carrier_name' => '测试快递', 'pickup_time' => '2026-10-01 14:00-16:00',
            'courier_name' => '测试员', 'courier_phone' => '13800000000', 'tracking_no' => 'TEST123', 'attempt_id' => 'attempt-1'], $changes);
    }
    $count = 0; $failures = [];
    function check(bool $ok, string $message): void { $GLOBALS['count']++; if (!$ok) $GLOBALS['failures'][] = $message; }
    function rejects(callable $fn, string $message): void { try { $fn(); check(false, $message); } catch (\Throwable $e) { check(true, $message); } }
    $service = new Notify();

    resetState();
    $service->notify(1, 10, pickup());
    check(count(Store::$calls) === 1 && count(Store::$logs) === 1, 'configured order dispatches one channel');
    check(Store::$logs[0]['status'] === 1, 'verified errcode 0 marks accepted');
    $receipt = json_decode(Store::$logs[0]['response_data'], true);
    check($receipt['send_accepted'] === true && $receipt['customer_read'] === false, 'accepted never means read');
    check(Store::$calls[0]['body']['page'] === 'addon/hsx_recycle/pages/order/detail?id=10', 'weapp opens correct owner order');
    check(Store::$calls[0]['body']['touser'] === 'owner-100-weapp', 'receiver loaded from order member');
    check(Store::$calls[0]['body']['data']['character_string1']['value'] === 'R10', 'configured field mapping applied');
    $service->notify(1, 10, pickup('confirmed', ['message' => '重放改变描述', 'member_id' => 200]));
    check(count(Store::$calls) === 1 && count(Store::$logs) === 1, 'same snapshot callback stays deduped despite message change');
    $service->notify(1, 10, pickup('assigned'));
    check(count(Store::$calls) === 2, 'meaningful assigned state produces new notification');
    $service->notify(1, 10, pickup('confirmed', ['attempt_id' => 'attempt-2']));
    check(count(Store::$calls) === 3, 'new stable reservation attempt can notify same state');

    resetState();
    Store::$hook = fn() => $service->notify(1, 10, pickup());
    $service->notify(1, 10, pickup());
    check(count(Store::$calls) === 1 && count(Store::$logs) === 1, 'pending claim prevents concurrent/reentrant callback resend');

    resetState();
    $service->notify(2, 10, pickup());
    check(Store::$calls === [] && Store::$logs === [], 'cross-site order cannot resolve receiver or create misleading log');
    foreach (['accepted', 'pending', 'requesting', 'unknown'] as $state) $service->notify(1, 10, pickup($state));
    $service->notify(1, 10, ['state' => []]);
    check(Store::$calls === [], 'unconfirmed and malformed states do not tell user booking succeeded');

    resetState();
    Store::$notices[1] = ['is_weapp' => 0, 'is_wechat' => 0];
    $service->notify(1, 10, pickup());
    check(count(Store::$logs) === 1 && Store::$logs[0]['status'] === 2 && Store::$calls === [], 'disabled config leaves explicit failure without transport');
    $service->notify(1, 10, pickup());
    check(count(Store::$logs) === 1, 'disabled repeated callback dedupes failure log');

    resetState();
    Store::$config[1][Config::CONFIG_KEY]['weapp']['content'] = [];
    $service->notify(1, 10, pickup());
    check(Store::$calls === [] && Store::$logs[0]['status'] === 2, 'missing field mapping must not call transport');

    resetState();
    Store::$notices[1]['weapp_template_id'] = '';
    $service->notify(1, 10, pickup());
    check(Store::$calls === [] && Store::$logs[0]['status'] === 2, 'missing real template id must not call transport');

    resetState();
    Store::$members[1][100] = [];
    $service->notify(1, 10, pickup());
    check(Store::$calls === [] && Store::$logs[0]['status'] === 2, 'unbound member is not silently considered sent');

    foreach ([['errcode' => 43101, 'errmsg' => 'user refuse'], ['errcode' => 40003], false, [], ['errcode' => 0.5], new \RuntimeException('timeout')] as $response) {
        resetState(); Store::$responses['weapp'] = $response;
        $service->notify(1, 10, pickup());
        check(Store::$logs[0]['status'] === 2, 'transport rejection/unknown response is failure, not accepted');
        $service->notify(1, 10, pickup());
        check(count(Store::$calls) === 1, 'failed or uncertain transport is not replayed automatically');
    }

    resetState();
    (new Config())->save(1, ['weapp' => channel(), 'wechat' => channel()]);
    Store::$responses['weapp'] = ['errcode' => 43101, 'errmsg' => 'no subscribe quota'];
    $service->notify(1, 10, pickup());
    check(count(Store::$calls) === 2 && Store::$logs[0]['status'] === 2 && Store::$logs[1]['status'] === 1, 'one channel error cannot stop other channel');
    check(Store::$calls[1]['body']['url'] === 'https://site-1.example.test/addon/hsx_recycle/pages/order/detail?id=10', 'wechat has correct site and owner order URL');

    resetState();
    (new Config())->save(2, ['weapp' => channel(1, 'character_string8'), 'wechat' => channel(0)]);
    $service->notify(2, 20, pickup());
    check(Store::$calls[0]['site'] === 2 && isset(Store::$calls[0]['body']['data']['character_string8']), 'per-site credentials and mappings remain isolated');
    $weapp = require dirname(__DIR__) . '/app/dict/notice/weapp.php';
    $entry = $weapp[Config::NOTICE_KEY];
    $content = Config::withSite(2, fn() => $entry['content'](['site_id' => 1]));
    check($content[0][2] === 'character_string8', 'explicit callback site wins over stale request header');
    check($entry['content'](['site_id' => 1])[0][2] === 'character_string1', 'scoped context restored after read');
    check($entry['tid'] === '' && $entry['kid_list'] === [], 'no fake weapp template catalog id');
    $wechat = require dirname(__DIR__) . '/app/dict/notice/wechat.php';
    check($wechat[Config::NOTICE_KEY]['temp_key'] === '', 'no fake wechat template catalog id');
    $dictionary = require dirname(__DIR__) . '/app/dict/notice/notice.php';
    check($dictionary[Config::NOTICE_KEY]['receiver_type'] === 1 && $dictionary[Config::NOTICE_KEY]['async'] === false, 'member notification dictionary is registered without unsafe queue success');

    $config = new Config();
    rejects(fn() => $config->normalize(['weapp' => ['enabled' => 1]]), 'cannot enable an empty configuration');
    rejects(fn() => $config->normalize(['weapp' => array_merge(channel(), ['content' => [['variable' => 'member_id', 'keyword' => 'thing1']]])]), 'unknown template variable is rejected');
    rejects(fn() => $config->normalize(['weapp' => array_merge(channel(), ['content' => [channel()['content'][0], channel()['content'][0]]])]), 'duplicate template keyword is rejected');
    rejects(fn() => $config->normalize(['weapp' => array_merge(channel(), ['template_id' => 'bad id'])]), 'invalid template ID is rejected');
    check($config->normalize([])['weapp']['enabled'] === 0, 'empty configuration defaults disabled');
    resetState(); Store::$failLogs = true;
    $service->notify(1, 10, pickup());
    check(Store::$calls === [], 'log failure is isolated and fail-closed before send');

    foreach (['failed', 'cancelled', 'picked_up'] as $state) {
        resetState(); $service->notify(1, 10, pickup($state));
        check(count(Store::$calls) === 1, 'supported lifecycle state notifies: ' . $state);
    }
    if ($failures) { fwrite(STDERR, implode("\n", $failures) . "\n"); exit(1); }
    echo "PASS {$count} checks (mock database + mock SDK; no real notifications)\n";
}
