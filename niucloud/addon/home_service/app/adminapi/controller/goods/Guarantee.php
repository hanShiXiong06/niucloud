<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\adminapi\controller\goods;

use addon\home_service\app\service\admin\goods\GuaranteeService;
use core\base\BaseAdminController;

/**
 * 服务保障管理
 * Class Guarantee
 * @description 服务保障
 * @package addon\home_service\app\adminapi\controller\goods
 */
class Guarantee extends BaseAdminController
{
    /**
     * 服务保障列表
     * @description 服务保障列表
     * @return void
     */
    public function lists()
    {
        $data = $this->request->params([
            ['guarantee_title', ''],
        ]);
        return success((new GuaranteeService())->getPage($data));
    }


    /**
     * 添加服务保障
     * @description 添加服务保障
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["guarantee_title", ""],
            ["guarantee_image", ""],
            ["guarantee_content", ""],
        ]);
        return success('SUCCESS', (new GuaranteeService())->add($data));
    }


    /**
     * 服务保障编辑
     * @return \think\Response
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ["guarantee_title", ""],
            ["guarantee_image", ""],
            ["guarantee_content", ""],
        ]);
        return success('EDIT_SUCCESS', (new GuaranteeService())->edit($id, $data));
    }


    /**
     * 服务保障详情
     * @description 服务保障详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new GuaranteeService())->getInfo($id));
    }

    /**
     * 删除服务保障
     * @description 删除服务保障
     * @param int $id
     * @return \think\Response
     */
    public function del(int $id)
    {
        return success('DELETE_SUCCESS', (new GuaranteeService())->del($id));
    }
}