<?php
declare(strict_types=1);

return [
    'hsx_project_distribution' => [
        'key' => 'hsx_project_distribution',
        'name' => '项目推广分佣',
        'desc' => '控制当前会员等级是否可以推广项目，以及一级、二级佣金系数',
        'component' => '/src/addon/hsx_project_center/views/member/components/benefits-project-distribution.vue',
        'content' => [
            'admin' => static function (int $siteId, array $config): string {
                if (empty($config['is_use'])) return '未开放项目推广分佣';
                $first = number_format((float)($config['first_coefficient'] ?? 100), 0);
                $second = !empty($config['second_enabled'])
                    ? '，二级系数 ' . number_format((float)($config['second_coefficient'] ?? 100), 0) . '%'
                    : '，不参与二级分佣';
                return '可推广项目；一级系数 ' . $first . '%' . $second;
            },
            'member_level' => static function (int $siteId, array $config): array {
                return [
                    'title' => '项目推广分佣',
                    'desc' => !empty($config['is_use']) ? '分享项目可按规则获得佣金' : '当前等级未开放',
                    'icon' => '/static/resource/images/member/benefits/benefits_pinkage.png',
                ];
            },
        ],
    ],
];
