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

use addon\home_service\app\service\admin\order\RefundService;
use core\base\BaseAdminController;

/**
 * 售后
 * Class Refund
 * @description 售后
 * @package app\adminapi\controller\o2o_goods_category
 */
class Refund extends BaseAdminController
{
    /**
     * 查询售后记录
     * @description 查询售后记录
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['join_status', ''],
            ['order_no', ''],
            ['member_search', ''],
            ['join_create_time', []],
            ['technician_name', ''],
            ['store_name', ''],
            ['store_id', ''],
            ['technician_id','']
        ]);
        return success(data: (new RefundService())->getPage($data));
    }


    /**
     * 查询售后详情
     * @description 查询售后详情
     * @param int $refund_id
     * @return \think\Response
     */
    public function detail(string $refund_id)
    {
        return success(data: (new RefundService())->getDetail($refund_id));
    }

    /**
     * 拒绝退款
     * @description 拒绝退款
     * @param int $refund_id
     * @return \think\Response
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function refuse(int $refund_id)
    {
        $refuse_reason = input('refuse_reason', '');
        return success(data: (new RefundService())->refuse($refund_id, $refuse_reason));
    }

    /**
     * 确认转账
     * @description 确认转账
     * @param int $refund_id
     * @return void
     */
    public function refund(int $refund_id)
    {
        $data = $this->request->params([
            ['money', 0]
        ]);
        return success(data: (new RefundService())->refund($refund_id, round($data['money'], 2)));
    }

    /**
     * 状态
     * @description 获取状态
     * @return void
     */
    public function status()
    {
        return success(data: (new RefundService())->getStatus());
    }


    /**
     *  售后状态
     * @description 获取状态
     * @return void
     */
    public function taskStatus()
    {
        return success(data: (new RefundService())->getTaskStatus());
    }


}
