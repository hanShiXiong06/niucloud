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

namespace addon\home_service\app\listener\export;


use addon\home_service\app\model\order\Order;


/**
 * 订单导出数据源查询
 * Class ShopInvoiceExportDataListener
 * @package addon\home_service\app\listener\export
 */
class HomeServiceOrderExportDataListener
{

    public function handle($param)
    {

        $data = [];

        if ($param['type'] == 'home_service_order') {
            $where = $param['where'];
            $field = 'technician_commission,technician_additional_commission,technician_ratio,store_commission,store_additional_commission,store_ratio,   
       label_id,member_message, taker_name,taker_mobile,taker_full_address,depart_time,service_finish_time,finish_time,service_time,reserve_service_time,
      is_settlement,order_name,category_id,store_id,order_id,site_id, member_id, order_from, order_type, order_no, out_trade_no, technician_id,order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund, delete_time, order_money, pay_money';
            $order = 'create_time desc';
            $join_where = [];
            if (isset($where['member_search']) && $where['member_search'] != '') $join_where[] = ['member.member_no|member.nickname|member.username|member.mobile', 'like', "%" . $where['member_search'] . "%"];
            if (isset($where['technician_name']) && $where['technician_name'] != '') $join_where[] = ['technician.real_name', 'like', "%" . $where['technician_name'] . "%"];
            if (isset($where['store_name']) && $where['store_name'] != '') $join_where[] = ['store.store_name', 'like', "%" . $where['store_name'] . "%"];
            $search_model = (new Order)->where([['order.site_id', '=', $param['site_id']]])
                ->where($join_where)
                ->withSearch(['label_id', 'is_settlement', 'order_no', 'order_from', 'order_status', 'member_id', 'out_trade_no', 'create_time', 'order_name', 'pay_time', 'member_search_text', 'technician_search_text'], $where)->field($field)
                ->withJoin([
                    'member' => ['nickname', 'member_id'],
                    'technician' => ['real_name'],
                    'store' => ['store_name', 'mobile', 'store_id']
                ], 'left')
                ->with(
                    [
                        'pay' => function ($query) {
                            $query->field('main_id, out_trade_no, type, pay_time, status')->append(['type_name']);
                        },
                        'goodsCategory' => function ($query) {
                            $query->field('category_name,category_id');
                        },
                        'label' => function ($query) {
                            $query->field('label_id,label_name');
                        },
                    ])
                ->order($order)->append(['order_status_info', 'order_from_name', 'settlement_name', 'time_reminder']);
            if ($param['page']['page'] > 0 && $param['page']['limit'] > 0) {
                $data = $search_model->page($param['page']['page'], $param['page']['limit'])->select()->toArray();
            } else {
                $data = $search_model->select()->toArray();
            }
            foreach ($data as $key => $val) {
                $order_no = $val['order_no'];
                $data[$key]['order_no'] = !empty($order_no) ? $order_no . "\t" : '';
                $data[$key]['category_name'] = !empty($val['category_name']) ? $val['category_name'] : '';
                $data[$key]['category_name'] = $val['goodsCategory']['category_name'] ?? '' . "\t";
                $data[$key]['order_status_name'] = $val['order_status_info']['name'] ?? '' . "\t";
                $data[$key]['technician_name'] = $val['technician']['real_name'] ?? '' . "\t";
                $data[$key]['technician_sum_commission'] = bcadd($val['technician_additional_commission'], $val['technician_commission'], 2) . "\t";
                $data[$key]['store_name'] = $val['store']['store_name'] ?? '' . "\t";
                $data[$key]['store_sum_commission'] = bcadd($val['store_additional_commission'], $val['store_commission'], 2) . "\t";
                $data[$key]['service_time_text'] = $val['time_reminder']['text'] ?? '' . "\t";
            }
        }
        return $data;
    }
}
