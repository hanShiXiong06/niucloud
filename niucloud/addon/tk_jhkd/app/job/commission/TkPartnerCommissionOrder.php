<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------
namespace addon\tk_jhkd\app\job\commission;

use core\base\BaseJob;

/**
 * 进行开店宝订单入库
 */
class TkPartnerCommissionOrder extends BaseJob
{
    public function doJob($data)
    {
        try {
            event('TkPartnerCommissionOrder', $data);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
