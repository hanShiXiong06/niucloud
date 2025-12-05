<?php

namespace addon\ai_image\app\listener;

use app\service\core\addon\CoreAddonService;
use app\service\core\site\CoreSiteService;

/**
 * 底部导航
 */
class BottomNavigationListener
{
    /**
     * @param array $params
     * @return array|void
     */
    public function handle($params = [])
    {
        $key = 'ai_image';
        $site_addon = (new CoreSiteService())->getAddonKeysBySiteId(request()->siteId());
        if (!in_array($key, $site_addon)) return;

        if (!empty($params) && !empty($params['key']) && $params['key'] != $key) return;

        $core_addon_service = new CoreAddonService();
        $addon_info = $core_addon_service->getAddonConfig($key);

        return [
            'key' => $key,
            'info' => $addon_info,
            'value' =>
                [
                    "backgroundColor" =>"#FFFFFF",
                    "textColor" =>"#000000",
                    "textHoverColor" =>"#000000",
                    "type" =>"1",
                    "list" =>[
                        [
                            "text" =>"首页",
                            "link" => [
                                "parent" =>"AI_IMAGE_COMMONLINK",
                                "name" =>"AI_IMAGE_DIY",
                                "title" =>"首页",
                                "url" =>"/addon/ai_image/pages/index",
                                "action" =>"decorate"
                            ],
                            "iconPath" =>"https://saasr2.sotui.top/upload/attachment/image/100000/202510/17/176068436066cdec844c362f079e8d0aaeee31b0b4_tk_s3.png",
                            "iconSelectPath" =>"https://saasr2.sotui.top/upload/attachment/image/100000/202510/17/176068436066cdec844c362f079e8d0aaeee31b0b4_tk_s3.png"
                        ],
                        [
                            "text" =>"创作",
                            "link" => [
                                "parent" =>"AI_IMAGE_COMMONLINK",
                                "name" =>"AI_IMAGE_MODEL",
                                "title" =>"智能体",
                                "url" =>"/addon/ai_image/pages/model",
                                "action" =>"decorate"
                            ],
                            "iconPath" =>"https://saasr2.sotui.top/upload/attachment/image/100000/202510/17/176068462476f4c267f45709fc8fbd1d3f6f98274d_tk_s3.png",
                            "iconSelectPath" =>"https://saasr2.sotui.top/upload/attachment/image/100000/202510/17/176068462476f4c267f45709fc8fbd1d3f6f98274d_tk_s3.png"
                        ],
                        [
                            "text" =>"我的",
                            "link" => [
                                "parent" =>"AI_IMAGE_COMMONLINK",
                                "name" =>"AI_IMAGE_DIY_MEMBER",
                                "title" =>"个人中心",
                                "url" =>"/addon/ai_image/pages/member",
                                "action" =>"decorate"
                            ],
                            "iconSelectPath" =>"https://saasr2.sotui.top/upload/attachment/image/100000/202510/17/1760684505687125950b819e8d83051c1612acee3f_tk_s3.png",
                            "iconPath" =>"https://saasr2.sotui.top/upload/attachment/image/100000/202510/17/1760684505687125950b819e8d83051c1612acee3f_tk_s3.png"
                        ]
                    ]
                ]
        ];


    }
}
