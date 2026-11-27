<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\service\admin\ErpLedgerService;
use addon\hsx_erp\app\service\admin\ErpSalesPriceService;
use core\exception\CommonException;
use think\facade\Db;

final class PhoneShopSalesPriceChanged
{
    public function handle(array $event): array
    {
        if (!Db::connect()->getPdo()->inTransaction()) throw new CommonException('价格同步必须与商城保存处于同一事务');
        $site = (int)$event['site_id'];
        $asset = ErpAsset::where('site_id', $site)->where('id', (int)$event['erp_asset_id'])->lock(true)->findOrEmpty();
        if ($asset->isEmpty()) throw new CommonException('关联 ERP 设备不存在，本次价格未保存');
        $imei = trim((string)$event['imei']);
        if ($imei === '' || !in_array($imei, array_filter([trim((string)$asset->imei), trim((string)($asset->sn ?? ''))]), true)) {
            throw new CommonException('商城与 ERP 设备串号不一致，请先核对关联，本次价格未保存');
        }
        if ((string)$asset->status !== 'in_stock') throw new CommonException('ERP 设备不在库，不能修改销售价格；已有订单金额保持不变');
        $old = $asset->toArray();
        $save = ['retail_price' => round((float)$event['retail_price'], 2), 'update_at' => time()];
        if ($event['base_price'] !== null) $save['estimate_sale_price'] = round((float)$event['base_price'], 2);
        $asset->save($save);
        ErpSalesPriceService::sync($site, $asset->toArray());
        ErpLedgerService::forSite($site)->asset(['asset_id' => (int)$asset->id, 'action' => 'mall_price_sync',
            'source_type' => 'phone_shop', 'source_id' => (int)$event['sku_id'], 'before_status' => $old['status'], 'after_status' => $old['status'],
            'remark' => '商城改价同步；普通售价 ' . $old['retail_price'] . ' → ' . $save['retail_price'] . '；不影响已有订单及成本',
            'extra' => ['before' => array_intersect_key($old, $save), 'after' => $save]]);
        return ['consumer' => 'hsx_erp.sales_price', 'synced' => true];
    }
}
