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


use addon\home_service\app\dict\order\InvoiceDict;
use addon\home_service\app\model\order\Invoice;
use addon\home_service\app\model\order\Order;

/**
 * 发票导出数据源查询
 * Class ShopInvoiceExportDataListener
 * @package addon\home_service\app\listener\export
 */
class HomeServiceInvoiceExportDataListener
{

    public function handle($param)
    {
        $data = [];
        if ($param['type'] == 'home_service_invoice') {
            $where_arr = [];
            if (!isset($param['where']['status']) || empty($param['where']['status'])){
                $param['where']['status'] = 'all';
            }
            if ($param['where']['status'] != 'all'){
                $where_arr[] = ['invoice.status', '=', $param['where']['status']];
            }
            if (isset($param['where']['header_name']) && !empty($param['where']['header_name'])){
                $where_arr[] = ['header_name', 'like', '%' . $param['where']['header_name'] . '%'];
            }
            if (isset($param['where']['nickname']) && !empty($param['where']['nickname'])){
                $where_arr[] = ['member.nickname', 'like', '%' . $param['where']['nickname'] . '%'];
            }

            if (isset($param['where']['create_time']) && !empty($param['where']['create_time'])){
                $start_time = empty($param['where']['create_time'][0]) ? 0 : strtotime($param['where']['create_time'][0]);
                $end_time = empty($param['where']['create_time'][1]) ? 0 : strtotime($param['where']['create_time'][1]);
                if ($start_time > 0 && $end_time > 0) {
                    $where_arr[] = ['invoice.create_time','between',[$start_time,$end_time]];
                } else if ($start_time > 0 && $end_time == 0) {
                    $where_arr[] = ['invoice.create_time', '>=', $start_time];
                } else if ($start_time == 0 && $end_time > 0) {
                    $where_arr[] = ['invoice.create_time', '<=', $end_time];
                }
            }
            $model = new Invoice();
            $order = 'id desc';
            $search_model = $model->where([[ 'invoice.site_id', '=', $param['site_id'] ]])
                ->where($where_arr)
                ->withJoin(['member' => function ($query) use ($param){
                    $query = $query->field('member.member_id,nickname');
                    if (!empty($where['nickname'])){
                        $query->where([['nickname', '=', $where['nickname']]]);
                    }
                }])
                ->order($order)->hidden(['member'])->append([ 'type_name', 'header_type_name', 'content_name', 'status_name' ]);
            if ($param['page']['page'] > 0 && $param['page']['limit'] > 0) {
                $data = $search_model->page($param['page']['page'], $param['page']['limit'])->select()->toArray();
            } else {
                $data = $search_model->select()->toArray();
            }
            foreach ($data as $key => $val) {
                $order_no_list = (new Order())->where([['site_id', '=', $param['site_id']], ['order_id', 'in', $val['order_ids']]])->column('order_no');
                $order_no = implode(',',$order_no_list);


                $data[$key]['order_no'] = !empty($order_no) ? $order_no."\t" : '';
                $data[$key]['order_money'] = !empty($val['order_money']) ? $val['order_money'] : '';
                $data[$key]['nickname'] = !empty($val['nickname']) ? $val['nickname'] : '';
                $data[$key]['header_name'] = $val['header_name']."\t";
                $data[$key]['tax_number'] = $val['tax_number']."\t";
                $data[$key]['bank_card_number'] = $val['bank_card_number']."\t";
                $data[$key]['invoice_number'] = $val['invoice_number']."\t";
                $data[$key]['create_time'] = !empty($val['create_time']) ? $val['create_time'] : '';
                $data[$key]['invoice_time'] = !empty($val['invoice_time']) ? $val['invoice_time'] : '';
            }
        }
        return $data;
    }
}
