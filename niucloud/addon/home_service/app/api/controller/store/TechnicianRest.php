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

use addon\home_service\app\service\api\store\TechnicianRestService;
use core\base\BaseApiController;
use  addon\home_service\app\dict\technician\RestDict;


/**
 * 师傅休息控制器
 * @description 师傅休息
 * Class Reserve
 * @package app\api\controller\technician
 */
class TechnicianRest extends BaseApiController
{
    /**
     * 获取师傅休息记录
     * @description 获取师傅休息记录
     * @return \think\Response
     */
    public function getRestMonthstats()
    {
        $data = $this->request->params([
            ["date", ""],
            ["technician_id", ""],
        ]);
        return success('SUCCESS', (new TechnicianRestService())->getRestMonthstats($data));
    }

    /**
     * 设置师傅休息
     * @description 设置师傅休息
     * @return \think\Response
     */
    public function setTechnicianRest()
    {
        $data = $this->request->params([
            ["date", ""],
            ["hour", ""],
            ["notes", 1],
            ["technician_id", ""],
        ]);
        return success('SUCCESS', (new TechnicianRestService())->setTechnicianRest($data));
    }


    /**
     * 取消师傅信息
     * @description 设置师傅休息
     * @return \think\Response
     */
    public function cancelRest()
    {
        $data = $this->request->params([
            ["date", ""],
            ["technician_id", ""],
        ]);
        return success('SUCCESS', (new TechnicianRestService())->cancelRest($data));
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


}
