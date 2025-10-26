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

namespace addon\home_service\app\api\controller\store;

use addon\home_service\app\dict\technician\TechnicianDict;
use addon\home_service\app\service\api\store\TechnicianService;
use core\base\BaseApiController;

class Technician extends BaseApiController
{

    /**
     * 获取师傅状态
     * @description 获取师傅状态
     * @return \think\Response
     */
    public function status()
    {
        return success('SUCCESS',TechnicianDict::getTechnicianStatus());
    }

    /**
     * 获取师傅分页列表
     * @description 获取师傅分页列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            ["category_id", ""],
            ["real_name", ""],
            ["status", "all"],
        ]);
        return success((new TechnicianService())->getPage($data));
    }

    /**
     * 师傅详情
     * @description 师傅详情
     * @param int $technician_id
     * @return \think\Response
     */
    public function info(int $technician_id)
    {
        return success((new TechnicianService())->getInfo($technician_id));
    }

    /**
     * 设置门店师傅分成比例
     * @description 设置门店师傅分成比例
     * @return \think\Response
     */
    public function setTechnicianRate()
    {
        $data = $this->request->params([
            ["technician_id", ""],
            ["order_rate", ""],
        ]);
        (new TechnicianService())->setTechnicianRate($data);
        return success('SUCCESS');
    }
}
