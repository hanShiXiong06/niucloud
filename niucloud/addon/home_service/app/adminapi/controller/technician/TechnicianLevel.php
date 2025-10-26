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

use addon\home_service\app\service\admin\technician\TechnicianLevelService;
use core\base\BaseAdminController;


/**
 * 师傅等级控制器
 * @description 师傅
 * Class Reserve
 * @package app\adminapi\controller\reserve
 */
class TechnicianLevel extends BaseAdminController
{
    /**
     * 获取师傅等级列表
     * @description 查看师傅等级列表-分页
     * @return \think\Response
     */
    public function pages()
    {
        $data = $this->request->params([
            ["level_name", ""],
        ]);
        return success((new TechnicianLevelService())->getPage($data));
    }

    /**
     * 师傅等级详情
     * @description 查看师傅等级详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $level_id)
    {
        return success((new TechnicianLevelService())->getInfo($level_id));
    }

    /**
     * 添加师傅等级
     * @description 添加师傅等级
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["level_num", ""],
            ["level_name", ""],
            ["order_rate", 0],
            ["order_num", 0],
            ["achievement", 0],
            ["is_default", 0],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\technician\Level.add');
        $level_id = (new TechnicianLevelService())->add($data);
        return success('ADD_SUCCESS', ['level_id' => $level_id]);
    }

    /**
     * 师傅等级编辑
     * @description 编辑师傅等级
     * @param $level_id  师傅等级id
     * @return \think\Response
     */
    public function edit($level_id)
    {
        $data = $this->request->params([
            ["level_name", ""],
            ["order_rate", 0],
            ["order_num", 0],
            ["achievement", 0],
            ["is_default", 0],
        ]);
        $this->validate($data, 'addon\home_service\app\validate\technician\Level.edit');
        (new TechnicianLevelService())->edit($level_id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 师傅等级删除
     * @description 删除师傅等级
     * @param $level_id  师傅等级id
     * @return \think\Response
     */
    public function del(int $level_id)
    {
        (new TechnicianLevelService())->del($level_id);
        return success('DELETE_SUCCESS');
    }

    /**
     * 师傅等级权重
     * @description 师傅等级权重
     * @return \think\Response
     */
    public function getLevelNumList()
    {
        return success((new TechnicianLevelService())->getLevelNumList());
    }


    public function getList()
    {
        return success((new TechnicianLevelService())->getList());
    }


}
