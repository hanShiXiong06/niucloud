<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\service\core\quotation;

use addon\recycle_daheng_quote\app\dict\quotation\QuotationV2Dict;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationCapacity;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationModel;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationNote;
use addon\recycle_daheng_quote\app\model\quotation_v2\QuotationPrice;

/** 报价插件对 AI 开放的只读事实投影，不让 AI 直接接触业务表。 */
final class AiQuoteReadService
{
    public function resolve(int $siteId, string $prompt, string $contextPrompt = ''): array
    {
        $entityPrompt = trim($contextPrompt) !== '' ? $contextPrompt : $prompt;
        $models = $this->resolveModels($siteId, $prompt);
        if ($models === []) $models = $this->resolveModels($siteId, $entityPrompt);
        if ($models === []) {
            return $this->clarification('model', '请告诉我具体的苹果型号，例如 17 Pro Max。');
        }

        $time = $this->resolveTime($prompt);
        if ($time['trend']) {
            $model = $models[0];
            $capacity = $this->resolveCapacity($siteId, (int)$model['id'], $entityPrompt);
            if ($capacity === null) {
                return $this->clarification('capacity', '查看行情需要指定内存，例如 ' . (string)$model['model_name'] . ' 256G。');
            }
            return $this->trend($siteId, $model, $capacity, $prompt, $time);
        }
        return $this->aggregateQuote($siteId, $models, $prompt, $entityPrompt, $time);
    }

    private function aggregateQuote(int $siteId, array $models, string $prompt, string $entityPrompt, array $time): array
    {
        $modelIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $models)));
        $capacityRows = (new QuotationCapacity())->where([
            ['site_id', '=', $siteId],
            ['status', '=', QuotationV2Dict::STATUS_ENABLED],
        ])->whereIn('model_id', $modelIds)->field('id,dataset_id,model_id,capacity_name,sort')
            ->order('sort asc,id asc')->select()->toArray();
        $capacityToken = $this->capacityToken($prompt) ?: $this->capacityToken($entityPrompt);
        if ($capacityToken !== '') {
            $capacityRows = array_values(array_filter($capacityRows, fn(array $row): bool => $this->capacityKey((string)$row['capacity_name']) === $capacityToken));
        }
        if ($capacityRows === []) {
            return $this->clarification('capacity', '已经找到 ' . (string)$models[0]['model_name'] . '，但没有匹配到该内存，请换一个容量。');
        }
        $capacityIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $capacityRows)));

        $dateQuery = (new QuotationPrice())->where([['site_id', '=', $siteId]])
            ->whereIn('model_id', $modelIds)->whereIn('capacity_id', $capacityIds);
        if ($time['exact']) {
            $dateQuery->where('price_date', '=', $time['date']);
        } else {
            $dateQuery->where('price_date', '<=', $time['date']);
        }
        $dateRows = $dateQuery->field('dataset_id,MAX(price_date) as price_date')->group('dataset_id')->select()->toArray();
        $dateMap = [];
        foreach ($dateRows as $row) $dateMap[(int)$row['dataset_id']] = (string)$row['price_date'];
        if ($dateMap === []) {
            return [
                'status' => 'no_snapshot',
                'message' => sprintf('%s 在 %s 没有报价快照。', $models[0]['model_name'], $time['date']),
                'suggestions' => ['查今天报价', '换一个内存', '看7天行情'],
            ];
        }

        $priceQuery = (new QuotationPrice())->alias('p')
            ->leftJoin('recycle_quotation_v2_field f', 'f.id = p.field_id AND f.site_id = p.site_id')
            ->where([
                ['p.site_id', '=', $siteId],
                ['f.field_type', '=', QuotationV2Dict::FIELD_TYPE_PRICE],
                ['f.status', '=', QuotationV2Dict::STATUS_ENABLED],
            ])->whereIn('p.model_id', $modelIds)->whereIn('p.capacity_id', $capacityIds)
            ->where(function ($query) use ($dateMap) {
                foreach ($dateMap as $datasetId => $priceDate) {
                    $query->whereOr(function ($scope) use ($datasetId, $priceDate) {
                        $scope->where('p.dataset_id', '=', $datasetId)->where('p.price_date', '=', $priceDate);
                    });
                }
            });
        $priceRows = $priceQuery->field('p.dataset_id,p.model_id,p.capacity_id,p.field_name,p.final_price,p.price_date,f.sort,f.id as field_id')
            ->order('p.dataset_id asc,p.model_id asc,p.capacity_id asc,f.sort asc,f.id asc')->select()->toArray();

        $pricesByCapacity = [];
        $allGrades = [];
        foreach ($priceRows as $row) {
            $grade = trim((string)$row['field_name']);
            if ($grade === '') continue;
            $pricesByCapacity[(int)$row['capacity_id']][] = ['grade' => $grade, 'price' => round((float)$row['final_price'], 2)];
            if (!in_array($grade, $allGrades, true)) $allGrades[] = $grade;
        }
        $selectedGrade = $this->selectedGrade($prompt, $allGrades);
        $adjustmentMap = $this->adjustmentMap($siteId, $modelIds, $capacityIds);
        $capacitiesByModel = [];
        foreach ($capacityRows as $row) $capacitiesByModel[(int)$row['model_id']][] = $row;

        $datasets = [];
        foreach ($models as $model) {
            $datasetId = (int)$model['dataset_id'];
            if (empty($dateMap[$datasetId])) continue;
            $actualDate = (string)$dateMap[$datasetId];
            $historical = $time['exact'] || $actualDate !== date('Y-m-d');
            $rows = [];
            foreach ($capacitiesByModel[(int)$model['id']] ?? [] as $capacity) {
                $prices = $pricesByCapacity[(int)$capacity['id']] ?? [];
                if ($prices === []) continue;
                $rows[] = [
                    'capacity_id' => (int)$capacity['id'],
                    'capacity' => (string)$capacity['capacity_name'],
                    'prices' => $prices,
                    'adjustments' => $historical ? [] : ($adjustmentMap[(int)$capacity['id']] ?? []),
                ];
            }
            if ($rows === []) continue;
            $datasets[] = [
                'dataset_id' => $datasetId,
                'quotation_id' => (int)($model['quotation_id'] ?? 0),
                'name' => trim((string)($model['dataset_name'] ?? '')) ?: (trim((string)($model['price_name'] ?? '')) ?: '回收报价单'),
                'model' => (string)$model['model_name'],
                'price_date' => $actualDate,
                'requested_date' => (string)$time['date'],
                'used_latest_snapshot' => !$time['exact'] && $actualDate !== (string)$time['date'],
                'rows' => $rows,
                'adjustment_notice' => $historical ? '历史扣价说明未按日期留存，仅展示当日等级价格。' : '',
                'sort' => (int)($model['dataset_sort'] ?? 0),
            ];
        }
        usort($datasets, static fn(array $a, array $b): int => ((int)($a['sort'] ?? 0) <=> (int)($b['sort'] ?? 0)) ?: ((int)$a['dataset_id'] <=> (int)$b['dataset_id']));
        foreach ($datasets as &$dataset) unset($dataset['sort']);
        unset($dataset);
        if ($datasets === []) {
            return ['status' => 'no_snapshot', 'message' => '已找到型号，但启用的报价单中没有可展示价格。', 'suggestions' => ['查今天报价', '换一个内存']];
        }
        return [
            'status' => 'success',
            'mode' => 'quote_aggregate',
            'model' => (string)$models[0]['model_name'],
            'capacity' => $capacityToken !== '' ? (string)($capacityRows[0]['capacity_name'] ?? '') : '全部容量',
            'requested_date' => (string)$time['date'],
            'source_count' => count($datasets),
            'selected_grade' => $selectedGrade,
            'datasets' => $datasets,
            'disclaimer' => '报价仅供参考，最终价格以实际质检结果为准。',
            'suggestions' => ['看这台7天行情', '只看某个等级', '查询指定日期'],
        ];
    }

    private function trend(int $siteId, array $model, array $capacity, string $prompt, array $time): array
    {
        $start = (string)$time['date'];
        $end = date('Y-m-d', strtotime($start . ' +6 days'));
        $rows = (new QuotationPrice())->alias('p')
            ->leftJoin('recycle_quotation_v2_field f', 'f.id = p.field_id AND f.site_id = p.site_id')
            ->where([
                ['p.site_id', '=', $siteId],
                ['p.dataset_id', '=', (int)$model['dataset_id']],
                ['p.model_id', '=', (int)$model['id']],
                ['p.capacity_id', '=', (int)$capacity['id']],
                ['p.price_date', '>=', $start],
                ['p.price_date', '<=', $end],
                ['f.field_type', '=', QuotationV2Dict::FIELD_TYPE_PRICE],
                ['f.status', '=', QuotationV2Dict::STATUS_ENABLED],
            ])->field('p.price_date,p.field_name,p.final_price,f.sort,f.id as field_id')
            ->order('p.price_date asc,f.sort asc,f.id asc')->select()->toArray();
        $grades = [];
        foreach ($rows as $row) {
            $grade = (string)$row['field_name'];
            if ($grade !== '' && !in_array($grade, $grades, true)) $grades[] = $grade;
        }
        $selectedGrade = $this->selectedGrade($prompt, $grades) ?: (string)($grades[0] ?? '');
        $byDate = [];
        foreach ($rows as $row) $byDate[(string)$row['price_date']][(string)$row['field_name']] = round((float)$row['final_price'], 2);
        $points = [];
        for ($cursor = 0; $cursor < 7; $cursor++) {
            $date = date('Y-m-d', strtotime($start . ' +' . $cursor . ' days'));
            $points[] = ['date' => $date, 'prices' => $byDate[$date] ?? []];
        }
        return [
            'status' => $rows === [] ? 'no_snapshot' : 'success',
            'mode' => 'trend',
            'model' => (string)$model['model_name'],
            'capacity' => (string)$capacity['capacity_name'],
            'capacity_id' => (int)$capacity['id'],
            'start_date' => $start,
            'end_date' => $end,
            'grades' => $grades,
            'selected_grade' => $selectedGrade,
            'points' => $points,
            'message' => $rows === [] ? sprintf('%s 至 %s 没有报价快照。', $start, $end) : '',
            'suggestions' => ['查询指定日期', '查今天报价', '换一个等级'],
        ];
    }

    private function resolveModels(int $siteId, string $prompt): array
    {
        $promptKey = $this->modelKey($prompt);
        if ($promptKey === '') return [];
        $rows = (new QuotationModel())->alias('m')
            ->leftJoin('recycle_quotation_v2_dataset d', 'd.id = m.dataset_id AND d.site_id = m.site_id')
            ->where([
                ['m.site_id', '=', $siteId],
                ['m.status', '=', QuotationV2Dict::STATUS_ENABLED],
                ['d.status', '=', QuotationV2Dict::STATUS_ENABLED],
            ])->field('m.id,m.dataset_id,m.quotation_id,m.model_name,m.price_name,d.dataset_name,d.sort as dataset_sort')->select()->toArray();
        $bestKey = '';
        $bestScore = 0;
        foreach ($rows as $row) {
            $modelKey = $this->modelKey((string)$row['model_name']);
            $shortKey = str_replace(['promax', 'plus'], ['pm', 'plus'], $modelKey);
            $matchedKey = str_contains($promptKey, $modelKey) ? $modelKey : (str_contains($promptKey, $shortKey) ? $shortKey : '');
            if ($matchedKey !== '' && strlen($matchedKey) > $bestScore) {
                $bestKey = $modelKey;
                $bestScore = strlen($matchedKey);
            }
        }
        if ($bestKey === '') return [];
        $matched = array_values(array_filter($rows, fn(array $row): bool => $this->modelKey((string)$row['model_name']) === $bestKey));
        usort($matched, static fn(array $a, array $b): int => ((int)$a['dataset_sort'] <=> (int)$b['dataset_sort']) ?: ((int)$a['dataset_id'] <=> (int)$b['dataset_id']));
        return $matched;
    }

    private function resolveCapacity(int $siteId, int $modelId, string $prompt): ?array
    {
        $rows = (new QuotationCapacity())->where([
            ['site_id', '=', $siteId],
            ['model_id', '=', $modelId],
            ['status', '=', QuotationV2Dict::STATUS_ENABLED],
        ])->field('id,capacity_name')->order('sort asc,id asc')->select()->toArray();
        $promptKey = strtoupper(preg_replace('/\s+/u', '', $prompt) ?: $prompt);
        foreach ($rows as $row) {
            $capacityKey = strtoupper(preg_replace('/\s+/u', '', (string)$row['capacity_name']) ?: (string)$row['capacity_name']);
            if ($capacityKey !== '' && str_contains($promptKey, $capacityKey)) return $row;
            $number = preg_replace('/[^0-9]/', '', $capacityKey) ?: '';
            if ($number !== '' && preg_match('/(?:^|[^0-9])' . preg_quote($number, '/') . '\s*(?:G|GB|T|TB)(?:[^0-9]|$)/iu', $prompt)) return $row;
        }
        return count($rows) === 1 ? $rows[0] : null;
    }

    private function resolveTime(string $prompt): array
    {
        $trend = (bool)preg_match('/行情|走势|趋势|连续\s*7\s*天|七天|7\s*天/iu', $prompt);
        $date = date('Y-m-d');
        $exact = false;
        if (preg_match('/(20\d{2})[年\/-](\d{1,2})[月\/-](\d{1,2})日?/u', $prompt, $match)) {
            $date = sprintf('%04d-%02d-%02d', (int)$match[1], (int)$match[2], (int)$match[3]);
            $exact = !$trend;
        } elseif (preg_match('/(20\d{2})年(\d{1,2})月/u', $prompt, $match)) {
            $date = sprintf('%04d-%02d-01', (int)$match[1], (int)$match[2]);
            $exact = !$trend;
        } elseif (preg_match('/(\d{1,3})\s*天前/u', $prompt, $match)) {
            $date = date('Y-m-d', strtotime('-' . max(1, (int)$match[1]) . ' days'));
            $exact = !$trend;
        } elseif (preg_match('/一周前|上周/u', $prompt)) {
            $date = date('Y-m-d', strtotime('-7 days'));
            $exact = !$trend;
        } elseif ($trend) {
            $date = date('Y-m-d', strtotime('-6 days'));
        }
        return ['trend' => $trend, 'date' => $date, 'exact' => $exact];
    }

    private function adjustmentMap(int $siteId, array $modelIds, array $capacityIds): array
    {
        $rows = (new QuotationNote())->alias('n')
            ->leftJoin('recycle_quotation_v2_field f', 'f.id = n.field_id AND f.site_id = n.site_id')
            ->where([
                ['n.site_id', '=', $siteId],
                ['n.status', '=', QuotationV2Dict::STATUS_ENABLED],
            ])->whereIn('n.model_id', $modelIds)->whereIn('n.capacity_id', $capacityIds)
            ->whereIn('f.field_type', [QuotationV2Dict::FIELD_TYPE_ADJUSTMENT, QuotationV2Dict::FIELD_TYPE_NOTE, QuotationV2Dict::FIELD_TYPE_REMARK])
            ->field('n.capacity_id,n.field_name,n.content_text,f.field_type,f.sort')->order('n.dataset_id asc,f.sort asc,n.id asc')->select()->toArray();
        $map = [];
        foreach ($rows as $row) {
            $content = trim((string)$row['content_text']);
            if ($content === '') continue;
            $map[(int)$row['capacity_id']][] = [
                'name' => trim((string)$row['field_name']),
                'type' => (string)$row['field_type'],
                'content' => $content,
            ];
        }
        return $map;
    }

    private function capacityToken(string $prompt): string
    {
        if (!preg_match('/(?:^|[^0-9])(\d{1,4})\s*(G|GB|T|TB)(?:[^A-Z]|$)/iu', strtoupper($prompt), $match)) return '';
        return (int)$match[1] . (str_starts_with(strtoupper((string)$match[2]), 'T') ? 'T' : 'G');
    }

    private function capacityKey(string $capacity): string
    {
        $capacity = strtoupper(preg_replace('/\s+/u', '', $capacity) ?: $capacity);
        if (!preg_match('/(\d{1,4})(G|GB|T|TB)/u', $capacity, $match)) return $capacity;
        return (int)$match[1] . (str_starts_with((string)$match[2], 'T') ? 'T' : 'G');
    }

    private function selectedGrade(string $prompt, array $grades): string
    {
        $promptKey = $this->textKey($prompt);
        foreach ($grades as $grade) {
            if ($grade !== '' && str_contains($promptKey, $this->textKey((string)$grade))) return (string)$grade;
        }
        return '';
    }

    private function clarification(string $field, string $message): array
    {
        return ['status' => 'needs_clarification', 'missing' => $field, 'message' => $message, 'suggestions' => ['17 Pro Max 256G 量级', '苹果 16 Pro 256G', '看苹果最近7天行情']];
    }

    private function modelKey(string $value): string
    {
        $value = mb_strtolower($value);
        $value = str_replace(['苹果', 'iphone'], '', $value);
        return preg_replace('/[^a-z0-9]/', '', $value) ?: '';
    }

    private function textKey(string $value): string
    {
        return mb_strtolower(preg_replace('/\s+/u', '', $value) ?: $value);
    }
}
