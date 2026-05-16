<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\yisu;

use addon\recycle\app\dict\yisu\YisuProductDict;
use addon\recycle\app\model\yisu\YisuProductConfig;
use core\base\BaseAdminService;

/**
 * 易速产品配置服务类
 * Class YisuProductService
 * @package addon\recycle\app\service\admin\yisu
 */
class YisuProductService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new YisuProductConfig();
    }

    /**
     * 获取产品列表（包含启用状态）
     * @return array
     */
    public function getList(): array
    {
        // 获取所有可用产品saas_yisu_product_config
        $allProducts = YisuProductDict::getProducts();
        // var_dump($allProducts);
        // exit;
        // 获取已配置的产品
        $configuredProducts =  $this->model
            ->where('site_id', $this->site_id)
            ->column('*', 'product_code');

        // 合并数据
 
        foreach ($allProducts as $product) {
            $productCode = $product['product_code'];
            $configured = $configuredProducts[$productCode] ?? null;

            $result[] = [
                'id' => $configured['id'] ?? 0,
                'product_code' => $productCode,
                'product_name' => $product['product_name'],
                'express_type' => $product['express_type'] ?? '快递',
                'logo' => $product['logo'],
                'status' => $configured['status'] ?? 0,
                'sort' => $configured['sort'] ?? 0,
                'is_configured' => !empty($configured),
            ];
        }

        return $result;
    }

    /**
     * 批量更新产品状态
     * @param array $products
     * @return bool
     */
    public function batchUpdate(array $products): bool
    {
        return YisuProductConfig::batchUpdateOrCreate($this->site_id, $products);
    }

    /**
     * 修改产品状态
     * @param string $productCode
     * @param int $status
     * @return bool
     */
    public function modifyStatus(string $productCode, int $status): bool
    {
        $product = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['product_code', '=', $productCode]
        ])->find();

        if ($product) {
            $product->save(['status' => $status]);
        } else {
            // 如果不存在，创建一个
            $productInfo = YisuProductDict::getProduct($productCode);
            if ($productInfo) {
                $this->model->create([
                    'site_id' => $this->site_id,
                    'product_code' => $productCode,
                    'product_name' => $productInfo['product_name'],
                    'logo' => $productInfo['logo'],
                    'status' => $status,
                    'sort' => 0,
                ]);
            }
        }

        return true;
    }

    /**
     * 获取启用的产品列表
     * @return array
     */
    public function getEnabledProducts(): array
    {
        return YisuProductConfig::getEnabledProducts($this->site_id);
    }
}
