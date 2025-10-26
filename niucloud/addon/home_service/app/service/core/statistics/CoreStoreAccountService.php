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

namespace addon\home_service\app\service\core\statistics;


use core\base\BaseCoreService;
use addon\home_service\app\model\account\StoreAccount;
use think\facade\Db;
use think\Model;

/**
 * 门店账户统计
 * Class CoreCardOrderCreateService
 */
class  CoreStoreAccountService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
        $this->model = new StoreAccount();
    }


}
