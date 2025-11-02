<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------
namespace addon\home_service\app\job\card;

use core\base\BaseJob;

/**
 * 订单创建后调用
 */
class AfterHomeServiceCardOrderCreate extends BaseJob
{
    /**
     * 消费
     * @param $data
     * @return true
     */
    public function doJob(int $site_id, int $order_id, array $order_data,int $time)
    {
        try {
            event('AfterHomeServiceCardOrderCreate', ['site_id' => $site_id, 'order_id' => $order_id, 'order_data' => $order_data, 'time' => $time]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

}
