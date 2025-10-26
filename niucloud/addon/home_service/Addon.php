<?php

namespace addon\home_service;



/**
 * 插件安装之后单独的插件方法
 * Class home_service
 * @package addon\home_service
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
