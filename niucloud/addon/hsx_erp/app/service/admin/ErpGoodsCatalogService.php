<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

/**
 * ERP 标准商品目录。
 *
 * 标准产品以 source_key + source_product_id 全局幂等；商家只能通过带 site_id
 * 的绑定表读取和覆盖名称/分类，避免跨站点数据污染。
 */
class ErpGoodsCatalogService extends BaseAdminService
{
    public function productSnapshot(int $siteProductId): array
    {
        if ($siteProductId <= 0) return [
            'catalog_product_id' => 0, 'category_name' => '', 'category_path' => '',
            'brand_name' => '', 'series_name' => '', 'product_name' => '',
        ];
        $row = Db::name('erp_site_catalog_product')->where([
            ['site_id', '=', $this->site_id], ['site_product_id', '=', $siteProductId], ['is_enabled', '=', 1],
        ])->field('site_product_id,category_path,brand_name,series_name,product_name')->find();
        if (!$row) throw new AdminException('选择的商品型号不存在或已停用');
        $parts = $this->catalogPathSegments((string)$row['category_path']);
        return [
            'catalog_product_id' => (int)$row['site_product_id'],
            'category_name' => $parts ? (string)end($parts) : '',
            'category_path' => (string)$row['category_path'],
            'brand_name' => (string)$row['brand_name'], 'series_name' => (string)$row['series_name'],
            'product_name' => (string)$row['product_name'],
        ];
    }

    public function lists(array $where = []): array
    {
        $page = max(1, (int)($where['page'] ?? 1));
        $limit = min(200, max(10, (int)($where['limit'] ?? 30)));
        $query = Db::name('erp_site_catalog_product')->alias('sp')
            ->leftJoin('erp_catalog_product_master mp', 'mp.master_product_id = sp.master_product_id')
            ->where('sp.site_id', '=', $this->site_id);

        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->whereLike('sp.product_name|sp.brand_name|sp.series_name|sp.category_path|mp.source_product_id', '%' . $keyword . '%');
        }
        if (trim((string)($where['category_path'] ?? '')) !== '') $query->where('sp.category_path', '=', trim((string)$where['category_path'], " /\t\n\r\0\x0B"));
        if (trim((string)($where['brand_name'] ?? '')) !== '') $query->where('sp.brand_name', '=', trim((string)$where['brand_name']));
        if (trim((string)($where['series_name'] ?? '')) !== '') $query->where('sp.series_name', '=', trim((string)$where['series_name']));
        if (($where['is_enabled'] ?? '') !== '') $query->where('sp.is_enabled', '=', (int)$where['is_enabled']);

        $total = (int)(clone $query)->count();
        $list = $query->field('sp.site_product_id,sp.master_product_id,sp.category_path,sp.product_name,sp.brand_name,sp.series_name,sp.is_enabled,sp.sort,sp.create_at,sp.update_at,mp.source_key,mp.source_product_id,mp.category_source_id,mp.brand_source_id')
            ->order('sp.sort desc,sp.brand_name asc,sp.series_name asc,sp.product_name asc')
            ->page($page, $limit)->select()->toArray();

        $base = Db::name('erp_site_catalog_product')->where('site_id', '=', $this->site_id);
        $brands = (clone $base)->where('brand_name', '<>', '')->group('brand_name')->order('brand_name asc')->column('brand_name');
        $series = (clone $base)->where('series_name', '<>', '')->group('series_name')->order('series_name asc')->column('series_name');
        return [
            'list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit,
            'filters' => ['brands' => array_values($brands), 'series' => array_values($series)],
        ];
    }

    public function summary(): array
    {
        $query = Db::name('erp_site_catalog_product')->where('site_id', '=', $this->site_id);
        $brands = (clone $query)->where('brand_name', '<>', '')->column('brand_name');
        $series = (clone $query)->where('series_name', '<>', '')->column('series_name');
        return [
            'products' => (int)(clone $query)->count(),
            'enabled' => (int)(clone $query)->where('is_enabled', '=', 1)->count(),
            'brands' => count(array_unique(array_map('strval', $brands))),
            'series' => count(array_unique(array_map('strval', $series))),
        ];
    }

    /** 新增本站商品型号。平台主模板只保存来源事实，本站可独立维护展示数据。 */
    public function add(array $data): int
    {
        $payload = $this->catalogProductPayload($data);
        $this->assertSiteProductUnique($payload);
        $now = time();
        $siteProductId = 0;
        Db::transaction(function () use ($payload, $now, &$siteProductId) {
            $sourceProductId = sprintf('site:%d:%s:%04d', (int)$this->site_id, date('YmdHis', $now), random_int(0, 9999));
            $hashData = [$payload['category_path'], '', $payload['brand_name'], '', $payload['series_name'], $payload['product_name']];
            $masterId = (int)Db::name('erp_catalog_product_master')->insertGetId([
                'created_site_id' => (int)$this->site_id,
                'source_key' => 'erp_manual',
                'source_product_id' => $sourceProductId,
                'category_source_id' => '',
                'category_path' => $payload['category_path'],
                'brand_source_id' => '',
                'brand_name' => $payload['brand_name'],
                'series_name' => $payload['series_name'],
                'product_name' => $payload['product_name'],
                'data_hash' => hash('sha256', json_encode($hashData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $siteProductId = (int)Db::name('erp_site_catalog_product')->insertGetId(array_merge($payload, [
                'site_id' => (int)$this->site_id,
                'master_product_id' => $masterId,
                'create_at' => $now,
                'update_at' => $now,
            ]));
        });
        $this->emitCatalogChanged('manual_create', ['site_product_id' => $siteProductId]);
        return $siteProductId;
    }

    /** 编辑只修改本站覆盖数据，不污染其他站点共享的标准模板。 */
    public function edit(int $siteProductId, array $data): bool
    {
        $current = $this->siteProduct($siteProductId);
        $payload = $this->catalogProductPayload($data);
        $this->assertSiteProductUnique($payload, $siteProductId);
        Db::name('erp_site_catalog_product')->where([
            ['site_id', '=', (int)$this->site_id],
            ['site_product_id', '=', $siteProductId],
        ])->update(array_merge($payload, ['update_at' => time()]));
        $this->emitCatalogChanged('manual_update', [
            'site_product_id' => $siteProductId,
            'master_product_id' => (int)$current['master_product_id'],
        ]);
        return true;
    }

    /** 已进入采购或库存业务的型号保留审计关系，只允许停用，不能物理删除。 */
    public function delete(int $siteProductId): bool
    {
        $current = $this->siteProduct($siteProductId);
        $usedByAsset = (int)Db::name('erp_asset')->where([
            ['site_id', '=', (int)$this->site_id], ['catalog_product_id', '=', $siteProductId],
        ])->count();
        $usedByPurchase = (int)Db::name('erp_purchase_item')->where([
            ['site_id', '=', (int)$this->site_id], ['catalog_product_id', '=', $siteProductId],
        ])->count();
        if ($usedByAsset > 0 || $usedByPurchase > 0) {
            throw new AdminException('该型号已被采购或库存使用，不能删除；请编辑后将其停用');
        }
        Db::transaction(function () use ($siteProductId, $current) {
            Db::name('erp_site_catalog_product')->where([
                ['site_id', '=', (int)$this->site_id], ['site_product_id', '=', $siteProductId],
            ])->delete();
            $masterId = (int)$current['master_product_id'];
            $hasBindings = (int)Db::name('erp_site_catalog_product')->where('master_product_id', '=', $masterId)->count();
            if ($hasBindings === 0) {
                Db::name('erp_catalog_product_master')->where([
                    ['master_product_id', '=', $masterId], ['source_key', '=', 'erp_manual'],
                ])->delete();
            }
        });
        $this->emitCatalogChanged('manual_delete', ['site_product_id' => $siteProductId]);
        return true;
    }

    /**
     * 虚拟目录节点没有独立表：调整父节点时整体平移子型号排序，既改变组顺序，
     * 又保留组内原有的相对顺序。
     */
    public function sortNode(array $data): int
    {
        $nodeType = trim((string)($data['node_type'] ?? ''));
        if (!in_array($nodeType, ['category', 'brand', 'series', 'product'], true)) {
            throw new AdminException('不支持调整该目录节点');
        }
        $query = Db::name('erp_site_catalog_product')->where('site_id', '=', (int)$this->site_id);
        $categoryPath = $this->normalizeCatalogPath(trim((string)($data['category_path'] ?? '')));
        if ($nodeType === 'product') {
            $query->where('site_product_id', '=', max(0, (int)($data['site_product_id'] ?? 0)));
        } elseif ($nodeType === 'category') {
            $segments = $this->catalogPathSegments($categoryPath);
            if (count($segments) === 1) {
                $query->where(function ($subQuery) use ($categoryPath) {
                    $subQuery->where('category_path', '=', $categoryPath)
                        ->whereOr('category_path', 'like', $categoryPath . '/%');
                });
            } else {
                $query->where('category_path', '=', $categoryPath);
            }
        } else {
            $query->where('category_path', '=', $categoryPath)
                ->where('brand_name', '=', trim((string)($data['brand_name'] ?? '')));
            if ($nodeType === 'series') $query->where('series_name', '=', trim((string)($data['series_name'] ?? '')));
        }
        $rows = (clone $query)->field('site_product_id,sort')->select()->toArray();
        if (!$rows) throw new AdminException('目录节点不存在或已经没有商品型号');
        $targetSort = max(-999999, min(999999, (int)($data['sort'] ?? 0)));
        $currentMax = max(array_map(static fn(array $row): int => (int)$row['sort'], $rows));
        $delta = $targetSort - $currentMax;
        if ($delta !== 0) {
            Db::transaction(function () use ($rows, $delta) {
                foreach ($rows as $row) {
                    $sort = max(-999999, min(999999, (int)$row['sort'] + $delta));
                    Db::name('erp_site_catalog_product')->where([
                        ['site_id', '=', (int)$this->site_id],
                        ['site_product_id', '=', (int)$row['site_product_id']],
                    ])->update(['sort' => $sort, 'update_at' => time()]);
                }
            });
            $this->emitCatalogChanged('sort_node', ['node_type' => $nodeType, 'affected_count' => count($rows)]);
        }
        return count($rows);
    }

    /** 产品目录懒加载层级：一级品类 -> 可选一级子品类 -> 品牌 -> 系列 -> 型号。 */
    public function hierarchy(array $where = []): array
    {
        $nodeType = trim((string)($where['node_type'] ?? 'root')) ?: 'root';
        $categoryPath = trim((string)($where['category_path'] ?? ''), " /\t\n\r\0\x0B");
        $brandName = trim((string)($where['brand_name'] ?? ''));
        $seriesName = trim((string)($where['series_name'] ?? ''));
        $keyword = trim((string)($where['keyword'] ?? ''));
        $siteProductId = max(0, (int)($where['site_product_id'] ?? 0));
        $categoryOnly = (int)($where['category_only'] ?? 0) === 1;
        $limit = min(500, max(20, (int)($where['limit'] ?? 200)));
        $query = Db::name('erp_site_catalog_product')->alias('sp')
            ->leftJoin('erp_catalog_product_master mp', 'mp.master_product_id = sp.master_product_id')
            ->where('sp.site_id', '=', $this->site_id);
        if ((int)($where['include_disabled'] ?? 0) !== 1) $query->where('sp.is_enabled', '=', 1);
        if (in_array($nodeType, ['brand', 'series'], true)) $query->where('sp.category_path', '=', $categoryPath);
        if ($brandName !== '' || in_array($nodeType, ['brand', 'series'], true)) $query->where('sp.brand_name', '=', $brandName);
        if ($seriesName !== '' || $nodeType === 'series') $query->where('sp.series_name', '=', $seriesName);

        $nodes = [];
        if ($categoryOnly && $keyword !== '') {
            $rows = $query->whereLike('sp.category_path', '%' . $keyword . '%')
                ->field('sp.category_path,count(*) as product_count,min(sp.sort) as sort,min(sp.site_product_id) as first_id')
                ->group('sp.category_path')->order('sort asc,first_id asc')->limit($limit)->select()->toArray();
            foreach ($rows as $row) {
                $path = trim((string)$row['category_path'], " /\t\n\r\0\x0B");
                if ($path === '') continue;
                $parts = $this->catalogPathSegments($path);
                $nodes[] = [
                    'node_key' => 'category:' . md5($path), 'node_type' => 'category',
                    'label' => (string)end($parts), 'category_path' => $path,
                    'catalog_level' => max(1, count($parts)), 'product_count' => (int)$row['product_count'],
                    'sort' => (int)$row['sort'], 'is_leaf' => 1, 'has_children' => 0,
                    'path_text' => str_replace('/', ' / ', $path),
                ];
            }
        } elseif ($siteProductId > 0 || $keyword !== '') {
            if ($siteProductId > 0) $query->where('sp.site_product_id', '=', $siteProductId);
            else $query->whereLike('sp.product_name|sp.brand_name|sp.series_name|sp.category_path|mp.source_product_id', '%' . $keyword . '%');
            $rows = $query
                ->field('sp.site_product_id,sp.category_path,sp.product_name,sp.brand_name,sp.series_name,sp.is_enabled,sp.sort,mp.source_product_id')
                ->order('sp.sort asc,sp.site_product_id asc')->limit($limit)->select()->toArray();
            foreach ($rows as $row) $nodes[] = $this->productHierarchyNode($row, true);
        } elseif (in_array($nodeType, ['root', 'category'], true)) {
            // Excel 的“品类”允许写成“智能数码/智能手表”。这里按路径逐级投影，
            // 不再依赖旧分类表，也不会把品牌、系列伪装成分类记录。
            $pathRows = (clone $query)->field('sp.category_path,count(*) as product_count,min(sp.sort) as sort,min(sp.site_product_id) as first_id')
                ->group('sp.category_path')->order('sort asc,first_id asc')->select()->toArray();
            $parent = $categoryPath === '' ? [] : $this->catalogPathSegments($categoryPath);
            $children = [];
            foreach ($pathRows as $row) {
                $path = trim((string)$row['category_path'], " /\t\n\r\0\x0B");
                $segments = $this->catalogPathSegments($path);
                if ($nodeType === 'root' && !$segments) {
                    $children[''] = [
                        'count' => (int)($children['']['count'] ?? 0) + (int)$row['product_count'],
                        'sort' => min((int)($children['']['sort'] ?? PHP_INT_MAX), (int)$row['sort']),
                        'first_id' => min((int)($children['']['first_id'] ?? PHP_INT_MAX), (int)$row['first_id']),
                    ];
                    continue;
                }
                if ($nodeType === 'category') {
                    if ($categoryPath === '') continue;
                    if (count($segments) <= count($parent) || array_slice($segments, 0, count($parent)) !== $parent) continue;
                }
                $depth = $nodeType === 'root' ? 0 : count($parent);
                if (!isset($segments[$depth])) continue;
                $childPath = implode('/', array_slice($segments, 0, $depth + 1));
                $children[$childPath] = [
                    'count' => (int)($children[$childPath]['count'] ?? 0) + (int)$row['product_count'],
                    'sort' => min((int)($children[$childPath]['sort'] ?? PHP_INT_MAX), (int)$row['sort']),
                    'first_id' => min((int)($children[$childPath]['first_id'] ?? PHP_INT_MAX), (int)$row['first_id']),
                    'has_deeper' => (bool)($children[$childPath]['has_deeper'] ?? false)
                        || count($segments) > $depth + 1,
                ];
            }
            uksort($children, static function (string $left, string $right) use ($children): int {
                $sortCompare = (int)$children[$left]['sort'] <=> (int)$children[$right]['sort'];
                if ($sortCompare !== 0) return $sortCompare;
                $idCompare = (int)$children[$left]['first_id'] <=> (int)$children[$right]['first_id'];
                return $idCompare !== 0 ? $idCompare : strnatcasecmp($left, $right);
            });
            foreach (array_slice($children, 0, $limit, true) as $path => $aggregate) {
                $parts = $this->catalogPathSegments($path);
                $nodes[] = [
                    'node_key' => 'category:' . md5($path), 'node_type' => 'category',
                    'label' => $path === '' ? '未分类' : (string)end($parts),
                    'category_path' => $path, 'catalog_level' => max(1, count($parts)),
                    'product_count' => (int)$aggregate['count'], 'sort' => (int)$aggregate['sort'],
                    'is_leaf' => $categoryOnly && empty($aggregate['has_deeper']) ? 1 : 0,
                    'has_children' => $categoryOnly && empty($aggregate['has_deeper']) ? 0 : 1,
                ];
            }
            // 当前品类没有更深的子品类，下一层直接进入品牌。
            if (!$categoryOnly && $nodeType === 'category' && !$nodes) {
                $brandQuery = (clone $query)->where('sp.category_path', '=', $categoryPath);
                $rows = $brandQuery->field('sp.brand_name,count(*) as product_count,min(sp.sort) as sort,min(sp.site_product_id) as first_id')->group('sp.brand_name')->order('sort asc,first_id asc')->limit($limit)->select()->toArray();
                foreach ($rows as $row) {
                    $name = (string)$row['brand_name'];
                    $nodes[] = [
                        'node_key' => 'brand:' . md5($categoryPath . '|' . $name), 'node_type' => 'brand',
                        'label' => $name !== '' ? $name : '未设置品牌', 'category_path' => $categoryPath,
                        'brand_name' => $name, 'product_count' => (int)$row['product_count'], 'sort' => (int)$row['sort'],
                        'is_leaf' => 0, 'has_children' => 1,
                    ];
                }
            }
        } elseif ($nodeType === 'brand') {
            $rows = $query->field('sp.series_name,count(*) as product_count,min(sp.sort) as sort,min(sp.site_product_id) as first_id')->group('sp.series_name')->order('sort asc,first_id asc')->limit($limit)->select()->toArray();
            foreach ($rows as $row) {
                $name = (string)$row['series_name'];
                $nodes[] = [
                    'node_key' => 'series:' . md5($categoryPath . '|' . $brandName . '|' . $name), 'node_type' => 'series',
                    'label' => $name !== '' ? $name : '未分系列', 'category_path' => $categoryPath,
                    'brand_name' => $brandName, 'series_name' => $name, 'product_count' => (int)$row['product_count'], 'sort' => (int)$row['sort'],
                    'is_leaf' => 0, 'has_children' => 1,
                ];
            }
        } else {
            $rows = $query->field('sp.site_product_id,sp.category_path,sp.product_name,sp.brand_name,sp.series_name,sp.is_enabled,sp.sort,mp.source_product_id')
                ->order('sp.sort asc,sp.site_product_id asc')->limit($limit)->select()->toArray();
            foreach ($rows as $row) $nodes[] = $this->productHierarchyNode($row);
        }

        $result = ['list' => $nodes];
        // 级联组件每展开一级都会调用本接口。筛选统计只在目录管理页显式请求，
        // 避免每次懒加载额外执行品牌、系列两次全表分组。
        if ((int)($where['include_filters'] ?? 0) === 1) {
            $base = Db::name('erp_site_catalog_product')->where('site_id', '=', $this->site_id);
            $result['filters'] = [
                'brands' => array_values((clone $base)->where('brand_name', '<>', '')->group('brand_name')->order('brand_name asc')->column('brand_name')),
                'series' => array_values((clone $base)->where('series_name', '<>', '')->group('series_name')->order('series_name asc')->column('series_name')),
            ];
        }
        return $result;
    }

    private function catalogPathSegments(string $path): array
    {
        // 必须使用 Unicode 模式。未加 u 时，中文 UTF-8 字节可能被误判为 ASCII 分隔符，
        // 例如“智能眼镜VR”中的“眼”曾被错误拆成“智能� / 镜VR”。
        $normalized = preg_replace('/[\x{00A0}\x{3000}]+/u', ' ', trim($path)) ?? trim($path);
        $segments = preg_split('~[/\\\\／＞>,，|｜]+~u', $normalized) ?: [];

        return array_values(array_filter(array_map(
            static fn(string $value): string => trim(preg_replace('/\s+/u', ' ', $value) ?? $value),
            $segments
        ), static fn(string $value): bool => $value !== ''));
    }

    private function productHierarchyNode(array $row, bool $withPath = false): array
    {
        return [
            'node_key' => 'product:' . (int)$row['site_product_id'], 'node_type' => 'product',
            'label' => (string)$row['product_name'], 'site_product_id' => (int)$row['site_product_id'],
            'category_path' => (string)$row['category_path'],
            'brand_name' => (string)$row['brand_name'], 'series_name' => (string)$row['series_name'],
            'source_product_id' => (string)($row['source_product_id'] ?? ''), 'is_enabled' => (int)$row['is_enabled'],
            'sort' => (int)($row['sort'] ?? 0),
            'path_text' => $withPath ? implode(' / ', array_filter([(string)$row['category_path'], (string)$row['brand_name'], (string)$row['series_name']])) : '',
            'is_leaf' => 1, 'has_children' => 0,
        ];
    }

    public function exportRows(): array
    {
        return Db::name('erp_site_catalog_product')->alias('sp')
            ->leftJoin('erp_catalog_product_master mp', 'mp.master_product_id = sp.master_product_id')
            ->where('sp.site_id', '=', $this->site_id)
            ->field('sp.category_path,mp.category_source_id,sp.brand_name,mp.brand_source_id,sp.series_name,sp.product_name,mp.source_product_id,mp.source_key,sp.is_enabled,sp.sort')
            ->order('sp.category_path asc,sp.brand_name asc,sp.series_name asc,sp.product_name asc')
            ->select()->toArray();
    }

    public function importRows(array $rows, string $defaultSourceKey = 'excel_product_catalog'): array
    {
        return $this->importRowsForSite($rows, $defaultSourceKey, (int)$this->site_id);
    }

    /**
     * 后台任务入口。队列进程没有管理端请求上下文，站点必须显式传入。
     */
    public function importRowsForSite(array $rows, string $defaultSourceKey, int $siteId, bool $emitEvent = true): array
    {
        if (!$rows) throw new AdminException('没有可导入的商品目录数据');
        if ($siteId <= 0) throw new AdminException('商品目录导入缺少有效站点');

        $stats = [
            'rows' => count($rows), 'master_created' => 0, 'site_created' => 0, 'site_updated' => 0,
            'skipped' => 0, 'duplicate_skipped' => 0, 'invalid_skipped' => 0,
            'master_conflicts' => 0, 'conflict_samples' => [],
        ];
        Db::transaction(function () use ($rows, $defaultSourceKey, $siteId, &$stats) {
            foreach ($rows as $raw) {
                $row = $this->normalizeRow((array)$raw, $defaultSourceKey);
                if ($row['product_name'] === '' || $row['category_path'] === '') {
                    $stats['skipped']++;
                    $stats['invalid_skipped']++;
                    continue;
                }
                $categoryPath = $this->normalizeCatalogPath($row['category_path']);
                $row['category_path'] = $categoryPath;

                $hashData = [
                    $row['category_path'], $row['category_source_id'], $row['brand_name'],
                    $row['brand_source_id'], $row['series_name'], $row['product_name'],
                ];
                $dataHash = hash('sha256', json_encode($hashData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $master = Db::name('erp_catalog_product_master')->where([
                    ['source_key', '=', $row['source_key']],
                    ['source_product_id', '=', $row['source_product_id']],
                ])->find();
                if (!$master) {
                    $masterId = (int)Db::name('erp_catalog_product_master')->insertGetId([
                        'created_site_id' => $siteId,
                        'source_key' => $row['source_key'], 'source_product_id' => $row['source_product_id'],
                        'category_source_id' => $row['category_source_id'], 'category_path' => $row['category_path'],
                        'brand_source_id' => $row['brand_source_id'], 'brand_name' => $row['brand_name'],
                        'series_name' => $row['series_name'], 'product_name' => $row['product_name'],
                        'data_hash' => $dataHash, 'create_at' => time(), 'update_at' => time(),
                    ]);
                    $stats['master_created']++;
                } else {
                    $masterId = (int)$master['master_product_id'];
                    if ((string)($master['data_hash'] ?? '') !== $dataHash) {
                        $stats['master_conflicts']++;
                        if (count($stats['conflict_samples']) < 10) {
                            $stats['conflict_samples'][] = [
                                'source_product_id' => $row['source_product_id'],
                                'master_name' => (string)($master['product_name'] ?? ''),
                                'uploaded_name' => $row['product_name'],
                            ];
                        }
                    }
                }

                $site = Db::name('erp_site_catalog_product')->where([
                    ['site_id', '=', $siteId], ['master_product_id', '=', $masterId],
                ])->find();
                $sitePayload = [
                    'category_path' => $categoryPath,
                    'product_name' => $row['product_name'], 'brand_name' => $row['brand_name'],
                    'series_name' => $row['series_name'], 'is_enabled' => $row['is_enabled'],
                    'sort' => $row['sort'], 'update_at' => time(),
                ];
                if ($site) {
                    $same = (string)$site['category_path'] === (string)$sitePayload['category_path']
                        && (string)$site['product_name'] === (string)$sitePayload['product_name']
                        && (string)$site['brand_name'] === (string)$sitePayload['brand_name']
                        && (string)$site['series_name'] === (string)$sitePayload['series_name']
                        && (int)$site['is_enabled'] === (int)$sitePayload['is_enabled']
                        && (int)$site['sort'] === (int)$sitePayload['sort'];
                    if ($same) {
                        $stats['skipped']++;
                        $stats['duplicate_skipped']++;
                    } else {
                        Db::name('erp_site_catalog_product')->where('site_product_id', '=', (int)$site['site_product_id'])->update($sitePayload);
                        $stats['site_updated']++;
                    }
                } else {
                    Db::name('erp_site_catalog_product')->insert(array_merge($sitePayload, [
                        'site_id' => $siteId, 'master_product_id' => $masterId, 'create_at' => time(),
                    ]));
                    $stats['site_created']++;
                }
            }
        });
        if ($emitEvent) {
            event('HsxErpCatalogChanged', [
                'site_id' => $siteId,
                'source_key' => $defaultSourceKey,
                'stats' => $stats,
            ]);
        }
        return $stats;
    }

    private function normalizeRow(array $row, string $defaultSourceKey): array
    {
        $categoryPath = trim((string)($row['category_path'] ?? $row['品类'] ?? $row['分类路径'] ?? ''));
        $productName = trim((string)($row['product_name'] ?? $row['型号'] ?? $row['产品名称'] ?? ''));
        $sourceKey = strtolower(trim((string)($row['source_key'] ?? $row['来源'] ?? $defaultSourceKey)));
        $sourceKey = preg_replace('/[^a-z0-9_.-]+/', '_', $sourceKey) ?: 'excel_product_catalog';
        $sourceProductId = trim((string)($row['source_product_id'] ?? $row['产品ID'] ?? $row['product_id'] ?? ''));
        if ($sourceProductId === '') {
            $sourceProductId = 'hash:' . substr(hash('sha256', implode('|', [
                $categoryPath, (string)($row['brand_name'] ?? $row['品牌'] ?? ''),
                (string)($row['series_name'] ?? $row['系列'] ?? ''), $productName,
            ])), 0, 48);
        }
        return [
            'source_key' => substr($sourceKey, 0, 40),
            'source_product_id' => substr($sourceProductId, 0, 80),
            'category_path' => $categoryPath,
            'category_source_id' => substr(trim((string)($row['category_source_id'] ?? $row['品类ID'] ?? '')), 0, 80),
            'brand_name' => trim((string)($row['brand_name'] ?? $row['品牌'] ?? '')),
            'brand_source_id' => substr(trim((string)($row['brand_source_id'] ?? $row['品牌ID'] ?? '')), 0, 80),
            'series_name' => trim((string)($row['series_name'] ?? $row['系列'] ?? '')),
            'product_name' => $productName,
            'is_enabled' => (int)($row['is_enabled'] ?? $row['是否启用'] ?? 1) === 0 ? 0 : 1,
            'sort' => (int)($row['sort'] ?? $row['排序'] ?? 0),
        ];
    }

    private function normalizeCatalogPath(string $path): string
    {
        $segments = $this->catalogPathSegments($path);
        if (!$segments) throw new AdminException('商品目录中存在空品类路径');
        if (count($segments) > 2) {
            throw new AdminException(sprintf(
                '商品品类只支持“一级品类/可选子品类”两层：%s（实际解析：%s）',
                $path,
                implode(' / ', $segments)
            ));
        }
        return implode('/', $segments);
    }

    private function siteProduct(int $siteProductId): array
    {
        if ($siteProductId <= 0) throw new AdminException('商品型号不存在');
        $row = Db::name('erp_site_catalog_product')->where([
            ['site_id', '=', (int)$this->site_id], ['site_product_id', '=', $siteProductId],
        ])->find();
        if (!$row) throw new AdminException('商品型号不存在或不属于当前站点');
        return $row;
    }

    private function catalogProductPayload(array $data): array
    {
        $categoryPath = $this->normalizeCatalogPath(trim((string)($data['category_path'] ?? '')));
        $productName = trim((string)($data['product_name'] ?? ''));
        if ($productName === '') throw new AdminException('请输入商品型号');
        return [
            'category_path' => mb_substr($categoryPath, 0, 255),
            'brand_name' => mb_substr(trim((string)($data['brand_name'] ?? '')), 0, 100),
            'series_name' => mb_substr(trim((string)($data['series_name'] ?? '')), 0, 100),
            'product_name' => mb_substr($productName, 0, 150),
            'is_enabled' => (int)($data['is_enabled'] ?? 1) === 0 ? 0 : 1,
            'sort' => max(-999999, min(999999, (int)($data['sort'] ?? 0))),
        ];
    }

    private function assertSiteProductUnique(array $payload, int $excludeId = 0): void
    {
        $query = Db::name('erp_site_catalog_product')->where([
            ['site_id', '=', (int)$this->site_id],
            ['category_path', '=', $payload['category_path']],
            ['brand_name', '=', $payload['brand_name']],
            ['series_name', '=', $payload['series_name']],
            ['product_name', '=', $payload['product_name']],
        ]);
        if ($excludeId > 0) $query->where('site_product_id', '<>', $excludeId);
        if ((int)$query->count() > 0) throw new AdminException('当前品类、品牌和系列下已存在同名型号');
    }

    private function emitCatalogChanged(string $action, array $extra = []): void
    {
        event('HsxErpCatalogChanged', array_merge([
            'site_id' => (int)$this->site_id,
            'source_key' => 'erp_manual',
            'action' => $action,
        ], $extra));
    }
}
