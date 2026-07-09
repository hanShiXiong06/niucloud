<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpGoodsCategory;
use addon\hsx_erp\app\model\ErpPurchaseItem;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

class ErpGoodsCategoryService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ErpGoodsCategory();
    }

    public function lists(array $where = []): array
    {
        $query = $this->model->where([['site_id', '=', $this->site_id]]);
        $keyword = trim((string)($where['keyword'] ?? $where['category_name'] ?? ''));
        if ($keyword !== '') {
            $query->whereLike('category_name|category_full_name', '%' . $keyword . '%');
        }
        if (($where['level'] ?? '') !== '') {
            $query->where('level', (int)$where['level']);
        }
        return $query->field($this->fields())->order('sort desc, category_id asc')->select()->toArray();
    }

    public function tree(array $where = []): array
    {
        return $this->buildTree($this->lists($where));
    }

    public function info(int $id): array
    {
        return $this->model->field($this->fields())->where([['site_id', '=', $this->site_id], ['category_id', '=', $id]])->findOrEmpty()->toArray();
    }

    public function save(int $id, array $data): int
    {
        $name = trim((string)($data['category_name'] ?? ''));
        if ($name === '') {
            throw new AdminException('请输入分类名称');
        }
        $pid = (int)($data['pid'] ?? 0);
        $parent = null;
        $level = 1;
        $fullName = $name;
        if ($pid > 0) {
            $parent = $this->info($pid);
            if (!$parent) {
                throw new AdminException('上级分类不存在');
            }
            if ((int)$parent['level'] >= 3) {
                throw new AdminException('最多支持三级分类');
            }
            $level = (int)$parent['level'] + 1;
            $fullName = trim((string)($parent['category_full_name'] ?: $parent['category_name'])) . '/' . $name;
        }
        if ($id > 0 && $pid === $id) {
            throw new AdminException('上级分类不能选择自身');
        }
        $exists = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['pid', '=', $pid],
            ['category_name', '=', $name],
        ])->when($id > 0, static fn($query) => $query->where('category_id', '<>', $id))->findOrEmpty()->toArray();
        if ($exists) {
            throw new AdminException('同级分类已存在');
        }

        $now = time();
        $payload = [
            'site_id' => $this->site_id,
            'category_name' => $name,
            'pid' => $pid,
            'level' => $level,
            'category_full_name' => $fullName,
            'is_show' => (int)($data['is_show'] ?? 1) === 1 ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 0),
            'source_plugin' => (string)($data['source_plugin'] ?? 'erp'),
            'source_id' => (string)($data['source_id'] ?? ''),
            'update_at' => $now,
        ];
        if ($id > 0) {
            $row = $this->info($id);
            if (!$row) {
                throw new AdminException('分类不存在');
            }
            $this->model->where([['site_id', '=', $this->site_id], ['category_id', '=', $id]])->update($payload);
            $this->rebuildFullNames();
            return $id;
        }
        $payload['create_at'] = $now;
        $category = $this->model->create($payload);
        return (int)$category->category_id;
    }

    public function delete(int $id): bool
    {
        $row = $this->info($id);
        if (!$row) {
            throw new AdminException('分类不存在');
        }
        $ids = $this->descendantIds($id);
        $ids[] = $id;
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $used = ErpAsset::where([['site_id', '=', $this->site_id]])->whereIn('category_id', $ids)->count()
            + ErpPurchaseItem::where([['site_id', '=', $this->site_id]])->whereIn('category_id', $ids)->count();
        if ($used > 0) {
            throw new AdminException('分类已被采购/库存数据使用，不能删除');
        }
        $this->model->where([['site_id', '=', $this->site_id]])->whereIn('category_id', $ids)->delete();
        return true;
    }

    public function exportRows(): array
    {
        return array_map(static fn($row) => [
            'category_id' => (int)$row['category_id'],
            'category_name' => (string)$row['category_name'],
            'category_full_name' => (string)$row['category_full_name'],
            'pid' => (int)$row['pid'],
            'level' => (int)$row['level'],
            'sort' => (int)$row['sort'],
            'is_show' => (int)$row['is_show'],
        ], $this->lists());
    }

    public function importRows(array $rows): array
    {
        $created = 0;
        $skipped = 0;
        Db::transaction(function () use ($rows, &$created, &$skipped) {
            foreach ($rows as $row) {
                $path = $this->normalizeImportPath($row);
                if (!$path) {
                    $skipped++;
                    continue;
                }
                $pid = 0;
                foreach (array_slice($path, 0, 3) as $index => $name) {
                    $name = trim((string)$name);
                    if ($name === '') {
                        continue;
                    }
                    $existing = $this->model->where([
                        ['site_id', '=', $this->site_id],
                        ['pid', '=', $pid],
                        ['category_name', '=', $name],
                    ])->findOrEmpty()->toArray();
                    if ($existing) {
                        $pid = (int)$existing['category_id'];
                        continue;
                    }
                    $pid = $this->save(0, [
                        'category_name' => $name,
                        'pid' => $pid,
                        'sort' => (int)($row['sort'] ?? 0),
                        'is_show' => (int)($row['is_show'] ?? 1),
                    ]);
                    $created++;
                }
            }
        });
        return ['created' => $created, 'skipped' => $skipped];
    }

    private function fields(): string
    {
        return 'category_id,category_name,pid,level,category_full_name,is_show,sort,source_plugin,source_id,create_at,update_at';
    }

    private function buildTree(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $row['child_list'] = [];
            $map[(int)$row['category_id']] = $row;
        }
        $tree = [];
        foreach ($map as $id => &$row) {
            $pid = (int)($row['pid'] ?? 0);
            if ($pid > 0 && isset($map[$pid])) {
                $map[$pid]['child_list'][] = &$row;
            } else {
                $tree[] = &$row;
            }
        }
        unset($row);
        return $tree;
    }

    private function descendantIds(int $id): array
    {
        $rows = $this->model->where([['site_id', '=', $this->site_id]])->field('category_id,pid')->select()->toArray();
        $children = [];
        foreach ($rows as $row) {
            $children[(int)$row['pid']][] = (int)$row['category_id'];
        }
        $out = [];
        $walk = function (int $pid) use (&$walk, &$out, $children) {
            foreach ($children[$pid] ?? [] as $childId) {
                $out[] = $childId;
                $walk($childId);
            }
        };
        $walk($id);
        return $out;
    }

    private function rebuildFullNames(): void
    {
        $rows = $this->model->where([['site_id', '=', $this->site_id]])->field($this->fields())->order('level asc, category_id asc')->select()->toArray();
        $map = [];
        foreach ($rows as $row) {
            $pid = (int)$row['pid'];
            $full = (string)$row['category_name'];
            $level = 1;
            if ($pid > 0 && isset($map[$pid])) {
                $full = $map[$pid]['full'] . '/' . $full;
                $level = (int)$map[$pid]['level'] + 1;
            }
            $map[(int)$row['category_id']] = ['full' => $full, 'level' => $level];
            $this->model->where([['site_id', '=', $this->site_id], ['category_id', '=', (int)$row['category_id']]])->update([
                'category_full_name' => $full,
                'level' => $level,
                'update_at' => time(),
            ]);
        }
    }

    private function normalizeImportPath(array $row): array
    {
        if (!empty($row['path']) && is_array($row['path'])) {
            return array_values(array_filter(array_map('trim', $row['path'])));
        }
        $fullName = (string)($row['category_full_name'] ?? $row['full_name'] ?? $row['name_path'] ?? '');
        if ($fullName !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/[\\/>,，|]+/', $fullName) ?: [])));
        }
        $name = trim((string)($row['category_name'] ?? $row['name'] ?? ''));
        return $name === '' ? [] : [$name];
    }
}
