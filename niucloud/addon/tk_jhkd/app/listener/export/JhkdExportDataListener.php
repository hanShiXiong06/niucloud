<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\listener\export;

use addon\tk_jhkd\app\dict\order\JhkdOrderAddDict;
use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\model\order\Order;
use addon\tk_jhkd\app\model\order\OrderAdd;
use addon\tk_jhkd\app\service\core\CommonService;

/**
 * 卡密导出数据源查询
 */
class JhkdExportDataListener
{

    public function handle($param)
    {
        if ($param['type'] == 'tk_jhkd_order') {
            $model = new Order();
            $where = $param['where']['searchParam'];
            if (!isset($where['create_time'])) {
                $where['create_time'] = ['', ''];
            }
            $order = 'create_time desc';
            $search_model = $model
                ->alias('o')
                ->join('tkjhkd_order_delivery oi', 'oi.order_id = o.order_id')
                ->where([['o.site_id', '=', $param['site_id']]])
                ->order($order)
                ->where(function ($query) use ($where) {
                    // 处理order_status查询
                    if ($where['order_status'] != '') {
                        $query->where('o.order_status', '=', $where['order_status']);
                    }
                    if ($where['member_id'] != '') {
                        $query->where('o.member_id', '=', $where['member_id']);
                    }
                    if ($where['order_from'] != '') {
                        $query->where('o.order_from', '=', $where['order_from']);
                    }
                    if ($where['refund_status'] != '') {
                        $query->where('o.refund_status', '=', $where['refund_status']);
                    }
                    if ($where['create_time'][0] != '') {
                        $query->whereBetweenTime('o.create_time', $where['create_time'][0], $where['create_time'][1]);
                    }
                    if ($where['out_trade_no'] != '') {
                        $query->where('o.out_trade_no', 'like', "%{$where['out_trade_no']}%");
                    }
                    if ($where['remark'] != '') {
                        $query->where('o.remark', 'like', "%{$where['remark']}%");
                    }
                    if ($where['is_send'] != '') {
                        $query->where('o.is_send', '=', $where['is_send']);
                    }
                    // 处理关键字搜索
                    if ($where['keyword'] != '') {
                        $query->whereOr([
                            ['oi.delivery_id', 'like', "%{$where['keyword']}%"],
                            ['o.order_id', 'like', "%{$where['keyword']}%"],
                        ]);
                        $query->whereOr("(JSON_VALID(oi.start_address) AND JSON_SEARCH(oi.start_address, 'one', '%{$where['keyword']}%', NULL, '$.name') IS NOT NULL)")
                            ->whereOr("(JSON_VALID(oi.start_address) AND JSON_SEARCH(oi.start_address, 'one', '%{$where['keyword']}%', NULL, '$.mobile') IS NOT NULL)")
                            ->whereOr("(JSON_VALID(oi.start_address) AND JSON_SEARCH(oi.start_address, 'one', '%{$where['keyword']}%', NULL, '$.address') IS NOT NULL)")
                            ->whereOr("(JSON_VALID(oi.start_address) AND JSON_SEARCH(oi.start_address, 'one', '%{$where['keyword']}%', NULL, '$.full_address') IS NOT NULL)")
                            ->whereOr("(JSON_VALID(oi.end_address) AND JSON_SEARCH(oi.end_address, 'one', '%{$where['keyword']}%', NULL, '$.name') IS NOT NULL)")
                            ->whereOr("(JSON_VALID(oi.end_address) AND JSON_SEARCH(oi.end_address, 'one', '%{$where['keyword']}%', NULL, '$.mobile') IS NOT NULL)")
                            ->whereOr("(JSON_VALID(oi.end_address) AND JSON_SEARCH(oi.end_address, 'one', '%{$where['keyword']}%', NULL, '$.address') IS NOT NULL)")
                            ->whereOr("(JSON_VALID(oi.end_address) AND JSON_SEARCH(oi.end_address, 'one', '%{$where['keyword']}%', NULL, '$.full_address') IS NOT NULL)");
                    }
                })
                ->field([
                    'o.*',
                    'oi.delivery_id',
                    'oi.start_address',
                    'oi.end_address'
                ])
                ->with([
                    'orderInfo',
                    'payInfo' => function ($query) {
                        $query->field('trade_id,status,pay_time,cancel_time,fail_reason,type,trade_type')
                            ->where(['trade_type' => JhkdOrderDict::getOrderType()['type']])
                            ->append(['status_name', 'type_name']);
                    },
                    'deliveryRealInfo',
                    'addorderInfo',
                    'member'
                ])
                ->order('o.id desc')
                ->append(['is_send_name', 'order_status_arr']);
            if ($param['page']['page'] > 0 && $param['page']['limit'] > 0) {
                $list['data'] = $search_model->page($param['page']['page'], $param['page']['limit'] == 1 ? 100 : $param['page']['limit'])->select()->toArray();
            } else {
                $list['data'] = $search_model->select()->toArray();
            }
            $commService = new CommonService();
            $addModel = new OrderAdd();
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['delivery_name'] = $commService->getBrand($v['orderInfo']['platform'], $v['orderInfo']['delivery_type'])['name'] ?? '';
                $list['data'][$k]['total_fee'] = $v['deliveryRealInfo']['total_fee'] ?? 0;
                $addInfo = $addModel->where(['order_id' => $v['order_id']])->findOrEmpty();
                $add = 0;
                if ($addInfo->isEmpty()) {
                    $list['data'][$k]['add_fee'] = 0;
                    $list['data'][$k]['add_status_name'] = '';
                } else {
                    $list['data'][$k]['add_fee'] = $addInfo['order_money'];
                    $list['data'][$k]['add_status_name'] = JhkdOrderAddDict::getStatus($addInfo['order_status'])['name'];
                    if ($addInfo['order_status'] == JhkdOrderAddDict::FINISH_PAY) {
                        $add = $addInfo['order_money'];
                    } else {
                        $add = -$addInfo['order_money'];
                    }
                }
                $list['data'][$k]['profit'] = $v['pay_money'] - $list['data'][$k]['total_fee'] + $add;
                $list['data'][$k]['order_status_name'] = JhkdOrderDict::getStatus($v['order_status'])['name'];
            }
            return $list['data'];
        }
        return [];
    }
}