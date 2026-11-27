<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\api\controller\recycle_order;

use addon\hsx_recycle\app\service\api\order\OrderSubmitConfigService;
use core\base\BaseApiController;

/**
 * 用户端回收下单配置
 */
class OrderSubmitConfig extends BaseApiController
{
    public function info()
    {
        return success((new OrderSubmitConfigService())->getConfig());
    }
}
