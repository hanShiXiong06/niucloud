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
namespace addon\phone_shop\app\listener;

use app\service\core\site\CoreSiteService;

/**
 * 站点初始化
 */
class SiteInitListener
{
    protected $tables = [
        'phone_shop_address', // 商家地址库
        'phone_shop_cart', // 购物车表
        'phone_shop_coupon', // 优惠券表
        'phone_shop_coupon_goods', // 优惠券商品关联表
        'phone_shop_coupon_member', // 优惠券会员记录
        'phone_shop_coupon_send_records', // 优惠券发券记录
        'phone_shop_delivery_company', // 快递公司
        'phone_shop_delivery_deliver', // 配送员表
        'phone_shop_delivery_electronic_sheet', // 电子面单
        'phone_shop_delivery_local_delivery', // 同城配送配置
        'phone_shop_delivery_shipping_template', // 运费模板
        'phone_shop_delivery_shipping_template_item', // 运费模板明细
        'phone_shop_discount', // 限时折扣主表
        'phone_shop_discount_goods', // 限时折扣商品
        'phone_shop_goods', // 商品主表
        'phone_shop_goods_attr', // 商品参数
        'phone_shop_goods_brand', // 商品品牌
        'phone_shop_goods_browse', // 商品浏览历史
        'phone_shop_forward_application', // 同行商品转发权益申请
        'phone_shop_goods_category', // 商品分类
        'phone_shop_goods_collect', // 商品收藏
        'phone_shop_goods_evaluate', // 商品评价
        'phone_shop_goods_label', // 商品标签
        'phone_shop_goods_label_group', // 标签分组
        'phone_shop_goods_rank', // 商品排行榜
        'phone_shop_goods_service', // 商品服务
        'phone_shop_goods_sku', // 商品SKU
        'phone_shop_goods_spec', // 商品规格
        'phone_shop_goods_stat', // 商品统计
        'phone_shop_invoice', // 发票表
        'phone_shop_manjian', // 满减活动
        'phone_shop_manjian_goods', // 满减商品
        'phone_shop_manjian_give_records', // 满减赠送记录
        'phone_shop_newcomer_member_records', // 新人专享记录
        'phone_shop_order', // 订单主表
        'phone_shop_order_batch_delivery', // 批量发货
        'phone_shop_order_delivery', // 订单发货
        'phone_shop_order_discount', // 订单优惠
        'phone_shop_order_discount_goods', // 订单项优惠
        'phone_shop_order_goods', // 订单商品项
        'phone_shop_order_refund', // 订单退款
        'phone_shop_point_exchange', // 积分兑换
        'phone_shop_point_exchange_order', // 积分订单
        'phone_shop_stat', // 店铺统计
        'phone_shop_store', // 自提门店
        'phone_shop_active', // 营销活动
        'phone_shop_active_goods', // 活动商品
    ];

    public function handle($params = [])
    {
        if (in_array('phone_shop', $params[ 'main_app' ])) {
            $site_id = $params[ 'site_id' ];

            (new CoreSiteService())->siteInitBySiteId($site_id, $this->tables);

            event("AddSiteAfter", [ 'site_id' => $site_id, 'main_app' => [ 'phone_shop' ], 'site_addons' => [] ]);

            return true;
        }
    }
}
