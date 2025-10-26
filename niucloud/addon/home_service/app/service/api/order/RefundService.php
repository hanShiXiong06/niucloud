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

namespace addon\home_service\app\service\api\order;

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\OrderRefundLogDict;
use addon\home_service\app\dict\order\RefundDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderItem;
use addon\home_service\app\model\order\OrderRefund;
use addon\home_service\app\model\technician\Technician;
use addon\home_service\app\service\core\order\CoreOrderConfigService;
use addon\home_service\app\service\core\order\CoreOrderRefundLogService;
use addon\home_service\app\service\core\order\CoreOrderRefundService;
use app\model\pay\Refund;
use app\service\core\pay\CoreRefundService;
use core\base\BaseApiService;
use core\exception\ApiException;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 退款服务层
 * Class RechargeOrderService
 * @package app\service\api
 */
class RefundService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderRefund();
    }

    /**
     * 获取平台估计金额
     * @param array $data
     * @return void
     */
    public function getPlatformExpectMoney(array $data)
    {
        if (empty($data['money'])) throw new CommonException('HOME_SERVICE_REFUND_MONEY_GT_ZERO');

        $core_order_config_service = new CoreOrderConfigService();
        $refund_expect_revenue_rate = $core_order_config_service->getOrderRefundConfig($this->site_id)['order_refund']['refund_expect_revenue_rate'] ?? '';
        if ($refund_expect_revenue_rate <= 0) return $data['money'];
        return bcmul($data['money'], bcmul($refund_expect_revenue_rate, 0.01, 2), 2);

    }

    /**
     * 申请退款
     * @param array $data
     * @return void
     */
    public function apply(array $data)
    {
        $order_refund_info = $this->model->where([['order_id', '=', $data['order_id']], ['site_id', '=', $this->site_id], ['status', 'not in',[RefundDict::REFUND_REFUSE, RefundDict::CANCEL] ]])->findOrEmpty();
        if (!$order_refund_info->isEmpty()) throw new CommonException('REFUND_HAD_APPLIED');

        $order = (new Order())->where([['order_id', '=', $data['order_id']], ['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])->append(['order_status_info'])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if (!$order->order_status_info['is_refund']) throw new CommonException('NOT_ALLOW_APPLY_REFUND');
        $order_item_list = (new OrderItem())->where([['order_id', '=', $data['order_id']], ['site_id', '=', $this->site_id]])->select();
//        if (!$order_item->is_enable_refund) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_ENABLE_REFUND');
//        if (!in_array($order_item->refund_status, ['', RefundDict::CANCEL])) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_ENABLE_REFUND');
        //次卡订单只能派单前退款
        if ($order->is_card_order && !$order->order_status == OrderDict::WAIT_DISPATCH) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_ENABLE_REFUND');
        $data['apply_money'] = $order->pay_money;
        Db::startTrans();
        try {
            //次卡订单
            if ($order->is_card_order) {
                $data['apply_money'] = 0;
            }

            //退款金额不能大于可退款总额
            if ($data['apply_money'] > $order->pay_money) throw new ApiException('HOME_SERVICE_ORDER_REFUND_MONEY_GT_ORDER_MONEY');//退款金额不能大于可退款总额

            $order_refund_no = create_no();
            if ($data['apply_money'] > 0) {
//                $order_refund_no = (new CoreRefundService())->create($this->site_id, $order->out_trade_no, $data['apply_money'], $data['remark'] ?? '');
            }

            $reason = $data['reason'];
            $insert_data = array(
                'order_id' => $data['order_id'],
                'order_item_id' => 0,
                'site_id' => $this->site_id,
                'refund_no' => '',
                'reason' => $reason,
                'member_id' => $this->member_id,
                'apply_money' => $data['apply_money'],
                'status' => RefundDict::WAIT_REFUND,
                'remark' => $data['remark'],
                'voucher' => $data['voucher'],
                'create_time' => time(),
                'source' => 'member',
                'technician_id' => $order->technician_id,
                'store_id' => $order->store_id
            );
            $res = $this->model->create($insert_data);

            // 添加售后日志
            CoreOrderRefundLogService::addLog($order['site_id'], $res->refund_id, OrderRefundLogDict::APPLY, 'member', $this->member_id);
            //将订单项的退款单号覆盖
            foreach ($order_item_list as $value) {
                $value->refund_no = '';
                $value->refund_status = RefundDict::WAIT_REFUND;
                $value->save();
            }


            $order_config = (new CoreOrderConfigService)->getOrderRefundConfig($order->site_id)['refund_auto'];
            if ($order_config && $order_config['is_check']) {
                if ($order_config['refund_auto_length'] > 0) {
                    $order->auto_refund_time = time() + $order_config['refund_auto_length'] * 60;
                }
            }
            $order->refund_status = RefundDict::WAIT_REFUND;
            $order->refund_apply_time = time();
            $order->is_abnormal = 1;
            $order->save();
            if (!empty($order->technician_id)) {
                event('NotificationEvent', [
                    'identity' => [NoticeDict::TECHNICIAN],
                    'type' => NoticeDict::REFUND,
                    'notice_source' => NoticeDict::ORDER,
                    'order_id' => $order->order_id,
                    'technician_id' => $order->technician_id,
                    'member_id' => $order->member_id,
                    'site_id' => $this->site_id,
                ]);
            }
            //退款金额为0或状态为待派遣，直接完成

            if($order->is_card_order && $order->technician_id){
                throw new ApiException('HOME_SERVICE_ACCEPT_ORDER_CARD_NOT_REFUND');
            }
            if ($order->store_id == 0 && ($data['apply_money'] <= 0 || $order->order_status == OrderDict::WAIT_DISPATCH)) {
                (new CoreOrderRefundService())->refundTransfer($res->refund_id, $insert_data['apply_money'], $this->site_id, 'member', $this->member_id);
            }
            Db::commit();
            return $res->refund_id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 撤销退款
     * @param int $refund_id
     * @return void
     */
    public function cancel(int $refund_id)
    {
        $refund = (new OrderRefund())->where([['refund_id', '=', $refund_id], ['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])->findOrEmpty();
        if ($refund->isEmpty()) throw new CommonException('REFUND_NOT_EXIST');
        if ($refund->status != RefundDict::WAIT_REFUND) throw new CommonException('REFUND_CANNOT_CANCEL');

        $refund->status = RefundDict::CANCEL;
        $refund->save();
        $refund->orderMain->refund_status = '';
        $refund->orderMain->refund_apply_time = 0;
        $refund->orderMain->is_abnormal = 0;
        $refund->orderMain->save();

        $out_trade_no = $refund->orderMain->out_trade_no;

        (new Refund())->where([['out_trade_no', '=', $out_trade_no], ['site_id', '=', $this->site_id]])->update(['status' => (new \app\dict\pay\RefundDict())::CANCEL]);

        (new OrderItem())->update(['refund_status' => RefundDict::CANCEL], [['order_id', '=', $refund->order_id]]);
        // 添加售后日志
        CoreOrderRefundLogService::addLog($refund->site_id, $refund->refund_id, OrderRefundLogDict::CANCEL, 'member', $this->member_id);
        return true;
    }

    /**
     * 查询退款详情
     * @param string $refund_id
     * @return array
     */
    public function getDetail(string $refund_id)
    {
        $field = 'refund_id,site_id,order_id,member_id,refund_no,apply_money,money,status,create_time,transfer_time,refuse_reason,source,reason,remark,voucher';

        $detail = (new OrderRefund())->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['refund_id', '=', $refund_id]])
            ->field($field)
            ->with([
                'refund_log' => function ($query) {
                    $query->field('refund_id, uid, action, action_time, action_way')->append(['nickname', 'action_name'])
                        ->order('id', 'desc');
                },
                'order_item' => function ($query) {
                    $query->field('order_item_id, site_id, order_id, member_id, goods_id, item_id, item_type, item_name, item_image, unit, price, num, refund_no,refund_status')->append(['item_image_thumb_small', 'refund_status_name']);
                },
                'orderMain' => function ($query) {
                    $query->field('taker_name,taker_mobile,taker_address,taker_full_address,store_id,order_id, check_code,site_id, reserve_service_time,member_message,order_type, member_id, order_from, order_type, order_no, out_trade_no, order_status, refund_status, ip, create_time, pay_time, close_time, auto_close_time, is_enable_refund');
                },
            ])
            ->append(['status_name', 'reason_name'])->findOrEmpty()->toArray();

        return $detail;
    }

    /**
     * 分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where)
    {
        $field = 'refund_id,site_id,order_id,member_id,refund_no,apply_money,money,status,create_time,transfer_time,refuse_reason,source,reason,remark,voucher';
        $order = 'refund_id desc';

        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])
            ->withSearch(['status'], $where)
            ->field($field)
            ->with(
                [
                    'order_item' => function ($query) {
                        $query->field('order_item_id, site_id, order_id, member_id, goods_id, item_id, item_type, item_name, item_image, unit, price, num')->append(['item_image_thumb_small']);
                    },
                    'orderMain' => function ($query) {
                        $query->field('order_id, order_no');
                    },
                ])
            ->order($order)->append(['status_name', 'reason_name']);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 退款状态
     * @return array|array[]
     */
    public function getStatus()
    {
        return RefundDict::getRefundStatus();
    }

    /**
     * 根据订单号，站点ID查询订单详情
     * @param string $refund_no
     * @return array
     */
    public function getOrderDetail(string $refund_no)
    {
        $refundOrderInfo = ($this->model)->where([['site_id', '=', $this->site_id], ['refund_no', '=', $refund_no]])->findOrEmpty()->toArray();
        if (!empty($refundOrderInfo)) {
            $technician_info = (new Technician())->field('id,site_id,member_id,real_name,mobile,status')->where([['member_id', '=', $this->member_id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
            $technician_id = $technician_info['id'] ?? 0;
            if (empty($technician_id)) throw new CommonException('师傅未找到');

            $orderInfo = (new Order())->where([['site_id', '=', $this->site_id], ['order_id', '=', $refundOrderInfo['order_id']], ['technician_id', '=', $technician_info['id']]])->findOrEmpty()->toArray();
            if (empty($orderInfo)) {
                throw new CommonException('REFUND_NOT_EXIST');
            } else {
                $field = 'refund_id,site_id,order_id,member_id,refund_no,apply_money,money,status,create_time,transfer_time,refuse_reason,source,reason,remark,voucher';
                $detail = ($this->model)->where([['site_id', '=', $this->site_id], ['refund_no', '=', $refund_no]])->field($field)->with(['refund_log' => function ($query) {
                    $query->field('refund_id, uid, action, action_time, action_way')->append(['nickname', 'action_name'])->order('id', 'desc');
                }, 'order_item' => function ($query) {
                    $query->field('order_item_id, site_id, order_id, member_id, goods_id, item_id, item_type, item_name, item_image, unit, price, num, refund_no,refund_status')->append(['item_image_thumb_small', 'refund_status_name']);
                },])->append(['status_name'])->findOrEmpty()->toArray();
                return $detail;
            }
        } else {
            throw new CommonException('REFUND_NOT_EXIST');
        }

    }



    /**
     * 根据订单ID  查询最新的售后详情
     * @param string $refund_no
     * @return array
     */
    public function latestByOrder(int $order_id)
    {
        $field = 'refund_id,site_id,order_id,member_id,refund_no,apply_money,money,status,create_time,transfer_time,refuse_reason,source,reason,remark,voucher';
        $detail = $this->model->where([['site_id', '=', $this->site_id], ['order_id', '=', $order_id]])->field($field)->append(['status_name'])->findOrEmpty()->toArray();
        return $detail;
    }


}
