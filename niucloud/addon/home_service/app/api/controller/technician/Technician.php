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

namespace addon\home_service\app\api\controller\technician;

use addon\home_service\app\service\api\technician\TechnicianService;
use core\base\BaseAdminController;


/**
 * 师傅控制器
 * Class Reserve
 * @package app\adminapi\controller
 */
class Technician extends BaseAdminController
{


    /**
     * 验证是否是师傅
     * @param int $id
     * @return \think\Response
     */
    public function checkTechnician()
    {
        return success((new TechnicianService())->checkTechnician());
    }


    /**
     * 师傅详情
     * @param int $id
     * @return \think\Response
     */
    public function info()
    {
        return success((new TechnicianService())->getInfo());
    }

    /**
     * 修改部分字段
     * @param $field
     * @return Response
     */
    public function modify($field)
    {
        $data = $this->request->params([
            ['value', ''],
            ['field', $field],
        ]);
        $data[$field] = $data['value'];
        (new TechnicianService())->modify($field, $data['value']);
        return success('MODIFY_SUCCESS');
    }


    public function edit()
    {
        $data = $this->request->params([
            ['data', []],
        ]);
        (new TechnicianService())->edit($data['data']);
        return success('MODIFY_SUCCESS');
    }


    /**
     * 师傅切换门店
     * @param int $id
     * @return \think\Response
     */
    public function switchStore()
    {
        $data = $this->request->params([
            ['store_id', ''],
        ]);
        return success((new TechnicianService())->switchStore($data));
    }


}
