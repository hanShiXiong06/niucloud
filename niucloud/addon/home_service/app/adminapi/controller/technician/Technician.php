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
use addon\home_service\app\service\admin\technician\TechnicianService;
use core\base\BaseAdminController;


/**
 * 师傅控制器
 * @description 师傅
 * Class Reserve
 * @package app\adminapi\controller\reserve
 */
class Technician extends BaseAdminController
{
    /**
     * 获取师傅分页列表
     * @description 获取师傅分页列表
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            ["search_text", ""],
            ["store_id", ""],
            ["level_id", ""],
            ["source", ""],
            ["create_time", ""],
            ["category_id", ""],
        ]);
        return success((new TechnicianService())->getPage($data));
    }

    /**
     * 获取可以设置的 会员
     * @description 获取会员
     */
    public function getEligibleMembers()
    {
        $data = $this->request->params([
            ["keyword", ""],
        ]);
        return success((new TechnicianService())->getEligibleMembers($data));
    }


    /**
     * 师傅分成方式
     * @description 获取会员
     */
    public function getDistributeType()
    {
        return success(TechnicianDict::getTechnicianDistributeName());
    }

    /**
     * 师傅入驻方式
     * @description 获取会员
     */
    public function getSource()
    {
        return success(TechnicianDict::getTechnicianSourceName());
    }


    /**
     * 师傅状态
     * @description 获取会员
     */
    public function getTechnicianStatus()
    {
        return success(TechnicianDict::getTechnicianStatus());
    }


    /**
     * 添加师傅
     * @description 添加师傅
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["real_name", 0],
            ["mobile", ""],
            ["status", 1],
            ["member_id", 0],
            ["headimg", ""],
            ["certificate", 0],
            ["province_id", ""],
            ["city_id", ''],
            ['district_id', ''],
            ['full_address', ''],
            ['lng', ''],
            ['lat', ''],
            ['store_id', ''],
            ['category_id', ''],
            ['level_id', ''],
            ['source', 'internal'],
            ['distribute_type', 'default'],
            ['order_rate', '0.00'],
            ['additional_rate', '0.00'],
            ['headimg', ''],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\technician\Technician.add');
        $id = (new TechnicianService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }


    /**
     * 师傅详情
     * @description 师傅详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $technician_id)
    {
        return success((new TechnicianService())->getInfo($technician_id));
    }


    /**
     * 修改 师傅状态
     * @description 修改 师傅状态
     */
    public function status($id)
    {
        $data = $this->request->params([
            ["status", ""]
        ]);
        (new TechnicianService())->editStatus($id, $data);
        return success('EDIT_SUCCESS');
    }


    /**
     * 师傅编辑
     * @description 师傅编辑
     * @param $id  师傅id
     * @return \think\Response
     */
    public function edit($id)
    {
        $data = $this->request->params([
            ["real_name", 0],
            ["mobile", ""],
            ["status", 1],
            ["headimg", ""],
            ["member_id", 0],
            ["certificate", 0],
            ["province_id", ""],
            ["city_id", ''],
            ['district_id', ''],
            ['full_address', ''],
            ['lng', ''],
            ['lat', ''],
            ['store_id', ''],
            ['category_id', ''],
            ['level_id', ''],
            ['distribute_type', 'default'],
            ['order_rate', '0.00'],
            ['headimg', ''],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\technician\Technician.edit');
        (new TechnicianService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }


    /**
     * 获取师傅列表（用于弹框选择）   (待考虑)
     * @description 获取师傅列表（用于弹框选择）
     * @return \think\Response
     */
    public function select()
    {
        $data = $this->request->params([
            ["name", ""],
            ["mobile", ""],
            ["create_time", ""]
        ]);
        return success((new TechnicianService())->getSelectPage($data));
    }
    //
}
