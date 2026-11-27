<?php
declare(strict_types=1);

// Real dictionary service / API option method with in-memory ORM. No .env, business DB or writes.
namespace MemoryFilterTest {
    class Store { public static array $tables = []; public static array $configs = []; public static int $site = 100005; public static array $queries = []; }
    class Collection { private array $rows; public function __construct(array $rows) { $this->rows = $rows; } public function toArray(): array { return $this->rows; } }
    class ModelRow extends \ArrayObject {
        public function toArray(): array { return $this->getArrayCopy(); }
    }
    class Query {
        private string $table; private array $tests = []; private string $sort = ''; private string $group = ''; private string $alias = ''; private string $skuAlias = '';
        public function __construct(string $table) { $this->table = $table; Store::$queries[] = $table; }
        public function where($key, $op = null, $value = null): self { return $this->condition('and', $key, $op, $value, func_num_args()); }
        public function whereOr($key, $op = null, $value = null): self { return $this->condition('or', $key, $op, $value, func_num_args()); }
        private function condition(string $join, $key, $op, $value, int $count): self {
            if (is_callable($key)) { $nested = new self($this->table); $key($nested); $test = static fn($r) => $nested->matches($r); }
            elseif (is_array($key)) { $nested = new self($this->table); foreach ($key as $part) $nested->where(...$part); $test = static fn($r) => $nested->matches($r); }
            else { if ($count === 2) { $value = $op; $op = '='; }
                $test = static function ($row) use ($key, $op, $value): bool {
                    $actual = $row[$key] ?? $row[preg_replace('/^goods\./', '', $key)] ?? null;
                    if ($op === 'in') return in_array((string)$actual, array_map('strval', (array)$value), true);
                    if ($op === '<>') return (string)$actual !== (string)$value;
                    if ($op === '>') return $actual > $value;
                    if ($op === 'like') return str_contains((string)$actual, trim((string)$value, '%'));
                    return (string)$actual === (string)$value;
                };
            }
            $this->tests[] = [$join, $test]; return $this;
        }
        public function whereIn(string $key, array $values): self { return $this->where($key, 'in', $values); }
        public function alias(string $alias): self { $this->alias=$alias; return $this; }
        public function join(string $table, string $condition): self {
            if ($table !== 'phone_shop_goods_sku sku' || !str_contains($condition,'sku.goods_id') || !str_contains($condition,'sku.site_id')) throw new \RuntimeException('unexpected SKU join: '.$table.' / '.$condition);
            $this->skuAlias='sku'; return $this;
        }
        public function withJoin(array $joins): self {
            if (!array_key_exists('goodsSku',$joins)) throw new \RuntimeException('unexpected list join');
            $this->alias='goods'; $this->skuAlias='goodsSku'; return $this;
        }
        public function withSearch(array $fields, array $where): self {
            if (!empty($where['goods_category'])) {
                $this->where(function ($nested) use ($where) {
                    foreach ((array)$where['goods_category'] as $category) $nested->whereOr('goods_category','like','%"'.$category.'"%');
                });
            }
            return $this;
        }
        public function append(array $fields): self { return $this; }
        public function matches(array $row): bool { $result = null; foreach ($this->tests as [$join, $test]) $result = $result === null ? $test($row) : ($join === 'or' ? $result || $test($row) : $result && $test($row)); return $result ?? true; }
        public function field($field): self { return $this; }
        public function order(string $sort): self { $this->sort = $sort; return $this; }
        public function group(string $group): self { $this->group = $group; return $this; }
        public function select(): Collection {
            $rows = [];
            foreach (Store::$tables[$this->table] ?? [] as $row) {
                if ($this->alias !== '') foreach ($row as $key=>$value) $row[$this->alias.'.'.$key]=$value;
                if ($this->skuAlias === '') { if ($this->matches($row)) $rows[]=$row; continue; }
                foreach (Store::$tables['skus'] ?? [] as $sku) {
                    if ($sku['site_id'] !== $row['site_id'] || $sku['goods_id'] !== $row['goods_id']) continue;
                    $joined=$row;
                    foreach ($sku as $key=>$value) $joined[$this->skuAlias.'.'.$key]=$value;
                    if ($this->matches($joined)) $rows[]=$joined;
                }
            }
            $sorts = array_filter(explode(',', $this->sort));
            usort($rows, static function ($a, $b) use ($sorts) {
                foreach ($sorts as $sort) { $part = preg_split('/\s+/', trim($sort)); $left = $a[$part[0]] ?? null; $right = $b[$part[0]] ?? null; $difference = $left <=> $right; if ($difference) return strtolower($part[1] ?? 'asc') === 'desc' ? -$difference : $difference; }
                return 0;
            });
            if ($this->group !== '') { $unique = []; foreach ($rows as $row) $unique[(string)($row[$this->group] ?? '')] = $row; $rows = array_values($unique); }
            return new Collection($rows);
        }
        public function column(string $field): array { return array_column($this->select()->toArray(), $field); }
        public function cursor(): \Generator {
            foreach ($this->select()->toArray() as $i=>$row) {
                if ($i%2===0) { yield $row; continue; }
                // Goods::getGoodsCategoryAttr returns int[] on hydrated models; raw reads have JSON.
                $row['goods_category']=array_map('intval',json_decode($row['goods_category'],true));
                yield new ModelRow($row);
            }
        }
    }
    abstract class Model {
        protected static string $table = '';
        public static function where(...$args): Query { return (new Query(static::$table))->where(...$args); }
    }
}
namespace core\base { class BaseCoreService {} class BaseApiService { protected int $site_id; public function __construct() { $this->site_id = \MemoryFilterTest\Store::$site; } } }
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys {
    class CoreConfigService {
        public function getConfigValue($site, $key) {
            if ($key !== 'PHONE_SHOP_LISTING_MAPPING') throw new \RuntimeException('读取了错误的站点内存别名配置');
            return \MemoryFilterTest\Store::$configs[$site] ?? [];
        }
    }
}
namespace addon\phone_shop\app\model\goods {
    class Goods extends \MemoryFilterTest\Model {
        protected static string $table = 'goods';
        public function withSearch(array $fields, array $where): \MemoryFilterTest\Query {
            return $this->where('delete_time',0)->withSearch($fields,$where);
        }
    }
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
namespace addon\phone_shop\app\service\core\agent { class AgentConfigService { public function getMasterSiteId() { return \MemoryFilterTest\Store::$site; } } }
namespace addon\phone_shop\app\service\core\goods { class CoreGoodsActivePriceService {} }
namespace {
    use MemoryFilterTest\Store;
    use addon\phone_shop\app\service\core\goods\CoreMemoryFilterService;
    use addon\phone_shop\app\service\core\intake\CoreListingMappingService;
    if (PHP_SAPI !== 'cli') exit(1);
    // 可直接验证发给站点的 ZIP；避免源码用例通过、实际发布包却漏文件。
    $filterRoot = dirname(__DIR__);
    if (isset($argv[1])) {
        $file = realpath($argv[1]);
        if (!$file || !is_file($file)) throw new \RuntimeException('补丁 ZIP 不存在');
        $filterRoot = 'zip://' . $file . '#addon/phone_shop';
    }
    require $filterRoot . '/app/support/GoodsMemorySpec.php';
    require $filterRoot . '/app/support/GoodsScopedSpec.php';
    require __DIR__ . '/../app/dict/goods/GoodsDict.php';
    require __DIR__ . '/../app/service/core/goods/CoreDeviceAttributeService.php';
    require $filterRoot . '/app/service/core/goods/CoreMemoryFilterService.php';
    require $filterRoot . '/app/service/core/goods/CoreModelSpecFacetService.php';
    require __DIR__ . '/../app/service/core/goods/CoreGoodsSubscriptionMatchService.php';
    require $filterRoot . '/app/service/api/goods/GoodsService.php';
    class Api extends \addon\phone_shop\app\service\api\goods\GoodsService {
        public function getWarehouseOptions(): array { return []; }
        protected function pageQuery($query): array {
            // Run the actual getPage query conditions, but skip price enrichment after pagination.
            $rows = $query->select()->toArray();
            return ['matched'=>array_column($rows,'memory_group'), 'ids'=>array_column($rows,'goods_id'), 'data'=>[]];
        }
    }
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
        foreach (Store::$tables['goods'] as &$row) $row += ['is_gift'=>0, 'is_online_sellable'=>1, 'stock'=>1, 'goods_name'=>'测试商品'];
        unset($row);
        buildSkus();
    }
    function buildSkus(): void {
        Store::$tables['skus']=[];
        foreach(Store::$tables['goods'] as $i=>&$row) {
            $row['goods_id']=$i+1;
            Store::$tables['skus'][]=['site_id'=>$row['site_id'],'goods_id'=>$row['goods_id'],'is_default'=>1,'stock'=>1];
        }
        unset($row);
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
    fixture(); Store::$tables['groups'][]=['site_id'=>100005,'group_id'=>5,'label'=>'平板独立规格','category_id'=>12,'sort'=>20]; Store::$tables['items'][]=['site_id'=>100005,'group_id'=>5,'item_id'=>51,'item_value'=>'1TB','sort'=>0];
    $service=new CoreMemoryFilterService(); check(array_column($service->groups(100005,[10]),'group_id')===[5,1], '选父分类包含其后代绑定组，排序相同按既有group_id desc');
    check(array_column($service->groups(100005,[11]),'group_id')===[1], '同品牌下不匹配无关兄弟叶分类');
    fixture(); $api=new Api(); $response=$api->getFilterOptions(); check(array_column($response['memory_groups'],'group_id')===[2,1,3,4], '真实API方法返回分组');
    check(array_column($response['memories'],'value')===['8+128','特制','128G','256G','46MM','16+512'], '旧扁平字段仅来源于同一规格字典');
    check(!in_array('脏值乱码ABC',array_column($response['memories'],'value'),true), '真实API不泄漏在售脏规格');
    $response=$api->getFilterOptions(['goods_category'=>'11']); check(array_column($response['memory_groups'],'group_id')===[1], '真实API分类过滤分组');
    $response=$api->getFilterOptions(['goods_category'=>'11','memory_group'=>'128G']); check(array_column($response['colors'],'value')===['白色','黑色'], '颜色联动使用同一受控规格扩展，128G和128GB均能命中');
    check(!class_exists(CoreListingMappingService::class, false), '空筛选及选中内存均不加载上架服务');
    require __DIR__ . '/../app/service/core/intake/CoreListingMappingService.php';
    $plain=new CoreListingMappingService(); foreach(['128G','8+128','46MM','特制'] as $raw) check(in_array($plain->normalizeMemory($raw,Store::$configs[100005]['memory_aliases']),$service->filterValues(100005,[$raw]),true), '与真实建品规范化一致:'.$raw);
    $response=$api->getFilterOptions(['goods_category'=>'11','brand_id'=>'999']); check($response['colors']===[] && $response['memory_groups']===[] && $response['memories']===[], '型号和品牌组合无商品时不回退全组内存');
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
    // 17 Pro Max inherits the entire Apple dictionary; only four capacities belong to this model.
    fixture();
    $options=['16GB','32GB','64GB','512G','256GB','1TB','2T','4TB','6TB','8TB','10TB','12TB','14TB','16TB','18TB'];
    Store::$tables['items']=[];
    foreach ($options as $sort=>$option) Store::$tables['items'][]=['site_id'=>100005,'group_id'=>1,'item_id'=>$sort+1,'item_value'=>$option,'sort'=>$sort];
    $base=Store::$tables['goods'][0];
    $base['goods_name']='iPhone 17 Pro Max';
    Store::$tables['goods']=[];
    foreach (['256GB','512GB','1T','2TB','2TB','奇怪规格','12GB+16GB'] as $value) Store::$tables['goods'][]=array_replace($base,['memory_group'=>$value]);
    Store::$tables['goods'][2]['device_color']='银色';
    Store::$tables['goods'][2]['condition_grade']='优良';
    foreach ([
        ['16GB',['goods_category'=>'["10","12"]']], ['32GB',['is_online_sellable'=>0]], ['64GB',['site_id'=>100006]],
        ['4TB',['is_gift'=>1]], ['6TB',['sale_status'=>'locked']], ['8TB',['sale_status'=>'sold']],
        ['10TB',['delete_time'=>123]], ['12TB',['status'=>0]], ['14TB',['stock'=>0]], ['16TB',[]], ['18TB',[]]
    ] as [$value,$overrides]) Store::$tables['goods'][]=array_replace($base,['memory_group'=>$value],$overrides);
    buildSkus();
    foreach (Store::$tables['goods'] as $i=>$row) {
        if ($row['memory_group']==='16TB') Store::$tables['skus'][$i]['stock']=0;
        if ($row['memory_group']==='18TB') {
            // 正库存来自其他站同号默认 SKU / 本站非默认 SKU，都不应算在这台商品上。
            Store::$tables['skus'][]=array_replace(Store::$tables['skus'][$i], ['site_id'=>100006]);
            Store::$tables['skus'][$i]['is_default']=0;
        }
    }
    $api=new Api();
    $all=$api->getFilterOptions();
    check(array_column($all['memories'],'value')===$options, '全站继续按运营配置顺序展示，不受某个型号库存影响');
    $scoped=['goods_category'=>'11'];
    $response=$api->getFilterOptions($scoped);
    $expected=['512G','256GB','1TB','2T'];
    check(array_column($response['memories'],'value')===$expected, '17 Pro Max 只保留有在售匹配的已配置容量；不混入兄弟型号、未上架、他站、赠品或脏规格，保留后台排序');
    check(count($response['memory_groups'])===1 && array_column($response['memory_groups'][0]['items'],'label')===$expected, '新旧前端的分组和扁平容量返回一致');
    $selected=$api->getFilterOptions($scoped+['memory_group'=>'256GB']);
    check(array_column($selected['memories'],'value')===$expected, '已经选择一项容量也不隐藏同型号的其他可选容量');
    foreach ([['device_color'=>'银色'],['condition_grade'=>'优良'],['brand_id'=>1,'condition_grade'=>'优良','device_color'=>'银色']] as $filters) {
        check(array_column($api->getFilterOptions($scoped+$filters)['memories'],'value')===['1TB'], '按型号和其他筛选联动：'.json_encode($filters,JSON_UNESCAPED_UNICODE));
    }
    check($api->getFilterOptions($scoped+['condition_grade'=>'极佳','device_color'=>'银色'])['memory_groups']===[], '其他筛选无匹配时不回退到整组');
    check(array_column($api->getFilterOptions(['goods_category'=>'12'])['memories'],'value')===['16GB'], '切换到兄弟型号不会继承之前的可售容量');
    foreach (array_combine($expected,['512GB','256GB','1T','2TB']) as $value=>$stored) {
        $result=$api->getPage($scoped+['memory_group'=>$value,'order'=>'latest']);
        check(array_values(array_unique($result['matched']))===[$stored], '点击容量能命中商品列表，且不会混入不相关容量：'.$value);
    }
    fixture();
    Store::$tables['items']=[];
    foreach ([1=>['16GB','256GB','512GB','1TB'],2=>['256GB','12+256','8+128'],3=>['38mm','41mm','46MM','49mm']] as $group=>$values) {
        foreach ($values as $i=>$value) Store::$tables['items'][]=['site_id'=>100005,'group_id'=>$group,'item_id'=>$group*10+$i,'item_value'=>$value,'sort'=>$i];
    }
    $base=Store::$tables['goods'][0];
    Store::$tables['goods']=[];
    $add=function(string $category,string $raw,string $title,string $color,array $overrides=[]) use ($base): int {
        Store::$tables['goods'][]=array_replace($base,['goods_category'=>$category,'memory_group'=>$raw,'goods_name'=>$title,'device_color'=>$color],$overrides);
        return count(Store::$tables['goods']);
    };
    $phone='["10","11"]'; $watch='["30"]'; $android='["20","21","22"]';
    $p256=$add($phone,'13','苹果 iPhone 17 Pro Max 256GB 原色','原色');
    $p512=$add($phone,'13','苹果 iPhone 17 Pro Max 512GB 银色','银色');
    $add($phone,'13','苹果 iPhone 17 Pro Max 1TB 已售','红色',['sale_status'=>'sold']);
    $add($phone,'13','苹果 iPhone 17 Pro Max（版本未注明）','粉色');
    $add($phone,'13','苹果 iPhone 17 Pro Max 8TB','橙色'); // no matching configured value
    $w41=$add($watch,'','Apple Watch S9 GPS 41mm 32GB','绿色');
    $w41Storage=$add($watch,'32GB','Apple Watch S9 41mm 32GB','银色');
    $w46=$add($watch,'46毫米','Apple Watch Series 10 32GB','金色');
    $w46Title=$add($watch,'13','苹果手表 S10 46毫米 铝金属','紫色');
    $add($watch,'','苹果手表 表带 49mm','橙色');
    $add($watch,'','兼容 Apple Watch 38mm 的保护壳','蓝色');
    $add($watch,'','Apple Watch 41mm / 46mm','粉色');
    $add($watch,'32GB','Apple Watch 32GB（未注明表盘尺寸）','白色');
    $add($watch,'','测试手表 256GB 未注明表盘尺寸','白色');
    $add($watch,'','Apple Watch 49mm 已下架','黄色',['status'=>0]);
    $aCombo=$add($android,'13','小米 17 12GB+256GB 黑色','黑色');
    buildSkus(); $api=new Api();
    foreach ([
        ['11',['256GB','512GB'],['256GB'=>[[$p256],['原色']], '512GB'=>[[$p512],['银色']]]],
        ['30',['41mm','46MM'],['41mm'=>[[$w41,$w41Storage],['绿色','银色']], '46MM'=>[[$w46,$w46Title],['金色','紫色']]]],
        ['22',['12+256'],['12+256'=>[[$aCombo],['黑色']]]],
    ] as [$category,$values,$selections]) {
        $scope=['goods_category'=>$category];
        $response=$api->getFilterOptions($scope);
        check(array_column($response['memories'],'value')===$values, '旧商品和手表按本站字典及该分类的实际可售规格返回: '.$category);
        foreach ($selections as $value=>[$ids,$colors]) {
            $selection=$scope+['memory_group'=>$value];
            $found=$api->getPage($selection+['order'=>'latest'])['ids'];
            sort($found); sort($ids);
            check($found===$ids, '已选规格精确命中设备，不混入同样旧字段值但实际规格不同的其他设备: '.$category.'/'.$value);
            $found=array_column($api->getFilterOptions($selection)['colors'],'value'); sort($found); sort($colors);
            check($found===$colors,'颜色联动与所选规格的同一批设备一致: '.$category.'/'.$value);
        }
    }
    $multi=['goods_category'=>'11,30','memory_group'=>'256GB'];
    check($api->getPage($multi+['order'=>'latest'])['ids']===[$p256],'同时选择手表和手机分类时，只有手机能按手机字典匹配256GB');
    check(array_column($api->getFilterOptions($multi)['colors'],'value')===['原色'],'多个型号共选时，手表容量不能混进手机的颜色结果');
    Store::$tables['items']=[];
    check((new Api())->getFilterOptions(['goods_category'=>'30'])['memories']===[], '无手表尺寸字典时不直接从商品标题生成选项');
    // 苹果大类提供容量；它下面的手表子类另配尺寸，手表不能同时贡献父类的容量。
    fixture();
    foreach (Store::$tables['categories'] as &$node) if ($node['category_id']===30) $node['pid']=10;
    unset($node);
    Store::$tables['categories'][]=['site_id'=>100005,'category_id'=>31,'pid'=>30];
    Store::$tables['items']=[
        ['site_id'=>100005,'group_id'=>1,'item_id'=>1,'item_value'=>'32GB','sort'=>0],
        ['site_id'=>100005,'group_id'=>1,'item_id'=>2,'item_value'=>'256GB','sort'=>1],
        ['site_id'=>100005,'group_id'=>3,'item_id'=>3,'item_value'=>'46MM','sort'=>0],
    ];
    Store::$tables['goods']=[];
    $handset=$add('["10","11"]','256GB','苹果手机 256GB','白色');
    $watch46=$add('["10","30","31"]','32GB','Apple Watch 46mm 32GB','绿色');
    buildSkus(); $api=new Api();
    foreach (['10'=>['256GB','46MM'],'31'=>['46MM'],'11,31'=>['256GB','46MM']] as $scope=>$visible) {
        $model=['goods_category'=>(string)$scope];
        check(array_column($api->getFilterOptions($model)['memories'],'value')===$visible, '同一分类路径最近的具体规格优先，不把手表当作32GB手机: '.$scope);
        check($api->getPage($model+['memory_group'=>'32GB','order'=>'latest'])['ids']===[], '没有32GB手机时，按32GB不能找到库存字段填32GB的手表: '.$scope);
        check($api->getPage($model+['memory_group'=>'46MM','order'=>'latest'])['ids']===[$watch46], '手表可按46MM而非存储容量查到: '.$scope);
    }
    $handset32=$add('["10","11"]','32GB','苹果手机 32GB','紫色');
    buildSkus(); $api=new Api();
    foreach (['10','11,31'] as $scope) {
        $selected=['goods_category'=>$scope,'memory_group'=>'32GB'];
        check($api->getPage($selected+['order'=>'latest'])['ids']===[$handset32], '兄弟手机真实有32GB时，只命中手机: '.$scope);
        check(array_column($api->getFilterOptions($selected)['colors'],'value')===['紫色'], '兄弟手机的32GB联动颜色不串入手表绿色: '.$scope);
    }
    echo "PASS {$checks} memory catalog/API checks; in-memory ORM, no live DB or writes.\n";
}
