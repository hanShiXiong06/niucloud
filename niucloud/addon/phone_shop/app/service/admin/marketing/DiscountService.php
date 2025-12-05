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

namespace addon\phone_shop\app\service\admin\marketing;

use addon\phone_shop\app\dict\active\ActiveDict;
use addon\phone_shop\app\dict\order\OrderDict;
use addon\phone_shop\app\model\active\Active;
use addon\phone_shop\app\model\active\ActiveGoods;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\order\Order;
use addon\phone_shop\app\model\order\OrderDiscounts;
use addon\phone_shop\app\model\order\OrderGoods;
use addon\phone_shop\app\service\core\marketing\CoreActiveService;
use app\service\core\sys\CoreConfigService;
use core\exception\AdminException;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\db\Query;
use think\facade\Db;
use think\facade\Log;

/**
 * 限时折扣服务层
 * Class DiscountService
 * @package addon\phone_shop\app\service\admin\marketing
 */
class DiscountService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Active();
    }

    /**
     * 获取限时折扣列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'active_id,site_id,active_name,active_desc,active_type,active_goods_type,active_goods_info,active_class,active_class_category,relate_member,active_value,start_time,end_time,active_status,create_time,update_time,active_order_money,active_order_num,active_member_num,active_success_num';
        $order = 'active_id desc';
        $search_model = $this->model->where([ [ 'site_id', '=', $this->site_id ], [ 'active_class', '=', ActiveDict::DISCOUNT ] ])->withSearch([ "active_name", "active_status" ], $where)->append([ 'active_type_name', 'active_goods_type_name', 'active_status_name' ])->field($field)->order($order);
        $list = $this->pageQuery($search_model);
        return $list;
    }

    /**
     * 获取限时折扣详情
     * @param int $active_id
     * @return array
     */
    public function getInfo(int $active_id)
    {
        $info = $this->model->where([ [ 'active_id', '=', $active_id ], [ 'site_id', '=', $this->site_id ], [ 'active_class', '=', ActiveDict::DISCOUNT ] ])->append([ 'active_type_name', 'active_goods_type_name', 'active_status_name' ])->findOrEmpty()->toArray();
        return $info;
    }

    /**
     * 获取限时折扣详情
     * @param int $active_id
     * @return array
     */
    public function getDetail(int $active_id)
    {
        $info = $this->model->where([ [ 'active_id', '=', $active_id ], [ 'active_class', '=', ActiveDict::DISCOUNT ], [ 'site_id', '=', $this->site_id ] ])->append([ 'active_type_name', 'active_goods_type_name', 'active_status_name' ])->findOrEmpty()->toArray();
        $active_goods = ( new ActiveGoods() )->where([ [ 'site_id', '=', $this->site_id ], [ 'active_id', '=', $active_id ] ])->select()->toArray();
        $info[ 'active_goods' ] = $active_goods;
        return $info;
    }

    /**
     * 添加限时折扣
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $goods_list = json_decode($data[ 'goods_data' ], true);

        $goods_ids = array_column($goods_list, 'goods_id');
        $data[ 'start_time' ] = strtotime($data[ 'start_time' ]);
        $data[ 'end_time' ] = strtotime($data[ 'end_time' ]);

        $this->checkGoods($goods_list);

        $check_condition = [
            [ 'active_goods.site_id', '=', $this->site_id ],
            [ 'active_goods.active_class', '=', ActiveDict::DISCOUNT ],
            [ 'active_goods.goods_id', 'in', $goods_ids ],
        ];

        $goods_where = [
            [ 'active.site_id', '=', $this->site_id ],
            [ 'active.active_status', 'in', [ ActiveDict::NOT_ACTIVE, ActiveDict::ACTIVE ] ]
        ];
        $goods_where_or = [
            [ 'active.start_time|active.end_time', 'between', [ $data[ 'start_time' ], $data[ 'end_time' ] ] ],
            [ [ 'active.start_time', '<=', $data[ 'start_time' ] ], [ 'active.end_time', '>=', $data[ 'end_time' ] ] ],
            [ [ 'active.start_time', '>=', $data[ 'start_time' ] ], [ 'active.end_time', '<=', $data[ 'end_time' ] ] ],
        ];

        $active_gooods_model = new ActiveGoods();
        $count = $active_gooods_model->where($check_condition)
            ->withJoin([
                'active' => function(Query $query) use ($goods_where, $goods_where_or) {
                    $query->where($goods_where)->where(function($query) use ($goods_where_or) {
                        $query->whereOr($goods_where_or);
                    });
                },
            ], 'inner')
            ->count();

        if ($count > 0) throw new AdminException('ACTIVE_GOODS_NOT_REPEAR');

        try {

            $active_goods = [];
            foreach ($goods_list as $k => $v) {
                $discount_price = array_column($v[ 'sku_list' ], 'discount_price');
                $active_goods[] = [
                    'site_id' => $this->site_id,
                    'goods_id' => $v[ 'goods_id' ],
                    'active_goods_type' => ActiveDict::GOODS_SINGLE,
                    'active_class' => ActiveDict::DISCOUNT,
                    'active_goods_value' => json_encode($v[ 'sku_list' ]),
                    'active_goods_status' => ActiveDict::NOT_ACTIVE,
                    'active_goods_price' => min($discount_price),
                ];
            }
            $data[ 'active_goods_info' ] = $data[ 'goods_data' ];
            $data[ 'site_id' ] = $this->site_id;
            $data[ 'active_goods' ] = $active_goods;
            $data[ 'active_status' ] = ActiveDict::NOT_ACTIVE;
            $data[ 'active_type' ] = ActiveDict::GOODS;
            $data[ 'active_goods_type' ] = ActiveDict::GOODS_SINGLE;
            $data[ 'active_class' ] = ActiveDict::DISCOUNT;

            $active_id = ( new CoreActiveService() )->add($data);
            return $active_id;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 编辑限时折扣
     * @param int $active_id
     * @param array $data
     * @return bool
     */
    public function edit(int $active_id, array $data)
    {

        $goods_list = json_decode($data[ 'goods_data' ], true);
        $goods_ids = array_column($goods_list, 'goods_id');
        $data[ 'start_time' ] = strtotime($data[ 'start_time' ]);
        $data[ 'end_time' ] = strtotime($data[ 'end_time' ]);

        $this->checkGoods($goods_list);
        $check_condition = [
            [ 'active_goods.site_id', '=', $this->site_id ],
            [ 'active_goods.active_class', '=', ActiveDict::DISCOUNT ],
            [ 'active_goods.active_id', '<>', $active_id ],
            [ 'active_goods.goods_id', 'in', $goods_ids ],
        ];

        $goods_where = [
            [ 'active.site_id', '=', $this->site_id ],
            [ 'active.active_status', 'in', [ ActiveDict::NOT_ACTIVE, ActiveDict::ACTIVE ] ],
            [ 'active.start_time|active.end_time', 'between', [ $data[ 'start_time' ], $data[ 'end_time' ] ] ],
        ];

        $active_gooods_model = new ActiveGoods();
        $count = $active_gooods_model->where($check_condition)
            ->withJoin([
                'active' => function(Query $query) use ($goods_where) {
                    $query->where($goods_where);
                },
            ], 'inner')
            ->count();

        if ($count > 0) throw new AdminException('ACTIVE_GOODS_NOT_REPEAR');

        $this->model->where([ [ 'active_id', '=', $active_id ] ])->update([ 'active_status' => ActiveDict::NOT_ACTIVE ]);
        $this->cancelGoodsDiscount($active_id);

        try {

            $active_goods = [];
            foreach ($goods_list as $k => $v) {
                $discount_price = array_column($v[ 'sku_list' ], 'discount_price');
                $active_goods[] = [
                    'site_id' => $this->site_id,
                    'goods_id' => $v[ 'goods_id' ],
                    'active_goods_type' => ActiveDict::GOODS_SINGLE,
                    'active_class' => ActiveDict::DISCOUNT,
                    'active_goods_value' => json_encode($v[ 'sku_list' ]),
                    'active_goods_status' => ActiveDict::NOT_ACTIVE,
                    'active_goods_price' => min($discount_price),
                ];
            }
            $data[ 'active_goods_info' ] = $data[ 'goods_data' ];
            $data[ 'site_id' ] = $this->site_id;
            $data[ 'active_goods' ] = $active_goods;
            $data[ 'active_status' ] = ActiveDict::NOT_ACTIVE;
            $data[ 'active_type' ] = ActiveDict::GOODS;
            $data[ 'active_goods_type' ] = ActiveDict::GOODS_SINGLE;
            $data[ 'active_class' ] = ActiveDict::DISCOUNT;

            ( new CoreActiveService() )->edit($active_id, $data);

            return $active_id;
        } catch (\Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 商品结构校验
     * @param $goods_list
     * @return void
     */
    public function checkGoods($goods_list)
    {
        if (empty($goods_list)) throw new AdminException('ACTIVE_GOODS_NOT_EMPTY');
        foreach ($goods_list as $k => $v) {
            if (empty($v[ 'sku_list' ])) throw new AdminException('ACTIVE_GOODS_SKU_NOT_EMPTY');
            foreach ($v[ 'sku_list' ] as $key => $value) {
                if (!isset($value[ 'is_enabled' ])) throw new AdminException('ACTIVE_IS_ENABLED_NOT_EMPTY');

                if ($value[ 'is_enabled' ]) {
                    if (empty($value[ 'discount_type' ])) throw new AdminException('ACTIVE_GOODS_DISCOUNT_TYPE_NOT_EMPTY');
                    if (!in_array($value[ 'discount_type' ], [ 'discount', 'reduce', 'specify' ])) throw new AdminException('ACTIVE_GOODS_DISCOUNT_TYPE_ERROR');
                    if (empty($value[ 'discount_price' ])) throw new AdminException('ACTIVE_GOODS_DISCOUNT_PRICE_NOT_EMPTY');
                    if (empty($value[ 'specify_price' ])) throw new AdminException('ACTIVE_GOODS_SPECIFY_PRICE_NOT_EMPTY');
                    if (empty($value[ 'discount_rate' ])) throw new AdminException('ACTIVE_GOODS_DISCOUNT_RATE_NOT_EMPTY');
                    if (empty($value[ 'reduce_money' ])) throw new AdminException('ACTIVE_GOODS_REDUCE_MONEY_NOT_EMPTY');
                }
            }
        }
    }

    /**
     * 删除限时折扣
     * @param int $active_id
     * @return bool
     */
    public function del(int $active_id)
    {
        ( new CoreActiveService() )->del($active_id, $this->site_id);
        return true;
    }

    /**
     * 设置商品限时折扣
     * @param $active_id
     * @return void
     */
    public function setGoodsDiscount($active_id)
    {
        $info = $this->model->where([ [ 'active_id', '=', $active_id ] ])->findOrEmpty()->toArray();
        Log::write('setGoodsDiscount:' . json_encode($info));
        if (empty($info)) return true;
        if ($info[ 'active_status' ] != ActiveDict::ACTIVE) return true;
        $active_goods_model = new ActiveGoods();
        $shop_goods_model = new Goods();
        $shop_goods_sku_model = new GoodsSku();

        $active_goods_list = $active_goods_model->where([ [ 'active_id', '=', $active_id ] ])->field('goods_id, active_id,active_goods_value')->select()->toArray();
        $active_goods_ids = array_column($active_goods_list, 'goods_id', );

        $shop_goods_model->where([ [ 'goods_id', 'in', $active_goods_ids ] ])->update([ 'is_discount' => 1 ]);

        foreach ($active_goods_list as $k => $v) {
            $active_goods_sku = json_decode($v[ 'active_goods_value' ], true);
            foreach ($active_goods_sku as $key => $val) {
                if ($val[ 'is_enabled' ]) {
                    $shop_goods_sku_model->where([ [ 'sku_id', '=', $val[ 'sku_id' ] ] ])->update([
                        'sale_price' => $val[ 'discount_price' ]
                    ]);
                }
            }
        }
        return true;
    }

    /**
     * 设置商品取消限时折扣
     * @param $active_id
     * @return void
     */
    public function cancelGoodsDiscount($active_id)
    {
        $info = $this->model->where([ [ 'active_id', '=', $active_id ] ])->findOrEmpty()->toArray();
        Log::write('cancelGoodsDiscount:' . json_encode($info));
        if (empty($info)) return true;
        if ($info[ 'active_status' ] == ActiveDict::ACTIVE) return true;

        $active_goods_model = new ActiveGoods();
        $shop_goods_model = new Goods();
        $shop_goods_sku_model = new GoodsSku();

        $active_goods_list = $active_goods_model->where([ [ 'active_id', '=', $active_id ] ])->field('goods_id, active_id,active_goods_value')->select()->toArray();
        $active_goods_ids = array_column($active_goods_list, 'goods_id');
        $shop_goods_model->where([ [ 'goods_id', 'in', $active_goods_ids ] ])->update([ 'is_discount' => 0 ]);
        $shop_goods_sku_model->where([ [ 'goods_id', 'in', $active_goods_ids ] ])->update([ 'sale_price' => Db::raw('price') ]);

        return true;
    }


    /**
     * 活动关闭
     * @return void
     */
    public function discountClose($active_id)
    {
        ( new CoreActiveService() )->close($active_id, $this->site_id);
        $this->cancelGoodsDiscount($active_id);
        return true;
    }

    /**
     * 活动开启
     * @return void
     */
    public function discountStartAfter($active_id)
    {
        $this->setGoodsDiscount($active_id);
        return true;

    }

    /**
     * 活动结束
     * @return void
     */
    public function discountEndAfter($active_id)
    {
        $this->cancelGoodsDiscount($active_id);
        return true;
    }


    /**
     * 领取记录
     * @param int $id
     * @return void
     */
    public function order(int $active_id, $where)
    {
        $order = 'create_time desc';
        $search_model = ( new Order() )
            ->where([ [ 'order.site_id', '=', $this->site_id ], [ 'order.pay_time', '>', 0 ] ])
            ->withSearch([ 'search_type', 'order_from', 'join_status', 'create_time', 'pay_time' ], $where)
            ->with([
                'member' => function($query) {
                    $query->field('member_id, nickname, mobile, headimg');
                },
                'pay'
            ])
            ->withJoin([ 'shopOrderDiscount' => function($query) use ($active_id) {
                $query->where([ [ 'discount_type', '=', ActiveDict::DISCOUNT ], [ 'discount_type_id', '=', $active_id ] ]);
            } ], 'left')->order($order);

        $order_status_list = OrderDict::getStatus();
        $list = $this->pageQuery($search_model, function($item, $key) use ($order_status_list) {
            $item[ 'order_status_data' ] = $order_status_list[ $item[ 'status' ] ] ?? [];
            $item_pay = $item[ 'pay' ];
            if (!empty($item_pay)) {
                $item_pay->append([ 'type_name' ]);
            }
        });
        return $list;
    }


    /**
     * 会员
     * @param int $active_id
     * @param $where
     * @return array
     * @throws \think\db\exception\DbException
     */
    public function member(int $active_id, $where)
    {
        $active_goods_ids = ( new ActiveGoods() )->where([ [ 'active_id', '=', $active_id ], [ 'site_id', '=', $this->site_id ] ])->column('goods_id');
        $order = 'create_time desc';
        $search_model = ( new Order() )
            ->where([ [ 'order.site_id', '=', $this->site_id ], [ 'order.pay_time', '>', 0 ] ])
            ->withJoin([ 'shopOrderDiscount' => function($query) use ($active_id) {
                $query->where([ [ 'discount_type', '=', ActiveDict::DISCOUNT ], [ 'discount_type_id', '=', $active_id ] ]);
            } ], 'left')
            ->with([ 'member' => function($query) {
                $query->field('username,member_id, nickname, mobile, headimg');
            }, 'orderGoods' ])
            ->order($order)
            ->field('order.member_id,COUNT(member_id) as member_count, group_concat(order.create_time) as create_time_data,group_concat(order.order_id) as order_ids')
            ->group('member_id');
        $list = $this->pageQuery($search_model, function($item, $key) use ($active_goods_ids) {
            $create_time_data = explode(',', $item[ 'create_time_data' ]);

            $item[ 'create_time' ] = date('Y-m-d H:i:s', end($create_time_data));

        });

        if (!empty($list[ 'data' ])) {
            $member_ids = array_column($list[ 'data' ], 'member_id');
            $data_list = ( new Order() )
                ->where([ [ 'order.site_id', '=', $this->site_id ], [ 'order.member_id', 'in', $member_ids ], [ 'order.pay_time', '>', 0 ] ])
                ->withJoin([ 'shopOrderDiscount' => function($query) use ($active_id) {
                    $query->where([ [ 'discount_type', '=', ActiveDict::DISCOUNT ], [ 'discount_type_id', '=', $active_id ] ]);
                } ], 'left')
                ->with([ 'orderGoods' ])
                ->select()->toArray();

            foreach ($list[ 'data' ] as $key => $item) {
                $item[ 'active_order_money' ] = 0;
                foreach ($data_list as $k => $v) {
                    if ($item[ 'member_id' ] == $v[ 'member_id' ]) {
                        foreach ($v[ 'orderGoods' ] as $order_goods) {
                            if (in_array($order_goods[ 'goods_id' ], $active_goods_ids)) {
                                $item[ 'active_order_money' ] += $order_goods[ 'order_goods_money' ];
                            }
                        }
                    }

                }
                $list[ 'data' ][ $key ] = $item;
            }
        }

        return $list;
    }

    /**
     * 商品
     * @param int $active_id
     * @param $where
     * @return array
     * @throws \think\db\exception\DbException
     */
    public function goods(int $active_id, $where)
    {
        $active_gooods_model = new ActiveGoods();
        $search_model = $active_gooods_model
            ->where([ [ 'active_goods.site_id', '=', $this->site_id ], [ 'active_goods.active_id', '=', $active_id ] ])
            ->field('active_goods_id,active_id,site_id,goods_id,active_goods_type,active_class,active_goods_label,active_goods_category,active_goods_value,active_goods_status,active_goods_point,active_goods_price,active_goods_stock,active_goods_order_money,active_goods_order_num,active_goods_member_num,active_goods_success_num')
            ->withJoin([ 'goods' => function($query) use ($where) {
                $query->where([ [ 'goods_name', 'like', '%' . $where[ 'keyword' ] . '%' ] ]);
            } ])->with([ 'goodsSku' ])->append([ 'goods.goods_cover_thumb_small' ]);
        return $this->pageQuery($search_model);
    }

    /**
     * 配置设置
     * @param array $params
     * @return array
     */
    public function setConfig($key, $data)
    {
        ( new CoreConfigService() )->setConfig($this->site_id, $key, $data);
        return true;
    }

    /**
     * 折扣页面轮播图配置
     */
    public function getDiscountBannerConfig()
    {
        $data = ( new CoreConfigService() )->getConfigValue($this->site_id, 'SHOP_DISCOUNT_BANNER_CONFIG');
        if (empty($data)) {
            $data = [];
        }
        return $data;
    }

    /**
     * 订单支付后累计
     * @param $order
     * @return void
     */
    public function orderPayAfter($order)
    {
        $order_discount_model = new OrderDiscounts();
        $order_discount = $order_discount_model->where([ [ 'order_id', '=', $order[ 'order_id' ] ], [ 'discount_type', '=', ActiveDict::DISCOUNT ] ])->field('order_id,discount_type_id')->findOrEmpty()->toArray();
        $active_id = $order_discount[ 'discount_type_id' ] ?? 0;
        if (empty($order_discount) || empty($active_id)) return true;

        $active_info = $this->model->where([ [ 'active_id', '=', $active_id ] ])->findOrEmpty()->toArray();

        $active_goods_model = ( new ActiveGoods() );

        $shop_order_model = new Order();
        $shop_order_goods_model = new OrderGoods();
        $order_info = $shop_order_model->where([ [ 'order_id', '=', $order[ 'order_id' ] ] ])->field('order_id, member_id')
            ->with(
                [
                    'order_goods' => function($query) {
                        $query->field('order_goods_id, order_id, member_id, goods_id, sku_id,  price, num, goods_money');
                    },
                ])->findOrEmpty()->toArray();

        $order_goods = array_column($order_info[ 'order_goods' ], null, 'sku_id');

        $order_goods_ids = array_column($order_info[ 'order_goods' ], 'goods_id');

        $active_goods = $active_goods_model->where([ [ 'active_id', '=', $active_id ], [ 'goods_id', 'in', $order_goods_ids ] ])->select()->toArray();

        $count = $order_discount_model->where([ [ 'discount_type', '=', ActiveDict::DISCOUNT ], [ 'discount_type_id', '=', $active_id ] ])->withJoin([ 'shoporder' => function($query) use ($order_info) {
            $query->where([ [ 'member_id', '=', $order_info[ 'member_id' ] ], [ 'pay_time', '>', 0 ] ]);
        } ])->count();

        $active_order_money = 0;
        $active_success_num = 0;

        foreach ($active_goods as $k => $v) {
            $active_goods_value = json_decode($v[ 'active_goods_value' ], true);
            $active_goods_sku = array_column($active_goods_value, null, 'sku_id');
            $active_goods_member_num = $v[ 'active_goods_member_num' ];

            //这个会员通过这个活动购买了几次这个商品
            $member_goods_order_ids = $shop_order_goods_model->where([ [ 'orderMain.order_id', '<>', $order_info[ 'order_id' ] ], [ 'orderMain.member_id', '=', $order_info[ 'member_id' ] ], [ 'goods_id', '=', $v[ 'goods_id' ] ] ])->withJoin([ 'orderMain' => function($query) use ($order_info) {
                $query->where([ [ 'pay_time', '>', 0 ] ]);
            } ])->column('orderMain.order_id');

            $member_active_goods_count = $order_discount_model->where([ [ 'discount_type', '=', ActiveDict::DISCOUNT ], [ 'discount_type_id', '=', $active_id ], [ 'order_id', 'in', $member_goods_order_ids ] ])->count();

            if (empty($member_active_goods_count)) $active_goods_member_num += 1;

            $save_data = [
                'active_goods_order_money' => $v[ 'active_goods_order_money' ],
                'active_goods_order_num' => $v[ 'active_goods_order_num' ] + 1,
                'active_goods_member_num' => $active_goods_member_num,
                'active_goods_success_num' => $v[ 'active_goods_success_num' ],
            ];

            foreach ($active_goods_sku as $key => $val) {
                if (isset($order_goods[ $val[ 'sku_id' ] ])) {
                    $save_data[ 'active_goods_order_money' ] += $order_goods[ $val[ 'sku_id' ] ][ 'goods_money' ];
                    $save_data[ 'active_goods_success_num' ] += $order_goods[ $val[ 'sku_id' ] ][ 'num' ];

                    $active_order_money += $order_goods[ $val[ 'sku_id' ] ][ 'goods_money' ];
                    $active_success_num += $order_goods[ $val[ 'sku_id' ] ][ 'num' ];
                }
            }
            $active_goods_model->where([ [ 'active_goods_id', '=', $v[ 'active_goods_id' ] ] ])->update($save_data);
        }

        $active_member_num = $active_info[ 'active_member_num' ];
        if ($count < 2) $active_member_num += 1;

        $active_save_data = [
            'active_order_money' => $active_order_money + $active_info[ 'active_order_money' ],
            'active_order_num' => $active_info[ 'active_order_num' ] + 1,
            'active_member_num' => $active_member_num,
            'active_success_num' => $active_success_num,
        ];

        $this->model->where([ [ 'active_id', '=', $active_id ] ])->update($active_save_data);

        return true;

    }


}
