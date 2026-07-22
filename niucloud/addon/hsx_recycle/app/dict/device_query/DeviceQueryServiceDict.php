<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\device_query;

/**
 * 设备查询服务字典。
 *
 * 字典只描述平台可识别的基础服务、服务商和接口标识；站点的启停、价格、排序、
 * 密钥等可变配置由 sys_config 保存。业务模块只消费 service_code，不感知服务商细节。
 */
class DeviceQueryServiceDict
{
    public const CATEGORIES = [
        'apple' => '苹果',
        'android' => '安卓',
        'imei' => 'IMEI',
        'other' => '其他',
    ];

    public const QUERY_TYPES = [
        'imei' => 'IMEI',
        'sn' => '序列号',
        'imei_or_sn' => 'IMEI / 序列号',
        'barcode' => '条码',
        'ip' => 'IP 地址',
        'phone' => '手机号',
    ];

    public const RESULT_HANDLERS = [
        'coverage' => '保修信息',
        'activationlock' => '激活锁',
        'mdm' => '监管锁',
        'generic' => '通用结果',
    ];

    public const PROVIDERS = [
        '3023_main' => [
            'key' => '3023_main',
            'name' => '3023Data',
            'provider' => 'path_query',
            'endpoint_type' => 'path',
            'endpoint_label' => '接口路径',
            'base_url' => 'https://api.3023data.com',
            'method' => 'GET',
            'auth_type' => 'header',
            'auth_key' => 'key',
            'service_id_key' => 'key',
            'credential_type' => 'api_key',
            'verify_ssl' => 1,
            'priority' => 100,
            'enabled' => 1,
        ],
        'gkdt_main' => [
            'key' => 'gkdt_main',
            'name' => '爱查助手',
            'provider' => 'gkdt_query',
            'endpoint_type' => 'service_id',
            'endpoint_label' => '服务 ID',
            'base_url' => 'https://api-srv.gkdt.com/inquiry/async',
            'method' => 'GET',
            'auth_type' => 'signed',
            'auth_key' => '',
            'service_id_key' => 'key',
            'query_param' => 'code',
            'style' => '11',
            'credential_type' => 'appid_secret',
            'verify_ssl' => 1,
            'priority' => 90,
            'enabled' => 0,
        ],
    ];

    public const SERVICES = [
        ['code' => 'apple_model', 'name' => '苹果型号查询', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 10, 'cost_price' => 0.05, 'cache_ttl' => 31536000],
        ['code' => 'apple_coverage', 'name' => '苹果保修查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 20, 'cost_price' => 0.4, 'cache_ttl' => 2592000],
        ['code' => 'apple_coverage_capacity', 'name' => '苹果保修查询（容量/颜色）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 30, 'cost_price' => 1, 'cache_ttl' => 2592000],
        ['code' => 'apple_imei2', 'name' => '苹果IMEI2查询', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 35, 'cost_price' => 0, 'cache_ttl' => 2592000],
        ['code' => 'apple_coverage_activation', 'name' => '苹果保修查询（预激活）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 40, 'cost_price' => 1.2, 'cache_ttl' => 2592000],
        ['code' => 'apple_coverage_backup', 'name' => '苹果保修查询（备用）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 45, 'cost_price' => 1.2, 'cache_ttl' => 2592000],
        ['code' => 'apple_activationlock', 'name' => '激活锁查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 50, 'cost_price' => 0.4, 'cache_ttl' => 2592000],
        ['code' => 'apple_icloud', 'name' => 'ID 黑白查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 60, 'cost_price' => 0.8, 'cache_ttl' => 2592000],
        ['code' => 'apple_serial', 'name' => '序列号转换', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 70, 'cost_price' => 1, 'cache_ttl' => 2592000],
        ['code' => 'apple_repair', 'name' => '维修状态查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 80, 'cost_price' => 0.2, 'cache_ttl' => 604800],
        ['code' => 'apple_simlock', 'name' => '网络锁查询', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 90, 'cost_price' => 1, 'cache_ttl' => 2592000],
        ['code' => 'apple_carrier', 'name' => '运营商查询', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 100, 'cost_price' => 1.2, 'cache_ttl' => 2592000],
        ['code' => 'apple_country', 'name' => '销售地查询', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 110, 'cost_price' => 1.2, 'cache_ttl' => 2592000],
        ['code' => 'apple_partnumber', 'name' => '型号号码查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 120, 'cost_price' => 1.6, 'cache_ttl' => 2592000],
        ['code' => 'apple_mdm', 'name' => '监管锁查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 130, 'cost_price' => 8, 'cache_ttl' => 2592000],
        ['code' => 'apple_mac_activationlock', 'name' => 'Mac 激活锁查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 140, 'cost_price' => 2, 'cache_ttl' => 2592000],
        ['code' => 'apple_details', 'name' => '苹果验机报告', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 150, 'cost_price' => 2.5, 'cache_ttl' => 2592000],
        ['code' => 'apple_details_essentials', 'name' => '苹果验机报告（极速版）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 160, 'cost_price' => 2.5, 'cache_ttl' => 2592000],
        ['code' => 'apple_details_ultimate', 'name' => '苹果验机报告（旗舰版）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 170, 'cost_price' => 3.5, 'cache_ttl' => 2592000],
        ['code' => 'huawei_coverage', 'name' => '华为保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 200, 'cost_price' => 0.4, 'cache_ttl' => 2592000],
        ['code' => 'honor_coverage', 'name' => '荣耀保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 210, 'cost_price' => 0.4, 'cache_ttl' => 2592000],
        ['code' => 'xiaomi_coverage', 'name' => '小米保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 220, 'cost_price' => 1, 'cache_ttl' => 2592000],
        ['code' => 'oppo_coverage', 'name' => 'OPPO 保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 230, 'cost_price' => 0.8, 'cache_ttl' => 2592000],
        ['code' => 'vivo_coverage', 'name' => 'vivo 保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 240, 'cost_price' => 1, 'cache_ttl' => 2592000],
        ['code' => 'samsung_coverage', 'name' => '三星保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 250, 'cost_price' => 1, 'cache_ttl' => 2592000],
        ['code' => 'realme_coverage', 'name' => '真我保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 260, 'cost_price' => 0.8, 'cache_ttl' => 2592000],
        ['code' => 'nubia_coverage', 'name' => '努比亚保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 270, 'cost_price' => 1, 'cache_ttl' => 2592000],
        ['code' => 'motorola_coverage', 'name' => 'moto 保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 280, 'cost_price' => 1, 'cache_ttl' => 2592000],
        ['code' => 'zte_coverage', 'name' => '中兴保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 290, 'cost_price' => 0.6, 'cache_ttl' => 2592000],
        ['code' => 'xiaomi_activationlock', 'name' => '小米激活锁查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 300, 'cost_price' => 0.02, 'cache_ttl' => 604800],
        ['code' => 'imei_model', 'name' => 'IMEI 查询（型号）', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 400, 'cost_price' => 0.2, 'cache_ttl' => 31536000],
        ['code' => 'imei_manufacture', 'name' => 'IMEI 查询（生产日期）', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 410, 'cost_price' => 0.6, 'cache_ttl' => 31536000],
        ['code' => 'imei_blacklist', 'name' => 'IMEI 查询（黑名单）', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 420, 'cost_price' => 0.4, 'cache_ttl' => 604800],
        ['code' => 'imei_att', 'name' => 'AT&T 状态查询', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 430, 'cost_price' => 0.8, 'cache_ttl' => 604800],
        ['code' => 'imei_t_mobile', 'name' => 'T-Mobile 状态查询', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 440, 'cost_price' => 0.8, 'cache_ttl' => 604800],
        ['code' => 'imei_verizon', 'name' => 'Verizon 状态查询', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 450, 'cost_price' => 0.6, 'cache_ttl' => 604800],
        ['code' => 'item_barcode', 'name' => '条码查询', 'category' => 'other', 'query_type' => 'barcode', 'enabled' => 1, 'sort' => 500, 'cost_price' => 0.02, 'cache_ttl' => 31536000],
        ['code' => 'ip_location', 'name' => 'IP 地址查询', 'category' => 'other', 'query_type' => 'ip', 'enabled' => 1, 'sort' => 510, 'cost_price' => 0.001, 'cache_ttl' => 31536000],
        ['code' => 'phone_location', 'name' => '号码归属地查询', 'category' => 'other', 'query_type' => 'phone', 'enabled' => 1, 'sort' => 520, 'cost_price' => 0.001, 'cache_ttl' => 31536000],
    ];

    public const PROVIDER_ENDPOINTS = [
        '3023_main' => [
            'apple_model' => '/apple/model',
            'apple_coverage' => '/apple/coverage',
            'apple_coverage_capacity' => '/apple/coverage-capacity',
            'apple_coverage_activation' => '/apple/coverage-activation',
            'apple_coverage_backup' => '/apple/coverage-backup',
            'apple_activationlock' => '/apple/activationlock',
            'apple_icloud' => '/apple/icloud',
            'apple_serial' => '/apple/serial',
            'apple_repair' => '/apple/repair',
            'apple_simlock' => '/apple/simlock',
            'apple_carrier' => '/apple/carrier',
            'apple_country' => '/apple/country',
            'apple_partnumber' => '/apple/partnumber',
            'apple_mdm' => '/apple/mdm',
            'apple_mac_activationlock' => '/apple/mac-activationlock',
            'apple_details' => '/apple/details',
            'apple_details_essentials' => '/apple/details-essentials',
            'apple_details_ultimate' => '/apple/details-ultimate',
            'huawei_coverage' => '/huawei/coverage',
            'honor_coverage' => '/honor/coverage',
            'xiaomi_coverage' => '/xiaomi/coverage',
            'oppo_coverage' => '/oppo/coverage',
            'vivo_coverage' => '/vivo/coverage',
            'samsung_coverage' => '/samsung/coverage',
            'realme_coverage' => '/realme/coverage',
            'nubia_coverage' => '/nubia/coverage',
            'motorola_coverage' => '/motorola/coverage',
            'zte_coverage' => '/zte/coverage',
            'xiaomi_activationlock' => '/xiaomi/activationlock',
            'imei_model' => '/imei/model',
            'imei_manufacture' => '/imei/manufacture',
            'imei_blacklist' => '/imei/blacklist',
            'imei_att' => '/imei/att',
            'imei_t_mobile' => '/imei/t-mobile',
            'imei_verizon' => '/imei/verizon',
            'item_barcode' => '/item/barcode',
            'ip_location' => '/ip/location',
            'phone_location' => '/phone/location',
        ],
    ];
}
