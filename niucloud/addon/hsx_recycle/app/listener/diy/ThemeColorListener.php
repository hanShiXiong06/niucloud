<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\hsx_recycle\app\listener\diy;

/**
 * 主题色
 * Class ThemeColorListener
 * @package addon\hsx_recycle\app\listener\diy
 */
class ThemeColorListener
{

    public function handle($params)
    {
        if (!empty($params[ 'key' ]) && $params[ 'key' ] == 'hsx_recycle') {
            return [
                'theme_color' => [
                    [
                        'title' => '环保绿',
                        'name' => 'eco_green',
                        'theme' => [
                            '--page-bg-color' => '#F6F6F6',
                            '--primary-color' => '#44B464',
                            '--primary-color-light' => '#EAFBEF',
                            '--primary-color-light2' => '#F7FFF9',
                            '--primary-help-color' => '#67C083',
                            '--primary-help-color2' => '#67C083',
                            '--primary-color-dark' => '#389652',
                            '--primary-color-disabled' => '#A9E0B8',
                            '--price-text-color' => '#44B464',
                        ],
                    ],
                    [
                        'title' => '科技蓝',
                        'name' => 'tech_blue',
                        'theme' => [
                            '--page-bg-color' => '#F5F7FB',
                            '--primary-color' => '#2563EB',
                            '--primary-color-light' => '#DBEAFE',
                            '--primary-color-light2' => '#EFF6FF',
                            '--primary-help-color' => '#60A5FA',
                            '--primary-help-color2' => '#93C5FD',
                            '--primary-color-dark' => '#1D4ED8',
                            '--primary-color-disabled' => '#BFDBFE',
                            '--price-text-color' => '#1D4ED8',
                        ],
                    ],
                    [
                        'title' => '商务黑金',
                        'name' => 'business_gold',
                        'theme' => [
                            '--page-bg-color' => '#F7F6F2',
                            '--primary-color' => '#111827',
                            '--primary-color-light' => '#E5E7EB',
                            '--primary-color-light2' => '#F9FAFB',
                            '--primary-help-color' => '#B45309',
                            '--primary-help-color2' => '#D97706',
                            '--primary-color-dark' => '#030712',
                            '--primary-color-disabled' => '#D1D5DB',
                            '--price-text-color' => '#B45309',
                        ],
                    ],
                    [
                        'title' => '运营橙',
                        'name' => 'operation_orange',
                        'theme' => [
                            '--page-bg-color' => '#FFF7ED',
                            '--primary-color' => '#EA580C',
                            '--primary-color-light' => '#FED7AA',
                            '--primary-color-light2' => '#FFEDD5',
                            '--primary-help-color' => '#F97316',
                            '--primary-help-color2' => '#FDBA74',
                            '--primary-color-dark' => '#C2410C',
                            '--primary-color-disabled' => '#FDBA74',
                            '--price-text-color' => '#EA580C',
                        ],
                    ],
                    [
                        'title' => '质检紫',
                        'name' => 'inspection_purple',
                        'theme' => [
                            '--page-bg-color' => '#F7F3FF',
                            '--primary-color' => '#7C3AED',
                            '--primary-color-light' => '#DDD6FE',
                            '--primary-color-light2' => '#F3E8FF',
                            '--primary-help-color' => '#A78BFA',
                            '--primary-help-color2' => '#C4B5FD',
                            '--primary-color-dark' => '#6D28D9',
                            '--primary-color-disabled' => '#DDD6FE',
                            '--price-text-color' => '#7C3AED',
                        ],
                    ],
                    [
                        'title' => '极简灰',
                        'name' => 'minimal_gray',
                        'theme' => [
                            '--page-bg-color' => '#F6F7F9',
                            '--primary-color' => '#475569',
                            '--primary-color-light' => '#E2E8F0',
                            '--primary-color-light2' => '#F8FAFC',
                            '--primary-help-color' => '#64748B',
                            '--primary-help-color2' => '#94A3B8',
                            '--primary-color-dark' => '#334155',
                            '--primary-color-disabled' => '#CBD5E1',
                            '--price-text-color' => '#0F766E',
                        ],
                    ],
                ],
                'theme_field' => [
                    [
                        'title' => '页面背景色',
                        'label' => '--page-bg-color',
                        'value' => '#F6F6F6',
                        'tip' => '页面背景色在uniapp中使用：var(--page-bg-color)',
                    ],
                    [
                        'title' => '主色调',
                        'label' => '--primary-color',
                        'value' => '#44B464',
                        'tip' => '主色调在uniapp中使用：var(--primary-color)',
                    ],
                    [
                        'title' => '主色调浅色',
                        'label' => '--primary-color-light',
                        'value' => '#EAFBEF',
                        'tip' => '主色调浅色在uniapp中使用：var(--primary-color-light)',
                    ],
                    [
                        'title' => '主色调淡色',
                        'label' => '--primary-color-light2',
                        'value' => '#F7FFF9',
                        'tip' => '主色调淡色在uniapp中使用：var(--primary-color-light2)',
                    ],
                    [
                        'title' => '辅色调',
                        'label' => '--primary-help-color',
                        'value' => '#67C083',
                        'tip' => '辅色调在uniapp中使用：var(--primary-help-color)',
                    ],
                    [
                        'title' => '辅色调2',
                        'label' => '--primary-help-color2',
                        'value' => '#67C083',
                        'tip' => '辅色调2在uniapp中使用：var(--primary-help-color2)',
                    ],
                    [
                        'title' => '深色调',
                        'label' => '--primary-color-dark',
                        'value' => '#389652',
                        'tip' => '深色调在uniapp中使用：var(--primary-color-dark)',
                    ],
                    [
                        'title' => '禁用色',
                        'label' => '--primary-color-disabled',
                        'value' => '#A9E0B8',
                        'tip' => '禁用色在uniapp中使用：var(--primary-color-disabled)',
                    ],
                    [
                        'title' => '价格颜色',
                        'label' => '--price-text-color',
                        'value' => '#44B464',
                        'tip' => '价格颜色在uniapp中使用：var(--price-text-color)',
                    ],
                ],
            ];
        }
    }
} 
