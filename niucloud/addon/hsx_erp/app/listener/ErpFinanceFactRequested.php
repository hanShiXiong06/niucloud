<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\ErpFinanceFactService;
use core\exception\CommonException;

/** 接收维修及其它插件提出的应收/应付业务事实请求。 */
class ErpFinanceFactRequested
{
    public function handle($event): array
    {
        if (!is_array($event)) {
            throw new CommonException('ERP财务事实事件格式不正确');
        }
        return (new ErpFinanceFactService())->consume($event);
    }
}
