<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

final class SoujikeProvider extends AbstractProvider
{
    public const STOCK_ENDPOINT = 'https://www.dianddnet.cn/sjk-erp-service/api/sku/homePage';
    private const SNAPSHOT_KEYS = ['id', 'productName', 'categoryName', 'capacity', 'color', 'brandType',
        'watchNetworkMode', 'watchCaseSize', 'watchCaseMaterial', 'watchBand', 'fineness', 'retailPrice',
        'tradePrice', 'createTime', 'brandName', 'phoneVersion', 'battery', 'isNew', 'imei', 'renovateName',
        'renovateDesc', 'firstResIsVideo'];

    public function key(): string { return 'soujike'; }

    public function login(#[\SensitiveParameter] array $credentials): array
    {
        // This provider only accepts a user-provided token; no undocumented login request.
        return ['access_token' => $this->accessToken($credentials), 'identity' => $this->identity($credentials)];
    }

    public function fetchPage(#[\SensitiveParameter] array $credentials, array $cursor, array $scope): array
    {
        [$pageNum, $pageSize] = $this->pagination($cursor, $scope);
        $store = $this->id($scope['store_id'] ?? null, '搜机客来源门店标识');
        $body = ['current' => $pageNum, 'size' => $pageSize, 'showHome' => false, 'fromShare' => false, 'storeId' => $store,
            'brandType' => '', 'brandIds' => [], 'categoryIds' => [], 'color' => '', 'capacity' => '', 'searchKey' => '',
            'sortFieldName' => '', 'sortOrderType' => '', 'minRetailPrice' => '', 'maxRetailPrice' => '', 'isNew' => null];
        $page = $this->transport->post(self::STOCK_ENDPOINT, ['recovery-token' => $this->accessToken($credentials)], $body);
        $this->assertBusinessSuccess($page);
        $data = $page['data'] ?? null;
        if (!is_array($data) || !is_array($data['records'] ?? null) || !array_is_list($data['records']) ||
            (string) ($data['current'] ?? '') !== (string) $pageNum || (string) ($data['size'] ?? '') !== (string) $pageSize ||
            (!is_string($data['total'] ?? null) && !is_int($data['total'] ?? null)) ||
            !preg_match('/^(?:0|[1-9][0-9]{0,7})$/D', (string) $data['total'])) {
            throw new ProviderException('schema_changed', '搜机客库存分页结构变化，已停止同步');
        }
        $total = (int) $data['total'];
        $expected = min($pageSize, max(0, $total - ($pageNum - 1) * $pageSize));
        if (count($data['records']) !== $expected) {
            throw new ProviderException('schema_changed', '搜机客分页结果不完整，已停止同步');
        }
        $rows = [];
        $seen = [];
        foreach ($data['records'] as $row) {
            if (!is_array($row)) throw new ProviderException('schema_changed', '搜机客库存记录格式变化');
            $id = $this->id($row['id'] ?? null, '搜机客商品标识');
            if (isset($seen[$id])) throw new ProviderException('schema_changed', '搜机客单页出现重复商品，已停止同步');
            $seen[$id] = true;
            $rows[] = $this->safeSnapshot($row);
        }
        return ['rows' => $rows, 'has_more' => $pageNum * $pageSize < $total, 'cursor' => ['page' => $pageNum + 1]];
    }

    public function normalize(array $row, array $context): array
    {
        $safe = $this->safeSnapshot($row);
        return $this->common($safe, $context, [
            'id' => $row['id'] ?? null, 'store_id' => $context['store_id'] ?? null,
            'tenant_id' => $context['tenant_id'] ?? null,
            'title' => $this->text($row['categoryName'] ?? null) ?? $row['productName'] ?? null, 'brand' => $row['brandName'] ?? null,
            'capacity' => $row['capacity'] ?? null, 'color' => $row['color'] ?? null,
            'retail_price' => $row['retailPrice'] ?? null, 'trade_price' => $row['tradePrice'] ?? null,
            'imei' => $row['imei'] ?? null,
            'images' => ($row['firstResIsVideo'] ?? null) === false ? [$safe['coverImage']] : [],
            'source_status' => [],
        ]);
    }

    private function safeSnapshot(array $row): array
    {
        $safe = [];
        foreach (self::SNAPSHOT_KEYS as $key) {
            if (array_key_exists($key, $row)) $safe[$key] = $this->scalar($row[$key]);
        }
        // A video URL is not a usable photo. No detail endpoint or media download is performed.
        $safe['coverImage'] = ($row['firstResIsVideo'] ?? null) === false ? $this->url($row['coverImage'] ?? null) : null;
        return $safe;
    }
}
