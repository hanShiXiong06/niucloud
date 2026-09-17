<?php
declare(strict_types=1);
// 只允许显式调用本机开发库。使用独立测试站点，所有测试记录最终 ROLLBACK。
if (!in_array('--local-rollback', $argv, true)) exit("需加 --local-rollback；禁止在生产执行\n");
chdir(dirname(__DIR__) . '/niucloud');
require 'vendor/autoload.php';
(new think\App())->initialize();
use think\facade\{Db, Event, Cache};
use addon\phone_shop\app\service\core\goods\{CoreTierPricingService, CoreGoodsPriceWriteService};
use addon\hsx_erp\app\service\admin\ErpSalesPriceService;
use addon\phone_shop\app\service\admin\goods\GoodsService;
$host = (string)config('database.connections.mysql.hostname');
if (!in_array($host, ['localhost','127.0.0.1','::1'], true) || PHP_OS_FAMILY !== 'Darwin') throw new RuntimeException('仅限 macOS 本地测试数据库');
$site = 19990917;
$tables = ['sys_config','member_level','erp_asset','erp_asset_ledger','phone_shop_goods','phone_shop_goods_sku','phone_shop_goods_stat','phone_shop_device_intake'];
foreach ($tables as $table) {
    if (Db::name($table)->where('site_id', $site)->count() > 0) throw new RuntimeException('测试站点已被占用，不执行');
    $name=Db::name($table)->getTable();
    $engine=Db::query('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',[$name]);
    if (($engine[0]['ENGINE'] ?? '') !== 'InnoDB') throw new RuntimeException('非事务表，停止测试');
}
request()->siteId($site);
foreach (['HsxErpSalesPricing','PhoneShopSalesPriceChanged','GetGoodsJoinInfo','AfterGoodsEdit','PhoneShopGoodsSaleableChanged','TreasureDataSync'] as $name) Event::remove($name);
Event::listen('HsxErpSalesPricing', \addon\phone_shop\app\listener\erp\ErpSalesPricing::class);
Event::listen('PhoneShopSalesPriceChanged', \addon\hsx_erp\app\listener\PhoneShopSalesPriceChanged::class);
$check = static function($ok,$message) { if(!$ok) throw new RuntimeException($message); echo 'PASS '.$message.PHP_EOL; };
Db::startTrans();
try {
    Db::name('member_level')->insert(['site_id'=>$site,'level_no'=>1,'level_name'=>'测试V1','growth'=>1,'status'=>1]);
    (new CoreTierPricingService())->save($site,['enabled'=>1,'base_level_no'=>1,'rules'=>[0=>['type'=>'fixed','value'=>200]]]);
    $asset=Db::name('erp_asset')->insertGetId(['site_id'=>$site,'asset_no'=>'PRICE-ROLLBACK-'.time(),'imei'=>'999999999999991','model'=>'价格联动回滚测试','status'=>'in_stock','sale_target'=>'mall','listing_status'=>'listed','retail_price'=>2200,'estimate_sale_price'=>2000,'total_cost'=>1600]);
    $goods=Db::name('phone_shop_goods')->insertGetId(['site_id'=>$site,'goods_name'=>'价格联动回滚测试','condition_grade'=>'测试','member_discount'=>'fixed_price','status'=>1,'is_proxy'=>0]);
    $sku=Db::name('phone_shop_goods_sku')->insertGetId(['site_id'=>$site,'goods_id'=>$goods,'erp_asset_id'=>$asset,'sku_no'=>'999999999999991','price'=>2200,'sale_price'=>2200,'member_price'=>'{"level_1":"2000.00"}','device_snapshot'=>'{"check_meta":{"kept":true},"_tier_pricing":{"base_price":2000}}','stock'=>1]);
    Db::name('phone_shop_device_intake')->insert(['site_id'=>$site,'erp_asset_id'=>$asset,'imei'=>'999999999999991','sale_price'=>2200,'peer_price'=>2000,'goods_id'=>$goods]);
    $writer = new CoreGoodsPriceWriteService();
    $before = $writer->capture($site,$goods);
    $writer->finish($site,$goods,[['sku_id'=>$sku,'price'=>2200,'pricing_base_price'=>2100]],$before);
    $a=Db::name('erp_asset')->where('site_id',$site)->where('id',$asset)->find();
    $check((float)$a['retail_price']===2300.0 && (float)$a['estimate_sale_price']===2100.0,'真实 ORM：商城改价回写 ERP 2300 / 2100');
    $writer->finish($site,$goods,[['sku_id'=>$sku,'price'=>2300,'pricing_base_price'=>2100]],$writer->capture($site,$goods));
    $check((float)Db::name('phone_shop_goods_sku')->where('sku_id',$sku)->value('price')===2300.0,'真实 ORM：重复保存不累计加价');
    $fields=ErpSalesPriceService::fields($site,2200);
    Db::name('erp_asset')->where('site_id',$site)->where('id',$asset)->update($fields);
    ErpSalesPriceService::sync($site,array_merge($a,$fields));
    $s=Db::name('phone_shop_goods_sku')->where('sku_id',$sku)->find();
    $check((float)$s['price']===2400.0 && json_decode($s['member_price'],true)['level_1']==='2200.00','真实 ORM：ERP 改价回写商城 2400 / 2200');
    $check(json_decode($s['device_snapshot'],true)['check_meta']['kept']===true,'质检快照保留');
    $check((float)Db::name('erp_asset')->where('id',$asset)->value('total_cost')===1600.0,'设备成本未改动');
    $check((float)Db::name('phone_shop_device_intake')->where('erp_asset_id',$asset)->where('site_id',$site)->value('sale_price')===2400.0,'交接货源价格同步');
    $goodsService = new GoodsService();
    $goodsService->editGoodsListPrice(['goods_id'=>$goods,'sku_list'=>[['sku_id'=>$sku,'price'=>2400,'cost_price'=>0,'market_price'=>0,'pricing_base_price'=>2300]]]);
    $check((float)Db::name('erp_asset')->where('id',$asset)->value('estimate_sale_price')===2300.0,'真实列表改价入口回写 ERP');
    $payload = [
        'goods_name'=>'仅用于回滚的定价测试','sub_title'=>'','goods_type'=>'real','goods_cover'=>'test.png','goods_image'=>'test.png','goods_video'=>'',
        'goods_category'=>[],'goods_desc'=>'','brand_id'=>0,'label_ids'=>[],'service_ids'=>[],'unit'=>'台','stock'=>1,'virtual_sale_num'=>0,
        'is_limit'=>0,'limit_type'=>0,'max_buy'=>0,'min_buy'=>0,'is_gift'=>0,'status'=>1,'sort'=>0,'attr_ids'=>'','attr_format'=>'',
        'delivery_type'=>'express','is_free_shipping'=>1,'fee_type'=>'fixed','delivery_money'=>0,'delivery_template_id'=>0,'supplier_id'=>0,
        'member_discount'=>'','poster_id'=>0,'form_id'=>0,'diy_detail_id'=>0,'spec_type'=>'single','sku_no'=>'','price'=>2000,'pricing_base_price'=>2000,
        'market_price'=>0,'cost_price'=>0,'weight'=>0,'volume'=>0,'condition_grade'=>'测试','_defer_agent_sync'=>1,
    ];
    $created = $goodsService->add($payload);
    $check((float)Db::name('phone_shop_goods_sku')->where('site_id',$site)->where('goods_id',$created)->value('price')===2200.0,'真实新建入口计算普通售价');
    $payload['price']=2200; $payload['pricing_base_price']=2100;
    $goodsService->edit((int)$created,$payload);
    $createdSku = Db::name('phone_shop_goods_sku')->where('site_id',$site)->where('goods_id',$created)->find();
    $check((float)$createdSku['price']===2300.0 && json_decode($createdSku['member_price'],true)['level_1']==='2100.00','真实编辑入口计算普通和会员价格');
} finally {
    Db::rollback();
    Cache::tag('sys_config'.$site)->clear();
}
foreach ($tables as $table) $check(Db::name($table)->where('site_id',$site)->count()===0,'回滚清理 '.$table);
