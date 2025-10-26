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

namespace addon\home_service\app\service\core\order;

use addon\home_service\app\dict\notice\NoticeDict;
use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\dict\order\OrderLogDict;
use addon\home_service\app\model\order\Order;
use addon\home_service\app\model\order\OrderItem;
use addon\home_service\app\model\technician\Technician;
use app\dict\pay\PayDict;
use app\model\pay\Pay;
use app\service\core\notice\NoticeService;
use app\service\core\pay\CorePayService;
use app\service\core\weapp\CoreWeappDeliveryService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;
use addon\home_service\app\model\technician\TechnicianRest;
use think\Model;
use  addon\home_service\app\service\core\technician\CoreTechnicianService;
use addon\home_service\app\dict\technician\TechnicianDict;


/**
 * 订单
 * Class CoreOrderService
 */
class  CoreOrderService extends BaseCoreService
{

    use SubStatusTrait;

    private $scene;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    /**
     * 订单是否能退款
     * @param int $order_id
     */
    public function checkOrderIsEnableRefund(int $order_id, $is_enable_refund)
    {
        $order_item_info = (new OrderItem())->field('is_enable_refund')->where([['order_id', '=', $order_id], ['item_type', 'in', 'reservation,buy']])->findOrEmpty()->toArray();
        if (!empty($order_item_info)) $is_enable_refund = $order_item_info['is_enable_refund'];
        return $is_enable_refund;
    }

    /**
     * 订单关闭
     * @param Order $order
     * @return true
     */
    public function close(Order $order,$is_auto = true)
    {
        if (!in_array($order['order_status'], [OrderDict::WAIT_PAY, OrderDict::DISPATCH, OrderDict::WAIT_SERVICE, OrderDict::IN_SERVICE])) throw new CommonException('ORDER_NOT_ALLOW_CLOSE');

        Db::startTrans();
        try {
            //关闭相关的支付
            (new CorePayService())->closeByTrade($order['site_id'], $order['order_type'], $order['order_id']);

            $order->order_status = OrderDict::CLOSE;
            $order->close_time = time();
            $order->is_enable_refund = 0;
            $order->save();
            if ($is_auto){
                // 添加订单日志
                CoreOrderLogService::addLog($order['site_id'], $order->order_id, OrderLogDict::ORDER_CANCEL, 'member', $order->member_id, OrderDict::getStatus(OrderDict::CLOSE));
            }


            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 订单自动关闭
     * @param int $order_id
     * @return void
     */
    public function autoClose(int $order_id)
    {
        $order = (new Order())->where([['order_id', '=', $order_id]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != OrderDict::WAIT_PAY) return true;

        try {
            $this->close($order,false);
            // 添加订单日志
            CoreOrderLogService::addLog($order['site_id'], $order_id, OrderLogDict::ORDER_OVERTIME, 'system', 0, OrderDict::getStatus(OrderDict::CLOSE));
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 师傅选择
     * @param array $data
     * @return bool
     */
    public function selectTechnician($array = [])
    {

        $date = date('Y-m-d', $array['reserve_service_time_stamp']);//希望服务时间
        $hour = date('H:i', $array['reserve_service_time_stamp']);//希望服务时间
        $storeId = $array['store_id'] ?? 0; // 获取门店ID，默认0
        // 1. 构建查询指定时间有请假记录的人员ID
        $leaveQuery = (new TechnicianRest)
            ->where('date', $date)
            ->where('site_id', $array['site_id'] ?? 0)
            ->whereRaw("FIND_IN_SET(?, hour)", [$hour])
            ->whereNotNull('technician_id');
        // 只有当store_id不为0时才添加门店筛选条件
        // $leaveQuery->where('store_id', $storeId);
        // 执行查询获取请假人员ID
        $leaveIds = $leaveQuery
            ->distinct(true)
            ->column('technician_id');
        $where = [
            'real_name' => $array['real_name']??'',
            'site_id' => $array['site_id'],
            'category_id' => $array['category_id'],
            'status' => TechnicianDict::ON,
            'store_id' => $storeId,
            'no_in_technician_ids' => $leaveIds,
        ];
        return (new CoreTechnicianService)->getPage($where);
    }


    /**
     * 通用获取订单方法
     */
    private function getOrder($orderId)
    {
        $order = (new Order())->where('order_id', $orderId)
            ->with(['item' => fn($q) => $q->field('order_id, is_force_clock_in, is_force_departure')])
            ->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->item->isEmpty()) throw new CommonException('ORDER_ITEM_NOT_EXIST');
        return $order;
    }


    /**
     * 订单派单  (门店 + 总平台)
     * @param array $data
     * @return bool
     */
    public function orderDispatch(array $data)
    {
        $order = $this->getOrder($data['order_id']);
        if (!in_array($order->order_status, [OrderDict::WAIT_DISPATCH,OrderDict::WAIT_SERVICE])) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        Db::startTrans();
        try {
            $orderItem = $order->item->first();
            $order->order_status = OrderDict::WAIT_SERVICE;
            $order->dispatch_time = time();
            $order->technician_id = $data['technician_id'];
            $order->sub_status = self::getSubStatus($orderItem);
            $order->save();
            (new CoreOrderLogService())->addLog($order->site_id, $data['order_id'], OrderLogDict::ORDER_DISPATCH, $data['action_way'], $data['id'] ?? 0, OrderDict::getStatus(OrderDict::WAIT_SERVICE));

            // 发送通知
            (new NoticeService())->send($order->site_id, 'home_service_store_dispatch', ['order_id' => $data['order_id']]);

            event('ComputeOrderCommission', [
                'order_id' => $order->order_id,
                'order_type' => $order->order_type,
            ]);
            event('NotificationEvent', [
                'identity' => [NoticeDict::TECHNICIAN],
                'type' => NoticeDict::DISPATCH_SUCCESS,
                'notice_source' => NoticeDict::ORDER,
                'order_id' => $order->order_id,
                'technician_id' => $order->technician_id,
                'member_id' => $order->member_id,
                'site_id' => $order->site_id,
            ]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 订单转单    (门店 + 总平台)
     * @param array $data
     * @return bool
     */
    public function orderTransfer(array $data)
    {
        $order = $this->getOrder($data['order_id']);
        $orderItem = $order->item->first();
        if ($order->order_status != OrderDict::WAIT_SERVICE ||
            $order->sub_status != self::getSubStatus($orderItem)) {   //不等于初始状态时 不能重新派单
            throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        }
        if ($order->technician_id == $data['technician_id']) throw new CommonException('HOME_SERVICE_ORDER_TECHNCIAN_IS_COIMCIDE');
        Db::startTrans();
        try {
            $order->dispatch_time = time();
            $order->technician_id = $data['technician_id'];
            $order->save();
            (new CoreOrderLogService())->addLog($order->site_id, $data['order_id'], OrderLogDict::ORDER_TRANSFER, $data['action_way'], $data['id'] ?? 0, OrderDict::getStatus(OrderDict::WAIT_SERVICE));
            event('ComputeOrderCommission', [
                'order_id' => $order->order_id,
                'order_type' => $order->order_type,
            ]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 添加增项服务
     * @param array $data
     * @return bool
     */
    public function orderAddItem(array $data)
    {
        $order = (new Order())->where([['order_id', '=', $data['order_id']]])->findOrEmpty()->toArray();
        if (empty($order)) throw new CommonException('ORDER_NOT_EXIST');
        if ($order['order_status'] != OrderDict::IN_SERVICE) throw new CommonException('当前订单不可添加项目');

        if (empty($data['item_list'])) throw new CommonException('HOME_SERVICE_ORDER_ADDED_ITEM_NOT_EXIST');

        $order_item = (new OrderItem())->where([['order_id', '=', $data['order_id']], ['is_pay', '=', 0], ['item_type', '=', 'custom']])->findOrEmpty();
        $batch_id = 0;
        if (!$order_item->isEmpty()) throw new CommonException('HOME_SERVICE_ORDER_IS_HAVE_WAIT_PAY_ITEM');

        // 分离“服务费”项
        $service_fee = [];
        $others = [];

        foreach ($data['item_list'] as $item) {
            if (isset($item['is_service_fee']) && $item['is_service_fee'] == 1) {
                $service_fee[] = $item;
            } else {
                $others[] = $item;
            }
        }

        // 合并，把服务费放最后
        $item_list = array_merge($others, $service_fee);

        $add_data = [];
        $order_money = 0;
        foreach ($item_list as $value) {
            $money = 0;
            $num = $value['num'] ?? 1;
            $money += $value['price'] * $num;
            $order_money += $value['price'] * $num;
            $add_data[] = [
                'order_id' => $order['order_id'],
                'site_id' => $order['site_id'],
                'member_id' => $order['member_id'],
                'item_id' => 0,
                'item_type' => 'custom',
                'item_name' => $value['item_name'],
                'item_image' => $value['item_image'] ?? '',
                'price' => $value['price'],
                'num' => $num,
                'order_itme_commission_ratio' => $value['order_itme_commission_ratio'] ?? 100,
                'item_money' => $money,
                'out_trade_no' => '',
                'technician_id' => $order['technician_id'],
                'is_enable_refund' => 0,
                'is_service_fee' => $value['is_service_fee'] ?? 0,
            ];
        }
        Db::startTrans();
        try {
            (new Order())->where([['order_id','=',$order['order_id']]])->inc('order_money', $order_money)->update();
            $order_item = (new OrderItem())->saveAll($add_data);
            if (empty($batch_id)) $batch_id = $order_item[0]['order_item_id'];
            (new OrderItem())->whereIn('order_item_id', array_column($order_item->toArray(), 'order_item_id'))->update(['batch_id' => $batch_id]);

            //订单金额为0的话,要直接支付
            if ($money == 0) {
                (new CoreOrderPayService())->itemPay(['site_id' => $order['site_id'], 'trade_id' => $batch_id]);
            }

            $action_way = $data['action_way'] ?? 'system';
            $id = $data['id'] ?? 0;
            (new CoreOrderLogService())->addLog($order['site_id'], $order['order_id'], OrderLogDict::ORDER_ADD_PAY, $action_way, $id ?? 0, OrderDict::getStatus(OrderDict::IN_SERVICE));
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 删除订单项
     * @param array $data
     */
    public function orderItemDel(array $data)
    {
        $order_item_ids = $data['order_item_ids'] ?? [];
        $order_items = (new OrderItem())->where([['order_item_id', 'in', $order_item_ids]])->select()->toArray();
        if (empty($order_items)) throw new CommonException('HOME_SERVICE_ORDER_ADDED_ITEM_NOT_EXIST');

        $order_id = $order_items[0]['order_id'] ?? 0;
        $order = (new Order())->where([['order_id', '=', $order_id]])->findOrEmpty();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');

        if ($order->technician_id != $data['id']) throw new CommonException('HOME_SERVICE_ORDER_ITEM_NOT_DEL_AUTHORITY');
        if ($order->order_status != OrderDict::IN_SERVICE) throw new CommonException('HOME_SERVICE_ORDER_ITEM_NOT_DEL');

        foreach ($order_items as $value) {
            if ($value['item_type'] != 'custom') throw new CommonException('HOME_SERVICE_ORDER_ITEM_NOT_DEL');
            if ($value['is_pay'] == 1) throw new CommonException('HOME_SERVICE_ORDER_ITEM_NOT_DEL');
        }

        Db::startTrans();
        try {
            (new OrderItem())->where([['order_item_id', 'in', $order_item_ids]])->delete();

            $action_way = $data['action_way'] ?? 'system';
            $id = $data['id'] ?? 0;
            (new CoreOrderLogService())->addLog($order->site_id, $order->order_id, OrderLogDict::ORDER_DEL_PAY, $action_way, $id ?? 0, OrderDict::getStatus(OrderDict::IN_SERVICE));
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
        return true;
    }

    /**
     * 修改增项服务
     * @param array $data
     * @return bool
     */
    public function orderItemEdit(array $data)
    {
        $order = (new Order())->where([['order_id', '=', $data['order_id']]])->findOrEmpty()->toArray();
        if (empty($order)) throw new CommonException('ORDER_NOT_EXIST');
        if ($order['order_status'] != OrderDict::IN_SERVICE) throw new CommonException('HOME_SERVICE_ORDER_NOT_ADD_ORDER_ITEM');

        $order_item_model = (new OrderItem());
        Db::startTrans();
        try {
            $order_item_list = $order_item_model->where([['order_id', '=', $data['order_id']], ['item_type', '=', 'custom'], ['is_pay', '=', 0]])->select()->toArray();
            $dec_money = 0;
            if(!empty($order_item_list)){
                foreach ($order_item_list as $value){
                    $dec_money = bcadd($dec_money, $value['item_money'], 2);
                }
                $order_item_model->where([['order_item_id', 'in', array_column($order_item_list,'order_item_id')]])->delete();
                (new Order())->where([['order_id','=',$order['order_id']]])->dec('order_money', $dec_money)->update();
            }

            if (!empty($data['item_list'])) {
                // 分离“服务费”项
                $service_fee = [];
                $others = [];

                foreach ($data['item_list'] as $item) {
                    if (isset($item['is_service_fee']) && $item['is_service_fee'] == 1) {
                        $service_fee[] = $item;
                    } else {
                        $others[] = $item;
                    }
                }

                // 合并，把服务费放最后
                $item_list = array_merge($others, $service_fee);

                $add_data = [];
                $order_money = 0;
                foreach ($item_list as $value) {
                    $money = 0;
                    $num = $value['num'] ?? 1;
                    $money += $value['price'] * $num;
                    $order_money += $value['price'] * $num;
                    $add_data[] = [
                        'order_id' => $order['order_id'],
                        'site_id' => $order['site_id'],
                        'member_id' => $order['member_id'],
                        'item_id' => 0,
                        'item_type' => 'custom',
                        'item_name' => $value['item_name'],
                        'item_image' => $value['item_image'] ?? '',
                        'price' => $value['price'],
                        'num' => $num,
                        'item_money' => $money,
                        'out_trade_no' => '',
                        'technician_id' => $order['technician_id'],
                        'is_enable_refund' => 0,
                        'is_service_fee' => $value['is_service_fee'] ?? 0,
                    ];
                }
                (new Order())->where([['order_id','=',$order['order_id']]])->inc('order_money', $order_money)->update();
                $order_item = (new OrderItem())->saveAll($add_data);
                if (empty($batch_id)) $batch_id = $order_item[0]['order_item_id'];
                (new OrderItem())->whereIn('order_item_id', array_column($order_item->toArray(), 'order_item_id'))->update(['batch_id' => $batch_id]);

                //订单金额为0的话,要直接支付
                if ($money == 0) {
                    (new CoreOrderPayService())->itemPay(['site_id' => $order['site_id'], 'trade_id' => $batch_id]);
                }

                $action_way = $data['action_way'] ?? 'system';
                $id = $data['id'] ?? 0;
                (new CoreOrderLogService())->addLog($order['site_id'], $order['order_id'], OrderLogDict::ORDER_EDIT_PAY, $action_way, $id ?? 0, OrderDict::getStatus(OrderDict::IN_SERVICE));
            }

            $order_item_money_total = $order_item_model
                ->where([['order_id', '=', $data['order_id']]])
                ->fieldRaw('SUM(item_money) - SUM(discount_money) as total_amount')
                ->findOrEmpty();
            if (!$order_item_money_total->isEmpty()){
                (new Order())->where([['order_id','=',$order['order_id']]])->update(['order_money' => $order_item_money_total->total_amount]);
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
        return true;
    }


    /**
     * 服务完成
     * @param array $data
     */
    public function orderServiceFinish(array $data)
    {

        $order = $this->model->where('order_id', $data['order_id'])->find();
        if ($order->isEmpty()) throw new CommonException('ORDER_NOT_EXIST');
        if ($order->order_status != OrderDict::WAIT_CHECK) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        $no_pay_count = (new OrderItem())->where([['order_id', '=', $data['order_id']], ['pay_time', '=', 0]])->count();
        if ($no_pay_count > 0) throw new CommonException('当前订单有项目未支付，请先支付');
        Db::startTrans();
        try {
            $order->order_status = OrderDict::FINISH;
            $order->finish_time = time();
            $order->save();
            (new OrderItem())->where([['order_id', '=', $data['order_id']]])->update(['is_enable_refund' => 0]);
            (new CoreOrderLogService())->addLog($order->site_id, $data['order_id'], OrderLogDict::ORDER_FINISH, $data['action_way'] ?? 'system', $data['id'] ?? 0, OrderDict::getStatus(OrderDict::FINISH));
            (new Technician())->where([['id', '=', $order->technician_id]])->inc('order_num', 1)->inc('achievement', $order->pay_money)->update();
            (new \addon\home_service\app\service\core\store\CoreTechnicianService())::addStat($order->site_id, $order->store_id, $order->technician_id, [], ['order_num' => 1]);
            event('SettlementOrderCommission', [
                'order_id' => $order->order_id,
                'order_type' => $order->order_type,
            ]);
            event("CheckTechnicianLevelUpgrade", [
                'technician_id' => $order->technician_id,
                'site_id' => $order->site_id,
            ]);
            Db::commit();
            // todo 师傅升级业务
            //            $order_money = (new OrderItem())->where([['order_id', '=', $data['order_id']], ['pay_time', '>', 0]])->sum('item_money');
            //            // 订单完成发放积分成长值
            //            CoreMemberService::sendGrowth($order->site_id, $order->member_id, 'o2o_buy_goods', [
            //                'order_money' => $order_money,
            //                'from_type' => 'o2o_buy_order',
            //                'related_id' => $order['order_id']
            //            ]);
            //            CoreMemberService::sendGrowth($order->site_id, $order->member_id, 'o2o_buy_order', [
            //                'from_type' => 'o2o_buy_order',
            //                'related_id' => $order['order_id']
            //            ]);
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 订单信息
     * @param int $site_id
     * @param int $order_id
     * @return array
     */
    public function orderInfo(int $site_id, int $order_id)
    {
        return (new Order())->where([
            ['site_id', '=', $site_id],
            ['order_id', '=', $order_id]
        ])->field('*')->findOrEmpty()->toArray();
    }

    /**
     * 订单信息
     * @param int $site_id
     * @param int $trade_id
     * @return array
     */
    public function orderItemList(int $site_id, int $trade_id)
    {
        return (new OrderItem())->where([
            ['site_id', '=', $site_id],
            ['item_type', '=', 'custom'],
            ['batch_id', '=', $trade_id]
        ])->field('*')->select()->toArray();
    }

    /**
     * 微信小程序 发货信息录入接口
     * @param $order_id
     * @return string
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     */
    public function orderShippingUploadShippingInfo($order_id)
    {
        try {

            $field = 'site_id, order_id, order_no, out_trade_no, member_id';

            $info = $this->model->where([
                ['order_id', '=', $order_id],
                ['order_type', '=', OrderDict::ORDER_TYPE_ORDER],
                ['order_status', '=', OrderDict::FINISH]
            ])->field($field)->with(['item' => function ($query) {
                $query->field('order_item_id, order_id, member_id, item_id, item_type, item_name, price, num, item_money');
            }, 'member' => function ($query) {
                $query->field('member_id, weapp_openid');
            }])->findOrEmpty()->toArray();

            // 订单不存在
            if (empty($info)) {
                return '';
            }

            $pay_model = new Pay();
            $where = array(
                ['out_trade_no', '=', $info['out_trade_no']]
            );
            $pay_info = $pay_model->where($where)->field('id,type')->findOrEmpty()->toArray();

            if (empty($pay_info)) {
                return '';
            }

            // 订单未使用微信支付，无须处理
            if ($pay_info['type'] != PayDict::WECHATPAY) {
                return '订单未使用微信支付';
            }

            $weapp_delivery_service = new CoreWeappDeliveryService();

            // 检测微信小程序是否已开通发货信息管理服务
            $is_trade_managed = $weapp_delivery_service->isTradeManaged($info['site_id']);

            if (empty($is_trade_managed['is_trade_managed'])) {
                return '发货信息录入接口，报错：' . $is_trade_managed["errmsg"];
            }

            // 设置消息跳转路径设置接口
            $result_jump_path = $weapp_delivery_service->setMsgJumpPath($info['site_id'], 'o2o_order');
            if ($result_jump_path['errcode'] != 0) {
                return '设置消息跳转路径设置接口，报错：' . $result_jump_path["errmsg"];
            }

            $shipping_list = [];
            $first_shipping_info = [];

            $delivery_mode = 1; // 发货模式，发货模式枚举值：1、UNIFIED_DELIVERY（统一发货）2、SPLIT_DELIVERY（分拆发货） 示例值: UNIFIED_DELIVERY

            foreach ($info['item'] as $k => $v) {

                $item = [
                    'tracking_no' => '', // 物流单号，物流快递发货时必填，示例值: 323244567777 字符字节限制: [1, 128]
                    'express_company' => '', // 物流公司编码，快递公司ID，参见「查询物流公司编码列表」，物流快递发货时必填， 示例值: DHL 字符字节限制: [1, 128]
                    'item_desc' => str_sub($v['item_name'], 90) . '*' . $v['num'], // 商品信息，例如：微信红包抱枕*1个，限120个字以内
                    'contact' => [
                        'consignor_contact' => '',
                        'receiver_contact' => ''
                    ]
                ];

                $shipping_list[] = $item;
                if ($k == 0) {
                    $first_shipping_info = $item;
                }
            }

            // 统一发货，只能有一个物流信息，拼装商品信息
            if (count($shipping_list) > 1) {
                foreach ($shipping_list as $k => $v) {
                    if ($k > 0) {
                        $first_shipping_info['item_desc'] .= ',' . $v['item_desc'];
                    }
                }
            }

            $data = [
                'out_trade_no' => $info['out_trade_no'],
                'logistics_type' => 3, // 物流模式，发货方式枚举值：1、实体物流配送采用快递公司进行实体物流配送形式 2、同城配送 3、虚拟商品，虚拟商品，例如话费充值，点卡等，无实体配送形式 4、用户自提
                'delivery_mode' => $delivery_mode, // 发货模式，发货模式枚举值：1、UNIFIED_DELIVERY（统一发货）2、SPLIT_DELIVERY（分拆发货） 示例值: UNIFIED_DELIVERY
                // 同城配送没有物流信息，只能传一个订单
                'shipping_list' => $delivery_mode == 1 ? [$first_shipping_info] : $shipping_list, // 物流信息列表，发货物流单列表，支持统一发货（单个物流单）和分拆发货（多个物流单）两种模式，多重性: [1, 10]
                'weapp_openid' => $info['member']['weapp_openid'], // 用户标识，用户在小程序appid下的唯一标识。 下单前需获取到用户的Openid 示例值: oUpF8uMuAJO_M2pxb1Q9zNjWeS6o 字符字节限制: [1, 128]
                'is_all_delivered' => true // 分拆发货模式时必填，用于标识分拆发货模式下是否已全部发货完成，只有全部发货完成的情况下才会向用户推送发货完成通知。示例值: true/false
            ];

            $weapp_delivery_service->uploadShippingInfo($info['site_id'], $data);
        } catch (\Exception $e) {
            Log::write('o2o订单小程序发货信息录入失败' . $e->getMessage() . $e->getFile() . $e->getLine());
        }
    }

    /**
     * 异常订单处理
     * @param int $order_id
     * @return bool
     */
    public function abnormalOrder($order_id)
    {
        Db::startTrans();
        try {
            $order = (new Order())->where([['order_id', '=', $order_id]])->findOrEmpty();
            if ($order->isEmpty()) return true;
            if ($order->order_status != OrderDict::WAIT_SERVICE) return true;

            $order->is_abnormal = 1;
            $order->save();

            if (!empty($order->technician_id)) {
                event('NotificationEvent', [
                    'identity' => [NoticeDict::TECHNICIAN],
                    'type' => NoticeDict::TIMEOUT,
                    'notice_source' => NoticeDict::ORDER,
                    'order_id' => $order->order_id,
                    'technician_id' => $order->technician_id,
                    'member_id' => $order->member_id,
                    'site_id' => $order->site_id,
                ]);
            }
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            return false;
        }
    }


    /**
     * 待确定删除逻辑之后再细化
     * @param $order_ids
     * @return bool|void
     */
    public function delete($order_ids, $site_id)
    {
        if (empty($order_ids)) throw new CommonException('HOME_SERVICE_ORDER_NOT_FOUND');
        $status_list = $this->model->field('order_id, order_status,order_no,create_time')->whereIn('order_id', $order_ids)->with(['item' => function ($query) {
            $query->field('site_id, order_id, goods_id');
        }])->select()->toArray();

        $error_order_str = '';
        foreach ($status_list as $item) {
            if ($item['order_status'] == OrderDict::CLOSE) {
                continue;
            }
            $error_order_str .= $item['order_no'] . ',';
        }
        $error_order_str = rtrim($error_order_str, ',');
        if (!empty($error_order_str)) {
            $error_str = sprintf(get_lang('HOME_SERVICE_ORDER_DELETE_STATUS_ERROR'), $error_order_str);
            throw new CommonException($error_str);
        }

        Db::startTrans();
        try {
            $this->model->whereIn('order_id', $order_ids)->update(['delete_time' => time()]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 订单关闭  (会员 + 总平台)
     * @param array $data
     * @return bool
     */
    public function orderClose(array $data)
    {
        $order = $this->getOrder($data['order_id']);
        if (!in_array($order->order_status, [OrderDict::WAIT_PAY])) throw new CommonException('HOME_SERVICE_ORDER_IS_NOT_SERVICE');
        Db::startTrans();
        try {
            $order->order_status = OrderDict::CLOSE;
            $order->close_time = time();
            $order->save();
            (new CoreOrderLogService())->addLog($order->site_id, $data['order_id'], OrderLogDict::ORDER_CANCEL, $data['action_way'], $data['id'] ?? 0, OrderDict::getStatus(OrderDict::CLOSE));
            Db::commit();
            return true;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }


    /**
     * 订单结算
     * @param int $order_id
     * @return bool
     */
    public function orderSettlement($order_id)
    {
        (new Order())->where([['order_id', '=', $order_id]])->update(['is_settlement' => 1]);
        return true;
    }

    /**
     * 订单催单
     * @param int $order_ids
     * @param int $site_id
     * @return bool
     */
    public function reminder($order_ids,$site_id)
    {
        $order_list = (new Order())->where([
            ['order_id', 'in', $order_ids]
        ])->select();
        if (!empty($order_list)){
            foreach ($order_list as $value){
                if (!empty($value->technician_id) && in_array($value->order_status,[OrderDict::WAIT_SERVICE]) ) {
                    event('NotificationEvent', [
                        'identity' => [NoticeDict::TECHNICIAN],
                        'type' => NoticeDict::REMINDER,
                        'notice_source' => NoticeDict::ORDER,
                        'order_id' => $value->order_id,
                        'technician_id' => $value->technician_id,
                        'member_id' => $value->member_id,
                        'site_id' => $site_id,
                    ]);
                }
            }
        }

        return true;

    }




}
