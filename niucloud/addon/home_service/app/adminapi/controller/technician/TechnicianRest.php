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

namespace addon\home_service\app\adminapi\controller\technician;

use addon\home_service\app\dict\technician\RestDict;
use addon\home_service\app\service\admin\technician\TechnicianRestService;
use core\base\BaseAdminController;


/**
 * 师傅休息控制器
 * @description 师傅休息
 * Class Reserve
 * @package app\adminapi\controller\technician
 */
class TechnicianRest extends BaseAdminController
{
    /**
     * 获取师傅休息记录
     * @description 获取师傅休息记录
     * @return \think\Response
     */
    public function getTechnicianRestList()
    {
        $data = $this->request->params([
            ["technician_id", ""],
            ["date", ""],
        ]);
        return success('SUCCESS', (new TechnicianRestService())->getTechnicianRestList($data));
    }


    /**
     * 请假理由
     * @description 设置师傅休息
     * @return \think\Response
     */
    public function getRestReason()
    {
        return success('SUCCESS', RestDict::getRestType());
    }

    /**
     * 设置师傅休息
     * @description 设置师傅休息
     * @return \think\Response
     */
    public function setTechnicianRest()
    {
        $data = $this->request->params([
            ["technician_id", ""],
            ["date",""],
            ["notes",""],
            ["hour",""],
        ]);
        return success('SUCCESS', (new TechnicianRestService())->setTechnicianRest($data));
    }




    /**
     * 设置师傅月 休息 数据
     * @description 设置师傅休息
     * @return \think\Response
     */
    public function getRestMonthstats()
    {
        $data = $this->request->params([
            ["technician_id", ""],
            ["date", ""],
        ]);
        return success('SUCCESS', (new TechnicianRestService())->getRestMonthstats($data));

    }


}
