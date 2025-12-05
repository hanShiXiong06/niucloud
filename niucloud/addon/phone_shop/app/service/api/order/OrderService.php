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

use addon\phone_shop\app\dict\delivery\DeliveryDict;
use addon\phone_shop\app\dict\order\OrderDeliveryDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\dict\order\OrderLogDict;
use addon\phone_shop\app\dict\order\OrderRefundDict;
use addon\phone_shop\app\model\delivery\Store;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderDelivery;
use addon\phone_shop\app\model\order\OrderRefund;
use addon\phone_shop\app\service\core\order\CoreOrderCloseService;
use addon\phone_shop\app\service\core\order\CoreOrderConfigService;
use addon\phone_shop\app\service\core\order\CoreOrderFinishService;
use addon\phone_shop\app\service\core\order\CoreOrderService;
use app\dict\common\ChannelDict;
use app\dict\pay\PayDict;
use app\model\pay\Pay;
use app\service\admin\pay\PayChannelService;
use app\service\api\weapp\WeappDeliveryService;
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
        $field = 'point,activity_type,order_id,order_no,order_type,order_from,out_trade_no,status,member_id,ip,goods_money,delivery_money,order_money,create_time,pay_time,delivery_type,taker_name,taker_mobile,taker_full_address,take_store_id,is_enable_refund,member_remark,shop_remark,close_remark,pay_money,is_evaluate';
        $order = 'create_time desc';
        $search_model = $this->model
            ->where([ [ 'site_id', '=', $this->site_id ], [ 'member_id', '=', $this->member_id ] ])
            ->withSearch([ 'order_no', 'status', 'activity_type' ], $where)
            ->field($field)
            ->with(
                [
                    'order_goods' => function($query) {
                        $query->field('extend,order_goods_id, site_id, order_id, member_id, goods_id, sku_id, sku_no,goods_name, sku_name, goods_image, sku_image, price, num, goods_money, is_enable_refund, delivery_id')
                            ->with([
                                'order_delivery' => function($query) {
                                    $query->field('id, express_company_id, express_number')->with('company');
                                }
                            ])
                            ->append([ 'goods_image_thumb_small' ]);
                    },
                    'store' => function($query) {
                        $query->field('store_id, store_name, store_mobile');
                    }
                ]
            )->order($order)->append([ 'order_from_name', 'order_type_name', 'status_name', 'delivery_type_name' ]);
        $order_status_list = OrderDict::getStatus();
        $list = $this->pageQuery($search_model, function($item, $key) use ($order_status_list) {
            $item[ 'order_status_data' ] = $order_status_list[ $item[ 'status' ] ] ?? [];
        });
        $config = (new CoreOrderConfigService())->getConfig($this->site_id);
        $close_length = $config['close_order_info']['close_length'];
        foreach ($list[ 'data' ] as $k => $v) {
            $list[ 'data' ][ $k ]['now_time'] = time();
            $list[ 'data' ][ $k ]['expire_time'] = strtotime($v['create_time']) + 60 * $close_length;
            if ($v[ 'out_trade_no' ]) {
                $list[ 'data' ][ $k ][ 'pay' ] = ( new Pay() )->where([ [ 'out_trade_no', '=', $v[ 'out_trade_no' ] ] ])
                    ->field('type, pay_time')->append([ 'type_name' ])
                    ->findOrEmpty()->toArray();
            }
        }

        // 查询小程序是否已开通发货信息管理服务
        try {
            $list[ 'mch_id' ] = '';
            $result_is_trade_managed = ( new WeappDeliveryService() )->getIsTradeManaged();
            if ($result_is_trade_managed) {

                $list[ 'is_trade_managed' ] = true;
                $pay_service = new PayChannelService();
                $pay_config = $pay_service->getInfo([
                    'type' => PayDict::WECHATPAY,
                    'channel' => ChannelDict::WEAPP
                ]);

                $mch_id = '';
                if (!empty($pay_config)) {
                    $mch_id = $pay_config[ 'config' ][ 'mch_id' ];
                }

                $list[ 'mch_id' ] = $mch_id;
            } else {
                $list[ 'is_trade_managed' ] = false;
            }
        } catch (\Exception $e) {
            $list[ 'is_trade_managed' ] = false;
        }
        return $list;
    }

    /**
     * 详情
     * @param int $order_id
     * @return array
     */
    public function getDetail(int $order_id)
    {
        $field = 'relate_id,activity_type,point,order_id,site_id,order_no,order_type,order_from,out_trade_no,status,member_id,ip,goods_money,delivery_money,order_money,invoice_id,create_time,pay_time,delivery_time,take_time,finish_time,close_time,delivery_type,taker_name,taker_mobile,taker_province,taker_city,taker_district,taker_address,taker_full_address,taker_longitude,taker_latitude,take_store_id,is_enable_refund,member_remark,shop_remark,close_remark,discount_money,is_evaluate';
        $info = $this->model->where([ [ 'site_id', '=', $this->site_id ], [ 'order_id', '=', $order_id ], [ 'member_id', '=', $this->member_id ] ])->field($field)
            ->with(
                [
                    'order_goods' => function($query) {
                        $query->field('extend,order_goods_id, site_id, order_id, member_id, goods_id, sku_id,sku_no, goods_name, sku_name, goods_image, sku_image, price, num, goods_money, discount_money, is_enable_refund, status, order_refund_no, delivery_status, verify_count, verify_expire_time, is_verify, goods_type')->append([ 'goods_image_thumb_small' ]);
                    }
                ]
            )->append([ 'order_from_name', 'order_type_name', 'status_name', 'delivery_type_name' ])->findOrEmpty()->toArray();
        if (!empty($info)) {
            $info[ 'order_status_data' ] = $order_status_list[ $info[ 'status' ] ] ?? [];

            if ($info[ 'delivery_type' ] == DeliveryDict::STORE) {
                $info[ 'store' ] = ( new Store() )->where([ [ 'store_id', '=', $info[ 'take_store_id' ] ] ])
                    ->field('store_id, store_name, store_mobile, store_logo, trade_time, longitude, latitude, full_address')
                    ->findOrEmpty()->toArray();
            }

            if ($info[ 'out_trade_no' ]) {
                $info[ 'pay' ] = ( new Pay() )->where([ [ 'out_trade_no', '=', $info[ 'out_trade_no' ] ] ])
                    ->field('out_trade_no, type, pay_time')->append([ 'type_name' ])
                    ->findOrEmpty()->toArray();
            }

            if ($info[ 'delivery_type' ] == DeliveryDict::EXPRESS) {
                $info[ 'order_delivery' ] = ( new OrderDelivery() )
                    ->where([ [ 'order_id', '=', $info[ 'order_id' ] ] ])
                    ->field('id, order_id, name, delivery_type, express_company_id, sub_delivery_type, express_number, create_time')
                    ->select()->toArray();
            }

            // 查询小程序是否已开通发货信息管理服务
            try {
                $info[ 'mch_id' ] = '';
                $result_is_trade_managed = ( new WeappDeliveryService() )->getIsTradeManaged();
                if ($result_is_trade_managed) {
                    $info[ 'is_trade_managed' ] = true;

                    $pay_service = new PayChannelService();
                    $pay_config = $pay_service->getInfo([
                        'type' => PayDict::WECHATPAY,
                        'channel' => ChannelDict::WEAPP
                    ]);

                    $mch_id = '';
                    if (!empty($pay_config)) {
                        $mch_id = $pay_config[ 'config' ][ 'mch_id' ];
                    }

                    $info[ 'mch_id' ] = $mch_id;
                } else {
                    $info[ 'is_trade_managed' ] = false;
                }
            } catch (\Exception $e) {
                $info[ 'is_trade_managed' ] = false;
            }

            $config = (new CoreOrderConfigService())->getConfig($this->site_id);
            $close_length = $config['close_order_info']['close_length'];

            $info['now_time'] = time();
            $info['expire_time'] = strtotime($info['create_time']) + 60 * $close_length;

        }
        return $info;
    }


    /**
     * 订单关闭
     * @param int $order_id
     * @return true
     */
    public function close(int $order_id)
    {
      
        $data[ 'main_type' ] = OrderLogDict::MEMBER;
        $data[ 'main_id' ] = $this->member_id;
        $data[ 'close_type' ] = OrderDict::SHOP_CLOSE;
        $data[ 'order_id' ] = $order_id;
        $data[ 'site_id' ] = $this->site_id;
          
       return ( new CoreOrderCloseService() )->close($data);
         // true;
    }


    /**
     * 订单收货
     * @param $order_id
     * @return true
     */
    public function finish($order_id)
    {
        $data = [];
        $data[ 'order_id' ] = $order_id;
        $data[ 'main_type' ] = OrderLogDict::MEMBER;
        $data[ 'main_id' ] = $this->member_id;
        $data[ 'site_id' ] = $this->site_id;
        //查询订单
        $where = array (
            [ 'order_id', '=', $order_id ],
            [ 'site_id', '=', $this->site_id ]
        );
        $order = $this->model->where($where)->findOrEmpty()->toArray();
        if (empty($order)) throw new ApiException('SHOP_ORDER_NOT_FOUND');//订单不存在
        if ($order[ 'status' ] != OrderDict::WAIT_TAKE) throw new ApiException('SHOP_ONLY_WAIT_TAKE_CAN_BE_TAKE');//只有待收货的订单才可以收货
        ( new CoreOrderFinishService() )->finish($data);
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
        $info = ( new OrderDelivery() )->where([ [ 'id', '=', $data[ 'id' ] ], [ 'site_id', '=', $this->site_id ] ])->with([
            'company' => function($query) {
                $query->field('company_id, company_name, express_no');
            },
            'order_goods' => function($query) {
                $query->field('goods_name, sku_name, site_id, goods_image, delivery_id, num, price')->append([ 'goods_image_thumb_small' ]);
            }
        ])->field($field)->findOrEmpty()->toArray();

        if (!empty($info) && $info[ 'delivery_type' ] == OrderDeliveryDict::EXPRESS && $info[ 'sub_delivery_type' ] != OrderDeliveryDict::NONE_EXPRESS) {
            $info[ 'mobile' ] = $data[ 'mobile' ];
            $info = ( new CoreOrderService() )->deliverySearch($info);
            return $info;
        }
        return $info;
    }

    public function num()
    {

        $data['wait_pay'] = $this->model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $this->member_id ],
            [ 'status', '=', OrderDict::WAIT_PAY ],
        ])->count() ?? 0;

        $data['wait_shipping'] = $this->model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $this->member_id ],
            [ 'status', '=', OrderDict::WAIT_DELIVERY ],
        ])->count() ?? 0;

        $data['wait_take'] = $this->model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $this->member_id ],
            [ 'status', '=', OrderDict::WAIT_TAKE ],
        ])->count() ?? 0;

        $data['evaluate'] = $this->model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $this->member_id ],
            [ 'status', '=', OrderDict::FINISH ],
            [ 'is_evaluate', '=', 0 ],
        ])->count() ?? 0;

        $data['refund'] = (new OrderRefund())->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'member_id', '=', $this->member_id ],
            [ 'status', 'in', [
                    OrderRefundDict::BUYER_APPLY_WAIT_STORE,
                    OrderRefundDict::STORE_AGREE_REFUND_GOODS_APPLY_WAIT_BUYER,
                    OrderRefundDict::STORE_REFUSE_REFUND_GOODS_APPLY_WAIT_BUYER,
                    OrderRefundDict::BUYER_REFUND_GOODS_WAIT_STORE,
                    OrderRefundDict::STORE_REFUSE_TAKE_REFUND_GOODS_WAIT_BUYER,
                    OrderRefundDict::STORE_AGREE_REFUND_WAIT_TRANSFER,
                    OrderRefundDict::STORE_REFUND_TRANSFERING,
                ]
            ],
        ])->count() ?? 0;

        return $data;
    }
}
