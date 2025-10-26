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

use addon\home_service\app\dict\account\AccountDict;
use addon\home_service\app\service\api\technician\TechnicianAccountService;
use addon\home_service\app\service\api\technician\TechnicianRestService;
use core\base\BaseApiController;


/**
 * 师傅账单控制器
 * @description 师傅账单
 * Class Reserve
 * @package app\api\controller\technician
 */
class TechnicianAccount extends BaseApiController
{

    /**
     * 账单类型
     * @return \think\Response
     */
    public function type()
    {
        return success('SUCCESS', AccountDict::getType());
    }

    /**
     * 账单状态
     * @return \think\Response
     */
    public function status()
    {
        return success('SUCCESS', AccountDict::getStatus());
    }

    /**
     * 日账单列表
     * @return \think\Response
     */
    public function getDayBillPage()
    {
        return success('SUCCESS', (new TechnicianAccountService())->getDayBillPage());
    }

    /**
     * 获取师傅账单
     * @description 获取师傅账单
     * @return \think\Response
     */
    public function getTechnicianAccountList()
    {
        $data = $this->request->params([
            ["from_type", ""],
            ["date", ""],
            ["status", "all"],
        ]);
        return success('SUCCESS',(new TechnicianAccountService())->getTechnicianAccountList($data));
    }

    /**
     * 获取师傅账单收支统计
     * @description 设置师傅休息
     * @return \think\Response
     */
    public function setTechnicianRest()
    {
        $data = $this->request->params([
            ["store_id", ""],
            ["technician_id", ""],
            ["date", ""],
            ["hour", ""],
        ]);
        return success('SUCCESS',(new TechnicianAccountService())->setTechnicianRest($data));
    }

}
