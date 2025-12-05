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

namespace addon\phone_shop\app\service\api\cart;

use addon\phone_shop\app\model\cart\Cart;
use addon\phone_shop\app\service\api\goods\GoodsService;
use app\dict\member\MemberDict;
use app\service\core\member\CoreMemberService;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 购物车服务层
 * Class CartService
 */
class CartService extends BaseApiService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new Cart();
    }

    /**
     * 添加购物车
     * @param $data
     * @return mixed
     */
    public function add($data)
    {
        $member_info = ( new CoreMemberService() )->getInfoByMemberId($this->site_id, $this->member_id, 'status');

        if (empty($member_info)) throw new CommonException('SHOP_ORDER_BUYER_NOT_FOUND');//无效的账号
        if ($member_info['status'] == MemberDict::OFF) throw new CommonException('SHOP_ORDER_BUYER_LOCKED');//账号被锁定
        $data[ 'member_id' ] = $this->member_id;
        $data[ 'site_id' ] = $this->site_id;
        $info = $this->model->where([
            [ 'member_id', '=', $data[ 'member_id' ] ],
            [ 'goods_id', '=', $data[ 'goods_id' ] ],
            [ 'sku_id', '=', $data[ 'sku_id' ] ],
            [ 'site_id', '=', $this->site_id ],
        ])->field('id,num')->findOrEmpty()->toArray();

        if (!empty($info)) {
            // 存在，更新数量
            $update = [
                'num' => $info[ 'num' ] + $data[ 'num' ]
            ];
            $this->model->where([ [ 'id', '=', $info[ 'id' ] ] ])->update($update);
            return $info[ 'id' ];
        } else {
            // 添加
            $res = $this->model->create($data);
            return $res->id;
        }
    }

    /**
     * 编辑购物车数量
     * @param $data
     * @return bool
     */
    public function edit($data)
    {
        $data[ 'member_id' ] = $this->member_id;
        $data[ 'site_id' ] = $this->site_id;
        $info = $this->model->where([
            [ 'id', '=', $data[ 'id' ] ],
            [ 'member_id', '=', $data[ 'member_id' ] ],
            [ 'site_id', '=', $data[ 'site_id' ] ],
        ])->field('sku_id')->findOrEmpty()->toArray();

        if (empty($info)) return false;

        $update = [
            'num' => $data[ 'num' ]
        ];

        if (!empty($data[ 'sku_id' ]) && $info[ 'sku_id' ] != $data[ 'sku_id' ]) {
            $update[ 'sku_id' ] = $data[ 'sku_id' ];

            $cart_info = $this->model->where([
                [ 'sku_id', '=', $data[ 'sku_id' ] ],
                [ 'member_id', '=', $this->member_id ],
            ])->field('id')->findOrEmpty()->toArray();

            if (!empty($cart_info)) {
                $this->model->where([ [ 'id', '=', $cart_info[ 'id' ] ] ])->delete();
            }
        }

        $this->model->where([ [ 'id', '=', $data[ 'id' ] ] ])->update($update);
        return true;
    }

    /**
     * 购物车删除，支持批量
     * @param $data
     * @return bool
     */
    public function del($data)
    {
        $this->model->where([ [ 'member_id', '=', $this->member_id ], [ 'site_id', '=', $this->site_id ], [ 'id', 'in', $data[ 'ids' ] ] ])->delete();
        return true;
    }

    /**
     * 清空购物车
     * @return bool
     */
    public function clear()
    {
        $this->model->where([ [ 'member_id', '=', $this->member_id ], [ 'site_id', '=', $this->site_id ] ])->delete();
        return true;
    }

    /**
     * 获取购物车列表
     * @param $data
     * @return array
     */
    public function getList($data)
    {
        $data[ 'member_id' ] = $this->member_id;
        $field = 'id, member_id, goods_id, site_id, sku_id, num, market_type, market_type_id, status, invalid_remark';
        $order = "create_time desc";
        $list = $this->model->where([ [ 'site_id', '=', $this->site_id ] ])->withSearch([ "member_id" ], $data)->field($field)
            ->with([
                'goodsSku' => function($query) {
                    $query->withField('sku_id, site_id, sku_image, price, sale_price, stock,member_price');
                },
                'goods' => function($query) {
                    $query->withField('goods_id, site_id, status,delete_time,member_discount,is_discount');
                },
            ])->order($order)->select()->toArray();

        $goods_service = new GoodsService();
        $member_info = $goods_service->getMemberInfo();
        foreach ($list as $k => &$v) {
            if(!empty($v[ 'goodsSku' ])) {
                $v[ 'goodsSku' ][ 'member_price' ] = $goods_service->getMemberPrice($member_info, $v[ 'goods' ][ 'member_discount' ], $v[ 'goodsSku' ][ 'member_price' ], $v[ 'goodsSku' ][ 'price' ], $v[ 'goodsSku' ][ 'market_price' ]);
            }
        }
        return $list;
    }

    /**
     * 获取购物车商品列表
     * @param $data
     * @return array
     */
    public function getGoodsList($data)
    {
        $data[ 'member_id' ] = $this->member_id;
        $field = 'id, member_id, goods_id, sku_id, num, market_type, market_type_id, status, invalid_remark';
        $order = "create_time desc";
        $list = $this->model->where([ [ 'site_id', '=', $this->site_id ] ])->withSearch([ "member_id" ], $data)->field($field)
            ->with([
                'goods' => function($query) {
                    $query->withField('goods_id, site_id, goods_name, goods_type, sub_title, goods_cover, unit, stock, sale_num + virtual_sale_num as sale_num, status,delete_time,member_discount,is_discount');
                },
                'goodsSku' => function($query) {
                    $query->withField('sku_id, site_id, sku_name, sku_image, sku_no, goods_id, sku_spec_format, price, market_price, sale_price, stock, weight, volume, is_default,member_price');
                },
                // 商品规格项/规格值列表
                'goodsSpec' => function($query) {
                    $query->field('spec_id, goods_id, spec_name, spec_values');
                },
            ])->order($order)->select()->toArray();

        $goods_service = new GoodsService();
        $member_info = $goods_service->getMemberInfo();
        foreach ($list as $k => &$v) {
            if(!empty($v[ 'goodsSku' ])) {
                $v[ 'goodsSku' ][ 'member_price' ] = $goods_service->getMemberPrice($member_info, $v[ 'goods' ][ 'member_discount' ], $v[ 'goodsSku' ][ 'member_price' ], $v[ 'goodsSku' ][ 'price' ]);
            }
        }
        return $list;
    }

    /**
     * 获取购物车数量
     * @param $data
     * @return float
     */
    public function getSum($data)
    {
        $condition = [
            [ 'member_id', '=', $this->member_id ],
            [ 'site_id', '=', $this->site_id ]
        ];
        if (!empty($data[ 'goods_id' ])) {
            $condition[] = [ 'goods_id', '=', $data[ 'goods_id' ] ];
        }
        return $this->model->where($condition)->sum('num');
    }

}
