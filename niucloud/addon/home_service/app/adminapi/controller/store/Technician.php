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

namespace addon\home_service\app\adminapi\controller\store;


use addon\home_service\app\service\admin\store\TechnicianService;
use core\base\BaseAdminController;


/**
 * 门店师傅控制器
 * @description 师傅
 * Class Reserve
 * @package app\adminapi\controller\reserve
 */
class Technician extends BaseAdminController
{

    /**
     * 分页列表
     * @description 门店分页列表
     * @return \think\Response
     */
    public function lists($store_id)
    {
        $data = $this->request->params([
            ["status", 1],
            ["real_name", ""],
        ]);
        $data['store_id'] = $store_id ?? 0;
        return success('SUCCESS', (new TechnicianService())->getPage($data));
    }


    /**
     * 设置门店师傅分成比例
     * @description 设置门店师傅分成比例
     * @return \think\Response
     */
    public function setTechnicianRate()
    {
        $data = $this->request->params([
            ["store_id", ""],
            ["technician_id", ""],
            ["order_rate", ""],
        ]);
        (new TechnicianService())->setTechnicianRate($data);
        return success('SUCCESS');
    }


}
