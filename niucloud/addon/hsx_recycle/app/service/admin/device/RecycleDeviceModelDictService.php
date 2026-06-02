<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device;

use addon\hsx_recycle\app\model\device\RecycleDeviceModelDict;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 回收设备型号字典服务
 *
 * 表结构参考分类表：pid + level + model_full_name 表达层级。
 * 支持二级：苹果/iPhone15，也支持三级：苹果/iPhone/iPhone15。
 */
class RecycleDeviceModelDictService extends BaseAdminService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeviceModelDict();
    }

    public function getPage(array $where = []): array
    {
        $query = $this->model->where([['site_id', '=', $this->site_id], ['level', '>', 1]]);
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereOr('node_name', 'like', "%{$keyword}%")
                    ->whereOr('model_full_name', 'like', "%{$keyword}%");
            });
        }
        if ($where['status'] !== '' && $where['status'] !== null) {
            $query->where('status', (int)$where['status']);
        }

        $result = $this->pageQuery($query->order('sort asc, id desc'));
        foreach (['data', 'list'] as $key) {
            if (!empty($result[$key]) && is_array($result[$key])) {
                $result[$key] = array_values(array_map([$this, 'formatNode'], $this->filterLeafNodes($result[$key])));
            }
        }
        return $result;
    }

    public function tree(): array
    {
        $rows = $this->model->where([['site_id', '=', $this->site_id]])
            ->field('id,pid,level,node_name,model_full_name,status,sort')
            ->order('level asc, sort asc, id asc')
            ->select()
            ->toArray();

        return $this->buildTree($rows);
    }

    public function options(array $where = []): array
    {
        $query = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
            ['level', '>', 1],
        ]);

        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->whereOr('node_name', 'like', "%{$keyword}%")
                    ->whereOr('model_full_name', 'like', "%{$keyword}%");
            });
        }

        $rows = $query->field('id,pid,level,node_name,model_full_name')
            ->order('sort asc, id desc')
            ->limit(300)
            ->select()
            ->toArray();
        $parentIds = $this->model->where([['site_id', '=', $this->site_id]])
            ->whereIn('pid', array_column($rows, 'id') ?: [0])
            ->column('pid');
        $parentMap = $this->buildParentMap($parentIds);

        $leaves = array_filter($rows, static fn($row) => !isset($parentMap[(int)$row['id']]));
        return array_map([$this, 'formatNode'], array_values($leaves));
    }

    public function add(array $data): int
    {
        $path = $this->normalizePath($data);
        return $this->createPath($path, (int)($data['status'] ?? 1), (int)($data['sort'] ?? 0), false);
    }

    public function edit(int $id, array $data): bool
    {
        $node = $this->getNode($id);
        if ($this->hasChildren($id)) {
            throw new CommonException('存在下级型号，不能直接改成其他路径');
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
        return true;
    }

    public function delete(int $id): bool
    {
        $this->getNode($id);
        if ($this->hasChildren($id)) {
            throw new CommonException('请先删除下级型号');
        }
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->delete();
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
            throw new CommonException('请输入要录入的型号');
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

        return [
            'created_count' => count($paths),
            'duplicates' => [],
        ];
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
        $parts = array_values(array_filter([
            trim((string)($data['brand_name'] ?? '')),
            trim((string)($data['series_name'] ?? '')),
            trim((string)($data['model_name'] ?? '')),
        ], static fn($value) => $value !== ''));

        if (count($parts) < 2) {
            throw new CommonException('请至少填写品牌和型号');
        }
        if (count($parts) > 3) {
            throw new CommonException('型号层级最多支持三级');
        }
        return $parts;
    }

    private function normalizePathFromLine(string $line, int $lineNo): array
    {
        $parts = array_values(array_filter(array_map('trim', explode('/', $line)), static fn($value) => $value !== ''));
        if (count($parts) < 2 || count($parts) > 3) {
            throw new CommonException('第' . $lineNo . '行格式错误，请使用 品牌/型号 或 品牌/系列/型号');
        }
        return $parts;
    }

    private function createPath(array $parts, int $status, int $sort, bool $allowExisting): int
    {
        $pid = 0;
        $fullPath = [];
        $leafId = 0;
        foreach ($parts as $index => $name) {
            $level = $index + 1;
            $fullPath[] = $name;
            $node = $this->findNode($pid, $name);
            if (empty($node)) {
                $node = $this->model->create([
                    'site_id' => $this->site_id,
                    'pid' => $pid,
                    'level' => $level,
                    'node_name' => $name,
                    'model_full_name' => implode('/', $fullPath),
                    'status' => $status,
                    'sort' => $sort,
                    'create_at' => time(),
                    'update_at' => time(),
                ])->toArray();
            } elseif ($level === count($parts) && !$allowExisting) {
                throw new CommonException('该型号已存在：' . implode('/', $parts));
            }

            $pid = (int)$node['id'];
            $leafId = $pid;
        }
        return $leafId;
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
            throw new CommonException('型号不存在');
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
}
