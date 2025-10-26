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

namespace addon\home_service\app\adminapi\controller\order;

use addon\home_service\app\service\admin\order\OrderLaberlService;
use core\base\BaseAdminController;

/**
 * 标签标签
 * Class Order
 * @description 标签
 * @package addon\home_service\app\adminapi\controller\order
 */
class OrderLaber extends BaseAdminController
{
    /**
     * 标签列表
     * @description 标签列表
     * @return void
     */
    public function lists()
    {
        $data = $this->request->params([
            ['label_name', ''],
        ]);
        return success((new OrderLaberlService())->getPage($data));
    }


    /**
     * 添加标签
     * @description 添加标签
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["label_name", ""],
            ["label_color", ""],
        ]);
        return success('SUCCESS', (new OrderLaberlService())->add($data));
    }


    /**
     * 标签编辑
     * @return \think\Response
     */
    public function edit($label_id)
    {
        $data = $this->request->params([
            ["label_name", ""],
            ["label_color", ""],
        ]);
        return success('EDIT_SUCCESS', (new OrderLaberlService())->edit($label_id, $data));
    }


    /**
     * 标签详情
     * @description 标签详情
     * @param int $store_id
     * @return \think\Response
     */
    public function info(int $label_id)
    {
        return success((new OrderLaberlService())->getInfo($label_id));
    }

    /**
     * 删除标签
     * @description 删除标签
     * @param $label_id  标签id
     * @return \think\Response
     */
    public function del(int $label_id)
    {
        (new OrderLaberlService())->del($label_id);
        return success('DELETE_SUCCESS');
    }


}
