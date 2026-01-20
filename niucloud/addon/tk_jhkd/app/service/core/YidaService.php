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
 * 易达接口对接
 * Class ConfigService
 * @package addon\tk_jhkd\service\core\yida
 */
class YidaService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->changeService = new ChangeNoticeService();
    }
    /**
     * 账户余额查询
     */
    public function getBalance()
    {
        return  (new CoreDeliveryService())->getBalance();
    }

    /**
     * 获取配置信息
     * @param $site_id
     * @param string $config_key
     */
    public function getConfig()
    {
        return (new CoreConfigService())->getConfig($this->site_id, 'tk_jhkd_config');
    }


    /**
     * 回调处理
     */
    public function notice($data)
    {
        try {
            $type = $data['pushType'];
            //状态推送
            if ($type == 1) {
                $this->changeService->changeOrderStatus($data);
            }
            //重量推送
            if ($type == 2) {
                $this->changeService->changeWeight($data);
            }
            //揽收推送
            if ($type == 3) {
                $this->changeService->doPick($data);
            }
            //订单变更
            if ($type == 5) {
                $this->changeService->changeOrder($data);
            }
            return true;
        } catch (Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

}