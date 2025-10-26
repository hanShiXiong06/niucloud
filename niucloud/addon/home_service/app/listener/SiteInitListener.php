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
namespace addon\home_service\app\listener;

use app\service\core\site\CoreSiteService;

/**
 * 站点初始化
 */
class SiteInitListener
{
    protected $tables = [
        'home_service_goods_category', // 商品分类
        'home_service_goods', // 项目表
        'home_service_goods_sku', // 项目规格表
        'home_service_order', // 订单表
        'home_service_order_item', // 家政订单商品表
        'home_service_order_log', // 订单操作记录表
        'home_service_order_refund', // 家政订单退款表
        'home_service_order_refund_log', // 订单维权日志表
        'home_service_stat', // 统计表
        'home_service_hour_stat', // 时统计表
        'home_service_technician', // 师傅表
        'home_service_member_collect', // 会员收藏表

        //'home_service_technician_evaluate', // 师傅评价表  (目前不考虑)
        // 'home_service_technician_goods', // 师傅项目表  (目前不考虑)

    ];

    public function handle($params = [])
    {
        if (in_array('o2o', $params['main_app'])) {
            $site_id = $params['site_id'];
            (new CoreSiteService())->siteInitBySiteId($site_id, $this->tables);
            return true;
        }
    }
}
