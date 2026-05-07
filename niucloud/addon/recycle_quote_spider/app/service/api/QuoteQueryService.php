<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\api;

use addon\recycle_quote_spider\app\model\QuoteCategory;
use addon\recycle_quote_spider\app\model\QuoteItem;
use addon\recycle_quote_spider\app\model\QuoteRow;
use addon\recycle_quote_spider\app\model\QuoteSource;
use core\base\BaseApiService;
use core\exception\CommonException;

class QuoteQueryService extends BaseApiService
{
    public function sources(): array
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
        $limit = (int)($where['limit'] ?? 10);
        if ($limit <= 0) {
            $limit = 100;
        } elseif ($limit > 100) {
            $limit = 100;
        }

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

        $items = $query->field('id,source_id,category_id,brand,tab,name,parent_name,quote_type,is_image_quote,image,timage,bimage,icon,is_hot,sort,last_sync_at,update_at')
            ->order('is_hot desc,sort desc,id desc')
            ->limit($limit)
            ->select()
            ->toArray();

        if (empty($items)) {
            return [];
        }

        $itemIds = array_map(static fn($item) => (int)$item['id'], $items);
        $rowCountMap = $this->countRowsByItem($itemIds);
        $categoryPathMap = $this->getCategoryPathMap(array_unique(array_map(static fn($item) => (int)$item['category_id'], $items)));

        foreach ($items as &$item) {
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
        $query = (new QuoteCategory())->where('site_id', $this->site_id)->where('is_show', 1);
        if (!empty($where['source_id'])) {
            $query->where('source_id', (int)$where['source_id']);
        }
        $list = $query->order('sort asc,id asc')->select()->toArray();
        return $this->buildTree($list);
    }

    public function items(array $where = []): array
    {
        $query = (new QuoteItem())->where('site_id', $this->site_id)->where('is_show', 1);
        if (!empty($where['source_id'])) {
            $query->where('source_id', (int)$where['source_id']);
        }
        $this->applyCategoryScope($query, (int)($where['category_id'] ?? 0));
        if (!empty($where['keyword'])) {
            $query->whereLike('name|brand|tab|keywords|parent_name', '%' . $where['keyword'] . '%');
        }
        return $this->pageQuery($query->order('is_hot desc,sort desc,id desc'));
    }

    public function detail(int $id): array
    {
        $item = (new QuoteItem())->where('site_id', $this->site_id)->where('is_show', 1)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($item)) {
            throw new CommonException('报价不存在');
        }
        $rows = (new QuoteRow())->where('site_id', $this->site_id)->where('item_id', $id)->where('is_show', 1)->order('sort asc,id asc')->select()->toArray();
        $item['rows'] = $rows;
        return $item;
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

    private function formatItemTitle(array $item): string
    {
        return trim((string)($item['name'] ?? '')) ?: '报价单';
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
