<?php

namespace addon\recycle;


/**
 * 插件安装之后单独的插件方法
 */
class Addon
{
    /**
     * 插件安装执行
     */
    public function install()
    {
        // 安装计划任务
        (new \app\service\core\schedule\CoreScheduleInstallService())->installAddonSchedule('recycle');
        return true;
    }

    /**
     * 插件卸载执行
     */
    public function uninstall()
    {
        // 卸载计划任务
        (new \app\service\core\schedule\CoreScheduleInstallService())->uninstallAddonSchedule('recycle');
        return true;
    }

    /**
     * 插件升级执行
     */
    public function upgrade()
    {
        return true;
    }

}
