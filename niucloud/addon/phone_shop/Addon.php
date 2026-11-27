<?php

namespace addon\phone_shop;

use addon\phone_shop\app\service\core\upgrade\SchemaSyncService;
use app\service\core\schedule\CoreScheduleInstallService;
use think\facade\Log;

/**
 * 插件安装之后单独的插件方法
 */
class Addon
{
    /**
     * 插件安装执行
     * 注意：框架先跑 install.sql(已改为 CREATE TABLE IF NOT EXISTS，不会清老数据)，再调本方法。
     *      这里做幂等表结构同步：给已存在的老表补缺列、回填 attr_ids。全新装则为空操作。
     */
    public function install()
    {
        try {
            $report = (new SchemaSyncService())->run();
            Log::write('[phone_shop] install schema sync: ' . json_encode($report, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::write('[phone_shop] install schema sync 异常: ' . $e->getMessage());
        }
    }

    /**
     * 插件卸载执行
     */
    public function uninstall()
    {
        return true;
    }

    /**
     * 插件升级执行
     */
    public function upgrade()
    {
        (new CoreScheduleInstallService())->installAddonSchedule('phone_shop');
        try {
            $report = (new SchemaSyncService())->run();
            Log::write('[phone_shop] upgrade schema sync: ' . json_encode($report, JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::write('[phone_shop] upgrade schema sync 异常: ' . $e->getMessage());
        }
        return true;
    }
}
