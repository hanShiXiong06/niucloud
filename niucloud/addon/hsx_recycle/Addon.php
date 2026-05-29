<?php

namespace addon\hsx_recycle;

use think\facade\Db;

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
        $this->installSystemColumns();
        $this->syncAdminappResources();
        // 安装计划任务
        (new \app\service\core\schedule\CoreScheduleInstallService())->installAddonSchedule('hsx_recycle');
        return true;
    }

    /**
     * 插件卸载执行
     */
    public function uninstall()
    {
        // 卸载计划任务
        (new \app\service\core\schedule\CoreScheduleInstallService())->uninstallAddonSchedule('hsx_recycle');
        return true;
    }

    /**
     * 插件升级执行
     */
    public function upgrade()
    {
        $this->installSystemColumns();
        $this->syncAdminappResources();
        return true;
    }

    /**
     * 补齐移动管理端依赖的系统扩展字段。
     * 这里做字段存在检查，避免插件升级重复执行 SQL 时中断。
     */
    protected function installSystemColumns()
    {
        $prefix = config('database.connections.mysql.prefix');
        $table = $prefix . 'sys_menu';

        if (!$this->hasColumn($table, 'support_addon')) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `support_addon` varchar(255) NOT NULL DEFAULT '' COMMENT '支持插件标识JSON，空表示不限制插件' AFTER `parent_select_key`");
        }

        if (!$this->hasColumn($table, 'mount_key')) {
            Db::execute("ALTER TABLE `{$table}` ADD COLUMN `mount_key` varchar(255) NOT NULL DEFAULT '' COMMENT '菜单挂载目标key，多个用JSON数组存储' AFTER `support_addon`");
        }
    }

    protected function hasColumn(string $table, string $column): bool
    {
        $result = Db::query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return !empty($result);
    }

    /**
     * 移动管理端资源按 hsx_recycle 前端路径访问，安装/升级时从插件内同步过去。
     */
    protected function syncAdminappResources()
    {
        $from_dir = __DIR__ . DIRECTORY_SEPARATOR . 'resource' . DIRECTORY_SEPARATOR . 'site-tabbar' . DIRECTORY_SEPARATOR;
        $to_dir = public_path() . 'addon' . DIRECTORY_SEPARATOR . 'hsx_recycle' . DIRECTORY_SEPARATOR . 'site-tabbar' . DIRECTORY_SEPARATOR;

        if (!is_dir($from_dir)) {
            return true;
        }

        if (!is_dir($to_dir)) {
            mkdir($to_dir, 0755, true);
        }

        foreach (glob($from_dir . '*') ?: [] as $file) {
            if (is_file($file)) {
                copy($file, $to_dir . basename($file));
            }
        }

        return true;
    }

}
