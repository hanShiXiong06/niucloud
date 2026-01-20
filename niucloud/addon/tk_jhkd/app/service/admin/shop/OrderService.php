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

namespace addon\tk_jhkd\app\service\admin\shop;

use addon\shop\app\dict\order\OrderDeliveryDict;
use addon\shop\app\dict\delivery\DeliveryDict;
use addon\shop\app\dict\order\OrderDict;
use addon\shop\app\dict\order\OrderGoodsDict;
use addon\shop\app\model\delivery\Store;
use addon\tk_jhkd\app\model\shop_order\Order;
use addon\tk_jhkd\app\model\order\ShopOrder;
use addon\shop\app\model\order\OrderDelivery;
use addon\shop\app\model\order\OrderGoods;
use addon\tk_jhkd\app\model\shop_address\ShopAddress;
use addon\tk_jhkd\app\model\TkjhkdBrand;
use addon\tk_jhkd\app\service\core\CommonService;
use app\model\pay\Pay;
use app\service\core\site\CoreSiteService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use Exception;
use think\db\exception\DbException;
use think\db\Query;
use addon\tk_jhkd\app\model\shop_order\ShopOrder as YdShopOrder;
use think\facade\Db;

/**
 * 订单
 */
class OrderService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Order();
        $this->sendAddressModel = new ShopAddress();
        $this->shopOrderModel = new ShopOrder();
    }

    public function yidaCancel($data)
    {
        $orderInfo = $this->model->where(['order_no' => $data['order_no']])->findOrEmpty();
        $data['order_id'] = $orderInfo['order_id'];
        //更改shop运单及商品发货信息
        $deliveryInfo = (new OrderDelivery())->where(['order_id' => $data['order_id']])->findOrEmpty();
        if (!$deliveryInfo->isEmpty()) {
            (new OrderGoods)->where(['delivery_id' => $deliveryInfo['id']])->update(['delivery_status' => OrderDeliveryDict::WAIT_DELIVERY]);
            (new OrderDelivery())->where(['order_id' => $data['order_id']])->delete();
        }
        //更改shop订单状态
        $this->model->where(['order_id' => $data['order_id']])->update(['status' => OrderDict::WAIT_DELIVERY]);

    }

    public function cancelOrder($data)
    {
        $orderInfo = $this->model->where(['order_no' => $data['order_no']])->findOrEmpty();
        $data['order_id'] = $orderInfo['order_id'];
        Db::startTrans();
        try {
            //更改shop运单及商品发货信息
            $deliveryInfo = (new OrderDelivery())->where(['order_id' => $data['order_id']])->select();
            if (!$deliveryInfo->isEmpty()) {
                foreach ($deliveryInfo as $k => $v) {
                    (new OrderGoods)->where(['delivery_id' => $v['id']])->update(['delivery_status' => OrderDeliveryDict::WAIT_DELIVERY]);
                    (new OrderDelivery())->where(['order_id' => $data['order_id']])->delete();
                }
            }
            $this->model->where(['order_id' => $data['order_id']])->update(['status' => OrderDict::WAIT_DELIVERY]);
            //易达取消发单
            $res = (new YdShopOrder())->where(['order_no' => $data['order_no'], 'is_send' => 1])->select();
            foreach ($res as $k => $v) {
                if ($v['is_pick'] == 1) {
                    throw new Exception('订单已揽收，暂不支持取消');
                }
                $params = [
                    'order_no' => $v['yida_order_no'],
                    'task_id' => ''
                ];

                $resInfo = event('DeliveryCancelOrder', ['site_id' => $this->site_id, 'data' => $params])[0];

                if ($resInfo['data'] == true || strpos($resInfo['msg'], '已取消') !== false) {

                    (new YdShopOrder())->where(['id' => $v['id']])->update([
                        'is_send' => 0,
                        'is_pick' => 0,
                        'order_status_name' => '主动取消发单'
                    ]);
                } else {
                    throw new CommonException($resInfo['msg']);
                }
            }
            Db::commit();
            return [];
        } catch (Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }

    }

    public function checkShop()
    {
        $addons = (new CoreSiteService())->getAddonKeysBySiteId($this->site_id);
        return $addons;
    }

    public function sendAddress($where)
    {
        $field = 'id,contact_name,mobile,province_id,city_id,district_id,address,full_address,lat,lng,is_delivery_address,is_refund_address,is_default_delivery,is_default_refund';
        $order = '';
        $wheret = [];
        if ($where['is_default_delivery'] != '') {
            $wheret[] = ['is_default_delivery', '=', $where['is_default_delivery']];
        }
        $search_model = $this->sendAddressModel->where([['site_id', '=', $this->site_id]])
            ->where($wheret)
            ->withSearch(["mobile", "full_address"], $where)->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        if (count($list) == 0) throw new \Exception('请发货地址并设置一个默认发货地址');
        return $list;
    }

    public function preOrder($data)
    {
        $order_info = $this->shopOrderModel
            ->where(['site_id' => $this->site_id, 'order_id' => $data['order_id']])
            ->with(
                [
                    'order_goods' => function ($query) {
                        $query->field('order_goods_id, order_id, member_id, goods_id, sku_id, goods_name, sku_name, goods_image, sku_image, price, num, goods_money, is_enable_refund, goods_type, delivery_status, status')->append(['delivery_status_name']);
                    }
                ])
            ->findOrEmpty()->append(['province_name', 'city_name', 'district_name']);
        if ($order_info->isEmpty()) throw new \Exception('订单不存在');
        $sendaddress = $this->sendAddressModel->where(['site_id' => $this->site_id, 'id' => $data['send_id']])->findOrEmpty()->append(['province_name', 'city_name', 'district_name']);
        if (empty($sendaddress)) throw new \Exception('收货地址不存在');
        //易达需要查询省市区的地址name
        $goods_name = $order_info['order_goods'][0]['goods_name'];
        $encoding = mb_detect_encoding($goods_name, 'UTF-8, ISO-8859-1', true);
        if ($encoding != 'UTF-8') {
            $goods_name = mb_convert_encoding($goods_name, 'UTF-8', $encoding);
        }
        $length = mb_strlen($goods_name, 'UTF-8'); // 使用 mb_strlen() 获取 UTF-8 编码下的字符串长度
        if ($length > 30) {
            $goods_name = mb_substr($goods_name, 0, 30, 'UTF-8'); // 使用 mb_substr() 截取 UTF-8 编码下的字符串
        }

        $params = [
            "receiveAddress" => $order_info['taker_address'],
            "receiveMobile" => $order_info['taker_mobile'],
            "receiveTel" => "",
            "receiveName" => $order_info['taker_name'],
            "receiveProvince" => $order_info['province_name'],
            "receiveCity" => $order_info['city_name'],
            "receiveDistrict" => $order_info['district_name'],
            "senderAddress" => $sendaddress['address'],
            "senderMobile" => $sendaddress['mobile'],
            "senderTel" => "",
            "senderName" => $sendaddress['contact_name'],
            "senderProvince" => $sendaddress['province_name'],
            "senderCity" => $sendaddress['city_name'],
            "senderDistrict" => $sendaddress['district_name'],
            "goods" => $goods_name,
            "packageCount" => 1,
            "weight" => $data['weight'],
            "vloumLong" => '10',
            "vloumWidth" => '10',
            "vloumHeight" => '10',
            "guaranteeValueAmount" => $data['bj_price'],
            "customerType" => 'kd',
            "onlinePay" => "ALL",
            "payMethod" => 10,
            "thirdNo" => "TD965412356"
        ];
        $resInfo = event('DeliveryPreOrder', ['site_id' => $this->site_id, 'data' => $params]);
        $resInfo = $resInfo[0];
        $url = (new CommonService())->getUrl();
        $dataInfo = [];
        foreach ($resInfo as $k1 => $v1) {
            $brand = (new TkjhkdBrand())->where(['delivery_type' => $v1['deliveryType']])->findOrEmpty();
            if (!$brand->isEmpty()) {
                $v1['logo'] = $url . '/' . $brand['logo'];
            }
            $dataInfo[] = $v1;
        }
        return $dataInfo;
    }

    /**
     * 分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where)
    {
        $field = 'order_id,order_no,order_type,order_from,out_trade_no,status,member_id,ip,goods_money,delivery_money,order_money,create_time,pay_time,delivery_type,taker_name,taker_mobile,taker_full_address,take_store_id,is_enable_refund,member_remark,shop_remark,close_remark,pay_money';
        $order = 'create_time desc';

        $pay_where = [];
        if ($where['pay_type']) {
            $pay_where[] = ['pay.type', '=', $where['pay_type']];
        }
        $search_model = $this->model
            ->where([['order.site_id', '=', $this->site_id], ['delivery_type', '=', 'express']])
            ->withSearch(['search_type', 'order_from', 'join_status', 'create_time', 'join_pay_time'], $where)
            ->field($field)
            ->withJoin([
                'pay' => function (Query $query) use ($pay_where) {
                    $query->where($pay_where);
                },
            ], 'left')
            ->with([
                'order_goods' => function ($query) {
                    $query->field('order_goods_id, order_id, member_id, goods_id, sku_id, goods_name, sku_name, goods_image, sku_image, price, num, goods_money, is_enable_refund, goods_type, delivery_status, status')->append(['delivery_status_name', 'status_name']);
                },
                'member' => function ($query) {
                    $query->field('member_id, nickname, mobile, headimg');
                }
            ])->order($order)->append(['order_from_name', 'order_type_name', 'status_name', 'delivery_type_name']);
        $order_status_list = OrderDict::getStatus();
        $list = $this->pageQuery($search_model, function ($item, $key) use ($order_status_list) {
            $item['order_status_data'] = $order_status_list[$item['status']] ?? [];
            $item_pay = $item['pay'];
            if (!empty($item_pay)) {
                $item_pay->append(['type_name']);
            }
        });
        return $list;
    }

    /**
     * 详情
     * @param int $order_id
     * @return array
     */
    public function getDetail(int $order_id)
    {
        $field = 'order_id,order_no,order_type,order_from,out_trade_no,status,member_id,ip,goods_money,delivery_money,order_money,invoice_id,create_time,pay_time,delivery_time,take_time,finish_time,close_time,delivery_type,taker_name,taker_mobile,taker_province,taker_city,taker_district,taker_address,taker_full_address,taker_longitude,taker_latitude,take_store_id,is_enable_refund,member_remark,shop_remark,close_remark,discount_money';
        $info = $this->model->where([['order_id', '=', $order_id], ['site_id', '=', $this->site_id]])->field($field)
            ->with(
                [
                    'order_goods' => function ($query) {
                        $query->field('order_goods_id, order_id, member_id, goods_id, sku_id, goods_name, sku_name, goods_image, sku_image, price, num, goods_money, is_enable_refund, goods_type, delivery_status, status')->append(['delivery_status_name']);
                    },
                    'member' => function ($query) {
                        $query->field('member_id, nickname, mobile, headimg');
                    },
                    'order_log' => function ($query) {
                        $query->field('order_id, content, main_type, create_time, main_id, type')->order("create_time desc, id desc")->append(['main_type_name', 'type_name', 'main_name']);
                    }
                ])->append(['order_from_name', 'order_type_name', 'status_name', 'delivery_type_name'])->findOrEmpty()->toArray();
        $order_status_list = OrderDict::getStatus();
        if (!empty($info)) $info['order_status_data'] = $order_status_list[$info['status']] ?? [];

        if ($info['delivery_type'] == DeliveryDict::STORE) {
            $info['store'] = (new Store())->where([['store_id', '=', $info['take_store_id']]])
                ->field('store_id, store_name, full_address, store_mobile, trade_time')
                ->findOrEmpty()->toArray();
        }

        if ($info['delivery_type'] == DeliveryDict::EXPRESS) {
            $info['order_delivery'] = (new OrderDelivery())
                ->where([['order_id', '=', $info['order_id']]])
                ->field('id, order_id, name, delivery_type, sub_delivery_type,express_company_id, express_number, create_time')
                ->select()->toArray();
        }

        if ($info['out_trade_no']) {
            $info['pay'] = (new Pay())->where([['out_trade_no', '=', $info['out_trade_no']]])
                ->field('out_trade_no, type, pay_time')->append(['type_name'])->findOrEmpty()->toArray();
        }

        return $info;
    }

    /**
     * 商家留言
     * @param $data
     * @return bool
     */
    public function shopRemark($data)
    {
        $this->model->where([['order_id', '=', $data['order_id']], ['site_id', '=', $this->site_id]])->update(['shop_remark' => $data['shop_remark']]);
        return true;
    }

    /**
     * 订单数量统计
     * @throws DbException
     */
    public function getOrderCount()
    {
        $data = [
            "wait_pay_order" => 0, //待付款
            "wait_delivery_order" => 0, //待发货
            "wait_take_order" => 0, //待收货
            "refund_order" => 0, //退款中（订单项）
        ];

        $data['wait_pay_order'] = $this->model->where([['site_id', '=', $this->site_id], ['status', '=', OrderDict::WAIT_PAY]])->count();
        $data['wait_delivery_order'] = $this->model->where([['site_id', '=', $this->site_id], ['status', '=', OrderDict::WAIT_DELIVERY]])->count();
        $data['wait_take_order'] = $this->model->where([['site_id', '=', $this->site_id], ['status', '=', OrderDict::WAIT_TAKE]])->count();
        $data['refund_order'] = (new OrderGoods())->where([['site_id', '=', $this->site_id], ['status', '=', OrderGoodsDict::REFUNDING]])->count();

        return $data;
    }
}
