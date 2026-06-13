<?php

namespace addon\hsx_recycle;

use addon\hsx_recycle\app\service\core\adminapp\AdminAppCompatCleanupService;
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
        $this->repairAddonSchedule();
        return true;
    }

    /**
     * 插件卸载执行
     */
    public function uninstall()
    {
        (new AdminAppCompatCleanupService())->cleanup();
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
        $this->repairAddonSchedule();
        return true;
    }

    /**
     * 自修复本插件的计划任务时间配置。
     *
     * 历史安装中曾出现 time JSON 损坏(误用 minute 且缺 day),type=day 会被拼成
     * 非法 cron(间隔位变成星号斜杠星号),导致 workerman 调度进程整体崩溃重启。
     * 这里以插件 dict(schedule.php)中的定义为准,仅修正“会生成非法 cron”的行,
     * 合法配置(含管理员自定义)保持不动。只读写本插件自己的 sys_schedule 行,不触碰框架。
     */
    protected function repairAddonSchedule()
    {
        try {
            $templates = array_column(
                (new \app\service\core\schedule\CoreScheduleService())->getTemplateList('hsx_recycle'),
                'time',
                'key'
            );
            $rows = Db::name('sys_schedule')
                ->where('addon', 'hsx_recycle')
                ->field('id,key,time')
                ->select()
                ->toArray();
            foreach ($rows as $row) {
                $time = is_array($row['time']) ? $row['time'] : json_decode((string)$row['time'], true);
                if ($this->isValidScheduleTime($time)) {
                    continue; // 合法配置保留,不覆盖管理员自定义
                }
                $fallback = $templates[$row['key']] ?? null;
                if (empty($fallback)) {
                    continue;
                }
                Db::name('sys_schedule')
                    ->where('id', (int)$row['id'])
                    ->update(['time' => json_encode($fallback, JSON_UNESCAPED_UNICODE)]);
            }
        } catch (\Throwable $e) {
            // 自修复失败不应阻断安装/升级流程
        }
        return true;
    }

    /**
     * 判断计划任务 time 配置能否生成合法 cron(间隔型字段必须是正整数)。
     */
    protected function isValidScheduleTime($time): bool
    {
        if (!is_array($time) || empty($time['type'])) {
            return false;
        }
        $isPositiveInt = static function ($value): bool {
            return is_numeric($value) && (int)$value >= 1 && (string)(int)$value === (string)$value;
        };
        switch ($time['type']) {
            case 'sec':
                return $isPositiveInt($time['sec'] ?? null);
            case 'min':
                return $isPositiveInt($time['min'] ?? null);
            case 'hour':
                return $isPositiveInt($time['hour'] ?? null);
            case 'day':
            case 'month':
                return $isPositiveInt($time['day'] ?? null);
            case 'week':
                return isset($time['week']) && $time['week'] !== '';
            default:
                return false;
        }
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
