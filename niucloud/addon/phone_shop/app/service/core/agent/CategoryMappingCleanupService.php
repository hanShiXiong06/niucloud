<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 子站重复分类安全归并
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\service\core\agent;

use core\base\BaseCoreService;
use think\facade\Db;
use think\facade\Log;

/**
 * 只归并子站中由主站同步产生、且能唯一匹配子站本地分类的重复项。
 * 主站全程只读；有歧义的分类一律跳过。
 */
class CategoryMappingCleanupService extends BaseCoreService
{
    public function preview(int $masterSiteId, int $agentSiteId): array
    {
        $this->assertSites($masterSiteId, $agentSiteId);
        return $this->buildPlan($masterSiteId, $agentSiteId);
    }

    public function apply(int $masterSiteId, int $agentSiteId, string $expectedPlanHash): array
    {
        $this->assertSites($masterSiteId, $agentSiteId);
        if ($expectedPlanHash === '') {
            throw new \InvalidArgumentException('缺少预览指纹，已拒绝执行分类归并');
        }
        Db::startTrans();
        try {
            // 锁住子站分类后重新生成方案，避免预览后分类又被修改。
            Db::name('phone_shop_goods_category')
                ->where('site_id', '=', $agentSiteId)
                ->lock(true)
                ->field('category_id')
                ->select();
            $plan = $this->buildPlan($masterSiteId, $agentSiteId);
            if (!hash_equals((string)$plan['plan_hash'], $expectedPlanHash)) {
                throw new \RuntimeException('分类数据已发生变化，请重新预览后再执行');
            }
            $mapping = $plan['mapping'];
            if (!$mapping) {
                Db::commit();
                return $plan + ['goods_updated' => 0, 'references_updated' => 0, 'children_reparented' => 0, 'deleted' => 0];
            }

            $goodsUpdated = $this->replaceGoodsCategories($agentSiteId, $mapping);
            $referencesUpdated = $this->replaceCategoryReferences($agentSiteId, $mapping);
            $childrenReparented = 0;
            foreach ($mapping as $oldId => $newId) {
                $childrenReparented += Db::name('phone_shop_goods_category')->where([
                    ['site_id', '=', $agentSiteId],
                    ['pid', '=', (int)$oldId],
                ])->update(['pid' => (int)$newId, 'update_time' => time()]);
            }

            // 删除条件再次限定 site_id + source_site_id，任何情况下都不会触碰主站。
            $deleted = Db::name('phone_shop_goods_category')->where([
                ['site_id', '=', $agentSiteId],
                ['source_site_id', '=', $masterSiteId],
                ['category_id', 'in', array_map('intval', array_keys($mapping))],
            ])->delete();

            Db::commit();
            $result = $plan + [
                'goods_updated' => $goodsUpdated,
                'references_updated' => $referencesUpdated,
                'children_reparented' => $childrenReparented,
                'deleted' => (int)$deleted,
            ];
            Log::write('[phone_shop 分类归并] ' . json_encode($result, JSON_UNESCAPED_UNICODE));
            return $result;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    protected function assertSites(int $masterSiteId, int $agentSiteId): void
    {
        $configuredMaster = (new AgentConfigService())->getMasterSiteId();
        if ($masterSiteId <= 0 || $agentSiteId <= 0 || $masterSiteId === $agentSiteId) {
            throw new \InvalidArgumentException('主从站参数无效，已拒绝执行');
        }
        if ($configuredMaster !== $masterSiteId) {
            throw new \RuntimeException('主站ID与系统配置不一致，已拒绝执行');
        }
        $relation = Db::name('phone_shop_agent')->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
        ])->find();
        if (!$relation) throw new \RuntimeException('主从站关系不存在，已拒绝执行');
    }

    protected function buildPlan(int $masterSiteId, int $agentSiteId): array
    {
        $rows = Db::name('phone_shop_goods_category')->where('site_id', '=', $agentSiteId)
            ->field('category_id,source_site_id,level,category_name,category_full_name,pid')
            ->order('level asc,category_id asc')->select()->toArray();
        $locals = [];
        foreach ($rows as $row) {
            if ((int)$row['source_site_id'] !== 0) continue;
            $key = $this->pathKey($row);
            if ($key !== '') $locals[$key][] = $row;
        }

        $mapping = [];
        $details = [];
        $skipped = [];
        foreach ($rows as $row) {
            if ((int)$row['source_site_id'] !== $masterSiteId) continue;
            $key = $this->pathKey($row);
            $matches = $key === '' ? [] : ($locals[$key] ?? []);
            if (count($matches) !== 1) {
                $skipped[] = [
                    'source_category_id' => (int)$row['category_id'],
                    'path' => (string)($row['category_full_name'] ?: $row['category_name']),
                    'reason' => count($matches) === 0 ? '子站无同路径分类' : '子站同路径分类不唯一',
                ];
                continue;
            }
            $target = $matches[0];
            $mapping[(int)$row['category_id']] = (int)$target['category_id'];
            $details[] = [
                'source_category_id' => (int)$row['category_id'],
                'target_category_id' => (int)$target['category_id'],
                'path' => (string)($row['category_full_name'] ?: $row['category_name']),
            ];
        }
        $planHash = hash('sha256', json_encode([
            'master_site_id' => $masterSiteId,
            'agent_site_id' => $agentSiteId,
            'mapping' => $mapping,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return [
            'master_site_id' => $masterSiteId,
            'agent_site_id' => $agentSiteId,
            'plan_hash' => $planHash,
            'mapping_count' => count($mapping),
            'mapping' => $mapping,
            'details' => $details,
            'skipped' => $skipped,
        ];
    }

    protected function pathKey(array $row): string
    {
        $path = trim((string)($row['category_full_name'] ?: $row['category_name'] ?? ''));
        if ($path === '') return '';
        $path = str_replace(['\\', '>', '＞', '|'], '/', $path);
        $parts = array_values(array_filter(array_map('trim', explode('/', $path)), static fn($v) => $v !== ''));
        return (int)($row['level'] ?? 0) . '|' . mb_strtolower(implode('/', $parts));
    }

    protected function replaceGoodsCategories(int $agentSiteId, array $mapping): int
    {
        $rows = Db::name('phone_shop_goods')->where('site_id', '=', $agentSiteId)
            ->field('goods_id,goods_category')->select()->toArray();
        $updated = 0;
        foreach ($rows as $row) {
            $categories = is_array($row['goods_category'])
                ? $row['goods_category']
                : json_decode((string)$row['goods_category'], true);
            if (!is_array($categories)) continue;
            $changed = false;
            foreach ($categories as &$categoryId) {
                $oldId = (int)$categoryId;
                if (!isset($mapping[$oldId])) continue;
                $categoryId = (string)$mapping[$oldId];
                $changed = true;
            }
            unset($categoryId);
            if (!$changed) continue;
            $categories = array_values(array_unique(array_map('strval', $categories)));
            $updated += Db::name('phone_shop_goods')->where([
                ['site_id', '=', $agentSiteId],
                ['goods_id', '=', (int)$row['goods_id']],
            ])->update(['goods_category' => json_encode($categories, JSON_UNESCAPED_UNICODE), 'update_time' => time()]);
        }
        return $updated;
    }

    /** 更新插件内明确存在的分类外键/分类ID集合，避免删分类后留下悬空引用。 */
    protected function replaceCategoryReferences(int $agentSiteId, array $mapping): int
    {
        $updated = 0;
        foreach ([
            ['phone_shop_goods_spec_group', 'category_id'],
            ['phone_shop_coupon_goods', 'category_id'],
            ['phone_shop_goods_evaluate', 'category_id'],
        ] as [$table, $field]) {
            if (!$this->hasColumns($table, ['site_id', $field])) continue;
            foreach ($mapping as $oldId => $newId) {
                $updated += Db::name($table)->where([
                    ['site_id', '=', $agentSiteId],
                    [$field, '=', (int)$oldId],
                ])->update([$field => (int)$newId]);
            }
        }
        foreach ([
            ['phone_shop_goods_spec_group', 'group_id', 'category_ids'],
            ['phone_shop_goods_rank', 'rank_id', 'category_ids'],
            ['phone_shop_active_goods', 'active_goods_id', 'active_goods_category'],
        ] as [$table, $pk, $field]) {
            $updated += $this->replaceCategoryListField($table, $pk, $field, $agentSiteId, $mapping);
        }
        return $updated;
    }

    protected function replaceCategoryListField(string $table, string $pk, string $field, int $agentSiteId, array $mapping): int
    {
        if (!$this->hasColumns($table, [$pk, 'site_id', $field])) return 0;
        $rows = Db::name($table)->where('site_id', '=', $agentSiteId)
            ->where($field, '<>', '')->field($pk . ',' . $field)->select()->toArray();
        $updated = 0;
        foreach ($rows as $row) {
            $raw = trim((string)($row[$field] ?? ''));
            if ($raw === '') continue;
            $isJson = str_starts_with($raw, '[');
            $values = $isJson ? json_decode($raw, true) : preg_split('/[,，\s]+/', $raw);
            if (!is_array($values)) continue;
            $changed = false;
            foreach ($values as &$value) {
                $oldId = (int)$value;
                if (!isset($mapping[$oldId])) continue;
                $value = (string)$mapping[$oldId];
                $changed = true;
            }
            unset($value);
            if (!$changed) continue;
            $values = array_values(array_unique(array_filter(array_map('strval', $values), static fn($v) => $v !== '0' && $v !== '')));
            $newValue = $isJson
                ? json_encode($values, JSON_UNESCAPED_UNICODE)
                : implode(',', $values);
            $updated += Db::name($table)->where([
                ['site_id', '=', $agentSiteId],
                [$pk, '=', (int)$row[$pk]],
            ])->update([$field => $newValue]);
        }
        return $updated;
    }

    protected function hasColumns(string $baseTable, array $required): bool
    {
        $prefix = (string)config('database.connections.mysql.prefix');
        $full = $prefix . $baseTable;
        if (empty(Db::query("SHOW TABLES LIKE '{$full}'"))) return false;
        $columns = array_column(Db::query("SHOW COLUMNS FROM `{$full}`"), 'Field');
        return count(array_diff($required, $columns)) === 0;
    }
}
