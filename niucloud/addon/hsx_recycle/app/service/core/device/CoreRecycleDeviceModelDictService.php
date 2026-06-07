<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\device;

use addon\hsx_recycle\app\model\device\RecycleDeviceModelDict;
use core\base\BaseCoreService;

/**
 * 设备型号字典核心服务。
 *
 * Admin 和 API 共用这里的搜索口径，避免前后台型号检索结果不一致。
 */
class CoreRecycleDeviceModelDictService extends BaseCoreService
{
    /**
     * @var RecycleDeviceModelDict
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeviceModelDict();
    }

    public function searchLeafOptions(int $siteId, string $keyword = '', int $limit = 300, array $extraIdFields = []): array
    {
        if ($siteId <= 0) {
            return [];
        }

        $keyword = trim($keyword);
        $limit = max(1, min(500, $limit));
        $field = 'id,pid,level,node_name,model_full_name,is_hot,select_count,node_type,source,source_node_id,source_parent_id,category_source_id,brand_source_id,series_source_id,product_source_id,extra_json';
        $query = $this->model->where([
            ['site_id', '=', $siteId],
            ['status', '=', 1],
            ['level', '>', 1],
        ]);

        if ($keyword !== '') {
            $this->applyKeywordFilter($query, $keyword, $extraIdFields);
        }

        $rows = $query->field($field)
            ->order('is_hot desc, select_count desc, sort asc, id desc')
            ->limit($limit)
            ->select()
            ->toArray();

        $leaves = array_values($this->filterLeafNodes($rows, $siteId));
        $leafIds = array_column($leaves, 'id');
        $parentRows = array_values(array_filter($rows, static fn($row) => !in_array($row['id'], $leafIds)));

        $parentPaths = array_values(array_filter(array_map(static fn($row) => trim((string)($row['model_full_name'] ?? '')), $parentRows)));
        if (!empty($parentPaths)) {
            $descendantQuery = $this->model->where([
                ['site_id', '=', $siteId],
                ['status', '=', 1],
            ]);
            $descendantQuery->where(function ($subQuery) use ($parentPaths) {
                foreach ($parentPaths as $path) {
                    $subQuery->whereOr('model_full_name', 'like', $path . '/%');
                }
            });
            $descendantRows = $descendantQuery->field($field)
                ->order('is_hot desc, select_count desc, sort asc, id desc')
                ->limit($limit)
                ->select()
                ->toArray();
            $leaves = array_merge($leaves, array_values($this->filterLeafNodes($descendantRows, $siteId)));
        }

        $uniqueRows = [];
        foreach ($leaves as $row) {
            $uniqueRows[(int)$row['id']] = $row;
            if (count($uniqueRows) >= $limit) {
                break;
            }
        }

        return array_map([$this, 'formatNode'], array_values($uniqueRows));
    }

    public function applyKeywordFilter($query, string $keyword, array $extraFields = []): void
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return;
        }

        $likeKeyword = "%{$keyword}%";
        $normalizedKeyword = $this->normalizeSearchKeyword($keyword);
        $normalizedLike = "%{$normalizedKeyword}%";
        $idFields = array_values(array_unique(array_merge([
            'source_node_id',
            'category_source_id',
            'brand_source_id',
            'series_source_id',
            'product_source_id',
        ], $extraFields)));

        $query->where(function ($subQuery) use ($likeKeyword, $normalizedKeyword, $normalizedLike, $idFields) {
            $subQuery->whereOr('node_name', 'like', $likeKeyword)
                ->whereOr('model_full_name', 'like', $likeKeyword);

            if ($normalizedKeyword !== '') {
                $subQuery
                    ->whereOrRaw($this->normalizedSearchExpression('node_name') . ' like ?', [$normalizedLike])
                    ->whereOrRaw($this->normalizedSearchExpression('model_full_name') . ' like ?', [$normalizedLike]);
            }

            foreach ($idFields as $field) {
                $subQuery->whereOr($field, 'like', $likeKeyword);
            }
        });
    }

    public function filterLeafNodes(array $rows, int $siteId = 0): array
    {
        $ids = array_map(static fn($row) => (int)($row['id'] ?? 0), $rows);
        if (empty($ids)) {
            return [];
        }

        $query = $this->model->where('pid', 'in', $ids)->where('status', '=', 1);
        if ($siteId > 0) {
            $query->where('site_id', '=', $siteId);
        }
        $parentIds = $query->column('pid');
        $parentIdMap = array_flip(array_map('intval', $parentIds));

        return array_values(array_filter($rows, static fn($row) => !isset($parentIdMap[(int)($row['id'] ?? 0)])));
    }

    public function formatNode(array $row): array
    {
        $parts = explode('/', (string)($row['model_full_name'] ?? $row['node_name'] ?? ''));
        $extra = [];
        if (!empty($row['extra_json'])) {
            $decoded = json_decode((string)$row['extra_json'], true);
            if (is_array($decoded)) {
                $extra = $decoded;
            }
        }

        return array_merge($row, [
            'brand_name' => $parts[0] ?? '',
            'series_name' => count($parts) === 3 ? ($parts[1] ?? '') : '',
            'model_name' => $parts[count($parts) - 1] ?? '',
            'extra' => $extra,
            'leaf' => true,
            'has_children' => 0,
        ]);
    }

    private function normalizeSearchKeyword(string $value): string
    {
        $value = strtolower(trim($value));
        return preg_replace('/[\s\-_\/\\\\.　]+/u', '', $value) ?: '';
    }

    private function normalizedSearchExpression(string $field): string
    {
        $expr = "LOWER({$field})";
        foreach ([' ', '　', '-', '_', '/', '\\\\', '.'] as $char) {
            $expr = "REPLACE({$expr}, '{$char}', '')";
        }
        return $expr;
    }
}
