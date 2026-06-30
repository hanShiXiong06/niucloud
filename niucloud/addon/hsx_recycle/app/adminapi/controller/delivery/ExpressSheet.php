<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\delivery;

use addon\hsx_recycle\app\service\admin\delivery\RecycleExpressSheetService;
use core\base\BaseAdminController;

/**
 * 电子面单模板控制器
 * @package addon\hsx_recycle\app\adminapi\controller\delivery
 */
class ExpressSheet extends BaseAdminController
{
    public function pages()
    {
        $data = $this->request->params([
            ['template_name', ''],
            ['express_company_id', ''],
            ['provider', ''],
            ['status', ''],
        ]);
        return success((new RecycleExpressSheetService())->getPage($data));
    }

    public function lists()
    {
        $data = $this->request->params([
            ['express_company_id', ''],
            ['provider', ''],
            ['status', ''],
        ]);
        return success((new RecycleExpressSheetService())->getList($data));
    }

    public function info($id)
    {
        return success((new RecycleExpressSheetService())->getInfo((int)$id));
    }

    public function payType()
    {
        return success((new RecycleExpressSheetService())->getPayType());
    }

    public function add()
    {
        return success((new RecycleExpressSheetService())->add($this->sheetParams()));
    }

    public function edit($id)
    {
        return success((new RecycleExpressSheetService())->edit((int)$id, $this->sheetParams()));
    }

    public function del($id)
    {
        return success((new RecycleExpressSheetService())->del((int)$id));
    }

    public function setDefault($id)
    {
        return success((new RecycleExpressSheetService())->setDefault((int)$id));
    }

    /**
     * 统一收集模板表单字段
     */
    private function sheetParams(): array
    {
        return $this->request->params([
            ['template_name', ''],
            ['provider', ''],
            ['express_company_id', 0],
            ['exp_type', ''],
            ['exp_type_name', ''],
            ['print_style', ''],
            ['customer_name', ''],
            ['customer_pwd', ''],
            ['send_site', ''],
            ['send_staff', ''],
            ['month_code', ''],
            ['pay_type', 1],
            ['output_type', 'IMAGE'],
            ['print_channel', 'browser'],
            ['temp_id', ''],
            ['child_temp_id', ''],
            ['back_temp_id', ''],
            ['siid', ''],
            ['is_notice', 0],
            ['status', 1],
            ['is_default', 0],
        ]);
    }
}
