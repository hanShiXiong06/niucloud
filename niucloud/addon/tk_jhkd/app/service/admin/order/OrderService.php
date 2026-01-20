<?php

namespace addon\tk_jhkd\app\service\admin\order;

use addon\tk_jhkd\app\dict\coupon\CouponMemberDict;
use addon\tk_jhkd\app\dict\order\CommissionStatusDict;
use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\model\coupon\CouponMember;
use addon\tk_jhkd\app\model\fenxiao\FenxiaoOrder;
use addon\tk_jhkd\app\model\order\Order;
use addon\tk_jhkd\app\model\order\OrderAdd;
use addon\tk_jhkd\app\service\core\CommonService;
use addon\tk_jhkd\app\service\core\OrderFinishService;
use addon\tk_jhkd\app\service\core\OrderLogService;
use addon\tk_jhkd\app\service\core\WeappDeliveryService;
use app\model\member\Member;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use app\service\core\sys\CoreSysConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use addon\tk_jhkd\app\model\OrderDeliveryReal;
use addon\tk_jhkd\app\model\order\OrderLog;
use think\Exception;

/**
 * 订单列服务层
 * Class OrderService
 * @package addon\tk_jhkd\app\service\admin\order
 */
class OrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }
    /**
     * Get order statistics data
     * Multi-dimensional statistics: today, yesterday, this week, this month, this quarter, this year
     * Statistical indicators: order quantity, trend, order amount, actual amount, discount amount, refund orders, completed orders, cancelled orders
     * @return array
     */
    public function getStat()
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $weekStart = date('Y-m-d', strtotime('this week'));
        $monthStart = date('Y-m-01');
        $quarterStart = $this->getQuarterStart();
        $yearStart = date('Y-01-01');

        // Define statistical dimensions
        $dimensions = [
            'today' => ['start' => $today . ' 00:00:00', 'end' => $today . ' 23:59:59', 'name' => 'Today'],
            'yesterday' => ['start' => $yesterday . ' 00:00:00', 'end' => $yesterday . ' 23:59:59', 'name' => 'Yesterday'],
            'this_week' => ['start' => $weekStart . ' 00:00:00', 'end' => date('Y-m-d H:i:s'), 'name' => 'This Week'],
            'this_month' => ['start' => $monthStart . ' 00:00:00', 'end' => date('Y-m-d H:i:s'), 'name' => 'This Month'],
            'this_quarter' => ['start' => $quarterStart . ' 00:00:00', 'end' => date('Y-m-d H:i:s'), 'name' => 'This Quarter'],
            'this_year' => ['start' => $yearStart . ' 00:00:00', 'end' => date('Y-m-d H:i:s'), 'name' => 'This Year']
        ];

        $result = [];
        
        foreach ($dimensions as $key => $dimension) {
            $result[$key] = $this->getStatByPeriod($dimension['start'], $dimension['end']);
            $result[$key]['period_name'] = $dimension['name'];
        }

        // Add trend comparison data
        $result['trends'] = $this->getTrendData();
        
        // Add order status distribution statistics
        $result['status_distribution'] = $this->getStatusDistribution();
        
        // Add payment method statistics
        $result['payment_methods'] = $this->getPaymentMethodStats();
        
        return $result;
    }

    /**
     * 获取指定时间段的统计数据
     * @param string $startTime
     * @param string $endTime
     * @return array
     */
    private function getStatByPeriod($startTime, $endTime)
    {
        $baseQuery = $this->model->where([['site_id', '=', $this->site_id]]);
        $timeQuery = (clone $baseQuery)->whereBetweenTime('create_time', $startTime, $endTime);
        
        // 基础统计
        $totalOrders = $timeQuery->count();
        $totalAmount = $timeQuery->sum('order_money') ?? 0;
        $totalDiscount = $timeQuery->sum('order_discount_money') ?? 0;
        $actualAmount = $totalAmount - $totalDiscount;
        
        // 按状态统计
        $waitPayCount = (clone $timeQuery)->where('order_status', JhkdOrderDict::WAIT_PAY)->count();
        $paidCount = (clone $timeQuery)->where('order_status', JhkdOrderDict::FINISH_PAY)->count();
        $pickingCount = (clone $timeQuery)->where('order_status', JhkdOrderDict::FINISH_PICK)->count();
        $finishedCount = (clone $timeQuery)->where('order_status', JhkdOrderDict::FINISH)->count();
        $closedCount = (clone $timeQuery)->where('order_status', JhkdOrderDict::CLOSE)->count();
        
        // 退款相关统计
        $refundingCount = (clone $timeQuery)->where('refund_status', JhkdOrderDict::REFUNDING)->count();
        $refundCompletedCount = (clone $timeQuery)->where('refund_status', JhkdOrderDict::REFUND_COMPLETED)->count();
        $refundFailCount = (clone $timeQuery)->where('refund_status', JhkdOrderDict::REFUND_FAIL)->count();
        $totalRefundCount = $refundingCount + $refundCompletedCount + $refundFailCount;
        
        // 已支付订单的金额统计
        $paidOrdersQuery = (clone $timeQuery)->whereIn('order_status', [
            JhkdOrderDict::FINISH_PAY,
            JhkdOrderDict::FINISH_PICK, 
            JhkdOrderDict::FINISH
        ]);
        $paidAmount = $paidOrdersQuery->sum('order_money') ?? 0;
        $paidDiscount = $paidOrdersQuery->sum('order_discount_money') ?? 0;
        $paidActualAmount = $paidAmount - $paidDiscount;
        
        return [
            'total_orders' => $totalOrders,
            'total_amount' => round($totalAmount, 2),
            'total_discount' => round($totalDiscount, 2),
            'actual_amount' => round($actualAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'paid_actual_amount' => round($paidActualAmount, 2),
            'wait_pay_count' => $waitPayCount,
            'paid_count' => $paidCount,
            'picking_count' => $pickingCount,
            'finished_count' => $finishedCount,
            'closed_count' => $closedCount,
            'refunding_count' => $refundingCount,
            'refund_completed_count' => $refundCompletedCount,
            'refund_fail_count' => $refundFailCount,
            'total_refund_count' => $totalRefundCount,
            'success_rate' => $totalOrders > 0 ? round(($finishedCount / $totalOrders) * 100, 2) : 0,
            'pay_rate' => $totalOrders > 0 ? round((($paidCount + $pickingCount + $finishedCount) / $totalOrders) * 100, 2) : 0
        ];
    }

    /**
     * 获取趋势对比数据
     * @return array
     */
    private function getTrendData()
    {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $lastWeekStart = date('Y-m-d', strtotime('last week'));
        $lastWeekEnd = date('Y-m-d', strtotime('last week +6 days'));
        $lastMonth = date('Y-m', strtotime('-1 month'));
        
        $todayStats = $this->getStatByPeriod($today . ' 00:00:00', $today . ' 23:59:59');
        $yesterdayStats = $this->getStatByPeriod($yesterday . ' 00:00:00', $yesterday . ' 23:59:59');
        $lastWeekStats = $this->getStatByPeriod($lastWeekStart . ' 00:00:00', $lastWeekEnd . ' 23:59:59');
        $lastMonthStats = $this->getStatByPeriod($lastMonth . '-01 00:00:00', date('Y-m-d', strtotime($lastMonth . '-01 +1 month -1 day')) . ' 23:59:59');
        
        return [
            'daily_trend' => [
                'current' => $todayStats['total_orders'],
                'previous' => $yesterdayStats['total_orders'],
                'growth_rate' => $yesterdayStats['total_orders'] > 0 ? 
                    round((($todayStats['total_orders'] - $yesterdayStats['total_orders']) / $yesterdayStats['total_orders']) * 100, 2) : 0,
                'amount_growth_rate' => $yesterdayStats['actual_amount'] > 0 ? 
                    round((($todayStats['actual_amount'] - $yesterdayStats['actual_amount']) / $yesterdayStats['actual_amount']) * 100, 2) : 0
            ]
        ];
    }

    /**
     * 获取订单状态分布统计
     * @return array
     */
    private function getStatusDistribution()
    {
        $statusStats = [];
        $statusList = JhkdOrderDict::getStatus();
        
        foreach ($statusList as $status => $info) {
            $count = $this->model->where([['site_id', '=', $this->site_id], ['order_status', '=', $status]])->count();
            $statusStats[] = [
                'status' => $status,
                'name' => $info['name'],
                'count' => $count
            ];
        }
        
        return $statusStats;
    }

    /**
     * 获取支付方式统计
     * @return array
     */
    private function getPaymentMethodStats()
    {
        // 通过关联的支付记录统计支付方式
        $paymentStats = $this->model
            ->alias('o')
            ->join('pay p', 'p.trade_id = o.id')
            ->where([['o.site_id', '=', $this->site_id], ['p.status', '=', 1]])
            ->field('p.type, COUNT(*) as count, SUM(o.order_money) as total_amount')
            ->group('p.type')
            ->select()
            ->toArray();
            
        return $paymentStats;
    }

    /**
     * 获取当前季度开始日期
     * @return string
     */
    private function getQuarterStart()
    {
        $month = date('n');
        if ($month <= 3) {
            return date('Y') . '-01-01';
        } elseif ($month <= 6) {
            return date('Y') . '-04-01';
        } elseif ($month <= 9) {
            return date('Y') . '-07-01';
        } else {
            return date('Y') . '-10-01';
        }
    }
    public function uploadWeappDelivery(): bool
    {
        $page = 1;
        $pageSize = 10;
        $daysBack = 15;
        try {
            do {
                $orderList = $this->model
                    ->whereIn('order_status', [
                        JhkdOrderDict::FINISH_PAY,
                        JhkdOrderDict::FINISH_PICK,
                        JhkdOrderDict::FINISH
                    ])
                    ->where('create_time', '>', date('Y-m-d H:i:s', strtotime("-{$daysBack} day")))
                    ->order('id', 'asc')
                    ->page($page, $pageSize)
                    ->select()
                    ->toArray();
                foreach ($orderList as $order) {
                    (new WeappDeliveryService())->uploadShippingInfo($order);
                }
                $page++;
            } while (!empty($orderList));
            return true;
        } catch (\Exception $e) {
            throw new Exception("Failed to upload delivery info: " . $e->getMessage());
        }
    }


    public function commissionOrder($id)
    {
        $order_info = $this->model->where(['id' => $id])->findOrEmpty();
        if ($order_info->isEmpty()) throw new CommonException('订单不存在');
        $fenxiaoOrderModel = new FenxiaoOrder();
        $fenxiao_order_info = $fenxiaoOrderModel->where(['order_id' => $order_info->order_id])->findOrEmpty();
        if ($fenxiao_order_info->isEmpty()) throw new CommonException('分销订单不存在');
        if ($fenxiao_order_info['status'] != 0) throw new CommonException('订单已结算/关闭');
        (new OrderFinishService())->orderFinish($order_info);
        return [];
    }

    public function getLink()
    {
        $wap_url = (new CoreSysConfigService())->getSceneDomain($this->site_id)['wap_url'];
        return $wap_url . '/addon/tk_jhkd/pages/ordersubmit';
    }

    /**
     * @Notes:更改订单状态
     * @Interface changeStatus
     * @param $data
     * @return true
     * @author: TK
     * @Time: 2024/7/25   下午9:55
     */
    public function changeStatus($data)
    {
        $this->model->where([['order_id', '=', $data['order_id']], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 获取订单列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,member_id,order_from,order_id,order_money,order_discount_money,is_send,is_pick,order_status,refund_status,out_trade_no,remark,pay_time,create_time,close_reason,is_enable_refund,close_time,ip,update_time,delete_time,send_log,remark';
        $order = 'id desc';
        $search_model = $this->model
            ->alias('o')
            ->join('tkjhkd_order_delivery oi', 'oi.order_id = o.order_id')
            ->where([['o.site_id', '=', $this->site_id]])
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

        $list = $this->pageQuery($search_model);
        $commService = new CommonService();
        $fenxiaoOrderModel = new FenxiaoOrder();
        $deliveryRealModel = new OrderDeliveryReal();
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['platform_name'] = $commService->getDriverByType($v['orderInfo']['platform'])['name'] ?? '';
            $list['data'][$k]['delivery_name'] = $commService->getBrand($v['orderInfo']['platform'], $v['orderInfo']['delivery_type'])['name'] ?? '';
            $list['data'][$k]['start_address'] = json_decode($v['orderInfo']['start_address'], true);
            $list['data'][$k]['end_address'] = json_decode($v['orderInfo']['end_address'], true);
            $list['data'][$k]['total_fee'] = $deliveryRealModel->where(['order_id' => $v['order_id']])->value('total_fee');
            $fenxiao_info = $fenxiaoOrderModel->where(['site_id' => $this->site_id, 'order_id' => $v['order_id']])->findOrEmpty();
            if ($fenxiao_info->isEmpty()) {
                $list['data'][$k]['fenxiao_order'] = [];
            } else {
                $list['data'][$k]['fenxiao_order'] = $fenxiao_info;
                $list['data'][$k]['fenxiao_order']['status'] = CommissionStatusDict::getStatus($fenxiao_info['status']);
            }
        }
        return $list;
    }

    public function sendOrder($order_id)
    {
        event('OrderSend', $order_id);
        return true;
    }

    /**
     * 获取订单列信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,member_id,order_from,order_id,order_money,order_discount_money,is_send,is_pick,order_status,refund_status,out_trade_no,remark,pay_time,create_time,close_reason,is_enable_refund,close_time,ip,update_time,delete_time,send_log';
        $info = $this->model->field($field)
            ->where([['id', "=", $id]])
            ->with(
                [
                    'orderInfo',
                    'addorderInfo',
                    'deliveryRealInfo',
                    'payInfo' => function ($query) {
                        $query->field('trade_id,status,pay_time,cancel_time,fail_reason,type,trade_type')->append(['status_name', 'type_name']);
                    },
                    'memberInfo' => function ($query) {
                        $query->field('nickname,member_id');
                    },
                ]
            )
            ->findOrEmpty()->append(['order_status_arr'])->toArray();
        $info['is_send'] = strval($info['is_send']);
        $info['is_pick'] = strval($info['is_pick']);
        $info['orderInfo']['price_rule'] = json_decode($info['orderInfo']['price_rule'], true);
        $info['orderInfo']['original_rule'] = json_decode($info['orderInfo']['original_rule'], true);
        $info['orderInfo']['delivery_arry'] = (new CommonService())->getBrand($info['orderInfo']['platform'], $info['orderInfo']['delivery_type']);
        $fee_list = !empty($info['deliveryRealInfo']['fee_blockList']) ? json_decode($info['deliveryRealInfo']['fee_blockList'], true) : [];
        $new_fee_list = [];
        if ($fee_list != '') {
            foreach ($fee_list as $fee) {
                if ($fee['type'] != 0) {
                    $new_fee_list[] = [
                        'fee' => $fee['fee'],
                        'type' => $fee['type'],
                        'name' => $fee['name']
                    ];
                }
            }
        }

        $info['deliveryRealInfo']['fee_blockList'] = $new_fee_list;
        return $info;
    }

    public function getFxInfo($id)
    {
        $field = 'id,site_id,member_id,order_from,order_id,order_money,order_discount_money,is_send,is_pick,order_status,refund_status,out_trade_no,remark,pay_time,create_time,close_reason,is_enable_refund,close_time,ip,update_time,delete_time,send_log';
        $info = $this->model->field($field)
            ->where([['order_id', "=", $id]])
            ->with(
                [
                    'orderInfo',
                    'addorderInfo',
                    'deliveryRealInfo',
                    'payInfo' => function ($query) {
                        $query->field('trade_id,status,pay_time,cancel_time,fail_reason,type,trade_type')->append(['status_name', 'type_name']);
                    },
                    'memberInfo' => function ($query) {
                        $query->field('nickname,member_id');
                    },
                ]
            )
            ->findOrEmpty()->append(['order_status_arr'])->toArray();
        $info['is_send'] = strval($info['is_send']);
        $info['is_pick'] = strval($info['is_pick']);
        $info['orderInfo']['price_rule'] = json_decode($info['orderInfo']['price_rule'], true);
        $info['orderInfo']['original_rule'] = json_decode($info['orderInfo']['original_rule'], true);
        $info['orderInfo']['delivery_arry'] = (new CommonService())->getBrand($info['orderInfo']['platform'], $info['orderInfo']['delivery_type']);
        $fee_list = !empty($info['deliveryRealInfo']['fee_blockList']) ? json_decode($info['deliveryRealInfo']['fee_blockList'], true) : [];
        $new_fee_list = [];
        if ($fee_list != '') {
            foreach ($fee_list as $fee) {
                if ($fee['type'] != 0) {
                    $new_fee_list[] = [
                        'fee' => $fee['fee'],
                        'type' => $fee['type'],
                        'name' => $fee['name']
                    ];
                }
            }
        }

        $info['deliveryRealInfo']['fee_blockList'] = $new_fee_list;
        return $info;
    }

    /**
     * 添加订单列
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 订单列编辑
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data)
    {

        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除订单列
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if ($model['order_status'] == -1 || $model['order_status'] == 0) {
            if ($model['coupon_value'] && $model['order_status'] == JhkdOrderDict::CLOSE) {
                $couponInfo = $model['coupon_value'];
                $couponMember = (new CouponMember())->where(['id' => $couponInfo['id']])->findOrEmpty();
                if (!$couponMember->isEmpty()) {
                    $couponMember->save(['status' => CouponMemberDict::WAIT_USE, 'use_time' => 0]);
                }
            }
            //检查补差价订单
            $addModel = (new OrderAdd())->where(['order_id' => $model['order_id']])->findOrEmpty();
            if (!$addModel->isEmpty()) {
                if ($addModel['order_status'] == 0) throw new CommonException('存在未支付补差价订单，禁止删除');
            }
            $deliveryInfo = (new OrderDelivery())->where(['order_id' => $model['order_id']])->findOrEmpty();
            if (!$deliveryInfo->isEmpty()) {
                $deliveryInfo->delete();
            }
            $realInfo = (new OrderDeliveryReal())->where(['order_id' => $model['order_id']])->findOrEmpty();
            if (!$realInfo->isEmpty()) {
                $realInfo->delete();
            }
            (new OrderLog())->where(['order_id' => $model['order_id']])->delete();
            (new OrderLogService())->writeOrderLog(
                $model['site_id'],
                $model['order_id'],
                $model['order_status'],
                '后台删除',
                'system'
            );
            $res = $model->delete();
            //删除分销订单
            (new FenxiaoOrder())->where(['order_id' => $model['order_id']])->delete();
            return $res;
        }
        throw new CommonException('当前订单状态不允许删除');
    }

    public function getMemberAll()
    {
        $memberModel = new Member();
        return $memberModel->where([["site_id", "=", $this->site_id]])->select()->toArray();
    }
}
