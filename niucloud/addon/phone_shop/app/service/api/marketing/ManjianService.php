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

namespace addon\phone_shop\app\service\api\marketing;

use addon\phone_shop\app\dict\active\ManjianDict;
use addon\phone_shop\app\dict\coupon\CouponDict;
use addon\phone_shop\app\dict\coupon\CouponMemberDict;
use addon\phone_shop\app\model\coupon\Coupon;
use addon\phone_shop\app\model\coupon\CouponMember;
use addon\phone_shop\app\model\goods\GoodsSku;
use addon\phone_shop\app\model\manjian\Manjian;
use addon\phone_shop\app\model\manjian\ManjianGoods;
use addon\phone_shop\app\service\core\marketing\CoreManjianService;
use core\base\BaseApiService;

/**
 * 满减送服务层
 * Class ManjianService
 * @package addon\phone_shop\app\service\api\marketing
 */
class ManjianService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Manjian();
    }

    /**
     * 获取满减信息
     * @return array
     */
    public function getManjianInfo($data)
    {
        $data[ 'site_id' ]   = $this->site_id;
        $data[ 'member_id' ] = $this->member_id;
        return ( new CoreManjianService() )->getManjianInfo($data);
    }

}
