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

namespace addon\home_service\app\service\admin\account;

use addon\home_service\app\service\core\account\CoreStoreAccountService;
use addon\home_service\app\model\account\StoreAccount;
use core\base\BaseAdminService;


/**
 * 师傅账户服务层
 * Class CouponService
 * @package addon\home_service\app\service\admin\coupon
 */
class StoreAccountService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreAccount();
    }


    public function getPage(array $where)
    {
        $where['site_id'] = $this->site_id;
        return (new  CoreStoreAccountService)->getPage($where);
    }


}