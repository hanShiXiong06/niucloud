<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

class ErpGoodsSpecService extends BaseAdminService
{
    public function meta(): array
    {
        $this->ensureDefaults();
        $groups = Db::name('erp_goods_spec_group')
            ->where('site_id', $this->site_id)
            ->order('sort asc, group_id asc')
            ->select()
            ->toArray();
        $groupIds = array_values(array_filter(array_map(static fn($row) => (int)$row['group_id'], $groups)));
        $items = $groupIds ? Db::name('erp_goods_spec_item')
            ->where('site_id', $this->site_id)
            ->whereIn('group_id', $groupIds)
            ->order('sort asc, item_id asc')
            ->select()
            ->toArray() : [];
        $itemMap = [];
        foreach ($items as $item) {
            $itemMap[(int)$item['group_id']][] = [
                'id' => (int)$item['item_id'],
                'label' => (string)$item['item_value'],
                'value' => (string)$item['item_value'],
                'sort' => (int)($item['sort'] ?? 0),
                'source_id' => (int)$item['item_id'],
            ];
        }

        return [
            'groups' => array_map(static function ($group) use ($itemMap) {
                $id = (int)$group['group_id'];
                return [
                    'id' => $id,
                    'label' => (string)$group['label'],
                    'key' => 'erp_spec_' . $id,
                    'title_part' => (int)$group['title_part'] === 1,
                    'sort' => (int)($group['sort'] ?? 0),
                    'items' => $itemMap[$id] ?? [],
                    'source_id' => $id,
                ];
            }, $groups),
            'grades' => array_map(static fn($row) => [
                'id' => (int)$row['grade_id'],
                'label' => (string)$row['grade_name'],
                'value' => (string)$row['grade_name'],
                'sort' => (int)($row['sort'] ?? 0),
                'source_id' => (int)$row['grade_id'],
            ], Db::name('erp_goods_grade')
                ->where([['site_id', '=', $this->site_id], ['status', '=', 1]])
                ->order('sort asc, grade_id asc')
                ->select()
                ->toArray()),
            'memory_groups' => [],
            'colors' => [],
        ];
    }

    public function saveGroup(int $id, array $data): int
    {
        $label = trim((string)($data['label'] ?? ''));
        if ($label === '') {
            throw new AdminException('请填写规格名称');
        }
        $payload = [
            'site_id' => $this->site_id,
            'label' => $label,
            'title_part' => (int)($data['title_part'] ?? 1) === 1 ? 1 : 0,
            'sort' => (int)($data['sort'] ?? 0),
            'update_at' => time(),
        ];
        $this->assertUnique('erp_goods_spec_group', 'label', $label, 'group_id', $id);
        if ($id > 0) {
            Db::name('erp_goods_spec_group')->where([['site_id', '=', $this->site_id], ['group_id', '=', $id]])->update($payload);
            return $id;
        }
        $payload['create_at'] = time();
        return (int)Db::name('erp_goods_spec_group')->insertGetId($payload);
    }

    public function deleteGroup(int $id): bool
    {
        Db::transaction(function () use ($id) {
            Db::name('erp_goods_spec_item')->where([['site_id', '=', $this->site_id], ['group_id', '=', $id]])->delete();
            Db::name('erp_goods_spec_group')->where([['site_id', '=', $this->site_id], ['group_id', '=', $id]])->delete();
        });
        return true;
    }

    public function saveItem(int $id, array $data): int
    {
        $groupId = (int)($data['group_id'] ?? 0);
        $value = trim((string)($data['item_value'] ?? $data['label'] ?? ''));
        if ($groupId <= 0) {
            throw new AdminException('请选择规格分组');
        }
        if ($value === '') {
            throw new AdminException('请填写规格值');
        }
        $group = Db::name('erp_goods_spec_group')->where([['site_id', '=', $this->site_id], ['group_id', '=', $groupId]])->find();
        if (!$group) {
            throw new AdminException('规格分组不存在');
        }
        $exists = Db::name('erp_goods_spec_item')->where([
            ['site_id', '=', $this->site_id],
            ['group_id', '=', $groupId],
            ['item_value', '=', $value],
        ])->when($id > 0, static fn($query) => $query->where('item_id', '<>', $id))->find();
        if ($exists) {
            throw new AdminException('同分组规格值已存在');
        }
        $payload = [
            'site_id' => $this->site_id,
            'group_id' => $groupId,
            'item_value' => $value,
            'sort' => (int)($data['sort'] ?? 0),
            'update_at' => time(),
        ];
        if ($id > 0) {
            Db::name('erp_goods_spec_item')->where([['site_id', '=', $this->site_id], ['item_id', '=', $id]])->update($payload);
            return $id;
        }
        $payload['create_at'] = time();
        return (int)Db::name('erp_goods_spec_item')->insertGetId($payload);
    }

    public function deleteItem(int $id): bool
    {
        Db::name('erp_goods_spec_item')->where([['site_id', '=', $this->site_id], ['item_id', '=', $id]])->delete();
        return true;
    }

    public function saveGrade(int $id, array $data): int
    {
        $name = trim((string)($data['grade_name'] ?? $data['label'] ?? ''));
        if ($name === '') {
            throw new AdminException('请填写成色名称');
        }
        $this->assertUnique('erp_goods_grade', 'grade_name', $name, 'grade_id', $id);
        $payload = [
            'site_id' => $this->site_id,
            'grade_name' => $name,
            'sort' => (int)($data['sort'] ?? 0),
            'status' => (int)($data['status'] ?? 1) === 1 ? 1 : 0,
            'update_at' => time(),
        ];
        if ($id > 0) {
            Db::name('erp_goods_grade')->where([['site_id', '=', $this->site_id], ['grade_id', '=', $id]])->update($payload);
            return $id;
        }
        $payload['create_at'] = time();
        return (int)Db::name('erp_goods_grade')->insertGetId($payload);
    }

    public function deleteGrade(int $id): bool
    {
        Db::name('erp_goods_grade')->where([['site_id', '=', $this->site_id], ['grade_id', '=', $id]])->delete();
        return true;
    }

    private function assertUnique(string $table, string $field, string $value, string $pk, int $id): void
    {
        $exists = Db::name($table)->where([
            ['site_id', '=', $this->site_id],
            [$field, '=', $value],
        ])->when($id > 0, static fn($query) => $query->where($pk, '<>', $id))->find();
        if ($exists) {
            throw new AdminException('名称已存在');
        }
    }

    private function ensureDefaults(): void
    {
        if (Db::name('erp_goods_spec_group')->where('site_id', $this->site_id)->count() <= 0) {
            $iosId = $this->saveGroup(0, ['label' => '苹果内存', 'title_part' => 1, 'sort' => 10]);
            foreach (['64G', '128G', '256G', '512G', '1T'] as $index => $value) {
                $this->saveItem(0, ['group_id' => $iosId, 'item_value' => $value, 'sort' => ($index + 1) * 10]);
            }
            $androidId = $this->saveGroup(0, ['label' => '安卓内存', 'title_part' => 1, 'sort' => 20]);
            foreach (['4+64G', '6+128G', '8+128G', '8+256G', '12+256G', '16+512G'] as $index => $value) {
                $this->saveItem(0, ['group_id' => $androidId, 'item_value' => $value, 'sort' => ($index + 1) * 10]);
            }
        }
        if (Db::name('erp_goods_grade')->where('site_id', $this->site_id)->count() <= 0) {
            foreach (['全新', '99新', '95新', '9成新', '8成新', '小花', '大花'] as $index => $value) {
                $this->saveGrade(0, ['grade_name' => $value, 'sort' => ($index + 1) * 10, 'status' => 1]);
            }
        }
    }
}
