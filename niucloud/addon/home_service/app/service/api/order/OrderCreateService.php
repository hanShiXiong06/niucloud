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

namespace addon\home_service\app\service\api\order;

use addon\home_service\app\service\core\order\CoreOrderConfigService;
use addon\home_service\app\service\core\order\CoreOrderCreateService;
use core\base\BaseApiService;


/**
 * 订单创建服务层
 * Class OrderService
 * @package app\service\api
 */
class OrderCreateService extends BaseApiService
{


    /**
     * 订单计算
     * @param array $data
     * @return array|null
     */
    public function calculate(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        return (new CoreOrderCreateService())->calculate($data);
    }


    /**
     * 订单确认
     * @param array $data
     * @return array|null
     */
    public function confirm(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        return (new CoreOrderCreateService())->confirm($data);
    }


    /**
     * 订单创建
     * @param array $data
     * @return array|null
     */
    public function create(array $data)
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $data['order_from'] = $this->channel;

        $config = ( new CoreOrderConfigService() )->getOrderConfig($this->site_id);
        $data['is_auto_refund'] = $config[ 'order_auto_refund' ][ 'is_auto_refund' ] ?? false;
        return (new CoreOrderCreateService())->create($data);
    }

    /**
     * 获取优惠券列表
     * @param array $data
     * @return void|null
     */
    public function getCoupon(array $data)
    {
        $data['member_id'] = $this->member_id;
        $data['site_id'] = $this->site_id;
        return (new CoreOrderCreateService())->getCoupon($data);
    }

}
