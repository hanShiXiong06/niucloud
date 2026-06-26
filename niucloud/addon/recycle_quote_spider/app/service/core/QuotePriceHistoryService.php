<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\core;

use addon\recycle_quote_spider\app\model\QuotePriceHistory;

/**
 * 报价历史价格快照
 * - 写入即快照（carry-forward）：每次价格变动 upsert 当天一条
 * - 给后台/移动端提供「昨日价对比」与「历史折线」数据
 * 所有方法都吞掉异常，保证历史功能永不影响主流程（同步/导入/编辑）
 */
class QuotePriceHistoryService
{
    /**
     * 记录单行当天快照（同一型号同一天去重覆盖为最新）
     */
    public function record(int $siteId, array $row): void
    {
        try {
            $rowId = (int)($row['id'] ?? $row['row_id'] ?? 0);
            if ($siteId <= 0 || $rowId <= 0) {
                return;
            }
            $finalPrices = $this->normalizeList($row['final_prices'] ?? []);
            if (empty($finalPrices)) {
                return;
            }
            $today = date('Y-m-d');
            $model = new QuotePriceHistory();
            $exists = $model->where('site_id', $siteId)->where('row_id', $rowId)->where('record_date', $today)->findOrEmpty()->toArray();
            $payload = [
                'columns' => $this->normalizeList($row['columns'] ?? []),
                'final_prices' => $finalPrices,
                'model_name' => (string)($row['model_name'] ?? ($row['name'] ?? '')),
            ];
            if (empty($exists)) {
                $model->create(array_merge($payload, [
                    'site_id' => $siteId,
                    'source_id' => (int)($row['source_id'] ?? 0),
                    'item_id' => (int)($row['item_id'] ?? 0),
                    'row_id' => $rowId,
                    'record_date' => $today,
                ]));
            } else {
                $model->where('id', $exists['id'])->update($payload);
            }
        } catch (\Throwable $e) {
            // 历史快照失败不影响主流程
        }
    }

    public function recordMany(int $siteId, array $rows): void
    {
        foreach ($rows as $row) {
            if (is_array($row)) {
                $this->record($siteId, $row);
            }
        }
    }

    /**
     * 取一批行「今天之前最近一次」的最终价（用于今天 vs 昨天涨跌对比）
     * @return array<int, array> row_id => final_prices
     */
    public function previousMap(int $siteId, array $rowIds): array
    {
        $rowIds = array_values(array_unique(array_filter(array_map('intval', $rowIds))));
        if ($siteId <= 0 || empty($rowIds)) {
            return [];
        }
        try {
            $list = (new QuotePriceHistory())
                ->where('site_id', $siteId)
                ->whereIn('row_id', $rowIds)
                ->where('record_date', '<', date('Y-m-d'))
                ->order('record_date desc,id desc')
                ->select()
                ->toArray();
            $map = [];
            foreach ($list as $item) {
                $rid = (int)$item['row_id'];
                if (!isset($map[$rid])) {
                    $map[$rid] = $this->normalizeList($item['final_prices'] ?? []);
                }
            }
            return $map;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 单个型号近 N 天的价格序列
     * @return array{columns: array, points: array<int, array{date: string, prices: array}>}
     */
    public function series(int $siteId, int $rowId, int $days): array
    {
        $days = max(1, min(365, $days));
        $empty = ['columns' => [], 'points' => []];
        if ($siteId <= 0 || $rowId <= 0) {
            return $empty;
        }
        try {
            $startDate = date('Y-m-d', time() - ($days - 1) * 86400);
            $list = (new QuotePriceHistory())
                ->where('site_id', $siteId)
                ->where('row_id', $rowId)
                ->where('record_date', '>=', $startDate)
                ->order('record_date asc,id asc')
                ->select()
                ->toArray();

            $points = [];
            $columns = [];
            foreach ($list as $item) {
                $prices = $this->normalizeList($item['final_prices'] ?? []);
                $cols = $this->normalizeList($item['columns'] ?? []);
                if (!empty($cols)) {
                    $columns = $cols;
                }
                $points[] = ['date' => (string)$item['record_date'], 'prices' => $prices];
            }

            // 区间起点之前的最近一条作为基线，让折线从区间开头平延过来
            $baseline = (new QuotePriceHistory())
                ->where('site_id', $siteId)
                ->where('row_id', $rowId)
                ->where('record_date', '<', $startDate)
                ->order('record_date desc,id desc')
                ->findOrEmpty()
                ->toArray();
            if (!empty($baseline)) {
                $firstDate = $points[0]['date'] ?? '';
                if ($firstDate !== $startDate) {
                    array_unshift($points, ['date' => $startDate, 'prices' => $this->normalizeList($baseline['final_prices'] ?? [])]);
                }
                if (empty($columns)) {
                    $columns = $this->normalizeList($baseline['columns'] ?? []);
                }
            }

            return ['columns' => $columns, 'points' => $points];
        } catch (\Throwable $e) {
            return $empty;
        }
    }

    private function normalizeList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? array_values($value) : [];
    }
}
