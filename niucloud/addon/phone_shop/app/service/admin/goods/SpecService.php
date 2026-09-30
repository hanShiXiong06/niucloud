<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 上架属性参考数据服务
// |   规格分组(绑分类)+ 规格子项 + 成色等级(扁平)，供建品时按分类选规格/成色。
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\admin\goods;

use addon\phone_shop\app\model\goods\GoodsGrade;
use addon\phone_shop\app\model\goods\GoodsSpecGroup;
use addon\phone_shop\app\model\goods\GoodsSpecItem;
use addon\phone_shop\app\service\core\agent\RefDataSyncService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

class SpecService extends BaseAdminService
{
    public static function forSite(int $siteId): self
    {
        $service = new self();
        $service->site_id = $siteId;
        return $service;
    }

    /** 当前数据库是否已升级为“一个规格组绑定多个分类”。 */
    private ?bool $hasCategoryIdsColumn = null;

    // ============ 规格分组 ============

    /** 分组列表（含子项；管理页用） */
    public function groupList(array $where = []): array
    {
        $query = (new GoodsSpecGroup())->where([['site_id', '=', $this->site_id]]);
        if (!empty($where['category_id'])) {
            $categoryId = (int)$where['category_id'];
            $query->where(function ($q) use ($categoryId) {
                $q->where('category_id', '=', $categoryId);
                if ($this->hasCategoryIdsColumn()) {
                    $q->whereOr(function ($qq) use ($categoryId) {
                        $qq->whereFindInSet('category_ids', $categoryId);
                    });
                }
            });
        }
        return $this->normalizeGroupCategoryIds(
            $query->with(['items'])->order('sort asc, group_id desc')->select()->toArray()
        );
    }

    public function groupAdd(array $data): int
    {
        $categoryIds = $this->normalizeCategoryIds($data);
        $label = trim((string)($data['label'] ?? '内存'));
        if (empty($categoryIds)) {
            throw new AdminException('请选择绑定的商品分类');
        }
        if ($label === '') {
            throw new AdminException('请填写规格标签名');
        }
        $now = time();
        $rowData = [
            'site_id'      => $this->site_id,
            'category_id'  => $categoryIds[0],
            'label'        => $label,
            'sort'         => (int)($data['sort'] ?? 0),
            'create_time'  => $now,
            'update_time'  => $now,
        ];
        if ($this->hasCategoryIdsColumn()) {
            $rowData['category_ids'] = implode(',', $categoryIds);
        }
        $row = (new GoodsSpecGroup())->create($rowData);
        RefDataSyncService::pushIfMaster('spec_group', (int)$this->site_id, (int)$row->group_id);
        return (int)$row->group_id;
    }

    /** 归一化绑定分类ID（兼容 单值 category_id / 数组 / 逗号串 category_ids） */
    private function normalizeCategoryIds(array $data): array
    {
        $ids = $data['category_ids'] ?? '';
        if (is_string($ids)) {
            $ids = $ids === '' ? [] : explode(',', $ids);
        }
        if (!is_array($ids)) {
            $ids = [];
        }
        if (empty($ids) && !empty($data['category_id'])) {
            $ids = [$data['category_id']];
        }
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    public function groupEdit(int $groupId, array $data): bool
    {
        $this->findGroup($groupId);
        $categoryIds = $this->normalizeCategoryIds($data);
        if (empty($categoryIds)) {
            throw new AdminException('请选择绑定的商品分类');
        }
        $updateData = [
            'category_id'  => $categoryIds[0],
            'label'        => trim((string)($data['label'] ?? '内存')),
            'sort'         => (int)($data['sort'] ?? 0),
            'update_time'  => time(),
        ];
        if ($this->hasCategoryIdsColumn()) {
            $updateData['category_ids'] = implode(',', $categoryIds);
        }
        (new GoodsSpecGroup())->where([['site_id', '=', $this->site_id], ['group_id', '=', $groupId]])->update($updateData);
        RefDataSyncService::pushIfMaster('spec_group', (int)$this->site_id, $groupId);
        return true;
    }

    public function groupDel(int $groupId): bool
    {
        $this->findGroup($groupId);
        Db::transaction(function () use ($groupId) {
            (new GoodsSpecItem())->where([['site_id', '=', $this->site_id], ['group_id', '=', $groupId]])->delete();
            (new GoodsSpecGroup())->where([['site_id', '=', $this->site_id], ['group_id', '=', $groupId]])->delete();
        });
        return true;
    }

    // ============ 规格子项 ============

    public function itemAdd(array $data): int
    {
        $groupId = (int)($data['group_id'] ?? 0);
        $value = trim((string)($data['item_value'] ?? ''));
        $this->findGroup($groupId);
        if ($value === '') {
            throw new AdminException('请填写规格值');
        }
        $now = time();
        $row = (new GoodsSpecItem())->create([
            'site_id'     => $this->site_id,
            'group_id'    => $groupId,
            'item_value'  => $value,
            'sort'        => (int)($data['sort'] ?? 0),
            'create_time' => $now,
            'update_time' => $now,
        ]);
        RefDataSyncService::pushIfMaster('spec_item', (int)$this->site_id, (int)$row->item_id);
        return (int)$row->item_id;
    }

    public function itemEdit(int $itemId, array $data): bool
    {
        (new GoodsSpecItem())->where([['site_id', '=', $this->site_id], ['item_id', '=', $itemId]])->update([
            'item_value'  => trim((string)($data['item_value'] ?? '')),
            'sort'        => (int)($data['sort'] ?? 0),
            'update_time' => time(),
        ]);
        RefDataSyncService::pushIfMaster('spec_item', (int)$this->site_id, $itemId);
        return true;
    }

    public function itemDel(int $itemId): bool
    {
        (new GoodsSpecItem())->where([['site_id', '=', $this->site_id], ['item_id', '=', $itemId]])->delete();
        return true;
    }

    // ============ 成色等级（扁平） ============

    public function gradeList(): array
    {
        return (new GoodsGrade())->where([['site_id', '=', $this->site_id]])->order('sort asc, grade_id asc')->select()->toArray();
    }

    public function gradeAdd(array $data): int
    {
        $name = trim((string)($data['grade_name'] ?? ''));
        if ($name === '') {
            throw new AdminException('请填写成色名称');
        }
        $now = time();
        $row = (new GoodsGrade())->create([
            'site_id'     => $this->site_id,
            'grade_name'  => $name,
            'grade_desc'  => mb_substr(trim((string)($data['grade_desc'] ?? '')), 0, 500),
            'grade_image' => trim((string)($data['grade_image'] ?? '')),
            'sort'        => (int)($data['sort'] ?? 0),
            'status'      => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'create_time' => $now,
            'update_time' => $now,
        ]);
        RefDataSyncService::pushIfMaster('grade', (int)$this->site_id, (int)$row->grade_id);
        return (int)$row->grade_id;
    }

    public function gradeEdit(int $gradeId, array $data): bool
    {
        $name = trim((string)($data['grade_name'] ?? ''));
        if ($name === '') {
            throw new AdminException('请填写成色名称');
        }
        (new GoodsGrade())->where([['site_id', '=', $this->site_id], ['grade_id', '=', $gradeId]])->update([
            'grade_name'  => $name,
            'grade_desc'  => mb_substr(trim((string)($data['grade_desc'] ?? '')), 0, 500),
            'grade_image' => trim((string)($data['grade_image'] ?? '')),
            'sort'        => (int)($data['sort'] ?? 0),
            'status'      => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'update_time' => time(),
        ]);
        RefDataSyncService::pushIfMaster('grade', (int)$this->site_id, $gradeId);
        return true;
    }

    public function gradeDel(int $gradeId): bool
    {
        (new GoodsGrade())->where([['site_id', '=', $this->site_id], ['grade_id', '=', $gradeId]])->delete();
        return true;
    }

    // ============ 供建品表单 ============

    /**
     * 建品时按"分类"取可选规格 + 全部成色。
     * 分类无直接规格组时，向上回退到父级分类的规格组（三级分类）。
     */
    public function optionsForCategory(int $categoryId, array $categoryPath = []): array
    {
        $catIds = array_values(array_unique(array_filter(array_map('intval', array_merge([$categoryId], $categoryPath)))));
        $groups = [];
        if (!empty($catIds)) {
            $groups = (new GoodsSpecGroup())->where([['site_id', '=', $this->site_id]])
                ->where(function ($q) use ($catIds) {
                    // 老库只有 category_id；升级后的库同时支持一个规格组绑定多个分类。
                    $q->whereIn('category_id', $catIds);
                    if ($this->hasCategoryIdsColumn()) {
                        foreach ($catIds as $cid) {
                            $q->whereOr(function ($qq) use ($cid) {
                                $qq->whereFindInSet('category_ids', $cid);
                            });
                        }
                    }
                })
                ->with(['items'])->order('sort asc')->select()->toArray();
            $groups = $this->normalizeGroupCategoryIds($groups);
        }
        return [
            'spec_groups' => $groups,
            'grades'      => (new GoodsGrade())->where([['site_id', '=', $this->site_id], ['status', '=', 1]])
                ->order('sort asc, grade_id asc')->select()->toArray(),
        ];
    }

    private function findGroup(int $groupId): array
    {
        $row = (new GoodsSpecGroup())->where([['site_id', '=', $this->site_id], ['group_id', '=', $groupId]])->findOrEmpty()->toArray();
        if (empty($row)) {
            throw new AdminException('规格分组不存在');
        }
        return $row;
    }

    /**
     * 兼容尚未执行结构同步的历史站点。
     * 不在业务请求中执行 ALTER TABLE，避免线上请求锁表；升级后由 SchemaSyncService 补列。
     */
    private function hasCategoryIdsColumn(): bool
    {
        if ($this->hasCategoryIdsColumn !== null) {
            return $this->hasCategoryIdsColumn;
        }
        try {
            $this->hasCategoryIdsColumn = in_array(
                'category_ids',
                (new GoodsSpecGroup())->getTableFields(),
                true
            );
        } catch (\Throwable $e) {
            $this->hasCategoryIdsColumn = false;
        }
        return $this->hasCategoryIdsColumn;
    }

    /** 为旧库补齐接口返回格式，前端无需区分单分类/多分类表结构。 */
    private function normalizeGroupCategoryIds(array $groups): array
    {
        foreach ($groups as &$group) {
            if (trim((string)($group['category_ids'] ?? '')) === '') {
                $categoryId = (int)($group['category_id'] ?? 0);
                $group['category_ids'] = $categoryId > 0 ? (string)$categoryId : '';
            }
        }
        unset($group);
        return $groups;
    }
}
