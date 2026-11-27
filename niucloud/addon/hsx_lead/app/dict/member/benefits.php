<?php
declare(strict_types=1);

return [
    'hsx_lead_subject' => [
        'key' => 'hsx_lead_subject', 'name' => '商户协作主体会员', 'desc' => '供门店主体购买、续费和共享的协作权益，不修改个人会员等级',
        'component' => '/src/addon/hsx_lead/views/member/components/BenefitsSubject.vue',
        'content' => [
            'admin' => static function ($site, array $config): string {
                $limit = (int) ($config['account_limit'] ?? 1);
                $text = !empty($config['unlimited']) ? '不限账号' : $limit.'个账号（含负责人）';
                return '门店协作权益 · '.$text;
            },
            'member_level' => static fn ($site, array $config): array => [
                'title' => '商户协作主体会员', 'desc' => '门店开通后按套餐共享协作权益，非个人等级自动授予',
                'icon' => '/static/resource/images/member/benefits/benefits_badge.png',
            ],
        ],
    ],
];
