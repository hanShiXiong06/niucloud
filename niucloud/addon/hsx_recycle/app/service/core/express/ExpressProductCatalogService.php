<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use addon\hsx_recycle\app\dict\config\RecycleConfigKeyDict;
use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use addon\hsx_recycle\app\dict\yisu\YisuProductDict;
use app\service\core\sys\CoreConfigService;
use core\base\BaseCoreService;

/**
 * 快递产品目录。
 *
 * 产品主数据、站点启用状态和排序统一存放在 sys_config，避免每接入一个
 * 快递服务商就增加一张产品配置表。业务订单仍保存产品编号和名称快照。
 */
class ExpressProductCatalogService extends BaseCoreService
{
    private const SCHEMA_VERSION = 2;

    private CoreConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = new CoreConfigService();
    }

    /**
     * 获取服务商产品平铺列表。
     */
    public function getProducts(int $siteId, string $provider = ThirdPartyDict::PROVIDER_YISU): array
    {
        $provider = $this->normalizeProvider($provider);
        $config = $this->getConfig($siteId);
        $saved = $this->getSavedProducts($siteId, $provider, $config);
        $products = [];

        foreach ($this->getDefaultProducts($provider) as $product) {
            $normalized = $this->normalizeProduct($product, $provider);
            if ($normalized['product_code'] !== '') {
                $products[$normalized['product_code']] = $normalized;
            }
        }

        foreach ($this->normalizeProductCollection($saved, $provider) as $product) {
            $code = $product['product_code'];
            $products[$code] = isset($products[$code])
                ? array_merge($products[$code], $product)
                : $product;
        }

        $products = array_values($products);
        usort($products, [$this, 'compareProducts']);
        return $products;
    }

    /**
     * 获取已开启产品，供报价和下单使用。
     */
    public function getEnabledProducts(int $siteId, string $provider = ThirdPartyDict::PROVIDER_YISU): array
    {
        return array_values(array_filter(
            $this->getProducts($siteId, $provider),
            static fn(array $product): bool => (int)($product['status'] ?? 0) === 1
        ));
    }

    /**
     * 返回后台编辑器需要的 Tab、Tree 和平铺数据。
     */
    public function getView(int $siteId, string $provider = ThirdPartyDict::PROVIDER_YISU): array
    {
        $products = $this->getProducts($siteId, $provider);
        $tree = $this->buildTree($products);
        $tabs = array_map(static function (array $node): array {
            return [
                'key' => (string)$node['key'],
                'label' => (string)$node['label'],
                'count' => (int)($node['product_count'] ?? 0),
            ];
        }, $tree);

        return [
            'provider' => $this->normalizeProvider($provider),
            'tabs' => $tabs,
            'tree' => $tree,
            'list' => $products,
            'total' => count($products),
            'enabled_count' => count(array_filter($products, static fn(array $item): bool => (int)$item['status'] === 1)),
        ];
    }

    /**
     * 保存某个服务商的完整站点配置。
     */
    public function saveProducts(int $siteId, string $provider, array $products): bool
    {
        $provider = $this->normalizeProvider($provider);
        $config = $this->getConfig($siteId);
        $oldChunkCount = (int)($config['providers'][$provider]['chunk_count'] ?? 0);
        $indexed = [];
        foreach ($this->normalizeProductCollection($products, $provider) as $product) {
            if ($product['product_code'] === '') {
                continue;
            }
            $indexed[$product['product_code']] = $product;
        }

        $config['schema_version'] = self::SCHEMA_VERSION;
        $config['providers'][$provider] = [
            'updated_at' => time(),
            'storage' => 'inline_compact',
            'product_count' => count($indexed),
            // 产品通常只有几十至几百条。采用紧凑行结构放在一份 sys_config 中，
            // 比人为拆分很多配置项更容易维护，也避免重复保存 provider/type 字段。
            'products' => array_map([$this, 'compactProduct'], array_values($indexed)),
        ];
        $config['updated_at'] = time();
        $this->configService->setConfig($siteId, RecycleConfigKeyDict::EXPRESS_PRODUCT_CATALOG, $config);

        // 兼容上一版分片结构：读取仍支持，任意一次保存后收敛为单配置并清理旧片。
        for ($index = 0; $index < $oldChunkCount; $index++) {
            $this->configService->clearConfig($siteId, $this->chunkConfigKey($provider, $index));
        }
        return true;
    }

    public function modifyStatus(int $siteId, string $provider, string $productCode, int $status): bool
    {
        $products = $this->getProducts($siteId, $provider);
        foreach ($products as &$product) {
            if ((string)$product['product_code'] === trim($productCode)) {
                $product['status'] = $status === 1 ? 1 : 0;
                break;
            }
        }
        unset($product);
        return $this->saveProducts($siteId, $provider, $products);
    }

    /**
     * 合并 Excel 导入数据。已有的 provider + product_code 不覆盖，防止重置
     * 客户已经配置好的启用状态与排序。
     */
    public function importProducts(int $siteId, array $rows, string $defaultProvider = ThirdPartyDict::PROVIDER_YISU): array
    {
        $grouped = [];
        foreach ($rows as $row) {
            $provider = $this->normalizeProvider((string)($row['provider'] ?? $defaultProvider));
            $product = $this->normalizeProduct($row, $provider);
            if ($product['product_code'] === '' || $product['product_name'] === '') {
                continue;
            }
            $grouped[$provider][] = $product;
        }

        $created = 0;
        $skipped = 0;
        $skippedItems = [];
        foreach ($grouped as $provider => $incomingProducts) {
            $products = $this->getProducts($siteId, $provider);
            $existing = [];
            foreach ($products as $product) {
                $existing[(string)$product['product_code']] = true;
            }

            foreach ($incomingProducts as $product) {
                $code = (string)$product['product_code'];
                if (isset($existing[$code])) {
                    $skipped++;
                    if (count($skippedItems) < 100) {
                        $skippedItems[] = [
                            'provider' => $provider,
                            'product_code' => $code,
                            'product_name' => (string)$product['product_name'],
                            'reason' => '产品编号已存在',
                        ];
                    }
                    continue;
                }
                $product['status'] = 0;
                $products[] = $product;
                $existing[$code] = true;
                $created++;
            }
            $this->saveProducts($siteId, $provider, $products);
        }

        return [
            'created_count' => $created,
            'skipped_count' => $skipped,
            'skipped' => $skippedItems,
            'errors_truncated' => $skipped > count($skippedItems),
        ];
    }

    private function getConfig(int $siteId): array
    {
        $config = $this->configService->getConfigValue($siteId, RecycleConfigKeyDict::EXPRESS_PRODUCT_CATALOG);
        return is_array($config) ? $config : [];
    }

    private function getSavedProducts(int $siteId, string $provider, array $config): array
    {
        $providerConfig = $config['providers'][$provider] ?? [];
        if (($providerConfig['storage'] ?? '') !== 'sys_config_chunks') {
            $products = is_array($providerConfig['products'] ?? null) ? $providerConfig['products'] : [];
            if (($providerConfig['storage'] ?? '') === 'inline_compact') {
                return array_map([$this, 'expandProduct'], $products);
            }
            return $products;
        }

        $products = [];
        $chunkCount = max(0, (int)($providerConfig['chunk_count'] ?? 0));
        for ($index = 0; $index < $chunkCount; $index++) {
            $chunk = $this->configService->getConfigValue($siteId, $this->chunkConfigKey($provider, $index));
            if (is_array($chunk['products'] ?? null)) {
                array_push($products, ...$chunk['products']);
            }
        }
        return $products;
    }

    private function chunkConfigKey(string $provider, int $index): string
    {
        return RecycleConfigKeyDict::EXPRESS_PRODUCT_CATALOG . ':' . $provider . ':' . $index;
    }

    /** @return array<int, mixed> */
    private function compactProduct(array $product): array
    {
        return [
            (string)$product['product_code'],
            (string)$product['product_name'],
            (string)$product['category_path'],
            (string)$product['logo'],
            (int)$product['status'],
            (int)$product['sort'],
            (string)$product['source'],
        ];
    }

    private function expandProduct(array $row): array
    {
        // 同时兼容尚未压缩的关联数组，便于灰度期间平滑读取。
        if (isset($row['product_code'])) {
            return $row;
        }

        return [
            'product_code' => (string)($row[0] ?? ''),
            'product_name' => (string)($row[1] ?? ''),
            'category_path' => (string)($row[2] ?? '快递'),
            'logo' => (string)($row[3] ?? ''),
            'status' => (int)($row[4] ?? 0),
            'sort' => (int)($row[5] ?? 0),
            'source' => (string)($row[6] ?? 'config'),
        ];
    }

    private function getDefaultProducts(string $provider): array
    {
        if ($provider !== ThirdPartyDict::PROVIDER_YISU) {
            return [];
        }

        return array_map(static function (array $product) use ($provider): array {
            return [
                'provider' => $provider,
                'product_code' => (string)($product['product_code'] ?? ''),
                'product_name' => (string)($product['product_name'] ?? ''),
                'category_path' => (string)($product['express_type'] ?? '快递'),
                'express_type' => (string)($product['express_type'] ?? '快递'),
                'logo' => (string)($product['logo'] ?? ''),
                'status' => 0,
                'sort' => 0,
                'source' => 'builtin',
            ];
        }, YisuProductDict::getProducts());
    }

    private function normalizeProductCollection(array $products, string $provider): array
    {
        $result = [];
        foreach ($products as $key => $product) {
            if (!is_array($product)) {
                continue;
            }
            if (empty($product['product_code']) && !is_int($key)) {
                $product['product_code'] = (string)$key;
            }
            $normalized = $this->normalizeProduct($product, $provider);
            if ($normalized['product_code'] !== '') {
                $result[] = $normalized;
            }
        }
        return $result;
    }

    private function normalizeProduct(array $product, string $provider): array
    {
        $categoryPath = trim((string)($product['category_path'] ?? $product['express_type'] ?? '快递'));
        $categoryPath = str_replace(['\\', '＞', '>'], '/', $categoryPath);
        $segments = array_values(array_filter(array_map('trim', explode('/', $categoryPath)), static fn(string $item): bool => $item !== ''));
        $categoryPath = mb_substr(implode('/', $segments ?: ['其他']), 0, 500);

        return [
            'provider' => $this->normalizeProvider((string)($product['provider'] ?? $provider)),
            'product_code' => mb_substr(trim((string)($product['product_code'] ?? '')), 0, 80),
            'product_name' => mb_substr(trim((string)($product['product_name'] ?? '')), 0, 200),
            'category_path' => $categoryPath,
            'express_type' => mb_substr((string)($segments[0] ?? '其他'), 0, 100),
            'logo' => mb_substr(trim((string)($product['logo'] ?? '')), 0, 500),
            'status' => (int)($product['status'] ?? $product['enabled'] ?? 0) === 1 ? 1 : 0,
            'sort' => max(0, (int)($product['sort'] ?? 0)),
            'source' => mb_substr(trim((string)($product['source'] ?? 'config')), 0, 40),
        ];
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = mb_substr(strtolower(trim($provider)), 0, 40);
        return $provider !== '' ? $provider : ThirdPartyDict::PROVIDER_YISU;
    }

    private function compareProducts(array $left, array $right): int
    {
        $pathCompare = strcmp((string)$left['category_path'], (string)$right['category_path']);
        if ($pathCompare !== 0) {
            return $pathCompare;
        }
        $sortCompare = (int)$left['sort'] <=> (int)$right['sort'];
        if ($sortCompare !== 0) {
            return $sortCompare;
        }
        return strnatcasecmp((string)$left['product_code'], (string)$right['product_code']);
    }

    private function buildTree(array $products): array
    {
        $nodes = [];
        foreach ($products as $product) {
            $segments = array_values(array_filter(array_map('trim', explode('/', (string)$product['category_path']))));
            $this->appendTreeNode($nodes, $segments ?: ['其他'], $product, 'root');
        }
        $this->sortTree($nodes);
        $this->countTreeProducts($nodes);
        return array_values($nodes);
    }

    private function appendTreeNode(array &$nodes, array $segments, array $product, string $parentKey): void
    {
        $label = (string)array_shift($segments);
        $key = 'category:' . sha1($parentKey . '/' . $label);
        if (!isset($nodes[$key])) {
            $nodes[$key] = [
                'key' => $key,
                'node_type' => 'category',
                'label' => $label,
                'children' => [],
                'product_count' => 0,
            ];
        }

        if (!empty($segments)) {
            $this->appendTreeNode($nodes[$key]['children'], $segments, $product, $key);
            return;
        }

        $productKey = 'product:' . $product['provider'] . ':' . $product['product_code'];
        $nodes[$key]['children'][$productKey] = array_merge($product, [
            'key' => $productKey,
            'node_type' => 'product',
            'label' => $product['product_name'],
            'children' => [],
        ]);
    }

    private function sortTree(array &$nodes): void
    {
        uasort($nodes, static function (array $left, array $right): int {
            if ($left['node_type'] !== $right['node_type']) {
                return $left['node_type'] === 'category' ? -1 : 1;
            }
            if ($left['node_type'] === 'product') {
                $sortCompare = (int)$left['sort'] <=> (int)$right['sort'];
                if ($sortCompare !== 0) {
                    return $sortCompare;
                }
                return strnatcasecmp((string)$left['product_code'], (string)$right['product_code']);
            }
            return strcmp((string)$left['label'], (string)$right['label']);
        });
        foreach ($nodes as &$node) {
            if (!empty($node['children'])) {
                $this->sortTree($node['children']);
                $node['children'] = array_values($node['children']);
            }
        }
        unset($node);
    }

    private function countTreeProducts(array &$nodes): int
    {
        $count = 0;
        foreach ($nodes as &$node) {
            if ($node['node_type'] === 'product') {
                $count++;
                continue;
            }
            $node['product_count'] = $this->countTreeProducts($node['children']);
            $count += $node['product_count'];
        }
        unset($node);
        return $count;
    }
}
