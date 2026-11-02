<?php
declare(strict_types=1);

// Real dictionary service / API option method with in-memory ORM. No .env, business DB or writes.
namespace MemoryFilterTest {
    class Store { public static array $tables = []; public static array $configs = []; public static int $site = 100005; public static array $queries = []; }
    class Collection { private array $rows; public function __construct(array $rows) { $this->rows = $rows; } public function toArray(): array { return $this->rows; } }
    class Query {
        private string $table; private array $tests = []; private string $sort = ''; private string $group = '';
        public function __construct(string $table) { $this->table = $table; Store::$queries[] = $table; }
        public function where($key, $op = null, $value = null): self { return $this->condition('and', $key, $op, $value, func_num_args()); }
        public function whereOr($key, $op = null, $value = null): self { return $this->condition('or', $key, $op, $value, func_num_args()); }
        private function condition(string $join, $key, $op, $value, int $count): self {
            if (is_callable($key)) { $nested = new self($this->table); $key($nested); $test = static fn($r) => $nested->matches($r); }
            elseif (is_array($key)) { $nested = new self($this->table); foreach ($key as $part) $nested->where(...$part); $test = static fn($r) => $nested->matches($r); }
            else { if ($count === 2) { $value = $op; $op = '='; }
                $test = static function ($row) use ($key, $op, $value): bool {
                    $actual = $row[$key] ?? null;
                    if ($op === 'in') return in_array((string)$actual, array_map('strval', (array)$value), true);
                    if ($op === '<>') return (string)$actual !== (string)$value;
                    if ($op === 'like') return str_contains((string)$actual, trim((string)$value, '%'));
                    return (string)$actual === (string)$value;
                };
            }
            $this->tests[] = [$join, $test]; return $this;
        }
        public function whereIn(string $key, array $values): self { return $this->where($key, 'in', $values); }
        public function matches(array $row): bool { $result = null; foreach ($this->tests as [$join, $test]) $result = $result === null ? $test($row) : ($join === 'or' ? $result || $test($row) : $result && $test($row)); return $result ?? true; }
        public function field($field): self { return $this; }
        public function order(string $sort): self { $this->sort = $sort; return $this; }
        public function group(string $group): self { $this->group = $group; return $this; }
        public function select(): Collection {
            $rows = array_values(array_filter(Store::$tables[$this->table] ?? [], fn($row) => $this->matches($row)));
            $sorts = array_filter(explode(',', $this->sort));
            usort($rows, static function ($a, $b) use ($sorts) {
                foreach ($sorts as $sort) { $part = preg_split('/\s+/', trim($sort)); $left = $a[$part[0]] ?? null; $right = $b[$part[0]] ?? null; $difference = $left <=> $right; if ($difference) return strtolower($part[1] ?? 'asc') === 'desc' ? -$difference : $difference; }
                return 0;
            });
            if ($this->group !== '') { $unique = []; foreach ($rows as $row) $unique[(string)($row[$this->group] ?? '')] = $row; $rows = array_values($unique); }
            return new Collection($rows);
        }
        public function column(string $field): array { return array_column($this->select()->toArray(), $field); }
    }
    abstract class Model {
        protected static string $table = '';
        public static function where(...$args): Query { return (new Query(static::$table))->where(...$args); }
    }
}
namespace core\base { class BaseCoreService {} class BaseApiService { protected int $site_id; public function __construct() { $this->site_id = \MemoryFilterTest\Store::$site; } } }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys { class CoreConfigService { public function getConfigValue($site, $key) { return \MemoryFilterTest\Store::$configs[$site] ?? []; } } }
namespace addon\phone_shop\app\model\goods {
    class Goods extends \MemoryFilterTest\Model { protected static string $table = 'goods'; }
    class Category extends \MemoryFilterTest\Model { protected static string $table = 'categories'; }
    class GoodsSpecGroup extends \MemoryFilterTest\Model { protected static string $table = 'groups'; }
    class GoodsSpecItem extends \MemoryFilterTest\Model { protected static string $table = 'items'; }
    class GoodsGrade extends \MemoryFilterTest\Model { protected static string $table = 'grades'; }
    class Label extends \MemoryFilterTest\Model { protected static string $table = 'labels'; }
    class LabelGroup extends \MemoryFilterTest\Model { protected static string $table = 'label_groups'; }
    class Brand extends \MemoryFilterTest\Model { protected static string $table = 'brands'; }
    class Service extends \MemoryFilterTest\Model { protected static string $table = 'services'; }
}
namespace addon\phone_shop\app\service\api\goods { class GoodsCategoryService { public function getTree() { return []; } } }
namespace {
    use MemoryFilterTest\Store;
    use addon\phone_shop\app\service\core\goods\CoreMemoryFilterService;
    use addon\phone_shop\app\service\core\intake\CoreListingMappingService;
    require __DIR__ . '/../app/support/ListingGoodsTitle.php';
    require __DIR__ . '/../app/service/core/intake/CoreListingMappingService.php';
    require __DIR__ . '/../app/service/core/goods/CoreDeviceAttributeService.php';
    require __DIR__ . '/../app/service/core/goods/CoreMemoryFilterService.php';
    require __DIR__ . '/../app/service/core/goods/CoreGoodsSubscriptionMatchService.php';
    require __DIR__ . '/../app/service/api/goods/GoodsService.php';
    class Api extends \addon\phone_shop\app\service\api\goods\GoodsService { public function getWarehouseOptions(): array { return []; } }
    set_error_handler(static function ($level, $message, $file, $line): void { throw new \ErrorException($message, 0, $level, $file, $line); });
    $checks = 0;
    function check(bool $ok, string $message): void { global $checks; ++$checks; if (!$ok) throw new \RuntimeException($message); }
    function fixture(): void {
        Store::$queries = []; Store::$site = 100005; Store::$configs = [100005 => ['memory_aliases' => ['特制' => '12GB+1TB']]];
        Store::$tables = [
            'groups' => [
                ['site_id'=>100005,'group_id'=>1,'label'=>'苹果内存规格','category_id'=>10,'category_ids'=>'10','sort'=>20],
                ['site_id'=>100005,'group_id'=>2,'label'=>'安卓内存规格','category_id'=>20,'category_ids'=>'20,21','sort'=>10],
                ['site_id'=>100005,'group_id'=>3,'label'=>'手表规格','category_id'=>30,'sort'=>30],
                ['site_id'=>100005,'group_id'=>4,'label'=>'笔记本规格','category_id'=>40,'category_ids'=>'40','sort'=>40],
                ['site_id'=>100006,'group_id'=>99,'label'=>'其他站点','category_id'=>10,'sort'=>0],
            ],
            'items' => [
                ['site_id'=>100005,'group_id'=>1,'item_id'=>11,'item_value'=>'256G','sort'=>20],
                ['site_id'=>100005,'group_id'=>1,'item_id'=>12,'item_value'=>' 128G ','sort'=>10],
                ['site_id'=>100005,'group_id'=>1,'item_id'=>13,'item_value'=>'128G','sort'=>30],
                ['site_id'=>100005,'group_id'=>1,'item_id'=>14,'item_value'=>'','sort'=>0],
                ['site_id'=>100006,'group_id'=>1,'item_id'=>15,'item_value'=>'跨站脏选项','sort'=>0],
                ['site_id'=>100005,'group_id'=>2,'item_id'=>21,'item_value'=>'8+128','sort'=>0],
                ['site_id'=>100005,'group_id'=>2,'item_id'=>22,'item_value'=>'特制','sort'=>10],
                ['site_id'=>100005,'group_id'=>3,'item_id'=>31,'item_value'=>'46MM','sort'=>0],
                ['site_id'=>100005,'group_id'=>4,'item_id'=>41,'item_value'=>'16+512','sort'=>0],
                ['site_id'=>100006,'group_id'=>99,'item_id'=>91,'item_value'=>'其他站点128G','sort'=>0],
            ],
            'categories' => [
                ['site_id'=>100005,'category_id'=>10,'pid'=>0],['site_id'=>100005,'category_id'=>11,'pid'=>10],['site_id'=>100005,'category_id'=>12,'pid'=>10],
                ['site_id'=>100005,'category_id'=>20,'pid'=>0],['site_id'=>100005,'category_id'=>21,'pid'=>20],['site_id'=>100005,'category_id'=>22,'pid'=>21],
                ['site_id'=>100005,'category_id'=>30,'pid'=>0],['site_id'=>100005,'category_id'=>40,'pid'=>0],['site_id'=>100006,'category_id'=>90,'pid'=>0],
            ],
            'goods' => [
                ['site_id'=>100005,'status'=>1,'sale_status'=>'available','delete_time'=>0,'memory_group'=>'128G','device_color'=>'黑色','brand_id'=>1,'goods_category'=>'["10","11"]','condition_grade'=>'极佳'],
                ['site_id'=>100005,'status'=>1,'sale_status'=>'available','delete_time'=>0,'memory_group'=>'128GB','device_color'=>'白色','brand_id'=>1,'goods_category'=>'["10","11"]','condition_grade'=>'极佳'],
                ['site_id'=>100005,'status'=>1,'sale_status'=>'available','delete_time'=>0,'memory_group'=>'脏值乱码ABC','device_color'=>'粉色','brand_id'=>1,'goods_category'=>'["10","11"]','condition_grade'=>'极佳'],
            ],
            'grades'=>[], 'labels'=>[], 'label_groups'=>[], 'brands'=>[], 'services'=>[],
        ];
    }
    fixture(); $service = new CoreMemoryFilterService(); $groups = $service->groups(100005);
    check(array_column($groups,'group_id') === [2,1,3,4], '所有分组按后台sort排序，不写死三组');
    check(array_column($groups,'group_name') === ['安卓内存规格','苹果内存规格','手表规格','笔记本规格'], '分组标题取管理员label');
    check(array_column($groups[1]['items'],'value') === ['128G','256G'], '子项sort排序、trim、空值和组内重复排除');
    check(array_column($groups[1]['items'],'label') === ['128G','256G'], '显示保留字典原规格文本');
    check(count($groups)===4 && !in_array('跨站脏选项', array_column($service->flatten($groups),'value'),true), '分组与子项双重站点隔离');
    check(!in_array('脏值乱码ABC',array_column($service->flatten($groups),'value'),true), '不从在售库存反推脏候选');
    check(!in_array('goods',Store::$queries,true), '字典读取不访问商品库存表');
    check(array_column($service->groups(100005,[11]),'group_id')===[1], '叶分类继承祖先绑定');
    check(array_column($service->groups(100005,[22]),'group_id')===[2], '三级分类及多分类绑定');
    check(array_column($service->groups(100005,[11,22]),'group_id')===[2,1], '多个分类取组的有序并集');
    check($service->groups(100005,[90])===[] && $service->groups(100005,[99999])===[], '他站或不存在分类不回退全部');
    check(array_column($service->groups(100005,[30]),'group_id')===[3], '旧单category_id格式支持手表规格');
    check(array_column($service->groups(100005,[40]),'group_id')===[4], '未来扩展分组无需代码分类判断');
    check($service->groups(0)===[], '无站点不泄漏公共或他站数据');
    $values = $service->filterValues(100005,['128G']); check($values===['128G','128GB'], '苹果字典值扩展原值与现有建品规范值');
    check($service->filterValues(100005,['128GB'])===['128G','128GB'], '直接规范值同样可查字典原值');
    check($service->filterValues(100005,['8+128'])===['8+128','8GB+128GB'], '安卓组合复用建品规范化');
    check($service->filterValues(100005,['特制'])===['特制','12GB+1TB'], '本站配置memory_aliases复用');
    check($service->filterValues(100005,['46MM'])===['46MM'], '手表尺寸不被变成GB');
    check($service->filterValues(100005,['脏值乱码ABC'])===['脏值乱码ABC'], '未知直传值只精确查询，不反推展示值');
    check($service->filterValues(100005,[])===[], '空筛选不读取别名或追加条件');
    $many=$service->filterValues(100005,['128G','128G','128GB','8+128']); check($many===['128G','128GB','8+128','8GB+128GB'], '多选规范值去重');
    $plain=new CoreListingMappingService(); foreach(['128G','8+128','46MM','特制'] as $raw) check(in_array($plain->normalizeMemory($raw,Store::$configs[100005]['memory_aliases']),$service->filterValues(100005,[$raw]),true), '与真实建品规范化一致:'.$raw);
    fixture(); Store::$tables['groups'][]=['site_id'=>100005,'group_id'=>5,'label'=>'平板独立规格','category_id'=>12,'sort'=>20]; Store::$tables['items'][]=['site_id'=>100005,'group_id'=>5,'item_id'=>51,'item_value'=>'1TB','sort'=>0];
    $service=new CoreMemoryFilterService(); check(array_column($service->groups(100005,[10]),'group_id')===[5,1], '选父分类包含其后代绑定组，排序相同按既有group_id desc');
    check(array_column($service->groups(100005,[11]),'group_id')===[1], '同品牌下不匹配无关兄弟叶分类');
    fixture(); $api=new Api(); $response=$api->getFilterOptions(); check(array_column($response['memory_groups'],'group_id')===[2,1,3,4], '真实API方法返回分组');
    check(array_column($response['memories'],'value')===['8+128','特制','128G','256G','46MM','16+512'], '旧扁平字段仅来源于同一规格字典');
    check(!in_array('脏值乱码ABC',array_column($response['memories'],'value'),true), '真实API不泄漏在售脏规格');
    $response=$api->getFilterOptions(['goods_category'=>'11']); check(array_column($response['memory_groups'],'group_id')===[1], '真实API分类过滤分组');
    $response=$api->getFilterOptions(['goods_category'=>'11','memory_group'=>'128G']); check(array_column($response['colors'],'value')===['白色','黑色'], '颜色联动使用同一受控规格扩展，128G和128GB均能命中');
    $response=$api->getFilterOptions(['goods_category'=>'11','brand_id'=>'999']); check($response['colors']===[] && count($response['memory_groups'])===1, '保留颜色品牌联动，规格仍以配置字典为准');
    fixture(); Store::$tables['groups']=[]; $response=(new Api())->getFilterOptions(); check($response['memory_groups']===[] && $response['memories']===[], '规格库为空不回退库存脏值');
    fixture(); Store::$tables['items']=[]; check((new Api())->getFilterOptions()['memory_groups']===[], '空规格分组不输出虚假候选');
    fixture(); Store::$tables['groups'][0]['label']=' '; check(!in_array(1,array_column((new CoreMemoryFilterService())->groups(100005),'group_id'),true), '无分组名称不输出无名组');
    fixture(); Store::$tables['items'][]=['site_id'=>100005,'group_id'=>1,'item_id'=>16,'item_value'=>'64G','sort'=>10];
    check(array_column((new CoreMemoryFilterService())->groups(100005,[11])[0]['items'],'value')===['128G','64G','256G'], '同排序子项保持item_id asc稳定');
    fixture(); Store::$tables['items'][]=['site_id'=>100005,'group_id'=>2,'item_id'=>23,'item_value'=>'128','sort'=>20];
    check((new CoreMemoryFilterService())->filterValues(100005,['128'])===['128','128GB'], '纯数字字典查询值仍保持字符串，避免SQL数值模糊比较');
    fixture(); $service=new CoreMemoryFilterService(); $service->groups(100005);
    check(array_column($service->groups(100006),'group_id')===[99], '实例缓存按站点分开，不串站规格');
    Store::$tables['categories'][]=['site_id'=>100005,'category_id'=>50,'pid'=>51]; Store::$tables['categories'][]=['site_id'=>100005,'category_id'=>51,'pid'=>50];
    Store::$tables['groups'][]=['site_id'=>100005,'group_id'=>7,'label'=>'循环分类规格','category_id'=>50,'sort'=>50]; Store::$tables['items'][]=['site_id'=>100005,'group_id'=>7,'item_id'=>71,'item_value'=>'16GB','sort'=>0];
    check(array_column((new CoreMemoryFilterService())->groups(100005,[51]),'group_id')===[7], '畸形分类环不无限循环');
    fixture(); $matcher=new \addon\phone_shop\app\service\core\goods\CoreGoodsSubscriptionMatchService();
    $goods=['memory_group'=>'8GB+128GB','goods_name'=>'测试手机','device_color'=>'黑色','condition_grade'=>'极佳']; $sku=['price'=>'1000.00','stock'=>1];
    check($matcher->matches(['memory_group'=>['8+128']],$goods,$sku,100005), '真实订阅matches复用字典规范化，raw组合能匹配规范商品');
    $queryCount=count(Store::$queries);
    for($i=0;$i<20;$i++) check($matcher->matches(['memory_group'=>['8+128']],$goods,$sku,100005), '同批订阅复用字典缓存'.$i);
    check(count(Store::$queries)===$queryCount, '订阅循环不重复查询规格字典');
    check(!$matcher->matches(['memory_group'=>['8+128']],$goods,$sku,100006), '订阅字典扩展按站点隔离，不使用其他站规范映射');
    check(!$matcher->matches(['memory_group'=>['8+128'],'device_color'=>['银色']],$goods,$sku,100005), '订阅其他条件不因规格扩展而被绕过');
    check($matcher->matches(['memory_group'=>['特制']],array_replace($goods,['memory_group'=>'12GB+1TB']),$sku,100005), '订阅支持本站显式配置的memory_aliases');
    check($matcher->matches(['memory_group'=>['46MM']],array_replace($goods,['memory_group'=>'46MM']),$sku,100005), '订阅手表尺寸不被改写');
    check(!$matcher->matches(['memory_group'=>['46MM']],$goods,$sku,100005), '不匹配的其他规格仍拒绝');
    fixture(); $noMemoryMatcher=new \addon\phone_shop\app\service\core\goods\CoreGoodsSubscriptionMatchService();
    check($noMemoryMatcher->matches([],$goods,$sku,100005) && Store::$queries===[], '无内存筛选的订阅不新增规格数据库查询');
    $source=file_get_contents(__DIR__.'/../app/service/api/goods/GoodsService.php');
    check(strpos($source,"->group('memory_group')")===false, 'API候选不再聚合商品memory_group');
    check(substr_count($source,'->filterValues((int)$this->site_id,')===2, '商品列表与颜色联动都消费同一过滤契约');
    echo "PASS {$checks} memory catalog/API checks; in-memory ORM, no live DB or writes.\n";
}
