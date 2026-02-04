<?php
declare(strict_types=1);

namespace addon\recycle\app\model\yisu;

use core\base\BaseModel;

/**
 * 易速快递产品配置模型
 * Class YisuProductConfig
 * @package addon\recycle\app\model\yisu
 */
class YisuProductConfig extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'yisu_product_config';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

    /**
     * 获取站点启用的产品列表
     * @param int $siteId
     * @return array
     */
    public static function getEnabledProducts(int $siteId): array
    {
        return self::where([
            ['site_id', '=', $siteId],
            ['status', '=', 1]
        ])->order('sort', 'asc')->select()->toArray();
    }

    /**
     * 获取启用的产品代码列表
     * @param int $siteId
     * @return array
     */
    public static function getEnabledProductCodes(int $siteId): array
    {
        $products = self::getEnabledProducts($siteId);
        return array_column($products, 'product_code');
    }

    /**
     * 检查产品是否启用
     * @param int $siteId
     * @param string $productCode
     * @return bool
     */
    public static function isProductEnabled(int $siteId, string $productCode): bool
    {
        return self::where([
            ['site_id', '=', $siteId],
            ['product_code', '=', $productCode],
            ['status', '=', 1]
        ])->count() > 0;
    }

    /**
     * 批量更新或创建产品配置
     * @param int $siteId
     * @param array $products
     * @return bool
     */
    public static function batchUpdateOrCreate(int $siteId, array $products): bool
    {
        foreach ($products as $product) {
            $exists = self::where([
                ['site_id', '=', $siteId],
                ['product_code', '=', $product['product_code']]
            ])->find();

            if ($exists) {
                $exists->save([
                    'product_name' => $product['product_name'] ?? '',
                    'logo' => $product['logo'] ?? '',
                    'status' => $product['status'] ?? 1,
                    'sort' => $product['sort'] ?? 0,
                ]);
            } else {
                self::create([
                    'site_id' => $siteId,
                    'product_code' => $product['product_code'],
                    'product_name' => $product['product_name'] ?? '',
                    'logo' => $product['logo'] ?? '',
                    'status' => $product['status'] ?? 1,
                    'sort' => $product['sort'] ?? 0,
                ]);
            }
        }
        return true;
    }
}
