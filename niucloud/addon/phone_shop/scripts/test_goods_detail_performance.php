<?php
declare(strict_types=1);

// Exercise the actual detail and price services; no DB, HTTP, statistics or queue writes.
namespace DetailFixture {
    class Data {
        public static array $rows = [], $queries = [], $daily = [], $visits = [];
        public static function check(bool $ok, string $message): void {
            if (!$ok) throw new \RuntimeException($message);
        }
    }
    class Row extends \ArrayObject {
        public function toArray(): array { return $this->getArrayCopy(); }
        public function isEmpty(): bool { return !$this->count(); }
        public function getData(string $name) { return $this[$name]; }
    }
    class Model {
        protected const TABLE = '';
        private array $filters = [], $relations = [];
        public function where($conditions, ...$rest): self {
            $q = clone $this;
            foreach ($conditions as $key => $term) {
                if (!is_int($key)) $term = [$key, '=', $term];
                [$field, $operator, $value] = $term;
                Data::check($operator === '=', 'unexpected comparison');
                $q->filters[$field] = $value;
            }
            return $q;
        }
        public function field(...$args): self { return $this; }
        public function append(...$args): self { return $this; }
        public function with($relations): self { $this->relations = $relations; return $this; }
        private function read(): array {
            Data::$queries[] = static::TABLE;
            return array_filter(Data::$rows[static::TABLE], function ($row) {
                foreach ($this->filters as $key => $value) {
                    if (!array_key_exists($key, $row) || (string)$row[$key] !== (string)$value) return false;
                }
                return true;
            });
        }
        public function count(): int { return count($this->read()); }
        public function findOrEmpty(): Row {
            $rows = $this->read(); $row = reset($rows) ?: [];
            if ($row && static::TABLE === 'sku' && isset($this->relations['goods'])) {
                $row['goods'] = Data::$rows['goods'][$row['goods_id']];
                $row['skuList'] = array_values(array_filter(Data::$rows['sku'], fn($s) => $s['goods_id'] === $row['goods_id']));
                $row['goodsSpec'] = [];
            }
            return new Row($row);
        }
    }
}
namespace core\base {
    class BaseCoreService { public function __construct() {} }
    class BaseApiService extends BaseCoreService {
        public int $site_id = 1, $member_id = 0; protected $model;
    }
}
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace addon\phone_shop\app\model\goods {
    class Goods extends \DetailFixture\Model { protected const TABLE = 'goods'; }
    class GoodsSku extends \DetailFixture\Model { protected const TABLE = 'sku'; }
    class GoodsCollect extends \DetailFixture\Model { protected const TABLE = 'collect'; }
}
namespace addon\phone_shop\app\model\discount {
    class DiscountGoods extends \DetailFixture\Model { protected const TABLE = 'promotions'; }
    class Discount extends \DetailFixture\Model { protected const TABLE = 'activity'; }
}
namespace app\model\member { class Member extends \DetailFixture\Model { protected const TABLE = 'members'; } }
namespace app\service\api\diy { class DiyService { public function getInfo($params) { return ['test_template' => $params['name']]; } } }
namespace addon\phone_shop\app\service\core\order {
    class CoreOrderConfigService { public function getEvaluateConfig($site) { return ['evaluate_is_show' => 1]; } }
}
namespace addon\phone_shop\app\service\core\goods {
    class CoreGoodsLimitBuyService { public function getGoodsHasBuyNumber(...$args) { return 2; } }
    class CoreGoodsStatService { public static function addStat($args) { \DetailFixture\Data::$daily[] = $args; } }
    class CoreGoodsAccessNumService { public function inc($args) { \DetailFixture\Data::$visits[] = $args; } }
}
namespace addon\phone_shop\app\service\api\goods { function event(...$args) { return []; } }
namespace {
    use DetailFixture\Data as F;
    use addon\phone_shop\app\service\api\goods\GoodsService;
    if (PHP_SAPI !== 'cli') exit(1);
    require dirname(__DIR__, 3) . '/vendor/autoload.php';
    $base = ['delete_time'=>0,'is_discount'=>0,'diy_detail_id'=>0,'goods_desc'=>'desc','goods_image'=>'',
        'member_discount'=>'fixed_price','is_limit'=>1];
    foreach ([[1,1],[2,1],[3,2],[4,1]] as [$id,$site]) {
        F::$rows['goods'][$id] = ['goods_id'=>$id,'site_id'=>$site] + $base;
    }
    foreach ([[1,1,10,1,99,90],[1,1,11,0,109,95],[2,1,20,1,7,3],[3,2,30,1,1,1],[4,1,40,0,8,7]] as [$g,$s,$sku,$d,$p,$m]) {
        F::$rows['sku'][$sku] = ['goods_id'=>$g,'site_id'=>$s,'sku_id'=>$sku,'is_default'=>$d,'price'=>$p,
            'member_price'=>['level_2'=>$m],'stock'=>1];
    }
    F::$rows['members'] = [['site_id'=>1,'member_id'=>7,'member_level'=>8,'memberLevelData'=>['level_no'=>2]]];
    F::$rows['collect'] = [['site_id'=>1,'member_id'=>7,'goods_id'=>1]];
    F::$rows['promotions'] = F::$rows['activity'] = [];
    $service = new GoodsService();
    $detail = fn($g,$s='') => $service->getDetail(['goods_id'=>$g,'sku_id'=>$s,'type'=>'']);
    $guest = $detail(1);
    F::check($guest['sku_id'] === 10 && $guest['show_price'] == 99 && $guest['show_type'] === 'original_price', 'guest default SKU');
    F::check(count(array_keys(F::$queries,'sku',true)) === 1, 'default should use one primary SKU query');
    F::check(count(array_keys(F::$queries,'promotions',true)) === 2, 'each unique SKU price resolved only once');
    $service->member_id = 7;
    $member = $detail(1,11);
    F::check($member['sku_id'] === 11 && $member['show_price'] == 95 && $member['show_type'] === 'member_price', 'explicit member SKU price');
    F::check(array_column($member['skuList'],'show_price','sku_id') === [10=>90.0,11=>95.0], 'all SKU prices match current member');
    F::check($guest['goods']['is_collect'] === 0 && $member['goods']['is_collect'] === 1 && $member['goods']['has_buy'] === 2, 'member fields isolated');
    F::$rows['sku'][11]['stock'] = 0;
    F::$rows['sku'][11]['price'] = 77;
    $service->member_id = 0;
    $fresh = $detail('',11);
    F::check($fresh['stock'] === 0 && $fresh['show_price'] == 77 && $fresh['skuList'][1]['stock'] === 0, 'SKU-only load sees stock and price updates');
    F::$rows['promotions'][] = ['site_id'=>1,'goods_id'=>1,'sku_id'=>11,'status'=>'active','is_enabled'=>1,'discount_id'=>6,'discount_price'=>70];
    F::$rows['activity'][] = ['site_id'=>1,'discount_id'=>6,'status'=>'active','start_time'=>0,'end_time'=>0];
    $discount = $detail(1,11);
    F::check($discount['show_price'] == 70 && $discount['show_type'] === 'discount_price' && $discount['skuList'][1]['show_price'] == 70, 'active campaign applies to both views of selected SKU');
    F::$rows['activity'][0]['end_time'] = time()-1;
    F::check($detail(1,11)['show_price'] == 77, 'expired campaign never persists to next request');
    F::check($detail(4) === [] && $detail(1,99) === [] && $detail(1,20) === [] && $detail('',30) === [], 'no fallback to unrelated SKU');
    foreach ([999,3] as $notVisible) {
        try { $detail($notVisible); throw new RuntimeException('invisible product returned'); }
        catch (\core\exception\CommonException $e) { F::check($e->getMessage() === 'SHOP_GOODS_NOT_EXIST','missing or other-site goods'); }
    }
    F::check(array_column(F::$daily,'goods_id') === array_fill(0,5,1) && array_column(F::$visits,'goods_id') === array_fill(0,5,1), 'count each successful view exactly once');
    echo "PASS: default/explicit/site-scoped SKUs; live guest/member price and stock; no duplicate price/default lookup; statistics preserved.\n";
}
