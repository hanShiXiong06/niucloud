<?php

namespace addon\ai_image;


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
        $basedir = root_path() . 'addon' . DIRECTORY_SEPARATOR . 'ai_image' . DIRECTORY_SEPARATOR . 'movefile' . DIRECTORY_SEPARATOR . 'web';
        $rootpath = dirname(root_path()) . DIRECTORY_SEPARATOR . 'web' ;
        if (file_exists($basedir)) {
            dir_copy($basedir, $rootpath);
        }
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
