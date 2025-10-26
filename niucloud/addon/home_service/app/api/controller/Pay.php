<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\home_service\app\api\controller;

use addon\home_service\app\service\api\RefundService;
use app\dict\pay\OnlinePayDict;
use core\base\BaseApiController;
use app\service\core\pay\CorePayService;

/**
 * 支付测试
 * Class GoodsController
 * @package app\adminapi\controller
 */
class Pay extends BaseApiController
{


    public function notify()
    {
        $data = $this->request->params([
            ['out_trade_no', 0],
            ['site_id', 0],
            ['type', 0],
        ]);

        $temp_params = [
            'trade_no' => time(),
            'mch_id' => time(),
            'status' => OnlinePayDict::getWechatPayStatus('SUCCESS')
        ];
        $rrs = (new  CorePayService)->payNotify($data['site_id'],$data['out_trade_no'],$data['type'],$temp_params);
        dd($rrs);

    }

}