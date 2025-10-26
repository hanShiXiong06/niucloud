<?php
declare (strict_types=1);

namespace addon\home_service\app\listener\order;

use addon\home_service\app\dict\order\OrderDict;
use addon\home_service\app\service\core\order\CoreOrderCommissionService;

class SettlementOrderCommission
{

    public function handle($data)
    {
        if (isset($data['order_type']) && $data['order_type'] == OrderDict::ORDER_TYPE_ORDER) {
            return (new  CoreOrderCommissionService)->settlementOrderCommission($data);
        }
    }
}
