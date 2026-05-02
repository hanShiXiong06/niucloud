<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation_v2;

use addon\recycle\app\dict\quotation\QuotationV2Dict;
use addon\recycle\app\model\quotation_v2\QuotationDataset;
use addon\recycle\app\model\quotation_v2\QuotationCapacity;
use addon\recycle\app\model\quotation_v2\QuotationField;
use addon\recycle\app\model\quotation_v2\QuotationModel as QuotationV2Model;
use addon\recycle\app\model\quotation_v2\QuotationNote;
use addon\recycle\app\model\quotation_v2\QuotationPrice;
use addon\recycle\app\model\quotation_v2\QuotationSyncLog;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 报价 2.0 数据集服务
 */
class DatasetService extends BaseAdminService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new QuotationDataset();
    }

    public function getPage(array $where = []): array
    {
        $field = 'id,site_id,quotation_id,price_name,dataset_name,channel_key,sort,status,follow_crawler,request_params,parse_rule,last_sync_at,last_sync_status,last_sync_message,last_sync_summary,create_at,update_at';
        $query = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['quotation_id', 'dataset_name', 'status'], $where)
            ->field($field)
            ->order('sort asc,id desc');

        return $this->pageQuery($query);
    }

    public function getAll(array $where = []): array
    {
        return $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['quotation_id', 'dataset_name', 'status'], $where)
            ->order('sort asc,id desc')
            ->select()
            ->toArray();
    }

    public function getInfo(int $id): array
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
        ])->findOrEmpty()->toArray();

        if (empty($info)) {
            throw new CommonException('报价数据集不存在');
        }

        return $info;
    }

    public function add(array $data): int
    {
        $data = $this->normalizeData($data);
        $this->assertUnique((int)$data['quotation_id'], 0);
        $data['site_id'] = $this->site_id;
        $data['create_at'] = time();
        $data['update_at'] = time();

        $result = $this->model->create($data);
        if (!$result) {
            throw new CommonException('报价数据集创建失败');
        }

        return (int)$result->id;
    }

    public function edit(int $id, array $data): bool
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
        ])->findOrEmpty();
        if ($info->isEmpty()) {
            throw new CommonException('报价数据集不存在');
        }

        $data = $this->normalizeData($data, $info->toArray());
        $this->assertUnique((int)$data['quotation_id'], $id);
        $data['update_at'] = time();

        return $info->save($data);
    }

    public function del(int $id): bool
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
        ])->findOrEmpty();
        if ($info->isEmpty()) {
            throw new CommonException('报价数据集不存在');
        }

        Db::startTrans();
        try {
            $where = [
                ['site_id', '=', $this->site_id],
                ['dataset_id', '=', $id],
            ];
            (new QuotationPrice())->where($where)->delete();
            (new QuotationNote())->where($where)->delete();
            (new QuotationField())->where($where)->delete();
            (new QuotationCapacity())->where($where)->delete();
            (new QuotationV2Model())->where($where)->delete();
            (new QuotationSyncLog())->where($where)->delete();
            $result = $info->delete();
            Db::commit();
            return $result;
        } catch (\Exception $e) {
            Db::rollback();
            throw $e;
        }
    }

    public function initChaoniuDefaults(): array
    {
        $items = [
            ['quotation_id' => 114, 'price_name' => '靓机/小花', 'dataset_name' => '靓机/小花', 'sort' => 1],
            ['quotation_id' => 115, 'price_name' => '花机/内爆', 'dataset_name' => '花机/内爆', 'sort' => 2],
            ['quotation_id' => 116, 'price_name' => '卡贴外版', 'dataset_name' => '卡贴外版', 'sort' => 3],
            ['quotation_id' => 117, 'price_name' => '外版无锁', 'dataset_name' => '外版无锁', 'sort' => 4],
            ['quotation_id' => 121, 'price_name' => '资源机', 'dataset_name' => '资源机', 'sort' => 5],
        ];

        $created = 0;
        $skipped = 0;
        foreach ($items as $item) {
            $exists = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['quotation_id', '=', $item['quotation_id']],
            ])->findOrEmpty();
            if (!$exists->isEmpty()) {
                $skipped++;
                continue;
            }
            $this->add($item);
            $created++;
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'total' => count($items),
        ];
    }

    private function normalizeData(array $data, array $old = []): array
    {
        $quotationId = (int)($data['quotation_id'] ?? $old['quotation_id'] ?? 0);
        if ($quotationId <= 0) {
            throw new CommonException('报价单 ID 不能为空');
        }

        $priceName = trim((string)($data['price_name'] ?? $old['price_name'] ?? ''));
        if ($priceName === '') {
            throw new CommonException('报价单名称不能为空');
        }

        $datasetName = trim((string)($data['dataset_name'] ?? $old['dataset_name'] ?? $priceName));
        $requestParams = $data['request_params'] ?? $old['request_params'] ?? [];
        $parseRule = $data['parse_rule'] ?? $old['parse_rule'] ?? [];

        return [
            'quotation_id' => $quotationId,
            'price_name' => $priceName,
            'dataset_name' => $datasetName,
            'channel_key' => (string)($data['channel_key'] ?? $old['channel_key'] ?? 'chaoniu'),
            'sort' => (int)($data['sort'] ?? $old['sort'] ?? 0),
            'status' => (int)($data['status'] ?? $old['status'] ?? QuotationV2Dict::STATUS_ENABLED),
            'follow_crawler' => (int)($data['follow_crawler'] ?? $old['follow_crawler'] ?? QuotationV2Dict::FOLLOW_CRAWLER),
            'request_params' => is_array($requestParams) ? $requestParams : [],
            'parse_rule' => is_array($parseRule) ? $parseRule : [],
            'remark' => (string)($data['remark'] ?? $old['remark'] ?? ''),
        ];
    }

    private function assertUnique(int $quotationId, int $excludeId): void
    {
        $query = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['quotation_id', '=', $quotationId],
        ]);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }

        if (!$query->findOrEmpty()->isEmpty()) {
            throw new CommonException('该报价单 ID 已存在');
        }
    }
}
