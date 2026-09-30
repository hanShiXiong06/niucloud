<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\support\GoodsSource;
use core\exception\CommonException;

/** 必须在商品保存事务内使用。统一列表、编辑、新建的定价落库和 ERP 回写。 */
final class CoreGoodsPriceWriteService
{
    public function capture(int $siteId, int $goodsId): array
    {
        $goods = Goods::where('site_id', $siteId)->where('goods_id', $goodsId)->field('goods_id,site_id,source')->lock(true)->findOrEmpty();
        if ($goods->isEmpty()) throw new CommonException('商品不存在或不属于当前站点');
        $skus = GoodsSku::where('site_id', $siteId)->where('goods_id', $goodsId)->lock(true)->select()->toArray();
        if (!GoodsSource::isLocal($siteId, $goods->toArray()) && array_filter($skus, static fn(array $sku): bool => (int)($sku['erp_asset_id'] ?? 0) > 0)) {
            throw new CommonException('代理商品不能改写来源站点 ERP，请先核对错误的设备关联');
        }
        return array_column($skus, null, 'sku_id');
    }

    public function finish(int $siteId, int $goodsId, array $inputs, array $before, bool $isNew = false, bool $active = false, bool $fromErp = false): void
    {
        $service = new CoreTierPricingService();
        $policy = $service->policy($siteId);
        $skus = GoodsSku::where('site_id', $siteId)->where('goods_id', $goodsId)->lock(true)->order('sku_id asc')->select();
        foreach ($before as $old) {
            if ((int)($old['erp_asset_id'] ?? 0) > 0 && !in_array((int)$old['sku_id'], array_map('intval', $skus->column('sku_id')), true)) {
                throw new CommonException('已关联 ERP 的设备不能通过切换规格删除，请保留原规格');
            }
        }
        foreach ($skus as $sku) {
            $old = $before[(int)$sku->sku_id] ?? [];
            $input = null;
            foreach ($inputs as $row) {
                if ((int)($row['sku_id'] ?? 0) === (int)$sku->sku_id
                    || ($isNew && count($inputs) === 1)
                    || (empty($row['sku_id']) && (string)($row['spec_name'] ?? '') === (string)$sku->sku_name)) { $input = $row; break; }
            }
            if ($input === null) continue;
            if ((int)($old['erp_asset_id'] ?? 0) > 0 && trim((string)$sku->sku_no) !== trim((string)$old['sku_no'])) {
                throw new CommonException('关联 ERP 的设备串号不能在商城改写，请先核对设备关联');
            }
            if ((int)$policy['enabled'] === 1) {
                $base = $input['pricing_base_price'] ?? null;
                if ($base === '') throw new CommonException('请填写基准售价，不能留空；系统不会自动沿用被清空的价格');
                if ($base === null) {
                    if ($isNew) $base = $input['price'] ?? 0;
                    elseif (isset($input['price']) && abs((float)$input['price'] - (float)($old['price'] ?? 0)) > .001) {
                        throw new CommonException('已开启自动加价，请刷新页面，在“基准售价”输入最低销售价格');
                    } else $base = CoreTierPricingService::baseFromSku($old, $policy);
                }
                if ($base === null || $base === '') throw new CommonException('这件商品尚未确认基准售价，请填写最高等级会员的最低销售价格');
                $quote = $service->quote($siteId, $base, $policy);
                if ($active && (abs((float)($old['price'] ?? 0) - $quote['retail_price']) > .001
                    || CoreTierPricingService::snapshot($old['member_price'] ?? '') !== $quote['member_price'])) {
                    throw new CommonException('商品正在参加营销活动，请先结束活动再调整基准售价');
                }
                $snapshot = CoreTierPricingService::snapshot($sku->device_snapshot ?? '');
                $snapshot['_tier_pricing'] = ['base_price' => $quote['base_price'], 'base_level_no' => $quote['base_level_no'], 'updated_at' => time()];
                $save = ['member_price' => json_encode($quote['member_price'], JSON_UNESCAPED_UNICODE),
                    'device_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
                if (!$active) $save = array_merge($save, ['price' => $quote['retail_price'], 'sale_price' => $quote['retail_price']]);
                $sku->save($save);
            } elseif (isset(CoreTierPricingService::snapshot($sku->device_snapshot ?? '')['_tier_pricing'])) {
                $snapshot = CoreTierPricingService::snapshot($sku->device_snapshot ?? '');
                unset($snapshot['_tier_pricing']);
                $sku->save(['device_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE)]);
            }
            if (!$fromErp && (int)($sku->erp_asset_id ?? 0) > 0 && (abs((float)($old['price'] ?? 0) - (float)$sku->price) > .001
                || CoreTierPricingService::snapshot($old['member_price'] ?? '') !== CoreTierPricingService::snapshot($sku->member_price))) {
                $this->syncToErp($siteId, $sku->toArray(), $policy);
            }
        }
        if ((int)$policy['enabled'] === 1) Goods::where('site_id', $siteId)->where('goods_id', $goodsId)->update(['member_discount' => 'fixed_price']);
    }

    public function syncToErp(int $siteId, array $sku, ?array $policy = null): void
    {
        $goods = Goods::where('site_id', $siteId)->where('goods_id', (int)($sku['goods_id'] ?? 0))
            ->field('goods_id,site_id,source')->lock(true)->findOrEmpty();
        if ($goods->isEmpty()) throw new CommonException('商品不存在或不属于当前站点');
        if (!GoodsSource::isLocal($siteId, $goods->toArray())) {
            throw new CommonException('代理商品不能改写来源站点 ERP，请先核对错误的设备关联');
        }
        $policy = $policy ?? (new CoreTierPricingService())->policy($siteId);
        $base = CoreTierPricingService::baseFromSku($sku, $policy);
        // 关闭自动规则时保留原同行价；只有明确最高等级会员价才回写同行价。
        $ack = false;
        foreach ((array)event('PhoneShopSalesPriceChanged', [
            'site_id' => $siteId, 'erp_asset_id' => (int)$sku['erp_asset_id'], 'sku_id' => (int)$sku['sku_id'],
            'imei' => trim((string)$sku['sku_no']), 'retail_price' => (float)$sku['price'], 'base_price' => $base,
        ]) as $result) {
            if (is_array($result) && ($result['consumer'] ?? '') === 'hsx_erp.sales_price' && ($result['synced'] ?? false)) $ack = true;
        }
        if (!$ack) throw new CommonException('ERP 改价服务未响应，本次价格未保存，请更新配套 ERP 插件后重试');
    }
}
