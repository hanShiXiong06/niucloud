<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpOfflineSaleReturnService;

/** 同步调用；商城与 ERP 共用事务，任意一端失败都不关闭订单、不恢复库存。 */
class ErpOfflineSaleReturnRequested
{
    public function handle(array $data): array
    {
        return (new ErpOfflineSaleReturnService())->handle($data);
    }
}
