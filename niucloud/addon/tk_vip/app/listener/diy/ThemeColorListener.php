<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_vip\app\listener\diy;

/**
 * 主题色
 */
class ThemeColorListener
{

    public function handle($params)
    {
        if (!empty($params['key']) && $params['key'] == 'tk_vip') {
            return [
                // 应用主题色
                'theme_color' => [
                    [
                        'title' => '经典蓝',
                        'name' => 'blue',
                        'theme' => [
                            "--primary-help-color" => "rgba(156, 251, 235, 1)",
                            "--price-text-color" => "#333333",
                            "--page-bg-color" => "#F6F6F6",
                            "--primary-color" => "#007aff",
                            "--primary-color-light" => "#ecf5ff",
                            "--primary-color-light2" => "#FFF4ED",
                            "--primary-help-color2" => "#007aff",
                            "--primary-color-dark" => "#999999",
                            "--primary-color-disabled" => "#CCCCCC"
                        ],
                    ],
                ],
                // 主题颜色字段，前端展示用，字段中的value值颜色为添加自定义颜色的默认值，默认黑色风格
                'theme_field' => [
                    [
                        'title' => '页面背景色',
                        'label' => "--page-bg-color",
                        'value' => "rgba(17, 11, 11, 1)",
                        'tip' => "页面背景色在uniapp中使用：var(--page-bg-color)",
                    ],
                    [
                        'title' => '主色调',
                        'label' => "--primary-color",
                        'value' => "rgba(25, 198, 80, 1)",
                        'tip' => "主色调在uniapp中使用：var(--primary-color)",
                    ],
                    [
                        'title' => '主色调浅色（淡）',
                        'label' => "--primary-color-light",
                        'value' => "rgba(255, 255, 255, 1)",
                        'tip' => "主色调浅色（淡）在uniapp中使用：var(--primary-color-light)",
                    ],
                    [
                        'title' => '主色调深色（深）',
                        'label' => "--primary-color-light2",
                        'value' => "rgba(55, 55, 55, 1)",
                        'tip' => "主色调深色（深）在uniapp中使用：var(--primary-color-light2)",
                    ],
                    [
                        'title' => '辅色调2',
                        'label' => "--primary-help-color2",
                        'value' => "rgba(156, 251, 235, 1)",
                        'tip' => "辅色调2在uniapp中使用：var(--primary-help-color2)",
                    ],
                    [
                        'title' => '灰色调',
                        'label' => "--primary-color-dark",
                        'value' => "rgba(204, 204, 204, 1)",
                        'tip' => "灰色调在uniapp中使用：var(--primary-color-dark)",
                    ],
                    [
                        'title' => '禁用色',
                        'label' => "--primary-color-disabled",
                        'value' => "#eeeeee",
                        'tip' => "禁用色在uniapp中使用：var(--primary-color-disabled)",
                    ],
                ]
            ];
        }
    }
}