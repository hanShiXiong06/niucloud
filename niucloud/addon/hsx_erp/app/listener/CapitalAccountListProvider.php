<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpCapitalAccountService;

/**
 * 资金账户列表提供者（同步查询事件 GetErpCapitalAccountList 的应答方）
 *
 * 回收等插件在打款时需要选择"从哪个户头出账"，发 event('GetErpCapitalAccountList', ['site_id'=>...])，
 * 本监听器返回 ERP 的启用资金账户（现金/微信/支付宝/银行卡等，含余额）；
 * ERP 未安装时无人应答，调用方自动回退（不显示户头选择，不影响原打款流程）。
 * 故障隔离：任何异常都返回空数组，绝不影响调用方。
 *
 * Class CapitalAccountListProvider
 * @package addon\hsx_erp\app\listener
 */
class CapitalAccountListProvider
{
    public function handle(array $params): array
    {
        try {
            $all = (new ErpCapitalAccountService())->getAll();
            // 仅返回启用账户，避免把停用户头给到打款选择
            return array_values(array_filter($all, function ($a) {
                return (int)($a['status'] ?? 1) === 1;
            }));
        } catch (\Throwable $e) {
            return [];
        }
    }
}
