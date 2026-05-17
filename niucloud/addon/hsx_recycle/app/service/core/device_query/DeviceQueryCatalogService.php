<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device_query;

class DeviceQueryCatalogService
{
    public function defaultServices(): array
    {
        return [
            ['code' => 'apple_model', 'name' => '苹果型号查询', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 10, 'cost_price' => 0.05, 'cache_ttl' => 31536000],
            ['code' => 'apple_coverage', 'name' => '苹果保修查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 20, 'cost_price' => 0.4, 'cache_ttl' => 2592000],
            ['code' => 'apple_coverage_capacity', 'name' => '苹果保修查询（容量/颜色）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 30, 'cost_price' => 1, 'cache_ttl' => 2592000],
            ['code' => 'apple_coverage_activation', 'name' => '苹果保修查询（预激活）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 40, 'cost_price' => 1.2, 'cache_ttl' => 2592000],
            ['code' => 'apple_coverage_backup', 'name' => '苹果保修查询（备用）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 45, 'cost_price' => 1.2, 'cache_ttl' => 2592000],
            ['code' => 'apple_activationlock', 'name' => '激活锁查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 50, 'cost_price' => 0.4, 'cache_ttl' => 2592000],
            ['code' => 'apple_icloud', 'name' => 'ID黑白查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 60, 'cost_price' => 0.8, 'cache_ttl' => 2592000],
            ['code' => 'apple_serial', 'name' => '序列号转换', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 70, 'cost_price' => 1, 'cache_ttl' => 2592000],
            ['code' => 'apple_repair', 'name' => '维修状态查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 80, 'cost_price' => 0.2, 'cache_ttl' => 604800],
            ['code' => 'apple_simlock', 'name' => '网络锁查询', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 90, 'cost_price' => 1, 'cache_ttl' => 2592000],
            ['code' => 'apple_carrier', 'name' => '运营商查询', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 100, 'cost_price' => 1.2, 'cache_ttl' => 2592000],
            ['code' => 'apple_country', 'name' => '销售地查询', 'category' => 'apple', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 110, 'cost_price' => 1.2, 'cache_ttl' => 2592000],
            ['code' => 'apple_partnumber', 'name' => '型号号码查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 120, 'cost_price' => 1.6, 'cache_ttl' => 2592000],
            ['code' => 'apple_mdm', 'name' => '监管锁查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 130, 'cost_price' => 8, 'cache_ttl' => 2592000],
            ['code' => 'apple_mac_activationlock', 'name' => 'Mac激活锁查询', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 140, 'cost_price' => 2, 'cache_ttl' => 2592000],
            ['code' => 'apple_details', 'name' => '苹果验机报告', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 150, 'cost_price' => 2.5, 'cache_ttl' => 2592000],
            ['code' => 'apple_details_essentials', 'name' => '苹果验机报告（极速版）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 160, 'cost_price' => 2.5, 'cache_ttl' => 2592000],
            ['code' => 'apple_details_ultimate', 'name' => '苹果验机报告（旗舰版）', 'category' => 'apple', 'query_type' => 'sn', 'enabled' => 1, 'sort' => 170, 'cost_price' => 3.5, 'cache_ttl' => 2592000],
            ['code' => 'huawei_coverage', 'name' => '华为保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 200, 'cost_price' => 0.4, 'cache_ttl' => 2592000],
            ['code' => 'honor_coverage', 'name' => '荣耀保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 210, 'cost_price' => 0.4, 'cache_ttl' => 2592000],
            ['code' => 'xiaomi_coverage', 'name' => '小米保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 220, 'cost_price' => 1, 'cache_ttl' => 2592000],
            ['code' => 'oppo_coverage', 'name' => 'OPPO保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 230, 'cost_price' => 0.8, 'cache_ttl' => 2592000],
            ['code' => 'vivo_coverage', 'name' => 'vivo保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 240, 'cost_price' => 1, 'cache_ttl' => 2592000],
            ['code' => 'samsung_coverage', 'name' => '三星保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 250, 'cost_price' => 1, 'cache_ttl' => 2592000],
            ['code' => 'realme_coverage', 'name' => '真我保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 260, 'cost_price' => 0.8, 'cache_ttl' => 2592000],
            ['code' => 'nubia_coverage', 'name' => '努比亚保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 270, 'cost_price' => 1, 'cache_ttl' => 2592000],
            ['code' => 'motorola_coverage', 'name' => 'moto保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 280, 'cost_price' => 1, 'cache_ttl' => 2592000],
            ['code' => 'zte_coverage', 'name' => '中兴保修查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 290, 'cost_price' => 0.6, 'cache_ttl' => 2592000],
            ['code' => 'xiaomi_activationlock', 'name' => '小米激活锁查询', 'category' => 'android', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 300, 'cost_price' => 0.02, 'cache_ttl' => 604800],
            ['code' => 'imei_model', 'name' => 'IMEI查询（型号）', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 400, 'cost_price' => 0.2, 'cache_ttl' => 31536000],
            ['code' => 'imei_manufacture', 'name' => 'IMEI查询（生产日期）', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 410, 'cost_price' => 0.6, 'cache_ttl' => 31536000],
            ['code' => 'imei_blacklist', 'name' => 'IMEI查询（黑名单）', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 420, 'cost_price' => 0.4, 'cache_ttl' => 604800],
            ['code' => 'imei_att', 'name' => 'AT&T状态查询', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 430, 'cost_price' => 0.8, 'cache_ttl' => 604800],
            ['code' => 'imei_t_mobile', 'name' => 'T-Mobile状态查询', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 440, 'cost_price' => 0.8, 'cache_ttl' => 604800],
            ['code' => 'imei_verizon', 'name' => 'Verizon状态查询', 'category' => 'imei', 'query_type' => 'imei', 'enabled' => 1, 'sort' => 450, 'cost_price' => 0.6, 'cache_ttl' => 604800],
            ['code' => 'item_barcode', 'name' => '条码查询', 'category' => 'other', 'query_type' => 'barcode', 'enabled' => 1, 'sort' => 500, 'cost_price' => 0.02, 'cache_ttl' => 31536000],
            ['code' => 'ip_location', 'name' => 'IP地址查询', 'category' => 'other', 'query_type' => 'ip', 'enabled' => 1, 'sort' => 510, 'cost_price' => 0.001, 'cache_ttl' => 31536000],
            ['code' => 'phone_location', 'name' => '号码归属地查询', 'category' => 'other', 'query_type' => 'phone', 'enabled' => 1, 'sort' => 520, 'cost_price' => 0.001, 'cache_ttl' => 31536000],
        ];
    }

    public function serviceCodeFromEndpoint(string $endpoint): string
    {
        $map = $this->endpointMap();
        $endpoint = '/' . ltrim(trim($endpoint), '/');

        return $map[$endpoint] ?? str_replace(['/', '-'], ['_', '_'], trim($endpoint, '/'));
    }

    public function endpointFromServiceCode(string $serviceCode): string
    {
        $map = array_flip($this->endpointMap());

        return $map[$serviceCode] ?? '';
    }

    public function endpointMap(): array
    {
        return [
            '/apple/model' => 'apple_model',
            '/apple/coverage' => 'apple_coverage',
            '/apple/coverage-capacity' => 'apple_coverage_capacity',
            '/apple/coverage-activation' => 'apple_coverage_activation',
            '/apple/coverage-backup' => 'apple_coverage_backup',
            '/apple/activationlock' => 'apple_activationlock',
            '/apple/icloud' => 'apple_icloud',
            '/apple/serial' => 'apple_serial',
            '/apple/repair' => 'apple_repair',
            '/apple/simlock' => 'apple_simlock',
            '/apple/carrier' => 'apple_carrier',
            '/apple/country' => 'apple_country',
            '/apple/partnumber' => 'apple_partnumber',
            '/apple/mdm' => 'apple_mdm',
            '/apple/mac-activationlock' => 'apple_mac_activationlock',
            '/apple/details' => 'apple_details',
            '/apple/details-essentials' => 'apple_details_essentials',
            '/apple/details-ultimate' => 'apple_details_ultimate',
            '/huawei/coverage' => 'huawei_coverage',
            '/honor/coverage' => 'honor_coverage',
            '/xiaomi/coverage' => 'xiaomi_coverage',
            '/oppo/coverage' => 'oppo_coverage',
            '/vivo/coverage' => 'vivo_coverage',
            '/samsung/coverage' => 'samsung_coverage',
            '/realme/coverage' => 'realme_coverage',
            '/nubia/coverage' => 'nubia_coverage',
            '/motorola/coverage' => 'motorola_coverage',
            '/zte/coverage' => 'zte_coverage',
            '/xiaomi/activationlock' => 'xiaomi_activationlock',
            '/imei/model' => 'imei_model',
            '/imei/manufacture' => 'imei_manufacture',
            '/imei/blacklist' => 'imei_blacklist',
            '/imei/att' => 'imei_att',
            '/imei/t-mobile' => 'imei_t_mobile',
            '/imei/verizon' => 'imei_verizon',
            '/item/barcode' => 'item_barcode',
            '/ip/location' => 'ip_location',
            '/phone/location' => 'phone_location',
        ];
    }
}
