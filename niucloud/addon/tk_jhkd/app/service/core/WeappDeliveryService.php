<?php

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\model\order\Order;
use addon\tk_jhkd\app\model\order\OrderAdd;
use addon\tk_jhkd\app\model\orderdelivery\OrderDelivery;
use app\dict\pay\PayDict;
use app\model\member\Member;
use app\model\pay\Pay;
use app\service\core\weapp\CoreWeappDeliveryService;
use core\base\BaseApiService;
use think\facade\Log;

/**
 * 小程序发货信息管理
 */
class WeappDeliveryService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new OrderDelivery();
    }

    /**
     * @Notes:上传运单虚拟发货形式  运力匹配待统一
     * @Interface uploadShippingInfo
     * @param $params
     * @return string|void
     * @author: TK
     * @Time: 2024/12/3   上午2:50
     */
    public function uploadShippingInfo($params)
    {
        try {
            $this->model = new Order();
            $order_id = $params['order_id'];
            $site_id = $params['site_id'];
            $config = (new CommonService())->getConfig($site_id);
            if (!isset($config['is_shipping']) || $config['is_shipping'] != 1) return '未启用不需发货';
            //查询订单
            $where = array(
                ['order_id', '=', $order_id],
                ['site_id', '=', $site_id]
            );
            $order_data = $this->model->where($where)->findOrEmpty();
            //订单不存在
            if ($order_data->isEmpty()) {
                return '';
            }
            $orderDeliveryModel = new OrderDelivery();
            $where = array(
                ['order_id', '=', $order_id]
            );
            $order_delivery = $orderDeliveryModel->where($where)->findOrEmpty();
            if ($order_delivery->isEmpty()) {
                return '';
            }
            $pay_model = new Pay();
            $where = array(
                ['site_id', '=', $site_id],
                ['trade_type', '=', 'jhkdOrderPay'],
                ['trade_id', '=', $order_data['id']]
            );
            $pay_info = $pay_model->where($where)->field('id,type,out_trade_no')->findOrEmpty()->toArray();

            if (empty($pay_info)) {
                return '';
            }
            // 订单未使用微信支付，无须处理
            if (!in_array($pay_info['type'], [PayDict::WECHATPAY, 'fastpay_wechatpay'])) {
                return '订单未使用微信支付';
            }
            $weapp_delivery_service = new CoreWeappDeliveryService();

            // 检测微信小程序是否已开通发货信息管理服务
            $is_trade_managed = $weapp_delivery_service->isTradeManaged($site_id);

            if (empty($is_trade_managed['is_trade_managed'])) {
                return '发货信息录入接口，报错：' . $is_trade_managed["errmsg"];
            }

            // 设置消息跳转路径设置接口 - 直接设置为订单详情页
            try {
                $path = 'addon/tk_jhkd/pages/orderlist';
                $api = \app\service\core\weapp\CoreWeappService::appApiClient($site_id);
                $result_jump_path = $api->postJson('wxa/sec/order/set_msg_jump_path', [ 'path' => $path ])->toArray();
                if ($result_jump_path['errcode'] != 0) {
                    Log::write('设置消息跳转路径失败：' . json_encode($result_jump_path));
                }
            } catch (\Exception $e) {
                Log::write('设置消息跳转路径异常：' . $e->getMessage());
            }
            // 获取支付配置，判断是否为服务商支付
            $pay_service = new \app\service\admin\pay\PayChannelService();
            $pay_service->site_id = $site_id;
            $pay_config = $pay_service->getInfo([
                'type' => PayDict::WECHATPAY,
                'channel' => \app\dict\common\ChannelDict::WEAPP
            ]);

            $mch_id = '';
            $sub_mch_id = '';
            if (!empty($pay_config)) {
                $mch_id = $pay_config['config']['mch_id'] ?? '';
                $sub_mch_id = $pay_config['config']['sub_mch_id'] ?? '';
            }

            if (empty($mch_id)) {
                Log::write('========聚合快递订单上传小程序发货管理失败：未配置商户号=======');
                return '未配置商户号';
            }

            $member_info = (new Member())->where([['site_id', '=', $site_id], ['member_id', '=', $order_data['member_id']]])->field('weapp_openid')->findOrEmpty()->toArray();

            // 构建发货信息参数
            $order_key = [
                'order_number_type' => 1,
                'mchid' => $mch_id,
                'out_trade_no' => $pay_info['out_trade_no']
            ];

            // 如果是服务商支付，添加 sub_mchid
            if (!empty($sub_mch_id)) {
                $order_key['sub_mchid'] = $sub_mch_id;
            }

            $data = [
                'order_key' => $order_key,
                'logistics_type' => 3,
                'delivery_mode' => 'UNIFIED_DELIVERY',
                'shipping_list' => [
                    [
                        'item_desc' => '聚合快递代下单-物品:' . $order_delivery['goods'] . '-' . $order_delivery['weight'] . 'kg',
                    ]
                ],
                'upload_time' => date("c", time()),
                'payer' => [
                    'openid' => $member_info['weapp_openid']
                ],
                'is_all_delivered' => true
            ];

            Log::write('发货信息录入接口，参数打印：' . json_encode($data));

            // 延时3秒执行发货通知
            sleep(3);

            // 直接调用微信 API
            $api = \app\service\core\weapp\CoreWeappService::appApiClient($site_id);
            $result = $api->postJson('wxa/sec/order/upload_shipping_info', $data)->toArray();

            Log::write('发货信息录入接口，返回结果：' . json_encode($result));
        } catch (\Exception $e) {
            Log::write('========聚合快递订单上传小程序发货管理失败=======' . $e->getMessage() . $e->getFile() . $e->getLine());
        }
    }

    /**
     * @Notes:补差价订单自动上传发货管理
     * uploadShippingInfoAdd
     * @param $params
     * 2024/12/23  14:19
     * author:TK
     */
    public function uploadShippingInfoAdd($params)
    {
        try {
            $this->model = new OrderAdd();
            $order_id = $params['order_id'];
            $site_id = $params['site_id'];
            $config = (new CommonService())->getConfig($site_id);
            if (!isset($config['is_shipping']) || $config['is_shipping'] != 1) return '未启用不需发货';
            //查询订单
            $where = array(
                ['order_id', '=', $order_id],
                ['site_id', '=', $site_id]
            );
            $order_data = $this->model->where($where)->findOrEmpty();
            //订单不存在
            if ($order_data->isEmpty()) {
                return '';
            }
            $pay_model = new Pay();
            $where = array(
                ['site_id', '=', $site_id],
                ['out_trade_no', '=', $order_data['out_trade_no']]
            );
            $pay_info = $pay_model->where($where)->field('id,type')->findOrEmpty()->toArray();

            if (empty($pay_info)) {
                return '';
            }
            // 订单未使用微信支付，无须处理
            if (!in_array($pay_info['type'], [PayDict::WECHATPAY, 'fastpay_wechatpay'])) {
                return '订单未使用微信支付';
            }
            $weapp_delivery_service = new CoreWeappDeliveryService();

            // 检测微信小程序是否已开通发货信息管理服务
            $is_trade_managed = $weapp_delivery_service->isTradeManaged($site_id);

            if (empty($is_trade_managed['is_trade_managed'])) {
                return '发货信息录入接口，报错：' . $is_trade_managed["errmsg"];
            }

            // 设置消息跳转路径设置接口 - 直接设置为订单详情页
            try {
                $path = 'addon/tk_jhkd/pages/orderlist';
                $api = \app\service\core\weapp\CoreWeappService::appApiClient($site_id);
                $result_jump_path = $api->postJson('wxa/sec/order/set_msg_jump_path', [ 'path' => $path ])->toArray();
                if ($result_jump_path['errcode'] != 0) {
                    Log::write('设置消息跳转路径失败：' . json_encode($result_jump_path));
                }
            } catch (\Exception $e) {
                Log::write('设置消息跳转路径异常：' . $e->getMessage());
            }
            $member_info = (new Member())->where([['site_id', '=', $site_id], ['member_id', '=', $order_data['member_id']]])->field('weapp_openid')->findOrEmpty()->toArray();
            $data = [
                'out_trade_no' => $order_data['out_trade_no'],
                'logistics_type' => 3, // 物流模式，发货方式枚举值：1、实体物流配送采用快递公司进行实体物流配送形式 2、同城配送 3、虚拟商品，虚拟商品，例如话费充值，点卡等，无实体配送形式 4、用户自提
                'delivery_mode' => 'UNIFIED_DELIVERY', // 发货模式，发货模式枚举值：1、UNIFIED_DELIVERY（统一发货）2、SPLIT_DELIVERY（分拆发货） 示例值: UNIFIED_DELIVERY
                // 同城配送没有物流信息，只能传一个订单
                'shipping_list' => [
                    [
                        'item_desc' => '快递运费补差价订单', // 物流商品描述 示例值: "商品描述"
                    ]
                ], // 物流信息列表，发货物流单列表，支持统一发货（单个物流单）和分拆发货（多个物流单）两种模式，多重性: [1, 10]
                'weapp_openid' => $member_info['weapp_openid'], // 用户标识，用户在小程序appid下的唯一标识。 下单前需获取到用户的Openid 示例值: oUpF8uMuAJO_M2pxb1Q9zNjWeS6o 字符字节限制: [1, 128]
                'is_all_delivered' => true
            ];

            $weapp_delivery_service->uploadShippingInfo($site_id, $data);
        } catch (\Exception $e) {
            Log::write('========聚合快递订单上传小程序发货管理失败=======' . $e->getMessage() . $e->getFile() . $e->getLine());
        }
    }

    /**
     * @Notes:确认用户收货提醒
     * @Interface notifyConfirmReceive
     * @param $params
     * @return string|void
     * @author: TK
     * @Time: 2024/12/3   上午2:54
     */
    public function notifyConfirmReceive($params = [])
    {
        try {
            $site_id = $params['site_id'];
            $config = (new CommonService())->getConfig($site_id);
            if (!isset($config['is_shipping']) || $config['is_shipping'] != 1) return '未开启不需发货';
            $pay_model = new Pay();
            $where = array(
                ['site_id', '=', $site_id],
                ['out_trade_no', '=', $params['out_trade_no']]
            );
            $pay_info = $pay_model->where($where)->field('id,type')->findOrEmpty()->toArray();
            if ($pay_info['type'] != PayDict::WECHATPAY) {
                return '订单未使用微信支付';
            }
            $weapp_delivery_service = new CoreWeappDeliveryService();
            $is_trade_managed = $weapp_delivery_service->isTradeManaged($site_id);
            if (empty($is_trade_managed['is_trade_managed'])) {
                return '发货信息录入接口，报错：' . $is_trade_managed["errmsg"];
            }
            $result_jump_path = $weapp_delivery_service->setMsgJumpPath($site_id, 'tk_jhkd_order');
            if ($result_jump_path['errcode'] != 0) {
                return '设置消息跳转路径设置接口，报错：' . $result_jump_path["errmsg"];
            }
            $data = [
                'merchant_trade_no' => $params['out_trade_no'] // 商户系统内部订单号，只能是数字、大小写字母_-*且在同一个商户号下唯一
            ];
            $weapp_delivery_service->notifyConfirmReceive($site_id, $data);
        } catch (\Exception $e) {
            Log::write('========聚合快递小程序发送收货信息失败==========' . $e->getMessage() . $e->getFile() . $e->getLine());
        }
    }
}
