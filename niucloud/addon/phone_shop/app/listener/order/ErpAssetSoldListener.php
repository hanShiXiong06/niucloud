<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 监听 ERP 出库回流，建商城订单 / 同步商品状态
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\listener\order;

use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\service\core\order\CoreOfflineSaleService;
use app\service\core\site\CoreSiteService;
use app\model\site\Site;
use think\facade\Log;
use think\facade\Db;

/**
 * 监听 ERP 域事件(ErpDomainEvent)：
 *   - erp.asset.sold.v1     出库成交 → 建商城订单 + 商品置已售/锁定 + 推送(后续)
 *   - erp.asset.returned.v1 ERP退回 → 确认收回后保留原单并恢复上架；退款未收货不恢复库存。
 * 与 hsx_recycle 共享该事件；故障返回失败，由ERP持久化事件记录和重试。
 */
class ErpAssetSoldListener
{
    public function handle(array $event): array
    {
        $consumer = 'phone_shop.erp_asset_state';
        try {
            $name = (string) ($event['event_name'] ?? '');
            if (!in_array($name, ['erp.asset.sold.v1', 'erp.asset.returned.v1'], true)) {
                return ['consumer' => $consumer, 'status' => 'ignored', 'skipped' => true];
            }
            $siteId = (int)($event['site_id'] ?? 0);
            $addons = $siteId > 0 ? (array)(new CoreSiteService())->getAddonKeysBySiteId($siteId) : [];
            $site = $siteId > 0 ? (new Site())->where('site_id', $siteId)->findOrEmpty() : null;
            $siteApp = $site && !$site->isEmpty() && is_array($site->app) ? $site->app : [];
            $addons = array_unique(array_merge($addons, $siteApp));
            if (!in_array('phone_shop', (array)$addons, true)) {
                return ['consumer' => $consumer, 'status' => 'ignored', 'skipped' => true, 'reason' => 'addon_not_enabled_for_site'];
            }
            $result = Db::transaction(fn() => $name === 'erp.asset.sold.v1' ? $this->onSold($event) : $this->onReturned($event));
            if (!empty($result['error'])) {
                return array_merge(['consumer' => $consumer, 'status' => 'failed'], $result);
            }
            if (!empty($result['skipped'])) {
                return array_merge(['consumer' => $consumer, 'status' => 'rejected'], $result);
            }
            return array_merge(['consumer' => $consumer, 'status' => 'processed'], $result);
        } catch (\Throwable $e) {
            Log::write('[phone_shop] ERP出库回流失败: ' . $e->getMessage());
            return ['consumer' => $consumer, 'status' => 'failed', 'error' => true, 'message' => $e->getMessage()];
        }
    }

    /** 出库成交 → 建商城订单 */
    protected function onSold(array $event): array
    {
        $p = (array) ($event['payload'] ?? []);
        $assetId = (int) ($p['asset_id'] ?? $event['aggregate_id'] ?? 0);
        $siteId = (int)($event['site_id'] ?? 0);
        $settleMode = (string) ($p['settle_mode'] ?? '');
        if ($assetId <= 0) {
            Log::write('[phone_shop] 出库回流跳过 no_asset_id settle=' . $settleMode);
            return ['skipped' => true, 'reason' => 'no_asset_id'];
        }

        $assetState = null;
        foreach ((array)event('PhoneShopOrderReturnContext', ['site_id' => $siteId, 'action' => 'asset_state', 'asset_id' => $assetId, 'lock' => true]) as $response) {
            if (($response['provider'] ?? '') === 'hsx_erp') $assetState = $response['asset'] ?? null;
        }
        if ($assetState === null || (int)($p['sale_order_id'] ?? 0) <= 0) {
            throw new \core\exception\CommonException('ERP出库校验缺少原销售单或设备状态，未覆盖商城库存');
        }
        if (($assetState['status'] ?? '') !== 'sold' || (int)$assetState['sale_order_id'] !== (int)$p['sale_order_id']) {
            return ['ok' => true, 'stale' => true, 'message' => '该出库通知已过期，设备退回或后续销售状态未被覆盖'];
        }

        $sku = (new GoodsSku())->where([['site_id', '=', $siteId], ['erp_asset_id', '=', $assetId]])->findOrEmpty();
        if ($sku->isEmpty()) {
            Log::write('[phone_shop] 出库回流跳过 no_mall_goods(该机未在商城上架) asset_id=' . $assetId . ' settle=' . $settleMode);
            return ['skipped' => true, 'reason' => 'no_mall_goods'];
        }

        // 代下单收银台(build_mall_order=false)：纯 ERP 出库,商城不建订单,只把商品下架。
        // 已售→下架+库存清零；挂账也退出可售，实际退回后由 returnSaleItemByAsset 恢复上架。
        $buildMallOrder = !array_key_exists('build_mall_order', $p) || (bool) $p['build_mall_order'];
        if (!$buildMallOrder) {
            $resultStatus = ((string) ($p['result_status'] ?? 'sold')) === 'locked' ? 'locked' : 'sold';
            $goodsUpdate = ['sale_status' => $resultStatus, 'update_time' => time()];
            if ($resultStatus === 'sold') $goodsUpdate['status'] = 0; // 下架
            (new Goods())->where([['site_id', '=', $siteId], ['goods_id', '=', (int)$sku['goods_id']]])->update($goodsUpdate);
            (new GoodsSku())->where([['site_id', '=', $siteId], ['sku_id', '=', (int)$sku['sku_id']]])->update(['stock' => 0]);
            // 纯 ERP 出库不会经过商城建单服务，也必须把主站货源状态同步到关注从站。
            // sold 直接下架；locked 刷新副本的锁定状态，使其退出从站在售列表。
            try {
                (new \addon\phone_shop\app\service\core\agent\GoodsSyncService())->syncGoods(
                    (int)$sku['goods_id'],
                    $siteId,
                    $resultStatus === 'sold' ? 0 : 1
                );
            } catch (\Throwable $e) {
                Log::write('[phone_shop] ERP出库联动从站失败: ' . $e->getMessage());
            }
            Log::write('[phone_shop] 出库回流(纯ERP·不建单)下架商品 asset_id=' . $assetId . ' sku_id=' . $sku['sku_id'] . ' status=' . $resultStatus);
            // 挂账展示单经 ERP 收款成交(sold)后,把对应商城展示单标记完成(只动展示状态)
            if ($resultStatus === 'sold') {
                (new CoreOfflineSaleService())->finalizeByOutbound(
                    (int) ($event['site_id'] ?? $sku['site_id']),
                    (string) ($p['outbound_no'] ?? '')
                );
            }
            return ['ok' => true, 'mall_order' => false, 'sku_id' => (int) $sku['sku_id'], 'sale_status' => $resultStatus];
        }

        $memberId = (int) ($p['member_id'] ?? 0);
        if ($memberId <= 0) {
            Log::write('[phone_shop] 出库回流跳过 no_member asset_id=' . $assetId . ' settle=' . $settleMode);
            return ['skipped' => true, 'reason' => 'no_member'];
        }

        $res = (new CoreOfflineSaleService())->createSaleOrder([
            'site_id'          => (int) ($event['site_id'] ?? $sku['site_id']),
            'member_id'        => $memberId,
            'sku_id'           => (int) $sku['sku_id'],
            'sale_price'       => (float) ($p['sale_price'] ?? 0),
            'payment_mode'     => $settleMode === 'now' ? 'offline_cash' : 'offline_credit',
            'buyer_type'       => 'b', // ERP 销售（同行/商城零售）
            'source_device_id' => (int) ($p['source_device_id'] ?? 0),
            'staff_id'         => (int) ($p['staff_id'] ?? 0),
            'outbound_no'      => (string) ($p['outbound_no'] ?? ''),
            'result_status'    => (string) ($p['result_status'] ?? 'sold'),
        ]);
        Log::write('[phone_shop] 出库回流建单结果 asset_id=' . $assetId . ' settle=' . $settleMode . ' result=' . json_encode($res, JSON_UNESCAPED_UNICODE));
        return $res;
    }

    /** ERP 退回 → 保留原订单明细，已收回设备恢复上架，不重新记销售账。 */
    protected function onReturned(array $event): array
    {
        $p = (array) ($event['payload'] ?? []);
        $assetId = (int) ($p['asset_id'] ?? $event['aggregate_id'] ?? 0);
        if ($assetId <= 0) return ['skipped' => true];

        return (new CoreOfflineSaleService())->returnSaleItemByAsset(
            (int)($event['site_id'] ?? 0),
            $assetId,
            (string)($p['outbound_no'] ?? ''),
            $p
        );
    }
}
