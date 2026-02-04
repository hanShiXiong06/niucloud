<?php
declare(strict_types=1);

namespace addon\recycle\app\dict\yisu;

/**
 * 易速快递产品字典（基于官方文档）
 * Class YisuProductDict
 * @package addon\recycle\app\dict\yisu
 */
class YisuProductDict
{
    /**
     * 获取所有产品列表
     * @return array
     */
    public static function getProducts(): array
    {
        return [
            // 快递类
            ['product_code' => '1', 'product_name' => '申通快递', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '2', 'product_name' => '圆通快递', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '3', 'product_name' => '德邦快递', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '5', 'product_name' => '顺丰(标快)', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '10', 'product_name' => '极兔速递', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '11', 'product_name' => '中通快递', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '12', 'product_name' => '韵达快递', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '13', 'product_name' => '京东快递', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '36', 'product_name' => '菜鸟裹裹', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '47', 'product_name' => 'EMS特快', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '59', 'product_name' => '京东快递(电池)', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '95', 'product_name' => '安能小件', 'express_type' => '快递', 'logo' => ''],
            ['product_code' => '76', 'product_name' => '京东快递(3KG内小件)', 'express_type' => '快递', 'logo' => ''],

            // 重货类
            ['product_code' => '21', 'product_name' => '德邦物流(卡航)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '23', 'product_name' => '顺心捷达(60公斤内)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '24', 'product_name' => '顺丰快运', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '25', 'product_name' => '京东物流(重货)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '39', 'product_name' => '中通快运(70公斤内)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '40', 'product_name' => '百世快运', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '42', 'product_name' => '跨越陆运', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '48', 'product_name' => '壹米滴答', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '53', 'product_name' => '顺心捷达(60公斤以上)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '67', 'product_name' => '中通快运(60公斤以上)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '68', 'product_name' => '顺心捷达(8000抛)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '71', 'product_name' => '安能物流', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '86', 'product_name' => '百世快运(70公斤以上)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '97', 'product_name' => '跨越专运', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '101', 'product_name' => '京东重货(电池)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '102', 'product_name' => '百世快运(电池)', 'express_type' => '重货', 'logo' => ''],
            ['product_code' => '103', 'product_name' => '壹米滴答(电动车)', 'express_type' => '重货', 'logo' => ''],

            // 得物类
            ['product_code' => '28', 'product_name' => '京东得物', 'express_type' => '得物', 'logo' => ''],
            ['product_code' => '29', 'product_name' => '德邦得物', 'express_type' => '得物', 'logo' => ''],
            ['product_code' => '30', 'product_name' => '顺丰得物', 'express_type' => '得物', 'logo' => ''],
            ['product_code' => '32', 'product_name' => '京东物流(得物)', 'express_type' => '得物', 'logo' => ''],
            ['product_code' => '34', 'product_name' => '德邦物流(得物)', 'express_type' => '得物', 'logo' => ''],
            ['product_code' => '35', 'product_name' => '顺丰快运(得物)', 'express_type' => '得物', 'logo' => ''],
            ['product_code' => '88', 'product_name' => '京东得物(封控区域)', 'express_type' => '得物', 'logo' => ''],
        ];
    }

    /**
     * 根据产品代码获取产品信息
     * @param string $productCode
     * @return array|null
     */
    public static function getProduct(string $productCode): ?array
    {
        $products = self::getProducts();
        foreach ($products as $product) {
            if ($product['product_code'] == $productCode) {
                return $product;
            }
        }
        return null;
    }

    /**
     * 获取产品名称
     * @param string $productCode
     * @return string
     */
    public static function getProductName(string $productCode): string
    {
        $product = self::getProduct($productCode);
        return $product['product_name'] ?? '未知产品';
    }

    /**
     * 按类型分组获取产品
     * @return array
     */
    public static function getProductsByType(): array
    {
        $products = self::getProducts();
        $grouped = [
            '快递' => [],
            '重货' => [],
            '得物' => [],
        ];

        foreach ($products as $product) {
            $type = $product['express_type'];
            if (isset($grouped[$type])) {
                $grouped[$type][] = $product;
            }
        }

        return $grouped;
    }
}
