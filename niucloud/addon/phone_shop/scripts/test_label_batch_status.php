<?php
declare(strict_types=1);

// Execute the actual label service/controller with an in-memory ORM. No .env or business DB.
namespace LabelBatchTest {
    class Store {
        public static array $rows = [];
        public static int $site = 100005;
        public static int $page = 1;
        public static int $limit = 10;
        public static int $transactions = 0;
        public static int $locks = 0;
        public static int $writes = 0;
        public static bool $failWrite = false;
    }
    class Collection {
        private array $rows;
        public function __construct(array $rows) { $this->rows = $rows; }
        public function toArray(): array { return $this->rows; }
    }
    class Query {
        private array $conditions = [];
        private string $sort = '';
        public function where($key, $operator = null, $value = null): self {
            if (is_array($key)) { foreach ($key as $clause) $this->where(...$clause); return $this; }
            if (func_num_args() === 2) { $value = $operator; $operator = '='; }
            $this->conditions[] = [$key, $operator, $value]; return $this;
        }
        public function whereIn(string $key, array $values): self { return $this->where($key, 'in', $values); }
        public function withSearch(array $fields, array $where): self {
            if (in_array('label_name', $fields, true) && ($where['label_name'] ?? '') !== '') $this->where('label_name', 'like', $where['label_name']);
            if (in_array('group_id', $fields, true) && !empty($where['group_id'])) $this->where('group_id', $where['group_id']);
            return $this;
        }
        public function field($fields): self { return $this; }
        public function with($relations): self { return $this; }
        public function order(string $sort): self { $this->sort = $sort; return $this; }
        public function lock(bool $locked): self { if ($locked) Store::$locks++; return $this; }
        private function matches(array $row): bool {
            foreach ($this->conditions as [$key, $operator, $value]) {
                if ($operator === 'in') { if (!in_array((string)$row[$key], array_map('strval', $value), true)) return false; }
                elseif ($operator === 'like') { if (strpos((string)$row[$key], (string)$value) === false) return false; }
                elseif ((string)($row[$key] ?? '') !== (string)$value) return false;
            }
            return true;
        }
        public function select(): Collection {
            $rows = array_values(array_filter(Store::$rows, fn($row) => $this->matches($row)));
            $orders = explode(',', $this->sort);
            usort($rows, static function ($left, $right) use ($orders) {
                foreach ($orders as $order) {
                    $parts = preg_split('/\s+/', trim($order)); $key = $parts[0];
                    $direction = strtolower($parts[1] ?? 'asc') === 'desc' ? -1 : 1;
                    $compare = ($left[$key] ?? 0) <=> ($right[$key] ?? 0);
                    if ($compare !== 0) return $compare * $direction;
                }
                return 0;
            });
            return new Collection($rows);
        }
        public function update(array $values): int {
            Store::$writes++; $count = 0;
            foreach (Store::$rows as &$row) if ($this->matches($row)) {
                $row = array_replace($row, $values); $count++;
                if (Store::$failWrite) throw new \RuntimeException('simulated DB failure');
            }
            return $count;
        }
    }
    class Request {
        private array $input;
        public function __construct(array $input = []) { $this->input = $input; }
        public function params(array $definitions): array {
            $data = [];
            foreach ($definitions as [$key, $default]) $data[$key] = $this->input[$key] ?? $default;
            return $data;
        }
    }
}
namespace core\base {
    class BaseAdminService {
        protected int $site_id;
        protected $model;
        public function __construct() { $this->site_id = \LabelBatchTest\Store::$site; }
        protected function pageQuery($query): array {
            $rows = $query->select()->toArray();
            return ['data' => array_slice($rows, (\LabelBatchTest\Store::$page - 1) * \LabelBatchTest\Store::$limit, \LabelBatchTest\Store::$limit), 'total' => count($rows)];
        }
    }
    class BaseAdminController {
        protected $request;
        public function __construct(array $input = []) { $this->request = new \LabelBatchTest\Request($input); }
    }
}
namespace core\exception { class AdminException extends \RuntimeException {} }
namespace addon\phone_shop\app\model\goods {
    class Label {
        public function where(...$args): \LabelBatchTest\Query { return (new \LabelBatchTest\Query())->where(...$args); }
    }
}
namespace think\facade {
    class Db {
        public static function transaction(callable $work) {
            $rows = \LabelBatchTest\Store::$rows; \LabelBatchTest\Store::$transactions++;
            try { return $work(); } catch (\Throwable $e) { \LabelBatchTest\Store::$rows = $rows; throw $e; }
        }
    }
}
namespace {
    use LabelBatchTest\Store;
    use addon\phone_shop\app\service\admin\goods\LabelService;
    use addon\phone_shop\app\adminapi\controller\goods\Label as LabelController;
    require __DIR__ . '/../app/dict/goods/LabelDict.php';
    require __DIR__ . '/../app/service/admin/goods/LabelService.php';
    require __DIR__ . '/../app/adminapi/controller/goods/Label.php';
    function success($message, $data = []): array { return is_array($message) ? ['data' => $message] : ['msg' => $message, 'data' => $data]; }
    set_error_handler(static function ($level, $message, $file, $line): void { throw new \ErrorException($message, 0, $level, $file, $line); });
    $checks = 0;
    function check(bool $passed, string $message): void { global $checks; $checks++; if (!$passed) throw new \RuntimeException($message); }
    function fixture(): LabelService {
        Store::$site = 100005; Store::$page = 1; Store::$limit = 10;
        Store::$transactions = Store::$locks = Store::$writes = 0; Store::$failWrite = false;
        Store::$rows = [
            ['site_id' => 100005, 'label_id' => 1, 'label_name' => '屏幕极佳', 'group_id' => 1, 'status' => 1, 'sort' => 10, 'update_time' => 1],
            ['site_id' => 100005, 'label_id' => 2, 'label_name' => '电池正常', 'group_id' => 2, 'status' => 0, 'sort' => 20, 'update_time' => 2],
            ['site_id' => 100005, 'label_id' => 3, 'label_name' => '屏幕完美', 'group_id' => 1, 'status' => 0, 'sort' => 30, 'update_time' => 3],
            ['site_id' => 100006, 'label_id' => 4, 'label_name' => '屏幕完美', 'group_id' => 1, 'status' => 1, 'sort' => 40, 'update_time' => 4],
        ];
        return new LabelService();
    }
    function rejects(callable $work, string $label): void {
        $before = Store::$rows; $failed = false;
        try { $work(); } catch (\core\exception\AdminException $e) { $failed = true; }
        check($failed, $label . ': expected validation error');
        check(Store::$rows === $before, $label . ': no partial mutation');
    }
    $service = fixture();
    foreach ([[], ['status' => ''], ['status' => null]] as $filter) check($service->getPage($filter)['total'] === 3, 'empty status means all current-site labels');
    foreach ([0, '0'] as $status) {
        $result = $service->getPage(['status' => $status]);
        check($result['total'] === 2 && array_column($result['data'], 'label_id') === [3, 2], 'disabled filter is not swallowed');
    }
    foreach ([1, '1'] as $status) check(array_column($service->getPage(['status' => $status])['data'], 'label_id') === [1], 'enabled filter');
    check(array_column($service->getPage(['status' => 0, 'group_id' => 1, 'label_name' => '屏幕'])['data'], 'label_id') === [3], 'status with group/name');
    check(array_column($service->getPage(['status' => 0, 'order' => 'sort', 'sort' => 'asc'])['data'], 'label_id') === [2, 3], 'custom sorting retained');
    Store::$limit = 1; Store::$page = 2;
    $result = $service->getPage(['status' => 0]);
    check($result['total'] === 2 && array_column($result['data'], 'label_id') === [2], 'filter before pagination');
    foreach ([2, -1, true, false, [], 'all', '0 ', 0.0] as $status) rejects(fn() => $service->getPage(['status' => $status]), 'invalid list status ' . json_encode($status));
    $service = fixture();
    check(array_column($service->getList(), 'label_id') === [1], 'consumer getList remains enabled only');
    $result = $service->modifyStatus(['label_ids' => [1, '2', 3], 'status' => 1]);
    check($result === ['requested_count' => 3, 'changed_count' => 2, 'status' => 1], 'batch enable result');
    check(array_column(Store::$rows, 'status') === [1, 1, 1, 1], 'batch status changed only selected site');
    check(Store::$rows[0]['update_time'] === 1 && Store::$rows[1]['update_time'] > 2, 'no-op timestamp untouched');
    check(Store::$transactions === 1 && Store::$locks === 1 && Store::$writes === 1, 'one locked transaction and one write');
    $snapshot = Store::$rows;
    check($service->modifyStatus(['label_ids' => [1, 2, 3], 'status' => '1'])['changed_count'] === 0 && Store::$rows === $snapshot, 'retry idempotent');
    $result = $service->modifyStatus(['label_ids' => [1, 2], 'status' => 0]);
    check($result['changed_count'] === 2 && Store::$rows[2]['status'] === 1 && Store::$rows[3]['status'] === 1, 'batch disable isolated');
    check($service->modifyStatus(['label_id' => '3', 'status' => 0])['changed_count'] === 1, 'old single-id request retained');
    check($service->modifyStatus(['label_id' => 3, 'label_ids' => null, 'status' => 1])['changed_count'] === 1, 'controller null optional IDs retain single behavior');
    foreach ([[], null, '1,2', [0], [-1], [1.0], [true], ['1a'], [' 1'], ['01'], ['1e2'], [1, '1'], [1, 99], [1, 4], ['999999999999999999999999999999999']] as $ids) {
        if ($ids === null) $data = ['label_id' => null, 'label_ids' => null, 'status' => 1];
        else $data = ['label_ids' => $ids, 'status' => 1];
        rejects(fn() => $service->modifyStatus($data), 'invalid/cross-site/missing IDs ' . json_encode($ids));
    }
    foreach ([null, '', 'on', 2, -1, true, false, 0.0, [], '1.0'] as $status) rejects(fn() => $service->modifyStatus(['label_ids' => [1, 2], 'status' => $status]), 'invalid update status ' . json_encode($status));
    rejects(fn() => $service->modifyStatus(['label_ids' => range(1, 101), 'status' => 1]), 'over 100 IDs');
    $service = fixture(); Store::$failWrite = true; $before = Store::$rows; $failed = false;
    try { $service->modifyStatus(['label_ids' => [2, 3], 'status' => 1]); } catch (\RuntimeException $e) { $failed = true; }
    check($failed && Store::$rows === $before, 'database write failure rolls back entire batch');
    fixture();
    $response = (new LabelController(['label_ids' => [1, 2], 'status' => 0]))->modifyStatus();
    check($response['msg'] === 'SUCCESS' && $response['data']['requested_count'] === 2 && $response['data']['changed_count'] === 1, 'controller forwards batch and result');
    $response = (new LabelController(['label_id' => 3, 'status' => 1]))->modifyStatus();
    check($response['data']['changed_count'] === 1, 'controller preserves single operation');
    $response = (new LabelController(['status' => 0]))->pages();
    check(array_column($response['data']['data'], 'label_id') === [2, 1], 'controller forwards disabled status');
    echo "PASS {$checks} label filter/batch checks; in-memory ORM only, no business DB or production writes.\n";
}
