<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\SignService;
use core\base\BaseApiController;

/**
 * 签到控制器
 */
class Sign extends BaseApiController
{
    /**
     * 签到
     */
    public function sign()
    {
        $service = new SignService();
        $data = $service->sign();
        return success($data);
    }

    /**
     * 获取签到状态
     */
    public function status()
    {
        $service = new SignService();
        $data = $service->getSignStatus();
        return success($data);
    }

    /**
     * 获取签到历史
     */
    public function history()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 20]
        ]);

        $service = new SignService();
        $data = $service->getSignHistory($params['page'], $params['limit']);
        return success($data);
    }
}
