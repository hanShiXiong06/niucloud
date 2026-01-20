<?php

namespace addon\tk_jhkd\app\service\core;

use core\base\BaseApiService;
use core\exception\CommonException;
use think\Exception;
use think\facade\Log;

/**
 * 易达回掉接口对接
 */
class YidaNoticeService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->changeService = new ChangeNoticeService();
    }


    /**
     * 回调处理
     */
    public function notice($data)
    {
        Log::write('-----易达回调处理-----' . date('Y-m-d H:i:s', time()));
        Log::write($data);
        try {
            $type = $data['pushType'];
            //状态推送
            if ($type == 1) {
                $this->changeService->changeOrderStatus($data);
            }
            //重量推送
            if ($type == 2) {
                return $this->changeService->changeWeight($data);
            }
            //揽收推送
            if ($type == 3) {
                return $this->changeService->doPick($data);
            }
            //订单变更
            if ($type == 5) {
                return $this->changeService->changeOrder($data);
            }
        } catch (Exception $e) {
            throw new CommonException($e->getMessage());
        }
    }

}