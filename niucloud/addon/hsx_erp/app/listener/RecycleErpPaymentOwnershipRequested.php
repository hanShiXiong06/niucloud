<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpRecycleDeviceIdentityService;
use core\exception\CommonException;

/** 只读查询已存在的回收设备财务归属，不依赖当前联动开关，也不创建任何业务记录。 */
class RecycleErpPaymentOwnershipRequested
{
    public function handle($event): array
    {
        if (!is_array($event) || (int)($event['site_id'] ?? 0) <= 0 || !is_array($event['device_ids'] ?? null)) {
            throw new CommonException('回收付款归属查询参数不正确');
        }
        return ['consumer' => 'hsx_erp', 'status' => 'processed',
            'devices' => $this->identityService()->paymentOwnership((int)$event['site_id'], $event['device_ids'])];
    }

    protected function identityService(): ErpRecycleDeviceIdentityService
    {
        return new ErpRecycleDeviceIdentityService();
    }
}
