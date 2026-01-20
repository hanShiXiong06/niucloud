<?php

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\dict\coupon\CouponMemberDict;
use addon\tk_jhkd\app\dict\delivery\DeliveryDict;
use addon\tk_jhkd\app\dict\order\CommissionStatusDict;
use addon\tk_jhkd\app\dict\order\JhkdOrderAddDict;
use addon\tk_jhkd\app\dict\order\JhkdOrderDict;
use addon\tk_jhkd\app\dict\order\OrderRefundLogDict;
use addon\tk_jhkd\app\job\commission\TkPartnerCommissionOrder;
use addon\tk_jhkd\app\job\notice\Webhook;
use addon\tk_jhkd\app\job\order_event\OrderDeliveryCancel;
use addon\tk_jhkd\app\job\order_event\OrderPayAfter;
use addon\tk_jhkd\app\job\order_event\OrderRefundAfter;
use addon\tk_jhkd\app\job\tkfenxiao\UpdateOrderJob;
use addon\tk_jhkd\app\listener\order\delivery\DeliveryCancelOrderListener;
use addon\tk_jhkd\app\model\coupon\CouponMember;
use addon\tk_jhkd\app\model\fenxiao\FenxiaoOrder;
use addon\tk_jhkd\app\model\order\OrderAdd;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use addon\tk_jhkd\app\model\OrderDeliveryReal;
use addon\tk_jhkd\app\model\TkjhkdBrand;
use addon\tk_jhkd\app\model\tkjhkdorder\Tkjhkdorder;
use app\dict\pay\PayDict;
use app\dict\pay\RefundDict;
use app\model\pay\Pay;
use app\model\pay\Refund;
use app\service\core\notice\NoticeService;
use app\service\core\pay\CorePayService;
use app\service\core\pay\CoreRefundService;
use app\service\core\sys\CoreConfigService;
use core\base\BaseApiService;
use core\exception\CommonException;
use core\util\Snowflake;
use Exception;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

/**
 * 聚合快递订单服务层
 * Class OrderService
 * @package addon\tk_jhkd\service\core\order
 */
class OrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new TkjhkdOrder();
        $this->deliveryModel = new OrderDelivery();
        $this->yidaService = new YidaService();
        $this->config = $this->getConfig();
    }

    public function checkAddPay()
    {
        $orderModel = new OrderAdd();
        $res = $orderModel->where([['order_status', '=', JhkdOrderAddDict::WAIT_PAY]])
            ->where(['member_id' => $this->member_id])
            ->findOrEmpty();
        if ($res->isEmpty()) {
            return ['type' => 'success', 'page' => ''];
        } else {
            return ['type' => 'redirect', 'page' => '/addon/tk_jhkd/pages/orderaddlist'];
        }
    }

    /**
     * $order_id
     * 快递渠道发单，三方下单
     */
    public function sendOrder($order_id)
    {
        Db::startTrans();
        try {
            $order_model = $this->model;
            $order_info = $order_model->where([['order_id', '=', $order_id]])->findOrEmpty();
            if (empty($order_info))
                throw new CommonException('ORDER_NOT_EXIST');
            if ($order_info['is_send'] == 1) throw new CommonException('已经发单');
            $ordeDeliveryInfo = (new OrderDelivery())->where(['order_id' => $order_id])->findOrEmpty();
            if ($ordeDeliveryInfo->isEmpty()) throw new Exception('未找到运单数据');
            $params = $ordeDeliveryInfo;
            $send_address = json_decode($params['start_address'], true);
            $sendName = explode("-", $send_address['address']);
            $receive_address = json_decode($params['end_address'], true);
            $receiveName = explode("-", $receive_address['address']);
            $data = [
                "platform" => $ordeDeliveryInfo['platform'],
                "deliveryType" => $params['delivery_type'],
                "customer_type" => $params['customer_type'],
                "thirdNo" => $params['order_id'],
                "senderProvince" => $sendName[0],
                "senderCity" => $sendName[1],
                "senderDistrict" => $sendName[2],
                "senderAddress" => $send_address['full_address'],
                "senderName" => $send_address['name'],
                "senderMobile" => $send_address['mobile'],
                "senderTel" => $send_address['telephone'] ?? '',
                "receiveProvince" => $receiveName[0],
                "receiveCity" => $receiveName[1],
                "receiveDistrict" => $receiveName[2],
                "receiveAddress" => $receive_address['full_address'],
                "receiveName" => $receive_address['name'],
                "receiveMobile" => $receive_address['mobile'],
                "receiveTel" => $receive_address['telephone'] ?? '',
                "weight" => $params['weight'],
                "vloumLong" => $params['long'],
                "vloumWidth" => $params['width'],
                "vloumHeight" => $params['height'],
                "vloum" => $params['long'] * $params['width'] * $params['height'],
                "guaranteeValueAmount" => $params['bj_price'] > 0 ? $params['bj_price'] : null,
                "goods" => $params['goods'],
                "packageCount" => $params['package_count'],
                "remark" => $params['remark'],
                "deliveryBusiness" => $params['delivery_business'],
                "onlinePay" => $params['online_pay'],
                "payMethod" => $params['online_pay'] == 'N' ? 10 : '',
                "channelId" => $params['channel_id'] ?? ''
            ];
            $submitInfo = event('DeliverySendOrder', ['site_id' => $this->site_id, 'data' => $data]);
            $submitInfo = $submitInfo [0];
            if (isset($submitInfo['type']) && $submitInfo['type'] == 'error') {
                //下单失败主动发起退款
                $data = [
                    'id' => $order_info['id'],
                    'close_reason' => $submitInfo['msg'] ?? '三方平台下单失败，请重新下单'
                ];
                (new OrderLogService())->writeOrderLog(
                    $order_info['site_id'],
                    $order_info['order_id'],
                    JhkdOrderDict::REFUNDING,
                    $submitInfo['msg'] ?? '三方平台下单失败，发起退款'
                );
                event('CancelOrder', $data);
                Db::commit();
                return false;
            }
            $order_no = $submitInfo['orderNo'] ?? '';
            $delivery_id = $submitInfo['deliveryId'] ?? '';
            $orderDeliveryInfo = $this->deliveryModel->where(['order_id' => $order_id])->findOrEmpty();
            if ($orderDeliveryInfo->isEmpty()) {
                event('DeliveryCancelOrder', ['site_id' => $this->site_id, 'data' => [
                    'order_no' => $order_no,
                    'task_id' => $orderDeliveryInfo['task_id'],
                    'order_id' => $order_id,
                ]]);
                //取消下单
            } else {
                $orderDeliveryInfo->save(['order_no' => $order_no, 'delivery_id' => $delivery_id]);
                $order_info->save(['is_send' => 1]);
                (new OrderLogService())->writeOrderLog(
                    $order_info['site_id'],
                    $order_info['order_id'],
                    JhkdOrderDict::FINISH_PICK,
                    '成功发单'
                );
                Db::commit();
            }

        } catch (Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    public function create_no(string $prefix = '', string $tag = '')
    {

        $data_center_id = 1;
        $machine_id = 2;
        $snowflake = new Snowflake($data_center_id, $machine_id);
        $id = $snowflake->generateId();
        $no = $prefix . date('Ymd') . $tag . $id;
        $cacheKey = 'unique_no_' . $no;
        if (Cache::get($cacheKey)) {
            return create_no($prefix, $tag);
        } else {
            Cache::set($cacheKey, true, 60); // 设置过期时间为 60 秒
            return $no;
        }
    }

    /**
     * 快递预下单
     * @param $params
     * @return array
     * @throws Exception
     */
    public function preOrder($params)
    {
        if (!$this->config) throw new CommonException('基础配置未完成，请联系管理员');
        $startArr = explode("-", $params['startAddress']['address']);
        $endArr = explode("-", $params['endAddress']['address']);
        //必填参数校验
        $data = [
            "receiveAddress" => $params['endAddress']['full_address'],
            "receiveMobile" => $params['endAddress']['mobile'],
            "receiveTel" => "",
            "receiveName" => $params['endAddress']['name'],
            "receiveProvince" => $endArr[0],
            "receiveCity" => $endArr[1],
            "receiveDistrict" => $endArr[2],
            "senderAddress" => $params['startAddress']['full_address'],
            "senderMobile" => $params['startAddress']['mobile'],
            "senderTel" => "",
            "senderName" => $params['startAddress']['name'],
            "senderProvince" => $startArr[0],
            "senderCity" => $startArr[1],
            "senderDistrict" => $startArr[2],
            "goods" => $params['goods'],
            "packageCount" => $params['packageCount'],
            "weight" => $params['weight'],
            "vloumLong" => $params['vloumLong'],
            "vloumWidth" => $params['vloumWidth'],
            "vloumHeight" => $params['vloumHeight'],
            "guaranteeValueAmount" => $params['guaranteeValueAmount'],
            "customerType" => $params['customerType'],
            "onlinePay" => "ALL",
            "payMethod" => 10,
            "thirdNo" => "TD965412356"
        ];
        $resInfo = event('DeliveryPreOrder', ['site_id' => $this->site_id, 'data' => $data]);
        $resInfo = $resInfo[0];
        $dataInfo = [];
        foreach ($resInfo['data'] as $k1 => $v1) {
            $keysToRemove = ['preDeliveryFee', 'preOrderFee', 'price', 'originalPrice'];
            $v1 = array_diff_key($v1, array_flip($keysToRemove));
            $dataInfo[] = $v1;
        }
        //增加验证key
        $key = md5(create_no() . time());
        Cache::set($key, $resInfo, 180);
        $data = [
            'key' => $key,
            'list' => $dataInfo
        ];
        return $data;
    }

    /**
     * @param $params
     * 创建系统订单
     */
    public
    function createOrder($params)
    {
        if (!isset($params['delivery_info']['deliveryType'])) throw new CommonException('请选择正确快递渠道');
        //进行订单验证
        $cacheData = Cache::get($params['key']);
        if (!$cacheData) {
            throw new CommonException('报价失效,请重新获取运单报价');
        } else {
            $selectData = $cacheData['data'][$params['delivery_index']];
            if ($selectData['showPrice'] != $params['showPrice']) {
                throw new CommonException('非法的价格');
            } else {
                Cache::delete($params['key']);
            }
        }
        Db::startTrans();
        try {
            $orderData = [
                "site_id" => $this->site_id,
                "member_id" => $this->member_id,
                'ip' => request()->ip() ?? '',
                "order_from" => $this->channel,
                "order_id" => $this->create_no(),
                "order_money" => $params['showPrice'],
                "pay_money" => $params['showPrice'],
                "remark" => $params['remark'],
                "pub_id" => $params['pub_id'],
                "sid" => $params['sid'],
            ];

            //进行优惠券验证及使用
            if ($params['coupon_id'] > 0) {
                $coupon_info = (new CouponMember())->where(['coupon_id' => $params['coupon_id'], 'member_id' => $this->member_id, 'status' => CouponMemberDict::WAIT_USE])->findOrEmpty();
                if ($coupon_info->isEmpty()) throw new CommonException('请重新选择优惠券');
                //进行金额验证
                if ($coupon_info['min_condition_money'] > $orderData['order_money']) throw new CommonException('未达到最低使用门槛');
                //更新优惠券使用状态
                $coupon_info->save(['use_time' => time(), 'status' => CouponMemberDict::USED]);
                $orderData['order_discount_money'] = $coupon_info['price'];
                $c_money = $orderData['order_money'] - $orderData['order_discount_money'];
                if ($c_money > 0) {
                    $orderData['order_discount_money'] = $coupon_info['price'];
                    $orderData['pay_money'] = $c_money;
                } else {
                    $orderData['order_discount_money'] = $orderData['order_money'] - 0.01;
                    $orderData['pay_money'] = 0.01;
                }
                $orderData['pay_money'] = $orderData['order_money'] - $orderData['order_discount_money'];
                $orderData['coupon_value'] = $coupon_info;
            }
            $deliveryData = [
                "start_address" => $params['startAddress'],
                "end_address" => $params['endAddress'],
                "platform" => $cacheData['driver'],
                "member_id" => $this->member_id,
                "weight" => $params['weight'],
                "long" => $params['vloumLong'],
                "width" => $params['vloumWidth'],
                "height" => $params['vloumHeight'],
                "goods" => $params['goods'],
                "package_count" => $params['packageCount'],
                "bj_price" => $params['guaranteeValueAmount'],
                "order_id" => $orderData['order_id'],
                "delivery_status" => 1,
                "delivery_type" => $params['delivery_info']['deliveryType'],
                "delivery_business" => $params['delivery_info']['deliveryBusiness'],
                "online_pay" => $params['delivery_info']['onlinePay'],
                "customer_type" => $params['customerType'],
                "pay_method" => $params['pay_method'],
                "remark" => $params['remark'],
                "channel_id" => $params['delivery_info']['channelId'],
                "price_rule" => json_encode($selectData['price_rule']),
                "original_rule" => json_encode($selectData['original_rule']),
            ];
            (new OrderDelivery())->create($deliveryData);
            $info = $this->model->create($orderData);
            //添加订单支付表
            //(new CorePayService())->create($orderData['site_id'], PayDict::MEMBER, $orderData['member_id'], $orderData['order_money'], JhkdOrderDict::getOrderType()['type'], $info['id'], "快递下单付款");
            //同步创建计费
            (new OrderDeliveryReal())->create([
                'order_id' => $info['order_id'],
                "weight" => $params['weight'],
            ]);
            //写入分销订单库
            (new FenxiaoOrder())->save([
                'order_id' => $info['order_id'],
                'site_id' => $this->site_id,
                'member_id' => $this->member_id,
                'type' => '0',
                'status' => 0,
                'create_time' => time()
            ]);
            (new OrderLogService())->writeOrderLog(
                $orderData['site_id'],
                $orderData['order_id'],
                JhkdOrderDict::WAIT_PAY,
                '订单创建',
                PayDict::MEMBER,
                $this->member_id
            );
            $createOrder =[
                'order_data' => [
                    'id' => '',
                    'site_id' => $orderData['site_id'],
                    'member_id' => $orderData['member_id'],
                    'order_id' => $orderData['order_id'],
                    'order_no' => '',
                    'order_money' => $orderData['pay_money'],
                    'original_price' => $orderData['order_money'],
                ]
            ];
            event('AfterKdOrderCreate', $createOrder);
            Db::commit();
            $fenxiao_data= [
                'site_id' => $this->site_id,
                'member_id' => $this->member_id,
                'order_id'=>$orderData['order_id'],
                'key' => 'tk_jhkd_order',
                'status' => 0,  //0创建 1支付  2完成  -1取消
                'goods_list' => [
                    [
                        'name' => '快递产品下单',//产品名称
                        'image' => '',//主图
                        'order_money' => $orderData['order_money'],//订单金额
                        'pay_money' => $orderData['pay_money'],//实付金额
                        'num' => 1,//数量
                        'commission' => $orderData['pay_money'] ,//分佣金额,
                        'team_commission' => $orderData['pay_money'],//参与团队分红的金额
                        'agent_commission' => $orderData['pay_money'],//参与代理商分红的金额
                    ]
                ],
                'create_time' => time(),//创建时间
            ];
            UpdateOrderJob::dispatch(['data' => $fenxiao_data]);
            return [
                'trade_type' => 'jhkdOrderPay',//type
                'trade_id' => $info['id']//id
            ];
        } catch (Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 订单支付
     * @param array $pay_info
     * @return true
     */
    public function pay(array $pay_info)
    {
        try {
            Db::startTrans();
            $trade_id = $pay_info['trade_id'] ?? 0;
            $order_model = $this->model;
            $order_info = $order_model->where([['site_id', '=', $pay_info['site_id']], ['id', '=', $trade_id]])->findOrEmpty();
            if (empty($order_info))
                throw new CommonException('ORDER_NOT_EXIST');
            if ($order_info['order_status'] != JhkdOrderDict::WAIT_PAY) return true;
            $order_data = [
                'pay_time' => time(),
                'order_status' => JhkdOrderDict::FINISH_PAY,
                'is_enable_refund' => 1, // 是否允许退款
                'out_trade_no' => $pay_info['out_trade_no']//支付后的交易流水号
            ];
            $order_info->save($order_data);
            (new OrderLogService())->writeOrderLog(
                $order_info['site_id'],
                $order_info['order_id'],
                JhkdOrderDict::FINISH_PAY,
                '订单支付成功',
                PayDict::MEMBER,
                $this->member_id
            );
            //进行开店宝接入
            $key = 'tk_jhkd_order';
            $config = (new CommonService())->getPartnerCommissionConfig($order_info['site_id'])[$key] ?? [];
            if (isset($config['is_use']) && $config['is_use'] == 1) {
                $create_data = [
                    'site_id' => $order_info['site_id'],
                    'member_id' => $order_info['member_id'],
                    'name' => '快递推广订单',
                    'order_id' => $order_info['order_id'],
                    'key' => $key,
                    'order_money' => $order_info['pay_money'],
                    'profit' => $order_info['pay_money'] * $config['rate'] / 100,
                    'status' => 1,
                ];
                TkPartnerCommissionOrder::dispatch(['data' => $create_data]);
            }
            Db::commit();
            OrderPayAfter::dispatch([
                'order_data' => [
                    'id' => $order_info['id'],
                    'site_id' => $pay_info['site_id'],
                    'member_id' => $order_info['member_id'],
                    'order_id' => $order_info['order_id'],
                    'order_no' => '',
                    'order_money' => $order_info['pay_money'],
                    'original_price' => $order_info['order_money'],
                ]
            ]);
            event('DeliveryUploadShipping', ['site_id' => $order_info['site_id'], 'order_id' => $order_info['order_id']]);
            (new NoticeService())->send($order_info['site_id'], 'tk_jhkd_order_pay', ['order_id' => $order_info['order_id']]);
            $fenxiao_data= [
                'site_id' => $order_info['site_id'],
                'member_id' => $order_info['member_id'],
                'order_id'=>$order_info['order_id'],
                'key' => 'tk_jhkd_order',
                'status' => 1,  //0创建 1支付  2完成  -1取消
                'goods_list' => [
                    [
                        'name' => '快递产品下单',//产品名称
                        'image' => '',//主图
                        'order_money' => $order_info['order_money'],//订单金额
                        'pay_money' => $order_info['pay_money'],//实付金额
                        'num' => 1,//数量
                        'commission' => $order_info['pay_money'] ,//分佣金额,
                        'team_commission' => $order_info['pay_money'],//参与团队分红的金额
                        'agent_commission' => $order_info['pay_money'],//参与代理商分红的金额
                    ]
                ],
                'create_time' => strtotime($order_info['create_time']),//创建时间
            ];
            UpdateOrderJob::dispatch(['data' => $fenxiao_data]);

            $sendauto = $this->getConfig()['autosend'];
            if ($sendauto == 1) {
                (new OrderService())->sendOrder($order_info['order_id']);
            }
            $config = (new CommonService())->getConfig($order_info['site_id']);
            $text = '订单号：' . $order_info['order_id'] . ',已经支付成功，订单金额：' . $order_info['pay_money'] . '元，请及时关注是否存在超重补差价';
            Webhook::dispatch(['config' => $config, 'text' => $text]);
            return true;
        } catch (Exception $e) {
            Db::rollback();
            Log::write('支付回调失败' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 获取配置信息
     * @param $site_id
     * @param string $config_key
     */
    public function getConfig()
    {
        $info = (new CoreConfigService())->getConfig($this->site_id, 'tk_jhkd_config');
        if (empty($info)) {
            $info['value'] = ['autosend' => 0];
        }
        return $info['value'];
    }

    /**
     * 补单订单支付
     * @param array $pay_info
     * @return true
     */
    public
    function payAdd(array $pay_info)
    {
        Db::startTrans();
        try {
            $trade_id = $pay_info['trade_id'] ?? 0;
            $order_model = new OrderAdd();
            $order_info = $order_model->where([['site_id', '=', $pay_info['site_id']], ['id', '=', $trade_id]])->findOrEmpty();
            if (empty($order_info))
                throw new CommonException('ORDERADD_NOT_EXIST');
            if ($order_info['order_status'] != JhkdOrderAddDict::WAIT_PAY) return true;
            $order_data = [
                'pay_time' => time(),
                'order_status' => JhkdOrderAddDict::FINISH_PAY,
                'out_trade_no' => $pay_info['out_trade_no']//支付后的交易流水号
            ];
            $order_info->save($order_data);
            //补差价发货信息管理
            event('DeliveryUploadShippingAdd', ['site_id' => $order_info['site_id'], 'order_id' => $order_info['order_id']]);
            (new OrderLogService())->writeOrderLog(
                $order_info['site_id'],
                $order_info['order_id'],
                JhkdOrderDict::FINISH_PAY,
                '补差价支付成功',
                PayDict::MEMBER,
                $this->member_id
            );
            Db::commit();
            return true;
        } catch (Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    public
    function refund($data)
    {
        Db::startTrans();
        try {
            $this->model = new Refund();
            $this->OrderModel = new Tkjhkdorder();
            $this->PayModel = new Pay();
            $refund_no = $data['refund_no'];
            $trade_type = $data['trade_type'];
            $trade_id = $data['trade_id'];
            $site_id = $data['site_id'];
            $payInfo = $this->PayModel->where(['site_id' => $site_id, 'trade_id' => $trade_id, 'trade_type' => JhkdOrderDict::getOrderType()['type']])
                ->where([['status', '<>', -1]])->findOrEmpty();
            if ($payInfo->isEmpty()) throw new CommonException('select pay is empty');
            $this->PayModel->where(['site_id' => $site_id, 'trade_id' => $trade_id, 'trade_type' => JhkdOrderDict::getOrderType()['type']])
                ->update([
                    'status' => -1,
                    'type' => '',
                    'pay_time' => ''
                ]);
            $refundInfo = $this->model->where(['site_id' => $site_id, 'refund_no' => $refund_no, 'trade_id' => $trade_id])->findOrEmpty();
            if ($refundInfo->isEmpty()) throw new CommonException('select refund is empty');
            $this->model->where(['site_id' => $site_id, 'refund_no' => $refund_no, 'trade_id' => $trade_id])->update(['trade_type' => $trade_type]);
            $orderInfo = $this->OrderModel->where(['site_id' => $site_id, 'id' => $trade_id])->findOrEmpty();
            if ($orderInfo->isEmpty()) throw new CommonException('select order is empty');
            //增加优惠券退款的方式
            if ($orderInfo['coupon_value']) {
                $couponInfo = $orderInfo['coupon_value'];
                $couponMember = (new CouponMember())->where(['id' => $couponInfo['id']])->findOrEmpty();
                if (!$couponMember->isEmpty()) {
                    $couponMember->save(['status' => CouponMemberDict::WAIT_USE, 'use_time' => 0]);
                }
            }
            $this->OrderModel->where(['site_id' => $site_id, 'id' => $trade_id])->update([
                'refund_status' => JhkdOrderDict::REFUND_COMPLETED,
                'is_enable_refund' => 0,
                'close_time' => time(),
                'pay_time' => '',
                'order_status' => JhkdOrderDict::CLOSE,
                'is_send' => 0,
            ]);
            //查找未支付补差价
            $addModel = new OrderAdd();
            $addorder = $addModel->where(['site_id' => $site_id, 'order_id' => $orderInfo['order_id']])->findOrEmpty();
            if (!$addorder->isEmpty()) {
                if ($addorder['order_status'] == JhkdOrderAddDict::WAIT_PAY) {
                    $addModel->where(['site_id' => $site_id, 'order_id' => $orderInfo['order_id']])->delete();
                }
            }
            //进行开店宝接入
            $key = 'tk_jhkd_order';
            $config = (new CommonService())->getPartnerCommissionConfig($orderInfo['site_id'])[$key] ?? [];
            if (isset($config['is_use']) && $config['is_use'] == 1) {
                $create_data = [
                    'site_id' => $orderInfo['site_id'],
                    'member_id' => $orderInfo['member_id'],
                    'name' => '快递推广订单',
                    'order_id' => $orderInfo['order_id'],
                    'key' => $key,
                    'order_money' => $orderInfo['pay_money'],
                    'profit' => $orderInfo['pay_money'] * $config['rate'] / 100,
                    'status' => 3,
                ];
                TkPartnerCommissionOrder::dispatch(['data' => $create_data]);
            }
            Db::commit();
            OrderRefundAfter::dispatch(['order_data' => [
                'order_goods_id' => $orderInfo['id'],
                'site_id' => $site_id,
            ]]);
            $fenxiao_data= [
                'site_id' => $orderInfo['site_id'],
                'member_id' => $orderInfo['member_id'],
                'order_id'=>$orderInfo['order_id'],
                'key' => 'tk_jhkd_order',
                'status' => -1,  //0创建 1支付  2完成  -1取消
                'goods_list' => [
                    [
                        'name' => '快递产品下单',//产品名称
                        'image' => '',//主图
                        'order_money' => $orderInfo['order_money'],//订单金额
                        'pay_money' => $orderInfo['pay_money'],//实付金额
                        'num' => 1,//数量
                        'commission' => $orderInfo['pay_money'] ,//分佣金额,
                        'team_commission' => $orderInfo['pay_money'],//参与团队分红的金额
                        'agent_commission' => $orderInfo['pay_money'],//参与代理商分红的金额
                    ]
                ],
                'create_time' => strtotime($orderInfo['create_time']),//创建时间
            ];
            UpdateOrderJob::dispatch(['data' => $fenxiao_data]);
            $deliveryInfo = $this->deliveryModel->where(['order_id' => $orderInfo['order_id']])->findOrEmpty();
            //分销订单关闭
            $fenxiao_order = (new FenxiaoOrder())->where(['order_id' => $orderInfo['order_id'], 'site_id' => $site_id])->findOrEmpty();
            if (!$fenxiao_order->isEmpty()) {
                (new FenxiaoOrder())->where(['order_id' => $orderInfo['order_id'], 'site_id' => $site_id])->update(['status' => CommissionStatusDict::CLOSE]);
            }
            $order_no = $deliveryInfo['order_no'];
            (new OrderLogService())->writeOrderLog(
                $orderInfo['site_id'],
                $orderInfo['order_id'],
                $orderInfo['order_status'],
                '订单退款成功',
                'member'
            );
            $cancelData = [
                'site_id' => $site_id,
                'data' => [
                    'order_no' => $order_no,
                    'task_id' => $deliveryInfo['task_id'],
                    'order_id' => $orderInfo['order_id'],
                    'platform' => $deliveryInfo['platform'],
                ]
            ];
            (new DeliveryCancelOrderListener())->handle($cancelData);
            OrderDeliveryCancel::dispatch('doJob', $cancelData, 30);
            Db::commit();
        } catch (Exception $e) {
            Db::rollback();
            Log::write('===聚合快递退款失败===' . date('Y-m-d H:i:s'));
            Log::write($e->getMessage());
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * @Notes:重新计算价格和规则
     * @Interface newPriceAndRule
     * @param $data
     * @author: TK
     * @Time: 2024/9/8   下午10:56
     */
    public function newPriceAndRule($data, $weight)
    {
        $price = $data['preOrderFee'];
        // 易达通票计算价格
        if ($data['calcFeeType'] == 'profit') {
            if (is_array($data['price'])) {
                $pricerules = $data['price'];
            } else {
                $pricerules = json_decode($data['price'], true);
            }
            if (is_array($data['originalPrice'])) {
                $original_price = $data['originalPrice'];
            } else {
                $original_price = json_decode($data['originalPrice'], true);
            }
            $selectedRule = null;
            $index = 0;
            // 遍历所有规则，找到适用于当前重量的规则
            foreach ($pricerules as $k => $rule) {
                if ($weight >= $rule['start'] && ($weight <= $rule['end'] || $rule['end'] == 0)) {
                    $selectedRule = $rule;
                    $index = $k;
                    break;
                }
            }
            if (!$selectedRule) {
                $firstRule = reset($pricerules);
                if ($weight < $firstRule['start']) {
                    $selectedRule = $firstRule;
                    $index = key($pricerules);
                }
            }
            if ($this->config['floatWay'] == 'floatWayFixed') {
                $selectedRule['first'] += $this->config['floatAmount'];
                if ($selectedRule['add'] > 0) {
                    $selectedRule['add'] += $this->config['floatAmount'];
                } else {
                    $selectedRule['add'] = 3;
                }

            }
            if ($this->config['floatWay'] == 'floatWayRate') {
                $selectedRule['first'] = $selectedRule['first'] * (1 + $this->config['floatRate'] / 100);
                if ($selectedRule['add'] > 0) {
                    $selectedRule['add'] = $selectedRule['add'] * (1 + $this->config['floatRate'] / 100);
                } else {
                    $selectedRule['add'] = 3;
                }
            }
            if ($this->config['floatWay'] == 'floatWayBetwn') {
                $selectedRule['first'] = $selectedRule['first'] + $this->config['firstAmount'];
                if ($selectedRule['add'] > 0) {
                    $selectedRule['add'] = $selectedRule['add'] + $this->config['secondAmount'];
                } else {
                    $selectedRule['add'] = 3;
                }

            }
            $selectedRule['perAdd'] = 0;   // 单笔加收
            $selectedRule['discount'] = 1;    // 折扣

        } else {
            if (is_array($data['price'])) {
                $pricerules = $data['price'];
            } else {
                $pricerules = json_decode($data['price'], true);
            }
            if (is_array($data['originalPrice'])) {
                $original_price = $data['originalPrice'];
            } else {
                $original_price = json_decode($data['originalPrice'], true);
            }
            $selectedRule = null;
            $index = 0;
            // 遍历所有规则，找到适用于当前重量的规则
            foreach ($original_price as $k => $rule) {
                if ($weight >= $rule['start'] && ($weight <= $rule['end'] || $rule['end'] == 0)) {
                    $selectedRule = $rule;
                    $index = $k;
                    break;
                }
            }
            if (!$selectedRule) {
                $firstRule = reset($original_price);
                if ($weight < $firstRule['start']) {
                    $selectedRule = $firstRule;
                    $index = key($original_price);
                }
            }
            if ($this->config['floatWay'] == 'floatWayFixed') {
                $selectedRule['first'] = $selectedRule['first'] * $pricerules['discount'] + $pricerules['perAdd'] + $this->config['floatAmount'];
                $selectedRule['add'] = $selectedRule['add'] * $pricerules['discount'] + $this->config['floatAmount'];
            }
            if ($this->config['floatWay'] == 'floatWayRate') {
                $selectedRule['first'] = ($selectedRule['first'] * $pricerules['discount'] + $pricerules['perAdd']) * (1 + $this->config['floatRate'] / 100);
                $selectedRule['add'] = $selectedRule['add'] * $pricerules['discount'] * (1 + $this->config['floatRate'] / 100);
            }
            if ($this->config['floatWay'] == 'floatWayFixed') {
                $selectedRule['first'] = $selectedRule['first'] * $pricerules['discount'] + $pricerules['perAdd'] + $this->config['firstAmount'];
                $selectedRule['add'] = $selectedRule['add'] * $pricerules['discount'] + $this->config['secondAmount'];
            }
            $selectedRule['perAdd'] = $pricerules['perAdd'];   // 单笔加收
            $selectedRule['discount'] = $pricerules['discount'];    // 折扣
        }
        $selectedRule['first'] = round($selectedRule['first'], 2);
        $selectedRule['add'] = round($selectedRule['add'], 2);
//        $selectedRule['first']=ceil($selectedRule['first']);
//        $selectedRule['add']=ceil($selectedRule['add']);
        $weight_num = $weight - $selectedRule['start'];
        if ($weight_num < 0) {
            $weight_num = 0;
        }
        $price = $selectedRule['first'] + $weight_num * $selectedRule['add'];
        $price = round($price, 2);
        return ['price' => $price, 'price_rule' => $selectedRule, 'original_rule' => $original_price[$index]];
    }

    /**
     * @param $price
     */
    public
    function showPrice($data)
    {
        $price = $data['preOrderFee'];
        if ($this->config['floatWay'] == 'floatWayFixed') {
            $price = $price + $this->config['floatAmount'];
        }
        if ($this->config['floatWay'] == 'floatWayRate') {
            $price = $price * (1 + $this->config['floatRate'] / 100);
        }
        if ($this->config['floatWay'] == 'floatWayBetwn') {
            $weight = $data['calcFeeWeight'];
            $rules = json_decode($data['originalPrice'], true)[0];
            if ($weight <= $rules['start']) {
                $price = $rules['first'] - $this->config['firstAmount'] + $data['preBjFee'];
            } else {
                $firstPrice = $rules['first'] - $this->config['firstAmount'];
                $secondPrice = ($rules['add'] - $this->config['secondAmount']) * ($weight - 1);
                $price = $firstPrice + $secondPrice + $data['preBjFee'];
            }

        }
//        if ($price >= ($data['originalFee'] + $data['preBjFee'])) {
//            $price = $data['originalFee'] - 0.02;
//        }
        if ($price < $data['preOrderFee']) {
            $price = $data['preOrderFee'] + 3;
        }
        return round($price, 2);
    }
}