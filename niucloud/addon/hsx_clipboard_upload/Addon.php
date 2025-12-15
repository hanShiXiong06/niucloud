<?php

namespace addon\hsx_clipboard_upload;

use think\facade\Log;

/**
 * 剪贴板图片上传插件
 */
class Addon
{
    /**
     * 插件安装执行
     */
    public function install()
    {

        return true;
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
        return true;
    }

} 