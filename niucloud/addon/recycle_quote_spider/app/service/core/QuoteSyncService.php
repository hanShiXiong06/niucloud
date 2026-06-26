<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\core;

use addon\recycle_quote_spider\app\model\QuoteCategory;
use addon\recycle_quote_spider\app\model\QuoteItem;
use addon\recycle_quote_spider\app\model\QuoteRow;
use addon\recycle_quote_spider\app\model\QuoteSource;
use addon\recycle_quote_spider\app\model\QuoteSyncLog;
use addon\recycle_quote_spider\app\service\core\QuotePriceHistoryService;
use app\service\core\upload\CoreBase64Service;
use app\service\core\upload\CoreFetchService;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

class QuoteSyncService extends BaseCoreService
{
    private const DEFAULT_NOTICE_TEXT = '温馨提示：报价仅供参考，最终价格以质检结果为准';

    private QuoteSpiderClient $client;
    private QuotePriceCalculator $calculator;
    private array $imageCache = [];
    private array $imageErrors = [];

    public function __construct()
    {
        parent::__construct();
        $this->client = new QuoteSpiderClient();
        $this->calculator = new QuotePriceCalculator();
    }

    public function createLog(int $sourceId, string $syncType = 'manual'): array
    {
        $sourceModel = new QuoteSource();
        $source = $sourceModel->where('id', $sourceId)->findOrEmpty()->toArray();
        if (empty($source)) {
            throw new CommonException('报价源不存在');
        }

        $log = (new QuoteSyncLog())->create([
            'site_id' => $source['site_id'],
            'source_id' => $sourceId,
            'sync_type' => $syncType,
            'status' => 0,
            'started_at' => time(),
            'message' => '等待同步',
            'summary' => $this->emptyStats(),
        ]);

        return ['log_id' => (int)$log->id, 'status' => 0, 'message' => '同步任务已创建', 'summary' => $this->emptyStats()];
    }

    public function sync(int $sourceId, string $syncType = 'manual', int $logId = 0): array
    {
        $sourceModel = new QuoteSource();
        $source = $sourceModel->where('id', $sourceId)->findOrEmpty()->toArray();
        if (empty($source)) {
            throw new CommonException('报价源不存在');
        }

        $log = $this->resolveLog($source, $sourceId, $syncType, $logId);
        $stats = $this->emptyStats();

        try {
            $this->updateProgress($log, $stats, '正在获取分类列表');
            $list = $this->client->fetchList($source);
            $this->updateProgress($log, $stats, '已获取分类列表，开始同步报价');
        } catch (\Throwable $e) {
            $this->markFailed($sourceModel, $log, $sourceId, $stats, $e);
            throw new CommonException('同步失败：' . $e->getMessage());
        }

        try {
            foreach ($list as $category) {
                if (is_array($category)) {
                    $this->syncCategory($source, $category, 0, 1, $stats, $log);
                    $this->updateProgress($log, $stats, '同步中');
                }
            }
        } catch (\Throwable $e) {
            $this->markFailed($sourceModel, $log, $sourceId, $stats, $e);
            throw new CommonException('同步失败：' . $e->getMessage());
        }

        $status = $stats['detail_failed'] > 0 ? 3 : 1;
        $message = $status === 1 ? '同步成功' : '同步部分成功';
        $log->save([
            'status' => $status,
            'finished_at' => time(),
            'total_count' => $stats['items'],
            'success_count' => $stats['detail_success'],
            'failed_count' => $stats['detail_failed'],
            'message' => $message,
            'summary' => $stats,
        ]);
        $sourceModel->where('id', $sourceId)->update([
            'last_sync_at' => time(),
            'last_status' => $status === 1 ? 1 : 3,
            'last_error' => '',
        ]);
        (new QuoteApiCacheService())->refresh((int)$source['site_id']);
        return ['log_id' => $log->id, 'status' => $status, 'message' => $message, 'summary' => $stats];
    }

    public function syncDueSources(?int $siteId = null): array
    {
        $query = (new QuoteSource())->where([
            ['status', '=', 1],
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

        $sources = $query->select()->toArray();
        $result = ['total' => 0, 'success' => 0, 'failed' => 0, 'messages' => []];
        foreach ($sources as $source) {
            $interval = max(60, (int)($source['sync_interval'] ?? 86400));
            if ((int)$source['last_sync_at'] > 0 && time() - (int)$source['last_sync_at'] < $interval) {
                continue;
            }

            $result['total']++;
            try {
                $this->sync((int)$source['id'], 'auto');
                $result['success']++;
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['messages'][] = $source['source_name'] . ':' . $e->getMessage();
            }
        }
        return $result;
    }

    private function getEnabledSiteIds(): array
    {
        try {
            return Db::name('site')->where('status', 1)->column('site_id');
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function syncCategory(array $source, array $data, int $parentId, int $level, array &$stats, QuoteSyncLog $log): int
    {
        $categoryId = (string)($data['id'] ?? '');
        if ($categoryId === '') {
            return 0;
        }

        $hash = $this->hash($data);
        $model = new QuoteCategory();
        $old = $model->where([
            ['site_id', '=', $source['site_id']],
            ['source_id', '=', $source['id']],
            ['source_category_id', '=', $categoryId],
        ])->findOrEmpty()->toArray();

        $save = [
            'site_id' => $source['site_id'],
            'source_id' => $source['id'],
            'source_category_id' => $categoryId,
            'parent_source_id' => (string)($data['parent_id'] ?? ''),
            'parent_id' => $parentId,
            'level' => $level,
            'name' => (string)($data['mobile_name'] ?? $data['name'] ?? ''),
            'sort' => (int)($data['sort_order'] ?? 0),
            'source_is_show' => (int)($data['is_show'] ?? 1),
            'source_is_hot' => (int)($data['is_hot'] ?? 0),
            'icon' => $this->resolveSyncedImage($source, $data, $old, 'icon', $stats),
            'image' => $this->resolveSyncedImage($source, $data, $old, 'image', $stats),
            'pic' => $this->resolveSyncedImage($source, $data, $old, 'pic', $stats),
            'raw_data' => $data,
            'source_hash' => $hash,
            'has_update' => !empty($old) && ($old['source_hash'] ?? '') !== $hash ? 1 : 0,
            'last_changed_at' => !empty($old) && ($old['source_hash'] ?? '') !== $hash ? time() : (int)($old['last_changed_at'] ?? 0),
        ];
        if (empty($old)) {
            $save['is_show'] = $save['source_is_show'];
            $save['is_hot'] = $save['source_is_hot'];
            $record = $model->create($save);
            $id = (int)$record->id;
        } else {
            $model->where('id', $old['id'])->update($save);
            $id = (int)$old['id'];
        }
        $stats['categories']++;

        foreach (($data['types'] ?? []) as $child) {
            if (is_array($child)) {
                $this->syncCategory($source, $child, $id, $level + 1, $stats, $log);
            }
        }
        foreach (($data['goods'] ?? []) as $item) {
            if (is_array($item)) {
                $this->syncItem($source, $item, $id, $stats);
                if ($stats['items'] % 10 === 0) {
                    $this->updateProgress($log, $stats, '同步中');
                }
            }
        }
        return $id;
    }

    private function syncItem(array $source, array $data, int $categoryId, array &$stats): int
    {
        $itemId = (string)($data['id'] ?? '');
        if ($itemId === '') {
            return 0;
        }

        $columns = $this->normalizeList($data['key'] ?? []);
        $prices = $this->normalizeList($data['price'] ?? []);
        $isImageQuote = empty($columns) && empty($prices) && !empty($data['image']);
        $hash = $this->hash($data);

        $model = new QuoteItem();
        $old = $model->where([
            ['site_id', '=', $source['site_id']],
            ['source_id', '=', $source['id']],
            ['source_item_id', '=', $itemId],
        ])->findOrEmpty()->toArray();

        $save = [
            'site_id' => $source['site_id'],
            'source_id' => $source['id'],
            'category_id' => $categoryId,
            'source_item_id' => $itemId,
            'source_url_id' => (string)($data['url'] ?? ''),
            'brand' => (string)($data['brand'] ?? ''),
            'tab' => (string)($data['tab'] ?? ''),
            'name' => (string)($data['mobile_name'] ?? $data['name'] ?? ''),
            'parent_name' => (string)($data['parent_name'] ?? ''),
            'quote_type' => (string)($data['type'] ?? ''),
            'is_image_quote' => $isImageQuote ? 1 : 0,
            'image' => $this->resolveSyncedImage($source, $data, $old, 'image', $stats),
            'timage' => $this->resolveSyncedImage($source, $data, $old, 'timage', $stats),
            'bimage' => $this->resolveSyncedImage($source, $data, $old, 'bimage', $stats),
            'icon' => $this->resolveSyncedImage($source, $data, $old, 'icon', $stats),
            'notice_text' => $this->resolveNoticeText($data, $old),
            'keywords' => (string)($data['keywords'] ?? ''),
            'index1' => (string)($data['index1'] ?? ''),
            'source_is_show' => (int)($data['is_show'] ?? 1),
            'source_is_hot' => (int)($data['is_hot'] ?? 0),
            'sort' => (int)($data['sort_order'] ?? 0),
            'columns' => $columns,
            'raw_data' => $data,
            'source_hash' => $hash,
            'has_update' => !empty($old) && ($old['source_hash'] ?? '') !== $hash ? 1 : 0,
            'last_sync_at' => time(),
            'last_changed_at' => !empty($old) && ($old['source_hash'] ?? '') !== $hash ? time() : (int)($old['last_changed_at'] ?? 0),
        ];
        if (empty($old)) {
            $save['is_show'] = $save['source_is_show'];
            $save['is_hot'] = $save['source_is_hot'];
            $save['follow_source'] = 1;
            $record = $model->create($save);
            $id = (int)$record->id;
            $old = $record->toArray();
        } else {
            $model->where('id', $old['id'])->update($save);
            $id = (int)$old['id'];
        }
        $stats['items']++;

        if (!$isImageQuote) {
            $this->syncItemRows($source, $id, $data, $old, $stats);
        }

        return $id;
    }

    private function syncItemRows(array $source, int $itemId, array $data, array $itemRule, array &$stats): void
    {
        try {
            $detail = $this->client->fetchDetail($source, (string)$data['id']);
            $stats['detail_success']++;
            $rows = $this->extractDetailRows($detail);
        } catch (\Throwable $e) {
            $stats['detail_failed']++;
            $rows = $this->hasEmbeddedPrices($data) ? [$data] : [];
        }

        foreach ($rows as $index => $row) {
            if (is_array($row)) {
                $this->upsertRow($source, $itemId, $row, $itemRule, $stats, $index);
            }
        }

        $rateLimit = max(0, (int)($source['rate_limit'] ?? 300));
        if ($rateLimit > 0) {
            usleep($rateLimit * 1000);
        }
    }

    private function extractDetailRows(array $detail): array
    {
        $rows = [];
        if (!empty($detail['prices']) && is_array($detail['prices'])) {
            return $detail['prices'];
        }

        foreach ($detail as $group) {
            if (!is_array($group)) {
                continue;
            }
            if (!empty($group['prices']) && is_array($group['prices'])) {
                foreach ($group['prices'] as $row) {
                    if (is_array($row)) {
                        foreach (['brand', 'tab', 'parent_name', 'type', 'url', 'image', 'timage', 'bimage', 'icon'] as $field) {
                            if (($row[$field] ?? '') === '' && ($group[$field] ?? '') !== '') {
                                $row[$field] = $group[$field];
                            }
                        }
                        $rows[] = $row;
                    }
                }
                continue;
            }
            if ($this->hasEmbeddedPrices($group)) {
                $rows[] = $group;
            }
        }

        return $rows;
    }

    private function hasEmbeddedPrices(array $data): bool
    {
        return !empty($this->normalizeList($data['key'] ?? [])) && !empty($this->normalizeList($data['price'] ?? []));
    }

    private function upsertRow(array $source, int $itemId, array $row, array $itemRule, array &$stats, int $index): void
    {
        $sourceRowId = (string)($row['id'] ?? '');
        $rowName = (string)($row['mobile_name'] ?? $row['name'] ?? '');
        $rowTab = (string)($row['tab'] ?? '');
        $legacyRowId = $sourceRowId !== ''
            ? $sourceRowId . '#' . md5($rowName . '#' . $index)
            : '';
        $rowId = $sourceRowId !== ''
            ? $sourceRowId . '#' . md5($rowTab . '#' . $rowName . '#' . $index)
            : md5(json_encode($row, JSON_UNESCAPED_UNICODE) . '#' . $rowTab . '#' . $index);
        $columns = $this->normalizeList($row['key'] ?? $itemRule['columns'] ?? []);
        $prices = $this->normalizeList($row['price'] ?? []);
        $remark = $this->extractRemark($columns, $prices);
        $hash = $this->hash($row);

        $model = new QuoteRow();
        $old = $model->where([
            ['site_id', '=', $source['site_id']],
            ['item_id', '=', $itemId],
            ['source_row_id', '=', $rowId],
        ])->findOrEmpty()->toArray();
        if (empty($old) && $legacyRowId !== '' && $legacyRowId !== $rowId) {
            $old = $model->where([
                ['site_id', '=', $source['site_id']],
                ['item_id', '=', $itemId],
                ['source_row_id', '=', $legacyRowId],
            ])->findOrEmpty()->toArray();
        }

        $rule = [
            'adjust_type' => $old['adjust_type'] ?? $itemRule['adjust_type'] ?? 0,
            'adjust_value' => $old['adjust_value'] ?? $itemRule['adjust_value'] ?? 0,
            'adjust_ratio' => $old['adjust_ratio'] ?? $itemRule['adjust_ratio'] ?? 1,
            'round_mode' => $old['round_mode'] ?? $itemRule['round_mode'] ?? 'round',
        ];
        $followSource = (int)($old['follow_source'] ?? 1);
        $manualPrices = $old['manual_prices'] ?? [];
        $finalPrices = $followSource === 1
            ? $this->calculator->calculateList($prices, $rule)
            : (!empty($manualPrices) ? $manualPrices : ($old['final_prices'] ?? $prices));

        $save = [
            'site_id' => $source['site_id'],
            'source_id' => $source['id'],
            'item_id' => $itemId,
            'source_row_id' => $rowId,
            'brand' => (string)($row['brand'] ?? ''),
            'tab' => (string)($row['tab'] ?? ''),
            'model_name' => (string)($row['mobile_name'] ?? $row['name'] ?? ''),
            'keywords' => (string)($row['keywords'] ?? ''),
            'index1' => (string)($row['index1'] ?? ''),
            'columns' => $columns,
            'source_prices' => $prices,
            'final_prices' => $finalPrices,
            'remark' => $remark,
            'source_is_show' => (int)($row['is_show'] ?? 1),
            'is_show' => (int)($old['is_show'] ?? ($row['is_show'] ?? 1)),
            'sort' => (int)($old['sort'] ?? $index),
            'follow_source' => $followSource,
            'adjust_type' => $rule['adjust_type'],
            'adjust_value' => $rule['adjust_value'],
            'adjust_ratio' => $rule['adjust_ratio'],
            'round_mode' => $rule['round_mode'],
            'has_update' => !empty($old) && ($old['source_hash'] ?? '') !== $hash ? 1 : 0,
            'raw_data' => $row,
            'source_hash' => $hash,
        ];

        if (empty($old)) {
            $save['manual_prices'] = [];
            $record = $model->create($save);
            $savedRowId = (int)$record->id;
        } else {
            $model->where('id', $old['id'])->update($save);
            $savedRowId = (int)$old['id'];
        }
        (new QuotePriceHistoryService())->record((int)$source['site_id'], array_merge($save, ['id' => $savedRowId]));
        $stats['rows']++;
    }

    private function resolveSyncedImage(array $source, array $data, array $old, string $field, array &$stats): string
    {
        $sourceUrl = trim((string)($data[$field] ?? ''));
        $oldValue = trim((string)($old[$field] ?? ''));
        if ($sourceUrl === '') {
            return $oldValue;
        }

        if (!$this->isRemoteUrl($sourceUrl)) {
            return $sourceUrl;
        }

        $oldRaw = $old['raw_data'] ?? [];
        if (is_string($oldRaw)) {
            $decoded = json_decode($oldRaw, true);
            $oldRaw = is_array($decoded) ? $decoded : [];
        }
        $oldSourceUrl = trim((string)($oldRaw[$field] ?? ''));

        if ($oldValue !== '' && $oldValue !== $sourceUrl && ($oldSourceUrl === '' || $oldValue !== $oldSourceUrl)) {
            return $oldValue;
        }

        $localUrl = $this->fetchRemoteImage($source, $sourceUrl, $field, (string)($data['id'] ?? ''));
        if ($localUrl !== '') {
            $stats['image_success']++;
            return $localUrl;
        }

        // Do not persist third-party image URLs. If the fetch/upload failed, leave the
        // field empty so the frontend never requests the crawler source directly.
        $stats['image_failed']++;
        return '';
    }

    private function fetchRemoteImage(array $source, string $url, string $field, string $sourceId): string
    {
        if (!$this->isRemoteUrl($url)) {
            return $url;
        }
        if (isset($this->imageCache[$url])) {
            return $this->imageCache[$url];
        }

        try {
            $siteId = (int)($source['site_id'] ?? $this->site_id ?? 0);
            $dir = 'file/image/' . $siteId . '/' . date('Ym') . '/' . date('d');
            $result = $this->fetchImageByStorage($url, $siteId, $dir);
            $localUrl = trim((string)($result['url'] ?? ''));
            $this->imageCache[$url] = ($localUrl !== '' && $localUrl !== $url) ? $localUrl : '';
        } catch (\Throwable $e) {
            $this->imageCache[$url] = '';
            $this->recordImageError($url, $field, $sourceId, $e->getMessage());
        }

        return $this->imageCache[$url];
    }

    private function fetchImageByStorage(string $url, int $siteId, string $dir): array
    {
        try {
            return (new CoreFetchService())->image($url, $siteId, $dir);
        } catch (\Throwable $e) {
            $content = $this->downloadRemoteImage($url);
            if ($content === '') {
                throw $e;
            }
            return (new CoreBase64Service())->image(base64_encode($content), $siteId, $dir);
        }
    }

    private function downloadRemoteImage(string $url): string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new \RuntimeException('curl 初始化失败');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => [
                'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                'Referer: https://servicewechat.com/',
                'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.49 Language/zh_CN',
            ],
        ]);
        $content = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0) {
            throw new \RuntimeException('curl 下载失败：' . $curlError . '，错误码：' . $curlErrno);
        }

        if (!is_string($content) || $content === '' || $httpCode < 200 || $httpCode >= 300) {
            throw new \RuntimeException('图片下载 HTTP 状态异常：' . $httpCode);
        }

        $info = @getimagesizefromstring($content);
        if (!is_array($info)) {
            throw new \RuntimeException('下载内容不是有效图片');
        }

        return $content;
    }

    private function recordImageError(string $url, string $field, string $sourceId, string $message): void
    {
        $key = md5($field . '|' . $url);
        if (isset($this->imageErrors[$key])) {
            return;
        }

        $this->imageErrors[$key] = true;
        Log::warning('回收报价爬虫图片同步失败', [
            'source_item_id' => $sourceId,
            'field' => $field,
            'url' => $url,
            'error' => $message,
        ]);
    }

    private function isRemoteUrl(string $url): bool
    {
        return preg_match('/^https?:\/\//i', $url) === 1;
    }

    private function normalizeList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
            return $value === '' ? [] : [$value];
        }
        if (is_array($value)) {
            return array_values($value);
        }
        return $value === null ? [] : [$value];
    }

    private function extractRemark(array $columns, array $prices): string
    {
        foreach ($columns as $index => $column) {
            if (is_string($column) && str_contains($column, '备注')) {
                return (string)($prices[$index] ?? '');
            }
        }
        $last = end($prices);
        return is_string($last) && !$this->calculator->isNumericPrice($last) ? $last : '';
    }

    private function resolveNoticeText(array $data, array $old): string
    {
        $oldNotice = str_replace(["\r\n", "\r"], "\n", trim((string)($old['notice_text'] ?? '')));
        if ($oldNotice !== '') {
            return $oldNotice;
        }

        foreach (['notice_text', 'notice', 'tips', 'remark_text'] as $field) {
            $text = str_replace(["\r\n", "\r"], "\n", trim((string)($data[$field] ?? '')));
            if ($text !== '') {
                return $text;
            }
        }

        return self::DEFAULT_NOTICE_TEXT;
    }

    private function hash(array $data): string
    {
        return md5(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function emptyStats(): array
    {
        return [
            'categories' => 0,
            'items' => 0,
            'rows' => 0,
            'detail_success' => 0,
            'detail_failed' => 0,
            'image_success' => 0,
            'image_failed' => 0,
        ];
    }

    private function resolveLog(array $source, int $sourceId, string $syncType, int $logId): QuoteSyncLog
    {
        $logModel = new QuoteSyncLog();
        if ($logId > 0) {
            $log = $logModel->where([
                ['id', '=', $logId],
                ['source_id', '=', $sourceId],
            ])->findOrEmpty();
            if (!$log->isEmpty()) {
                $log->save([
                    'status' => 0,
                    'started_at' => time(),
                    'finished_at' => 0,
                    'message' => '同步中',
                    'error_detail' => '',
                    'summary' => $this->emptyStats(),
                ]);
                return $log;
            }
        }

        return $logModel->create([
            'site_id' => $source['site_id'],
            'source_id' => $sourceId,
            'sync_type' => $syncType,
            'status' => 0,
            'started_at' => time(),
            'message' => '同步中',
            'summary' => $this->emptyStats(),
        ]);
    }

    private function updateProgress(QuoteSyncLog $log, array $stats, string $message): void
    {
        $log->save([
            'status' => 0,
            'message' => $message,
            'total_count' => $stats['items'],
            'success_count' => $stats['detail_success'],
            'failed_count' => $stats['detail_failed'],
            'summary' => $stats,
        ]);
    }

    private function markFailed(QuoteSource $sourceModel, QuoteSyncLog $log, int $sourceId, array $stats, \Throwable $e): void
    {
        $log->save([
            'status' => 2,
            'finished_at' => time(),
            'message' => '同步失败',
            'error_detail' => $e->getMessage(),
            'summary' => $stats,
        ]);
        $sourceModel->where('id', $sourceId)->update([
            'last_sync_at' => time(),
            'last_status' => 2,
            'last_error' => $e->getMessage(),
        ]);
        try {
            $source = $sourceModel->where('id', $sourceId)->findOrEmpty()->toArray();
            if (!empty($source['site_id'])) {
                (new QuoteApiCacheService())->refresh((int)$source['site_id']);
            }
        } catch (\Throwable $ignore) {
        }
    }
}
