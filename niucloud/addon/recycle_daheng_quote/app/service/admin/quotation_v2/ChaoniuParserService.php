<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\admin\quotation_v2;

use addon\recycle_daheng_quote\app\dict\quotation\QuotationV2Dict;
use core\base\BaseAdminService;

/**
 * 超牛报价响应解析
 */
class ChaoniuParserService extends BaseAdminService
{
    public function parse(array $dataset, array $response): array
    {
        $data = $response['data'] ?? [];
        $skuGroups = is_array($data['sku'] ?? null) ? $data['sku'] : [];
        $models = [];
        $fields = [];
        $prices = [];
        $notes = [];
        $warnings = [];

        $remark = $data['remark'] ?? [];
        if (!empty($remark)) {
            $fields[$this->fieldKey(0, '报价说明', QuotationV2Dict::FIELD_TYPE_REMARK)] = [
                'question_id' => 0,
                'field_name' => '报价说明',
                'field_type' => QuotationV2Dict::FIELD_TYPE_REMARK,
                'sort' => 0,
                'raw_data' => ['remark' => $remark],
            ];
        }

        foreach ($skuGroups as $groupIndex => $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($group as $itemIndex => $item) {
                if (!is_array($item)) {
                    continue;
                }
                $externalGoodsId = (int)($item['goods_id'] ?? 0);
                $modelName = trim((string)($item['goods_name'] ?? ''));
                if ($externalGoodsId <= 0 || $modelName === '') {
                    $warnings[] = '第' . ($groupIndex + 1) . '组第' . ($itemIndex + 1) . '条缺少型号信息，已跳过';
                    continue;
                }

                $capacity = $this->extractCapacity($item['general_attr'] ?? []);
                if ($capacity['capacity_answer_id'] <= 0 || $capacity['capacity_name'] === '') {
                    $warnings[] = $modelName . ' 缺少容量信息，已跳过价格解析';
                    continue;
                }

                $modelKey = (string)$externalGoodsId;
                if (!isset($models[$modelKey])) {
                    $models[$modelKey] = [
                        'external_goods_id' => $externalGoodsId,
                        'model_name' => $modelName,
                        'group_key' => (int)($capacity['group_key'] ?: ($item['group_key'] ?? 0)),
                        'capacities' => [],
                        'raw_data' => [
                            'goods_id' => $externalGoodsId,
                            'goods_name' => $modelName,
                        ],
                    ];
                }

                $capacityKey = (string)$capacity['capacity_answer_id'];
                if (!isset($models[$modelKey]['capacities'][$capacityKey])) {
                    $models[$modelKey]['capacities'][$capacityKey] = [
                        'capacity_answer_id' => $capacity['capacity_answer_id'],
                        'capacity_name' => $capacity['capacity_name'],
                        'raw_data' => $capacity['raw_data'],
                    ];
                }

                foreach (($item['config_attr'] ?? []) as $attr) {
                    if (!is_array($attr)) {
                        continue;
                    }
                    $questionId = (int)($attr['question_id'] ?? 0);
                    $questionName = trim((string)($attr['question_name'] ?? ''));
                    if ($questionName === '') {
                        continue;
                    }

                    $fieldType = $this->resolveFieldType($attr, $dataset);
                    $fieldKey = $this->fieldKey($questionId, $questionName, $fieldType);
                    if (!isset($fields[$fieldKey])) {
                        $fields[$fieldKey] = [
                            'question_id' => $questionId,
                            'field_name' => $questionName,
                            'field_type' => $fieldType,
                            'sort' => count($fields) + 1,
                            'raw_data' => $this->compactAttr($attr),
                        ];
                    }

                    if ($fieldType === QuotationV2Dict::FIELD_TYPE_PRICE) {
                        $price = $this->parsePrice($attr['answer_name'] ?? null);
                        if ($price === null) {
                            $warnings[] = $modelName . ' ' . $capacity['capacity_name'] . ' 的 ' . $questionName . ' 不是有效价格，已跳过';
                            continue;
                        }
                        $prices[] = [
                            'external_goods_id' => $externalGoodsId,
                            'capacity_answer_id' => $capacity['capacity_answer_id'],
                            'question_id' => $questionId,
                            'field_name' => $questionName,
                            'crawler_price' => $price,
                            'raw_item' => $this->compactAttr($attr),
                        ];
                    } elseif (in_array($fieldType, [
                        QuotationV2Dict::FIELD_TYPE_ADJUSTMENT,
                        QuotationV2Dict::FIELD_TYPE_NOTE,
                    ], true)) {
                        $contentHtml = (string)($attr['answer_name'] ?? '');
                        if ($contentHtml === '') {
                            continue;
                        }
                        $scopeItems = $this->resolveMergeItems($attr, $externalGoodsId, $capacity['capacity_answer_id']);
                        foreach ($scopeItems as $mergeItem) {
                            $notes[] = [
                                'external_goods_id' => $mergeItem['external_goods_id'],
                                'capacity_answer_id' => $mergeItem['capacity_answer_id'],
                                'question_id' => $questionId,
                                'field_name' => $questionName,
                                'field_type' => $fieldType,
                                'content_html' => $contentHtml,
                                'content_text' => $this->htmlToText($contentHtml),
                                'merge_items' => $attr['merge_item'] ?? [],
                                'raw_item' => array_merge($this->compactAttr($attr), [
                                    'scope_mode' => !empty($attr['merge_item'] ?? []) ? 'merge_item' : 'current_sku',
                                ]),
                            ];
                        }
                    }
                }
            }
        }

        $models = array_values(array_map(function ($model) {
            $model['capacities'] = array_values($model['capacities']);
            return $model;
        }, $models));
        $fields = array_values($fields);

        return [
            'dataset' => [
                'id' => (int)($dataset['id'] ?? 0),
                'quotation_id' => (int)$dataset['quotation_id'],
                'price_name' => (string)$dataset['price_name'],
                'dataset_name' => (string)$dataset['dataset_name'],
                'channel_key' => (string)($dataset['channel_key'] ?? 'chaoniu'),
            ],
            'models' => $models,
            'fields' => $fields,
            'prices' => $prices,
            'notes' => $notes,
            'remark' => $remark,
            'stats' => [
                'model_count' => count($models),
                'capacity_count' => array_sum(array_map(function ($model) {
                    return count($model['capacities'] ?? []);
                }, $models)),
                'price_field_count' => count(array_filter($fields, function ($field) {
                    return $field['field_type'] === QuotationV2Dict::FIELD_TYPE_PRICE;
                })),
                'adjustment_field_count' => count(array_filter($fields, function ($field) {
                    return $field['field_type'] === QuotationV2Dict::FIELD_TYPE_ADJUSTMENT;
                })),
                'note_field_count' => count(array_filter($fields, function ($field) {
                    return $field['field_type'] === QuotationV2Dict::FIELD_TYPE_NOTE;
                })),
                'price_count' => count($prices),
                'adjustment_count' => count(array_filter($notes, function ($note) {
                    return ($note['field_type'] ?? '') === QuotationV2Dict::FIELD_TYPE_ADJUSTMENT;
                })),
                'note_count' => count(array_filter($notes, function ($note) {
                    return ($note['field_type'] ?? '') === QuotationV2Dict::FIELD_TYPE_NOTE;
                })),
                'warning_count' => count($warnings),
            ],
            'warnings' => $warnings,
        ];
    }

    private function extractCapacity(array $generalAttr): array
    {
        foreach ($generalAttr as $attr) {
            if (!is_array($attr)) {
                continue;
            }
            $questionName = (string)($attr['question_name'] ?? '');
            if (strpos($questionName, '内存') !== false || strpos($questionName, '容量') !== false) {
                return [
                    'capacity_answer_id' => (int)($attr['answer_id'] ?? 0),
                    'capacity_name' => (string)($attr['answer_name'] ?? ''),
                    'group_key' => (int)($attr['group_key'] ?? 0),
                    'raw_data' => $this->compactAttr($attr),
                ];
            }
        }

        return [
            'capacity_answer_id' => 0,
            'capacity_name' => '',
            'group_key' => 0,
            'raw_data' => [],
        ];
    }

    private function resolveFieldType(array $attr, array $dataset): string
    {
        $parseRule = is_array($dataset['parse_rule'] ?? null) ? $dataset['parse_rule'] : [];
        $overrides = is_array($parseRule['field_overrides'] ?? null) ? $parseRule['field_overrides'] : [];
        $questionId = (string)(int)($attr['question_id'] ?? 0);
        $questionName = trim((string)($attr['question_name'] ?? ''));

        $override = $overrides[$questionId] ?? ($overrides[$questionName] ?? null);
        if (in_array($override, [
            QuotationV2Dict::FIELD_TYPE_PRICE,
            QuotationV2Dict::FIELD_TYPE_ADJUSTMENT,
            QuotationV2Dict::FIELD_TYPE_NOTE,
            QuotationV2Dict::FIELD_TYPE_REMARK,
        ], true)) {
            return $override;
        }

        $calcType = (int)($attr['is_calc'] ?? 0);
        if ($calcType === 1) {
            return QuotationV2Dict::FIELD_TYPE_PRICE;
        }
        if ($calcType === 2) {
            return QuotationV2Dict::FIELD_TYPE_ADJUSTMENT;
        }

        return QuotationV2Dict::FIELD_TYPE_NOTE;
    }

    private function parsePrice($value): ?float
    {
        $text = trim((string)$value);
        if ($text === '' || !is_numeric($text)) {
            return null;
        }

        return round((float)$text, 2);
    }

    private function resolveMergeItems(array $attr, int $fallbackGoodsId, int $fallbackCapacityAnswerId): array
    {
        $mergeItems = $attr['merge_item'] ?? [];
        if (!is_array($mergeItems) || empty($mergeItems)) {
            return [[
                'external_goods_id' => $fallbackGoodsId,
                'capacity_answer_id' => $fallbackCapacityAnswerId,
            ]];
        }

        $result = [];
        foreach ($mergeItems as $item) {
            $parts = explode('#', (string)$item);
            $goodsId = (int)($parts[0] ?? 0);
            $capacityId = (int)($parts[1] ?? 0);
            if ($goodsId > 0 && $capacityId > 0) {
                $result[$goodsId . '#' . $capacityId] = [
                    'external_goods_id' => $goodsId,
                    'capacity_answer_id' => $capacityId,
                ];
            }
        }

        return array_values($result);
    }

    private function htmlToText(string $html): string
    {
        $text = strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', (string)$text);
        return trim((string)$text);
    }

    private function fieldKey(int $questionId, string $questionName, string $fieldType): string
    {
        return $fieldType . ':' . $questionId . ':' . $questionName;
    }

    private function compactAttr(array $attr): array
    {
        return [
            'question_id' => $attr['question_id'] ?? 0,
            'question_name' => $attr['question_name'] ?? '',
            'answer_id' => $attr['answer_id'] ?? 0,
            'answer_name' => $attr['answer_name'] ?? '',
            'attr_type' => $attr['attr_type'] ?? 0,
            'group_key' => $attr['group_key'] ?? 0,
            'mark_type' => $attr['mark_type'] ?? 0,
            'merge_item' => $attr['merge_item'] ?? null,
            'is_calc' => $attr['is_calc'] ?? 0,
        ];
    }
}
