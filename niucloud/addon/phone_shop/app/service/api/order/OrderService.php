<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\api\order;

use addon\phone_shop\app\dict\coupon\CouponDict;
use addon\phone_shop\app\dict\delivery\DeliveryDict;
use addon\phone_shop\app\dict\order\OrderDeliveryDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderLogDict;
use addon\phone_shop\app\dict\order\OrderRefundDict;
use addon\phone_shop\app\model\coupon\Coupon;
use addon\phone_shop\app\model\delivery\Store;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\manjian\Manjian;
use addon\phone_shop\app\model\manjian\ManjianGiveRecords;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderDelivery;
use addon\phone_shop\app\model\order\OrderOfflineRecord;
use addon\phone_shop\app\model\order\OrderRefund;
use addon\phone_shop\app\service\core\local_delivery\CoreLocalDeliveryService;
use addon\phone_shop\app\service\core\marketing\CoreManjianService;
use addon\phone_shop\app\service\core\order\CoreOrderCloseService;
use addon\phone_shop\app\service\core\order\CoreOrderConfigService;
use addon\phone_shop\app\service\core\order\CoreOrderFinishService;
use addon\phone_shop\app\service\core\order\CoreOrderService;
use addon\phone_shop\app\service\core\order\OfflineOrderSchemaService;
use app\dict\common\ChannelDict;
use app\dict\pay\PayDict;
use app\model\member\Member;
use app\model\pay\Pay;
use app\service\admin\pay\PayChannelService;
use app\service\api\weapp\WeappDeliveryService;
use app\service\core\pay\CorePayChannelService;
use core\base\BaseApiService;
use core\exception\ApiException;

/**
 *  订单服务层
 */
class OrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
    }

    /**
     * 分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where)
    {
        $field = 'point,activity_type,order_id,order_no,close_type,order_type,order_from,out_trade_no,status,member_id,site_id,ip,goods_money,delivery_money,order_money,create_time,pay_time,delivery_type,taker_name,taker_mobile,taker_full_address,take_store_id,is_enable_refund,member_remark,shop_remark,close_remark,pay_money,is_evaluate,invoice_id,payment_mode,pricing_identity,staff_id,is_credit,credit_status,settle_status';
        $order = 'create_time desc';
        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id],['user_delete_time','=',0]])
            ->withSearch(['order_no', 'status', 'activity_type','body'], $where)
            ->field($field)
            ->with(
                [
                    'invoice' => function ($query) {
                        $query->field('id');
                    },
                    'order_goods' => function ($query) {
                        $query->field('extend,order_goods_id, site_id, order_id, member_id, goods_id, sku_id, goods_name, sku_name, goods_image, sku_image, price, num, goods_money, is_enable_refund, delivery_id,is_enable_refund, status, is_gift')
                            ->with([
                                'order_delivery' => function ($query) {
                                    $query->field('id, express_company_id, express_number')->with('company');
                                }
                            ])
                            ->append(['goods_image_thumb_small']);
                    },
                    'store' => function ($query) {
                        $query->field('store_id, store_name, store_mobile');
                    }
                ]
            )->order($order)->append(['order_from_name', 'order_type_name', 'activity_type_name', 'status_name', 'delivery_type_name']);
        $order_status_list = OrderDict::getStatus();
        $order_close_list = OrderDict::getCloseType();
        $list = $this->pageQuery($search_model, function ($item, $key) use ($order_status_list, $order_close_list) {
            $item['close_type_name'] = $order_close_list[$item['close_type']] ?? "";
            $item['order_status_data'] = $order_status_list[$item['status']] ?? [];
        });
        $config = (new CoreOrderConfigService())->getConfig($this->site_id);
        $close_length = $config['close_order_info']['close_length'];
        $orderConfigService = new CoreOrderConfigService();
        $onlineTradeConfig = $orderConfigService->getOnlineTradeConfig((int)$this->site_id);
        foreach ($list['data'] as $k => $v) {

            $list['data'][$k]['now_time'] = time();
            $list['data'][$k]['expire_time'] = strtotime($v['create_time']) + 60 * $close_length;
            if ($v['out_trade_no']) {
                $list['data'][$k]['pay'] = (new Pay())->where([['out_trade_no', '=', $v['out_trade_no']]])
                    ->field('type, pay_time')->append(['type_name'])
                    ->findOrEmpty()->toArray();
            }
            if ($v['order_goods']) {
                $list['data'][$k]['order_goods'] = $this->hydrateOrderGoodsDeviceMeta($v['order_goods']);
            }
            $list['data'][$k]['is_can_invoice'] = 1;
            if (!empty($v['invoice']) || !in_array($v['status'], [OrderDict::WAIT_DELIVERY, OrderDict::WAIT_TAKE, OrderDict::FINISH]) || ($v['order_money'] == 0)) {
                $list['data'][$k]['is_can_invoice'] = 0;
            }
            $list['data'][$k] = array_merge(
                $list['data'][$k],
                $orderConfigService->getOrderOnlinePayState($v, $onlineTradeConfig)
            );
        }

        // 查询小程序是否已开通发货信息管理服务
        try {
            $list['mch_id'] = '';
            $result_is_trade_managed = (new WeappDeliveryService())->getIsTradeManaged();
            if ($result_is_trade_managed) {

                $list['is_trade_managed'] = true;
                $pay_service = new PayChannelService();
                $pay_config = $pay_service->getInfo([
                    'type' => PayDict::WECHATPAY,
                    'channel' => ChannelDict::WEAPP
                ]);

                $mch_id = '';
                if (!empty($pay_config)) {
                    $mch_id = $pay_config['config']['mch_id'];
                }

                $list['mch_id'] = $mch_id;
            } else {
                $list['is_trade_managed'] = false;
            }
        } catch (\Exception $e) {
            $list['is_trade_managed'] = false;
        }
        return $list;
    }

    /**
     * 详情
     * @param $order_id
     * @return array
     */
    public function getDetail($order_id)
    {
        $field = 'buyer_ask_delivery_time,relate_id,activity_type,point,order_id,site_id,order_no,order_type,order_from,out_trade_no,status,member_id,ip,goods_money,delivery_money,order_money,base_order_money,pricing_identity,payment_fee_rate,payment_fee_bearer,payment_fee_amount,merchant_net_amount,pay_money,invoice_id,create_time,pay_time,delivery_time,take_time,finish_time,close_time,timeout,delivery_type,taker_name,taker_mobile,taker_province,taker_city,taker_district,taker_address,taker_full_address,taker_longitude,taker_latitude,take_store_id,is_enable_refund,member_remark,shop_remark,close_remark,discount_money,is_evaluate,form_record_id,payment_mode,staff_id,is_credit,credit_status,settle_status';
        $info = $this->model->where([['site_id', '=', $this->site_id], ['order_id|out_trade_no', '=', $order_id], ['member_id', '=', $this->member_id],['user_delete_time','=',0]])->field($field)
            ->with(
                [
                    'order_goods' => function ($query) {
                        $query->field('extend,order_goods_id, site_id, order_id, member_id, goods_id, sku_id, goods_name, sku_name, goods_image, sku_image, price, num, goods_money, discount_money, is_enable_refund, status, order_refund_no, delivery_status, verify_count, verify_expire_time, is_verify, goods_type, is_gift,form_record_id')->append(['goods_image_thumb_small']);
                    },
                    'order_discount' => function ($query) {
                        $query->field('order_id,discount_type,money');
                    },
                    'invoice' => function ($query) {
                        $query->field('id');
                    }
                ]
            )->append(['order_from_name', 'order_type_name', 'status_name', 'delivery_type_name'])->findOrEmpty()->toArray();
        if (!empty($info)) {
            $info['is_can_invoice'] = 1;
            //已开票或者未支付 或已关闭订单不允许补开发票
            if (!empty($info['invoice']) || ($info['order_money'] == 0) || !in_array($info['status'], [OrderDict::WAIT_DELIVERY, OrderDict::WAIT_TAKE, OrderDict::FINISH])) {
                $info['is_can_invoice'] = 0;
            }
            $info['order_status_data'] = $order_status_list[$info['status']] ?? [];

            if ($info['delivery_type'] == DeliveryDict::STORE) {
                $info['store'] = (new Store())->where([['store_id', '=', $info['take_store_id']]])
                    ->field('store_id, store_name, store_mobile, store_logo, trade_time, longitude, latitude, full_address')
                    ->findOrEmpty()->toArray();
            }

            if ($info['out_trade_no']) {
                $info['pay'] = (new Pay())->where([['out_trade_no', '=', $info['out_trade_no']]])
                    ->field('main_id, out_trade_no, type, pay_time, status')->append(['type_name'])
                    ->findOrEmpty()->toArray();
                if (!empty($info['pay'])) {
                    if ($info['member_id'] != $info['pay']['main_id']) {
                        $member_info = (new Member())->field('nickname,headimg')->where([['site_id', '=', $this->site_id], ['member_id', '=', $info['pay']['main_id']]])->findOrEmpty()->toArray();
                        if (!empty($member_info)) {
                            $info['pay']['pay_member'] = $member_info['nickname'];
                            $info['pay']['pay_member_headimg'] = $member_info['headimg'];
                        }
                    }
                }
            }

            if ($info['delivery_type'] == DeliveryDict::EXPRESS || $info['delivery_type'] == DeliveryDict::LOCAL_DELIVERY) {
                $info['order_delivery'] = (new OrderDelivery())
                    ->where([['order_id', '=', $info['order_id']]])
                    ->field('id, order_id, name, delivery_type, sub_delivery_type,express_company_id, express_number, create_time,local_delivery_order_id')
                    ->select()->toArray();
                if ($info['delivery_type'] == DeliveryDict::LOCAL_DELIVERY) {
                    if (!empty($info['order_delivery'])) {
                        $local_delivery_order_id = $info['order_delivery'][0]['local_delivery_order_id'];
                        $local_delivery_order_info = (new CoreLocalDeliveryService()) ->getLocalDeliveryOrderInfo($local_delivery_order_id);
                        $info = array_merge($info, $local_delivery_order_info);
                    }
                }
            }

            if ($info['order_goods']) {
                $info['order_goods'] = $this->hydrateOrderGoodsDeviceMeta($info['order_goods']);
                foreach ($info['order_goods'] as $k => &$v) {
                    if (isset($v['extend']['is_impulse_buy']) == 1) {
                        $impulse_buy_num = $v['extend']['impulse_buy_goods_num'] ?? 0;//5
                        $impulse_buy_price_total = $v['extend']['impulse_buy_price'] ?? 0;
                        if ($impulse_buy_num > 0) {
                            $impulse_buy_price = $impulse_buy_price_total / $impulse_buy_num;
                            if ($impulse_buy_num == 1) {
                                $impulse_buy_tips = '第1' . $v['unit'] . $impulse_buy_price . '元';
                            } else {
                                $impulse_buy_tips = '第1-' . $impulse_buy_num . $v['unit'] . $impulse_buy_price . '元';
                                if ($v['num'] > $impulse_buy_num) {
                                    if ($impulse_buy_num + 1 == $v['num']) {
                                        $impulse_buy_tips .= ' 第' . ($impulse_buy_num + 1) . $v['unit'] . $v['price'] . '元';
                                    } else {
                                        $impulse_buy_tips .= ' 第' . ($impulse_buy_num + 1) . '-' . $v['num'] . $v['unit'] . $v['price'] . '元';
                                    }
                                }
                            }
                        } else {//全原价
                            $impulse_buy_tips = '第1-' . $v['num'] . $v['unit'] . $v['price'] . '元';
                        }
                    }
                    $v['impulse_buy_tips'] = $impulse_buy_tips ?? "";
                    (new CoreManjianService())->getOrderGoodsGiveInfo($v, $info['order_goods'], $this->site_id, $this->member_id);
                }
            }

            $coupon_money = 0;
            $manjian_discount_money = 0;
            if ($info['order_discount']) {
                foreach ($info['order_discount'] as $item) {
                    if ($item['discount_type'] == 'coupon') {
                        $coupon_money += $item['money'];
                    }
                    if ($item['discount_type'] == 'manjian') {
                        $manjian_discount_money += $item['money'];
                    }
                }
            }
            $info['coupon_money'] = number_format($coupon_money, 2, '.', '');
            $info['manjian_discount_money'] = number_format($manjian_discount_money, 2, '.', '');

            // 查询小程序是否已开通发货信息管理服务
            try {
                $info['mch_id'] = '';
                $result_is_trade_managed = (new WeappDeliveryService())->getIsTradeManaged();
                if ($result_is_trade_managed) {
                    $info['is_trade_managed'] = true;

                    $pay_service = new PayChannelService();
                    $pay_config = $pay_service->getInfo([
                        'type' => PayDict::WECHATPAY,
                        'channel' => ChannelDict::WEAPP
                    ]);

                    $mch_id = '';
                    if (!empty($pay_config)) {
                        $mch_id = $pay_config['config']['mch_id'];
                    }

                    $info['mch_id'] = $mch_id;
                } else {
                    $info['is_trade_managed'] = false;
                }
            } catch (\Exception $e) {
                $info['is_trade_managed'] = false;
            }

            $orderConfigService = new CoreOrderConfigService();
            $config = $orderConfigService->getConfig($this->site_id);
            $info = array_merge(
                $info,
                $orderConfigService->getOrderOnlinePayState(
                    $info,
                    (array)($config['online_trade'] ?? [])
                )
            );
            $close_length = $config['close_order_info']['close_length'];

            $info['now_time'] = time();
            $info['expire_time'] = (int)($info['timeout'] ?? 0) > 0
                ? (int)$info['timeout']
                : strtotime($info['create_time']) + 60 * $close_length;

            if ((string)($info['payment_mode'] ?? '') === 'offline_pending') {
                OfflineOrderSchemaService::ensure();
                $tradeConfig = $orderConfigService->getOnlineTradeConfig($this->site_id);
                $record = OrderOfflineRecord::where([
                    ['site_id', '=', $this->site_id],
                    ['order_id', '=', (int)$info['order_id']],
                ])->field('handler_name,handler_mobile,status,contact_at,voucher_urls,remark,update_time')->findOrEmpty()->toArray();
                $info['offline_contact'] = [
                    'handler_name' => (string)($record['handler_name'] ?? $tradeConfig['offline_contact_name'] ?? '门店业务员'),
                    'handler_mobile' => (string)($record['handler_mobile'] ?? $tradeConfig['offline_contact_mobile'] ?? ''),
                    'status' => (string)($record['status'] ?? 'pending'),
                    'contact_at' => (int)($record['contact_at'] ?? 0),
                    'contact_tip' => (string)($tradeConfig['offline_contact_tip'] ?? ''),
                    'payment_qrcode' => (string)($tradeConfig['offline_payment_qrcode'] ?? ''),
                    'payment_tip' => (string)($tradeConfig['offline_payment_tip'] ?? ''),
                    'voucher_enabled' => (int)($tradeConfig['offline_voucher_enabled'] ?? 1),
                    'hold_on_progress' => (int)($tradeConfig['offline_hold_on_progress'] ?? 1),
                    'voucher_urls' => array_values((array)($record['voucher_urls'] ?? [])),
                    'remark' => (string)($record['remark'] ?? ''),
                    'update_time' => (int)($record['update_time'] ?? 0),
                    'can_submit_voucher' => (int)($tradeConfig['offline_voucher_enabled'] ?? 1) === 1
                        && in_array((string)($record['status'] ?? 'pending'), ['pending', 'contacted', 'voucher_rejected'], true) ? 1 : 0,
                ];
            }

        }
        return $info;
    }

    /**
     * 给订单商品补齐二手机紧凑展示所需的副标题和 IMEI。
     * 订单仍保留成交快照，展示字段从商品/SKU 补齐，避免为每一行分别查询。
     */
    private function hydrateOrderGoodsDeviceMeta(array $items): array
    {
        if (empty($items)) return [];

        $goodsIds = array_values(array_unique(array_filter(array_map(
            static fn(array $item) => (int)($item['goods_id'] ?? 0),
            $items
        ))));
        $skuIds = array_values(array_unique(array_filter(array_map(
            static fn(array $item) => (int)($item['sku_id'] ?? 0),
            $items
        ))));

        $goodsMap = [];
        if (!empty($goodsIds)) {
            $goodsRows = (new Goods())->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'goods_id', 'in', $goodsIds ],
            ])->field('goods_id,unit,sub_title')->select()->toArray();
            foreach ($goodsRows as $row) {
                $goodsMap[(int)$row['goods_id']] = $row;
            }
        }

        $skuMap = [];
        if (!empty($skuIds)) {
            $skuRows = (new GoodsSku())->where([
                [ 'site_id', '=', $this->site_id ],
                [ 'sku_id', 'in', $skuIds ],
            ])->field('sku_id,sku_no')->select()->toArray();
            foreach ($skuRows as $row) {
                $skuMap[(int)$row['sku_id']] = (string)($row['sku_no'] ?? '');
            }
        }

        foreach ($items as &$item) {
            $goods = $goodsMap[(int)($item['goods_id'] ?? 0)] ?? [];
            $item['unit'] = (string)($goods['unit'] ?? '件');
            $item['sub_title'] = (string)($goods['sub_title'] ?? '');
            $item['sku_no'] = (string)($skuMap[(int)($item['sku_id'] ?? 0)] ?? '');
        }
        unset($item);

        return $items;
    }


    /**
     * 订单关闭
     * @param int $order_id
     * @return true
     */
    public function close(int $order_id)
    {
        $data['main_type'] = OrderLogDict::MEMBER;
        $data['main_id'] = $this->member_id;
        $data['close_type'] = OrderDict::BUYER_CLOSE;
        $data['order_id'] = $order_id;
        $data['site_id'] = $this->site_id;
        (new CoreOrderCloseService())->close($data);
        return true;
    }


    /**
     * 订单收货
     * @param $order_id
     * @return true
     */
    public function finish($order_id)
    {
        $data = [];
        $data['order_id'] = $order_id;
        $data['main_type'] = OrderLogDict::MEMBER;
        $data['main_id'] = $this->member_id;
        $data['site_id'] = $this->site_id;
        //查询订单
        $where = array(
            ['order_id', '=', $order_id],
            ['site_id', '=', $this->site_id]
        );
        $order = $this->model->where($where)->findOrEmpty()->toArray();
        if (empty($order)) throw new ApiException('SHOP_ORDER_NOT_FOUND');//订单不存在
        if ($order['status'] != OrderDict::WAIT_TAKE) throw new ApiException('SHOP_ONLY_WAIT_TAKE_CAN_BE_TAKE');//只有待收货的订单才可以收货
        (new CoreOrderFinishService())->finish($data);
        return true;
    }

    /**
     * 物流信息
     * @param $data
     * @return array|mixed
     */
    public function getDeliveryPackage($data)
    {
        $field = 'id, order_id, site_id, name, delivery_type, sub_delivery_type, express_company_id, express_number, local_deliver_id, status, create_time';
        $info = (new OrderDelivery())->where([['id', '=', $data['id']], ['site_id', '=', $this->site_id]])->with([
            'company' => function ($query) {
                $query->field('company_id, company_name, express_no');
            },
            'order_goods' => function ($query) {
                $query->field('goods_name, sku_name, site_id, goods_image, delivery_id, num, price')->append(['goods_image_thumb_small']);
            }
        ])->field($field)->findOrEmpty()->toArray();

        if (!empty($info) && $info['delivery_type'] == OrderDeliveryDict::EXPRESS && $info['sub_delivery_type'] != OrderDeliveryDict::NONE_EXPRESS) {
            $info['mobile'] = $data['mobile'];
            $info = (new CoreOrderService())->deliverySearch($info);
            return $info;
        }
        return $info;
    }

    public function num()
    {

        $data['wait_pay'] = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['status', '=', OrderDict::WAIT_PAY],
        ])->count() ?? 0;

        $data['wait_shipping'] = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['status', '=', OrderDict::WAIT_DELIVERY],
        ])->count() ?? 0;

        $data['wait_take'] = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['status', '=', OrderDict::WAIT_TAKE],
        ])->count() ?? 0;

        $data['evaluate'] = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['status', '=', OrderDict::FINISH],
            ['is_evaluate', '=', 0],
        ])->count() ?? 0;

        $data['refund'] = (new OrderRefund())->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['status', 'in', [
                OrderRefundDict::BUYER_APPLY_WAIT_STORE,
                OrderRefundDict::STORE_AGREE_REFUND_GOODS_APPLY_WAIT_BUYER,
                OrderRefundDict::STORE_REFUSE_REFUND_GOODS_APPLY_WAIT_BUYER,
                OrderRefundDict::BUYER_REFUND_GOODS_WAIT_STORE,
                OrderRefundDict::STORE_REFUSE_TAKE_REFUND_GOODS_WAIT_BUYER,
                OrderRefundDict::STORE_AGREE_REFUND_WAIT_TRANSFER,
                OrderRefundDict::STORE_REFUND_TRANSFERING
            ]]
        ])->count() ?? 0;

        return $data;
    }

    public function calculateInvoiceMoney($ids)
    {
        $order_money = $this->model->whereIn('order_id', $ids)->where('invoice_id',0)->sum('order_money');
        return [
            'invoice_money' => $order_money,
        ];
    }

    /**
     * 删除订单（仅用户端不显示，不做真实删除）
     * @param $id
     * @return void
     */
    public function delete($id)
    {
        $order = $this->model->where('order_id',$id)->findOrEmpty()->toArray();
        if (empty($order)) throw new ApiException('SHOP_ORDER_NOT_FOUND');//订单不存在
        if ($order['status'] != OrderDict::CLOSE) throw new ApiException('SHOP_ONLY_CLOSE_CAN_BE_DELETE');//只有已关闭的订单才能够删除
        $this->model->where('order_id', $id)->update(['user_delete_time' => time()]);
    }
}
