<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation_v2;

use addon\recycle\app\dict\quotation\QuotationV2Dict;
use addon\recycle\app\model\quotation_v2\QuotationCapacity;
use addon\recycle\app\model\quotation_v2\QuotationDataset;
use addon\recycle\app\model\quotation_v2\QuotationField;
use addon\recycle\app\model\quotation_v2\QuotationModel;
use addon\recycle\app\model\quotation_v2\QuotationNote;
use addon\recycle\app\model\quotation_v2\QuotationPrice;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 报价 2.0 明细管理
 */
class ManageService extends BaseAdminService
{
    public function addModel(array $data): int
    {
        $dataset = $this->getDataset((int)($data['dataset_id'] ?? 0));
        $modelName = trim((string)($data['model_name'] ?? ''));
        if ($modelName === '') {
            throw new CommonException('型号名称不能为空');
        }
        $this->assertUniqueModelName((int)$dataset['id'], $modelName);

        $externalGoodsId = (int)($data['external_goods_id'] ?? 0);
        if ($externalGoodsId === 0) {
            $externalGoodsId = $this->makeCustomExternalId(new QuotationModel(), 'external_goods_id', (int)$dataset['id']);
        }
        $this->assertUniqueExternalId(new QuotationModel(), 'external_goods_id', (int)$dataset['id'], $externalGoodsId, '该型号ID已存在');

        $row = (new QuotationModel())->create([
            'site_id' => $this->site_id,
            'dataset_id' => (int)$dataset['id'],
            'quotation_id' => (int)$dataset['quotation_id'],
            'price_name' => (string)$dataset['price_name'],
            'channel_key' => (string)$dataset['channel_key'],
            'external_goods_id' => $externalGoodsId,
            'model_name' => $modelName,
            'group_key' => (int)($data['group_key'] ?? 0),
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? QuotationV2Dict::STATUS_ENABLED),
            'follow_crawler' => QuotationV2Dict::FOLLOW_CUSTOM,
            'raw_data' => ['source' => 'manual'],
            'create_at' => time(),
            'update_at' => time(),
        ]);

        return (int)$row->id;
    }

    public function addCapacity(array $data): int
    {
        $dataset = $this->getDataset((int)($data['dataset_id'] ?? 0));
        $model = $this->getDatasetRow(new QuotationModel(), (int)($data['model_id'] ?? 0), (int)$dataset['id'], '型号不存在');
        $capacityName = trim((string)($data['capacity_name'] ?? ''));
        if ($capacityName === '') {
            throw new CommonException('容量名称不能为空');
        }
        $this->assertUniqueCapacityName((int)$dataset['id'], (int)$model['id'], $capacityName);

        $capacityAnswerId = (int)($data['capacity_answer_id'] ?? 0);
        if ($capacityAnswerId === 0) {
            $capacityAnswerId = $this->makeCustomExternalId(new QuotationCapacity(), 'capacity_answer_id', (int)$dataset['id'], [
                ['model_id', '=', (int)$model['id']],
            ]);
        }
        $this->assertUniqueExternalId(new QuotationCapacity(), 'capacity_answer_id', (int)$dataset['id'], $capacityAnswerId, '该型号下的容量ID已存在', [
            ['model_id', '=', (int)$model['id']],
        ]);

        $row = (new QuotationCapacity())->create([
            'site_id' => $this->site_id,
            'dataset_id' => (int)$dataset['id'],
            'quotation_id' => (int)$dataset['quotation_id'],
            'model_id' => (int)$model['id'],
            'external_goods_id' => (int)$model['external_goods_id'],
            'capacity_answer_id' => $capacityAnswerId,
            'capacity_name' => $capacityName,
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? QuotationV2Dict::STATUS_ENABLED),
            'follow_crawler' => QuotationV2Dict::FOLLOW_CUSTOM,
            'raw_data' => ['source' => 'manual'],
            'create_at' => time(),
            'update_at' => time(),
        ]);

        return (int)$row->id;
    }

    public function addField(array $data): int
    {
        $dataset = $this->getDataset((int)($data['dataset_id'] ?? 0));
        $fieldName = trim((string)($data['field_name'] ?? ''));
        if ($fieldName === '') {
            throw new CommonException('字段名称不能为空');
        }
        $fieldType = (string)($data['field_type'] ?? QuotationV2Dict::FIELD_TYPE_ADJUSTMENT);
        $this->checkFieldType($fieldType);
        $this->assertUniqueFieldName((int)$dataset['id'], $fieldType, $fieldName);

        $row = (new QuotationField())->create([
            'site_id' => $this->site_id,
            'dataset_id' => (int)$dataset['id'],
            'quotation_id' => (int)$dataset['quotation_id'],
            'question_id' => (int)($data['question_id'] ?? 0),
            'field_name' => $fieldName,
            'field_type' => $fieldType,
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? QuotationV2Dict::STATUS_ENABLED),
            'follow_crawler' => QuotationV2Dict::FOLLOW_CUSTOM,
            'raw_data' => ['source' => 'manual'],
            'create_at' => time(),
            'update_at' => time(),
        ]);

        return (int)$row->id;
    }

    public function addNote(array $data): int
    {
        $dataset = $this->getDataset((int)($data['dataset_id'] ?? 0));
        $model = $this->getDatasetRow(new QuotationModel(), (int)($data['model_id'] ?? 0), (int)$dataset['id'], '型号不存在');
        $field = $this->getDatasetRow(new QuotationField(), (int)($data['field_id'] ?? 0), (int)$dataset['id'], '字段项不存在');
        if (!in_array((string)$field['field_type'], [QuotationV2Dict::FIELD_TYPE_ADJUSTMENT, QuotationV2Dict::FIELD_TYPE_NOTE], true)) {
            throw new CommonException('只能给价格调整项或附加说明项添加内容');
        }

        $contentHtml = (string)($data['content_html'] ?? '');
        $contentText = trim((string)($data['content_text'] ?? ''));
        if ($contentText === '') {
            $contentText = $this->htmlToText($contentHtml);
        }
        if ($contentText === '') {
            throw new CommonException('内容不能为空');
        }

        $capacityIds = $data['capacity_ids'] ?? [];
        if (!is_array($capacityIds)) {
            $capacityIds = [];
        }
        if (empty($capacityIds)) {
            $capacityIds = [(int)($data['capacity_id'] ?? 0)];
        }
        $capacityIds = array_values(array_unique(array_filter(array_map('intval', $capacityIds))));
        if (empty($capacityIds)) {
            throw new CommonException('请选择容量');
        }

        $lastId = 0;
        foreach ($capacityIds as $capacityId) {
            $capacity = $this->getDatasetRow(new QuotationCapacity(), $capacityId, (int)$dataset['id'], '容量不存在');
            if ((int)$capacity['model_id'] !== (int)$model['id']) {
                throw new CommonException('容量不属于当前型号');
            }
            $lastId = $this->saveNoteContent($dataset->toArray(), $model->toArray(), $capacity->toArray(), $field->toArray(), $data, $contentHtml, $contentText);
        }

        return $lastId;
    }

    private function saveNoteContent(array $dataset, array $model, array $capacity, array $field, array $data, string $contentHtml, string $contentText): int
    {
        $noteModel = new QuotationNote();
        $row = $noteModel->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', (int)$dataset['id']],
            ['model_id', '=', (int)$model['id']],
            ['capacity_id', '=', (int)$capacity['id']],
            ['field_id', '=', (int)$field['id']],
        ])->findOrEmpty();

        $saveData = [
            'site_id' => $this->site_id,
            'dataset_id' => (int)$dataset['id'],
            'quotation_id' => (int)$dataset['quotation_id'],
            'model_id' => (int)$model['id'],
            'capacity_id' => (int)$capacity['id'],
            'field_id' => (int)$field['id'],
            'external_goods_id' => (int)$model['external_goods_id'],
            'capacity_answer_id' => (int)$capacity['capacity_answer_id'],
            'field_name' => (string)$field['field_name'],
            'content_html' => $contentHtml !== '' ? $contentHtml : $contentText,
            'content_text' => $contentText,
            'merge_items' => [],
            'status' => (int)($data['status'] ?? QuotationV2Dict::STATUS_ENABLED),
            'follow_crawler' => QuotationV2Dict::FOLLOW_CUSTOM,
            'raw_item' => ['source' => 'manual'],
            'update_at' => time(),
        ];

        if ($row->isEmpty()) {
            $saveData['create_at'] = time();
            $row = $noteModel->create($saveData);
        } else {
            $row->save($saveData);
        }

        return (int)$row->id;
    }

    public function editModel(int $id, array $data): bool
    {
        $info = $this->getRow(new QuotationModel(), $id, '型号不存在');
        $modelName = trim((string)($data['model_name'] ?? $info['model_name']));
        if ($modelName === '') {
            throw new CommonException('型号名称不能为空');
        }
        $this->assertUniqueModelName((int)$info['dataset_id'], $modelName, $id);

        return $info->save([
            'model_name' => $modelName,
            'group_key' => (int)($data['group_key'] ?? $info['group_key'] ?? 0),
            'sort' => (int)($data['sort'] ?? $info['sort'] ?? 0),
            'status' => (int)($data['status'] ?? $info['status'] ?? QuotationV2Dict::STATUS_ENABLED),
            'follow_crawler' => (int)($data['follow_crawler'] ?? $info['follow_crawler'] ?? QuotationV2Dict::FOLLOW_CRAWLER),
            'update_at' => time(),
        ]);
    }

    public function editCapacity(int $id, array $data): bool
    {
        $info = $this->getRow(new QuotationCapacity(), $id, '容量不存在');
        $datasetId = (int)$info['dataset_id'];
        $oldModelId = (int)$info['model_id'];
        $modelId = (int)($data['model_id'] ?? $info['model_id']);
        $model = $this->getDatasetRow(new QuotationModel(), $modelId, $datasetId, '型号不存在');
        $capacityName = trim((string)($data['capacity_name'] ?? $info['capacity_name']));
        if ($capacityName === '') {
            throw new CommonException('容量名称不能为空');
        }
        $this->assertUniqueCapacityName($datasetId, $modelId, $capacityName, $id);

        Db::startTrans();
        try {
            $saveData = [
                'model_id' => $modelId,
                'external_goods_id' => (int)$model['external_goods_id'],
                'capacity_name' => $capacityName,
                'sort' => (int)($data['sort'] ?? $info['sort'] ?? 0),
                'status' => (int)($data['status'] ?? $info['status'] ?? QuotationV2Dict::STATUS_ENABLED),
                'follow_crawler' => (int)($data['follow_crawler'] ?? $info['follow_crawler'] ?? QuotationV2Dict::FOLLOW_CRAWLER),
                'update_at' => time(),
            ];
            $result = $info->save($saveData);
            if ($oldModelId !== $modelId) {
                $relationData = [
                    'model_id' => $modelId,
                    'external_goods_id' => (int)$model['external_goods_id'],
                    'update_at' => time(),
                ];
                (new QuotationPrice())->where([
                    ['site_id', '=', $this->site_id],
                    ['capacity_id', '=', $id],
                ])->update($relationData);
                (new QuotationNote())->where([
                    ['site_id', '=', $this->site_id],
                    ['capacity_id', '=', $id],
                ])->update($relationData);
            }
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function editField(int $id, array $data): bool
    {
        $info = $this->getRow(new QuotationField(), $id, '字段项不存在');
        $fieldType = (string)($data['field_type'] ?? $info['field_type']);
        $this->checkFieldType($fieldType);

        $fieldName = trim((string)($data['field_name'] ?? $info['field_name']));
        if ($fieldName === '') {
            throw new CommonException('字段名称不能为空');
        }
        $this->assertUniqueFieldName((int)$info['dataset_id'], $fieldType, $fieldName, $id);

        Db::startTrans();
        try {
            $result = $info->save([
                'field_name' => $fieldName,
                'field_type' => $fieldType,
                'sort' => (int)($data['sort'] ?? $info['sort'] ?? 0),
                'status' => (int)($data['status'] ?? $info['status'] ?? QuotationV2Dict::STATUS_ENABLED),
                'follow_crawler' => (int)($data['follow_crawler'] ?? $info['follow_crawler'] ?? QuotationV2Dict::FOLLOW_CUSTOM),
                'update_at' => time(),
            ]);
            $this->saveFieldOverride($info->toArray(), $fieldType, $fieldName);
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function editNote(int $id, array $data): bool
    {
        $info = $this->getRow(new QuotationNote(), $id, '说明内容不存在');
        $datasetId = (int)$info['dataset_id'];
        $model = $this->getDatasetRow(new QuotationModel(), (int)($data['model_id'] ?? $info['model_id']), $datasetId, '型号不存在');
        $capacity = $this->getDatasetRow(new QuotationCapacity(), (int)($data['capacity_id'] ?? $info['capacity_id']), $datasetId, '容量不存在');
        $field = $this->getDatasetRow(new QuotationField(), (int)($data['field_id'] ?? $info['field_id']), $datasetId, '字段项不存在');
        if ((int)$capacity['model_id'] !== (int)$model['id']) {
            throw new CommonException('容量不属于当前型号');
        }
        if (!in_array((string)$field['field_type'], [QuotationV2Dict::FIELD_TYPE_ADJUSTMENT, QuotationV2Dict::FIELD_TYPE_NOTE], true)) {
            throw new CommonException('只能维护价格调整项或附加说明项内容');
        }
        $contentHtml = (string)($data['content_html'] ?? $info['content_html'] ?? '');
        $contentText = (string)($data['content_text'] ?? '');
        if ($contentText === '') {
            $contentText = $this->htmlToText($contentHtml);
        }
        if (trim($contentText) === '') {
            throw new CommonException('内容不能为空');
        }
        $this->assertUniqueNote($datasetId, (int)$model['id'], (int)$capacity['id'], (int)$field['id'], $id);

        return $info->save([
            'model_id' => (int)$model['id'],
            'capacity_id' => (int)$capacity['id'],
            'field_id' => (int)$field['id'],
            'external_goods_id' => (int)$model['external_goods_id'],
            'capacity_answer_id' => (int)$capacity['capacity_answer_id'],
            'field_name' => (string)$field['field_name'],
            'content_html' => $contentHtml,
            'content_text' => $contentText,
            'status' => (int)($data['status'] ?? $info['status'] ?? QuotationV2Dict::STATUS_ENABLED),
            'follow_crawler' => (int)($data['follow_crawler'] ?? $info['follow_crawler'] ?? QuotationV2Dict::FOLLOW_CUSTOM),
            'update_at' => time(),
        ]);
    }

    public function deleteModel(int $id): bool
    {
        $info = $this->getRow(new QuotationModel(), $id, '型号不存在');
        Db::startTrans();
        try {
            (new QuotationPrice())->where([
                ['site_id', '=', $this->site_id],
                ['model_id', '=', $id],
            ])->delete();
            (new QuotationNote())->where([
                ['site_id', '=', $this->site_id],
                ['model_id', '=', $id],
            ])->delete();
            (new QuotationCapacity())->where([
                ['site_id', '=', $this->site_id],
                ['model_id', '=', $id],
            ])->delete();
            $result = $info->delete();
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function deleteCapacity(int $id): bool
    {
        $info = $this->getRow(new QuotationCapacity(), $id, '容量不存在');
        Db::startTrans();
        try {
            (new QuotationPrice())->where([
                ['site_id', '=', $this->site_id],
                ['capacity_id', '=', $id],
            ])->delete();
            (new QuotationNote())->where([
                ['site_id', '=', $this->site_id],
                ['capacity_id', '=', $id],
            ])->delete();
            $result = $info->delete();
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function deleteField(int $id): bool
    {
        $info = $this->getRow(new QuotationField(), $id, '字段项不存在');
        Db::startTrans();
        try {
            (new QuotationPrice())->where([
                ['site_id', '=', $this->site_id],
                ['field_id', '=', $id],
            ])->delete();
            (new QuotationNote())->where([
                ['site_id', '=', $this->site_id],
                ['field_id', '=', $id],
            ])->delete();
            $result = $info->delete();
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function deleteNote(int $id): bool
    {
        return $this->getRow(new QuotationNote(), $id, '说明内容不存在')->delete();
    }

    private function getRow($model, int $id, string $message)
    {
        $info = $model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
        ])->findOrEmpty();
        if ($info->isEmpty()) {
            throw new CommonException($message);
        }
        return $info;
    }

    private function getDataset(int $datasetId)
    {
        return $this->getDatasetRow(new QuotationDataset(), $datasetId, 0, '报价单不存在');
    }

    private function getDatasetRow($model, int $id, int $datasetId, string $message)
    {
        $where = [
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
        ];
        if ($datasetId > 0) {
            $where[] = ['dataset_id', '=', $datasetId];
        }
        $info = $model->where($where)->findOrEmpty();
        if ($info->isEmpty()) {
            throw new CommonException($message);
        }
        return $info;
    }

    private function makeCustomExternalId($model, string $field, int $datasetId, array $extraWhere = []): int
    {
        for ($times = 0; $times < 20; $times++) {
            $id = -1 * random_int(100000, 999999999);
            $where = array_merge([
                ['site_id', '=', $this->site_id],
                ['dataset_id', '=', $datasetId],
                [$field, '=', $id],
            ], $extraWhere);
            if ($model->where($where)->count() === 0) {
                return $id;
            }
        }
        throw new CommonException('生成自定义ID失败，请重试');
    }

    private function checkFieldType(string $fieldType): void
    {
        if (!in_array($fieldType, [
            QuotationV2Dict::FIELD_TYPE_PRICE,
            QuotationV2Dict::FIELD_TYPE_ADJUSTMENT,
            QuotationV2Dict::FIELD_TYPE_NOTE,
            QuotationV2Dict::FIELD_TYPE_REMARK,
        ], true)) {
            throw new CommonException('字段类型不正确');
        }
    }

    private function saveFieldOverride(array $field, string $fieldType, string $fieldName): void
    {
        $dataset = (new QuotationDataset())->where([
            ['id', '=', (int)$field['dataset_id']],
            ['site_id', '=', $this->site_id],
        ])->findOrEmpty();
        if ($dataset->isEmpty()) {
            return;
        }

        $parseRule = is_array($dataset['parse_rule'] ?? null) ? $dataset['parse_rule'] : [];
        $parseRule['field_overrides'] = is_array($parseRule['field_overrides'] ?? null) ? $parseRule['field_overrides'] : [];

        $questionId = (int)($field['question_id'] ?? 0);
        if ($questionId > 0) {
            $parseRule['field_overrides'][(string)$questionId] = $fieldType;
        } else {
            unset($parseRule['field_overrides'][(string)($field['field_name'] ?? '')]);
            $parseRule['field_overrides'][$fieldName] = $fieldType;
        }

        $dataset->save([
            'parse_rule' => $parseRule,
            'update_at' => time(),
        ]);
    }

    private function assertUniqueModelName(int $datasetId, string $modelName, int $ignoreId = 0): void
    {
        $query = (new QuotationModel())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', $datasetId],
            ['model_name', '=', $modelName],
        ]);
        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->count() > 0) {
            throw new CommonException('该报价单下已存在同名型号');
        }
    }

    private function assertUniqueCapacityName(int $datasetId, int $modelId, string $capacityName, int $ignoreId = 0): void
    {
        $query = (new QuotationCapacity())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', $datasetId],
            ['model_id', '=', $modelId],
            ['capacity_name', '=', $capacityName],
        ]);
        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->count() > 0) {
            throw new CommonException('该型号下已存在同名容量');
        }
    }

    private function assertUniqueFieldName(int $datasetId, string $fieldType, string $fieldName, int $ignoreId = 0): void
    {
        $query = (new QuotationField())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', $datasetId],
            ['field_type', '=', $fieldType],
            ['field_name', '=', $fieldName],
        ]);
        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->count() > 0) {
            throw new CommonException('该报价单下已存在同名字段');
        }
    }

    private function assertUniqueExternalId($model, string $field, int $datasetId, int $externalId, string $message, array $extraWhere = []): void
    {
        $where = array_merge([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', $datasetId],
            [$field, '=', $externalId],
        ], $extraWhere);
        if ($model->where($where)->count() > 0) {
            throw new CommonException($message);
        }
    }

    private function assertUniqueNote(int $datasetId, int $modelId, int $capacityId, int $fieldId, int $ignoreId = 0): void
    {
        $query = (new QuotationNote())->where([
            ['site_id', '=', $this->site_id],
            ['dataset_id', '=', $datasetId],
            ['model_id', '=', $modelId],
            ['capacity_id', '=', $capacityId],
            ['field_id', '=', $fieldId],
        ]);
        if ($ignoreId > 0) {
            $query->where('id', '<>', $ignoreId);
        }
        if ($query->count() > 0) {
            throw new CommonException('该型号容量下已存在该字段内容，请直接编辑已有内容');
        }
    }

    private function htmlToText(string $html): string
    {
        $text = strip_tags(html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
        $text = preg_replace('/\s+/', ' ', (string)$text);
        return trim((string)$text);
    }
}
