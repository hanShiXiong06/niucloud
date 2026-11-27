<?php

namespace addon\phone_shop\app\job\goods;

use addon\phone_shop\app\service\core\goods\CoreGoodsSubscriptionMatchService;
use core\base\BaseJob;

/**
 * 异步匹配商品筛选订阅。
 */
class GoodsSubscriptionMatch extends BaseJob
{
    public function doJob($site_id, $goods_id): bool
    {
        (new CoreGoodsSubscriptionMatchService())->match((int)$site_id, (int)$goods_id);
        return true;
    }
}
