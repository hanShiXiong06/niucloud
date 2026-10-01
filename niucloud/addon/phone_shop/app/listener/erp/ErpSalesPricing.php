<?php
declare(strict_types=1);
namespace addon\phone_shop\app\listener\erp;

use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\service\admin\goods\GoodsService;
use addon\phone_shop\app\service\core\goods\CoreGoodsPriceWriteService;
use addon\phone_shop\app\service\core\goods\CoreGoodsChangeLogService;
use addon\phone_shop\app\service\core\goods\CoreTierPricingService;
use addon\phone_shop\app\support\GoodsSource;
use core\exception\CommonException;
use think\facade\Db;

final class ErpSalesPricing
{
    public function handle(array $event): array
    {
        $site = (int)($event['site_id'] ?? 0);
        $service = new CoreTierPricingService();
        $policy = $service->policy($site);
        $action = (string)($event['action'] ?? 'policy');
        if ($action === 'policy') return array_merge($policy, ['provider' => 'phone_shop']);
        if ($action === 'quote') return array_merge($service->quote($site, $event['base_price'], $policy), ['provider' => 'phone_shop']);
        if ($action !== 'sync') throw new CommonException('不支持的销售价格操作');
        if (!Db::connect()->getPdo()->inTransaction()) throw new CommonException('ERP 价格同步必须在事务内执行');
        $asset = (array)$event['asset'];
        $assetId = (int)$asset['id'];
        $skus = GoodsSku::where('site_id', $site)->where('erp_asset_id', $assetId)->lock(true)->select()->toArray();
        $goodsIds = [];
        foreach ($skus as $row) {
            $goods = Db::name('phone_shop_goods')->where('site_id', $site)->where('goods_id', (int)$row['goods_id'])->find();
            if (!$goods || (int)($goods['delete_time'] ?? 0) > 0) continue;
            if (!GoodsSource::isLocal($site, $goods)) throw new CommonException('当前设备误关联了代理商品，请先核对货源关联，未修改价格');
            $imei = trim((string)$row['sku_no']);
            if ($imei === '' || !in_array($imei, array_filter([trim((string)$asset['imei']), trim((string)($asset['sn'] ?? ''))]), true)) {
                throw new CommonException('ERP 与商城设备串号不一致，未修改价格');
            }
            $active = (new GoodsService())->getActiveGoodsCount((int)$row['goods_id'], 0, [], $site) > 0;
            if ($active && abs((float)$row['price'] - (float)$asset['retail_price']) > .001) throw new CommonException('商城商品正在参加营销活动，请结束活动后再改价');
            $writer = new CoreGoodsPriceWriteService();
            $before = $writer->capture($site, (int)$row['goods_id']);
            $auditBefore = (new CoreGoodsChangeLogService())->capture($site, (int)$row['goods_id']);
            $input = ['sku_id' => (int)$row['sku_id'], 'price' => (float)$asset['retail_price'], 'pricing_base_price' => (float)$asset['estimate_sale_price']];
            $save = ['price' => $input['price'], 'sale_price' => $input['price']];
            if ($active) unset($save['sale_price']);
            if ((int)$policy['enabled'] !== 1 && (int)$policy['base_level_no'] > 0 && (float)$asset['estimate_sale_price'] > 0) {
                $prices = CoreTierPricingService::snapshot($row['member_price'] ?? '');
                $prices['level_' . (int)$policy['base_level_no']] = number_format((float)$asset['estimate_sale_price'], 2, '.', '');
                if ((float)$asset['estimate_sale_price'] > $input['price']) throw new CommonException('同行价不能高于普通售价，请一并核对销售价格');
                $save['member_price'] = json_encode($prices, JSON_UNESCAPED_UNICODE);
                if ($active && $prices !== CoreTierPricingService::snapshot($row['member_price'] ?? '')) throw new CommonException('商品参与营销活动，暂不能调整会员价格');
                Db::name('phone_shop_goods')->where('site_id', $site)->where('goods_id', $row['goods_id'])->update(['member_discount' => 'fixed_price']);
            }
            GoodsSku::where('site_id', $site)->where('sku_id', (int)$row['sku_id'])->update($save);
            $writer->finish($site, (int)$row['goods_id'], [$input], $before, false, $active, true);
            (new CoreGoodsChangeLogService())->record($site, (int)$row['goods_id'], $auditBefore, 'erp_price');
            $goodsIds[] = (int)$row['goods_id'];
        }
        // 交接中尚未建品的货源也要更新，否则运营后上架会拿到旧价格。
        Db::name('phone_shop_device_intake')->where('site_id', $site)->where('erp_asset_id', $assetId)
            ->update(['sale_price' => (float)$asset['retail_price'], 'peer_price' => (float)$asset['estimate_sale_price'], 'update_time' => time()]);
        return ['provider' => 'phone_shop', 'enabled' => $policy['enabled'], 'synced' => true, 'goods_ids' => array_values(array_unique($goodsIds))];
    }
}
