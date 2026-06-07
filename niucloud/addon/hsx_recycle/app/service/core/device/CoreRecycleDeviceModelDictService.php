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
        $field = 'id,site_id,pid,level,node_name,model_full_name,is_hot,select_count,node_type,source,source_node_id,source_parent_id,category_source_id,brand_source_id,series_source_id,product_source_id,extra_json';
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

    public function tree(int $siteId): array
    {
        if ($siteId <= 0) {
            return [];
        }

        $rows = $this->model->where([
            ['site_id', '=', $siteId],
            ['status', '=', 1],
        ])->field('id,site_id,pid,level,node_name,model_full_name,is_hot,select_count,node_type,source,source_node_id,source_parent_id,category_source_id,brand_source_id,series_source_id,product_source_id,extra_json')
            ->order('level asc, is_hot desc, select_count desc, sort asc, id asc')
            ->select()
            ->toArray();

        $items = [];
        foreach ($rows as $row) {
            $row = $this->formatNode($row);
            $row['child_list'] = [];
            $row['children'] = [];
            $items[(int)$row['id']] = $row;
        }

        $tree = [];
        foreach ($items as $id => &$item) {
            $pid = (int)($item['pid'] ?? 0);
            if ($pid > 0 && isset($items[$pid])) {
                $items[$pid]['child_list'][] = &$item;
                $items[$pid]['children'][] = &$item;
                $items[$pid]['leaf'] = false;
                $items[$pid]['has_children'] = 1;
            } else {
                $tree[] = &$item;
            }
        }
        unset($item);

        return $tree;
    }

    public function children(int $siteId, int $pid = 0, string $keyword = '', int $limit = 200): array
    {
        if ($siteId <= 0) {
            return [];
        }

        $limit = max(20, min(500, $limit));
        $query = $this->model->where([
            ['site_id', '=', $siteId],
            ['pid', '=', $pid],
            ['status', '=', 1],
        ]);

        if (trim($keyword) !== '') {
            $this->applyKeywordFilter($query, trim($keyword));
        }

        $rows = $query->field('id,site_id,pid,level,node_name,model_full_name,is_hot,select_count,node_type,source,source_node_id,source_parent_id,category_source_id,brand_source_id,series_source_id,product_source_id,extra_json')
            ->order('is_hot desc, select_count desc, sort asc, id asc')
            ->limit($limit)
            ->select()
            ->toArray();

        if (empty($rows)) {
            return [];
        }

        $parentIds = $this->model->where([
            ['site_id', '=', $siteId],
            ['status', '=', 1],
        ])->whereIn('pid', array_column($rows, 'id') ?: [0])->column('pid');
        $parentMap = array_flip(array_map('intval', $parentIds));

        return array_map(function ($row) use ($parentMap) {
            $row = $this->formatNode($row);
            $row['has_children'] = isset($parentMap[(int)$row['id']]) ? 1 : 0;
            $row['child_list'] = [];
            $row['children'] = [];
            $row['leaf'] = empty($row['has_children']);
            return $row;
        }, $rows);
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
            'category_path' => $this->buildCategoryPathIds($row),
            'category_path_names' => $parts,
            'leaf' => true,
            'has_children' => 0,
        ]);
    }

    private function buildCategoryPathIds(array $row): array
    {
        $siteId = (int)($row['site_id'] ?? 0);
        $id = (int)($row['id'] ?? 0);
        if ($siteId <= 0 || $id <= 0) {
            return $id > 0 ? [$id] : [];
        }

        $path = [];
        $currentId = $id;
        $guard = 0;
        while ($currentId > 0 && $guard < 12) {
            $node = $this->model->where([
                ['site_id', '=', $siteId],
                ['id', '=', $currentId],
            ])->field('id,pid')->findOrEmpty()->toArray();
            if (empty($node)) {
                break;
            }
            array_unshift($path, (int)$node['id']);
            $currentId = (int)($node['pid'] ?? 0);
            $guard++;
        }

        return $path;
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
