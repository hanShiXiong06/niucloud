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

use addon\home_service\app\dict\technician\TechnicianDict;
use addon\home_service\app\service\admin\technician\TechnicianApplicationService;
use core\base\BaseAdminController;


/**
 * 师傅入驻控制器
 * @description 师傅入驻申请
 * Class Reserve
 * @package app\adminapi\controller\reserve
 */
class TechnicianApplication extends BaseAdminController
{
    /**
     * 获取师傅入驻申请分页列表
     * @description 获取师傅入驻申请分页列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            ["nickname", ""],
            ["mobile", ""],
            ["create_time", ""],
            ['real_name', ''],
            ['audit_status', '']
        ]);
        return success((new TechnicianApplicationService())->getPage($data));
    }


    /**
     * 获取师傅入驻状态
     * @description 获取师傅入驻申请分页列表
     * @return \think\Response
     */
    public function getStatus()
    {
        return success(TechnicianDict::getTechnicianApplicationStatus());
    }


    /**
     * 师傅入驻申请详情
     * @description 师傅入驻申请详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new TechnicianApplicationService())->getInfo($id));
    }


    /**
     * 审核操作
     * @description 添加师傅入驻申请
     * @return \think\Response
     */
    public function examine(int $id)
    {
        $data = $this->request->params([
            ["real_name", ""],
            ["id_card_front", ""],
            ["id_card_back", ""],
            ["id_number", ""],
            ['mobile', ''],
            ['certificate', ''],
            ['notes', ''],
            ["province_id", 0],
            ["city_id", 0],
            ["district_id", 0],
            ["full_address", ""],
            ["lng", ""],
            ["lat", ""],
            ["store_id", 0],
            ["category_id", ''],
            ["member_id", ''],
            ['audit_status', ''],
            ['audit_remark', ''],
            ["headimg", ""],
        ]);
        $id = (new TechnicianApplicationService())->examine($data, $id);
        return success('ADD_SUCCESS', ['id' => $id]);
    }


}
