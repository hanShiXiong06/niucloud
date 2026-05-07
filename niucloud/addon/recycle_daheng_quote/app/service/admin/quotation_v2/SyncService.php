<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\admin\quotation_v2;

use addon\recycle_daheng_quote\app\dict\quotation\QuotationV2Dict;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationSyncLog;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 报价 2.0 同步编排
 */
class SyncService extends BaseAdminService
{
    public function preview(int $datasetId): array
    {
        $dataset = (new DatasetService())->getInfo($datasetId);
        if ((int)($dataset['status'] ?? 0) !== QuotationV2Dict::STATUS_ENABLED) {
            throw new CommonException('报价数据集未启用');
        }

        $fetch = (new ChaoniuCrawlerService())->fetch($dataset);
        $parsed = (new ChaoniuParserService())->parse($dataset, $fetch['response']);
        $logId = $this->createLog($dataset, $fetch, $parsed, false);

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
        $dataset = (new DatasetService())->getInfo((int)$logData['dataset_id']);
        $parsed = $logData['parsed_preview'] ?? [];
        if (empty($parsed)) {
            throw new CommonException('同步预览数据为空，请重新测试');
        }

        $stats = (new ImportService())->import($dataset, $parsed);
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

    public function syncNow(int $datasetId): array
    {
        $preview = $this->preview($datasetId);
        return $this->importFromPreview((int)$preview['log_id']);
    }

    private function createLog(array $dataset, array $fetch, array $parsed, bool $imported): int
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
}
