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

namespace addon\phone_shop\app\service\api\marketing;

use addon\phone_shop\app\dict\active\ActiveDict;
use addon\phone_shop\app\model\active\Active;
use addon\phone_shop\app\model\active\ActiveGoods;
use addon\phone_shop\app\model\goods\Goods;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use think\facade\Db;

/**
 * 限时折扣服务层
 * Class DiscountService
 * @package addon\phone_shop\app\service\api\marketing
 */
class DiscountService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Active();
    }

    /**
     * 查询商品正在参与的限时折扣活动
     * @param $goods_id
     * @return mixed
     */
    public function getInfoByGoods($goods_id)
    {
        $active_goods_model = new ActiveGoods();

        $field = 'active_goods_id,active_id';

        $info = $active_goods_model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'active_goods_status', '=', 'active' ],
            [ 'active_goods_type', '=', 'single' ],
            [ 'active_class', '=', 'discount' ],
            [ 'goods_id', '=', $goods_id ]
        ])->field($field)
            ->with([
                'active' => function($query) {
                    $query->withField('active_id,active_name, active_desc, start_time, end_time');
                }
            ])
            ->findOrEmpty()->toArray();
        return $info;

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
     * 获取商品列表
     * @param array $where
     * @return array
     */
    public function getGoodsPage(array $where = [])
    {
        $field = 'site_id,goods_id,label_ids,goods_name,sub_title,goods_category,goods_type,goods_cover,unit,status,sale_num + goods.virtual_sale_num as sale_num,member_discount,is_discount';

        $sku_where = [
            [ 'goods.site_id', '=', $this->site_id ],
            [ 'status', '=', 1 ]
        ];

        if (!empty($where[ 'keyword' ])) {
            $sku_where[] = [ 'goods_name|sub_title', 'like', '%' . $where[ 'keyword' ] . '%' ];
        }

        // 参数过滤
        if (!empty($where[ 'order' ]) && in_array($where[ 'order' ], [ 'sale_num', 'sale_price' ])) {
            $order = $where[ 'order' ] . ' ' . $where[ 'sort' ];
        } else {
            $order = 'sort desc,create_time desc';
        }

        $search_model = ( new Goods() )
            ->withSearch([ "brand_id", "goods_category", "label_ids", 'service_ids' ], $where)
            ->field($field)
            ->with([ 'skuList' ])
            ->withJoin([ 'activeGoods' => function($query) use ($where) {
                $active_where = [ [ 'active_goods_status', 'in', [ ActiveDict::ACTIVE, ActiveDict::NOT_ACTIVE ] ], [ 'active_class', '=', ActiveDict::DISCOUNT ] ];
                if ($where[ 'active_id' ]) $active_where[] = [ 'active_id', '=', $where[ 'active_id' ] ];
                $query->where($active_where);
            } ])
            ->where($sku_where)->order($order)->append([ 'goods_type_name', 'goods_cover_thumb_small', 'goods_cover_thumb_mid', 'goods_label_name', 'activeGoods.active_goods_status_name' ]);
        $list = $this->pageQuery($search_model);

        foreach ($list[ 'data' ] as $key => $item) {
            $active_goods_value = json_decode($item[ 'activeGoods' ][ 'active_goods_value' ], true);
            $active_goods_value = array_column($active_goods_value, null, 'sku_id');
            $sku_list = $item[ 'skuList' ] ?? [];
            $sku_list = array_column($sku_list, null, 'sku_id');
            foreach ($active_goods_value as $k => $v) {
                if (!empty($v[ 'is_enabled' ])) {
                    $goods_sku = $sku_list[ $v[ 'sku_id' ] ] ?? [];
                    $discount_price = $v[ 'discount_price' ] ?? $goods_sku[ 'price' ];

                    if (( !empty($item[ 'goodsSku' ]) && $item[ 'goodsSku' ][ 'active_discount_price' ] > $discount_price ) || empty($item[ 'goodsSku' ])) {
                        $item[ 'goodsSku' ] = $goods_sku;
                        $item[ 'goodsSku' ][ 'active_discount_type' ] = $v[ 'discount_type' ] ?? '';
                        $item[ 'goodsSku' ][ 'active_discount_rate' ] = $v[ 'discount_rate' ] ?? '10';
                        $item[ 'goodsSku' ][ 'active_specify_price' ] = $v[ 'specify_price' ] ?? 0;
                        $item[ 'goodsSku' ][ 'active_discount_price' ] = $discount_price;
                        $item[ 'goodsSku' ][ 'active_reduce_money' ] = $v[ 'reduce_money' ] ?? 0;
                    }
                }
            }
            if (empty($item[ 'goodsSku' ])) {
                $item[ 'goodsSku' ] = $item[ 'skuList' ][ 0 ] ?? [];

                $item[ 'goodsSku' ][ 'active_discount_type' ] = 'discount';
                $item[ 'goodsSku' ][ 'active_discount_rate' ] = '10';
                $item[ 'goodsSku' ][ 'active_specify_price' ] = $item[ 'goodsSku' ][ 'price' ] ?? '0';
                $item[ 'goodsSku' ][ 'active_discount_price' ] = $item[ 'goodsSku' ][ 'price' ] ?? '0';
                $item[ 'goodsSku' ][ 'active_reduce_money' ] = 0;

            }
            $list[ 'data' ][ $key ] = $item;
        }

        return $list;
    }

    public function getList(array $where)
    {
        $field = 'active_id,site_id,active_name,active_desc,active_type,active_goods_type,active_goods_info,active_class,active_class_category,relate_member,active_value,start_time,end_time,active_status,create_time,update_time,active_order_money,active_order_num,active_member_num,active_success_num';

        $order = Db::raw('FIELD(active_status, "' . ActiveDict::ACTIVE . '","' . ActiveDict::NOT_ACTIVE . '"), start_time asc');

        $list = $this->model->where([
            [ 'site_id', '=', $this->site_id ],
            [ 'active_class', '=', ActiveDict::DISCOUNT ],
            [ 'active_status', 'in', [ ActiveDict::ACTIVE, ActiveDict::NOT_ACTIVE ] ],
        ])->withSearch([ "active_name" ], $where)->append([ 'active_type_name', 'active_goods_type_name', 'active_status_name' ])->field($field)->limit($where[ 'limit' ])->order($order)->select()->toArray();

        return $list;

    }

}
