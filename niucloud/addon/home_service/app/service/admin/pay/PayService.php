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

namespace addon\home_service\app\service\admin\pay;


use core\base\BaseAdminService;
use app\model\pay\Pay;


/**
 * 支付账户服务层
 * Class CouponService
 * @package addon\home_service\app\service\admin\coupon
 */
class PayService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Pay();
    }


    public function getOrderPayData()
    {


    }


}