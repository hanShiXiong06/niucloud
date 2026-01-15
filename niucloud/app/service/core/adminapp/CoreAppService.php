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

namespace app\service\core\adminapp;

use addon\mall\app\model\order\Order;
use app\dict\trade\SettlementDict;
use app\service\core\finance\CoreFinanceCalculateService;
use app\service\core\finance\CorePlatService;
use app\service\core\finance\CoreSiteService;
use app\service\core\sys\CoreStatService;
use core\base\BaseCoreService;
use core\dict\DictLoader;

/**
 * 手机管理端应用
 */
class CoreAppService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取所有手机管理端菜单应用分组
     * @return array|null
     */
    public function getMobileAppGroup(){
        $loader = new DictLoader('AdminApps');
        return $loader->load([]);
    }

    /**
     *  获取手机管理端应用
     * @return mixed
     */
    public function getMobileApp(){
        $loader = new DictLoader('AdminApps');
        return $loader->loadApps([]);
    }



}
