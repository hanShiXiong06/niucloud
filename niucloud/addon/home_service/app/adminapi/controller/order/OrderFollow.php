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

use addon\home_service\app\service\admin\order\OrderFollowService;
use core\base\BaseAdminController;
use addon\home_service\app\dict\order\OrderFollowDict;

/**
 * 订单 回访
 * Class Order
 * @description 订单
 * @package addon\home_service\app\adminapi\controller\order
 */
class OrderFollow extends BaseAdminController
{


    /**
     * 订单状态 带数字
     * @description 获取订单状态
     * @return void
     */
    public function taskStatus()
    {
        return success((new OrderFollowService())->getTaskStatus());
    }


    public function getFollowResult()
    {
        return success(OrderFollowDict::getFollowResult());
    }

    public function getFeeSituation()
    {
        return success(OrderFollowDict::getFeeSituation());
    }


    /**
     * 订单列表
     * @description 订单列表
     * @ret urn void
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_no', ''],
            ['member_search', ''],
            ['join_create_time', []],
            ['pay_time', []],
            ['technician_name', ''],
            ['follow_status', ''],
            ['store_name', ''],
            ['order_from', '']
        ]);
        return success((new OrderFollowService())->getPage($data));
    }


    public function follow(int $order_id = 0)
    {
        $data = $this->request->params([
            ['fee_situation', ''],
            ['result_feedback', ''],
            ['satisfaction_score', ''],
            ['follow_summary', ''],
            ['suggestion_content', ''],
        ]);
        return success('SUCCESS',(new OrderFollowService())->follow($order_id, $data));
    }


    /**
     * 订单详情
     * @description 订单详情
     * @param int $order_id
     * @return void
     */
    public function getInfo(int $order_id)
    {
        return success((new OrderFollowService())->getInfo($order_id));
    }


}
