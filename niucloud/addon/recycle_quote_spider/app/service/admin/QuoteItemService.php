<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\admin;

use addon\recycle_quote_spider\app\model\QuoteItem;
use addon\recycle_quote_spider\app\model\QuoteCategory;
use addon\recycle_quote_spider\app\model\QuotePriceHistory;
use addon\recycle_quote_spider\app\model\QuoteRow;
use addon\recycle_quote_spider\app\model\QuoteSource;
use addon\recycle_quote_spider\app\service\core\QuoteApiCacheService;
use addon\recycle_quote_spider\app\service\core\QuotePriceCalculator;
use addon\recycle_quote_spider\app\service\core\QuotePriceHistoryService;
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
                'create_at_start',
                'create_at_end',
            ], $where)
            ->order($this->resolveItemOrder($where));
        $this->applyCategoryScope($search, $where);
        $page = $this->pageQuery($search);
        $this->appendItemStatistics($page['data']);
        return $page;
    }

    public function summary(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $itemQuery = $this->model->withSearch(['site_id', 'source_id'], $where);
        $this->applyCategoryScope($itemQuery, $where);
        $itemIds = array_values(array_unique(array_map('intval', (clone $itemQuery)->column('id'))));

        $sourceQuery = (new QuoteSource())->where('site_id', $this->site_id);
        if (($where['source_id'] ?? '') !== '' && $where['source_id'] !== null) {
            $sourceQuery->where('id', '=', (int)$where['source_id']);
        }
        $rowQuery = (new QuoteRow())->where('site_id', $this->site_id)->whereIn('item_id', $itemIds ?: [0]);
        $rowTable = (new QuoteRow())->getTable();
        $historyQuery = (new QuotePriceHistory())->alias('h')
            ->join($rowTable . ' r', 'r.id = h.row_id AND r.site_id = h.site_id')
            ->where('h.site_id', '=', $this->site_id)
            ->whereIn('h.item_id', $itemIds ?: [0]);
        $historyStats = (clone $historyQuery)->field([
            'COUNT(DISTINCT h.row_id) as history_row_count',
            'COUNT(DISTINCT h.record_date) as snapshot_day_count',
            'MAX(h.record_date) as latest_record_date',
        ])->findOrEmpty()->toArray();

        return [
            'source_count' => (int)(clone $sourceQuery)->count(),
            'enabled_source_count' => (int)(clone $sourceQuery)->where('status', '=', 1)->count(),
            'item_count' => (int)(clone $itemQuery)->count(),
            'row_count' => (int)(clone $rowQuery)->count(),
            'visible_row_count' => (int)(clone $rowQuery)->where('is_show', '=', 1)->count(),
            'history_row_count' => (int)($historyStats['history_row_count'] ?? 0),
            'snapshot_day_count' => (int)($historyStats['snapshot_day_count'] ?? 0),
            'latest_record_date' => (string)($historyStats['latest_record_date'] ?? ''),
            'latest_price_at' => (int)((clone $rowQuery)->max('update_at') ?: 0),
            'latest_sync_at' => (int)((clone $sourceQuery)->max('last_sync_at') ?: 0),
        ];
    }

    private function resolveItemOrder(array $where): string
    {
        $orderBy = (string)($where['order_by'] ?? '');
        $map = [
            'view' => 'view_count desc,id desc',
            'view_asc' => 'view_count asc,id desc',
            'new' => 'create_at desc,id desc',
            'old' => 'create_at asc,id asc',
        ];
        return $map[$orderBy] ?? 'sort asc,id desc';
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
                'is_hot',
                'follow_source',
                'has_update',
                'keyword',
                'create_at_start',
                'create_at_end',
            ], $where)
            ->order('sort asc,id asc');
        // 一个报价项下的型号行需要整张矩阵展示，返回全量 list 而非分页
        $list = $search->select()->toArray();
        $list = array_map(fn($row) => $this->appendRowDisplayFields($row), $list);
        $this->appendRowMergeFields($list);
        // 附带「今天之前最近一次」的价格，供前端做今天 vs 昨天涨跌对比
        $prevMap = (new QuotePriceHistoryService())->previousMap($this->site_id, array_column($list, 'id'));
        foreach ($list as &$row) {
            $row['prev_final_prices'] = $prevMap[(int)($row['id'] ?? 0)] ?? [];
        }
        unset($row);
        return ['data' => $list, 'total' => count($list)];
    }

    public function priceHistory(int $rowId, int $days): array
    {
        return (new QuotePriceHistoryService())->series($this->site_id, $rowId, $days);
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
            'is_hot' => (int)($data['is_hot'] ?? 0),
            'sort' => (int)($data['sort'] ?? 0),
            'follow_source' => (int)($data['follow_source'] ?? 0),
            'adjust_type' => 0,
            'adjust_value' => 0,
            'adjust_ratio' => 1,
            'round_mode' => 'round',
            'raw_data' => ['manual' => true],
            'source_hash' => md5($modelName . json_encode($manualPrices, JSON_UNESCAPED_UNICODE)),
        ]);
        (new QuotePriceHistoryService())->record($this->site_id, [
            'id' => (int)$record->id,
            'item_id' => $itemId,
            'source_id' => (int)($data['source_id'] ?? $item['source_id']),
            'model_name' => $modelName,
            'columns' => $columns,
            'final_prices' => $manualPrices,
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
            $history = new QuotePriceHistoryService();
            foreach ($rows as $row) {
                $rule = array_merge($row, $save);
                $finalPrices = $this->calculator->calculateList($row['source_prices'] ?? [], $rule);
                (new QuoteRow())->where('id', $row['id'])->update(['final_prices' => $finalPrices]);
                $history->record($this->site_id, array_merge($row, ['final_prices' => $finalPrices]));
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

        $save = $this->buildAdjustSave($data, ['follow_source', 'is_show', 'is_hot', 'sort']);
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
        if (array_key_exists('final_prices', $save)) {
            (new QuotePriceHistoryService())->record($this->site_id, array_merge($row, $save, ['id' => $id]));
        }
        $this->refreshApiCache();
        return true;
    }

    public function deleteItem(int $id): bool
    {
        $info = $this->model->where('site_id', $this->site_id)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($info)) {
            throw new CommonException('报价项不存在');
        }
        (new QuoteRow())->where('site_id', $this->site_id)->where('item_id', $id)->delete();
        $this->model->where('site_id', $this->site_id)->where('id', $id)->delete();
        $this->refreshApiCache();
        return true;
    }

    public function deleteRow(int $id): bool
    {
        $model = new QuoteRow();
        $row = $model->where('site_id', $this->site_id)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($row)) {
            throw new CommonException('报价行不存在');
        }
        $model->where('site_id', $this->site_id)->where('id', $id)->delete();
        $this->refreshApiCache();
        return true;
    }

    private function refreshApiCache(): void
    {
        (new QuoteApiCacheService())->refresh($this->site_id);
    }

    private function appendItemStatistics(array &$rows): void
    {
        if ($rows === []) return;
        $itemIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'id')))));
        if ($itemIds === []) return;

        $rowStats = (new QuoteRow())->where('site_id', $this->site_id)->whereIn('item_id', $itemIds)
            ->field('item_id,COUNT(*) as row_count,MAX(update_at) as latest_price_at')
            ->group('item_id')->select()->toArray();
        $rowMap = [];
        foreach ($rowStats as $stat) $rowMap[(int)$stat['item_id']] = $stat;

        $rowTable = (new QuoteRow())->getTable();
        $historyStats = (new QuotePriceHistory())->alias('h')
            ->join($rowTable . ' r', 'r.id = h.row_id AND r.site_id = h.site_id')
            ->where('h.site_id', '=', $this->site_id)
            ->whereIn('h.item_id', $itemIds)
            ->field('h.item_id,COUNT(DISTINCT h.row_id) as history_row_count,COUNT(DISTINCT h.record_date) as history_day_count,MAX(h.record_date) as latest_record_date')
            ->group('h.item_id')->select()->toArray();
        $historyMap = [];
        foreach ($historyStats as $stat) $historyMap[(int)$stat['item_id']] = $stat;

        $categoryIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'category_id')))));
        $categoryMap = $categoryIds === [] ? [] : (new QuoteCategory())->where('site_id', $this->site_id)
            ->whereIn('id', $categoryIds)->column('name', 'id');
        $sourceIds = array_values(array_unique(array_filter(array_map('intval', array_column($rows, 'source_id')))));
        $sourceMap = $sourceIds === [] ? [] : (new QuoteSource())->where('site_id', $this->site_id)
            ->whereIn('id', $sourceIds)->column('source_name', 'id');

        foreach ($rows as &$row) {
            $itemId = (int)$row['id'];
            $row['row_count'] = (int)($rowMap[$itemId]['row_count'] ?? 0);
            $row['latest_price_at'] = (int)($rowMap[$itemId]['latest_price_at'] ?? $row['last_sync_at'] ?? 0);
            $row['history_row_count'] = (int)($historyMap[$itemId]['history_row_count'] ?? 0);
            $row['history_day_count'] = (int)($historyMap[$itemId]['history_day_count'] ?? 0);
            $row['latest_record_date'] = (string)($historyMap[$itemId]['latest_record_date'] ?? '');
            $row['category_name'] = (string)($categoryMap[(int)$row['category_id']] ?? '未分类');
            $row['source_name'] = (string)($sourceMap[(int)$row['source_id']] ?? '未知来源');
        }
        unset($row);
    }

    private function appendRowDisplayFields($row)
    {
        $rawData = is_array($row) ? ($row['raw_data'] ?? []) : ($row->raw_data ?? []);
        $tab = is_array($row) ? (string)($row['tab'] ?? '') : (string)($row->tab ?? '');
        $capacityName = $this->extractCapacityName($rawData, $tab);
        $remark = $this->extractRemarkText($rawData);
        if (is_array($row)) {
            $row['capacity_name'] = $capacityName;
            $row['capacity'] = $capacityName;
            if (trim((string)($row['remark'] ?? '')) === '' && $remark !== '') {
                $row['remark'] = $remark;
            }
            return $row;
        }
        $row->setAttr('capacity_name', $capacityName);
        $row->setAttr('capacity', $capacityName);
        if (trim((string)($row->remark ?? '')) === '' && $remark !== '') {
            $row->setAttr('remark', $remark);
        }
        return $row;
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
