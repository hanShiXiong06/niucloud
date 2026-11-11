<?php
declare(strict_types=1);

// php -n addon/hsx_recycle/scripts/test_mobile_quote_image.php
// 执行真实报价分类服务及历史快照服务；权限、数据库、缓存仅使用内存替身。
// 不读取 .env、不访问业务数据库、不上传附件、不发送外部请求。
namespace { if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } }
namespace MobileQuoteFixture {
    final class State {
        public static array $categories = [], $history = [], $cleared = [], $rules = [], $role = [], $checks = [];
        public static int $siteId = 100005, $siteStatus = 1, $rowLocks = 0;
        public static bool $super = false, $frameworkAllowed = true, $historyFails = false;
        public static function reset(): void {
            self::$categories = [
                8 => ['category_id' => 8, 'site_id' => 100005, 'images' => 'upload/old.png', 'update_time' => 123,
                    'category_name' => '苹果', 'sort' => 99, 'need_vip' => 1, 'is_show' => 1, 'image' => 'cover.png', 'pid' => 2],
                9 => ['category_id' => 9, 'site_id' => 100006, 'images' => 'other.png'],
                10 => ['category_id' => 10, 'site_id' => 0, 'images' => 'public.png'],
            ];
            self::$history = self::$cleared = self::$checks = [];
            self::$rules = ['get' => ['recycle/recycle_category'], 'put' => ['recycle/recycle_category/<id>']];
            self::$role = ['is_admin' => 0];
            self::$siteId = 100005; self::$siteStatus = 1; self::$rowLocks = 0;
            self::$super = false; self::$frameworkAllowed = true; self::$historyFails = false;
        }
    }
    final class Row {
        private array $data;
        public function __construct(array $data) { $this->data = $data; }
        public function toArray(): array { return $this->data; }
    }
    final class Query {
        private string $table;
        private array $filters = [];
        public function __construct(string $table) { $this->table = $table; }
        public function master($flag) { return $this; }
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
            $count = 0;
            foreach (State::$categories as &$row) if ($this->matches($row)) { $row = array_replace($row, $data); $count++; }
            unset($row);
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
            $categories = \MobileQuoteFixture\State::$categories; $history = \MobileQuoteFixture\State::$history;
            try { return $operation(); }
            catch (\Throwable $error) { \MobileQuoteFixture\State::$categories = $categories; \MobileQuoteFixture\State::$history = $history; throw $error; }
        }
    }
    final class Cache {
        public static function get($key) { return null; }
        public static function tag(string $tag) { return new class($tag) {
            private string $tag;
            public function __construct($tag) { $this->tag = $tag; }
            public function clear(): void { \MobileQuoteFixture\State::$cleared[] = $this->tag; }
            public function set(...$arguments): void {}
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
    use addon\hsx_recycle\app\service\admin\category\RecycleCategoryService;
    use MobileQuoteFixture\State;
    $checks = 0;
    function check(string $name, bool $value): void { global $checks; if (!$value) throw new \RuntimeException($name); $checks++; echo 'PASS ' . $name . PHP_EOL; }
    function rejects(string $name, callable $operation, string $contains = ''): void {
        try { $operation(); } catch (\Throwable $error) { check($name, $contains === '' || strpos($error->getMessage(), $contains) !== false); return; }
        throw new \RuntimeException($name . ' 未拒绝');
    }
    function save(array $overrides = [], int $id = 8): array { return (new RecycleCategoryService())->updateQuoteImage($id, array_replace(['images' => 'upload/new.png', 'expected_images' => 'upload/old.png'], $overrides)); }

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
    echo '完成：' . $checks . ' 项移动报价隔离检查；未访问业务数据库、存储或网络。' . PHP_EOL;
}
