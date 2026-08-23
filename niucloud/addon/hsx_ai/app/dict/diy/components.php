<?php
declare(strict_types=1);

return [
    'HSX_AI_COMPONENT' => [
        'title' => 'AI 组件',
        'list' => [
            'AiAssistantEntry' => [
                'title' => 'AI 智能助手',
                'icon' => 'iconfont icongaikuang1',
                'path' => 'edit-ai-assistant-entry',
                'support_page' => [],
                'uses' => 1,
                'sort' => 10020,
                'value' => [
                    'layout' => 'card',
                    'title' => 'AI 智能助手',
                    'subtitle' => '结合当前业务知识，为客户快速解答核心问题',
                    'buttonText' => '开始咨询',
                    'showVoiceHint' => 1,
                    'voiceHint' => '支持语音咨询',
                    'panelColor' => '#FFFFFF',
                    'accentColor' => '#2563EB',
                    'titleColor' => '#172033',
                    'subtitleColor' => '#667085',
                    'buttonTextColor' => '#FFFFFF',
                ],
                'template' => [
                    'pageStartBgColor' => '',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentStartBgColor' => '',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 0,
                    'topRounded' => 8,
                    'bottomRounded' => 8,
                    'margin' => [
                        'top' => 10,
                        'bottom' => 10,
                        'both' => 12,
                    ],
                ],
            ],
        ],
    ],
];
