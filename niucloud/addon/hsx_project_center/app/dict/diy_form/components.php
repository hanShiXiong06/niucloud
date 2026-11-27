<?php

$decodeLocation = static function ($data): array {
    if (is_array($data)) {
        $location = $data;
    } elseif (is_string($data) && trim($data) !== '') {
        $location = json_decode($data, true);
        $location = is_array($location) ? $location : [];
    } else {
        $location = [];
    }

    $latitude = $location['latitude'] ?? null;
    $longitude = $location['longitude'] ?? null;
    if (!is_numeric($latitude) || !is_numeric($longitude)) {
        return [];
    }

    $source = (string) ($location['source'] ?? '');
    if (!in_array($source, ['wechat_js_sdk', 'native_gps', 'manual_map'], true)) {
        $source = '';
    }

    $fullAddress = trim((string) ($location['full_address'] ?? $location['address'] ?? ''));

    return [
        'latitude' => (float) $latitude,
        'longitude' => (float) $longitude,
        'full_address' => $fullAddress,
        'name' => trim((string) ($location['name'] ?? '')),
        'province' => trim((string) ($location['province'] ?? '')),
        'city' => trim((string) ($location['city'] ?? '')),
        'district' => trim((string) ($location['district'] ?? '')),
        'community' => trim((string) ($location['community'] ?? '')),
        'accuracy' => is_numeric($location['accuracy'] ?? null) ? round((float) $location['accuracy'], 2) : null,
        'coordinate_type' => (string) ($location['coordinate_type'] ?? 'gcj02'),
        'source' => $source,
        'captured_at' => (int) ($location['captured_at'] ?? 0),
    ];
};

return [
    'PROJECT_CENTER_FORM_COMPONENT' => [
        'title' => '项目中心',
        'list' => [
            'ProjectFormLocation' => [
                'title' => '精准定位',
                'icon' => 'iconfont iconbiaotipc',
                'path' => 'edit-project-form-location',
                'uses' => 1,
                'support' => ['DIY_FORM'],
                'sort' => 10901,
                'position' => '',
                'template' => [
                    'textColor' => '#303133',
                    'pageStartBgColor' => '#FFFFFF',
                    'pageEndBgColor' => '',
                    'pageGradientAngle' => 'to bottom',
                    'componentBgUrl' => '',
                    'componentBgAlpha' => 2,
                    'componentStartBgColor' => '#FFFFFF',
                    'componentEndBgColor' => '',
                    'componentGradientAngle' => 'to bottom',
                    'topRounded' => 0,
                    'bottomRounded' => 0,
                    'elementBgColor' => '#F7F8FA',
                    'topElementRounded' => 8,
                    'bottomElementRounded' => 8,
                    'margin' => [
                        'top' => 8,
                        'bottom' => 8,
                        'both' => 10,
                    ],
                ],
                'value' => [
                    'field' => [
                        'name' => '门店定位',
                        'remark' => [
                            'text' => '请在门店现场定位，或在地图中选择准确位置',
                            'color' => '#999999',
                            'fontSize' => 14,
                        ],
                        'required' => true,
                        'unique' => false,
                        'autofill' => false,
                        'privacyProtection' => false,
                        'cache' => true,
                        'detailComponent' => '/src/addon/hsx_project_center/views/diy_form/components/detail-project-form-location.vue',
                        'default' => [],
                        'value' => [],
                    ],
                    'placeholder' => '请选择门店位置',
                    'fontSize' => 14,
                    'fontWeight' => 'normal',
                    'mode' => 'both',
                    'requireAddress' => true,
                    'maxAccuracyMeters' => 0,
                    'maxAgeMinutes' => 0,
                    'showCoordinates' => true,
                ],
                'render' => static function ($data) use ($decodeLocation): string {
                    $location = $decodeLocation($data);
                    if (empty($location)) {
                        return '';
                    }

                    if ($location['full_address'] !== '') {
                        return $location['full_address'];
                    }
                    if ($location['name'] !== '') {
                        return $location['name'];
                    }
                    return $location['latitude'] . ',' . $location['longitude'];
                },
                'convert' => static function ($data) use ($decodeLocation): array {
                    return $decodeLocation($data);
                },
            ],
        ],
    ],
];
