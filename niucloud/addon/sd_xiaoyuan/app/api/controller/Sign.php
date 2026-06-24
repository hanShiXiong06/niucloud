<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\SignService;
use core\base\BaseApiController;
use core\exception\CommonException;

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
        $data = null;
        try {
            $data = (new SignService())->sign();
        } catch (CommonException $e) {
            return fail($e->getMessage());
        } catch (\Throwable $e) {
            return fail('签到失败：' . $e->getMessage());
        }
        return success($data);
    }

    public function status()
    {
        $data = null;
        try {
            $data = (new SignService())->getSignStatus();
        } catch (\Throwable $e) {
            return fail('获取签到状态失败：' . $e->getMessage());
        }
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
