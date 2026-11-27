<?php

namespace addon\phone_shop\app\api\controller\goods;

use addon\phone_shop\app\service\api\goods\GoodsSubscriptionService;
use core\base\BaseApiController;

/**
 * 商品筛选订阅
 */
class GoodsSubscription extends BaseApiController
{
    public function capability()
    {
        return success(data: (new \addon\phone_shop\app\service\core\goods\CoreGoodsNoticeService())->capability((int)$this->request->siteId()));
    }

    public function lists()
    {
        return success(data: (new GoodsSubscriptionService())->getPage());
    }

    public function status()
    {
        $data = $this->request->params([
            [ 'rule', [] ],
        ]);
        return success(data: (new GoodsSubscriptionService())->getStatus((array)$data['rule']));
    }

    public function add()
    {
        $data = $this->request->params([
            [ 'name', '' ],
            [ 'rule', [] ],
            [ 'authorization', [] ],
        ]);
        $subscription_id = (new GoodsSubscriptionService())->add($data);
        return success('订阅成功', [ 'subscription_id' => $subscription_id ]);
    }

    public function cancel()
    {
        $data = $this->request->params([
            [ 'subscription_id', 0 ],
            [ 'rule', [] ],
        ]);
        (new GoodsSubscriptionService())->cancel($data);
        return success('已取消订阅');
    }
}
