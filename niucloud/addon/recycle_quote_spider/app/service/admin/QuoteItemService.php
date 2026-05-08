<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\admin;

use addon\recycle_quote_spider\app\model\QuoteItem;
use addon\recycle_quote_spider\app\model\QuoteRow;
use addon\recycle_quote_spider\app\service\core\QuoteApiCacheService;
use addon\recycle_quote_spider\app\service\core\QuotePriceCalculator;
use core\base\BaseAdminService;
use core\exception\CommonException;

class QuoteItemService extends BaseAdminService
{
    private const DEFAULT_NOTICE_TEXT = '温馨提示：报价仅供参考，最终价格以质检结果为准';

    private QuotePriceCalculator $calculator;

    public function __construct()
    {
        parent::__construct();
        $this->model = new QuoteItem();
        $this->calculator = new QuotePriceCalculator();
    }

    public function getPage(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $search = $this->model
            ->withSearch([
                'site_id',
                'source_id',
                'is_show',
                'is_hot',
                'follow_source',
                'has_update',
                'is_image_quote',
                'brand',
                'tab',
                'quote_type',
                'keyword',
            ], $where)
            ->order('sort asc,id desc');
        $this->applyCategoryScope($search, $where);
        return $this->pageQuery($search);
    }

    public function getFilterOptions(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $base = $this->model->withSearch(['site_id', 'source_id'], $where);
        $this->applyCategoryScope($base, $where);
        return [
            'brands' => $this->columnOptions(clone $base, 'brand'),
            'tabs' => $this->columnOptions(clone $base, 'tab'),
            'quote_types' => $this->columnOptions(clone $base, 'quote_type'),
        ];
    }

    public function getInfo(int $id): array
    {
        $info = $this->model->where('site_id', $this->site_id)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($info)) {
            throw new CommonException('报价项不存在');
        }
        return $info;
    }

    public function addItem(array $data): int
    {
        $sourceId = (int)($data['source_id'] ?? 0);
        $categoryId = (int)($data['category_id'] ?? 0);
        $name = trim((string)($data['name'] ?? ''));
        if ($sourceId <= 0) {
            throw new CommonException('请选择报价源');
        }
        if ($categoryId <= 0) {
            throw new CommonException('请选择分类');
        }
        if ($name === '') {
            throw new CommonException('请输入报价项名称');
        }

        $columns = $this->normalizeList($data['columns'] ?? []);
        $isImageQuote = (int)($data['is_image_quote'] ?? 0);
        $record = $this->model->create([
            'site_id' => $this->site_id,
            'source_id' => $sourceId,
            'category_id' => $categoryId,
            'source_item_id' => 'manual_' . uniqid('', true),
            'source_url_id' => '',
            'brand' => (string)($data['brand'] ?? ''),
            'tab' => (string)($data['tab'] ?? ''),
            'name' => $name,
            'parent_name' => '',
            'quote_type' => (string)($data['quote_type'] ?? 'manual'),
            'is_image_quote' => $isImageQuote,
            'image' => (string)($data['image'] ?? ''),
            'timage' => (string)($data['timage'] ?? ''),
            'bimage' => (string)($data['bimage'] ?? ''),
            'icon' => (string)($data['icon'] ?? ''),
            'notice_text' => $this->normalizeNoticeText($data['notice_text'] ?? ''),
            'keywords' => (string)($data['keywords'] ?? ''),
            'source_is_show' => 1,
            'source_is_hot' => 0,
            'is_show' => (int)($data['is_show'] ?? 1),
            'is_hot' => (int)($data['is_hot'] ?? 0),
            'sort' => (int)($data['sort'] ?? 0),
            'follow_source' => (int)($data['follow_source'] ?? 0),
            'columns' => $columns,
            'raw_data' => ['manual' => true],
            'source_hash' => md5($name . microtime(true)),
            'last_sync_at' => time(),
        ]);
        $this->refreshApiCache();
        return (int)$record->id;
    }

    public function rows(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $search = (new QuoteRow())
            ->withSearch([
                'site_id',
                'source_id',
                'item_id',
                'brand',
                'tab',
                'is_show',
                'follow_source',
                'has_update',
                'keyword',
            ], $where)
            ->order('sort asc,id asc');
        return $this->pageQuery($search, function ($row) {
            return $this->appendRowDisplayFields($row);
        });
    }

    public function addRow(array $data): int
    {
        $itemId = (int)($data['item_id'] ?? 0);
        $modelName = trim((string)($data['model_name'] ?? ''));
        if ($itemId <= 0) {
            throw new CommonException('请选择报价项');
        }
        if ($modelName === '') {
            throw new CommonException('请输入型号');
        }

        $item = $this->getInfo($itemId);
        $columns = $this->normalizeList($data['columns'] ?? ($item['columns'] ?? []));
        $manualPrices = $this->normalizeList($data['manual_prices'] ?? []);
        $record = (new QuoteRow())->create([
            'site_id' => $this->site_id,
            'source_id' => (int)($data['source_id'] ?? $item['source_id']),
            'item_id' => $itemId,
            'source_row_id' => 'manual_' . md5($modelName . microtime(true)),
            'brand' => (string)($data['brand'] ?? $item['brand'] ?? ''),
            'tab' => (string)($data['tab'] ?? $item['tab'] ?? ''),
            'model_name' => $modelName,
            'keywords' => (string)($data['keywords'] ?? ''),
            'columns' => $columns,
            'source_prices' => $manualPrices,
            'manual_prices' => $manualPrices,
            'final_prices' => $manualPrices,
            'remark' => (string)($data['remark'] ?? ''),
            'source_is_show' => 1,
            'is_show' => (int)($data['is_show'] ?? 1),
            'sort' => (int)($data['sort'] ?? 0),
            'follow_source' => (int)($data['follow_source'] ?? 0),
            'adjust_type' => 0,
            'adjust_value' => 0,
            'adjust_ratio' => 1,
            'round_mode' => 'round',
            'raw_data' => ['manual' => true],
            'source_hash' => md5($modelName . json_encode($manualPrices, JSON_UNESCAPED_UNICODE)),
        ]);
        $this->refreshApiCache();
        return (int)$record->id;
    }

    public function editItem(int $id, array $data): bool
    {
        $this->getInfo($id);
        $save = $this->buildAdjustSave($data, ['is_show', 'is_hot', 'sort', 'follow_source']);
        foreach (['name', 'brand', 'tab', 'keywords', 'quote_type', 'image', 'timage', 'bimage', 'icon'] as $field) {
            if (array_key_exists($field, $data)) {
                $save[$field] = (string)$data[$field];
            }
        }
        if (array_key_exists('notice_text', $data)) {
            $save['notice_text'] = $this->normalizeNoticeText($data['notice_text']);
        }
        if (array_key_exists('is_image_quote', $data) && $data['is_image_quote'] !== '') {
            $save['is_image_quote'] = (int)$data['is_image_quote'];
        }
        if (!empty($save)) {
            $this->model->where('site_id', $this->site_id)->where('id', $id)->update($save);
        }

        if ($this->hasAdjustRule($data)) {
            $rows = (new QuoteRow())->where('site_id', $this->site_id)->where('item_id', $id)->select()->toArray();
            foreach ($rows as $row) {
                $rule = array_merge($row, $save);
                $finalPrices = $this->calculator->calculateList($row['source_prices'] ?? [], $rule);
                (new QuoteRow())->where('id', $row['id'])->update(['final_prices' => $finalPrices]);
            }
        }
        $this->refreshApiCache();
        return true;
    }

    public function editRow(int $id, array $data): bool
    {
        $model = new QuoteRow();
        $row = $model->where('site_id', $this->site_id)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($row)) {
            throw new CommonException('报价行不存在');
        }

        $save = $this->buildAdjustSave($data, ['follow_source', 'is_show', 'sort']);
        foreach (['model_name', 'brand', 'tab', 'keywords', 'remark'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '') {
                $save[$field] = (string)$data[$field];
            }
        }
        if (isset($data['manual_prices']) && is_array($data['manual_prices'])) {
            $save['manual_prices'] = $data['manual_prices'];
        }

        $rule = array_merge($row, $save);
        if ((int)($rule['follow_source'] ?? 1) === 1) {
            $save['final_prices'] = $this->calculator->calculateList($row['source_prices'] ?? [], $rule);
        } elseif (isset($save['manual_prices'])) {
            $save['final_prices'] = $save['manual_prices'];
        }

        if (!empty($save)) {
            $model->where('id', $id)->update($save);
        }
        $this->refreshApiCache();
        return true;
    }

    private function refreshApiCache(): void
    {
        (new QuoteApiCacheService())->refresh($this->site_id);
    }

    private function appendRowDisplayFields($row)
    {
        $rawData = is_array($row) ? ($row['raw_data'] ?? []) : ($row->raw_data ?? []);
        $tab = is_array($row) ? (string)($row['tab'] ?? '') : (string)($row->tab ?? '');
        $capacityName = $this->extractCapacityName($rawData, $tab);
        if (is_array($row)) {
            $row['capacity_name'] = $capacityName;
            $row['capacity'] = $capacityName;
            return $row;
        }
        $row->setAttr('capacity_name', $capacityName);
        $row->setAttr('capacity', $capacityName);
        return $row;
    }

    private function extractCapacityName($rawData, string $tab = ''): string
    {
        $raw = is_array($rawData) ? $rawData : [];
        $candidates = [
            $raw['内存'] ?? null,
            $raw['容量'] ?? null,
            $raw['规格'] ?? null,
            $raw['存储'] ?? null,
            $raw['capacity_name'] ?? null,
            $raw['capacity'] ?? null,
            $raw['memory'] ?? null,
            $raw['storage'] ?? null,
            $raw['rom'] ?? null,
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

    private function normalizeNoticeText($value): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim((string)$value));
        return $text !== '' ? $text : self::DEFAULT_NOTICE_TEXT;
    }

    private function buildAdjustSave(array $data, array $intFields = []): array
    {
        $save = [];
        foreach ($intFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '') {
                $save[$field] = (int)$data[$field];
            }
        }
        foreach (['adjust_type', 'adjust_value', 'adjust_ratio', 'round_mode'] as $field) {
            if (!array_key_exists($field, $data) || $data[$field] === '') {
                continue;
            }
            $save[$field] = $field === 'round_mode' ? (string)$data[$field] : (float)$data[$field];
        }
        return $save;
    }

    private function hasAdjustRule(array $data): bool
    {
        return array_key_exists('adjust_type', $data)
            || array_key_exists('adjust_value', $data)
            || array_key_exists('adjust_ratio', $data)
            || array_key_exists('round_mode', $data);
    }

    private function normalizeList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values($decoded);
            }
            return $value === '' ? [] : array_map('trim', explode(',', $value));
        }
        if (is_array($value)) {
            return array_values($value);
        }
        return $value === null ? [] : [$value];
    }

    private function columnOptions($query, string $field): array
    {
        return array_values(array_filter($query
            ->where($field, '<>', '')
            ->group($field)
            ->order($field)
            ->column($field), fn($value) => $value !== '' && $value !== null));
    }

    private function applyCategoryScope($query, array $where): void
    {
        $categoryIds = $where['category_ids'] ?? [];
        if (is_string($categoryIds)) {
            $categoryIds = $categoryIds === '' ? [] : explode(',', $categoryIds);
        }
        if (is_array($categoryIds)) {
            $categoryIds = array_values(array_filter(array_unique(array_map('intval', $categoryIds))));
        } else {
            $categoryIds = [];
        }

        if (!empty($categoryIds)) {
            $query->whereIn('category_id', $categoryIds);
            return;
        }

        if (($where['category_id'] ?? '') !== '' && $where['category_id'] !== null) {
            $query->where('category_id', '=', (int)$where['category_id']);
        }
    }
}
