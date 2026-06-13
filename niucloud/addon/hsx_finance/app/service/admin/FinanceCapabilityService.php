<?php
declare(strict_types=1);

namespace addon\hsx_finance\app\service\admin;

use core\base\BaseAdminService;

/**
 * 财务-能力检测(模式感知)
 *
 * 折账依赖业务插件: 应付来自【回收】, 应收来自【ERP/销售(中台)】。
 * 检测不到这些插件时, 前端不应展示折账/结算按钮, 避免给客户"一会能折一会不能折"的困惑。
 * 仅用 class_exists 探测, 不跨插件读表、不强依赖。
 */
class FinanceCapabilityService extends BaseAdminService
{
    /** 回收插件在场(产出应付) */
    public function recycleConnected(): bool
    {
        return class_exists('\addon\hsx_recycle\app\service\admin\order\RecycleDeviceService');
    }

    /** ERP/中台在场(产出应收/销售事实) */
    public function erpConnected(): bool
    {
        return class_exists('\addon\hsx_erp\app\service\admin\ErpWarehouseService');
    }

    /** 是否具备折账能力: 两侧业务插件都在 */
    public function canOffset(): bool
    {
        return $this->recycleConnected() && $this->erpConnected();
    }

    public function getCapability(): array
    {
        $recycle = $this->recycleConnected();
        $erp = $this->erpConnected();
        $missing = [];
        if (!$recycle) { $missing[] = '回收'; }
        if (!$erp) { $missing[] = 'ERP'; }
        return [
            'recycle_connected' => $recycle,
            'erp_connected'     => $erp,
            'can_offset'        => $recycle && $erp,
            'can_settle'        => $recycle && $erp, // 结算/折账同口径: 需业务插件产出应付应收
            'missing'           => $missing,         // 缺哪些插件(给前端提示)
        ];
    }
}
