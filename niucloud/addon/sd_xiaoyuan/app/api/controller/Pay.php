<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use app\service\api\pay\PayService;
use core\base\BaseApiController;
use think\Response;

/**
 * 校园帮支付控制器
 * 用于处理支付相关请求，确保参数类型正确
 */
class Pay extends BaseApiController
{
    /**
     * 获取支付信息
     * @param string $trade_type
     * @param string $trade_id 路由参数为字符串，需要转换为int
     * @return Response
     */
    public function info($trade_type, $trade_id)
    {
        $data = $this->request->params([
            ['scene', '']
        ]);
        
        // 将字符串类型的trade_id转换为int类型
        return success((new PayService())->getInfoByTrade($trade_type, (int)$trade_id, $data));
    }
}
