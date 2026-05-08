<?php
declare(strict_types=1);

namespace addon\recycle_quote_spider\app\service\admin;

use addon\recycle_quote_spider\app\model\QuoteCategory;
use addon\recycle_quote_spider\app\service\core\QuoteApiCacheService;
use core\base\BaseAdminService;
use core\exception\CommonException;

class QuoteCategoryService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new QuoteCategory();
    }

    public function getPage(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $search = $this->model
            ->withSearch(['site_id', 'source_id', 'parent_id', 'is_show', 'keyword'], $where)
            ->order('sort asc,id asc');
        return $this->pageQuery($search);
    }

    public function tree(array $where = []): array
    {
        $where['site_id'] = $this->site_id;
        $list = $this->model
            ->withSearch(['site_id', 'source_id', 'is_show', 'keyword'], $where)
            ->order('sort asc,id asc')
            ->select()
            ->toArray();
        return $this->buildTree($list);
    }

    public function add(array $data): int
    {
        $sourceId = (int)($data['source_id'] ?? 0);
        if ($sourceId <= 0) {
            throw new CommonException('请选择报价源');
        }
        $name = trim((string)($data['name'] ?? ''));
        if ($name === '') {
            throw new CommonException('请输入分类名称');
        }

        $parentId = (int)($data['parent_id'] ?? 0);
        $level = 1;
        if ($parentId > 0) {
            $parent = $this->model->where('site_id', $this->site_id)->where('id', $parentId)->findOrEmpty()->toArray();
            if (empty($parent)) {
                throw new CommonException('父级分类不存在');
            }
            $level = (int)$parent['level'] + 1;
        }

        $record = $this->model->create([
            'site_id' => $this->site_id,
            'source_id' => $sourceId,
            'source_category_id' => 'manual_' . uniqid('', true),
            'parent_source_id' => '',
            'parent_id' => $parentId,
            'level' => $level,
            'name' => $name,
            'sort' => (int)($data['sort'] ?? 0),
            'source_is_show' => 1,
            'source_is_hot' => 0,
            'is_show' => (int)($data['is_show'] ?? 1),
            'is_hot' => (int)($data['is_hot'] ?? 0),
            'raw_data' => ['manual' => true],
            'source_hash' => md5($name . microtime(true)),
        ]);
        $this->refreshApiCache();
        return (int)$record->id;
    }

    public function edit(int $id, array $data): bool
    {
        $info = $this->model->where('site_id', $this->site_id)->where('id', $id)->findOrEmpty()->toArray();
        if (empty($info)) {
            throw new CommonException('分类不存在');
        }
        $save = [];
        foreach (['is_show', 'is_hot', 'sort'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== '') {
                $save[$field] = (int)$data[$field];
            }
        }
        if (isset($data['name']) && $data['name'] !== '') {
            $save['name'] = (string)$data['name'];
        }
        if (!empty($save)) {
            $this->model->where('id', $id)->update($save);
        }
        $this->refreshApiCache();
        return true;
    }

    private function refreshApiCache(): void
    {
        (new QuoteApiCacheService())->refresh($this->site_id);
    }

    private function buildTree(array $list, int $parentId = 0): array
    {
        $tree = [];
        foreach ($list as $item) {
            if ((int)$item['parent_id'] !== $parentId) {
                continue;
            }
            $item['children'] = $this->buildTree($list, (int)$item['id']);
            $tree[] = $item;
        }
        return $tree;
    }
}
