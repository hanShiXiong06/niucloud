<?php

namespace addon\tk_jhkd\app\service\api;

use addon\tk_jhkd\app\dict\coupon\CouponMemberDict;
use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\dict\order\OrderRefundLogDict;
use addon\tk_jhkd\app\model\coupon\CouponMember;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use addon\tk_jhkd\app\service\core\CommonService;
use addon\tk_jhkd\app\service\core\OrderLogService;
use app\dict\pay\RefundDict;
use app\model\pay\Refund;
use app\service\core\pay\CoreRefundService;
use app\service\core\sys\CoreConfigService;
use core\base\BaseApiService;
use core\exception\CommonException;
use Exception;
use think\facade\Db;
use think\facade\Log;

/**
 * 订单列服务层
 */
class OrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Tkjhkdorder();
        $this->RefundModel = new Refund();
    }

    public function applyRefundCheck($data)
    {
        $min = $this->getConfig()['cancelmin'] ?? 20;
        $min = intval($min);
        $orderInfo = $this->model->where(['id' => $data['id']])->findOrEmpty();

        if ((time() - $orderInfo['pay_time']) <= 60) throw new CommonException('请等一分钟后再取消订单');
        if (($orderInfo['pay_time'] + $min * 60) < time() && $min != 0) {
            throw new CommonException('超过自主取消时间，请联系客服取消订单');
        }
        event('CancelOrder', $data);
        return [];
    }

    public function getConfig()
    {
        $info = (new CoreConfigService())->getConfig($this->site_id, 'tk_jhkd_config');
        if (empty($info)) {
            $info['value'] = ['autosend' => 0];
        }
        return $info['value'];
    }

    /**
     * 获取订单列列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $alias = 'o';
        $search_model = $this->model
            ->alias($alias)
            ->join('tkjhkd_order_delivery oi', 'oi.order_id = ' . $alias . '.order_id')
            ->where([[$alias . '.member_id', '=', $this->member_id]])
            // 直接处理order_status条件
            ->where(function ($query) use ($where, $alias) {
                // 处理order_status查询
                if ($where['order_status'] != '') {
                    $query->where($alias . '.order_status', '=', $where['order_status']);
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
                $alias . '.*',
                'oi.delivery_id',
                'oi.start_address',
                'oi.end_address'
            ])
            ->with([
                'orderInfo',
                'payInfo',
                'deliveryRealInfo',
                'addorderInfo'
            ])
            ->order($alias . '.id desc')
            ->append(['is_send_name', 'order_status_arr']);
        $order_status_list = JhkdOrderDict::getStatus();
        $list = $this->pageQuery($search_model, function ($item, $key) use ($order_status_list) {
            $item['order_status_data'] = $order_status_list[$item['order_status']] ?? [];
        });

        $list['data'] = array_map(function ($item) {
            $item['order_status'] = strval($item['order_status']);
            if ($item['orderInfo']) {
                $item['orderInfo']['price_rule'] = $item['orderInfo']['price_rule'] ? json_decode($item['orderInfo']['price_rule'], true) : [];
                $item['orderInfo']['original_rule'] = $item['orderInfo']['original_rule'] ? json_decode($item['orderInfo']['original_rule'], true) : [];
                $item['orderInfo']['courier_context'] = $item['orderInfo']['courier_context'] ? json_decode($item['orderInfo']['courier_context'], true) : [];
                $item['orderInfo']['delivery_arry'] = (new CommonService())->getBrand($item['orderInfo']['platform'], $item['orderInfo']['delivery_type']);
            }
            $fee_list = $item['deliveryRealInfo']['fee_blockList'] ? json_decode($item['deliveryRealInfo']['fee_blockList'], true) : [];
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
            $item['deliveryRealInfo']['fee_blockList'] = $new_fee_list;
            return $item;
        }, $list['data']);
        return $list;
    }


    /**
     * 获取订单列信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,member_id,order_from,order_id,order_money,order_discount_money,pay_money,coupon_value,is_send,is_pick,order_status,out_trade_no,remark,pay_time,create_time,close_reason,is_enable_refund,close_time,ip,update_time,delete_time';
        $info = $this->model->field($field)
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
            ->with(
                [
                    'orderInfo',
                    'payInfo' => function ($query) {
                        $query->field('trade_id,status,pay_time,cancel_time,fail_reason,type,trade_type,money')
                            ->where(['trade_type' => JhkdOrderDict::getOrderType()['type']])
                            ->order('create_time desc')
                            ->append(['status_name', 'type_name']);
                    },
                    'deliveryRealInfo',
                    'addorderInfo'
                ]
            )
            ->findOrEmpty()->append(['order_status_arr'])->toArray();
        $item = $info;
        $item['orderInfo']['price_rule'] = json_decode($item['orderInfo']['price_rule'], true);
        $item['orderInfo']['original_rule'] = json_decode($item['orderInfo']['original_rule'], true);
        $item['orderInfo']['delivery_arry'] = (new CommonService())->getBrand($item['orderInfo']['platform'], $item['orderInfo']['delivery_type']);
        $fee_list = !empty($item['deliveryRealInfo']['fee_blockList']) ? json_decode($item['deliveryRealInfo']['fee_blockList'], true) : [];
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
        $item['deliveryRealInfo']['fee_blockList'] = $new_fee_list;
        return $item;
    }

    /**
     * 申请退款
     */
    public function applyRefund($data)
    {
        Db::startTrans();
        try {
            $orderInfo = $this->model->where(['id' => $data['id']])
                ->with(
                    [
                        'orderInfo' => function ($query) {
                            $query->field('start_address,end_address,order_id,goods,long,width,height,delivery_id,delivery_type,weight')->append(['delivery_arry']);
                        },
                        'payInfo' => function ($query) {
                            $query->field('trade_id,status,pay_time,cancel_time,fail_reason,type,trade_type,out_trade_no,money')
                                ->where(['trade_type' => JhkdOrderDict::getOrderType()['type'], 'status' => 2])
                                ->append(['status_name', 'type_name']);
                        },
                    ]
                )->findOrEmpty();

            if ($orderInfo->isEmpty()) throw new CommonException('订单信息获取失败');
            if ($orderInfo['order_status'] == JhkdOrderDict::FINISH) throw new CommonException('已完成订单不支持退款');
            if ($orderInfo['payInfo']['status'] !== 2 && $orderInfo['is_enable_refund'] !== 1) throw new CommonException('当前状态不支持退款');
            $orderInfo->refund_status = JhkdOrderDict::REFUNDING;
            $orderInfo->close_reason = $data['close_reason'];
            $orderInfo->save();
            $deliveryInfo = (new OrderDelivery())->where(['order_id' => $orderInfo['order_id']])->findOrEmpty();
            $cancelData = [
                'order_no' => $deliveryInfo['order_no'],
                'task_id' => $deliveryInfo['task_id'],
                'order_id' => $orderInfo['order_id'],
                'platform' => $deliveryInfo['platform'],
            ];
            if ($orderInfo['is_send'] == 1) {
                event('DeliveryCancelOrder', ['site_id' => $orderInfo['site_id'], 'data' => $cancelData]);
            }
            $refund_no = (new CoreRefundService())->create($orderInfo['site_id'], $orderInfo['payInfo']['out_trade_no'], $orderInfo['payInfo']['money'], $data['close_reason'], $orderInfo['payInfo']['trade_type'], $orderInfo['payInfo']['trade_id']);
            (new CoreRefundService())->refund($orderInfo['site_id'], $refund_no, $orderInfo['payInfo']['money'], RefundDict::BACK, OrderRefundLogDict::MEMBER, $this->member_id);
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            Log::write('退款操作失败' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 关闭订单
     */
    public function close($id)
    {
        $orderInfo = $this->model->where([['id', '=', $id]])->findOrEmpty();
        if ($orderInfo['order_status'] != JhkdOrderDict::WAIT_PAY) throw new CommonException('当前订单状态不允许操作');
        $orderInfo->save(['order_status' => JhkdOrderDict::CLOSE]);
        (new OrderLogService())->writeOrderLog(
            $orderInfo['site_id'],
            $orderInfo['order_id'],
            JhkdOrderDict::CLOSE,
            '订单退款成功'
        );
        return true;
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
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty();
        //删除订单校验  已经支付状态订单不支持删除  同时删除支付表和退款表数据
        if ($model['order_status'] == JhkdOrderDict::FINISH_PAY) throw new Exception('当前订单状态暂时不支持删除');
        //增加优惠券返回方式
        if ($model['coupon_value'] && $model['order_status'] == JhkdOrderDict::CLOSE) {
            $couponInfo = $model['coupon_value'];
            $couponMember = (new CouponMember())->where(['id' => $couponInfo['id']])->findOrEmpty();
            if (!$couponMember->isEmpty()) {
                $couponMember->save(['status' => CouponMemberDict::WAIT_USE, 'use_time' => 0]);
            }
        }
        $refundInfo = $this->RefundModel->where(['site_id' => $this->site_id, 'trade_id' => $id])->findOrEmpty();
        if (!$refundInfo->isEmpty()) {
            $refundInfo->delete();
        }
        $res = $model->delete();
        (new OrderLogService())->writeOrderLog(
            $model['site_id'],
            $model['order_id'],
            JhkdOrderDict::CLOSE,
            '订单删除',
            'member'
        );

        return $res;
    }
}
