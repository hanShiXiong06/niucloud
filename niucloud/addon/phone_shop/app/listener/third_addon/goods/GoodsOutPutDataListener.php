<?php

namespace addon\phone_shop\app\listener\third_addon\goods;


use addon\phone_shop\app\service\admin\goods\OfferGoodsService;

/**
 * 三方应用获取商城商品列表
 */
class GoodsOutPutDataListener
{

    public function handle($params)
    {
        if (!empty($params['key']) && $params['key'] == 'phone_shop') {
            return (new OfferGoodsService())->getOfferGoodsList($params);
        }
    }

}