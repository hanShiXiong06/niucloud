<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\device_query;

/**
 * 爱查助手内置服务目录。
 *
 * 这里只维护服务商公开的产品 ID、成本和业务元数据。站点密钥、启停及人工排序
 * 仍保存在 sys_config，恢复默认目录时不会覆盖 AppID/Secret。
 */
class GkdtDeviceQueryServiceDict
{
    public const PROVIDER_KEY = 'gkdt_main';

    public const SERVICES = [
        ['code' => 'apple_coverage', 'name' => '苹果保修查询', 'cost_price' => 0.07, 'endpoint_value' => '10101', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'apple_coverage_capacity', 'name' => '苹果保修查询（容量/颜色）', 'cost_price' => 0.42, 'endpoint_value' => '10102', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'apple_coverage_backup', 'name' => '苹果保修查询（备用）', 'cost_price' => 0.5, 'endpoint_value' => '10103', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'apple_activationlock', 'name' => '苹果激活锁查询', 'cost_price' => 0.15, 'endpoint_value' => '10104', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'activationlock'],
        ['code' => 'apple_icloud', 'name' => '苹果ID黑白查询', 'cost_price' => 0.2, 'endpoint_value' => '10105', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_repair', 'name' => '苹果维修状态查询', 'cost_price' => 0.05, 'endpoint_value' => '10106', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_model_config', 'name' => '苹果型号配置查询', 'cost_price' => 0.35, 'endpoint_value' => '10107', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_serial', 'name' => '苹果IMEI转序列号', 'cost_price' => 0.22, 'endpoint_value' => '10108', 'category' => 'apple', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'apple_imei2', 'name' => '苹果IMEI2查询', 'cost_price' => 0.39, 'endpoint_value' => '10109', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_activationlock_backup', 'name' => '苹果激活锁查询（备用）', 'cost_price' => 0.24, 'endpoint_value' => '10110', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'activationlock'],
        ['code' => 'apple_report_simlock', 'name' => '苹果验机报告（网络锁）', 'cost_price' => 1.0, 'endpoint_value' => '10111', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_report_carrier', 'name' => '苹果验机报告（网络锁/运营商）', 'cost_price' => 1.2, 'endpoint_value' => '10112', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_report_purchase_date', 'name' => '苹果验机报告（购买日期）', 'cost_price' => 1.2, 'endpoint_value' => '10113', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_report_product_type', 'name' => '苹果验机报告（产品类型）', 'cost_price' => 1.35, 'endpoint_value' => '10114', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_details_ultimate', 'name' => '苹果验机报告（旗舰版）', 'cost_price' => 1.35, 'endpoint_value' => '10115', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_simlock', 'name' => '苹果网络锁查询', 'cost_price' => 0.2, 'endpoint_value' => '10116', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_carrier', 'name' => '苹果运营商查询', 'cost_price' => 0.24, 'endpoint_value' => '10117', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_purchase_date', 'name' => '苹果购买日期查询', 'cost_price' => 0.32, 'endpoint_value' => '10118', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'apple_purchase_date_dop', 'name' => '苹果购买日期查询（DOP）', 'cost_price' => 0.5, 'endpoint_value' => '10119', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'apple_country', 'name' => '型号/销售地查询', 'cost_price' => 0.31, 'endpoint_value' => '10120', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_country_all', 'name' => '型号/销售地查询（全部设备）', 'cost_price' => 0.47, 'endpoint_value' => '10121', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_partnumber', 'name' => '苹果型号号码查询', 'cost_price' => 0.55, 'endpoint_value' => '10122', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_mac_activationlock', 'name' => 'Mac激活锁查询', 'cost_price' => 0.8, 'endpoint_value' => '10123', 'category' => 'apple', 'query_type' => 'sn', 'result_handler' => 'activationlock'],
        ['code' => 'apple_mac_config', 'name' => 'Mac配置查询', 'cost_price' => 0.18, 'endpoint_value' => '10124', 'category' => 'apple', 'query_type' => 'sn', 'result_handler' => 'generic'],
        ['code' => 'apple_mdm', 'name' => '苹果监管锁查询', 'cost_price' => 3.0, 'endpoint_value' => '10125', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'mdm'],
        ['code' => 'apple_model', 'name' => '苹果型号查询', 'cost_price' => 0.01, 'endpoint_value' => '10126', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_device_model', 'name' => '苹果机型查询', 'cost_price' => 0.09, 'endpoint_value' => '10127', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_capacity_color', 'name' => '苹果容量/颜色查询', 'cost_price' => 0.35, 'endpoint_value' => '10128', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'apple_repair_progress', 'name' => '苹果维修进度查询', 'cost_price' => 0.05, 'endpoint_value' => '10129', 'category' => 'apple', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],

        ['code' => 'huawei_coverage', 'name' => '华为保修查询', 'cost_price' => 0.14, 'endpoint_value' => '20101', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'xiaomi_coverage', 'name' => '小米保修查询', 'cost_price' => 0.29, 'endpoint_value' => '20201', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'xiaomi_activationlock', 'name' => '小米账号锁查询', 'cost_price' => 0.01, 'endpoint_value' => '20202', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'activationlock'],
        ['code' => 'oppo_coverage', 'name' => 'OPPO保修查询', 'cost_price' => 0.12, 'endpoint_value' => '20301', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'oppo_coverage_official', 'name' => 'OPPO保修查询（官网版）', 'cost_price' => 0.3, 'endpoint_value' => '20302', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'vivo_coverage', 'name' => 'vivo保修查询', 'cost_price' => 0.35, 'endpoint_value' => '20401', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'samsung_coverage', 'name' => '三星保修查询', 'cost_price' => 0.3, 'endpoint_value' => '20501', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'samsung_country', 'name' => '三星销售地查询', 'cost_price' => 0.45, 'endpoint_value' => '20502', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'generic'],
        ['code' => 'honor_coverage', 'name' => '荣耀保修查询', 'cost_price' => 0.14, 'endpoint_value' => '20601', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'oneplus_coverage', 'name' => '一加保修查询', 'cost_price' => 0.3, 'endpoint_value' => '20701', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'meizu_coverage', 'name' => '魅族保修查询', 'cost_price' => 0.24, 'endpoint_value' => '20801', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'realme_coverage', 'name' => 'realme保修查询', 'cost_price' => 0.3, 'endpoint_value' => '20901', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'nubia_coverage', 'name' => '努比亚保修查询', 'cost_price' => 0.24, 'endpoint_value' => '21001', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'asus_coverage', 'name' => '华硕保修查询', 'cost_price' => 0.24, 'endpoint_value' => '21101', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],
        ['code' => 'sony_coverage', 'name' => '索尼保修查询', 'cost_price' => 0.24, 'endpoint_value' => '21201', 'category' => 'android', 'query_type' => 'imei_or_sn', 'result_handler' => 'coverage'],

        ['code' => 'imei_blacklist', 'name' => 'GSMA黑白查询', 'cost_price' => 0.18, 'endpoint_value' => '90101', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'imei_usa_blacklist', 'name' => '美国黑名单查询', 'cost_price' => 0.29, 'endpoint_value' => '90102', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'imei_att', 'name' => 'AT&T黑白查询', 'cost_price' => 0.36, 'endpoint_value' => '90103', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'imei_t_mobile', 'name' => 'T-Mobile黑白查询', 'cost_price' => 0.18, 'endpoint_value' => '90104', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'imei_verizon', 'name' => 'Verizon黑白查询', 'cost_price' => 0.4, 'endpoint_value' => '90105', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'imei_tracfone', 'name' => 'TracFone黑白查询', 'cost_price' => 0.18, 'endpoint_value' => '90106', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'imei_kddi', 'name' => 'KDDI黑白查询', 'cost_price' => 0.18, 'endpoint_value' => '90107', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'imei_softbank', 'name' => 'SoftBank黑白查询', 'cost_price' => 0.2, 'endpoint_value' => '90108', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'imei_docomo', 'name' => 'DOCOMO黑白查询', 'cost_price' => 0.22, 'endpoint_value' => '90109', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'imei_uq_mobile', 'name' => 'UQ Mobile黑白查询', 'cost_price' => 0.18, 'endpoint_value' => '90110', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'japan_carrier', 'name' => '日本运营商查询', 'cost_price' => 0.48, 'endpoint_value' => '90901', 'category' => 'imei', 'query_type' => 'imei', 'result_handler' => 'generic'],
        ['code' => 'item_barcode', 'name' => '条形码查询', 'cost_price' => 0.01, 'endpoint_value' => '90902', 'category' => 'other', 'query_type' => 'barcode', 'result_handler' => 'generic'],
        ['code' => 'ip_location', 'name' => 'IP地址查询', 'cost_price' => 0.01, 'endpoint_value' => '90903', 'category' => 'other', 'query_type' => 'ip', 'result_handler' => 'generic'],
        ['code' => 'phone_location', 'name' => '号码归属地查询', 'cost_price' => 0.01, 'endpoint_value' => '90904', 'category' => 'other', 'query_type' => 'phone', 'result_handler' => 'generic'],
        ['code' => 'dji_coverage', 'name' => '大疆保修查询', 'cost_price' => 0.05, 'endpoint_value' => '90905', 'category' => 'other', 'query_type' => 'sn', 'result_handler' => 'coverage'],
    ];

    public static function services(): array
    {
        $services = [];
        foreach (self::SERVICES as $index => $item) {
            $services[] = [
                'code' => (string)$item['code'],
                'name' => (string)$item['name'],
                'category' => (string)$item['category'],
                'query_type' => (string)$item['query_type'],
                'result_handler' => (string)$item['result_handler'],
                'enabled' => 1,
                'show_in_check' => in_array((string)$item['code'], ['apple_coverage', 'apple_coverage_capacity', 'apple_activationlock'], true) ? 1 : 0,
                'sort' => ($index + 1) * 10,
                'cost_price' => (float)$item['cost_price'],
                'cache_ttl' => str_contains((string)$item['code'], 'repair') ? 604800 : 2592000,
            ];
        }

        return $services;
    }

    public static function mappings(bool $enabled = true): array
    {
        $mappings = [];
        foreach (self::SERVICES as $item) {
            $mappings[] = [
                'service_code' => (string)$item['code'],
                'channel_key' => self::PROVIDER_KEY,
                'enabled' => $enabled ? 1 : 0,
                'endpoint_type' => 'service_id',
                'endpoint_value' => (string)$item['endpoint_value'],
                'query_param' => 'code',
                'cost_price' => (float)$item['cost_price'],
                'retry_on' => [410, 502, 503],
                'switch_on_404' => 0,
                'switch_on_no_data' => 0,
            ];
        }

        return $mappings;
    }

    public static function presets(): array
    {
        $services = [];
        foreach (self::services() as $service) {
            $services[(string)$service['code']] = $service;
        }

        $result = [];
        foreach (self::mappings(true) as $mapping) {
            $result[] = [
                'service' => $services[(string)$mapping['service_code']] ?? [],
                'mapping' => $mapping,
            ];
        }

        return $result;
    }
}
