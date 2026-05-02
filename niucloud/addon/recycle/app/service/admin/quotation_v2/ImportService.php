<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation_v2;

use addon\recycle\app\dict\quotation\QuotationV2Dict;
use addon\recycle\app\model\quotation_v2\QuotationCapacity;
use addon\recycle\app\model\quotation_v2\QuotationDataset;
use addon\recycle\app\model\quotation_v2\QuotationField;
use addon\recycle\app\model\quotation_v2\QuotationModel as QuotationV2Model;
use addon\recycle\app\model\quotation_v2\QuotationNote;
use addon\recycle\app\model\quotation_v2\QuotationPrice;
use core\base\BaseAdminService;
use think\facade\Db;

/**
 * 报价 2.0 标准数据导入
 */
class ImportService extends BaseAdminService
{
    public function import(array $dataset, array $parsed): array
    {
        $datasetId = (int)$dataset['id'];
        $priceDate = date('Y-m-d');
        $stats = [
            'models_saved' => 0,
            'capacities_saved' => 0,
            'price_fields_saved' => 0,
            'note_fields_saved' => 0,
            'prices_saved' => 0,
            'notes_saved' => 0,
        ];

        Db::startTrans();
        try {
            $modelMap = $this->upsertModels($dataset, $parsed['models'] ?? [], $stats);
            $capacityMap = $this->upsertCapacities($dataset, $parsed['models'] ?? [], $modelMap, $stats);
            $fieldMap = $this->upsertFields($dataset, $parsed['fields'] ?? [], $stats);
            $this->upsertPrices($dataset, $parsed['prices'] ?? [], $modelMap, $capacityMap, $fieldMap, $priceDate, $stats);
            $this->upsertNotes($dataset, $parsed['notes'] ?? [], $modelMap, $capacityMap, $fieldMap, $stats);

            (new QuotationDataset())->where([
                ['id', '=', $datasetId],
                ['site_id', '=', $this->site_id],
            ])->update([
                'last_sync_at' => time(),
                'last_sync_status' => QuotationV2Dict::SYNC_STATUS_SUCCESS,
                'last_sync_message' => '同步成功',
                'last_sync_summary' => $stats,
                'update_at' => time(),
            ]);

            Db::commit();
            return $stats;
        } catch (\Exception $e) {
            Db::rollback();
            (new QuotationDataset())->where([
                ['id', '=', $datasetId],
                ['site_id', '=', $this->site_id],
            ])->update([
                'last_sync_at' => time(),
                'last_sync_status' => QuotationV2Dict::SYNC_STATUS_FAILED,
                'last_sync_message' => $e->getMessage(),
                'update_at' => time(),
            ]);
            throw $e;
        }
    }

    private function upsertModels(array $dataset, array $models, array &$stats): array
    {
        $map = [];
        $model = new QuotationV2Model();
        foreach ($models as $item) {
            $externalGoodsId = (int)$item['external_goods_id'];
            $row = $model->where([
                ['site_id', '=', $this->site_id],
                ['dataset_id', '=', (int)$dataset['id']],
                ['external_goods_id', '=', $externalGoodsId],
            ])->findOrEmpty();

            $data = [
                'site_id' => $this->site_id,
                'dataset_id' => (int)$dataset['id'],
                'quotation_id' => (int)$dataset['quotation_id'],
                'price_name' => (string)$dataset['price_name'],
                'channel_key' => (string)($dataset['channel_key'] ?? 'chaoniu'),
                'external_goods_id' => $externalGoodsId,
                'group_key' => (int)($item['group_key'] ?? 0),
                'raw_data' => $item['raw_data'] ?? [],
                'update_at' => time(),
            ];

            if ($row->isEmpty()) {
                $data['model_name'] = (string)$item['model_name'];
                $data['follow_crawler'] = QuotationV2Dict::FOLLOW_CRAWLER;
                $data['status'] = QuotationV2Dict::STATUS_ENABLED;
                $data['create_at'] = time();
                $row = $model->create($data);
            } else {
                if ($this->canCrawlerUpdate($row->toArray())) {
                    $data['model_name'] = (string)$item['model_name'];
                    $data['status'] = QuotationV2Dict::STATUS_ENABLED;
                }
                $row->save($data);
            }

            $map[$externalGoodsId] = (int)$row->id;
            $stats['models_saved']++;
        }

        return $map;
    }

    private function upsertCapacities(array $dataset, array $models, array $modelMap, array &$stats): array
    {
        $map = [];
        $model = new QuotationCapacity();
        foreach ($models as $item) {
            $externalGoodsId = (int)$item['external_goods_id'];
            $modelId = $modelMap[$externalGoodsId] ?? 0;
            if ($modelId <= 0) {
                continue;
            }

            foreach (($item['capacities'] ?? []) as $capacity) {
                $capacityAnswerId = (int)$capacity['capacity_answer_id'];
                $row = $model->where([
                    ['site_id', '=', $this->site_id],
                    ['dataset_id', '=', (int)$dataset['id']],
                    ['model_id', '=', $modelId],
                    ['capacity_answer_id', '=', $capacityAnswerId],
                ])->findOrEmpty();

                $data = [
                    'site_id' => $this->site_id,
                    'dataset_id' => (int)$dataset['id'],
                    'quotation_id' => (int)$dataset['quotation_id'],
                    'model_id' => $modelId,
                    'external_goods_id' => $externalGoodsId,
                    'capacity_answer_id' => $capacityAnswerId,
                    'raw_data' => $capacity['raw_data'] ?? [],
                    'update_at' => time(),
                ];

                if ($row->isEmpty()) {
                    $data['capacity_name'] = (string)$capacity['capacity_name'];
                    $data['follow_crawler'] = QuotationV2Dict::FOLLOW_CRAWLER;
                    $data['status'] = QuotationV2Dict::STATUS_ENABLED;
                    $data['create_at'] = time();
                    $row = $model->create($data);
                } else {
                    if ($this->canCrawlerUpdate($row->toArray())) {
                        $data['capacity_name'] = (string)$capacity['capacity_name'];
                        $data['status'] = QuotationV2Dict::STATUS_ENABLED;
                    }
                    $row->save($data);
                }

                $map[$externalGoodsId . '#' . $capacityAnswerId] = (int)$row->id;
                $stats['capacities_saved']++;
            }
        }

        return $map;
    }

    private function upsertFields(array $dataset, array $fields, array &$stats): array
    {
        $map = [];
        $model = new QuotationField();
        foreach ($fields as $field) {
            $questionId = (int)$field['question_id'];
            $fieldType = (string)$field['field_type'];
            $fieldName = (string)$field['field_name'];
            $queryWhere = [
                ['site_id', '=', $this->site_id],
                ['dataset_id', '=', (int)$dataset['id']],
            ];
            if ($questionId > 0) {
                $queryWhere[] = ['question_id', '=', $questionId];
            } else {
                $queryWhere[] = ['field_type', '=', $fieldType];
                $queryWhere[] = ['field_name', '=', $fieldName];
            }
            $row = $model->where($queryWhere)->findOrEmpty();

            $data = [
                'site_id' => $this->site_id,
                'dataset_id' => (int)$dataset['id'],
                'quotation_id' => (int)$dataset['quotation_id'],
                'question_id' => $questionId,
                'raw_data' => $field['raw_data'] ?? [],
                'update_at' => time(),
            ];

            if ($row->isEmpty()) {
                $data['field_name'] = $fieldName;
                $data['field_type'] = $fieldType;
                $data['sort'] = (int)($field['sort'] ?? 0);
                $data['follow_crawler'] = QuotationV2Dict::FOLLOW_CRAWLER;
                $data['status'] = QuotationV2Dict::STATUS_ENABLED;
                $data['create_at'] = time();
                $row = $model->create($data);
            } else {
                if ($this->canCrawlerUpdate($row->toArray())) {
                    $data['field_name'] = $fieldName;
                    $data['field_type'] = $fieldType;
                    $data['sort'] = (int)($field['sort'] ?? 0);
                    $data['status'] = QuotationV2Dict::STATUS_ENABLED;
                }
                $row->save($data);
            }

            $map[$fieldType . ':' . $questionId . ':' . $fieldName] = (int)$row->id;
            if ($fieldType === QuotationV2Dict::FIELD_TYPE_PRICE) {
                $stats['price_fields_saved']++;
            } elseif ($fieldType === QuotationV2Dict::FIELD_TYPE_ADJUSTMENT) {
                $stats['adjustment_fields_saved'] = ($stats['adjustment_fields_saved'] ?? 0) + 1;
            } elseif ($fieldType === QuotationV2Dict::FIELD_TYPE_NOTE) {
                $stats['note_fields_saved']++;
            }
        }

        return $map;
    }

    private function upsertPrices(array $dataset, array $prices, array $modelMap, array $capacityMap, array $fieldMap, string $priceDate, array &$stats): void
    {
        $model = new QuotationPrice();
        foreach ($prices as $price) {
            $externalGoodsId = (int)$price['external_goods_id'];
            $capacityAnswerId = (int)$price['capacity_answer_id'];
            $fieldKey = QuotationV2Dict::FIELD_TYPE_PRICE . ':' . (int)$price['question_id'] . ':' . (string)$price['field_name'];
            $modelId = $modelMap[$externalGoodsId] ?? 0;
            $capacityId = $capacityMap[$externalGoodsId . '#' . $capacityAnswerId] ?? 0;
            $fieldId = $fieldMap[$fieldKey] ?? 0;
            if ($modelId <= 0 || $capacityId <= 0 || $fieldId <= 0) {
                continue;
            }

            $row = $model->where([
                ['site_id', '=', $this->site_id],
                ['dataset_id', '=', (int)$dataset['id']],
                ['model_id', '=', $modelId],
                ['capacity_id', '=', $capacityId],
                ['field_id', '=', $fieldId],
                ['price_date', '=', $priceDate],
            ])->findOrEmpty();

            $crawlerPrice = (float)$price['crawler_price'];
            $data = [
                'site_id' => $this->site_id,
                'dataset_id' => (int)$dataset['id'],
                'quotation_id' => (int)$dataset['quotation_id'],
                'model_id' => $modelId,
                'capacity_id' => $capacityId,
                'field_id' => $fieldId,
                'external_goods_id' => $externalGoodsId,
                'capacity_answer_id' => $capacityAnswerId,
                'field_name' => (string)$price['field_name'],
                'crawler_price' => $crawlerPrice,
                'adjust_type' => 1,
                'adjust_value' => 0,
                'final_price' => $crawlerPrice,
                'price_date' => $priceDate,
                'is_current' => 1,
                'locked' => 0,
                'raw_item' => $price['raw_item'] ?? [],
                'update_at' => time(),
            ];

            if ($row->isEmpty()) {
                $data['create_at'] = time();
                $model->create($data);
            } elseif ((int)($row['locked'] ?? 0) !== 1) {
                $row->save($data);
            }
            $stats['prices_saved']++;
        }
    }

    private function upsertNotes(array $dataset, array $notes, array $modelMap, array $capacityMap, array $fieldMap, array &$stats): void
    {
        $model = new QuotationNote();
        foreach ($notes as $note) {
            $externalGoodsId = (int)$note['external_goods_id'];
            $capacityAnswerId = (int)$note['capacity_answer_id'];
            $fieldType = (string)($note['field_type'] ?? QuotationV2Dict::FIELD_TYPE_NOTE);
            $fieldKey = $fieldType . ':' . (int)$note['question_id'] . ':' . (string)$note['field_name'];
            $modelId = $modelMap[$externalGoodsId] ?? 0;
            $capacityId = $capacityMap[$externalGoodsId . '#' . $capacityAnswerId] ?? 0;
            $fieldId = $fieldMap[$fieldKey] ?? 0;
            if ($modelId <= 0 || $capacityId <= 0 || $fieldId <= 0) {
                continue;
            }

            $row = $model->where([
                ['site_id', '=', $this->site_id],
                ['dataset_id', '=', (int)$dataset['id']],
                ['model_id', '=', $modelId],
                ['capacity_id', '=', $capacityId],
                ['field_id', '=', $fieldId],
            ])->findOrEmpty();

            $data = [
                'site_id' => $this->site_id,
                'dataset_id' => (int)$dataset['id'],
                'quotation_id' => (int)$dataset['quotation_id'],
                'model_id' => $modelId,
                'capacity_id' => $capacityId,
                'field_id' => $fieldId,
                'external_goods_id' => $externalGoodsId,
                'capacity_answer_id' => $capacityAnswerId,
                'field_name' => (string)$note['field_name'],
                'content_html' => (string)$note['content_html'],
                'content_text' => (string)$note['content_text'],
                'merge_items' => $note['merge_items'] ?? [],
                'follow_crawler' => QuotationV2Dict::FOLLOW_CRAWLER,
                'status' => QuotationV2Dict::STATUS_ENABLED,
                'raw_item' => $note['raw_item'] ?? [],
                'update_at' => time(),
            ];

            if ($row->isEmpty()) {
                $data['create_at'] = time();
                $model->create($data);
            } else {
                if (!$this->canCrawlerUpdate($row->toArray())) {
                    unset($data['content_html'], $data['content_text'], $data['status'], $data['follow_crawler']);
                }
                $row->save($data);
            }
            if ($fieldType === QuotationV2Dict::FIELD_TYPE_ADJUSTMENT) {
                $stats['adjustments_saved'] = ($stats['adjustments_saved'] ?? 0) + 1;
            } else {
                $stats['notes_saved']++;
            }
        }
    }

    private function canCrawlerUpdate(array $row): bool
    {
        return (int)($row['follow_crawler'] ?? QuotationV2Dict::FOLLOW_CRAWLER) === QuotationV2Dict::FOLLOW_CRAWLER;
    }
}
