<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\delivery;

use addon\hsx_recycle\app\service\admin\delivery\RecycleDeliveryCompanyService;
use core\base\BaseAdminController;

/**
 * 快递公司管理控制器
 * @package addon\hsx_recycle\app\adminapi\controller\delivery
 */
class Company extends BaseAdminController
{
    /**
     * 分页列表
     */
    public function pages()
    {
        $data = $this->request->params([
            ['company_name', ''],
            ['status', ''],
            ['electronic_sheet_switch', ''],
        ]);
        return success((new RecycleDeliveryCompanyService())->getPage($data));
    }

    /**
     * 全量列表（下拉用）
     */
    public function lists()
    {
        $data = $this->request->params([
            ['electronic_sheet_switch', ''],
            ['status', ''],
        ]);
        return success((new RecycleDeliveryCompanyService())->getList($data));
    }

    /**
     * 某服务商下可用（已绑定且出面单）的公司列表
     */
    public function providerCompanies()
    {
        $provider = (string)$this->request->param('provider', '');
        return success((new RecycleDeliveryCompanyService())->getProviderCompanies($provider));
    }

    /**
     * 详情
     */
    public function info($id)
    {
        return success((new RecycleDeliveryCompanyService())->getInfo((int)$id));
    }

    /**
     * 新增
     */
    public function add()
    {
        $data = $this->request->params([
            ['company_name', ''],
            ['logo', ''],
            ['url', ''],
            ['express_code', ''],
            ['kuaidi100_com', ''],
            ['yisu_product_code', ''],
            ['electronic_sheet_switch', 0],
            ['exp_type', []],
            ['print_style', []],
            ['sort', 0],
            ['status', 1],
        ]);
        return success((new RecycleDeliveryCompanyService())->add($data));
    }

    /**
     * 编辑
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ['company_name', ''],
            ['logo', ''],
            ['url', ''],
            ['express_code', ''],
            ['kuaidi100_com', ''],
            ['yisu_product_code', ''],
            ['electronic_sheet_switch', 0],
            ['exp_type', []],
            ['print_style', []],
            ['sort', 0],
            ['status', 1],
        ]);
        return success((new RecycleDeliveryCompanyService())->edit((int)$id, $data));
    }

    /**
     * 删除
     */
    public function del($id)
    {
        return success((new RecycleDeliveryCompanyService())->del((int)$id));
    }

    /**
     * 一键导入常用快递公司
     */
    public function importPresets()
    {
        $count = (new RecycleDeliveryCompanyService())->importPresets();
        return success(['count' => $count]);
    }
}
