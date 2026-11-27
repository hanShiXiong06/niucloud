<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\core;

use addon\recycle_quote_spider\app\model\QuoteItem;
use addon\recycle_quote_spider\app\model\QuotePriceHistory;
use addon\recycle_quote_spider\app\model\QuoteRow;
use addon\recycle_quote_spider\app\support\QuoteSyncIdentity;
use think\facade\Db;

/**
 * 合并因上游每日更换 ID 产生的重复报价项，并把旧型号快照接回当前型号。
 */
class QuoteDuplicateMergeService
{
    public function merge(int $siteId, int $sourceId, int $canonicalItemId, array $itemData): void
    {
        $sourceUrlId = trim((string)($itemData['url'] ?? $itemData['source_url_id'] ?? ''));
        if ($siteId <= 0 || $sourceId <= 0 || $canonicalItemId <= 0 || $sourceUrlId === '') {
            return;
        }

        $signature = QuoteSyncIdentity::itemSignature($itemData);
        $candidates = (new QuoteItem())->where([
            ['site_id', '=', $siteId],
            ['source_id', '=', $sourceId],
            ['source_url_id', '=', $sourceUrlId],
            ['id', '<>', $canonicalItemId],
        ])->order('id desc')->select()->toArray();
        $duplicates = array_values(array_filter($candidates, static fn(array $item): bool => QuoteSyncIdentity::itemSignature($item) === $signature));
        if ($duplicates === []) {
            return;
        }

        Db::transaction(function () use ($siteId, $canonicalItemId, $duplicates) {
            $canonicalRows = (new QuoteRow())->where([
                ['site_id', '=', $siteId],
                ['item_id', '=', $canonicalItemId],
            ])->order('id asc')->select()->toArray();
            $canonicalMap = [];
            foreach ($canonicalRows as $row) {
                $canonicalMap[QuoteSyncIdentity::rowSignature($row)] = (int)$row['id'];
            }

            $mergedViewCount = 0;
            foreach ($duplicates as $duplicate) {
                $duplicateId = (int)$duplicate['id'];
                $mergedViewCount += (int)($duplicate['view_count'] ?? 0);
                $rows = (new QuoteRow())->where([
                    ['site_id', '=', $siteId],
                    ['item_id', '=', $duplicateId],
                ])->order('id asc')->select()->toArray();

                foreach ($rows as $row) {
                    $rowId = (int)$row['id'];
                    $rowSignature = QuoteSyncIdentity::rowSignature($row);
                    $targetRowId = (int)($canonicalMap[$rowSignature] ?? 0);
                    if ($targetRowId <= 0) {
                        $stableRowId = QuoteSyncIdentity::rowKey($row);
                        (new QuoteRow())->where('id', $rowId)->update([
                            'item_id' => $canonicalItemId,
                            'source_row_id' => $stableRowId,
                            'is_show' => 0,
                        ]);
                        (new QuotePriceHistory())->where('site_id', $siteId)->where('row_id', $rowId)->update(['item_id' => $canonicalItemId]);
                        $canonicalMap[$rowSignature] = $rowId;
                        continue;
                    }

                    $this->mergeRowHistory($siteId, $canonicalItemId, $rowId, $targetRowId);
                    (new QuotePriceHistory())->where('site_id', $siteId)->where('row_id', $rowId)->delete();
                    (new QuoteRow())->where('id', $rowId)->delete();
                }
                (new QuoteItem())->where('id', $duplicateId)->delete();
            }

            if ($mergedViewCount > 0) {
                $canonical = (new QuoteItem())->where('site_id', $siteId)->where('id', $canonicalItemId)->lock(true)->findOrEmpty();
                if (!$canonical->isEmpty()) {
                    $canonical->save(['view_count' => (int)$canonical->view_count + $mergedViewCount]);
                }
            }
        });
    }

    private function mergeRowHistory(int $siteId, int $itemId, int $sourceRowId, int $targetRowId): void
    {
        $historyRows = (new QuotePriceHistory())->where('site_id', $siteId)->where('row_id', $sourceRowId)->order('record_date asc,id asc')->select()->toArray();
        foreach ($historyRows as $history) {
            $recordDate = (string)($history['record_date'] ?? '');
            if ($recordDate === '') {
                continue;
            }
            $exists = (new QuotePriceHistory())->where([
                ['site_id', '=', $siteId],
                ['row_id', '=', $targetRowId],
                ['record_date', '=', $recordDate],
            ])->findOrEmpty();
            if (!$exists->isEmpty()) {
                continue;
            }
            (new QuotePriceHistory())->create([
                'site_id' => $siteId,
                'source_id' => (int)($history['source_id'] ?? 0),
                'item_id' => $itemId,
                'row_id' => $targetRowId,
                'model_name' => (string)($history['model_name'] ?? ''),
                'columns' => (array)($history['columns'] ?? []),
                'final_prices' => (array)($history['final_prices'] ?? []),
                'record_date' => $recordDate,
                'create_at' => (int)($history['create_at'] ?? time()),
            ]);
        }
    }
}
