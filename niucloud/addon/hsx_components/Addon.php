<?php

namespace addon\hsx_components;

/**
 * HSX 框架组件库。
 * 前端目录和菜单由 NiuCloud 的应用安装流程统一同步，本类不侵入框架核心。
 */
class Addon
{
    public function install(): bool
    {
        return true;
    }

    public function uninstall(): bool
    {
        return true;
    }

    public function upgrade(): bool
    {
        return true;
    }
}
