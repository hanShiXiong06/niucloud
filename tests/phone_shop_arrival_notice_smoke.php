<?php
declare(strict_types=1);

// 隔离测试：内存模型与微信假客户端，不连接数据库、不发真实通知。
namespace TestArrival {
    class State {
        public static array $tables = [], $calls = [], $queued = [], $templateCalls = [], $templateResponse = [];
        public static array $response = ['errcode' => 0];
        public static ?array $noticeContent = null;
        public static bool $networkError = false;
        public static bool $taskBusy = false;
    }
    class Rows {
        public function __construct(private array $rows) {}
        public function toArray(): array { return $this->rows; }
    }
    class Record {
        public function __construct(private string $table, private $id, private string $pk) {}
        public function isEmpty(): bool { return !isset(State::$tables[$this->table][$this->id]); }
        public function toArray(): array { return State::$tables[$this->table][$this->id] ?? []; }
        public function __get($name) { return $this->toArray()[$name] ?? null; }
        public function save(array $values): bool {
            foreach (['request_json', 'result_json'] as $key) if (isset($values[$key]) && is_string($values[$key])) $values[$key] = json_decode($values[$key], true);
            State::$tables[$this->table][$this->id] = array_merge($this->toArray(), $values);
            return true;
        }
    }
    class Model {
        protected string $table = '', $pk = 'id';
        private array $conditions = [], $increments = [], $orders = [];
        private ?array $fields = null;
        public function where($field, $op = null, $value = null): static {
            if (is_array($field)) {
                foreach ($field as $key => $item) {
                    if (is_array($item)) $this->conditions[] = $item;
                    else $this->conditions[] = [$key, '=', $item];
                }
            } else $this->conditions[] = func_num_args() === 2 ? [$field, '=', $op] : [$field, $op, $value];
            return $this;
        }
        public function whereIn($field, $values): static { return $this->where($field, 'in', $values); }
        public function lock($value): static { return $this; }
        public function field($value): static { $this->fields = is_array($value) ? $value : array_map('trim', explode(',', $value)); return $this; }
        public function order($value): static { $this->orders = explode(' ', $value); return $this; }
        public function getTableFields(): array { return ['price_snapshot', 'stock_snapshot', 'change_type', 'change_summary']; }
        public function inc($key): static { $this->increments[] = $key; return $this; }
        public function all(): array {
            $rows = array_values(array_filter(State::$tables[$this->table] ?? [], function ($row) {
                foreach ($this->conditions as [$key, $operator, $value]) {
                    $actual = $row[$key] ?? null;
                    $pass = match ($operator) {
                        '=' => $actual == $value, '>' => $actual > $value, '<' => $actual < $value,
                        'in' => in_array($actual, $value), '<>' => $actual != $value,
                        default => throw new \Exception('Unmocked operator ' . $operator),
                    };
                    if (!$pass) return false;
                }
                return true;
            }));
            if ($this->orders) { [$key, $direction] = $this->orders; usort($rows, static fn($a, $b) => (($a[$key] ?? 0) <=> ($b[$key] ?? 0)) * ($direction === 'desc' ? -1 : 1)); }
            if ($this->fields !== null) $rows = array_map(fn($row) => array_intersect_key($row, array_flip($this->fields)), $rows);
            return $rows;
        }
        private function take(): array { $rows = $this->all(); $this->conditions = []; $this->orders = []; $this->fields = null; return $rows; }
        public function select(): Rows { return new Rows($this->take()); }
        public function count(): int { return count($this->take()); }
        public function findOrEmpty(): Record { $row = $this->take()[0] ?? []; return new Record($this->table, $row[$this->pk] ?? -1, $this->pk); }
        public function value($key) { return ($this->all()[0] ?? [])[$key] ?? null; }
        public function column($value, $key = null): array { return $key === null ? array_column($this->all(), $value) : array_column($this->all(), $value, $key); }
        public function update(array $values): int {
            $rows = $this->all();
            foreach ($rows as $row) {
                $record = new Record($this->table, $row[$this->pk], $this->pk);
                $changes = $values;
                foreach ($this->increments as $key) $changes[$key] = ($row[$key] ?? 0) + 1;
                $record->save($changes);
            }
            return count($rows);
        }
        public function create(array $values): Record {
            $id = max(array_keys(State::$tables[$this->table] ?? []) ?: [0]) + 1;
            State::$tables[$this->table][$id] = [$this->pk => $id, 'result_json' => [], 'request_json' => [], 'error_message' => ''];
            $record = new Record($this->table, $id, $this->pk);
            $record->save($values);
            return $record;
        }
    }
}
namespace think\facade {
    class Db {
        public static function transaction(callable $fn) { return $fn(); }
        public static function connect(): object { return new class {
            public function getConfig($key): string { return 'mock'; }
            public function query($sql, $params, $master): array { return str_contains($sql, 'GET_LOCK') ? [['acquired' => \TestArrival\State::$taskBusy ? 0 : 1]] : [['released' => 1]]; }
        }; }
    }
    class Log { public static function error($text): void {} public static function write($text): void {} }
}
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace core\base { class BaseApiService {} }
namespace app\listener\notice_template {
    class BaseNoticeTemplate { public function toReturn($vars, $to): array { return ['vars' => $vars, 'to' => $to]; } }
}
namespace EasyWeChat\Kernel {
    class Config { public function __construct(private array $data) {} public function all(): array { return $this->data; } }
}
namespace app\model\member { class Member extends \TestArrival\Model { protected string $table = 'members', $pk = 'member_id'; } }
namespace app\model\site { class Site extends \TestArrival\Model { protected string $table = 'sites', $pk = 'site_id'; } }
namespace addon\phone_shop\app\model\goods {
    class Brand extends \TestArrival\Model { protected string $table = 'brands', $pk = 'brand_id'; }
    class Goods extends \TestArrival\Model { protected string $table = 'goods', $pk = 'goods_id'; }
    class GoodsSku extends \TestArrival\Model { protected string $table = 'skus', $pk = 'sku_id'; }
    class Category extends \TestArrival\Model { protected string $table = 'categories', $pk = 'category_id'; }
    class GoodsSubscription extends \TestArrival\Model { protected string $table = 'subscriptions', $pk = 'subscription_id'; }
    class GoodsSubscriptionMatch extends \TestArrival\Model { protected string $table = 'matches', $pk = 'match_id'; }
    class GoodsTransferTask extends \TestArrival\Model { protected string $table = 'tasks'; }
}
namespace app\service\core\notice {
    class CoreNoticeService {
        public function getInfo(int $site, string $key): array {
            $dictionary = require dirname(__DIR__) . '/niucloud/addon/phone_shop/app/dict/notice/weapp.php';
            return ['is_weapp' => 1, 'weapp_template_id' => 'tpl-' . $site, 'weapp' => ['content' => \TestArrival\State::$noticeContent ?? $dictionary[$key]['content']]];
        }
    }
}
namespace app\service\core\weapp {
    class CoreWeappConfigService { public function getWeappConfig(int $site): array { return ['app_id' => 'app-' . $site, 'app_secret' => 'mock']; } }
    class CoreWeappService {
        public static function app(int $site): object { return new class($site) {
            private array $config = ['http' => ['retry' => true]];
            public function __construct(private int $site) {}
            public function getConfig(): object { return new \EasyWeChat\Kernel\Config($this->config); }
            public function setConfig($config): void { $this->config = $config->all(); }
            public function createClient(): object {
                if ($this->config['http']['retry'] !== false) throw new \RuntimeException('SDK retry must be disabled');
                return CoreWeappService::appApiClient($this->site);
            }
        }; }
        public static function appApiClient(int $site): object { return new class($site) {
            public function __construct(private int $site) {}
            public function get($path, $options): object {
                \TestArrival\State::$templateCalls[] = ['site' => $this->site, 'path' => $path, 'options' => $options];
                if (\TestArrival\State::$networkError) throw new \RuntimeException('simulated timeout');
                return new class { public function toArray(): array { return \TestArrival\State::$templateResponse; } };
            }
            public function postJson($path, $payload): object {
                \TestArrival\State::$calls[] = ['site' => $this->site, 'payload' => $payload];
                if (\TestArrival\State::$networkError) throw new \RuntimeException('simulated timeout');
                return new class { public function toArray(): array { return \TestArrival\State::$response; } };
            }
        }; }
    }
}
namespace addon\phone_shop\app\job\goods {
    class GoodsArrivalNotice { public static function dispatch(array $params): int { \TestArrival\State::$queued[] = $params; return 1; } }
}
namespace addon\phone_shop\app\service\core\goods {
    class CoreDeviceAttributeService {
        public function batteryRanges(): array { return [['value' => '95_99', 'min' => 95, 'max' => 99]]; }
        public function warrantyRanges(): array { return []; }
    }
}
namespace addon\phone_shop\app\service\core\agent {
    class AgentConfigService { public function getMasterSiteId(): int { return 100005; } }
}
namespace {
    use TestArrival\State;
    use addon\phone_shop\app\service\core\goods\CoreGoodsNoticeService as Notice;
    use addon\phone_shop\app\service\core\goods\CoreGoodsArrivalService as Arrival;
    function env($key, $default = null) { return $key === 'queue.state' ? true : $default; }
    function get_wap_domain($site): string { return 'https://example.test/wap/' . $site; }
    $base = dirname(__DIR__) . '/niucloud/addon/phone_shop/app/service/';
    require $base . 'core/goods/CoreGoodsNoticeService.php';
    require $base . 'core/goods/CoreGoodsSubscriptionMatchService.php';
    require $base . 'core/goods/CoreGoodsArrivalService.php';
    require $base . 'api/goods/GoodsSubscriptionService.php';
    require dirname($base) . '/listener/notice_template/GoodsMatch.php';
    $checks = 0;
    function check(string $message, bool $condition): void { global $checks; if (!$condition) throw new \RuntimeException('FAIL ' . $message); $checks++; echo 'PASS ' . $message . PHP_EOL; }
    function seed(int $members = 2): void {
        State::$tables = []; State::$calls = []; State::$queued = []; State::$response = ['errcode' => 0]; State::$networkError = false; State::$taskBusy = false;
        State::$noticeContent = null;
        State::$templateCalls = []; State::$templateResponse = ['errcode' => 0, 'data' => [['priTmplId' => 'tpl-10', 'title' => '新商品上架提醒', 'content' => "上架商品数:{{number1.DATA}}\n上架时间:{{time2.DATA}}\n品牌:{{thing3.DATA}}\n备注:{{thing4.DATA}}\n供应商:{{thing5.DATA}}\n"]]];
        State::$tables['sites'][10] = ['site_id' => 10, 'site_name' => '测试商城'];
        State::$tables['sites'][11] = ['site_id' => 11, 'site_name' => '其他站点'];
        State::$tables['brands'][1] = ['brand_id' => 1, 'site_id' => 10, 'brand_name' => '苹果'];
        State::$tables['brands'][2] = ['brand_id' => 2, 'site_id' => 10, 'brand_name' => '华为'];
        State::$tables['brands'][99] = ['brand_id' => 99, 'site_id' => 11, 'brand_name' => '外站品牌'];
        for ($i = 1; $i <= 3; $i++) {
            State::$tables['goods'][$i] = ['goods_id' => $i, 'site_id' => 10, 'status' => 1, 'sale_status' => 'available', 'is_online_sellable' => 1, 'delete_time' => 0, 'stock' => 1, 'is_gift' => 0, 'goods_category' => [3], 'goods_name' => '设备' . $i, 'brand_id' => $i === 1 ? 1 : 2, 'create_time' => strtotime('2026-09-23 10:00:00') + $i * 60];
            State::$tables['skus'][$i] = ['sku_id' => $i, 'goods_id' => $i, 'site_id' => 10, 'is_default' => 1, 'stock' => 1, 'price' => 100 + $i];
        }
        State::$tables['goods'][3]['sale_status'] = 'locked';
        State::$tables['categories'][3] = ['site_id' => 10, 'category_id' => 3, 'pid' => 2];
        State::$tables['categories'][2] = ['site_id' => 10, 'category_id' => 2, 'pid' => 0];
        State::$tables['tasks'][1] = ['id' => 1, 'site_id' => 10, 'task_type' => 'import', 'status' => 'completed', 'result_json' => ['imported_goods_ids' => [1, 2, 3]]];
        for ($i = 1; $i <= $members; $i++) {
            State::$tables['members'][$i] = ['member_id' => $i, 'site_id' => 10, 'nickname' => '测试客户' . $i, 'weapp_openid' => 'mock-' . $i];
            State::$tables['subscriptions'][$i] = ['subscription_id' => $i, 'site_id' => 10, 'member_id' => $i, 'status' => 1, 'rule_json' => json_encode(['arrival_notice' => 1, '_weapp_consent' => ['app_id' => 'app-10', 'template_id' => 'tpl-10', 'accepted_at' => 1]])];
        }
    }
    function runQueued(): void { while ($params = array_shift(State::$queued)) (new Arrival())->run($params['siteId'], $params['taskId']); }
    seed();
    $service = new Arrival();
    $preview = $service->preview(10, 1);
    check('预览排除锁定设备', $preview['saleable_count'] === 2 && $preview['excluded_count'] === 1);
    check('预览只包括本站明确同意客户', $preview['member_count'] === 2);
    $task = $service->create(10, 1, 1, 'test');
    $again = $service->create(10, 1, 1, 'test');
    check('重复点击复用同一通知任务', $task['id'] === $again['id'] && count(State::$queued) === 1);
    runQueued();
    check('每个客户一条，而不是每个商品一条', count(State::$calls) === 2);
    $message = State::$calls[0]['payload']['data'];
    check('正式批次使用真实51261模板字段，数量为匹配设备数', array_keys($message) === ['number1', 'time2', 'thing3', 'thing4', 'thing5'] && $message['number1']['value'] === '2');
    check('品牌供应商语义正确，不把商品说明填成品牌', $message['thing3']['value'] === '苹果、华为' && $message['thing5']['value'] === '测试商城' && $message['thing4']['value'] === '本批匹配2台，点击查看实时报价');
    check('批次上架时间取匹配商品最新创建时间并精确到分钟', $message['time2']['value'] === '2026-09-23 10:02');
    check('正式发送不包含未填充的模板变量', !preg_match('/\{[a-z_]+\}/', json_encode($message)));
    check('消息落地为同站点本批列表', State::$calls[0]['payload']['page'] === 'addon/phone_shop/pages/goods/list?arrival_batch_id=1');
    check('受理成功才累计成功', $service->noticeInfo(10, $task['id'])['success_count'] === 2);
    $service->retry(10, $task['id']); runQueued();
    check('完成任务不可重复发送', count(State::$calls) === 2);
    check('分页能查询全部接收者结果', $service->results(10, $task['id'], 2, 1)['data'][0]['member_name'] === '测试客户2');
    try { $service->preview(11, 1); check('不能查询其他站点批次', false); } catch (\core\exception\CommonException $e) { check('不能查询其他站点批次', true); }

    seed(7); $task = $service->create(10, 1, 1, 'test'); runQueued();
    check('超过一批消费者会续投递且不会重复', count(State::$calls) === 7 && $service->noticeInfo(10, $task['id'])['processed_rows'] === 7);
    seed(); $task = $service->create(10, 1, 1, 'test');
    State::$taskBusy = true;
    $service->run(10, $task['id']);
    check('消费者互斥，正在处理时重复队列不发送', !State::$calls);
    try { $service->retry(10, $task['id']); check('不允许恢复仍在执行的任务', false); }
    catch (\core\exception\CommonException $e) { check('不允许恢复仍在执行的任务', true); }
    State::$taskBusy = false;
    State::$tables['tasks'][$task['id']]['status'] = 'processing';
    State::$tables['matches'][1] = ['match_id' => 1, 'site_id' => 10, 'member_id' => 1, 'subscription_id' => 1, 'goods_id' => 0, 'notify_status' => 1, 'goods_fingerprint' => hash('sha256', 'arrival:' . $task['id'] . ':1')];
    runQueued();
    check('进程中断后续跑复用成功回执，统计不重复', count(State::$calls) === 1 && $service->noticeInfo(10, $task['id'])['success_count'] === 2);
    seed(); $task = $service->create(10, 1, 1, 'test');
    State::$tables['subscriptions'][1]['status'] = 0; State::$tables['members'][2]['weapp_openid'] = '';
    runQueued();
    check('排队后取消订阅及未绑定身份均不发微信', count(State::$calls) === 0);
    check('未发送原因有持久记录', count(State::$tables['matches']) === 2 && $service->noticeInfo(10, $task['id'])['skipped_count'] === 2);

    seed(); $task = $service->create(10, 1, 1, 'test'); State::$tables['goods'][1]['status'] = 0; State::$tables['goods'][2]['stock'] = 0; runQueued();
    check('排队后全部不可售不推送', count(State::$calls) === 0);
    seed(); $task = $service->create(10, 1, 1, 'test'); State::$response = ['errcode' => 43101]; runQueued();
    check('额度用完不计成功', $service->noticeInfo(10, $task['id'])['success_count'] === 0);
    State::$response = ['errcode' => 0]; $service->retry(10, $task['id']); runQueued();
    check('明确失败允许管理员重试', $service->noticeInfo(10, $task['id'])['success_count'] === 2 && count(State::$tables['matches']) === 2);
    seed(); $task = $service->create(10, 1, 1, 'test'); State::$networkError = true; runQueued();
    check('网络超时标记待核实，不虚报失败/成功', $service->noticeInfo(10, $task['id'])['result_json']['unknown'] === 2);
    $service->retry(10, $task['id']); runQueued(); check('待核实通知不自动重发', count(State::$calls) === 2);
    seed();
    State::$tables['subscriptions'][1]['rule_json'] = json_encode(['arrival_notice' => 1]);
    State::$tables['subscriptions'][2]['site_id'] = 99;
    check('历史有效订阅可由微信核验，跨站订阅仍排除', $service->preview(10, 1)['member_count'] === 1);
    seed(1);
    $consent = ['app_id' => 'app-10', 'template_id' => 'tpl-10', 'accepted_at' => 1];
    State::$tables['subscriptions'][2] = ['subscription_id' => 2, 'site_id' => 10, 'member_id' => 1, 'status' => 1, 'rule_json' => json_encode(['category_ids' => [2], '_weapp_consent' => $consent])];
    $matcher = new \addon\phone_shop\app\service\core\goods\CoreGoodsSubscriptionMatchService();
    check('开启批次提醒后不叠加逐商品通知', $matcher->match(10, 1) === 0 && !State::$calls);
    State::$tables['subscriptions'][1]['status'] = 0;
    check('仅订阅父分类的客户也可进入匹配批次名单', $service->preview(10, 1)['member_count'] === 1);
    State::$tables['subscriptions'][3] = State::$tables['subscriptions'][2];
    State::$tables['subscriptions'][3]['subscription_id'] = 3;
    State::$tables['subscriptions'][3]['rule_json'] = json_encode(['category_ids' => [3], '_weapp_consent' => $consent]);
    check('取消批次提醒恢复原分类订阅，父子分类只发一次', $matcher->match(10, 1) === 1 && count(State::$calls) === 1);
    check('单商品上新补齐数量品牌站点及上架时间', State::$calls[0]['payload']['data']['number1']['value'] === '1' && State::$calls[0]['payload']['data']['thing3']['value'] === '苹果' && State::$calls[0]['payload']['data']['time2']['value'] === '2026-09-23 10:01');
    check('重复商品事件不重复发', $matcher->match(10, 1) === 0 && count(State::$calls) === 1);
    State::$tables['skus'][1]['price'] = 110;
    check('原筛选订阅的价格变化提醒保留', $matcher->match(10, 1) === 1 && count(State::$calls) === 2);
    check('调价不虚报新增设备，备注明确标记调价', State::$calls[1]['payload']['data']['number1']['value'] === '0' && str_starts_with(State::$calls[1]['payload']['data']['thing4']['value'], '调价提醒：'));
    State::$tables['goods'][2]['sale_status'] = 'sold';
    check('已售商品不触发原筛选通知', $matcher->match(10, 2) === 0);
    seed(1);
    State::$tables['subscriptions'][1]['rule_json'] = json_encode(['category_ids' => [2]]);
    check('保存筛选不等于微信授权', $matcher->match(10, 1) === 0 && !State::$calls);
    $consent['accepted_at'] = time() + 1;
    State::$tables['subscriptions'][1]['rule_json'] = json_encode(['category_ids' => [2], '_weapp_consent' => $consent]);
    check('重新授权后允许后续事件处理原未授权记录', $matcher->match(10, 1) === 1 && count(State::$tables['matches']) === 1);

    seed(1);
    $legacyRule = json_encode(['category_ids' => ['2']]);
    State::$tables['subscriptions'][1]['rule_json'] = $legacyRule;
    check('历史分类订阅缺少本地授权字段也能预览', $service->preview(10, 1)['member_count'] === 1);
    $task = $service->create(10, 1, 1, 'test'); runQueued();
    check('历史父分类订阅汇总一次发送给微信', count(State::$calls) === 1 && State::$calls[0]['payload']['data']['number1']['value'] === '2');
    check('试发不补写或伪造历史微信授权', State::$tables['subscriptions'][1]['rule_json'] === $legacyRule);
    $service->retry(10, $task['id']); runQueued();
    check('历史订阅成功后同批不重复发送', count(State::$calls) === 1);

    seed(1);
    State::$tables['goods'][2]['goods_category'] = [4];
    State::$tables['categories'][4] = ['category_id' => 4, 'pid' => 0, 'site_id' => 10];
    State::$tables['subscriptions'][1]['rule_json'] = $legacyRule;
    State::$tables['subscriptions'][2] = array_replace(State::$tables['subscriptions'][1], ['subscription_id' => 2, 'rule_json' => json_encode(['category_ids' => ['3']])]);
    check('同一客户父子分类重复订阅只算一人', $service->preview(10, 1)['member_count'] === 1);
    $task = $service->create(10, 1, 1, 'test'); runQueued();
    check('分类订阅只汇总匹配设备，不混入其他分类的数量和品牌', count(State::$calls) === 1 && State::$calls[0]['payload']['data']['number1']['value'] === '1' && State::$calls[0]['payload']['data']['thing3']['value'] === '苹果');

    seed(1);
    State::$tables['goods'][1] = array_replace(State::$tables['goods'][1], [
        'sub_title' => '测试型号', 'brand_id' => 7, 'memory_group' => '256', 'condition_grade' => 'A',
        'device_color' => 'black', 'battery_health' => 96, 'label_ids' => [8], 'service_ids' => [9], 'source' => '',
    ]);
    State::$tables['subscriptions'][1]['rule_json'] = json_encode([
        'keyword' => '测试型号', 'category_ids' => ['2'], 'brand_ids' => ['7'], 'memory_group' => ['256'],
        'condition_grade' => ['A'], 'device_color' => ['black'], 'battery_range' => ['95_99'],
        'label_ids' => ['8'], 'service_ids' => ['9'], 'start_price' => '100', 'end_price' => '101',
        'warehouse' => 'local', 'in_stock' => 1,
    ]);
    check('分类以外的内存成色电池价格等筛选继续生效', $service->preview(10, 1)['member_count'] === 1);
    $task = $service->create(10, 1, 1, 'test'); runQueued();
    check('实际查询包含匹配字段，仅发送符合全部筛选的设备', count(State::$calls) === 1 && State::$calls[0]['payload']['data']['number1']['value'] === '1');

    seed(1);
    State::$tables['subscriptions'][1]['rule_json'] = json_encode(['category_ids' => ['999']]);
    check('不匹配的分类订阅不群发', $service->preview(10, 1)['member_count'] === 0);
    State::$tables['subscriptions'][1]['rule_json'] = $legacyRule;
    State::$tables['subscriptions'][1]['status'] = 0;
    check('历史已取消分类订阅不加入发送名单', $service->preview(10, 1)['member_count'] === 0);
    State::$tables['subscriptions'][1]['status'] = 1;
    $task = $service->create(10, 1, 1, 'test');
    State::$tables['subscriptions'][1]['status'] = 0;
    runQueued();
    check('历史分类订阅排队后取消仍不发送', !State::$calls && $service->noticeInfo(10, $task['id'])['skipped_count'] === 1);

    seed(1);
    State::$tables['subscriptions'][1]['rule_json'] = $legacyRule;
    $task = $service->create(10, 1, 1, 'test');
    State::$tables['categories'][3]['pid'] = 999;
    runQueued();
    check('排队后商品分类变更会重新匹配', !State::$calls && $service->noticeInfo(10, $task['id'])['skipped_count'] === 1);

    seed(1);
    State::$tables['subscriptions'][1]['rule_json'] = $legacyRule;
    $task = $service->create(10, 1, 1, 'test'); State::$response = ['errcode' => 43101]; runQueued();
    $results = $service->results(10, $task['id'], 1);
    check('历史订阅由微信实际拒绝时显示43101而非虚报成功', count(State::$calls) === 1 && $service->noticeInfo(10, $task['id'])['success_count'] === 0 && str_contains($results['data'][0]['result'], '43101'));

    seed(1);
    foreach (['app_id' => 'app-other', 'template_id' => 'tpl-other', 'accepted_at' => 0] as $key => $value) {
        State::$tables['subscriptions'][1]['rule_json'] = json_encode(['category_ids' => ['2'], '_weapp_consent' => array_replace($consent, [$key => $value])]);
        check('已记录的无效授权不作为历史缺失放行 ' . $key, $service->preview(10, 1)['member_count'] === 0);
    }
    foreach (['{}', '[]', 'null', '"bad"', '{bad', json_encode(['_weapp_consent' => $consent])] as $invalidRule) {
        State::$tables['subscriptions'][1]['rule_json'] = $invalidRule;
        check('损坏或空订阅规则不能退化成全站群发', $service->preview(10, 1)['member_count'] === 0);
    }
    foreach ([[], ['errcode' => null], ['errcode' => 'bad']] as $response) check('无明确微信回执不计成功', Notice::fromResponse($response)['status'] === Notice::UNKNOWN);
    check('微信明确非零码记失败', Notice::fromResponse(['errcode' => 47003])['status'] === Notice::FAILED);
    $templateError = Notice::fromResponse(['errcode' => 47003, 'errmsg' => 'argument invalid! data.date5.value invalid rid: test-request-1 touser=mock-openid access_token=mock-token']);
    check('正式通知保留微信具体错误字段和请求编号', $templateError['wechat_errcode'] === 47003 && $templateError['invalid_field'] === 'data.date5.value' && $templateError['wechat_request_id'] === 'test-request-1' && str_contains($templateError['reason'], 'data.date5.value'));
    check('正式通知错误不泄露回执中的身份和令牌', !str_contains(json_encode($templateError), 'mock-openid') && !str_contains(json_encode($templateError), 'mock-token'));
    $data = Notice::templateData([['标题', '{name}', 'thing1'], ['类型', '{name}', 'phrase2']], ['name' => str_repeat('测', 30)]);
    check('微信文本字段按类型限长', mb_strlen($data['thing1']['value']) === 20 && mb_strlen($data['phrase2']['value']) === 5);
    seed(1);
    $unknown = array_replace(State::$tables['goods'][1], ['brand_id' => 99]);
    $foreign = array_replace(State::$tables['goods'][2], ['site_id' => 11]);
    $vars = Notice::arrivalVariables(10, [$unknown, $unknown, $foreign]);
    check('通知数据拒绝外站设备，重复设备不多计，品牌查询按站点隔离', $vars['goods_count'] === '1' && $vars['brand_name'] === '品牌未标注' && $vars['supplier_name'] === '测试商城');
    $vars = Notice::arrivalVariables(10, [array_replace($unknown, ['create_time' => '2026-09-23 09:30:00'])]);
    check('通知上架时间兼容模型格式化的时间值', $vars['listing_time'] === '2026-09-23 09:30');
    $notice = new Notice();
    check('网页不能冒充小程序同意', $notice->consent(10, 'h5', ['status' => 'accepted', 'template_id' => 'tpl-10']) === []);
    check('不同模板授权不混用', $notice->consent(10, 'weapp', ['status' => 'accepted', 'template_id' => 'tpl-other']) === []);
    check('正确小程序授权绑定本站模板', $notice->consent(10, 'weapp', ['status' => 'accepted', 'template_id' => 'tpl-10'])['app_id'] === 'app-10');
    $rules = (new \ReflectionClass(\addon\phone_shop\app\service\api\goods\GoodsSubscriptionService::class))->newInstanceWithoutConstructor();
    check('订阅条件不接受客户端伪造授权元数据', $rules->normalizeRule(['arrival_notice' => 1, '_weapp_consent' => ['accepted_at' => 1]]) === ['arrival_notice' => 1]);
    echo '完成 ' . $checks . ' 项隔离测试，无数据库/真实微信请求。' . PHP_EOL;
}
