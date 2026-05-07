<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\admin\quotation_v2;

use addon\recycle_daheng_quote\app\dict\quotation\QuotationV2Dict;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationCapacity;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationField;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationModel;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationNote;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationPrice;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationSyncLog;
use core\base\BaseAdminService;

/**
 * 报价 2.0 查询服务
 */
class QueryService extends BaseAdminService
{
    public function getModels(array $where = []): array
    {
        $query = (new QuotationModel())->where([['site_id', '=', $this->site_id]])
            ->withSearch(['dataset_id', 'model_name'], $where)
            ->order('sort asc,id desc');

        return $this->pageQuery($query);
    }

    public function getCapacities(array $where = []): array
    {
        $query = (new QuotationCapacity())->alias('c')
            ->leftJoin('recycle_quotation_v2_model m', 'c.model_id = m.id AND c.site_id = m.site_id')
            ->where([['c.site_id', '=', $this->site_id]])
            ->field('c.*,m.model_name,m.group_key as model_group_key,m.sort as model_sort')
            ->order('m.sort asc,m.id asc,c.sort asc,c.id asc');

        if (isset($where['dataset_id']) && $where['dataset_id'] !== '') {
            $query->where('c.dataset_id', '=', $where['dataset_id']);
        }
        if (isset($where['model_id']) && $where['model_id'] !== '') {
            $query->where('c.model_id', '=', $where['model_id']);
        }
        if (isset($where['capacity_name']) && $where['capacity_name'] !== '') {
            $query->where('c.capacity_name', 'like', '%' . $where['capacity_name'] . '%');
        }

        return $this->pageQuery($query);
    }

    public function getFields(array $where = []): array
    {
        $query = (new QuotationField())->where([['site_id', '=', $this->site_id]])
            ->withSearch(['dataset_id', 'field_type', 'field_name'], $where)
            ->order('field_type asc,sort asc,id asc');

        return $this->pageQuery($query);
    }

    public function getPrices(array $where = []): array
    {
        $query = (new QuotationPrice())->alias('p')
            ->leftJoin('recycle_quotation_v2_model m', 'p.model_id = m.id AND p.site_id = m.site_id')
            ->leftJoin('recycle_quotation_v2_capacity c', 'p.capacity_id = c.id AND p.site_id = c.site_id')
            ->leftJoin('recycle_quotation_v2_field f', 'p.field_id = f.id AND p.site_id = f.site_id')
            ->where([['p.site_id', '=', $this->site_id]])
            ->field('p.*,m.model_name,m.group_key as model_group_key,m.sort as model_sort,c.capacity_name,c.sort as capacity_sort,f.field_type,f.sort as field_sort')
            ->order('m.sort asc,m.id asc,c.sort asc,c.id asc,f.sort asc,f.id asc,p.id asc');

        $this->applyCommonFilters($query, $where, 'p');
        $this->applyPriceDateFilter($query, $where, 'p');
        if (!empty($where['model_name'])) {
            $query->where('m.model_name', 'like', '%' . $where['model_name'] . '%');
        }
        if (!empty($where['capacity_name'])) {
            $query->where('c.capacity_name', 'like', '%' . $where['capacity_name'] . '%');
        }
        if (!empty($where['field_name'])) {
            $query->where('p.field_name', 'like', '%' . $where['field_name'] . '%');
        }

        $result = $this->pageQuery($query, function ($item) {
            $item['model_label'] = trim((string)($item['model_name'] ?? '')) ?: ('型号ID ' . (int)($item['external_goods_id'] ?? 0));
            $item['capacity_label'] = trim((string)($item['capacity_name'] ?? '')) ?: ('容量ID ' . (int)($item['capacity_answer_id'] ?? 0));
            $item['price_label'] = (string)($item['field_name'] ?? '');
            $item['price_value_text'] = '¥' . number_format((float)($item['final_price'] ?? 0), 2);
            $item['crawler_price_text'] = '¥' . number_format((float)($item['crawler_price'] ?? 0), 2);
            $adjustValue = (float)($item['adjust_value'] ?? 0);
            $item['adjust_value_text'] = ($adjustValue >= 0 ? '+' : '') . number_format($adjustValue, 2);
            $item['row_key'] = implode('-', [
                (int)($item['model_id'] ?? 0),
                (int)($item['capacity_id'] ?? 0),
                (int)($item['field_id'] ?? 0),
            ]);
            return $item;
        });
        $result['list'] = $this->attachAdjustmentItems($result['data'] ?? []);
        $result['matrix'] = $this->buildPriceMatrix($result['list']);

        return $result;
    }

    public function getPriceMatrix(array $where = []): array
    {
        $query = (new QuotationPrice())->alias('p')
            ->leftJoin('recycle_quotation_v2_model m', 'p.model_id = m.id AND p.site_id = m.site_id')
            ->leftJoin('recycle_quotation_v2_capacity c', 'p.capacity_id = c.id AND p.site_id = c.site_id')
            ->leftJoin('recycle_quotation_v2_field f', 'p.field_id = f.id AND p.site_id = f.site_id')
            ->where([['p.site_id', '=', $this->site_id]])
            ->field('p.*,m.model_name,m.group_key as model_group_key,m.sort as model_sort,c.capacity_name,c.sort as capacity_sort,f.field_type,f.sort as field_sort')
            ->order('m.sort asc,m.id asc,c.sort asc,c.id asc,f.sort asc,f.id asc,p.id asc');

        $this->applyCommonFilters($query, $where, 'p');
        $this->applyPriceDateFilter($query, $where, 'p');
        if (!empty($where['model_name'])) {
            $query->where('m.model_name', 'like', '%' . $where['model_name'] . '%');
        }
        if (!empty($where['capacity_name'])) {
            $query->where('c.capacity_name', 'like', '%' . $where['capacity_name'] . '%');
        }
        if (!empty($where['field_name'])) {
            $query->where('p.field_name', 'like', '%' . $where['field_name'] . '%');
        }

        $rows = $query->select()->toArray();
        $rows = array_map(function ($item) {
            $item['model_label'] = trim((string)($item['model_name'] ?? '')) ?: ('型号ID ' . (int)($item['external_goods_id'] ?? 0));
            $item['capacity_label'] = trim((string)($item['capacity_name'] ?? '')) ?: ('容量ID ' . (int)($item['capacity_answer_id'] ?? 0));
            $item['price_label'] = (string)($item['field_name'] ?? '');
            $item['price_value_text'] = '¥' . number_format((float)($item['final_price'] ?? 0), 2);
            $item['crawler_price_text'] = '¥' . number_format((float)($item['crawler_price'] ?? 0), 2);
            $adjustValue = (float)($item['adjust_value'] ?? 0);
            $item['adjust_value_text'] = ($adjustValue >= 0 ? '+' : '') . number_format($adjustValue, 2);
            $item['row_key'] = implode('-', [
                (int)($item['model_id'] ?? 0),
                (int)($item['capacity_id'] ?? 0),
                (int)($item['field_id'] ?? 0),
            ]);
            return $item;
        }, $rows);
        $rows = $this->attachAdjustmentItems($rows);
        $matrix = $this->buildPriceMatrix($rows);

        return [
            'data' => $rows,
            'list' => $rows,
            'total' => count($rows),
            'matrix' => $matrix,
            'price_date' => $this->resolvePriceDate($where),
        ];
    }

    private function buildPriceMatrix(array $rows): array
    {
        $columns = [];
        $matrixRows = [];
        $modelFieldMap = [];

        foreach ($rows as $row) {
            $fieldId = (int)($row['field_id'] ?? 0);
            $modelId = (int)($row['model_id'] ?? 0);
            if ($fieldId > 0 && !isset($columns[$fieldId])) {
                $columns[$fieldId] = [
                    'field_id' => $fieldId,
                    'field_name' => (string)($row['field_name'] ?? ''),
                    'field_sort' => (int)($row['field_sort'] ?? 0),
                ];
            }
            if ($modelId > 0 && $fieldId > 0) {
                $modelFieldMap[$modelId][$fieldId] = $fieldId;
            }

            $matrixKey = $modelId . '#' . (int)($row['capacity_id'] ?? 0);
            if (!isset($matrixRows[$matrixKey])) {
                $matrixRows[$matrixKey] = [
                    'row_key' => $matrixKey,
                    'model_id' => $modelId,
                    'model_name' => (string)($row['model_name'] ?? ''),
                    'model_label' => (string)($row['model_label'] ?? ''),
                    'model_group_key' => (int)($row['model_group_key'] ?? 0),
                    'external_goods_id' => (int)($row['external_goods_id'] ?? 0),
                    'capacity_id' => (int)($row['capacity_id'] ?? 0),
                    'capacity_name' => (string)($row['capacity_name'] ?? ''),
                    'capacity_label' => (string)($row['capacity_label'] ?? ''),
                    'capacity_answer_id' => (int)($row['capacity_answer_id'] ?? 0),
                    'adjustment_items' => $row['adjustment_items'] ?? [],
                    'adjustments' => $row['adjustments'] ?? [],
                    'adjustment_summary' => (string)($row['adjustment_summary'] ?? ''),
                    'prices' => [],
                    'price_ids' => [],
                ];
            }

            if ($fieldId > 0) {
                $matrixRows[$matrixKey]['prices'][$fieldId] = [
                    'id' => (int)($row['id'] ?? 0),
                    'field_id' => $fieldId,
                    'field_name' => (string)($row['field_name'] ?? ''),
                    'crawler_price' => (float)($row['crawler_price'] ?? 0),
                    'crawler_price_text' => (string)($row['crawler_price_text'] ?? ''),
                    'adjust_type' => (int)($row['adjust_type'] ?? 1),
                    'adjust_value' => (float)($row['adjust_value'] ?? 0),
                    'adjust_value_text' => (string)($row['adjust_value_text'] ?? ''),
                    'final_price' => (float)($row['final_price'] ?? 0),
                    'price_value_text' => (string)($row['price_value_text'] ?? ''),
                    'locked' => (int)($row['locked'] ?? 0),
                    'price_date' => (string)($row['price_date'] ?? ''),
                ];
                $matrixRows[$matrixKey]['price_ids'][] = (int)($row['id'] ?? 0);
            }
        }

        $columns = array_values($columns);
        $this->sortMatrixColumns($columns);
        $flatRows = array_values($matrixRows);
        $groups = $this->buildPriceMatrixGroups($flatRows, $columns, $modelFieldMap);

        return [
            'columns' => $columns,
            'adjustment_columns' => $this->collectAdjustmentColumns($flatRows),
            'rows' => $flatRows,
            'groups' => $groups,
        ];
    }

    private function buildPriceMatrixGroups(array $rows, array $columns, array $modelFieldMap): array
    {
        $columnMap = [];
        foreach ($columns as $column) {
            $columnMap[(int)$column['field_id']] = $column;
        }

        $groups = [];
        foreach ($rows as $row) {
            $modelId = (int)($row['model_id'] ?? 0);
            $fieldIds = array_values($modelFieldMap[$modelId] ?? []);
            sort($fieldIds);
            $modelGroupKey = (int)($row['model_group_key'] ?? 0);
            $businessGroupKey = $modelGroupKey > 0 ? 'series-' . $modelGroupKey : 'model-' . $modelId;
            $gradeSignature = !empty($fieldIds) ? implode('-', $fieldIds) : 'empty';
            $signature = $businessGroupKey . ':' . $gradeSignature;

            if (!isset($groups[$signature])) {
                $groupColumns = [];
                foreach ($fieldIds as $fieldId) {
                    if (isset($columnMap[$fieldId])) {
                        $groupColumns[] = $columnMap[$fieldId];
                    }
                }
                $this->sortMatrixColumns($groupColumns);

                $groups[$signature] = [
                    'group_key' => $signature,
                    'business_group_key' => $businessGroupKey,
                    'model_group_key' => $modelGroupKey,
                    'group_title' => '',
                    'model_names' => [],
                    'model_count' => 0,
                    'capacity_count' => 0,
                    'columns' => $groupColumns,
                    'adjustment_columns' => [],
                    'rows' => [],
                ];
            }

            $groups[$signature]['rows'][] = $row;
            $modelName = (string)($row['model_label'] ?? '');
            if ($modelName !== '') {
                $groups[$signature]['model_names'][$modelId] = $modelName;
            }
        }

        foreach ($groups as &$group) {
            $modelNames = array_values($group['model_names']);
            $group['model_count'] = count($modelNames);
            $group['capacity_count'] = count($group['rows']);
            $group['group_title'] = count($modelNames) === 1
                ? $modelNames[0]
                : '等级结构 ' . (count($modelNames) > 0 ? implode('、', array_slice($modelNames, 0, 3)) : '未命名');
            if (count($modelNames) > 3) {
                $group['group_title'] .= ' 等 ' . count($modelNames) . ' 个型号';
            }
            $group['model_names'] = $modelNames;
            $group['adjustment_columns'] = $this->collectAdjustmentColumns($group['rows']);
        }
        unset($group);

        return array_values($groups);
    }

    private function sortMatrixColumns(array &$columns): void
    {
        usort($columns, function ($a, $b) {
            return ((int)($a['field_sort'] ?? 0) <=> (int)($b['field_sort'] ?? 0))
                ?: ((int)($a['field_id'] ?? 0) <=> (int)($b['field_id'] ?? 0));
        });
    }

    private function collectAdjustmentColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (($row['adjustment_items'] ?? []) as $item) {
                $fieldId = (int)($item['field_id'] ?? 0);
                if ($fieldId <= 0 || isset($columns[$fieldId])) {
                    continue;
                }
                $columns[$fieldId] = [
                    'field_id' => $fieldId,
                    'field_name' => (string)($item['field_name'] ?? ''),
                    'field_sort' => (int)($item['field_sort'] ?? 0),
                ];
            }
        }

        $columns = array_values($columns);
        $this->sortMatrixColumns($columns);
        return $columns;
    }

    private function attachAdjustmentItems(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $capacityPairs = [];
        foreach ($rows as $row) {
            $modelId = (int)($row['model_id'] ?? 0);
            $capacityId = (int)($row['capacity_id'] ?? 0);
            if ($modelId > 0 && $capacityId > 0) {
                $capacityPairs[$modelId . '#' . $capacityId] = [
                    'model_id' => $modelId,
                    'capacity_id' => $capacityId,
                ];
            }
        }
        if (empty($capacityPairs)) {
            return $rows;
        }

        $query = (new QuotationNote())->alias('n')
            ->leftJoin('recycle_quotation_v2_field f', 'n.field_id = f.id AND n.site_id = f.site_id')
            ->where([
                ['n.site_id', '=', $this->site_id],
                ['n.status', '=', 1],
                ['f.field_type', '=', QuotationV2Dict::FIELD_TYPE_ADJUSTMENT],
            ])
            ->field('n.id,n.model_id,n.capacity_id,n.field_id,n.field_name,n.content_text,n.content_html,n.raw_item,f.sort as field_sort')
            ->order('f.sort asc,n.id asc');

        $query->where(function ($subQuery) use ($capacityPairs) {
            foreach ($capacityPairs as $pair) {
                $subQuery->whereOr(function ($itemQuery) use ($pair) {
                    $itemQuery->where([
                        ['n.model_id', '=', $pair['model_id']],
                        ['n.capacity_id', '=', $pair['capacity_id']],
                    ]);
                });
            }
        });

        $adjustmentMap = [];
        foreach ($query->select()->toArray() as $item) {
            $key = (int)$item['model_id'] . '#' . (int)$item['capacity_id'];
            $adjustmentMap[$key][] = [
                'id' => (int)$item['id'],
                'field_id' => (int)$item['field_id'],
                'field_name' => (string)$item['field_name'],
                'field_sort' => (int)($item['field_sort'] ?? 0),
                'content_text' => (string)$item['content_text'],
                'content_html' => (string)($item['content_html'] ?? ''),
                'raw_item' => $item['raw_item'] ?? [],
            ];
        }

        foreach ($rows as &$row) {
            $key = (int)($row['model_id'] ?? 0) . '#' . (int)($row['capacity_id'] ?? 0);
            $items = $adjustmentMap[$key] ?? [];
            $row['adjustment_items'] = $items;
            $row['adjustments'] = [];
            foreach ($items as $item) {
                $row['adjustments'][(int)$item['field_id']] = $item;
            }
            $row['adjustment_summary'] = implode('；', array_map(function ($item) {
                $text = trim((string)($item['content_text'] ?? ''));
                return trim((string)$item['field_name'] . ($text !== '' ? '：' . $text : ''));
            }, $items));
        }
        unset($row);

        return $rows;
    }

    public function getNotes(array $where = []): array
    {
        $query = (new QuotationNote())->alias('n')
            ->leftJoin('recycle_quotation_v2_field f', 'n.field_id = f.id AND n.site_id = f.site_id')
            ->leftJoin('recycle_quotation_v2_model m', 'n.model_id = m.id AND n.site_id = m.site_id')
            ->leftJoin('recycle_quotation_v2_capacity c', 'n.capacity_id = c.id AND n.site_id = c.site_id')
            ->where([['n.site_id', '=', $this->site_id]])
            ->field('n.*,m.model_name,c.capacity_name,f.field_type,f.field_type as content_type')
            ->order('m.sort asc,m.id asc,c.sort asc,c.id asc,f.sort asc,n.id asc');

        $this->applyCommonFilters($query, $where, 'n');
        if (isset($where['field_type']) && $where['field_type'] !== '') {
            $query->where('f.field_type', '=', $where['field_type']);
        }
        if (!empty($where['model_name'])) {
            $query->where('m.model_name', 'like', '%' . $where['model_name'] . '%');
        }
        if (!empty($where['field_name'])) {
            $query->where('n.field_name', 'like', '%' . $where['field_name'] . '%');
        }

        return $this->pageQuery($query, function ($item) {
            $item['field_type_name'] = QuotationV2Dict::getFieldTypeName((string)($item['field_type'] ?? ''));
            $item['content_type_name'] = $item['field_type_name'];
            return $item;
        });
    }

    public function getLogs(array $where = []): array
    {
        $query = (new QuotationSyncLog())->where([['site_id', '=', $this->site_id]])
            ->withSearch(['dataset_id'], $where)
            ->field('id,site_id,dataset_id,quotation_id,channel_key,http_code,duration,request_url,request_params,stats,warnings,status,imported,error_message,create_at,update_at')
            ->order('id desc');

        return $this->pageQuery($query, function ($item) {
            $item['create_at_text'] = $this->formatTimeValue($item['create_at'] ?? 0);
            $item['update_at_text'] = $this->formatTimeValue($item['update_at'] ?? 0);
            $item['status_name'] = (int)($item['status'] ?? 0) === QuotationV2Dict::SYNC_STATUS_SUCCESS ? '成功' : '失败';
            $item['imported_name'] = (int)($item['imported'] ?? 0) === 1 ? '已导入' : '仅预览';
            return $item;
        });
    }

    private function applyCommonFilters($query, array $where, string $alias): void
    {
        foreach (['dataset_id', 'model_id', 'capacity_id', 'field_id'] as $field) {
            if (isset($where[$field]) && $where[$field] !== '') {
                $query->where($alias . '.' . $field, '=', $where[$field]);
            }
        }
    }

    private function applyPriceDateFilter($query, array $where, string $alias): void
    {
        $query->where($alias . '.price_date', '=', $this->resolvePriceDate($where));
    }

    private function resolvePriceDate(array $where): string
    {
        $date = trim((string)($where['price_date'] ?? ''));
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return date('Y-m-d');
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
}
