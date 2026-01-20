<?php

namespace addon\tk_jhkd\app\service\core;

use core\base\BaseApiService;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\Exception;
use addon\tk_jhkd\app\model\TkjhkdBrand;
use think\facade\Cache;
use think\facade\Db;
use think\Log;

/**
 * 物流查询接口,预留其他平台推送轨迹的情况
 */
class TranceService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 轨迹查询
     */
    public function deliveryTrance($params)
    {
        return (new CoreDeliveryService())->deliveryTrance($params)??[];
    }


}