<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\api\quotation;

use addon\recycle_daheng_quote\app\dict\quotation\QuotationV2Dict;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationDataset;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationNote;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationPrice;
use addon\recycle_daheng_quote\app\service\core\quotation\QuotationDisplayConfigService;
use addon\recycle_daheng_quote\app\service\core\quotation\QuotationV2CacheService;
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
            ->field('p.id,p.site_id,p.dataset_id,p.quotation_id,p.model_id,p.capacity_id,p.field_id,p.external_goods_id,p.capacity_answer_id,p.field_name,p.crawler_price,p.adjust_type,p.adjust_value,p.final_price,p.price_date,p.create_at,p.update_at,m.model_name,m.group_key as model_group_key,m.sort as model_sort,c.capacity_name,c.sort as capacity_sort,f.sort as field_sort')
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
