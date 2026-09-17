<?php
declare(strict_types=1);
// 内存数据库替身，执行真实价格计算/保存服务/双向监听器；不访问生产、不付款、不通知。
namespace PricingTest {
    class State { public static array $tables = [], $config = [], $levels = [], $tx = [], $ledger = []; public static bool $erp = true, $mall = true, $active = false; }
    class Row {
        public function __construct(public string $table, public array $data = []) {}
        public function __get($key) { return $this->data[$key] ?? null; }
        public function __isset($key): bool { return isset($this->data[$key]); }
        public function isEmpty(): bool { return !$this->data; }
        public function toArray(): array { return $this->data; }
        public function save($data): void { $this->data = array_replace($this->data, $data); $pk = str_contains($this->table, 'sku') ? 'sku_id' : ($this->table === 'phone_shop_goods' ? 'goods_id' : 'id'); State::$tables[$this->table][$this->data[$pk]] = $this->data; }
    }
    class Rows implements \IteratorAggregate {
        public function __construct(private array $rows) {}
        public function getIterator(): \Traversable { return new \ArrayIterator($this->rows); }
        public function toArray(): array { return array_map(fn($r) => $r->toArray(), $this->rows); }
        public function column($field): array { return array_column($this->toArray(), $field); }
    }
    class Query {
        private array $filters = [];
        public function __construct(private string $table) {}
        public function where(...$args): self {
            if (is_array($args[0])) { foreach ($args[0] as $f) $this->where(...$f); return $this; }
            [$key,$op,$value] = count($args) === 2 ? [$args[0],'=',$args[1]] : $args;
            $this->filters[] = fn($r) => $op === '>' ? ($r[$key] ?? 0) > $value : ($r[$key] ?? null) == $value;
            return $this;
        }
        public function lock($value): self { if (!State::$tx) throw new \RuntimeException('lock outside transaction'); return $this; }
        public function order(...$args): self { return $this; }
        public function field(...$args): self { return $this; }
        private function all(): array { return array_values(array_filter(State::$tables[$this->table] ?? [], function($row) { foreach($this->filters as $f) if(!$f($row)) return false; return true; })); }
        public function select(): Rows { return new Rows(array_map(fn($r) => new Row($this->table,$r),$this->all())); }
        public function findOrEmpty(): Row { return new Row($this->table, $this->all()[0] ?? []); }
        public function find(): ?array { return $this->all()[0] ?? null; }
        public function count(): int { return count($this->all()); }
        public function update($data): int { $rows=$this->all(); foreach($rows as $row) (new Row($this->table,$row))->save($data); return count($rows); }
    }
    class Model { public static function where(...$args): Query { return (new Query(static::TABLE))->where(...$args); } }
}
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace app\service\core\sys { class CoreConfigService { public function getConfigValue($site,$key) { return \PricingTest\State::$config[$site] ?? []; } public function setConfig($site,$key,$data) { \PricingTest\State::$config[$site]=$data; } } }
namespace addon\phone_shop\app\service\admin { class MemberLevelNoService { public static function levelsWithNo($site): array { return \PricingTest\State::$levels[$site] ?? []; } } }
namespace addon\phone_shop\app\service\admin\goods { class GoodsService { public function getActiveGoodsCount($id): int { return \PricingTest\State::$active ? 1 : 0; } } }
namespace addon\phone_shop\app\model\goods { class Goods extends \PricingTest\Model { const TABLE='phone_shop_goods'; } class GoodsSku extends \PricingTest\Model { const TABLE='phone_shop_goods_sku'; } }
namespace addon\hsx_erp\app\model { class ErpAsset extends \PricingTest\Model { const TABLE='erp_asset'; } }
namespace addon\hsx_erp\app\service\admin { class ErpLedgerService { public static function forSite(...$args): self { return new self(); } public function asset($data) { \PricingTest\State::$ledger[]=$data; } } }
namespace think\facade {
    class Db {
        public static function name($name): \PricingTest\Query { return new \PricingTest\Query($name); }
        public static function connect(): self { return new self(); } public function getPdo(): self { return $this; } public function inTransaction(): bool { return (bool)\PricingTest\State::$tx; }
        public static function transaction($fn) { \PricingTest\State::$tx[] = [\PricingTest\State::$tables,\PricingTest\State::$ledger]; try { $v=$fn();array_pop(\PricingTest\State::$tx);return $v; } catch(\Throwable $e) { [\PricingTest\State::$tables,\PricingTest\State::$ledger]=array_pop(\PricingTest\State::$tx);throw $e; } }
    }
}
namespace {
    use PricingTest\State as S;
    use think\facade\Db;
    use addon\phone_shop\app\support\TierPriceRule as Rule;
    use addon\phone_shop\app\service\core\goods\CoreTierPricingService as Pricing;
    use addon\phone_shop\app\service\core\goods\CoreGoodsPriceWriteService as Writer;
    use addon\hsx_erp\app\service\admin\ErpSalesPriceService as Erp;
    $root=dirname(__DIR__).'/niucloud/addon/';
    foreach(['phone_shop/app/support/TierPriceRule.php','phone_shop/app/service/core/goods/CoreTierPricingService.php','phone_shop/app/service/core/goods/CoreGoodsPriceWriteService.php','phone_shop/app/service/core/goods/CoreMemberPriceService.php','phone_shop/app/listener/erp/ErpSalesPricing.php','hsx_erp/app/service/admin/ErpSalesPriceService.php','hsx_erp/app/listener/PhoneShopSalesPriceChanged.php'] as $f) require $root.$f;
    function event($name,$data): array {
        if($name==='PhoneShopSalesPriceChanged' && S::$erp) return [(new \addon\hsx_erp\app\listener\PhoneShopSalesPriceChanged())->handle($data)];
        if($name==='HsxErpSalesPricing' && S::$mall) return [(new \addon\phone_shop\app\listener\erp\ErpSalesPricing())->handle($data)];
        return [];
    }
    $count=0;
    function check($value,$message): void { global $count; ++$count; if(!$value) throw new \RuntimeException($message); }
    function rejects($fn,$message): void { try{$fn();}catch(\Throwable $e){check(str_contains($e->getMessage(),$message),'unexpected error: '.$e->getMessage());return;} throw new \RuntimeException('Expected rejection: '.$message); }
    $levels=[['level_id'=>501,'level_no'=>7,'level_name'=>'V1','growth'=>10,'status'=>1],['level_id'=>302,'level_no'=>2,'level_name'=>'V2','growth'=>20,'status'=>1],['level_id'=>123,'level_no'=>1,'level_name'=>'V3','growth'=>30,'status'=>1]];
    $config=['enabled'=>1,'base_level_no'=>1,'rules'=>[0=>['type'=>'fixed','value'=>200],7=>['type'=>'percent','value'=>5],2=>['type'=>'fixed','value'=>50]]];
    $q=Rule::quote(2000,$config,$levels);
    check($q['retail_price']===2200.0 && $q['member_price']===['level_7'=>'2100.00','level_2'=>'2050.00','level_1'=>'2000.00'],'site level prices');
    check(\addon\phone_shop\app\service\core\goods\CoreMemberPriceService::calculate([], 'fixed_price', $q['member_price'], $q['retail_price']) === '2200.00', 'customer resolver: ordinary price');
    check(\addon\phone_shop\app\service\core\goods\CoreMemberPriceService::calculate(['site_id'=>100005,'member_level'=>123,'memberLevelData'=>['level_no'=>1]], 'fixed_price', $q['member_price'], $q['retail_price']) === '2000.00', 'customer resolver: highest membership baseline');
    check(Rule::quote(2000,$config,$levels)===$q,'idempotent calculation');
    $single=[['level_no'=>5,'level_name'=>'V1','growth'=>0,'status'=>1]];
    check(Rule::quote(2000,['enabled'=>1,'base_level_no'=>5,'rules'=>[0=>['value'=>200]]],$single)['member_price']['level_5']==='2000.00','one membership level');
    $bands=$config; $bands['rules'][0]['bands']=[['min'=>1000,'max'=>3000,'type'=>'fixed','value'=>300]];
    check(Rule::quote(1000,$bands,$levels)['retail_price']===1300.0,'inclusive lower');
    check(Rule::quote(3000,$bands,$levels)['retail_price']===3200.0,'exclusive upper');
    check(Rule::quote(999.99,$bands,$levels)['retail_price']===1199.99,'fallback band');
    rejects(fn()=>Rule::quote(0,$config,$levels),'必须大于');
    rejects(fn()=>Rule::quote(-1,$config,$levels),'价格或加价值');
    rejects(fn()=>Rule::quote('NaN',$config,$levels),'价格或加价值');
    rejects(fn()=>Rule::quote(2000,array_merge($config,['base_level_no'=>7]),$levels),'门槛最高');
    $bad=$bands;$bad['rules'][0]['bands'][]=['min'=>2000,'max'=>4000,'value'=>200]; rejects(fn()=>Rule::quote(2000,$bad,$levels),'不能重叠');
    $bad=$config;$bad['rules'][0]['value']=1;rejects(fn()=>Rule::quote(2000,$bad,$levels),'不能低于');
    S::$levels=[100005=>$levels,100024=>$single]; S::$config=[100005=>$config];
    check((new Pricing())->policy(100024)['enabled']===0,'other site default off');
    check((float)Rule::quote(2000,['enabled'=>0],[])['retail_price']===2000.0,'off no levels');
    S::$tables=['erp_asset'=>[10=>['id'=>10,'site_id'=>100005,'imei'=>'357465822199406','status'=>'in_stock','listing_status'=>'listed','retail_price'=>2200,'estimate_sale_price'=>2000,'total_cost'=>1800]],
        'phone_shop_goods'=>[20=>['goods_id'=>20,'site_id'=>100005,'is_proxy'=>0,'status'=>1,'member_discount'=>'fixed_price']],
        'phone_shop_goods_sku'=>[30=>['sku_id'=>30,'site_id'=>100005,'goods_id'=>20,'erp_asset_id'=>10,'sku_no'=>'357465822199406','sku_name'=>'','price'=>2200,'sale_price'=>2200,'member_price'=>json_encode($q['member_price']),'device_snapshot'=>json_encode(['check_meta'=>['ok'=>1],'_tier_pricing'=>['base_price'=>2000]]),'stock'=>1]],
        'phone_shop_device_intake'=>[40=>['id'=>40,'site_id'=>100005,'erp_asset_id'=>10,'sale_price'=>2200,'peer_price'=>2000]], 'orders'=>[1=>['amount'=>2200,'paid'=>2200]]];
    function mallWrite($base): void { Db::transaction(function()use($base){$w=new Writer();$old=$w->capture(100005,20);$w->finish(100005,20,[['sku_id'=>30,'price'=>S::$tables['phone_shop_goods_sku'][30]['price'],'pricing_base_price'=>$base]],$old);}); }
    mallWrite(2100);
    check((float)S::$tables['erp_asset'][10]['retail_price']===2300.0,'mall to erp retail');
    check((float)S::$tables['erp_asset'][10]['estimate_sale_price']===2100.0,'mall to erp baseline');
    check((float)S::$tables['phone_shop_device_intake'][40]['peer_price']===2100.0,'mall updates pending intake');
    check(S::$tables['orders'][1]===['amount'=>2200,'paid'=>2200],'historical order unchanged');
    check(S::$tables['erp_asset'][10]['total_cost']===1800,'cost unchanged');
    check(Pricing::snapshot(S::$tables['phone_shop_goods_sku'][30]['device_snapshot'])['check_meta']['ok']===1,'QC preserved');
    mallWrite(2100);check((float)S::$tables['phone_shop_goods_sku'][30]['price']===2300.0,'save again no compound');
    Db::transaction(function(){ $fields=Erp::fields(100005,2200);S::$tables['erp_asset'][10]=array_merge(S::$tables['erp_asset'][10],$fields);Erp::sync(100005,S::$tables['erp_asset'][10]); });
    check((float)S::$tables['phone_shop_goods_sku'][30]['price']===2400.0,'erp to mall retail');
    check(Pricing::snapshot(S::$tables['phone_shop_goods_sku'][30]['member_price'])['level_1']==='2200.00','erp to mall member');
    $before=S::$tables;rejects(fn()=>mallWrite(''),'不能留空');check(S::$tables===$before,'cleared baseline rejected without changes');
    S::$tables['phone_shop_goods_sku'][30]['sale_price']=1990;
    Db::transaction(function(){ $w=new Writer();$w->finish(100005,20,[['sku_id'=>30,'pricing_base_price'=>2200]],$w->capture(100005,20),false,true); });
    check(S::$tables['phone_shop_goods_sku'][30]['sale_price']===1990,'unchanged campaign keeps promotional sale price');
    S::$tables['phone_shop_goods_sku'][30]['sale_price']=2400;
    S::$erp=false;$before=S::$tables;rejects(fn()=>mallWrite(2300),'未响应');check(S::$tables===$before,'missing erp rolls back whole change');S::$erp=true;
    S::$tables['erp_asset'][10]['imei']='different';$before=S::$tables;rejects(fn()=>mallWrite(2300),'串号不一致');check(S::$tables===$before,'identity mismatch rollback');S::$tables['erp_asset'][10]['imei']='357465822199406';
    S::$tables['erp_asset'][10]['status']='sold';$before=S::$tables;rejects(fn()=>mallWrite(2300),'不在库');check(S::$tables===$before,'sold does not reprice or relist');S::$tables['erp_asset'][10]['status']='in_stock';
    S::$tables['phone_shop_goods'][20]['is_proxy']=1;rejects(fn()=>mallWrite(2300),'代理商品');S::$tables['phone_shop_goods'][20]['is_proxy']=0;
    rejects(fn()=>Db::transaction(fn()=>(new Writer())->capture(100024,20)),'不属于当前站点');
    S::$active=true;$before=S::$tables;rejects(fn()=>Db::transaction(function(){S::$tables['erp_asset'][10]['retail_price']=2500;Erp::sync(100005,S::$tables['erp_asset'][10]);}),'营销活动');check(S::$tables===$before,'campaign rollback');S::$active=false;
    S::$config[100005]['enabled']=0;
    Db::transaction(function(){S::$tables['erp_asset'][10]['retail_price']=2600;Erp::sync(100005,S::$tables['erp_asset'][10]);});
    check((float)S::$tables['phone_shop_goods_sku'][30]['price']===2600.0,'off keeps manual retail');
    check(!isset(Pricing::snapshot(S::$tables['phone_shop_goods_sku'][30]['device_snapshot'])['_tier_pricing']),'off removes stale baseline snapshot only on edited sku');
    check(S::$tables['phone_shop_goods'][20]['status']===1 && S::$tables['phone_shop_goods_sku'][30]['stock']===1,'no inventory changes');
    S::$config[100005]['enabled']=1;
    S::$tables['phone_shop_goods'][21]=['goods_id'=>21,'site_id'=>100005,'is_proxy'=>0,'status'=>1];
    foreach ([31=>'A',32=>'B'] as $id=>$name) S::$tables['phone_shop_goods_sku'][$id]=['sku_id'=>$id,'site_id'=>100005,'goods_id'=>21,'sku_name'=>$name,'price'=>2000,'sale_price'=>2000,'erp_asset_id'=>0];
    Db::transaction(fn()=>(new Writer())->finish(100005,21,[['sku_id'=>0,'spec_name'=>'A','pricing_base_price'=>2000],['sku_id'=>0,'spec_name'=>'B','pricing_base_price'=>3000]],[],true));
    check((float)S::$tables['phone_shop_goods_sku'][31]['price']===2200.0 && (float)S::$tables['phone_shop_goods_sku'][32]['price']===3200.0,'new multi SKU with temporary zero id computes each baseline');
    echo "PASS $count pricing assertions; no real database writes\n";
}
