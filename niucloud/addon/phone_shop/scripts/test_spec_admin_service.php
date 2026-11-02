<?php
declare(strict_types=1);

// Actual SpecService validation/update methods; memory models, no business DB.
namespace SpecAdminTest {
    class Store {
        public static array $groups = [];
        public static array $items = [];
        public static array $syncs = [];
    }
    class Row {
        private array $row;
        public function __construct(array $row) { $this->row = $row; }
        public function toArray(): array { return $this->row; }
        public function isEmpty(): bool { return $this->row === []; }
        public function __get($key) { return $this->row[$key] ?? null; }
    }
    class Query {
        private string $table; private array $conditions = [];
        public function __construct(string $table) { $this->table = $table; }
        public function where(array $conditions): self { $this->conditions = array_merge($this->conditions, $conditions); return $this; }
        private function matches(array $row): bool { foreach ($this->conditions as [$key, $op, $value]) if ((string)($row[$key] ?? '') !== (string)$value) return false; return true; }
        public function findOrEmpty(): Row { foreach (Store::${$this->table} as $row) if ($this->matches($row)) return new Row($row); return new Row([]); }
        public function update(array $data): int { $count = 0; foreach (Store::${$this->table} as &$row) if ($this->matches($row)) { $row = array_replace($row, $data); $count++; } return $count; }
    }
    class Model {
        protected string $table = '';
        public function where(array $conditions): Query { return (new Query($this->table))->where($conditions); }
        public function getTableFields(): array { return ['category_ids']; }
        public function create(array $row): Row { $key = $this->table === 'groups' ? 'group_id' : 'item_id'; $row[$key] = count(Store::${$this->table}) + 10; Store::${$this->table}[] = $row; return new Row($row); }
    }
}
namespace core\base { class BaseAdminService { protected int $site_id = 100005; } }
namespace core\exception { class AdminException extends \RuntimeException {} }
namespace addon\phone_shop\app\model\goods {
    class GoodsSpecGroup extends \SpecAdminTest\Model { protected string $table = 'groups'; }
    class GoodsSpecItem extends \SpecAdminTest\Model { protected string $table = 'items'; }
}
namespace addon\phone_shop\app\service\core\agent {
    class RefDataSyncService { public static function pushIfMaster(...$args): void { \SpecAdminTest\Store::$syncs[] = $args; } }
}
namespace {
    use SpecAdminTest\Store;
    use addon\phone_shop\app\service\admin\goods\SpecService;
    require __DIR__ . '/../app/service/admin/goods/SpecService.php';
    set_error_handler(static function ($level, $message, $file, $line): void { throw new ErrorException($message, 0, $level, $file, $line); });
    $checks = 0;
    function check(bool $passed, string $reason): void { global $checks; ++$checks; if (!$passed) throw new RuntimeException($reason); }
    function fixture(): SpecService {
        Store::$groups = [['group_id'=>1,'site_id'=>100005,'category_id'=>1,'category_ids'=>'1,2','label'=>'安卓内存规格','sort'=>5], ['group_id'=>2,'site_id'=>100006,'category_id'=>3,'label'=>'其他站点','sort'=>8]];
        Store::$items = [['item_id'=>1,'site_id'=>100005,'group_id'=>1,'item_value'=>'8+128','sort'=>5], ['item_id'=>2,'site_id'=>100006,'group_id'=>2,'item_value'=>'44MM','sort'=>8]];
        Store::$syncs = [];
        return SpecService::forSite(100005);
    }
    function reject(callable $fn, string $reason): void {
        $before = [Store::$groups, Store::$items, Store::$syncs]; $failed = false;
        try { $fn(); } catch (\core\exception\AdminException $e) { $failed = true; }
        check($failed, $reason . ': reject'); check($before === [Store::$groups, Store::$items, Store::$syncs], $reason . ': no writes or sync');
    }
    $service = fixture();
    $service->groupEdit(1, ['category_ids'=>[1,2],'label'=>'笔记本规格','sort'=>0]);
    check(Store::$groups[0]['label'] === '笔记本规格' && Store::$groups[0]['sort'] === 0, 'group name/sort persist to existing fields');
    check(!array_key_exists('group_name', Store::$groups[0]), 'no extra group_name column');
    check(Store::$groups[1]['label'] === '其他站点', 'other-site group unchanged');
    $service->itemEdit(1, ['item_value'=>'12+256','sort'=>'0']);
    check(Store::$items[0]['item_value'] === '12+256' && Store::$items[0]['sort'] === 0, 'item value and zero sort preserved');
    check(Store::$items[1]['sort'] === 8, 'other-site item unchanged');
    foreach (['', '   ', str_repeat('名',51), [], null] as $label) reject(fn()=> $service->groupEdit(1,['category_id'=>1,'label'=>$label,'sort'=>0]), 'invalid group name');
    foreach (['', '  ', str_repeat('值',51), [], null] as $value) reject(fn()=> $service->itemEdit(1,['item_value'=>$value,'sort'=>0]), 'invalid item value');
    foreach ([-1, 1000000, 1.5, '1.5', '1e2', ' 1', '', null, true, []] as $sort) {
        reject(fn()=> $service->itemEdit(1,['item_value'=>'128G','sort'=>$sort]), 'invalid item sort');
        reject(fn()=> $service->groupEdit(1,['category_id'=>1,'label'=>'苹果内存规格','sort'=>$sort]), 'invalid group sort');
    }
    reject(fn()=> $service->itemEdit(2,['item_value'=>'128G','sort'=>0]), 'cross-site item');
    reject(fn()=> $service->itemEdit(999,['item_value'=>'128G','sort'=>0]), 'missing item');
    reject(fn()=> $service->groupEdit(2,['category_id'=>1,'label'=>'苹果内存规格','sort'=>0]), 'cross-site group');
    reject(fn()=> $service->itemAdd(['group_id'=>2,'item_value'=>'128G','sort'=>0]), 'cross-site item parent');
    $id = $service->itemAdd(['group_id'=>1,'item_value'=>' 16+512 ','sort'=>999999]);
    check($id > 0 && end(Store::$items)['item_value'] === '16+512' && end(Store::$items)['sort'] === 999999, 'valid trimmed item and max sort');
    $method = new ReflectionMethod(SpecService::class,'groupLabel'); $method->setAccessible(true);
    check($method->invoke($service,str_repeat('名',50)) === str_repeat('名',50), '50-character group name accepted');
    echo "PASS {$checks} spec-admin service checks; memory ORM only, no business database.\n";
}
