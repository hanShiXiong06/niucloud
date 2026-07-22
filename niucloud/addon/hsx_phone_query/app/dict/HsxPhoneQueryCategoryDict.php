<?php

namespace addon\hsx_phone_query\app\dict;

/**
 * 手机查询项目默认模板
 */
class HsxPhoneQueryCategoryDict
{
    public static function types(): array
    {
        return [
            ['id' => 1, 'name' => '苹果查询'],
            ['id' => 2, 'name' => '安卓查询'],
            ['id' => 9, 'name' => '其他查询'],
        ];
    }

    public static function defaultItems(): array
    {
        $items = [];
        $overrides = self::loadInitOverrides();
        foreach (self::provider3023Items() as $item) {
            $row = [
                'channel_key' => '3023_main',
                'channel_name' => '3023Data',
                'type_id' => $item['type_id'],
                'service_code' => $item['service_code'],
                'query_param' => (string)($item['query_param'] ?? self::infer3023QueryParam((string)$item['endpoint'])),
                'name' => $item['name'],
                'price' => $item['price'],
                'cost_price' => $item['cost_price'],
                'is_show' => $item['is_show'] ?? 1,
                'sort' => $item['sort'],
            ];
            $items[] = self::applyItemOverride($row, $overrides);
        }

        foreach (self::providerAichaItems() as $item) {
            $row = [
                'channel_key' => 'gkdt_main',
                'channel_name' => '爱查助手',
                'type_id' => $item['type_id'],
                'service_code' => $item['service_code'],
                'query_param' => 'code',
                'name' => $item['name'],
                'price' => $item['price'] ?? 0,
                'cost_price' => $item['cost_price'] ?? 0,
                'is_show' => $item['is_show'] ?? 0,
                'sort' => $item['sort'],
            ];
            $items[] = self::applyItemOverride($row, $overrides);
        }

        return $items;
    }

    public static function provider3023Mappings(): array
    {
        $mappings = [];
        foreach (self::provider3023Items() as $item) {
            $mappings[] = [
                'service_code' => $item['service_code'],
                'endpoint_value' => $item['endpoint'],
                'query_param' => (string)($item['query_param'] ?? self::infer3023QueryParam((string)$item['endpoint'])),
                'cost_price' => $item['cost_price'],
            ];
        }

        return $mappings;
    }

    public static function providerAichaMappings(): array
    {
        $mappings = [];
        foreach (self::providerAichaItems() as $item) {
            $mappings[] = [
                'service_code' => $item['service_code'],
                'endpoint_value' => (string)$item['key'],
                'cost_price' => $item['cost_price'] ?? 0,
            ];
        }

        return $mappings;
    }

    public static function providerItems(string $channelKey): array
    {
        $items = [];
        if ($channelKey === 'gkdt_main') {
            $items = self::providerAichaItems();
            $queryParam = 'code';
        } elseif ($channelKey === '3023_main') {
            $items = self::provider3023Items();
            $queryParam = '';
        } else {
            return [];
        }

        foreach ($items as &$item) {
            $item['query_param'] = $queryParam ?: (string)($item['query_param'] ?? self::infer3023QueryParam((string)($item['endpoint'] ?? '')));
        }
        unset($item);

        return $items;
    }

    public static function providerItemMap(string $channelKey): array
    {
        $map = [];
        foreach (self::providerItems($channelKey) as $item) {
            $serviceCode = (string)($item['service_code'] ?? '');
            if ($serviceCode !== '') {
                $map[$serviceCode] = $item;
            }
        }

        return $map;
    }

    /**
     * 3023 的参数名由接口领域决定，不能全部写死为 sn。
     */
    public static function infer3023QueryParam(string $endpoint): string
    {
        $path = strtolower('/' . ltrim($endpoint, '/'));
        if (str_starts_with($path, '/imei/')) {
            return 'imei';
        }
        if (str_starts_with($path, '/item/')) {
            return 'barcode';
        }
        if (str_starts_with($path, '/ip/')) {
            return 'ip';
        }
        if (str_starts_with($path, '/phone/')) {
            return 'phone';
        }

        return 'sn';
    }

    private static function loadInitOverrides(): array
    {
        $file = dirname(__DIR__, 2) . '/resource/query_items.php';
        if (!is_file($file)) {
            return [];
        }

        $data = require $file;
        return is_array($data) ? $data : [];
    }

    private static function applyItemOverride(array $item, array $overrides): array
    {
        $channelKey = (string)($item['channel_key'] ?? '');
        $serviceCode = (string)($item['service_code'] ?? '');
        $override = $overrides[$channelKey][$serviceCode] ?? [];
        if (!is_array($override) || empty($override)) {
            return $item;
        }

        foreach (['name', 'price', 'cost_price', 'is_show', 'sort'] as $field) {
            if (array_key_exists($field, $override)) {
                $item[$field] = $override[$field];
            }
        }

        return $item;
    }

    private static function providerAichaItems(): array
    {
        return [
            ['type_id' => 1, 'service_code' => 'apple_coverage', 'name' => '苹果保修查询', 'key' => '10101', 'sort' => 680],
            ['type_id' => 1, 'service_code' => 'apple_coverage_capacity', 'name' => '苹果保修查询（容量/颜色）', 'key' => '10102', 'sort' => 670],
            ['type_id' => 1, 'service_code' => 'apple_coverage_backup', 'name' => '苹果保修查询（备用）', 'key' => '10103', 'sort' => 660],
            ['type_id' => 1, 'service_code' => 'apple_activationlock', 'name' => '苹果激活锁查询', 'key' => '10104', 'sort' => 650],
            ['type_id' => 1, 'service_code' => 'apple_icloud', 'name' => '苹果ID黑白查询', 'key' => '10105', 'sort' => 640],
            ['type_id' => 1, 'service_code' => 'apple_repair', 'name' => '苹果维修状态查询', 'key' => '10106', 'sort' => 630],
            ['type_id' => 1, 'service_code' => 'apple_model_config', 'name' => '苹果型号配置查询', 'key' => '10107', 'sort' => 620],
            ['type_id' => 1, 'service_code' => 'apple_serial', 'name' => '苹果IMEI转序列号', 'key' => '10108', 'sort' => 610],
            ['type_id' => 1, 'service_code' => 'apple_imei2', 'name' => '苹果IMEI2查询', 'key' => '10109', 'sort' => 600],
            ['type_id' => 1, 'service_code' => 'apple_activationlock_backup', 'name' => '苹果激活锁查询（备用）', 'key' => '10110', 'sort' => 590],
            ['type_id' => 1, 'service_code' => 'apple_details_simlock', 'name' => '苹果验机报告（网络锁）', 'key' => '10111', 'sort' => 580],
            ['type_id' => 1, 'service_code' => 'apple_details_carrier', 'name' => '苹果验机报告（网络锁/运营商）', 'key' => '10112', 'sort' => 570],
            ['type_id' => 1, 'service_code' => 'apple_details_purchase_date', 'name' => '苹果验机报告（购买日期）', 'key' => '10113', 'sort' => 560],
            ['type_id' => 1, 'service_code' => 'apple_details_part_description', 'name' => '苹果验机报告（产品类型）', 'key' => '10114', 'sort' => 550],
            ['type_id' => 1, 'service_code' => 'apple_details_ultimate', 'name' => '苹果验机报告（旗舰版）', 'key' => '10115', 'sort' => 540],
            ['type_id' => 1, 'service_code' => 'apple_simlock', 'name' => '苹果网络锁查询', 'key' => '10116', 'sort' => 530],
            ['type_id' => 1, 'service_code' => 'apple_carrier', 'name' => '苹果运营商查询', 'key' => '10117', 'sort' => 520],
            ['type_id' => 1, 'service_code' => 'apple_purchase_date', 'name' => '苹果购买日期查询', 'key' => '10118', 'sort' => 510],
            ['type_id' => 1, 'service_code' => 'apple_purchase_date_dop', 'name' => '苹果购买日期查询（DOP）', 'key' => '10119', 'sort' => 500],
            ['type_id' => 1, 'service_code' => 'apple_country', 'name' => '型号/销售地查询', 'key' => '10120', 'sort' => 490],
            ['type_id' => 1, 'service_code' => 'apple_part_country_all', 'name' => '型号/销售地查询（全部设备）', 'key' => '10121', 'sort' => 480],
            ['type_id' => 1, 'service_code' => 'apple_partnumber', 'name' => '苹果型号号码查询', 'key' => '10122', 'sort' => 470],
            ['type_id' => 1, 'service_code' => 'apple_mac_activationlock', 'name' => 'Mac激活锁查询', 'key' => '10123', 'sort' => 460],
            ['type_id' => 1, 'service_code' => 'apple_mac_config', 'name' => 'Mac配置查询', 'key' => '10124', 'sort' => 450],
            ['type_id' => 1, 'service_code' => 'apple_mdm', 'name' => '苹果监管锁查询', 'key' => '10125', 'sort' => 440],
            ['type_id' => 1, 'service_code' => 'apple_model', 'name' => '苹果型号查询', 'key' => '10126', 'sort' => 430],
            ['type_id' => 1, 'service_code' => 'apple_model_number_identifier', 'name' => '苹果机型查询', 'key' => '10127', 'sort' => 420],
            ['type_id' => 1, 'service_code' => 'apple_capacity_color', 'name' => '苹果容量/颜色查询', 'key' => '10128', 'sort' => 410],
            ['type_id' => 1, 'service_code' => 'apple_repair_progress', 'name' => '苹果维修进度查询', 'key' => '10129', 'sort' => 400],
            ['type_id' => 2, 'service_code' => 'huawei_coverage', 'name' => '华为保修查询', 'key' => '20101', 'sort' => 390],
            ['type_id' => 2, 'service_code' => 'xiaomi_coverage', 'name' => '小米保修查询', 'key' => '20201', 'sort' => 380],
            ['type_id' => 2, 'service_code' => 'xiaomi_activationlock', 'name' => '小米账号锁查询', 'key' => '20202', 'sort' => 370],
            ['type_id' => 2, 'service_code' => 'oppo_coverage', 'name' => 'OPPO保修查询', 'key' => '20301', 'sort' => 360],
            ['type_id' => 2, 'service_code' => 'oppo_coverage_official', 'name' => 'OPPO保修查询（官网版）', 'key' => '20302', 'sort' => 350],
            ['type_id' => 2, 'service_code' => 'vivo_coverage', 'name' => 'vivo保修查询', 'key' => '20401', 'sort' => 340],
            ['type_id' => 2, 'service_code' => 'samsung_coverage', 'name' => '三星保修查询', 'key' => '20501', 'sort' => 330],
            ['type_id' => 2, 'service_code' => 'samsung_country', 'name' => '三星销售地查询', 'key' => '20502', 'sort' => 320],
            ['type_id' => 2, 'service_code' => 'honor_coverage', 'name' => '荣耀保修查询', 'key' => '20601', 'sort' => 310],
            ['type_id' => 2, 'service_code' => 'oneplus_coverage', 'name' => '一加保修查询', 'key' => '20701', 'sort' => 300],
            ['type_id' => 2, 'service_code' => 'meizu_coverage', 'name' => '魅族保修查询', 'key' => '20801', 'sort' => 290],
            ['type_id' => 2, 'service_code' => 'realme_coverage', 'name' => 'realme保修查询', 'key' => '20901', 'sort' => 280],
            ['type_id' => 2, 'service_code' => 'nubia_coverage', 'name' => '努比亚保修查询', 'key' => '21001', 'sort' => 270],
            ['type_id' => 2, 'service_code' => 'asus_coverage', 'name' => '华硕保修查询', 'key' => '21101', 'sort' => 260],
            ['type_id' => 2, 'service_code' => 'sony_coverage', 'name' => '索尼保修查询', 'key' => '21201', 'sort' => 250],
            ['type_id' => 9, 'service_code' => 'gsma_blacklist', 'name' => 'GSMA黑白查询', 'key' => '90101', 'sort' => 240],
            ['type_id' => 9, 'service_code' => 'us_blacklist', 'name' => '美国黑名单查询', 'key' => '90102', 'sort' => 230],
            ['type_id' => 9, 'service_code' => 'imei_att', 'name' => 'AT&T黑白查询', 'key' => '90103', 'sort' => 220],
            ['type_id' => 9, 'service_code' => 'imei_t_mobile', 'name' => 'T-Mobile黑白查询', 'key' => '90104', 'sort' => 210],
            ['type_id' => 9, 'service_code' => 'imei_verizon', 'name' => 'Verizon黑白查询', 'key' => '90105', 'sort' => 200],
            ['type_id' => 9, 'service_code' => 'tracfone_blacklist', 'name' => 'TracFone黑白查询', 'key' => '90106', 'sort' => 190],
            ['type_id' => 9, 'service_code' => 'kddi_blacklist', 'name' => 'KDDI黑白查询', 'key' => '90107', 'sort' => 180],
            ['type_id' => 9, 'service_code' => 'softbank_blacklist', 'name' => 'SoftBank黑白查询', 'key' => '90108', 'sort' => 170],
            ['type_id' => 9, 'service_code' => 'docomo_blacklist', 'name' => 'DOCOMO黑白查询', 'key' => '90109', 'sort' => 160],
            ['type_id' => 9, 'service_code' => 'uq_mobile_blacklist', 'name' => 'UQ Mobile黑白查询', 'key' => '90110', 'sort' => 150],
            ['type_id' => 9, 'service_code' => 'japan_carrier', 'name' => '日本运营商查询', 'key' => '90901', 'sort' => 140],
            ['type_id' => 9, 'service_code' => 'dji_coverage', 'name' => '大疆保修查询', 'key' => '90905', 'sort' => 130],
        ];
    }

    private static function provider3023Items(): array
    {
        return [
            ['type_id' => 1, 'service_code' => 'apple_coverage', 'name' => '苹果保修查询', 'endpoint' => '/apple/coverage', 'price' => 0.80, 'cost_price' => 0.80, 'sort' => 380],
            ['type_id' => 1, 'service_code' => 'apple_coverage_capacity', 'name' => '苹果保修查询（容量/颜色）', 'endpoint' => '/apple/coverage-capacity', 'price' => 1.00, 'cost_price' => 1.00, 'sort' => 370],
            ['type_id' => 1, 'service_code' => 'apple_coverage_activation', 'name' => '苹果保修查询（预激活）', 'endpoint' => '/apple/coverage-activation', 'price' => 1.20, 'cost_price' => 1.20, 'sort' => 360],
            ['type_id' => 1, 'service_code' => 'apple_coverage_backup', 'name' => '苹果保修查询（备用）', 'endpoint' => '/apple/coverage-backup', 'price' => 1.20, 'cost_price' => 1.20, 'sort' => 350],
            ['type_id' => 1, 'service_code' => 'apple_activationlock', 'name' => '激活锁查询', 'endpoint' => '/apple/activationlock', 'price' => 0.40, 'cost_price' => 0.40, 'sort' => 340],
            ['type_id' => 1, 'service_code' => 'apple_icloud', 'name' => 'ID黑白查询', 'endpoint' => '/apple/icloud', 'price' => 0.80, 'cost_price' => 0.80, 'sort' => 330],
            ['type_id' => 1, 'service_code' => 'apple_serial', 'name' => '序列号转换', 'endpoint' => '/apple/serial', 'price' => 1.00, 'cost_price' => 1.00, 'sort' => 320],
            ['type_id' => 1, 'service_code' => 'apple_repair', 'name' => '维修状态查询', 'endpoint' => '/apple/repair', 'price' => 0.20, 'cost_price' => 0.20, 'sort' => 310],
            ['type_id' => 1, 'service_code' => 'apple_simlock', 'name' => '网络锁查询', 'endpoint' => '/apple/simlock', 'price' => 1.00, 'cost_price' => 1.00, 'sort' => 300],
            ['type_id' => 1, 'service_code' => 'apple_carrier', 'name' => '运营商查询', 'endpoint' => '/apple/carrier', 'price' => 1.20, 'cost_price' => 1.20, 'sort' => 290],
            ['type_id' => 1, 'service_code' => 'apple_country', 'name' => '销售地查询', 'endpoint' => '/apple/country', 'price' => 1.20, 'cost_price' => 1.20, 'sort' => 280],
            ['type_id' => 1, 'service_code' => 'apple_partnumber', 'name' => '型号号码查询', 'endpoint' => '/apple/partnumber', 'price' => 1.60, 'cost_price' => 1.60, 'sort' => 270],
            ['type_id' => 1, 'service_code' => 'apple_mdm', 'name' => '监管锁查询', 'endpoint' => '/apple/mdm', 'price' => 8.00, 'cost_price' => 8.00, 'sort' => 260],
            ['type_id' => 1, 'service_code' => 'apple_mac_activationlock', 'name' => 'Mac激活锁查询', 'endpoint' => '/apple/mac-activationlock', 'price' => 2.00, 'cost_price' => 2.00, 'sort' => 250],
            ['type_id' => 1, 'service_code' => 'apple_details', 'name' => '苹果验机报告', 'endpoint' => '/apple/details', 'price' => 2.50, 'cost_price' => 2.50, 'sort' => 240],
            ['type_id' => 1, 'service_code' => 'apple_details_essentials', 'name' => '苹果验机报告（极速版）', 'endpoint' => '/apple/details-essentials', 'price' => 3.50, 'cost_price' => 3.50, 'sort' => 230],
            ['type_id' => 1, 'service_code' => 'apple_details_ultimate', 'name' => '苹果验机报告（旗舰版）', 'endpoint' => '/apple/details-ultimate', 'price' => 3.50, 'cost_price' => 3.50, 'sort' => 220],
            ['type_id' => 1, 'service_code' => 'apple_model', 'name' => '苹果型号查询', 'endpoint' => '/apple/model', 'price' => 0.05, 'cost_price' => 0.05, 'sort' => 210],
            ['type_id' => 2, 'service_code' => 'huawei_coverage', 'name' => '华为保修查询', 'endpoint' => '/huawei/coverage', 'price' => 0.40, 'cost_price' => 0.40, 'sort' => 200],
            ['type_id' => 2, 'service_code' => 'honor_coverage', 'name' => '荣耀保修查询', 'endpoint' => '/honor/coverage', 'price' => 0.40, 'cost_price' => 0.40, 'sort' => 190],
            ['type_id' => 2, 'service_code' => 'xiaomi_coverage', 'name' => '小米保修查询', 'endpoint' => '/xiaomi/coverage', 'price' => 1.00, 'cost_price' => 1.00, 'sort' => 180],
            ['type_id' => 2, 'service_code' => 'oppo_coverage', 'name' => 'OPPO保修查询', 'endpoint' => '/oppo/coverage', 'price' => 0.80, 'cost_price' => 0.80, 'sort' => 170],
            ['type_id' => 2, 'service_code' => 'vivo_coverage', 'name' => 'vivo保修查询', 'endpoint' => '/vivo/coverage', 'price' => 1.00, 'cost_price' => 1.00, 'sort' => 160],
            ['type_id' => 2, 'service_code' => 'samsung_coverage', 'name' => '三星保修查询', 'endpoint' => '/samsung/coverage', 'price' => 1.00, 'cost_price' => 1.00, 'sort' => 150],
            ['type_id' => 2, 'service_code' => 'realme_coverage', 'name' => '真我保修查询', 'endpoint' => '/realme/coverage', 'price' => 0.80, 'cost_price' => 0.80, 'sort' => 140],
            ['type_id' => 2, 'service_code' => 'nubia_coverage', 'name' => '努比亚保修查询', 'endpoint' => '/nubia/coverage', 'price' => 1.00, 'cost_price' => 1.00, 'sort' => 130],
            ['type_id' => 2, 'service_code' => 'motorola_coverage', 'name' => 'moto保修查询', 'endpoint' => '/motorola/coverage', 'price' => 1.00, 'cost_price' => 1.00, 'sort' => 120],
            ['type_id' => 2, 'service_code' => 'zte_coverage', 'name' => '中兴保修查询', 'endpoint' => '/zte/coverage', 'price' => 0.60, 'cost_price' => 0.60, 'sort' => 110],
            ['type_id' => 2, 'service_code' => 'xiaomi_activationlock', 'name' => '小米激活锁查询', 'endpoint' => '/xiaomi/activationlock', 'price' => 0.02, 'cost_price' => 0.02, 'sort' => 100],
            ['type_id' => 9, 'service_code' => 'imei_model', 'name' => 'IMEI查询（型号）', 'endpoint' => '/imei/model', 'price' => 0.20, 'cost_price' => 0.20, 'sort' => 90],
            ['type_id' => 9, 'service_code' => 'imei_manufacture', 'name' => 'IMEI查询（生产日期）', 'endpoint' => '/imei/manufacture', 'price' => 0.60, 'cost_price' => 0.60, 'sort' => 80],
            ['type_id' => 9, 'service_code' => 'imei_blacklist', 'name' => 'IMEI查询（黑名单）', 'endpoint' => '/imei/blacklist', 'price' => 0.40, 'cost_price' => 0.40, 'sort' => 70],
            ['type_id' => 9, 'service_code' => 'imei_att', 'name' => 'AT&T状态查询', 'endpoint' => '/imei/att', 'price' => 0.80, 'cost_price' => 0.80, 'sort' => 60],
            ['type_id' => 9, 'service_code' => 'imei_t_mobile', 'name' => 'T-Mobile状态查询', 'endpoint' => '/imei/t-mobile', 'price' => 0.80, 'cost_price' => 0.80, 'sort' => 50],
            ['type_id' => 9, 'service_code' => 'imei_verizon', 'name' => 'Verizon状态查询', 'endpoint' => '/imei/verizon', 'price' => 0.60, 'cost_price' => 0.60, 'sort' => 40],
            ['type_id' => 9, 'service_code' => 'item_barcode', 'name' => '条码查询', 'endpoint' => '/item/barcode', 'price' => 0.02, 'cost_price' => 0.02, 'sort' => 30],
            ['type_id' => 9, 'service_code' => 'ip_location', 'name' => 'IP地址查询', 'endpoint' => '/ip/location', 'price' => 0.001, 'cost_price' => 0.001, 'sort' => 20],
            ['type_id' => 9, 'service_code' => 'phone_location', 'name' => '号码归属地查询', 'endpoint' => '/phone/location', 'price' => 0.001, 'cost_price' => 0.001, 'sort' => 10],
        ];
    }
}
