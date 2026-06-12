<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpWarehouseService;

/**
 * 仓库列表提供者（同步查询事件 GetErpWarehouseList 的应答方）
 *
 * 回收等插件在需要展示"目标仓库"时，发 event('GetErpWarehouseList', ['site_id'=>...])，
 * 本监听器返回 ERP 的启用仓库（含库位）选项；ERP 未安装时无人应答，调用方自动回退。
 * 故障隔离：任何异常都返回空数组，绝不影响调用方。
 *
 * Class WarehouseListProvider
 * @package addon\hsx_erp\app\listener
 */
class WarehouseListProvider
{
    public function handle(array $params): array
    {
        try {
            return (new ErpWarehouseService())->getOptions();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
