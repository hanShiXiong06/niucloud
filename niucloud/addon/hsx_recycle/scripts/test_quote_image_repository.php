<?php
declare(strict_types=1);

// php -n addon/hsx_recycle/scripts/test_quote_image_repository.php
// 执行真实 Repository，数据库、连接锁、缓存均使用内存替身；不加载框架、不访问业务数据。
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys { class CoreConfigService { public static $cache_tag_name = 'sys_config'; } }
namespace think\facade {
    class Db
    {
        public static array $tables = [];
        public static function name($table) { return new \QuoteImageRepoTestQuery($table); }
        public static function connect() { return new \QuoteImageRepoTestConnection(); }
        public static function transaction($operation)
        {
            $before = self::$tables;
            try { return $operation(); }
            catch (\Throwable $error) { self::$tables = $before; throw $error; }
        }
    }
    class Cache { public static function tag($tag) { return new class { public function clear() {} }; } }
    class Log { public static function warning(...$arguments) {} }
}
namespace {
    class QuoteImageRepoTestQuery
    {
        private string $table;
        private array $filters = [];
        private string $ordering = '';
        public function __construct($table) { $this->table = $table; }
        public function master($value) { return $this; }
        public function field($value) { return $this; }
        public function lock($value) { return $this; }
        public function where($key, $operator, $value = null)
        {
            if (func_num_args() === 2) { $value = $operator; $operator = '='; }
            $this->filters[] = [$key, $operator, $value];
            return $this;
        }
        public function whereIn($key, $values) { return $this->where($key, 'in', $values); }
        public function order($value) { $this->ordering = $value; return $this; }
        public function rows(): array
        {
            $rows = array_values(array_filter(\think\facade\Db::$tables[$this->table] ?? [], function ($row): bool {
                foreach ($this->filters as [$key, $operator, $value]) {
                    if ($operator === '=' && ($row[$key] ?? null) != $value) return false;
                    if ($operator === '>' && ($row[$key] ?? null) <= $value) return false;
                    if ($operator === 'in' && !in_array($row[$key] ?? null, $value)) return false;
                }
                return true;
            }));
            if ($this->ordering) {
                $descending = str_contains($this->ordering, 'desc');
                usort($rows, static fn($left, $right): int => $descending
                    ? (($right['id'] ?? 0) <=> ($left['id'] ?? 0))
                    : (($left['id'] ?? 0) <=> ($right['id'] ?? 0)));
            }
            return $rows;
        }
        public function value($key) { return $this->rows()[0][$key] ?? null; }
        public function find() { return $this->rows()[0] ?? null; }
        public function select()
        {
            return new class($this->rows()) {
                private array $rows;
                public function __construct($rows) { $this->rows = $rows; }
                public function toArray(): array { return $this->rows; }
            };
        }
        public function column($key): array { return array_column($this->rows(), $key); }
        public function insert($row): int
        {
            $row['id'] = $row['id'] ?? count(\think\facade\Db::$tables[$this->table] ?? []) + 1;
            \think\facade\Db::$tables[$this->table][] = $row;
            return 1;
        }
        public function update($data): int
        {
            $selected = $this->rows();
            foreach (\think\facade\Db::$tables[$this->table] as &$row) {
                foreach ($selected as $item) {
                    if (($row['id'] ?? $row['category_id'] ?? null) === ($item['id'] ?? $item['category_id'] ?? null)) {
                        $row = array_replace($row, $data);
                    }
                }
            }
            unset($row);
            return 1;
        }
    }
    class QuoteImageRepoTestConnection
    {
        public function query(...$arguments) {}
        public function getPdo() { return new QuoteImageRepoTestPdo(); }
    }
    class QuoteImageRepoTestPdo
    {
        public static bool $busy = false;
        public static int $released = 0;
        public function prepare($sql) { return new QuoteImageRepoTestStatement($sql); }
    }
    class QuoteImageRepoTestStatement
    {
        private string $sql;
        public function __construct($sql) { $this->sql = $sql; }
        public function execute($parameters)
        {
            if (str_contains($this->sql, 'RELEASE_LOCK')) QuoteImageRepoTestPdo::$released++;
        }
        public function fetchColumn() { return QuoteImageRepoTestPdo::$busy ? 0 : 1; }
    }

    require __DIR__ . '/../app/service/core/quote_image/QuoteImageSyncRepository.php';
    use addon\hsx_recycle\app\service\core\quote_image\QuoteImageSyncRepository;
    use think\facade\Db;

    $repository = new QuoteImageSyncRepository();
    $checks = 0;
    function quoteRepositoryCheck(string $name, bool $condition): void
    {
        global $checks;
        if (!$condition) throw new \RuntimeException($name);
        $checks++;
        echo 'PASS ' . $name . PHP_EOL;
    }
    function quoteRepositoryRejects(callable $operation): bool
    {
        try { $operation(); return false; }
        catch (\core\exception\CommonException $error) { return true; }
    }

    quoteRepositoryCheck('缺失配置返回空数组', $repository->readConfig(100005) === []);
    $repository->writeConfig(100005, ['enabled' => 1]);
    $repository->writeState(100005, ['status' => 'queued']);
    quoteRepositoryCheck('配置与运行状态独立保存', count(Db::$tables['sys_config']) === 2
        && $repository->readConfig(100005)['enabled'] === 1 && $repository->readState(100005)['status'] === 'queued');
    $repository->writeConfig(100005, ['enabled' => 0]);
    quoteRepositoryCheck('重复保存不插入重复配置', count(Db::$tables['sys_config']) === 2 && $repository->readConfig(100005)['enabled'] === 0);
    quoteRepositoryCheck('拒绝公共站点作为同步目标', quoteRepositoryRejects(fn() => $repository->categories(0)));
    quoteRepositoryCheck('超出TEXT安全上限时拒绝写入', quoteRepositoryRejects(fn() => $repository->writeConfig(100005, ['large' => str_repeat('a', 60001)]))
        && count(Db::$tables['sys_config']) === 2);

    Db::$tables['recycle_category'] = [
        ['category_id' => 8, 'site_id' => 100005, 'category_name' => '苹果', 'category_full_name' => '手机/苹果', 'images' => 'old.png'],
        ['category_id' => 9, 'site_id' => 100006, 'images' => 'other.png'],
        ['category_id' => 10, 'site_id' => 0, 'images' => 'public.png'],
    ];
    quoteRepositoryCheck('分类选项仅包含本站分类', count($repository->categories(100005)) === 1);
    quoteRepositoryCheck('跨站分类不能读取或锁定', quoteRepositoryRejects(fn() => $repository->category(100005, 9)));
    quoteRepositoryCheck('空图片不得清空原报价', quoteRepositoryRejects(fn() => $repository->transaction(
        fn() => $repository->replaceImages(100005, 8, 'old.png', '', '同步', 1, '操作人')))
        && $repository->category(100005, 8)['images'] === 'old.png');
    quoteRepositoryCheck('人工修改冲突不覆盖', quoteRepositoryRejects(fn() => $repository->transaction(
        fn() => $repository->replaceImages(100005, 8, 'outdated.png', 'new.png', '同步', 1, '操作人')))
        && $repository->category(100005, 8)['images'] === 'old.png');
    $repository->transaction(fn() => $repository->replaceImages(100005, 8, 'old.png', 'new.png', '同步报价', 1, '操作人'));
    quoteRepositoryCheck('成功更新同时保留旧图备份与新版本', count(Db::$tables['recycle_category_quote_history']) === 2
        && $repository->category(100005, 8)['images'] === 'new.png');
    $repository->transaction(fn() => $repository->replaceImages(100005, 8, 'new.png', 'next.png', '同步报价', 1, '操作人'));
    quoteRepositoryCheck('已知最新旧版本不重复备份', count(Db::$tables['recycle_category_quote_history']) === 3);
    try {
        $repository->transaction(function () use ($repository): void {
            $repository->replaceImages(100005, 8, 'next.png', 'rollback.png', '同步报价', 1, '操作人');
            throw new \RuntimeException('模拟检查点保存失败');
        });
    } catch (\RuntimeException $error) {}
    quoteRepositoryCheck('调用者事务失败时图片和历史一起回滚', $repository->category(100005, 8)['images'] === 'next.png'
        && count(Db::$tables['recycle_category_quote_history']) === 3);

    QuoteImageRepoTestPdo::$busy = true;
    quoteRepositoryCheck('重复运行消息不执行第二个worker', $repository->work(100005, 'run',
        fn() => throw new \RuntimeException('不应执行')) === null);
    quoteRepositoryCheck('状态锁繁忙返回明确错误', quoteRepositoryRejects(fn() => $repository->locked(100005, 'state', fn() => true)));
    QuoteImageRepoTestPdo::$busy = false;
    $before = QuoteImageRepoTestPdo::$released;
    try { $repository->locked(100005, 'state', fn() => throw new \RuntimeException('测试异常')); }
    catch (\RuntimeException $error) {}
    quoteRepositoryCheck('回调异常也释放数据库命名锁', QuoteImageRepoTestPdo::$released === $before + 1);

    foreach ([100005, 100006, 100007] as $site) $repository->writeConfig($site, ['enabled' => 1]);
    Db::$tables['site'] = [
        ['site_id' => 100005, 'status' => 1, 'addons' => '["hsx_recycle"]', 'expire_time' => 0],
        ['site_id' => 100006, 'status' => 1, 'app' => '["hsx_recycle"]', 'expire_time' => time() - 1],
        ['site_id' => 100007, 'status' => 1, 'addons' => '["other"]', 'expire_time' => 0],
    ];
    quoteRepositoryCheck('定时任务排除过期及未开通回收的站点', $repository->configuredSites() === [100005]);
    echo '完成：' . $checks . ' 项隔离Repository检查；未访问数据库或网络。' . PHP_EOL;
}
