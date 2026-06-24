<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\api\PayService as XiaoyuanPayService;
use app\service\api\pay\PayService;
use core\base\BaseApiController;
use think\Response;

/**
 * 支付接口（校园帮类型走专用服务，其它类型走框架）
 */
class Pay extends BaseApiController
{
    /**
     * 获取支付信息
     */
    public function info($trade_type, $trade_id)
    {
        $data = $this->request->params([
            ['scene', ''],
        ]);
        $trade_id = (int)$trade_id;
        $trade_type = (string)$trade_type;

        if (strpos($trade_type, 'sd_xiaoyuan_') === 0) {
            trace('sd_xiaoyuan pay/info: type=' . $trade_type . ' id=' . $trade_id, 'info');
            return success((new XiaoyuanPayService())->getInfoByTrade($trade_type, $trade_id, $data));
        }

        return success((new PayService())->getInfoByTrade($trade_type, $trade_id, $data));
    }

    /**
     * 去支付
     */
    public function pay()
    {
        $data = $this->request->params([
            ['type', ''],
            ['trade_type', ''],
            ['trade_id', ''],
            ['quit_url', ''],
            ['buyer_id', ''],
            ['return_url', ''],
            ['voucher', ''],
            ['openid', ''],
        ]);
        $trade_type = (string)($data['trade_type'] ?? '');
        $trade_id = (int)($data['trade_id'] ?? 0);

        if (strpos($trade_type, 'sd_xiaoyuan_') === 0) {
            trace('sd_xiaoyuan pay提交: type=' . $trade_type . ' id=' . $trade_id, 'info');
            return success('SUCCESS', (new XiaoyuanPayService())->pay(
                (string)$data['type'],
                $trade_type,
                $trade_id,
                (string)($data['return_url'] ?? ''),
                (string)($data['quit_url'] ?? ''),
                (string)($data['buyer_id'] ?? ''),
                (string)($data['voucher'] ?? ''),
                (string)($data['openid'] ?? '')
            ));
        }

        return success('SUCCESS', (new PayService())->pay(
            (string)$data['type'],
            $trade_type,
            $trade_id,
            (string)($data['return_url'] ?? ''),
            (string)($data['quit_url'] ?? ''),
            (string)($data['buyer_id'] ?? ''),
            (string)($data['voucher'] ?? ''),
            (string)($data['openid'] ?? '')
        ));
    }
}
