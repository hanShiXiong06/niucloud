<?php
declare (strict_types=1);

namespace addon\home_service\app\listener\order;

use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\service\core\order\CoreOrderCommissionService;

class ComputeOrderItemCommission
{

    public function handle($data)
    {
        if (isset($data['order_type']) && $data['order_type'] == OrderDict::ORDER_TYPE_ITEM) {
            return (new  CoreOrderCommissionService)->computeOrderItemCommission($data);
        }
    }
}
