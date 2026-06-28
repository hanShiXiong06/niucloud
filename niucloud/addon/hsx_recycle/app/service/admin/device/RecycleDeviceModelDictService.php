<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device;

use addon\hsx_recycle\app\model\device\RecycleDeviceModelDict;
use addon\hsx_recycle\app\service\core\device\CoreRecycleDeviceModelDictService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Cache;

/**
 * 回收设备分类服务
 *
 * 设备分类树：pid + level + model_full_name 表达层级。
 * 层级语义由 node_type/source 数据决定，不按固定层级写死。
 */
class RecycleDeviceModelDictService extends BaseAdminService
{
    protected $model;
    protected CoreRecycleDeviceModelDictService $coreService;

    /** 型号字典缓存 tag 前缀(按站点隔离:CACHE_TAG . site_id);写操作后整体清空 */
    const CACHE_TAG = 'recycle_device_model_dict_';

    /** 缓存有效期(秒)。型号字典极少改,设长一点;改动时按 tag 主动失效 */
    const CACHE_TTL = 86400;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeviceModelDict();
        $this->coreService = new CoreRecycleDeviceModelDictService();
    }

    public function getPage(array $where = []): array
    {
        $query = $this->model->where([['site_id', '=', $this->site_id], ['level', '>', 1]]);
        if (!empty($where['keyword'])) {
            $this->coreService->applyKeywordFilter($query, trim((string)$where['keyword']), ['source_node_id']);
        }
        if ($where['status'] !== '' && $where['status'] !== null) {
            $query->where('status', (int)$where['status']);
        }

        $result = $this->pageQuery($query->order('is_hot desc, select_count desc, sort asc, id desc'));
        foreach (['data', 'list'] as $key) {
            if (!empty($result[$key]) && is_array($result[$key])) {
                $result[$key] = array_values(array_map([$this, 'formatNode'], $this->filterLeafNodes($result[$key])));
            }
        }
        return $result;
    }

    public function tree(): array
    {
        // 整棵树缓存:命中直接返回,不查库;写操作会按 tag 失效
        return cache_remember(
            self::CACHE_TAG . 'tree_' . $this->site_id,
            function () {
                $rows = $this->model->where([['site_id', '=', $this->site_id]])
                    ->field('id,pid,level,node_name,model_full_name,status,sort,is_hot,select_count,node_type,source,source_node_id,source_parent_id,category_source_id,brand_source_id,series_source_id,product_source_id,extra_json')
                    ->order('level asc, is_hot desc, select_count desc, sort asc, id asc')
                    ->select()
                    ->toArray();
                return $this->buildTree($rows);
            },
            self::CACHE_TAG . $this->site_id,
            ['expire' => self::CACHE_TTL]
        );
    }

    public function options(array $where = []): array
    {
        $keyword = trim((string)($where['keyword'] ?? ''));
        return $this->coreService->searchLeafOptions((int)$this->site_id, $keyword);
    }

    public function children(array $where = []): array
    {
        $pid = (int)($where['pid'] ?? 0);
        $keyword = trim((string)($where['keyword'] ?? ''));
        $limit = max(20, min(500, (int)($where['limit'] ?? 200)));

        $fetch = function () use ($pid, $keyword, $limit) {
            $query = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['pid', '=', $pid],
                ['status', '=', 1],
            ]);
            if ($keyword !== '') {
                $this->coreService->applyKeywordFilter($query, $keyword);
            }
            $rows = $query->field('id,pid,level,node_name,model_full_name,status,sort,is_hot,select_count,node_type,source,source_node_id,source_parent_id,category_source_id,brand_source_id,series_source_id,product_source_id,extra_json')
                ->order('is_hot desc, select_count desc, sort asc, id asc')
                ->limit($limit)
                ->select()
                ->toArray();
            return $this->appendChildrenState($rows);
        };

        // 带关键字的是搜索,不缓存;纯按 pid 浏览某一层才缓存(懒加载热点路径,命中即不查库)
        if ($keyword !== '') {
            return $fetch();
        }
        return cache_remember(
            self::CACHE_TAG . 'children_' . $this->site_id . '_' . $pid . '_' . $limit,
            $fetch,
            self::CACHE_TAG . $this->site_id,
            ['expire' => self::CACHE_TTL]
        );
    }

    /**
     * 清空本站点型号字典缓存(tree/未来可能的子级缓存)。
     * 只在型号库发生变更(增删改/导入/排序)时调用 —— 这样读取才"除非数据变了否则不查库"。
     */
    private function clearTreeCache(): void
    {
        Cache::tag(self::CACHE_TAG . $this->site_id)->clear();
    }

    public function add(array $data): int
    {
        $path = $this->normalizePath($data);
        $id = $this->createPath($path, (int)($data['status'] ?? 1), (int)($data['sort'] ?? 0), false);
        $this->clearTreeCache();
        return $id;
    }

    public function edit(int $id, array $data): bool
    {
        $node = $this->getNode($id);
        if ($this->hasChildren($id)) {
            throw new CommonException('存在下级分类，不能直接改成其他路径');
        }

        $path = $this->normalizePath($data);
        $targetId = $this->createPath($path, (int)($data['status'] ?? 1), (int)($data['sort'] ?? 0), false);
        if ($targetId !== $id) {
            $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->delete();
        } else {
            $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update([
                'status' => (int)($data['status'] ?? $node['status']),
                'sort' => (int)($data['sort'] ?? $node['sort']),
                'update_at' => time(),
            ]);
        }
        $this->clearTreeCache();
        return true;
    }

    public function delete(int $id): bool
    {
        $this->getNode($id);
        if ($this->hasChildren($id)) {
            throw new CommonException('请先删除下级分类');
        }
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->delete();
        $this->clearTreeCache();
        return true;
    }

    public function quickAdd(string $content): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $paths = [];
        $inputKeys = [];
        $duplicates = [];

        foreach ($lines as $lineNo => $line) {
            $line = trim((string)$line);
            if ($line === '') {
                continue;
            }
            $parts = $this->normalizePathFromLine($line, $lineNo + 1);
            $key = implode('/', $parts);
            if (isset($inputKeys[$key])) {
                $duplicates[] = $key;
                continue;
            }
            $inputKeys[$key] = true;
            $paths[] = $parts;
        }

        if (empty($paths)) {
            throw new CommonException('请输入要录入的设备分类');
        }

        foreach ($paths as $parts) {
            if ($this->pathExists($parts)) {
                $duplicates[] = implode('/', $parts);
            }
        }
        if (!empty($duplicates)) {
            return [
                'created_count' => 0,
                'duplicates' => array_values(array_unique($duplicates)),
            ];
        }

        foreach ($paths as $parts) {
            $this->createPath($parts, 1, 0, true);
        }
        $this->clearTreeCache();
        return [
            'created_count' => count($paths),
            'duplicates' => [],
        ];
    }

    public function importExternalRows(array $rows, string $source = 'recycle_spider'): array
    {
        $source = trim($source) !== '' ? trim($source) : 'recycle_spider';
        $created = 0;
        $updated = 0;
        $skipped = [];

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                $skipped[] = [
                    'line' => $index + 1,
                    'reason' => '行数据格式错误',
                ];
                continue;
            }

            try {
                $payload = $this->normalizeExternalRow($row, $source);
                $beforeExists = $this->pathExists($payload['parts']);
                $this->createPath($payload['parts'], 1, 0, true, $payload['node_metas']);
                $beforeExists ? $updated++ : $created++;
            } catch (\Throwable $e) {
                $skipped[] = [
                    'line' => $index + 1,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        if ($created > 0 || $updated > 0) {
            $this->clearTreeCache();
        }
        return [
            'created_count' => $created,
            'updated_count' => $updated,
            'skipped_count' => count($skipped),
            'skipped' => $skipped,
        ];
    }

    public function updateSort(array $rows): bool
    {
        if (empty($rows)) {
            return true;
        }

        $now = time();
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $sort = (int)($row['sort'] ?? 0);
            $cascade = (int)($row['cascade'] ?? 0) === 1;
            if ($cascade) {
                $node = $this->getNode($id);
                $path = trim((string)($node['model_full_name'] ?? ''));
                if ($path === '') {
                    continue;
                }
                $this->model->where([['site_id', '=', $this->site_id]])
                    ->where(function ($query) use ($path) {
                        $query->where('model_full_name', '=', $path)
                            ->whereOr('model_full_name', 'like', $path . '/%');
                    })
                    ->update([
                        'sort' => $sort,
                        'update_at' => $now,
                    ]);
                continue;
            }

            $this->model->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $id],
            ])->update([
                'sort' => $sort,
                'update_at' => $now,
            ]);
        }
        $this->clearTreeCache();
        return true;
    }

    public function incrementSelectCount(int $nodeId): void
    {
        if ($nodeId <= 0) {
            return;
        }
        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $nodeId],
        ])->inc('select_count')->update(['update_at' => time()]);
    }

    public function ensureFromModelName(string $modelName, int $siteId = 0): void
    {
        $modelName = trim($modelName);
        if ($modelName === '') {
            return;
        }

        $oldSiteId = $this->site_id;
        if ($siteId > 0) {
            $this->site_id = $siteId;
        }

        try {
            if (strpos($modelName, '/') !== false) {
                $this->createPath($this->normalizePathFromLine($modelName, 1), 1, 0, true);
                return;
            }

            $exists = $this->model->where([
                ['site_id', '=', $this->site_id],
                ['level', '>', 1],
                ['node_name', '=', $modelName],
            ])->count();
            if ($exists > 0) {
                return;
            }

            $this->createPath(['未归类', $modelName], 1, 0, true);
        } finally {
            $this->site_id = $oldSiteId;
        }
    }

    private function normalizePath(array $data): array
    {
        $parts = $this->normalizePathInput($data);

        if (count($parts) < 2) {
            throw new CommonException('请至少填写品类和末级设备');
        }
        if (count($parts) > 8) {
            throw new CommonException('设备分类层级最多支持八级');
        }
        return $parts;
    }

    private function normalizePathInput(array $data): array
    {
        if (!empty($data['path']) && is_array($data['path'])) {
            return array_values(array_filter(array_map(static fn($value) => trim((string)$value), $data['path']), static fn($value) => $value !== ''));
        }

        return array_values(array_filter([
            trim((string)($data['category_name'] ?? '')),
            trim((string)($data['subcategory_name'] ?? '')),
            trim((string)($data['brand_name'] ?? '')),
            trim((string)($data['series_name'] ?? '')),
            trim((string)($data['model_name'] ?? '')),
        ], static fn($value) => $value !== ''));
    }

    private function normalizePathFromLine(string $line, int $lineNo): array
    {
        $parts = array_values(array_filter(array_map('trim', explode('/', $line)), static fn($value) => $value !== ''));
        if (count($parts) < 2 || count($parts) > 8) {
            throw new CommonException('第' . $lineNo . '行格式错误，请使用斜杠分隔完整路径，如 品类/品牌/系列/型号');
        }
        return $parts;
    }

    private function normalizeExternalRow(array $row, string $source): array
    {
        $categoryText = $this->getRowValue($row, ['品类', 'category', 'category_name']);
        $categoryId = $this->getRowValue($row, ['品类ID', 'category_id']);
        $brandName = $this->getRowValue($row, ['品牌', 'brand', 'brand_name']);
        $brandId = $this->getRowValue($row, ['品牌ID', 'brand_id']);
        $seriesName = $this->getRowValue($row, ['系列', 'series', 'series_name']);
        $modelName = $this->getRowValue($row, ['型号', 'model', 'model_name', 'goods_name']);
        $productId = $this->getRowValue($row, ['产品ID', 'product_id', 'goods_id']);
        $isHot = $this->parseHotValue($this->getRowValue($row, ['热门', 'is_hot', 'hot']));

        $categoryParts = $this->splitPathText($categoryText);
        $brandName = trim($brandName);
        $seriesName = trim($seriesName) !== '' ? trim($seriesName) : '其他';
        $modelName = trim($modelName);

        if (empty($categoryParts)) {
            throw new CommonException('缺少品类');
        }
        if ($brandName === '') {
            throw new CommonException('缺少品牌');
        }
        if ($modelName === '') {
            throw new CommonException('缺少型号');
        }

        $parts = array_merge($categoryParts, [$brandName, $seriesName, $modelName]);
        if (count($parts) > 8) {
            throw new CommonException('设备分类层级最多支持八级');
        }

        $nodeMetas = [];
        $sourceParentId = '';
        $lastCategorySourceId = '';
        foreach ($categoryParts as $index => $name) {
            $isLastCategory = $index === count($categoryParts) - 1;
            $sourceNodeId = $isLastCategory && $categoryId !== ''
                ? $categoryId
                : $this->buildSyntheticSourceId('category', array_slice($categoryParts, 0, $index + 1));
            $nodeMetas[] = [
                'node_type' => $index === 0 ? 'category' : 'subcategory',
                'source' => $source,
                'source_node_id' => $sourceNodeId,
                'source_parent_id' => $sourceParentId,
                'category_source_id' => $isLastCategory ? $categoryId : '',
                'brand_source_id' => '',
                'series_source_id' => '',
                'product_source_id' => '',
                'extra_json' => [
                    'raw_category_path' => $categoryText,
                ],
            ];
            $sourceParentId = $sourceNodeId;
            if ($isLastCategory) {
                $lastCategorySourceId = $sourceNodeId;
            }
        }

        $brandSourceId = $brandId !== ''
            ? $brandId
            : $this->buildSyntheticSourceId('brand', [$sourceParentId, $brandName]);
        $nodeMetas[] = [
            'node_type' => 'brand',
            'source' => $source,
            'source_node_id' => $brandSourceId,
            'source_parent_id' => $lastCategorySourceId,
            'category_source_id' => $categoryId,
            'brand_source_id' => $brandId,
            'series_source_id' => '',
            'product_source_id' => '',
            'extra_json' => [],
        ];

        $seriesSourceId = $this->buildSyntheticSourceId('series', [$categoryId ?: $sourceParentId, $brandSourceId, $seriesName]);
        $nodeMetas[] = [
            'node_type' => 'series',
            'source' => $source,
            'source_node_id' => $seriesSourceId,
            'source_parent_id' => $brandSourceId,
            'category_source_id' => $categoryId,
            'brand_source_id' => $brandId,
            'series_source_id' => $seriesSourceId,
            'product_source_id' => '',
            'extra_json' => [],
        ];

        $nodeMetas[] = [
            'node_type' => 'model',
            'source' => $source,
            'source_node_id' => $productId !== '' ? $productId : $this->buildSyntheticSourceId('model', $parts),
            'source_parent_id' => $seriesSourceId,
            'category_source_id' => $categoryId,
            'brand_source_id' => $brandId,
            'series_source_id' => $seriesSourceId,
            'product_source_id' => $productId,
            'is_hot' => $isHot,
            'extra_json' => [
                'is_hot' => $isHot,
                'raw' => $row,
            ],
        ];

        return [
            'parts' => $parts,
            'node_metas' => $nodeMetas,
        ];
    }

    private function getRowValue(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return trim((string)$row[$key]);
            }
        }
        return '';
    }

    private function splitPathText(string $text): array
    {
        return array_values(array_filter(array_map(static fn($value) => trim((string)$value), explode('/', $text)), static fn($value) => $value !== ''));
    }

    private function parseHotValue(string $value): int
    {
        $value = trim($value);
        return in_array($value, ['1', '是', 'true', 'TRUE', 'yes', 'YES'], true) ? 1 : 0;
    }

    private function buildSyntheticSourceId(string $prefix, array $parts): string
    {
        return $prefix . ':' . md5(implode('/', array_map('strval', $parts)));
    }

    private function updateNodeMeta(int $id, array $meta): void
    {
        $data = [
            'update_at' => time(),
        ];
        foreach (['node_type', 'source', 'source_node_id', 'source_parent_id', 'category_source_id', 'brand_source_id', 'series_source_id', 'product_source_id'] as $field) {
            if (isset($meta[$field]) && trim((string)$meta[$field]) !== '') {
                $data[$field] = (string)$meta[$field];
            }
        }
        if (array_key_exists('is_hot', $meta)) {
            $data['is_hot'] = (int)$meta['is_hot'];
        }
        if (array_key_exists('extra_json', $meta)) {
            $data['extra_json'] = $this->encodeExtraJson($meta['extra_json']);
        }
        $this->model->where([['site_id', '=', $this->site_id], ['id', '=', $id]])->update($data);
    }

    private function encodeExtraJson($data): string
    {
        if (empty($data)) {
            return '';
        }
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return is_string($json) ? $json : '';
    }

    private function createPath(array $parts, int $status, int $sort, bool $allowExisting, array $nodeMetas = []): int
    {
        $pid = 0;
        $fullPath = [];
        $leafId = 0;
        foreach ($parts as $index => $name) {
            $level = $index + 1;
            $fullPath[] = $name;
            $meta = $nodeMetas[$index] ?? [];
            $pathSourceId = $this->buildSyntheticSourceId('path', $fullPath);
            $node = $this->findNode($pid, $name);
            if (empty($node)) {
                $node = $this->createNodeSafely($pid, $level, $name, $fullPath, $status, $sort, $meta, $pathSourceId);
            } elseif ($level === count($parts) && !$allowExisting) {
                throw new CommonException('该设备分类已存在：' . implode('/', $parts));
            } elseif (!empty($meta)) {
                $this->updateNodeMeta((int)$node['id'], $meta);
            }

            $pid = (int)$node['id'];
            $leafId = $pid;
        }
        return $leafId;
    }

    private function createNodeSafely(
        int $pid,
        int $level,
        string $name,
        array $fullPath,
        int $status,
        int $sort,
        array $meta,
        string $pathSourceId
    ): array {
        $data = [
            'site_id' => $this->site_id,
            'pid' => $pid,
            'level' => $level,
            'node_name' => $name,
            'model_full_name' => implode('/', $fullPath),
            'status' => $status,
            'sort' => $sort,
            'node_type' => $meta['node_type'] ?? $this->inferNodeType($level, count($fullPath)),
            'source' => $meta['source'] ?? 'manual',
            'source_node_id' => (string)($meta['source_node_id'] ?? $pathSourceId),
            'source_parent_id' => (string)($meta['source_parent_id'] ?? ''),
            'category_source_id' => (string)($meta['category_source_id'] ?? ''),
            'brand_source_id' => (string)($meta['brand_source_id'] ?? ''),
            'series_source_id' => (string)($meta['series_source_id'] ?? ''),
            'product_source_id' => (string)($meta['product_source_id'] ?? ''),
            'extra_json' => $this->encodeExtraJson($meta['extra_json'] ?? []),
            'is_hot' => (int)($meta['is_hot'] ?? 0),
            'select_count' => 0,
            'create_at' => time(),
            'update_at' => time(),
        ];

        try {
            return $this->model->create($data)->toArray();
        } catch (\Throwable $e) {
            $node = $this->findNode($pid, $name);
            if (!empty($node)) {
                if (!empty($meta)) {
                    $this->updateNodeMeta((int)$node['id'], $meta);
                }
                return $node;
            }

            $data['source_node_id'] = $this->buildSyntheticSourceId('path_retry', [
                $this->site_id,
                $pid,
                implode('/', $fullPath),
                microtime(true),
            ]);
            try {
                return $this->model->create($data)->toArray();
            } catch (\Throwable $retryException) {
                throw new CommonException($retryException->getMessage());
            }
        }
    }

    private function pathExists(array $parts): bool
    {
        $pid = 0;
        foreach ($parts as $name) {
            $node = $this->findNode($pid, $name);
            if (empty($node)) {
                return false;
            }
            $pid = (int)$node['id'];
        }
        return true;
    }

    private function findNode(int $pid, string $name): array
    {
        return $this->model->where([
            ['site_id', '=', $this->site_id],
            ['pid', '=', $pid],
            ['node_name', '=', $name],
        ])->findOrEmpty()->toArray();
    }

    private function getNode(int $id): array
    {
        $node = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();
        if (empty($node)) {
            throw new CommonException('设备分类不存在');
        }
        return $node;
    }

    private function hasChildren(int $id): bool
    {
        return $this->model->where([['site_id', '=', $this->site_id], ['pid', '=', $id]])->count() > 0;
    }

    private function formatNode(array $node): array
    {
        $parts = explode('/', (string)($node['model_full_name'] ?? $node['node_name'] ?? ''));
        $node['brand_name'] = $parts[0] ?? '';
        $node['series_name'] = count($parts) === 3 ? ($parts[1] ?? '') : '';
        $node['model_name'] = $parts[count($parts) - 1] ?? '';
        return $node;
    }

    private function filterLeafNodes(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $parentIds = $this->model->where([['site_id', '=', $this->site_id]])
            ->whereIn('pid', array_column($rows, 'id') ?: [0])
            ->column('pid');
        $parentMap = $this->buildParentMap($parentIds);

        return array_filter($rows, static fn($row) => !isset($parentMap[(int)$row['id']]));
    }

    private function buildParentMap(array $parentIds): array
    {
        return array_flip(array_map('intval', $parentIds));
    }

    private function appendChildrenState(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        $parentIds = $this->model->where([['site_id', '=', $this->site_id]])
            ->whereIn('pid', array_column($rows, 'id') ?: [0])
            ->column('pid');
        $parentMap = $this->buildParentMap($parentIds);

        return array_map(function ($row) use ($parentMap) {
            $row['has_children'] = isset($parentMap[(int)$row['id']]) ? 1 : 0;
            $row['child_list'] = [];
            return $this->formatNode($row);
        }, $rows);
    }

    private function buildTree(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $row['child_list'] = [];
            $items[(int)$row['id']] = $row;
        }

        $tree = [];
        foreach ($items as $id => &$item) {
            $pid = (int)$item['pid'];
            if ($pid > 0 && isset($items[$pid])) {
                $items[$pid]['child_list'][] = &$item;
            } else {
                $tree[] = &$item;
            }
        }
        unset($item);
        return $tree;
    }

    private function inferNodeType(int $level, int $totalLevel): string
    {
        if ($level === 1) {
            return 'category';
        }
        if ($level === $totalLevel) {
            return 'model';
        }
        return 'group';
    }

}
