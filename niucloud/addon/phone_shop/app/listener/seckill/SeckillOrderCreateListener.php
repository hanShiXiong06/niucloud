<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\listener\seckill;

use addon\phone_shop\app\service\admin\goods\OfferGoodsService;

/**
 * 秒杀商品验证
 * Class TreasureTypeListener
 * @package addon\phone_shop\app\listener\treasure
 */
class SeckillOrderCreateListener
{

    public function handle($params)
    {
        if(!empty($params['key']) && $params['key'] == 'phone_shop'){
            return (new OfferGoodsService())->checkGoods($params);
        }
    }
}