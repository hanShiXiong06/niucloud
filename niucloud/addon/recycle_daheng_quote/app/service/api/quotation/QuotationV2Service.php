<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\api\quotation;

use addon\recycle_daheng_quote\app\dict\quotation\QuotationV2Dict;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationDataset;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationField;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationNote;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationPrice;
use addon\recycle_daheng_quote\app\service\core\quotation\QuotationDisplayConfigService;
use addon\recycle_daheng_quote\app\service\core\quotation\QuotationV2CacheService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 报价 2.0 移动端展示服务
 */
class QuotationV2Service extends BaseApiService
{
    public function getTypes(array $where = []): array
    {
        return (new QuotationV2CacheService())->rememberTypes($this->site_id, $this->normalizeCacheWhere($where), function () use ($where) {
            return $this->buildTypes($where);
        });
    }

    public function getList(array $where = []): array
    {
        // 浏览量埋点:查看某数据集报价即 +1(在缓存外,确保每次访问都计)
        if ((int)($where['dataset_id'] ?? 0) > 0) {
            $this->incrementDatasetView((int)$where['dataset_id']);
        }
        return (new QuotationV2CacheService())->rememberList($this->site_id, $this->normalizeCacheWhere($where), function () use ($where) {
            return $this->buildList($where);
        });
    }

    private function buildTypes(array $where = []): array
    {
        $limit = (int)($where['limit'] ?? 20);
        if ($limit <= 0 || $limit > 50) {
            $limit = 20;
        }

        $displayConfig = (new QuotationDisplayConfigService())->getConfig($this->site_id);
        if ((int)$displayConfig['enabled'] !== 1) {
            return [];
        }

        $query = (new QuotationDataset())->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', QuotationV2Dict::STATUS_ENABLED],
        ])->field('id,quotation_id,price_name,dataset_name,channel_key,nav_image,sort,last_sync_at,last_sync_status,last_sync_message,last_sync_summary,remark,update_at')
            ->order('sort asc,id desc');

        if ($displayConfig['mode'] === QuotationDisplayConfigService::MODE_CUSTOM) {
            $displayDatasetIds = $displayConfig['dataset_ids'] ?? [];
            if (empty($displayDatasetIds)) {
                return [];
            }
            $query->where('id', 'in', $displayDatasetIds);
        }

        $datasetIds = $this->normalizeIdList($where['dataset_ids'] ?? []);
        if (!empty($datasetIds)) {
            $query->where('id', 'in', $datasetIds);
        }

        $quotationIds = $this->normalizeIdList($where['quotation_ids'] ?? []);
        if (!empty($quotationIds)) {
            $query->where('quotation_id', 'in', $quotationIds);
        }

        $datasets = $query->limit($limit)->select()->toArray();

        if (empty($datasets)) {
            return [];
        }

        $visibleDatasetIds = array_column($datasets, 'id');
        $priceCountMap = $this->countCurrentPriceByDataset($visibleDatasetIds);
        $modelCountMap = $this->countDistinctByDataset('recycle_quotation_v2_model', $visibleDatasetIds, 'id');

        foreach ($datasets as &$item) {
            $item['dataset_id'] = (int)$item['id'];
            $item['title'] = $this->datasetTitle($item);
            $item['last_sync_at_text'] = $this->formatTime((int)($item['last_sync_at'] ?? 0));
            $item['last_sync_status_name'] = (int)($item['last_sync_status'] ?? 0) === QuotationV2Dict::SYNC_STATUS_SUCCESS ? '已同步' : '待同步';
            $item['price_count'] = (int)($priceCountMap[(int)$item['id']] ?? 0);
            $item['model_count'] = (int)($modelCountMap[(int)$item['id']] ?? 0);
        }
        unset($item);

        return $datasets;
    }

    private function buildList(array $where = []): array
    {
        $dataset = $this->resolveDataset($where);
        $priceDate = $this->resolvePriceDate((int)$dataset['id'], (string)($where['price_date'] ?? ''));
        return $this->queryRows($dataset, $priceDate, $where);
    }

    private function queryRows(array $dataset, string $priceDate, array $where = []): array
    {
        $query = (new QuotationPrice())->alias('p')
            ->leftJoin('recycle_quotation_v2_model m', 'p.model_id = m.id AND p.site_id = m.site_id')
            ->leftJoin('recycle_quotation_v2_capacity c', 'p.capacity_id = c.id AND p.site_id = c.site_id')
            ->leftJoin('recycle_quotation_v2_field f', 'p.field_id = f.id AND p.site_id = f.site_id')
            ->where([
                ['p.site_id', '=', $this->site_id],
                ['p.dataset_id', '=', (int)$dataset['id']],
                ['p.price_date', '=', $priceDate],
                ['m.status', '=', QuotationV2Dict::STATUS_ENABLED],
                ['c.status', '=', QuotationV2Dict::STATUS_ENABLED],
                ['f.status', '=', QuotationV2Dict::STATUS_ENABLED],
                ['f.field_type', '=', QuotationV2Dict::FIELD_TYPE_PRICE],
            ])
            ->field('p.id,p.site_id,p.dataset_id,p.quotation_id,p.model_id,p.capacity_id,p.field_id,p.external_goods_id,p.capacity_answer_id,p.field_name,p.crawler_price,p.adjust_type,p.adjust_value,p.final_price,p.price_date,p.create_at,p.update_at,m.model_name,m.group_key as model_group_key,m.series_name as model_series_name,m.is_hot as model_is_hot,m.sort as model_sort,c.capacity_name,c.sort as capacity_sort,f.sort as field_sort')
            ->order('m.sort asc,m.id asc,c.sort asc,c.id asc,f.sort asc,f.id asc,p.id asc');

        if (!empty($where['model_name'])) {
            $query->where('m.model_name', 'like', '%' . trim((string)$where['model_name']) . '%');
        }
        if (!empty($where['capacity_name'])) {
            $query->where('c.capacity_name', 'like', '%' . trim((string)$where['capacity_name']) . '%');
        }

        $prices = $query->select()->toArray();
        if (empty($prices)) {
            return [];
        }

        $noteMap = $this->getAdjustmentMap((int)$dataset['id']);
        $rows = [];
        foreach ($prices as $item) {
            $key = (int)$item['model_id'] . '#' . (int)$item['capacity_id'];
            if (!isset($rows[$key])) {
                $noteData = $noteMap[$key] ?? [
                    'items' => [],
                    'summary' => '',
                ];
                $rows[$key] = [
                    'id' => (int)$item['capacity_id'],
                    'dataset_id' => (int)$item['dataset_id'],
                    'quotation_id' => (int)$item['quotation_id'],
                    'price_name' => $this->datasetTitle($dataset),
                    'goods_id' => (int)$item['external_goods_id'],
                    'goods_name' => (string)$item['model_name'],
                    'model_group_key' => (int)($item['model_group_key'] ?? 0),
                    'series_name' => $this->resolveSeriesName($item),
                    'is_hot' => (int)($item['model_is_hot'] ?? 0),
                    'capacity_id' => (int)$item['capacity_id'],
                    'capacity_answer_id' => (int)$item['capacity_answer_id'],
                    'capacity' => (string)$item['capacity_name'],
                    'prices' => [],
                    'add_value_info' => 0,
                    'value_info' => (string)$noteData['summary'],
                    'adjustment_items' => $noteData['items'],
                    'adjustment_summary' => (string)$noteData['summary'],
                    'price_date' => (string)$item['price_date'],
                    'create_at' => $this->formatTimeValue($item['create_at'] ?? 0),
                    'update_at' => $this->formatTimeValue($item['update_at'] ?? 0),
                ];
            }

            $rows[$key]['prices'][(string)$item['field_name']] = [
                'id' => (int)$item['id'],
                'original' => (float)$item['crawler_price'],
                'final' => (float)$item['final_price'],
                'price' => (float)$item['final_price'],
                'adjust_type' => (int)$item['adjust_type'],
                'adjust_value' => (float)$item['adjust_value'],
            ];
            $rows[$key]['create_at'] = $this->maxTimeValue($rows[$key]['create_at'], $item['create_at'] ?? 0);
            $rows[$key]['update_at'] = $this->maxTimeValue($rows[$key]['update_at'], $item['update_at'] ?? 0);
        }

        return array_values($rows);
    }

    /**
     * 报价单生成用：把某数据集转成与 spider 一致的「报价单详情」形状
     * { id, name, brand, columns[], notice_text, price_date, rows[ {id, model_name, capacity, columns[], final_prices[], prev_final_prices[], remark} ] }
     */
    public function getDetail(array $where = []): array
    {
        $dataset = $this->resolveDataset($where);
        $datasetId = (int)$dataset['id'];
        $this->incrementDatasetView($datasetId);
        $priceDate = $this->resolvePriceDate($datasetId, (string)($where['price_date'] ?? ''));
        $columns = $this->datasetPriceColumns($datasetId);
        $rows = $this->queryRows($dataset, $priceDate, []);

        // 上一日价格（今天 vs 昨天涨跌）
        $prevDate = $this->previousPriceDate($datasetId, $priceDate);
        $prevByCap = [];
        if ($prevDate !== '') {
            foreach ($this->queryRows($dataset, $prevDate, []) as $pr) {
                $prevByCap[(int)$pr['id']] = $pr['prices'] ?? [];
            }
        }

        $outRows = [];
        foreach ($rows as $r) {
            $finalArr = [];
            foreach ($columns as $col) {
                $p = $r['prices'][$col] ?? null;
                $finalArr[] = $p ? $this->priceToDisplay($p['final']) : '';
            }
            $prevArr = [];
            if (isset($prevByCap[(int)$r['id']])) {
                foreach ($columns as $col) {
                    $p = $prevByCap[(int)$r['id']][$col] ?? null;
                    $prevArr[] = $p ? $this->priceToDisplay($p['final']) : '';
                }
            }
            $outRows[] = [
                'id' => (int)$r['id'],
                'item_id' => $datasetId,
                'model_name' => (string)$r['goods_name'],
                'tab' => (string)$r['series_name'],
                'capacity_name' => (string)$r['capacity'],
                'capacity' => (string)$r['capacity'],
                'columns' => $columns,
                'final_prices' => $finalArr,
                'prev_final_prices' => $prevArr,
                'remark' => (string)($r['value_info'] ?? ''),
                'is_hot' => (int)($r['is_hot'] ?? 0),
                'price_date' => (string)($r['price_date'] ?? $priceDate),
            ];
        }

        return [
            'id' => $datasetId,
            'name' => $this->datasetTitle($dataset),
            'brand' => '',
            'columns' => $columns,
            'notice_text' => $this->resolveDetailNotice($dataset),
            'price_date' => $priceDate,
            'update_at_text' => $this->formatTime((int)($dataset['last_sync_at'] ?? 0)),
            'rows' => $outRows,
        ];
    }

    /**
     * 单个容量(=报价单里一行)近 N 天价格序列（趋势弹窗）
     * @return array{columns: array, points: array<int, array{date:string, prices:array}>}
     */
    public function getPriceHistory(int $capacityId, int $days): array
    {
        $days = max(1, min(365, $days));
        $empty = ['columns' => [], 'points' => []];
        if ($this->site_id <= 0 || $capacityId <= 0) {
            return $empty;
        }
        try {
            $start = date('Y-m-d', time() - ($days - 1) * 86400);
            $list = (new QuotationPrice())->alias('p')
                ->leftJoin('recycle_quotation_v2_field f', 'p.field_id = f.id AND p.site_id = f.site_id')
                ->where([
                    ['p.site_id', '=', $this->site_id],
                    ['p.capacity_id', '=', $capacityId],
                    ['p.price_date', '>=', $start],
                    ['f.field_type', '=', QuotationV2Dict::FIELD_TYPE_PRICE],
                ])
                ->field('p.price_date,p.field_name,p.final_price,f.sort as field_sort,f.id as field_id')
                ->order('p.price_date asc,f.sort asc,f.id asc')
                ->select()
                ->toArray();
            if (empty($list)) {
                return $empty;
            }
            $columns = [];
            foreach ($list as $it) {
                $name = (string)$it['field_name'];
                if ($name !== '' && !in_array($name, $columns, true)) {
                    $columns[] = $name;
                }
            }
            $byDate = [];
            foreach ($list as $it) {
                $d = (string)$it['price_date'];
                if (!isset($byDate[$d])) {
                    $byDate[$d] = array_fill(0, count($columns), null);
                }
                $idx = array_search((string)$it['field_name'], $columns, true);
                if ($idx !== false) {
                    $byDate[$d][$idx] = $this->priceToDisplay($it['final_price']);
                }
            }
            ksort($byDate);
            $points = [];
            foreach ($byDate as $d => $prices) {
                $points[] = ['date' => $d, 'prices' => $prices];
            }
            return ['columns' => $columns, 'points' => $points];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    /**
     * 会员是否有「报价单生成」权益（daheng_quote_report）
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
            $allowed = !empty($member['memberLevelData']['level_benefits']['daheng_quote_report']['is_use']) ? 1 : 0;
            return ['allowed' => $allowed];
        } catch (\Throwable $e) {
            return ['allowed' => 0];
        }
    }

    private function datasetPriceColumns(int $datasetId): array
    {
        $names = (new QuotationField())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', $datasetId],
            ['status', '=', QuotationV2Dict::STATUS_ENABLED],
            ['field_type', '=', QuotationV2Dict::FIELD_TYPE_PRICE],
        ])->order('sort asc,id asc')->column('field_name');

        $columns = [];
        foreach ($names as $name) {
            $name = trim((string)$name);
            if ($name !== '' && !in_array($name, $columns, true)) {
                $columns[] = $name;
            }
        }
        return $columns;
    }

    private function previousPriceDate(int $datasetId, string $priceDate): string
    {
        $prev = (new QuotationPrice())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', $datasetId],
            ['price_date', '<', $priceDate],
        ])->order('price_date desc')->value('price_date');
        return $prev ? (string)$prev : '';
    }

    private function priceToDisplay($value): string
    {
        $v = (float)$value;
        if ($v == (int)$v) {
            return (string)(int)$v;
        }
        return rtrim(rtrim(sprintf('%.2f', $v), '0'), '.');
    }

    private function resolveDetailNotice(array $dataset): string
    {
        $remark = trim((string)($dataset['remark'] ?? ''));
        return $remark !== '' ? $remark : '温馨提示：报价仅供参考，最终价格以质检结果为准';
    }

    private function incrementDatasetView(int $datasetId): void
    {
        if ($datasetId <= 0) {
            return;
        }
        try {
            Db::name('recycle_quotation_v2_dataset')
                ->where([['site_id', '=', $this->site_id], ['id', '=', $datasetId]])
                ->inc('view_count')
                ->update();
        } catch (\Throwable $e) {
            // 浏览量失败不影响主流程（字段可能尚未升级）
        }
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

    private function resolveSeriesName(array $item): string
    {
        $seriesName = trim((string)($item['model_series_name'] ?? ''));
        if ($seriesName !== '') {
            return $seriesName;
        }

        $groupKey = (int)($item['model_group_key'] ?? 0);
        return $groupKey > 0 ? '系列 ' . $groupKey : '';
    }

    private function resolveDataset(array $where): array
    {
        $displayConfig = (new QuotationDisplayConfigService())->getConfig($this->site_id);
        if ((int)$displayConfig['enabled'] !== 1) {
            throw new CommonException('报价单暂未开放展示');
        }

        $query = (new QuotationDataset())->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', QuotationV2Dict::STATUS_ENABLED],
        ]);

        if ($displayConfig['mode'] === QuotationDisplayConfigService::MODE_CUSTOM) {
            $displayDatasetIds = $displayConfig['dataset_ids'] ?? [];
            if (empty($displayDatasetIds)) {
                throw new CommonException('报价单暂未开放展示');
            }
            $query->where('id', 'in', $displayDatasetIds);
        }

        $datasetId = (int)($where['dataset_id'] ?? 0);
        $quotationId = (int)($where['quotation_id'] ?? 0);
        if ($datasetId > 0) {
            $query->where('id', '=', $datasetId);
        } elseif ($quotationId > 0) {
            $query->where('quotation_id', '=', $quotationId);
        } else {
            $query->order('sort asc,id desc');
        }

        $dataset = $query->findOrEmpty()->toArray();
        if (empty($dataset)) {
            throw new CommonException('报价单不存在或未启用');
        }

        return $dataset;
    }

    private function normalizeIdList($value): array
    {
        if (is_string($value)) {
            $value = array_filter(explode(',', $value), static fn($item) => trim((string)$item) !== '');
        }
        if (!is_array($value)) {
            return [];
        }

        $ids = [];
        foreach ($value as $item) {
            $id = (int)$item;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function resolvePriceDate(int $datasetId, string $requestedDate): string
    {
        $requestedDate = trim($requestedDate);
        if ($requestedDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
            return $requestedDate;
        }

        $today = date('Y-m-d');
        $existsToday = (new QuotationPrice())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', $datasetId],
            ['price_date', '=', $today],
        ])->value('id');
        if (!empty($existsToday)) {
            return $today;
        }

        $latest = (new QuotationPrice())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', $datasetId],
        ])->order('price_date desc')->value('price_date');

        return $latest ? (string)$latest : $today;
    }

    private function getAdjustmentMap(int $datasetId): array
    {
        $notes = (new QuotationNote())->alias('n')
            ->leftJoin('recycle_quotation_v2_field f', 'n.field_id = f.id AND n.site_id = f.site_id')
            ->where([
                ['n.site_id', '=', $this->site_id],
                ['n.dataset_id', '=', $datasetId],
                ['n.status', '=', QuotationV2Dict::STATUS_ENABLED],
            ])
            ->whereRaw('(f.field_type IS NULL OR f.field_type <> "' . QuotationV2Dict::FIELD_TYPE_PRICE . '")')
            ->field('n.id,n.model_id,n.capacity_id,n.field_id,n.field_name,n.content_text,n.content_html,n.raw_item,f.field_type,f.sort as field_sort')
            ->order('f.field_type asc,f.sort asc,n.id asc')
            ->select()
            ->toArray();

        $map = [];
        foreach ($notes as $note) {
            $content = trim((string)($note['content_text'] ?? ''));
            if ($content === '') {
                continue;
            }
            $key = (int)$note['model_id'] . '#' . (int)$note['capacity_id'];
            if (!isset($map[$key])) {
                $map[$key] = [
                    'items' => [],
                    'summary_items' => [],
                    'summary' => '',
                ];
            }

            $fieldName = trim((string)($note['field_name'] ?? ''));
            $summary = $fieldName !== '' ? $fieldName . '：' . $content : $content;
            if (in_array($summary, $map[$key]['summary_items'], true)) {
                continue;
            }

            $map[$key]['items'][] = [
                'id' => (int)$note['id'],
                'field_id' => (int)$note['field_id'],
                'field_name' => $fieldName,
                'field_type' => (string)($note['field_type'] ?? ''),
                'field_sort' => (int)($note['field_sort'] ?? 0),
                'content_text' => $content,
                'content_html' => (string)($note['content_html'] ?? ''),
                'raw_item' => $note['raw_item'] ?? [],
            ];
            $map[$key]['summary_items'][] = $summary;
        }

        foreach ($map as $key => $data) {
            $map[$key]['summary'] = implode('；', $data['summary_items']);
            unset($map[$key]['summary_items']);
        }

        return $map;
    }

    private function datasetTitle(array $item): string
    {
        $datasetName = trim((string)($item['dataset_name'] ?? ''));
        if ($datasetName !== '') {
            return $datasetName;
        }

        return trim((string)($item['price_name'] ?? '')) ?: '回收报价单';
    }

    private function countCurrentPriceByDataset(array $datasetIds): array
    {
        if (empty($datasetIds)) {
            return [];
        }

        $latestRows = (new QuotationPrice())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', 'in', $datasetIds],
        ])->field('dataset_id,max(price_date) as price_date')
            ->group('dataset_id')
            ->select()
            ->toArray();

        $dateMap = [];
        foreach ($latestRows as $row) {
            $dateMap[(int)$row['dataset_id']] = (string)$row['price_date'];
        }
        if (empty($dateMap)) {
            return [];
        }

        $rows = (new QuotationPrice())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', 'in', $datasetIds],
        ])->field('dataset_id,count(*) as total')
            ->where(function ($query) use ($dateMap) {
                foreach ($dateMap as $datasetId => $priceDate) {
                    $query->whereOr(function ($itemQuery) use ($datasetId, $priceDate) {
                        $itemQuery->where([
                            ['dataset_id', '=', $datasetId],
                            ['price_date', '=', $priceDate],
                        ]);
                    });
                }
            })->group('dataset_id')->select()->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['dataset_id']] = (int)$row['total'];
        }
        return $map;
    }

    private function countDistinctByDataset(string $table, array $datasetIds, string $field): array
    {
        if (empty($datasetIds)) {
            return [];
        }

        $rows = Db::name($table)
            ->where([
                ['site_id', '=', $this->site_id],
                ['dataset_id', 'in', $datasetIds],
            ])
            ->field('dataset_id,count(distinct ' . $field . ') as total')
            ->group('dataset_id')
            ->select()
            ->toArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['dataset_id']] = (int)$row['total'];
        }
        return $map;
    }

    private function formatTime(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }
        return date('Y-m-d H:i', $timestamp);
    }

    private function formatTimeValue($value): string
    {
        if (is_string($value) && strpos($value, '-') !== false) {
            return $value;
        }

        $timestamp = (int)$value;
        if ($timestamp <= 0) {
            return '';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function maxTimeValue($current, $incoming): string
    {
        $currentTime = $this->timeValueToTimestamp($current);
        $incomingTime = $this->timeValueToTimestamp($incoming);

        return $this->formatTimeValue(max($currentTime, $incomingTime));
    }

    private function timeValueToTimestamp($value): int
    {
        if (is_string($value) && strpos($value, '-') !== false) {
            $timestamp = strtotime($value);
            return $timestamp === false ? 0 : $timestamp;
        }

        return (int)$value;
    }
}
