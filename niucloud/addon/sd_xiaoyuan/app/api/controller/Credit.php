<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\CreditService;
use core\base\BaseApiController;
use think\Response;

/**
 * 信誉分接口
 */
class Credit extends BaseApiController
{
    /**
     * 获取我的信誉分信息
     */
    public function info(): Response
    {
        $memberId = $this->request->memberId();
        $info = (new CreditService())->getInfo($memberId);
        return success($info);
    }

    /**
     * 获取信誉分变动记录
     */
    public function logList(): Response
    {
        $memberId = $this->request->memberId();
        $data = $this->request->params([
            ['type', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new CreditService())->getLogPage($memberId, $data);
        return success($list);
    }

    /**
     * 检查是否可以操作(接单/发布)
     */
    public function checkCanOperate(): Response
    {
        try {
            $memberId = $this->request->memberId();
            (new CreditService())->checkCanOperate($memberId);
            return success(['can_operate' => true]);
        } catch (\Exception $e) {
            return success(['can_operate' => false, 'message' => $e->getMessage()]);
        }
    }
}
