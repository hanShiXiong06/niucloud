<?php
declare(strict_types=1);
namespace addon\hsx_recycle\app\adminapi\controller\order;

use addon\hsx_recycle\app\service\core\audit\OperationAuditService;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminController;
use core\exception\CommonException;

final class OperationAudit extends BaseAdminController
{
    public function lists()
    {
        // Framework skips unknown menu routes until menus are refreshed; never fall through on that case.
        if(!in_array('recycle/operation_logs',(new AuthService())->getAuthApiList()['get']??[],true))
            throw new CommonException('没有查看跨系统业务操作记录的权限');
        $input=$this->request->params([['order_id',0],['source',''],['limit',20],['cursor','']]);
        return success((new OperationAuditService())->page((int)$this->request->siteId(),$input));
    }
}
