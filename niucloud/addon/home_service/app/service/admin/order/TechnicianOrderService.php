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

namespace addon\home_service\app\service\admin\order;

use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\service\core\order\CoreOrderService;
use app\dict\pay\PayDict;
use app\model\member\Member;
use core\base\BaseAdminService;
use think\db\Query;
use think\facade\Db;
use think\Model;

/**
 *师傅订单业务
 */
class TechnicianOrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        Order::$contextRole = Order::ROLE_SYSTEM;
    }

    /**
     * 订单分页列表
     * @param array $where
     * @return mixed
     */
    public function getPage(array $where)
    {
        $field = 'site_id,technician_commission,technician_additional_commission,store_id,order_name,order_id,site_id,member_id, order_no, technician_id,order_status, create_time, order_money';
        $order = 'create_time desc';
        $join_where = [];
        if ($where['member_search'] != '') {
            $join_where = [
                ['member.member_no|member.nickname|member.username|member.mobile', 'like', "%" . $where['member_search'] . "%"],
            ];
        }
        $search_model = $this->model
            ->where([['order.site_id', '=', $this->site_id], ['technician_id', '=', $where['technician_id']]])
            ->where($join_where)
            ->withSearch(['order_no', 'order_name'], $where)->field($field)
            ->withJoin([
                'member' => ['nickname', 'member_id'],
            ], 'left')
            ->with([
                'store' => function ($query) {
                    $query->field('store_name, mobile, store_id');
                }
            ])
            ->order($order)
            ->append(['order_status_info']);
        $list = $this->pageQuery($search_model);
        foreach ($list['data'] as &$team) {
            $team['technician_sum_commission'] = bcadd($team['technician_additional_commission'], $team['technician_commission'], 2);
        }
        return $list;
    }


}
