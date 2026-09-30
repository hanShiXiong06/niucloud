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

namespace addon\phone_shop\app\listener\goods;

use addon\phone_shop\app\dict\active\ActiveDict;
use addon\phone_shop\app\service\core\goods\CoreGoodsActivePriceService;

/**
 * 获取商品展示价格
 * Class CouponCheckListener
 * @package addon\phone_shop\app\listener
 */
class GoodsShowPriceListener
{
    public function handle(array $param)
    {
        $sku_info = $param[ 'sku_info' ];
        $site_id = $param[ 'site_id' ];
        $member_id = $param[ 'member_id' ];
        return (new CoreGoodsActivePriceService())->getShowPrice($sku_info,$site_id,$member_id);
    }
}
