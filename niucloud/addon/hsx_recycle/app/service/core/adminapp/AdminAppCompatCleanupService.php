<?php

namespace addon\hsx_recycle\app\service\core\adminapp;

/**
 * 清理 hsx_recycle 为兼容旧移动管理端入口写入框架目录的桥接文件。
 *
 * 只删除带固定标记的文件，避免框架后续版本新增同名正式文件时被插件卸载误删。
 */
class AdminAppCompatCleanupService
{
    const BRIDGE_MARKER = 'HSX_RECYCLE_ADMINAPP_COMPAT_BRIDGE';

    public function cleanup()
    {
        foreach ($this->compatFiles() as $file) {
            $path = $this->projectPath($file);
            if ($this->isCompatBridgeFile($path)) {
                @unlink($path);
            }
        }

        foreach ($this->compatDirs() as $dir) {
            $this->removeIfEmpty($this->projectPath($dir));
        }

        return true;
    }

    protected function compatFiles()
    {
        return [
            'niucloud/core/dict/AdminApps.php',
            'niucloud/app/adminapi/controller/adminapp/site/Apps.php',
            'niucloud/app/adminapi/controller/adminapp/site/Index.php',
            'niucloud/app/adminapi/controller/adminapp/site/Attachment.php',
            'niucloud/app/service/admin/adminapp/AppsService.php',
            'niucloud/app/service/admin/adminapp/NavService.php',
            'niucloud/app/service/admin/adminapp/StatService.php',
            'niucloud/app/service/admin/adminapp/TodoService.php',
            'niucloud/app/service/core/adminapp/AdminAppDictService.php',
            'niucloud/app/service/core/adminapp/CoreAdminAppService.php',
            'niucloud/app/service/core/adminapp/CoreAppService.php',
            'niucloud/app/service/core/adminapp/CoreIndexService.php',
            'niucloud/app/model/adminapp/SysAdminapp.php',
        ];
    }

    protected function compatDirs()
    {
        return [
            'niucloud/app/adminapi/controller/adminapp/site',
            'niucloud/app/adminapi/controller/adminapp',
            'niucloud/app/service/admin/adminapp',
            'niucloud/app/service/core/adminapp',
            'niucloud/app/model/adminapp',
        ];
    }

    protected function isCompatBridgeFile($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $content = file_get_contents($path);
        if (!is_string($content)) {
            return false;
        }

        if (strpos($content, self::BRIDGE_MARKER) !== false) {
            return true;
        }

        // 兼容已经发布过、尚未写入固定标记的桥接文件。
        return strpos($content, 'addon\\hsx_recycle') !== false
            && (strpos($content, '兼容桥接') !== false || strpos($content, '兼容加载器') !== false);
    }

    protected function removeIfEmpty($path)
    {
        if (!is_dir($path)) {
            return;
        }

        $items = array_diff(scandir($path) ?: [], ['.', '..']);
        if (empty($items)) {
            @rmdir($path);
        }
    }

    protected function projectPath($relative_path)
    {
        return project_path() . str_replace('/', DIRECTORY_SEPARATOR, $relative_path);
    }
}
