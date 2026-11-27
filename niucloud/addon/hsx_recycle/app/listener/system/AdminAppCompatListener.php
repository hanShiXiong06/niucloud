<?php

namespace addon\hsx_recycle\app\listener\system;

/**
 * adminapp 兼容别名注册。
 *
 * 在应用初始化早期把旧核心命名空间映射到插件实现，降低升级时旧控制器、
 * 旧服务、旧路由缓存命中的风险。
 */
class AdminAppCompatListener
{
    public function handle(): void
    {
        $aliases = [
            'app\\adminapi\\controller\\adminapp\\site\\Apps' => 'addon\\hsx_recycle\\app\\adminapi\\controller\\adminapp\\site\\Apps',
            'app\\adminapi\\controller\\adminapp\\site\\Index' => 'addon\\hsx_recycle\\app\\adminapi\\controller\\adminapp\\site\\Index',
            'app\\adminapi\\controller\\adminapp\\site\\Attachment' => 'addon\\hsx_recycle\\app\\adminapi\\controller\\adminapp\\site\\Attachment',

            'app\\service\\admin\\adminapp\\AppsService' => 'addon\\hsx_recycle\\app\\service\\admin\\adminapp\\AppsService',
            'app\\service\\admin\\adminapp\\NavService' => 'addon\\hsx_recycle\\app\\service\\admin\\adminapp\\NavService',
            'app\\service\\admin\\adminapp\\StatService' => 'addon\\hsx_recycle\\app\\service\\admin\\adminapp\\StatService',
            'app\\service\\admin\\adminapp\\TodoService' => 'addon\\hsx_recycle\\app\\service\\admin\\adminapp\\TodoService',

            'app\\service\\core\\adminapp\\AdminAppDictService' => 'addon\\hsx_recycle\\app\\service\\core\\adminapp\\AdminAppDictService',
            'app\\service\\core\\adminapp\\CoreAdminAppService' => 'addon\\hsx_recycle\\app\\service\\core\\adminapp\\CoreAdminAppService',
            'app\\service\\core\\adminapp\\CoreAppService' => 'addon\\hsx_recycle\\app\\service\\core\\adminapp\\CoreAppService',
            'app\\service\\core\\adminapp\\CoreIndexService' => 'addon\\hsx_recycle\\app\\service\\core\\adminapp\\CoreIndexService',

            'app\\model\\adminapp\\SysAdminapp' => 'addon\\hsx_recycle\\app\\model\\adminapp\\SysAdminapp',
        ];

        foreach ($aliases as $legacy => $target) {
            if (class_exists($legacy, false) || interface_exists($legacy, false) || trait_exists($legacy, false)) {
                continue;
            }

            if (!class_exists($target)) {
                continue;
            }

            class_alias($target, $legacy);
        }
    }
}
