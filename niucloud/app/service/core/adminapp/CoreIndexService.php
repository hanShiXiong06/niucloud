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

use core\base\BaseCoreService;
use core\dict\DictLoader;

/**
 * 手机管理端指标管理
 */
class CoreIndexService extends BaseCoreService
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取统计项
     * @return void
     */
    public function getStatList(){
        $loader = new DictLoader('AdminApps');
        return $loader->loadStat([]);
    }

    /**
     *  获取待办项
     * @return mixed
     */
    public function getTodoList(){
        $loader = new DictLoader('AdminApps');
        return $loader->loadTodo([]);
    }



}
