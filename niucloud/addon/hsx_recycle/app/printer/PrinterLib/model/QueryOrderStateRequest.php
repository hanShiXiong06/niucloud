<?php

namespace Xpyun\model;

use addon\hsx_recycle\app\printer\PrinterLib\model\RestRequest;

class QueryOrderStateRequest extends RestRequest
{

    /**
     * 订单编号
     */
    var $orderId;
}

?>