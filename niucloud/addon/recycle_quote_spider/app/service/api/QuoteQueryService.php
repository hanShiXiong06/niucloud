<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\api;

use addon\recycle_quote_spider\app\model\QuoteCategory;
use addon\recycle_quote_spider\app\model\QuoteItem;
use addon\recycle_quote_spider\app\model\QuoteRow;
use addon\recycle_quote_spider\app\model\QuoteSource;
use addon\recycle_quote_spider\app\service\core\QuoteApiCacheService;
use addon\recycle_quote_spider\app\service\core\QuotePriceHistoryService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Db;

class QuoteQueryService extends BaseApiService
{
    private const DEFAULT_NOTICE_TEXT = '温馨提示：报价仅供参考，最终价格以质检结果为准';

    public function sources(): array
    {
        return (new QuoteApiCacheService())->remember($this->site_id, 'sources', [], function () {
            return $this->buildSources();
        });
    }

    private function buildSources(): array
    {
        return (new QuoteSource())->where('site_id', $this->site_id)
            ->where('status', 1)
            ->field('id,source_name,provider,last_sync_at,last_status,last_error,update_at')
            ->order('id desc')
            ->select()
            ->toArray();
    }

    public function featured(array $where = []): array
    {
        return (new QuoteApiCacheService())->remember($this->site_id, 'featured', $this->normalizeCacheWhere($where), function () use ($where) {
            return $this->buildFeatured($where);
        });
    }

    private function buildFeatured(array $where = []): array
    {
        $limit = (int)($where['limit'] ?? 10);

        $query = (new QuoteItem())->where('site_id', $this->site_id)->where('is_show', 1);
        if (!empty($where['source_id'])) {
            $query->where('source_id', (int)$where['source_id']);
        }
        $this->applyCategoryScope($query, (int)($where['category_id'] ?? 0));
        if (!empty($where['keyword'])) {
            $query->whereLike('name|brand|tab|keywords|parent_name', '%' . trim((string)$where['keyword']) . '%');
        }
        if ($where['only_hot'] !== '' && $where['only_hot'] !== null) {
            $query->where('is_hot', (int)$where['only_hot']);
        }

        $query->field('id,source_id,category_id,brand,tab,name,parent_name,quote_type,is_image_quote,image,timage,bimage,icon,notice_text,is_hot,sort,last_sync_at,update_at')
            ->order('is_hot desc,sort asc,id asc');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $items = $query->select()->toArray();

        if (empty($items)) {
            return [];
        }

        $itemIds = array_map(static fn($item) => (int)$item['id'], $items);
        $rowCountMap = $this->countRowsByItem($itemIds);
        $categoryPathMap = $this->getCategoryPathMap(array_unique(array_map(static fn($item) => (int)$item['category_id'], $items)));

        foreach ($items as &$item) {
            $this->sanitizeItemImages($item);
            $item['notice_text'] = $this->resolveNoticeText($item['notice_text'] ?? '');
            $item['title'] = $this->formatItemTitle($item);
            $item['category_path'] = (string)($categoryPathMap[(int)$item['category_id']] ?? '');
            $item['model_count'] = (int)($rowCountMap[(int)$item['id']] ?? 0);
            $item['last_sync_at_text'] = $this->formatTime((int)($item['last_sync_at'] ?: $item['update_at']));
        }
        unset($item);

        return $items;
    }

    public function categoryTree(array $where = []): array
    {
        return (new QuoteApiCacheService())->remember($this->site_id, 'category_tree', $this->normalizeCacheWhere($where), function () use ($where) {
            return $this->buildCategoryTree($where);
        });
    }

    private function buildCategoryTree(array $where = []): array
    {
        $query = (new QuoteCategory())->where('site_id', $this->site_id)->where('is_show', 1);
        if (!empty($where['source_id'])) {
            $query->where('source_id', (int)$where['source_id']);
        }
        $list = $query->order('sort desc,id desc')->select()->toArray();
        return $this->buildTree($list);
    }

    public function items(array $where = []): array
    {
        return (new QuoteApiCacheService())->remember($this->site_id, 'items', $this->normalizeCacheWhere($where), function () use ($where) {
            return $this->buildItems($where);
        });
    }

    private function buildItems(array $where = []): array
    {
        $query = (new QuoteItem())->where('site_id', $this->site_id)->where('is_show', 1);
        if (!empty($where['source_id'])) {
            $query->where('source_id', (int)$where['source_id']);
        }
        $this->applyCategoryScope($query, (int)($where['category_id'] ?? 0));
        if (!empty($where['keyword'])) {
            $query->whereLike('name|brand|tab|keywords|parent_name', '%' . $where['keyword'] . '%');
        }
        $siteId = $this->site_id;
        return $this->pageQuery($query->order('is_hot desc,sort desc,id desc'), function ($item) use ($siteId) {
            $item['model_count'] = (new QuoteRow())->where('site_id', $siteId)->where('is_show', 1)->where('item_id', (int)$item['id'])->count();
            return $item;
        });
    }

    public function detail(int $id): array
    {
        // 浏览量埋点：每次访问 +1，放在缓存外，避免被详情缓存挡掉
        $this->incrementViewCount($id);
        return (new QuoteApiCacheService())->remember($this->site_id, 'detail', ['id' => $id, 'row_merge' => 1], function () use ($id) {
            return $this->buildDetail($id);
        });
    }

    private function incrementViewCount(int $id): void
    {
        try {
            // 用查询构造器直接自增，避免模型 autoWriteTimestamp 每次浏览都改 update_at
            Db::name('recycle_quote_spider_item')
                ->where('site_id', $this->site_id)
                ->where('id', $id)
                ->where('is_show', 1)
                ->inc('view_count')
                ->update();
        } catch (\Throwable $e) {
            // 埋点失败不影响详情返回
        }
    }

    /**
     * 生成报价单权限：会员需开通「报价单生成」权益(level_benefits.quote_report.is_use)
     */
    public function reportPermission(): array
    {
        try {
            if (empty($this->member_id)) {
                return ['allowed' => 0];
            }
            $member = (new Member())
                ->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])
                ->field('member_level')
                ->with([
                    'memberLevelData' => function ($query) {
                        $query->field('level_id, site_id, level_name, status, level_benefits, level_gifts');
                    }
                ])
                ->findOrEmpty()
                ->toArray();
            $allowed = !empty($member['memberLevelData']['level_benefits']['quote_report']['is_use']) ? 1 : 0;
            return ['allowed' => $allowed];
        } catch (\Throwable $e) {
            return ['allowed' => 0];
        }
    }

    public function priceHistory(int $rowId, int $days): array
    {
        return (new QuoteApiCacheService())->remember(
            $this->site_id,
            'price_history',
            ['id' => $rowId, 'days' => $days],
            fn() => (new QuotePriceHistoryService())->series($this->site_id, $rowId, $days)
        );
    }

    private function buildDetail(int $id): array
    {
        $item = (new QuoteItem())->where('site_id', $this->site_id)->where('is_show', 1)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($item)) {
            throw new CommonException('报价不存在');
        }
        $rows = (new QuoteRow())->where('site_id', $this->site_id)->where('item_id', $id)->where('is_show', 1)->order('sort asc,id asc')->select()->toArray();
        $this->sanitizeItemImages($item);
        $item['notice_text'] = $this->resolveNoticeText($item['notice_text'] ?? '');
        // 报价更新时间（取最近同步时间，回退到更新时间），给 C 端展示用
        $itemUpdated = $this->resolveTimestamp($item['last_sync_at'] ?? 0) ?: $this->resolveTimestamp($item['update_at'] ?? 0);
        $item['update_at_text'] = $itemUpdated ? $this->formatTime($itemUpdated) : '';
        $item['price_date'] = $itemUpdated ? date('Y-m-d', $itemUpdated) : '';
        // 今天 vs 昨天涨跌对比所需的上一次价格
        $prevMap = (new QuotePriceHistoryService())->previousMap($this->site_id, array_column($rows, 'id'));
        foreach ($rows as &$row) {
            $this->appendRowDisplayFields($row);
            $row['prev_final_prices'] = $prevMap[(int)($row['id'] ?? 0)] ?? [];
            $rowUpdated = $this->resolveTimestamp($row['update_at'] ?? 0) ?: $this->resolveTimestamp($row['create_at'] ?? 0);
            $row['update_at_text'] = $rowUpdated ? $this->formatTime($rowUpdated) : '';
            $row['price_date'] = $rowUpdated ? date('Y-m-d', $rowUpdated) : '';
        }
        unset($row);
        $this->appendRowMergeFields($rows);
        $item['rows'] = $rows;
        return $item;
    }

    private function normalizeCacheWhere(array $where): array
    {
        $result = [];
        foreach ($where as $key => $value) {
            if (is_array($value)) {
                $value = array_values($value);
                sort($value);
            } elseif (is_string($value)) {
                $value = trim($value);
            }
            $result[$key] = $value;
        }
        ksort($result);
        return $result;
    }

    private function countRowsByItem(array $itemIds): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $rows = (new QuoteRow())->where('site_id', $this->site_id)
            ->where('is_show', 1)
            ->where('item_id', 'in', $itemIds)
            ->field('item_id,COUNT(*) as total')
            ->group('item_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['item_id']] = (int)$row['total'];
        }
        return $map;
    }

    private function appendRowDisplayFields(array &$row): void
    {
        $capacityName = $this->extractCapacityName($row['raw_data'] ?? [], (string)($row['tab'] ?? ''));
        $row['capacity_name'] = $capacityName;
        $row['capacity'] = $capacityName;
        if (trim((string)($row['remark'] ?? '')) === '') {
            $remark = $this->extractRemarkText($row['raw_data'] ?? []);
            if ($remark !== '') {
                $row['remark'] = $remark;
            }
        }
    }

    private function appendRowMergeFields(array &$rows): void
    {
        $count = count($rows);
        for ($index = 0; $index < $count; $index++) {
            $rows[$index]['remark_columns'] = [];
            $rows[$index]['remark_values'] = [];
            $rows[$index]['remark_rowspan'] = 1;
            $rows[$index]['remark_hidden'] = 0;
        }

        $index = 0;
        while ($index < $count) {
            $modelName = trim((string)($rows[$index]['model_name'] ?? ''));
            $end = $index + 1;
            while ($end < $count && trim((string)($rows[$end]['model_name'] ?? '')) === $modelName) {
                $end++;
            }

            $remark = '';
            for ($cursor = $index; $cursor < $end; $cursor++) {
                $candidate = trim((string)($rows[$cursor]['remark'] ?? ''));
                if ($candidate !== '') {
                    $remark = $candidate;
                    break;
                }
            }

            if ($remark !== '') {
                $span = $end - $index;
                for ($cursor = $index; $cursor < $end; $cursor++) {
                    if (trim((string)($rows[$cursor]['remark'] ?? '')) === '') {
                        $rows[$cursor]['remark'] = $remark;
                    }
                    $rows[$cursor]['remark_columns'] = ['备注'];
                    $rows[$cursor]['remark_values'] = [$remark];
                    $rows[$cursor]['remark_rowspan'] = $cursor === $index ? $span : 0;
                    $rows[$cursor]['remark_hidden'] = $cursor === $index ? 0 : 1;
                }
            }

            $index = $end;
        }
    }

    private function extractRemarkText($rawData): string
    {
        $raw = is_array($rawData) ? $rawData : [];
        foreach (['备注', '说明', '描述', 'remark', 'remark_text', 'note', 'notes', 'content_text', 'text'] as $field) {
            $value = trim((string)($raw[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }
        return '';
    }

    private function extractCapacityName($rawData, string $tab = ''): string
    {
        $raw = is_array($rawData) ? $rawData : [];
        $candidates = [
            $raw['内存'] ?? null,
            $raw['容量'] ?? null,
            $raw['规格'] ?? null,
            $raw['尺寸'] ?? null,
            $raw['表径'] ?? null,
            $raw['表壳'] ?? null,
            $raw['尺码'] ?? null,
            $raw['存储'] ?? null,
            $raw['capacity_name'] ?? null,
            $raw['capacity'] ?? null,
            $raw['memory'] ?? null,
            $raw['storage'] ?? null,
            $raw['rom'] ?? null,
            $raw['size'] ?? null,
            $raw['case_size'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            $value = trim((string)$candidate);
            if ($this->isValidCapacityValue($value, $tab)) {
                return $value;
            }
        }
        return '';
    }

    private function isValidCapacityValue(string $value, string $tab = ''): bool
    {
        if ($value === '') {
            return false;
        }
        if ($tab !== '' && $value === trim($tab)) {
            return false;
        }
        return !str_contains($value, '分组') && !str_contains($value, '系列');
    }

    private function formatItemTitle(array $item): string
    {
        return trim((string)($item['name'] ?? '')) ?: '报价单';
    }

    private function sanitizeItemImages(array &$item): void
    {
        foreach (['image', 'timage', 'bimage', 'icon'] as $field) {
            if ($this->isThirdPartyImageUrl((string)($item[$field] ?? ''))) {
                $item[$field] = '';
            }
        }
    }

    private function resolveNoticeText($value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim((string)$value));
        return $text !== '' ? $text : self::DEFAULT_NOTICE_TEXT;
    }

    private function isThirdPartyImageUrl(string $url): bool
    {
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            return false;
        }

        $requestHost = strtolower((string)(request()->host() ?: ''));
        if ($requestHost !== '' && $host === $requestHost) {
            return false;
        }

        return str_contains($host, 'ycdongxu.com');
    }

    private function getCategoryPathMap(array $categoryIds): array
    {
        $categoryIds = array_values(array_unique(array_filter(array_map('intval', $categoryIds))));
        if (empty($categoryIds)) {
            return [];
        }

        $categories = (new QuoteCategory())->where('site_id', $this->site_id)
            ->field('id,parent_id,name')
            ->select()
            ->toArray();
        $categoryMap = [];
        foreach ($categories as $category) {
            $categoryMap[(int)$category['id']] = $category;
        }

        $pathMap = [];
        foreach ($categoryIds as $categoryId) {
            $pathMap[$categoryId] = $this->buildCategoryPath($categoryId, $categoryMap);
        }
        return $pathMap;
    }

    private function buildCategoryPath(int $categoryId, array $categoryMap): string
    {
        $names = [];
        $visited = [];
        $currentId = $categoryId;
        while ($currentId > 0 && isset($categoryMap[$currentId]) && !isset($visited[$currentId])) {
            $visited[$currentId] = true;
            array_unshift($names, trim((string)($categoryMap[$currentId]['name'] ?? '')));
            $currentId = (int)($categoryMap[$currentId]['parent_id'] ?? 0);
        }
        $names = array_values(array_filter($names, static fn($name) => $name !== ''));
        return implode(' / ', $names);
    }

    private function formatTime(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '待同步';
        }
        return date('Y-m-d H:i', $timestamp);
    }

    /**
     * 兼容 int 时间戳与 'Y-m-d H:i:s' 字符串（模型自动时间字段会被格式化成字符串）
     */
    private function resolveTimestamp($value): int
    {
        if (is_numeric($value)) {
            return (int)$value;
        }
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }
        $ts = strtotime($value);
        return $ts ?: 0;
    }

    private function buildTree(array $list, int $parentId = 0): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int)$item['parent_id'] !== $parentId) {
                continue;
            }
            $item['children'] = $this->buildTree($list, (int)$item['id']);
            $tree[] = $item;
        }
        return $tree;
    }

    private function applyCategoryScope($query, int $categoryId): void
    {
        if ($categoryId <= 0) {
            return;
        }

        $categories = (new QuoteCategory())->where('site_id', $this->site_id)
            ->field('id,parent_id')
            ->select()
            ->toArray();
        $categoryIds = $this->collectChildCategoryIds($categories, $categoryId);
        if (empty($categoryIds)) {
            $categoryIds = [$categoryId];
        }
        $query->where('category_id', 'in', $categoryIds);
    }

    private function collectChildCategoryIds(array $categories, int $parentId): array
    {
        $ids = [$parentId];
        foreach ($categories as $category) {
            if ((int)($category['parent_id'] ?? 0) !== $parentId) {
                continue;
            }
            $ids = array_merge($ids, $this->collectChildCategoryIds($categories, (int)$category['id']));
        }
        return array_values(array_unique(array_filter($ids)));
    }
}
