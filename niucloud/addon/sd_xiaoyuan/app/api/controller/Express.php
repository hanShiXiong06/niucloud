<?php
namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\api\ExpressService;
use core\base\BaseApiController;

class Express extends BaseApiController
{
    public function stations()
    {
        $school_id = $this->request->param('school_id', 0);
        return success((new ExpressService())->getStations($school_id));
    }
    
    public function packagePrices()
    {
        return success((new ExpressService())->getPackagePrices());
    }
}
