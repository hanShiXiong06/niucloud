<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\listener;

use think\facade\Db;

/** Internal, site-scoped identity lookup for optional ERP adapters. */
final class ErpMasterDataLookupRequested
{
    public function handle(array $request = []): array
    {
        $tables = ['supplier' => 'erp_party', 'customer' => 'erp_party', 'asset_material' => 'erp_asset',
            'product' => 'erp_quantity_product', 'warehouse' => 'erp_warehouse'];
        $table = $tables[$request['kind'] ?? ''] ?? '';
        $site = (int)($request['site_id'] ?? 0);
        $id = (int)($request['local_id'] ?? 0);
        if ($table === '' || $site <= 0 || $id <= 0) return ['provider' => 'hsx_erp', 'found' => false];
        return ['provider' => 'hsx_erp', 'found' => Db::name($table)->where([['site_id', '=', $site], ['id', '=', $id]])->count() > 0];
    }
}
