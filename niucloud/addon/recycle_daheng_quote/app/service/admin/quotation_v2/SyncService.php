<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\admin\quotation_v2;

use addon\recycle_daheng_quote\app\dict\quotation\QuotationV2Dict;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationDataset;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationSyncLog;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 报价 2.0 同步编排
 */
class SyncService extends BaseAdminService
{
    public function setSiteId(int $siteId): self
    {
        $this->site_id = $siteId;
        return $this;
    }

    public function preview(int $datasetId, string $source = QuotationV2Dict::SYNC_SOURCE_PREVIEW): array
    {
        $dataset = (new DatasetService())->setSiteId((int)$this->site_id)->getInfo($datasetId);
        if ((int)($dataset['status'] ?? 0) !== QuotationV2Dict::STATUS_ENABLED) {
            throw new CommonException('报价数据集未启用');
        }

        try {
            $fetch = (new ChaoniuCrawlerService())->setSiteId((int)$this->site_id)->fetch($dataset);
            $parsed = (new ChaoniuParserService())->parse($dataset, $fetch['response']);
        } catch (\Throwable $e) {
            $this->createFailedLog($dataset, $source, $e->getMessage());
            $this->markDatasetFailed((int)$dataset['id'], $e->getMessage());
            throw $e;
        }
        $logId = $this->createLog($dataset, $fetch, $parsed, false, $source);

        return [
            'log_id' => $logId,
            'dataset' => $parsed['dataset'],
            'stats' => $parsed['stats'],
            'warnings' => $parsed['warnings'],
            'fields' => $parsed['fields'],
            'sample_models' => array_slice($parsed['models'], 0, 5),
            'sample_prices' => array_slice($parsed['prices'], 0, 10),
            'sample_notes' => array_slice($parsed['notes'], 0, 10),
            'duration' => $fetch['duration'],
        ];
    }

    public function importFromPreview(int $logId): array
    {
        $log = (new QuotationSyncLog())->where([
            ['id', '=', $logId],
            ['site_id', '=', $this->site_id],
        ])->findOrEmpty();
        if ($log->isEmpty()) {
            throw new CommonException('同步预览记录不存在');
        }

        $logData = $log->toArray();
        $dataset = (new DatasetService())->setSiteId((int)$this->site_id)->getInfo((int)$logData['dataset_id']);
        $parsed = $logData['parsed_preview'] ?? [];
        if (empty($parsed)) {
            throw new CommonException('同步预览数据为空，请重新测试');
        }

        $stats = (new ImportService())->setSiteId((int)$this->site_id)->import($dataset, $parsed);
        $log->save([
            'imported' => 1,
            'status' => QuotationV2Dict::SYNC_STATUS_SUCCESS,
            'stats' => array_merge($logData['stats'] ?? [], ['import' => $stats]),
            'update_at' => time(),
        ]);

        return [
            'dataset_id' => (int)$dataset['id'],
            'log_id' => $logId,
            'stats' => $stats,
        ];
    }

    public function syncNow(int $datasetId, string $source = QuotationV2Dict::SYNC_SOURCE_MANUAL): array
    {
        $preview = $this->preview($datasetId, $source);
        return $this->importFromPreview((int)$preview['log_id']);
    }

    public function syncDueDatasets(?int $siteId = null): array
    {
        $query = (new QuotationDataset())->where([
            ['status', '=', QuotationV2Dict::STATUS_ENABLED],
            ['sync_enabled', '=', 1],
        ]);
        if ($siteId !== null && $siteId > 0) {
            $query->where('site_id', '=', $siteId);
        } else {
            $siteIds = $this->getEnabledSiteIds();
            if (!empty($siteIds)) {
                $query->whereIn('site_id', $siteIds);
            }
        }

        $datasets = $query->order('site_id asc,sort asc,id desc')->select()->toArray();
        $result = ['total' => 0, 'success' => 0, 'failed' => 0, 'messages' => []];
        foreach ($datasets as $dataset) {
            $interval = max(3600, (int)($dataset['sync_interval'] ?? 86400));
            if ((int)$dataset['last_sync_at'] > 0 && time() - (int)$dataset['last_sync_at'] < $interval) {
                continue;
            }

            $result['total']++;
            try {
                (new self())->setSiteId((int)$dataset['site_id'])->syncNow((int)$dataset['id'], QuotationV2Dict::SYNC_SOURCE_AUTO);
                $result['success']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['messages'][] = ($dataset['dataset_name'] ?? $dataset['id']) . ':' . $e->getMessage();
                (new self())->setSiteId((int)$dataset['site_id'])->markDatasetFailed((int)$dataset['id'], $e->getMessage());
            }
        }
        return $result;
    }

    private function createLog(array $dataset, array $fetch, array $parsed, bool $imported, string $source): int
    {
        $result = (new QuotationSyncLog())->create([
            'site_id' => $this->site_id,
            'dataset_id' => (int)$dataset['id'],
            'quotation_id' => (int)$dataset['quotation_id'],
            'channel_key' => (string)($dataset['channel_key'] ?? 'chaoniu'),
            'request_url' => (string)$fetch['url'],
            'request_params' => array_merge($fetch['params'] ?? [], [
                '_credential_source' => (string)($fetch['credential_source'] ?? 'sys_config'),
            ]),
            'http_code' => (int)$fetch['http_code'],
            'duration' => (int)$fetch['duration'],
            'sync_source' => $this->normalizeSyncSource($source),
            'raw_response' => $this->compactResponse($fetch['response'] ?? []),
            'parsed_preview' => $parsed,
            'stats' => $parsed['stats'] ?? [],
            'warnings' => $parsed['warnings'] ?? [],
            'status' => QuotationV2Dict::SYNC_STATUS_SUCCESS,
            'imported' => $imported ? 1 : 0,
            'create_at' => time(),
            'update_at' => time(),
        ]);

        return (int)$result->id;
    }

    private function createFailedLog(array $dataset, string $source, string $message): int
    {
        $result = (new QuotationSyncLog())->create([
            'site_id' => $this->site_id,
            'dataset_id' => (int)$dataset['id'],
            'quotation_id' => (int)$dataset['quotation_id'],
            'channel_key' => (string)($dataset['channel_key'] ?? 'chaoniu'),
            'request_url' => '',
            'request_params' => [
                'quotation_id' => (int)($dataset['quotation_id'] ?? 0),
                'price_name' => (string)($dataset['price_name'] ?? ''),
            ],
            'http_code' => 0,
            'duration' => 0,
            'sync_source' => $this->normalizeSyncSource($source),
            'raw_response' => [],
            'parsed_preview' => [],
            'stats' => [],
            'warnings' => [],
            'status' => QuotationV2Dict::SYNC_STATUS_FAILED,
            'imported' => 0,
            'error_message' => mb_substr($message, 0, 500),
            'create_at' => time(),
            'update_at' => time(),
        ]);

        return (int)$result->id;
    }

    private function markDatasetFailed(int $datasetId, string $message): void
    {
        (new QuotationDataset())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $datasetId],
        ])->update([
            'last_sync_at' => time(),
            'last_sync_status' => QuotationV2Dict::SYNC_STATUS_FAILED,
            'last_sync_message' => mb_substr($message, 0, 500),
            'last_sync_summary' => [
                'sync_source' => 'failed',
            ],
            'update_at' => time(),
        ]);
    }

    private function normalizeSyncSource(string $source): string
    {
        return in_array($source, [
            QuotationV2Dict::SYNC_SOURCE_MANUAL,
            QuotationV2Dict::SYNC_SOURCE_AUTO,
            QuotationV2Dict::SYNC_SOURCE_PREVIEW,
        ], true) ? $source : QuotationV2Dict::SYNC_SOURCE_MANUAL;
    }

    private function compactResponse(array $response): array
    {
        $data = $response['data'] ?? [];
        return [
            'code' => $response['code'] ?? 0,
            'msg' => $response['msg'] ?? '',
            'quotation_id' => $data['quotation_id'] ?? 0,
            'price_name' => $data['price_name'] ?? '',
            'quotation_name' => $data['quotation_name'] ?? '',
            'sku_group_count' => is_array($data['sku'] ?? null) ? count($data['sku']) : 0,
            'remark' => $data['remark'] ?? [],
        ];
    }

    private function getEnabledSiteIds(): array
    {
        try {
            return Db::name('site')->where('status', 1)->column('site_id');
        } catch (\Throwable $e) {
            return [];
        }
    }
}
