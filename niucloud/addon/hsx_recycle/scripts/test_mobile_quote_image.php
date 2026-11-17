<?php
declare(strict_types=1);

// php -n addon/hsx_recycle/scripts/test_mobile_quote_image.php
// 执行真实报价分类服务及历史快照服务，覆盖报价图及显示状态更新；权限、数据库、缓存仅使用内存替身。
// 不读取 .env、不访问业务数据库、不上传附件、不发送外部请求。
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace MobileQuoteFixture {
    final class State {
        public static array $categories = [], $history = [], $cleared = [], $rules = [], $role = [], $checks = [], $writes = [], $cached = [], $tagKeys = [];
        public static int $siteId = 100005, $siteStatus = 1, $rowLocks = 0, $masterReads = 0, $transactions = 0;
        public static bool $super = false, $frameworkAllowed = true, $historyFails = false, $categoryUpdateFails = false, $cacheClearFails = false;
        public static function reset(): void {
            self::$categories = [
                8 => ['category_id' => 8, 'site_id' => 100005, 'images' => 'upload/old.png', 'update_time' => 123,
                    'category_name' => '苹果', 'sort' => 99, 'need_vip' => 1, 'is_show' => 1, 'image' => 'cover.png', 'pid' => 2],
                9 => ['category_id' => 9, 'site_id' => 100006, 'images' => 'other.png', 'is_show' => 1],
                10 => ['category_id' => 10, 'site_id' => 0, 'images' => 'public.png', 'is_show' => 1],
                11 => ['category_id' => 11, 'site_id' => 100005, 'images' => 'upload/child.png', 'is_show' => 1, 'pid' => 8],
            ];
            self::$history = self::$cleared = self::$checks = self::$writes = self::$cached = self::$tagKeys = [];
            self::$rules = ['get' => ['recycle/recycle_category'], 'put' => ['recycle/recycle_category/<id>']];
            self::$role = ['is_admin' => 0];
            self::$siteId = 100005; self::$siteStatus = 1; self::$rowLocks = self::$masterReads = self::$transactions = 0;
            self::$super = false; self::$frameworkAllowed = true; self::$historyFails = self::$categoryUpdateFails = self::$cacheClearFails = false;
        }
    }
    final class Row {
        private array $data;
        public function __construct(array $data) { $this->data = $data; }
        public function toArray(): array { return $this->data; }
    }
    final class Request {
        private array $payload;
        public array $fields = [];
        public function __construct(array $payload) { $this->payload = $payload; }
        public function params(array $definitions): array {
            $data = [];
            foreach ($definitions as [$field, $default]) {
                $this->fields[] = $field;
                $data[$field] = $this->payload[$field] ?? $default;
            }
            return $data;
        }
    }
    final class Query {
        private string $table;
        private array $filters = [];
        public function __construct(string $table) { $this->table = $table; }
        public function master($flag) { if ($flag && $this->table === 'category') State::$masterReads++; return $this; }
        public function lock($flag) { if ($flag) State::$rowLocks++; return $this; }
        public function order($order) { return $this; }
        public function where($field, $value = null, $third = null) {
            if (is_array($field)) { foreach ($field as $filter) $this->filters[] = [$filter[0], $filter[2]]; }
            else $this->filters[] = [$field, func_num_args() === 3 ? $third : $value];
            return $this;
        }
        private function matches(array $row): bool {
            foreach ($this->filters as [$field, $value]) if (($row[$field] ?? null) != $value) return false;
            return true;
        }
        private function rows(): array {
            $rows = $this->table === 'category' ? State::$categories : State::$history;
            return array_values(array_filter($rows, fn($row) => $this->matches($row)));
        }
        public function findOrEmpty(): Row { return new Row($this->rows()[0] ?? []); }
        public function value(string $field) { $rows = $this->rows(); return $rows ? $rows[count($rows) - 1][$field] ?? null : null; }
        public function update(array $data): int {
            State::$writes[] = ['filters' => $this->filters, 'data' => $data];
            $count = 0;
            foreach (State::$categories as &$row) if ($this->matches($row)) { $row = array_replace($row, $data); $count++; }
            unset($row);
            if (State::$categoryUpdateFails) throw new \RuntimeException('模拟分类更新失败');
            return $count;
        }
        public function create(array $data) {
            if (State::$historyFails) throw new \RuntimeException('模拟历史表缺失或写入失败');
            $data['id'] = count(State::$history) + 1;
            State::$history[] = $data;
            return new Row($data);
        }
    }
}
namespace core\exception { class AdminException extends \RuntimeException {} }
namespace core\base {
    class BaseAdminController {
        protected $request;
        public function __construct($request) { $this->request = $request; }
    }
    class BaseAdminService {
        protected $site_id, $uid = 37, $username = '报价员', $request, $model;
        public function __construct() { $this->site_id = \MobileQuoteFixture\State::$siteId; $this->request = new \stdClass(); }
    }
}
namespace app\dict\site { final class SiteDict { public const ON = 1, EXPIRE = 2, CLOSE = 3; } }
namespace app\service\admin\auth {
    final class AuthService {
        public function checkRole($request): bool {
            \MobileQuoteFixture\State::$checks[] = 'framework';
            if (!\MobileQuoteFixture\State::$frameworkAllowed) throw new \RuntimeException('框架授权校验不通过');
            return true;
        }
        public static function isSuperAdmin(): bool { return \MobileQuoteFixture\State::$super; }
        public function getAuthRole($siteId): array { return \MobileQuoteFixture\State::$role; }
        public function getAuthApiList(): array { return \MobileQuoteFixture\State::$rules; }
    }
    final class AuthSiteService { public function getSiteInfo(): array { return ['status' => \MobileQuoteFixture\State::$siteStatus]; } }
}
namespace think\facade {
    final class Db {
        public static function transaction(callable $operation) {
            \MobileQuoteFixture\State::$transactions++;
            $categories = \MobileQuoteFixture\State::$categories; $history = \MobileQuoteFixture\State::$history;
            try { return $operation(); }
            catch (\Throwable $error) { \MobileQuoteFixture\State::$categories = $categories; \MobileQuoteFixture\State::$history = $history; throw $error; }
        }
    }
    final class Cache {
        public static function get($key) { return \MobileQuoteFixture\State::$cached[$key] ?? null; }
        public static function tag(string $tag) { return new class($tag) {
            private string $tag;
            public function __construct($tag) { $this->tag = $tag; }
            public function clear(): void {
                if (\MobileQuoteFixture\State::$cacheClearFails) throw new \RuntimeException('模拟缓存清理失败');
                \MobileQuoteFixture\State::$cleared[] = $this->tag;
                foreach (\MobileQuoteFixture\State::$tagKeys[$this->tag] ?? [] as $key) unset(\MobileQuoteFixture\State::$cached[$key]);
            }
            public function set($key, $value, $ttl): void {
                \MobileQuoteFixture\State::$cached[$key] = $value;
                \MobileQuoteFixture\State::$tagKeys[$this->tag][] = $key;
            }
        }; }
    }
}
namespace addon\hsx_recycle\app\model\category {
    final class RecycleCategory { public function __call($method, $args) { return (new \MobileQuoteFixture\Query('category'))->$method(...$args); } }
    final class RecycleCategoryQuoteHistory { public function __call($method, $args) { return (new \MobileQuoteFixture\Query('history'))->$method(...$args); } }
    final class RecycleCategoryConfig { public function __call($method, $args) { return (new \MobileQuoteFixture\Query('config'))->$method(...$args); } }
}
namespace addon\hsx_recycle\app\service\core\category {
    final class CoreRecycleCategoryService { public function getTree($condition): array { return array_values(\MobileQuoteFixture\State::$categories); } }
}
namespace {
    require dirname(__DIR__) . '/app/service/admin/category/RecycleCategoryQuoteHistoryService.php';
    require dirname(__DIR__) . '/app/service/admin/category/RecycleCategoryService.php';
    require dirname(__DIR__) . '/app/adminapi/controller/category/RecycleCategory.php';
    use addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory as RecycleCategoryController;
    use addon\hsx_recycle\app\service\admin\category\RecycleCategoryService;
    use MobileQuoteFixture\Request;
    use MobileQuoteFixture\State;
    $checks = 0;
    function check(string $name, bool $value): void { global $checks; if (!$value) throw new \RuntimeException($name); $checks++; echo 'PASS ' . $name . PHP_EOL; }
    function rejects(string $name, callable $operation, string $contains = ''): void {
        try { $operation(); } catch (\Throwable $error) { check($name, $contains === '' || strpos($error->getMessage(), $contains) !== false); return; }
        throw new \RuntimeException($name . ' 未拒绝');
    }
    function save(array $overrides = [], int $id = 8): array { return (new RecycleCategoryService())->updateQuoteImage($id, array_replace(['images' => 'upload/new.png', 'expected_images' => 'upload/old.png'], $overrides)); }
    function saveVisibility(array $overrides = [], int $id = 8): array { return (new RecycleCategoryService())->updateQuoteVisibility($id, array_replace(['is_show' => 0, 'expected_is_show' => 1], $overrides)); }
    function success($message, $data = null): array { return ['message' => $message, 'data' => $data]; }

    State::reset();
    $original = State::$categories[8];
    $response = save(['remark' => '今日更新']);
    check('更新返回当前图片和变化状态', $response['images'] === 'upload/new.png' && $response['changed'] === true && $response['update_time'] > 123);
    check('修改前锁定分类行', State::$rowLocks === 1);
    check('分类名称、封面、排序、会员限制等不被覆盖', array_diff_assoc($original, State::$categories[8]) === ['images' => 'upload/old.png', 'update_time' => 123]);
    check('没有历史时先备份旧图再记录新图', array_column(State::$history, 'images') === ['upload/old.png', 'upload/new.png']);
    check('历史记下当前员工和站点', State::$history[1]['site_id'] === 100005 && State::$history[1]['operator_id'] === 37 && State::$history[1]['operator_name'] === '报价员' && State::$history[1]['remark'] === '今日更新');
    check('成功提交后清理同站点分类缓存', State::$cleared === ['hsx_recycle_category_100005']);
    check('复用框架的授权检查未被跳过', State::$checks === ['framework']);
    $repeat = save();
    check('响应丢失后重复提交同图幂等成功并重新清缓存', $repeat['changed'] === false && count(State::$history) === 2 && count(State::$cleared) === 2);
    save(['images' => 'https://cdn.example.com/quote/next.webp?token=abc', 'expected_images' => 'upload/new.png']);
    check('支持系统云存储HTTPS图片且不重复备份已知版本', count(State::$history) === 3 && State::$history[2]['images'] === 'https://cdn.example.com/quote/next.webp?token=abc');

    State::reset();
    rejects('并发修改时不覆盖报价', fn() => save(['expected_images' => 'upload/stale.png']), '已被其他操作更新');
    check('并发冲突不写图片历史或清理缓存', State::$categories[8]['images'] === 'upload/old.png' && State::$history === [] && State::$cleared === []);
    rejects('跨站点分类不能写入', fn() => save([], 9), '不属于本站');
    rejects('公共分类不能写入', fn() => save([], 10), '公共报价分类只支持查看');
    rejects('不存在的分类不能写入', fn() => save([], 999), '不存在');
    rejects('无效分类编号不能写入', fn() => save([], 0), '有效');

    foreach (['', 'javascript:alert(1)', 'data:image/png;base64,abc', 'upload/a.png,upload/b.png', 'https://cdn.example/a.svg', '//cdn.example/a.png', 'upload/../secret.png', 'upload/%2e%2e/secret.png', 'upload/%252e%252e/secret.png', 'upload\\bad.png', "upload/a\nb.png", 'upload/%0abad.png', 'http://cdn.example/a.png', 'https:upload/a.png', 'https://user:pass@cdn.example/a.png', ['upload/a.png']] as $bad) {
        rejects('拒绝无效图片 ' . json_encode($bad, JSON_UNESCAPED_UNICODE), fn() => save(['images' => $bad]), '报价');
    }
    rejects('必须传原报价以防覆盖', fn() => (new RecycleCategoryService())->updateQuoteImage(8, ['images' => 'upload/new.png']), '参数');
    rejects('拒绝过长备注', fn() => save(['remark' => str_repeat('字', 201)]), '200');
    check('非法入参不改变原报价', State::$categories[8]['images'] === 'upload/old.png' && State::$history === []);

    State::reset(); State::$historyFails = true;
    rejects('历史表缺失或写入失败时回滚', fn() => save(), '历史表');
    check('快照失败保留旧图和缓存', State::$categories[8]['images'] === 'upload/old.png' && State::$history === [] && State::$cleared === []);
    State::reset(); State::$history = [['id' => 1, 'site_id' => 100005, 'category_id' => 8, 'images' => 'upload/old.png']]; State::$historyFails = true;
    rejects('新图快照失败也回滚已更新分类', fn() => save(), '历史表');
    check('新版本失败不留下半成品', State::$categories[8]['images'] === 'upload/old.png' && count(State::$history) === 1 && State::$cleared === []);

    State::reset(); State::$rules['put'] = [];
    $options = (new RecycleCategoryService())->quoteOptions();
    check('只读员工获得分类但不可编辑', !$options['can_edit'] && $options['site_id'] === 100005 && $options['categories'][2]['site_id'] === 0);
    rejects('只读员工不能绕过前端写入', fn() => save(), '编辑权限');
    State::$rules['get'] = [];
    rejects('缺少查看权限不返回报价选项', fn() => (new RecycleCategoryService())->quoteOptions(), '查看权限');
    State::reset(); State::$frameworkAllowed = false;
    rejects('框架授权失败不被插件权限放行', fn() => save(), '框架授权');
    foreach ([2, 3] as $status) {
        State::reset(); State::$siteStatus = $status;
        check('停站或过期时不显示编辑能力 ' . $status, (new RecycleCategoryService())->quoteOptions()['can_edit'] === false);
        rejects('停站或过期不能直接更新 ' . $status, fn() => save(), '停用或过期');
    }
    State::reset(); State::$rules = []; State::$role = ['is_admin' => 1];
    check('本站管理员沿用既有权限', save()['changed'] === true);
    State::reset(); State::$siteId = 0; State::$super = true;
    rejects('超级管理员也不能在公共站点通过移动接口修改', fn() => save(), '业务站点');

    State::reset();
    $original = State::$categories;
    State::$history = [['id' => 1, 'site_id' => 100005, 'category_id' => 8, 'images' => 'upload/old.png', 'remark' => '原报价快照']];
    $originalHistory = State::$history;
    $options = (new RecycleCategoryService())->quoteOptions();
    \think\facade\Cache::tag('hsx_recycle_category_100006')->set('hsx_recycle_category_100006_tree', ['其他站点缓存'], 86400);
    check('显示状态沿用报价编辑能力且已缓存原分类树', $options['can_edit'] === true && State::$cached['hsx_recycle_category_100005_tree'][0]['is_show'] === 1);
    $response = saveVisibility(['images' => 'upload/evil.png', 'category_name' => '不得覆盖', 'need_vip' => 0, 'sort' => 0, 'pid' => 0]);
    check('隐藏接口返回已持久化状态和更新时间', $response['category_id'] === 8 && $response['is_show'] === 0 && $response['changed'] === true && $response['update_time'] > 123 && State::$categories[8]['is_show'] === 0);
    check('显示更新在事务内从主库锁定分类', State::$transactions === 1 && State::$masterReads === 1 && State::$rowLocks === 1);
    check('显示更新数据库仅写显示状态和更新时间', array_keys(State::$writes[0]['data']) === ['is_show', 'update_time'] && State::$writes[0]['filters'] === [['category_id', 8], ['site_id', 100005]]);
    check('显示更新不覆盖名称图片排序采集及会员限制', array_diff_assoc($original[8], State::$categories[8]) === ['update_time' => 123, 'is_show' => 1]);
    check('隐藏父分类不级联写子分类、其他站点及公共分类', State::$categories[9] === $original[9] && State::$categories[10] === $original[10] && State::$categories[11] === $original[11]);
    check('显示更新不新增或改写图片历史快照', State::$history === $originalHistory);
    check('显示更新提交后清理本站缓存', State::$cleared === ['hsx_recycle_category_100005'] && !isset(State::$cached['hsx_recycle_category_100005_tree']));
    check('显示更新不清理其他站点缓存', State::$cached['hsx_recycle_category_100006_tree'] === ['其他站点缓存']);
    $options = (new RecycleCategoryService())->quoteOptions();
    check('缓存刷新后管理树仍返回隐藏分类及其最新状态', $options['categories'][0]['category_id'] === 8 && $options['categories'][0]['is_show'] === 0);
    $updated = State::$categories[8];
    $repeat = saveVisibility();
    check('显示更新响应丢失后的重试幂等成功', $repeat['changed'] === false && $repeat['is_show'] === 0 && $repeat['update_time'] === $updated['update_time'] && count(State::$writes) === 1 && State::$history === $originalHistory);
    check('显示幂等重试也补清本站缓存', count(State::$cleared) === 2 && !isset(State::$cached['hsx_recycle_category_100005_tree']));
    $response = saveVisibility(['is_show' => 1, 'expected_is_show' => 0]);
    check('已隐藏分类可重新显示且不丢失原资料', $response['changed'] === true && State::$categories[8]['is_show'] === 1 && State::$categories[8]['images'] === $original[8]['images'] && State::$categories[8]['need_vip'] === 1 && State::$history === $originalHistory);
    $repeat = saveVisibility(['is_show' => 1, 'expected_is_show' => 0]);
    check('当前值已等于目标时不因旧前值报冲突', $repeat['changed'] === false && count(State::$writes) === 2);
    State::reset();
    saveVisibility();
    save();
    check('隐藏后再更新报价图片不会重新显示分类', State::$categories[8]['is_show'] === 0 && State::$categories[8]['images'] === 'upload/new.png');

    State::reset();
    $original = State::$categories;
    rejects('显示并发前值不一致时明确拒绝覆盖', fn() => saveVisibility(['expected_is_show' => 0]), '显示状态已被其他操作更新');
    check('显示并发冲突不写分类历史或清理缓存', State::$categories === $original && State::$history === [] && State::$writes === [] && State::$cleared === []);
    rejects('显示接口禁止跨站点更新', fn() => saveVisibility([], 9), '不属于本站');
    rejects('显示接口禁止改公共分类', fn() => saveVisibility([], 10), '公共报价分类只支持查看');
    rejects('显示接口禁止改不存在分类', fn() => saveVisibility([], 999), '不存在');
    rejects('显示接口拒绝零分类编号', fn() => saveVisibility([], 0), '有效');
    rejects('显示接口拒绝负分类编号', fn() => saveVisibility([], -8), '有效');
    check('显示接口的站点隔离失败不产生写入', State::$categories === $original && State::$writes === [] && State::$cleared === []);

    State::reset();
    foreach ([false, true, '0', '1', 0.0, 1.0, -1, 2, '', null, [], [0], new \stdClass()] as $bad) {
        rejects('显示目标拒绝非0/1整数 ' . json_encode($bad, JSON_UNESCAPED_UNICODE), fn() => saveVisibility(['is_show' => $bad]), '显示状态参数无效');
        rejects('显示前值拒绝非0/1整数 ' . json_encode($bad, JSON_UNESCAPED_UNICODE), fn() => saveVisibility(['expected_is_show' => $bad]), '显示状态参数无效');
    }
    rejects('显示目标缺失不能默认隐藏', fn() => (new RecycleCategoryService())->updateQuoteVisibility(8, ['expected_is_show' => 1]), '显示状态参数无效');
    rejects('显示前值缺失不能覆盖当前状态', fn() => (new RecycleCategoryService())->updateQuoteVisibility(8, ['is_show' => 0]), '显示状态参数无效');
    check('显示非法参数在进入事务前被拒绝且原资料未变', State::$transactions === 0 && State::$rowLocks === 0 && State::$writes === [] && State::$categories[8]['is_show'] === 1 && State::$cleared === []);

    State::reset(); State::$rules['put'] = [];
    rejects('只读员工不可直调显示接口', fn() => saveVisibility(), '编辑权限');
    check('无显示编辑权限不进入事务', State::$transactions === 0 && State::$writes === []);
    State::reset(); State::$frameworkAllowed = false;
    rejects('显示接口不跳过框架授权', fn() => saveVisibility(), '框架授权');
    foreach ([2, 3] as $status) {
        State::reset(); State::$siteStatus = $status;
        rejects('停站或过期不可直调显示接口 ' . $status, fn() => saveVisibility(), '停用或过期');
        check('停站或过期显示拒绝无副作用 ' . $status, State::$transactions === 0 && State::$writes === [] && State::$cleared === []);
    }
    State::reset(); State::$rules = []; State::$role = ['is_admin' => 1];
    check('本站管理员可以按既有权限改显示状态', saveVisibility()['changed'] === true);
    State::reset(); State::$rules = ['put' => ['recycle/recycle_category/:id']];
    check('显示接口兼容既有冒号参数分类编辑授权', saveVisibility()['changed'] === true);
    State::reset(); State::$rules = []; State::$super = true;
    check('超级管理员可在业务站点改显示状态', saveVisibility()['changed'] === true);
    foreach ([0, -1] as $siteId) {
        State::reset(); State::$siteId = $siteId; State::$super = true;
        rejects('非业务站点不能通过显示接口改公共分类 ' . $siteId, fn() => saveVisibility([], 10), '业务站点');
    }

    State::reset(); State::$categoryUpdateFails = true;
    $original = State::$categories;
    rejects('显示数据库更新失败回滚事务', fn() => saveVisibility(), '分类更新失败');
    check('显示写入失败恢复原资料且不清缓存', State::$categories === $original && State::$history === [] && State::$cleared === []);
    State::reset(); State::$cacheClearFails = true;
    rejects('显示提交后缓存暂时失败可被识别', fn() => saveVisibility(), '缓存清理失败');
    check('缓存失败不误称数据库回滚', State::$categories[8]['is_show'] === 0 && count(State::$writes) === 1);
    State::$cacheClearFails = false;
    $repeat = saveVisibility();
    check('缓存失败后幂等重试补清缓存且不重写数据库', $repeat['changed'] === false && count(State::$writes) === 1 && State::$cleared === ['hsx_recycle_category_100005']);

    State::reset();
    $request = new Request(['is_show' => 0, 'expected_is_show' => 1, 'images' => 'upload/evil.png', 'need_vip' => 0]);
    $response = (new RecycleCategoryController($request))->updateQuoteVisibility(8);
    check('真实控制器仅读取显示目标和并发前值', $request->fields === ['is_show', 'expected_is_show'] && State::$categories[8]['images'] === 'upload/old.png' && State::$categories[8]['need_vip'] === 1);
    check('真实控制器返回框架成功封装及保存后状态', $response['message'] === 'EDIT_SUCCESS' && $response['data']['category_id'] === 8 && $response['data']['is_show'] === 0 && $response['data']['changed'] === true);
    State::reset();
    rejects('真实控制器不会将缺失前值默认成当前值', fn() => (new RecycleCategoryController(new Request(['is_show' => 0])))->updateQuoteVisibility(8), '显示状态参数无效');
    rejects('真实控制器不强制转换布尔状态', fn() => (new RecycleCategoryController(new Request(['is_show' => false, 'expected_is_show' => 1])))->updateQuoteVisibility(8), '显示状态参数无效');
    $routes = file_get_contents(dirname(__DIR__) . '/app/adminapi/route/route.php');
    check('显示状态窄接口注册为PUT且位于完整编辑接口之前', preg_match("#Route::put\('recycle_category/:id/quote-visibility', '[^']+@updateQuoteVisibility'\);#", $routes) === 1 && strpos($routes, "'recycle_category/:id/quote-visibility'") < strpos($routes, "'recycle_category/:id',"));
    echo '完成：' . $checks . ' 项移动报价隔离检查；未访问业务数据库、存储或网络。' . PHP_EOL;
}
