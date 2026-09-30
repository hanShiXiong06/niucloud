<?php

namespace addon\phone_shop\app\service\core\agent;

use addon\phone_shop\app\model\agent\PhoneShopCategoryMapping;
use addon\phone_shop\app\service\core\upgrade\SchemaSyncService;
use core\base\BaseCoreService;
use think\facade\Db;

/**
 * 分类跨站映射唯一事实源。
 *
 * 已关联分类跟随主站名称和层级，保留子站ID；未关联分类不覆盖。
 * 缺失分类按关系设置补建，同名歧义与人工忽略仍交给管理员处理。
 */
class CategoryMappingService extends BaseCoreService
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_MAPPED = 'mapped';
    public const STATUS_BROKEN = 'broken';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_SOURCE_DELETED = 'source_deleted';

    protected static bool $schemaReady = false;
    private array $resolving = [];

    public function ensureSchema(): void
    {
        if (self::$schemaReady) return;
        $schema = new SchemaSyncService();
        // 映射依赖分类表的 category_no/source_site_id。老站点首次打开工作台时
        // 同步补齐这两个字段，避免再次出现 fields not exists 黑盒错误。
        if (!$schema->ensureTable('phone_shop_goods_category')) {
            throw new \RuntimeException('商品分类表结构初始化失败');
        }
        if (!$schema->ensureTable('phone_shop_category_mapping')) {
            throw new \RuntimeException('分类映射表初始化失败');
        }
        self::$schemaReady = true;
    }

    /** 返回有效的子站分类ID；无法安全确认时写待办并返回0。 */
    public function resolveAgentCategoryId(int $masterSiteId, int $agentSiteId, int $masterCategoryId, bool $createMissing = false): int
    {
        $key = "{$masterSiteId}:{$agentSiteId}:{$masterCategoryId}";
        if (isset($this->resolving[$key]) || count($this->resolving) >= 32) {
            throw new \RuntimeException('主站分类层级存在循环，请先修正分类关系');
        }
        $this->resolving[$key] = true;
        try {
            $this->ensureSchema();
            return Db::transaction(function () use ($masterSiteId, $agentSiteId, $masterCategoryId, $createMissing) {
                $relation = Db::name('phone_shop_agent')->where([
                    ['master_site_id', '=', $masterSiteId], ['agent_site_id', '=', $agentSiteId], ['status', '=', 1],
                ])->lock(true)->find();
                if (!$relation) return 0;
                return $this->resolveCategory($masterSiteId, $agentSiteId, $masterCategoryId,
                    $createMissing || (int)($relation['ref_auto_create_category'] ?? 0) === 1);
            });
        } finally {
            unset($this->resolving[$key]);
        }
    }

    protected function resolveCategory(int $masterSiteId, int $agentSiteId, int $masterCategoryId, bool $createMissing): int
    {
        if ($masterSiteId <= 0 || $agentSiteId <= 0 || $masterCategoryId <= 0 || $masterSiteId === $agentSiteId) return 0;
        $this->ensureSchema();
        $master = Db::name('phone_shop_goods_category')->where([
            ['site_id', '=', $masterSiteId],
            ['category_id', '=', $masterCategoryId],
        ])->find();
        if (!$master) {
            $this->saveState($masterSiteId, $agentSiteId, $masterCategoryId, [
                'agent_category_id' => 0,
                'status' => self::STATUS_SOURCE_DELETED,
                'problem_reason' => '主站分类已不存在',
                'candidate_ids' => [],
            ]);
            return 0;
        }

        $mapping = $this->findMapping($masterSiteId, $agentSiteId, $masterCategoryId);
        if ($mapping && (string)$mapping['status'] === self::STATUS_IGNORED) {
            $this->touchSnapshot((int)$mapping['mapping_id'], $master);
            return 0;
        }
        $targetId = $mapping ? (int)$mapping['agent_category_id'] : 0;
        if ($targetId > 0 && !$this->agentCategoryExists($agentSiteId, $targetId)) {
            $manual = in_array((string)$mapping['match_type'], ['manual', 'manual_created'], true);
            $this->saveState($masterSiteId, $agentSiteId, $masterCategoryId, [
                'agent_category_id' => 0,
                'status' => self::STATUS_BROKEN,
                'problem_reason' => '已关联的子站分类被删除，请重新选择',
                'candidate_ids' => [],
                'master_snapshot' => $this->snapshot($master),
            ]);
            if ($manual) return 0;
            $targetId = 0;
        }

        $mappedPid = 0;
        if ((int)($master['pid'] ?? 0) > 0) {
            $mappedPid = $this->resolveAgentCategoryId(
                $masterSiteId,
                $agentSiteId,
                (int)$master['pid'],
                $createMissing
            );
            if ($mappedPid <= 0) {
                if ($targetId <= 0) $this->savePending($masterSiteId, $agentSiteId, $master, [], '上级分类尚未完成映射');
                return 0;
            }
        }

        if ($targetId > 0) {
            $this->refreshMappedCategory($masterSiteId, $agentSiteId, $master, $targetId, $mappedPid);
            return $this->saveMapped($masterSiteId, $agentSiteId, $master, $targetId,
                (string)$mapping['match_type'], (int)$mapping['resolved_by']);
        }
        if ($mapping && (string)$mapping['status'] === self::STATUS_BROKEN
            && in_array((string)$mapping['match_type'], ['manual', 'manual_created'], true)) return 0;

        $locals = Db::name('phone_shop_goods_category')->where([
            ['site_id', '=', $agentSiteId],
            ['source_site_id', '=', 0],
            ['level', '=', (int)($master['level'] ?? 1)],
        ])->field('category_id,category_name,category_full_name,pid,level')->lock(true)->select()->toArray();

        $exactPath = array_values(array_filter($locals, function (array $row) use ($master, $mappedPid) {
            return (int)$row['pid'] === $mappedPid
                && $this->pathKey($row) === $this->pathKey($master);
        }));
        if (count($exactPath) === 1) {
            $this->refreshMappedCategory($masterSiteId, $agentSiteId, $master, (int)$exactPath[0]['category_id'], $mappedPid);
            return $this->saveMapped($masterSiteId, $agentSiteId, $master, (int)$exactPath[0]['category_id'], 'auto_exact', 0);
        }

        $sameName = array_values(array_filter($locals, static function (array $row) use ($master, $mappedPid) {
            return (int)$row['pid'] === $mappedPid
                && trim((string)$row['category_name']) === trim((string)($master['category_name'] ?? ''));
        }));
        if (count($sameName) === 1) {
            $this->refreshMappedCategory($masterSiteId, $agentSiteId, $master, (int)$sameName[0]['category_id'], $mappedPid);
            return $this->saveMapped($masterSiteId, $agentSiteId, $master, (int)$sameName[0]['category_id'], 'auto_exact', 0);
        }

        // 已有来源公共键是稳定身份，改名或移动后仍复用同一个子站分类。
        $publicNo = (int)($master['category_no'] ?? 0);
        if ($publicNo <= 0) $publicNo = $masterCategoryId;
        $legacyRows = Db::name('phone_shop_goods_category')->where([
            ['site_id', '=', $agentSiteId],
            ['source_site_id', '=', $masterSiteId],
            ['category_no', '=', $publicNo],
        ])->field('category_id,category_name,category_full_name,pid,level')->lock(true)->limit(5)->select()->toArray();
        if (count($legacyRows) === 1) {
            $this->refreshMappedCategory($masterSiteId, $agentSiteId, $master, (int)$legacyRows[0]['category_id'], $mappedPid);
            return $this->saveMapped($masterSiteId, $agentSiteId, $master, (int)$legacyRows[0]['category_id'], 'legacy_copy', 0);
        }

        $candidateIds = array_values(array_unique(array_map('intval', array_column(array_merge($exactPath, $sameName), 'category_id'))));
        if (!$candidateIds) {
            $candidateIds = array_values(array_unique(array_map('intval', array_column(array_filter($locals, static function (array $row) use ($master) {
                return trim((string)$row['category_name']) === trim((string)($master['category_name'] ?? ''));
            }), 'category_id'))));
        }
        if ($createMissing && !$candidateIds && !$legacyRows) {
            $parent = $mappedPid > 0 ? Db::name('phone_shop_goods_category')->where([
                ['site_id', '=', $agentSiteId], ['category_id', '=', $mappedPid],
            ])->lock(true)->find() : null;
            $created = Db::name('phone_shop_goods_category')->insertGetId([
                'site_id' => $agentSiteId, 'category_no' => $publicNo, 'source_site_id' => $masterSiteId,
                'category_name' => (string)$master['category_name'], 'image' => (string)($master['image'] ?? ''),
                'level' => $parent ? (int)$parent['level'] + 1 : 1, 'pid' => $mappedPid,
                'category_full_name' => $parent ? (($parent['category_full_name'] ?: $parent['category_name']) . '/' . $master['category_name']) : $master['category_name'],
                'is_show' => (int)$master['is_show'], 'sort' => (int)$master['sort'], 'create_time' => time(), 'update_time' => time(),
            ]);
            return $this->saveMapped($masterSiteId, $agentSiteId, $master, (int)$created, 'auto_created', 0);
        }
        $reason = count($candidateIds) > 1 ? '存在多个可能的子站分类，请人工确认' : '未找到可唯一复用的子站分类';
        $this->savePending($masterSiteId, $agentSiteId, $master, array_slice($candidateIds, 0, 20), $reason);
        return 0;
    }

    /** 扫描主站全部分类，返回映射健康度。 */
    public function scan(int $masterSiteId, int $agentSiteId, bool $createMissing = false): array
    {
        $this->ensureSchema();
        $ids = Db::name('phone_shop_goods_category')->where('site_id', '=', $masterSiteId)
            ->order('level asc,category_id asc')->column('category_id');
        $resolved = 0;
        foreach ($ids as $id) {
            if ($this->resolveAgentCategoryId($masterSiteId, $agentSiteId, (int)$id, $createMissing) > 0) $resolved++;
        }
        if ($ids) {
            Db::name('phone_shop_category_mapping')->where([
                ['master_site_id', '=', $masterSiteId],
                ['agent_site_id', '=', $agentSiteId],
                ['master_category_id', 'not in', array_map('intval', $ids)],
            ])->update([
                'status' => self::STATUS_SOURCE_DELETED,
                'problem_reason' => '主站分类已删除，子站分类保持不变',
                'last_check_time' => time(),
                'update_time' => time(),
            ]);
        }
        return $this->summary($masterSiteId, $agentSiteId) + ['master_total' => count($ids), 'resolved_now' => $resolved];
    }

    public function mapExisting(int $masterSiteId, int $agentSiteId, int $masterCategoryId, int $agentCategoryId, int $uid, string $type = 'manual'): int
    {
        $this->ensureSchema();
        $master = Db::name('phone_shop_goods_category')->where([
            ['site_id', '=', $masterSiteId], ['category_id', '=', $masterCategoryId],
        ])->find();
        if (!$master) throw new \RuntimeException('主站分类不存在');
        if (!$this->agentCategoryExists($agentSiteId, $agentCategoryId)) throw new \RuntimeException('子站分类不存在');
        return $this->saveMapped($masterSiteId, $agentSiteId, $master, $agentCategoryId, $type, $uid);
    }

    public function ignore(int $masterSiteId, int $agentSiteId, int $masterCategoryId, int $uid): void
    {
        $this->ensureSchema();
        $master = Db::name('phone_shop_goods_category')->where([
            ['site_id', '=', $masterSiteId], ['category_id', '=', $masterCategoryId],
        ])->find();
        if (!$master) throw new \RuntimeException('主站分类不存在');
        $this->saveState($masterSiteId, $agentSiteId, $masterCategoryId, [
            'agent_category_id' => 0,
            'status' => self::STATUS_IGNORED,
            'match_type' => 'manual',
            'candidate_ids' => [],
            'problem_reason' => '管理员选择忽略，该分类下的代理商品不会上架',
            'master_snapshot' => $this->snapshot($master),
            'resolved_by' => $uid,
        ]);
    }

    public function summary(int $masterSiteId, int $agentSiteId): array
    {
        $this->ensureSchema();
        $counts = Db::name('phone_shop_category_mapping')->where([
            ['master_site_id', '=', $masterSiteId], ['agent_site_id', '=', $agentSiteId],
        ])->group('status')->column('count(*)', 'status');
        return [
            'mapped' => (int)($counts[self::STATUS_MAPPED] ?? 0),
            'pending' => (int)($counts[self::STATUS_PENDING] ?? 0),
            'broken' => (int)($counts[self::STATUS_BROKEN] ?? 0),
            'ignored' => (int)($counts[self::STATUS_IGNORED] ?? 0),
            'source_deleted' => (int)($counts[self::STATUS_SOURCE_DELETED] ?? 0),
        ];
    }

    protected function findMapping(int $masterSiteId, int $agentSiteId, int $masterCategoryId): ?array
    {
        $row = Db::name('phone_shop_category_mapping')->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
            ['master_category_id', '=', $masterCategoryId],
        ])->lock(true)->find();
        return $row ?: null;
    }

    protected function saveMapped(int $masterSiteId, int $agentSiteId, array $master, int $agentCategoryId, string $type, int $uid): int
    {
        $this->saveState($masterSiteId, $agentSiteId, (int)$master['category_id'], [
            'agent_category_id' => $agentCategoryId,
            'status' => self::STATUS_MAPPED,
            'match_type' => $type,
            'candidate_ids' => [],
            'problem_reason' => '',
            'master_snapshot' => $this->snapshot($master),
            'resolved_by' => $uid,
        ]);
        return $agentCategoryId;
    }

    protected function savePending(int $masterSiteId, int $agentSiteId, array $master, array $candidateIds, string $reason): void
    {
        $existing = $this->findMapping($masterSiteId, $agentSiteId, (int)$master['category_id']);
        if ($existing && in_array((string)$existing['status'], [self::STATUS_IGNORED, self::STATUS_BROKEN], true)
            && in_array((string)$existing['match_type'], ['manual', 'manual_created'], true)) {
            $this->saveState($masterSiteId, $agentSiteId, (int)$master['category_id'], [
                'candidate_ids' => $candidateIds,
                'master_snapshot' => $this->snapshot($master),
                'last_check_time' => time(),
            ]);
            return;
        }
        $this->saveState($masterSiteId, $agentSiteId, (int)$master['category_id'], [
            'agent_category_id' => 0,
            'status' => self::STATUS_PENDING,
            'match_type' => '',
            'candidate_ids' => $candidateIds,
            'problem_reason' => $reason,
            'master_snapshot' => $this->snapshot($master),
        ]);
    }

    protected function saveState(int $masterSiteId, int $agentSiteId, int $masterCategoryId, array $data): void
    {
        $now = time();
        $model = new PhoneShopCategoryMapping();
        $row = $model->where([
            ['master_site_id', '=', $masterSiteId],
            ['agent_site_id', '=', $agentSiteId],
            ['master_category_id', '=', $masterCategoryId],
        ])->findOrEmpty();
        $data['last_check_time'] = (int)($data['last_check_time'] ?? $now);
        $data['update_time'] = $now;
        if (!$row->isEmpty()) {
            $row->save($data);
            return;
        }
        $payload = $data + [
                'master_site_id' => $masterSiteId,
                'agent_site_id' => $agentSiteId,
                'master_category_id' => $masterCategoryId,
                'agent_category_id' => 0,
                'status' => self::STATUS_PENDING,
                'match_type' => '',
                'candidate_ids' => [],
                'problem_reason' => '',
                'master_snapshot' => [],
                'resolved_by' => 0,
                'create_time' => $now,
            ];
        try {
            $model->create($payload);
        } catch (\Throwable $e) {
            // 多个商品任务可能同时首次碰到同一分类。唯一索引负责阻止重复行，
            // 冲突后仅更新已经由另一个任务创建的同一映射，不能让整件商品同步失败。
            $concurrent = (new PhoneShopCategoryMapping())->where([
                ['master_site_id', '=', $masterSiteId],
                ['agent_site_id', '=', $agentSiteId],
                ['master_category_id', '=', $masterCategoryId],
            ])->findOrEmpty();
            if ($concurrent->isEmpty()) throw $e;
            $concurrent->save($data);
        }
    }

    protected function touchSnapshot(int $mappingId, array $master): void
    {
        Db::name('phone_shop_category_mapping')->where('mapping_id', '=', $mappingId)->update([
            'master_snapshot' => json_encode($this->snapshot($master), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'last_check_time' => time(),
            'update_time' => time(),
        ]);
    }

    protected function snapshot(array $master): array
    {
        return [
            'category_id' => (int)($master['category_id'] ?? 0),
            'category_no' => (int)($master['category_no'] ?? 0),
            'category_name' => (string)($master['category_name'] ?? ''),
            'category_full_name' => (string)($master['category_full_name'] ?? ''),
            'pid' => (int)($master['pid'] ?? 0),
            'level' => (int)($master['level'] ?? 1),
        ];
    }

    protected function pathKey(array $row): string
    {
        $path = trim((string)(($row['category_full_name'] ?? '') ?: ($row['category_name'] ?? '')));
        $path = str_replace(['\\', '>', '＞', '|'], '/', $path);
        $parts = array_values(array_filter(array_map('trim', explode('/', $path)), static fn($v) => $v !== ''));
        return mb_strtolower(implode('/', $parts));
    }

    protected function agentCategoryExists(int $agentSiteId, int $categoryId): bool
    {
        return $categoryId > 0 && Db::name('phone_shop_goods_category')->where([
            ['site_id', '=', $agentSiteId], ['category_id', '=', $categoryId],
        ])->lock(true)->find() !== null;
    }

    /** 关联后以主站为准，但不替换子站ID；多个来源竞争同一分类时拒绝覆盖。 */
    protected function refreshMappedCategory(int $masterSiteId, int $agentSiteId, array $master, int $targetId, int $parentId): void
    {
        $conflict = Db::name('phone_shop_category_mapping')->where([
            ['agent_site_id', '=', $agentSiteId], ['agent_category_id', '=', $targetId], ['status', '=', self::STATUS_MAPPED],
        ])->where(function ($q) use ($masterSiteId, $master) {
            $q->where('master_site_id', '<>', $masterSiteId)->whereOr('master_category_id', '<>', (int)$master['category_id']);
        })->count();
        if ($conflict) throw new \RuntimeException('多个主站分类关联到了同一子站分类，请先调整分类映射');
        $target = Db::name('phone_shop_goods_category')->where([
            ['site_id', '=', $agentSiteId], ['category_id', '=', $targetId],
        ])->lock(true)->find();
        if (!$target) throw new \RuntimeException('子站分类不存在');
        $parent = null;
        $ancestor = $parentId;
        $seen = [$targetId => true];
        while ($ancestor > 0) {
            if (isset($seen[$ancestor]) || count($seen) > 32) throw new \RuntimeException('子站分类调整会形成循环，请检查映射');
            $seen[$ancestor] = true;
            $row = Db::name('phone_shop_goods_category')->where([
                ['site_id', '=', $agentSiteId], ['category_id', '=', $ancestor],
            ])->lock(true)->find();
            if (!$row) throw new \RuntimeException('子站上级分类不存在');
            if ($ancestor === $parentId) $parent = $row;
            $ancestor = (int)$row['pid'];
        }
        $payload = [
            'category_name' => (string)$master['category_name'], 'image' => (string)($master['image'] ?? ''),
            'level' => $parent ? (int)$parent['level'] + 1 : 1, 'pid' => $parentId,
            'category_full_name' => $parent ? (($parent['category_full_name'] ?: $parent['category_name']) . '/' . $master['category_name']) : $master['category_name'],
            'is_show' => (int)$master['is_show'], 'sort' => (int)$master['sort'],
        ];
        if (!array_diff_assoc($payload, array_intersect_key($target, $payload))) return;
        Db::name('phone_shop_goods_category')->where([
            ['site_id', '=', $agentSiteId], ['category_id', '=', $targetId],
        ])->update($payload + ['update_time' => time()]);
        $this->refreshChildPaths($agentSiteId, $targetId, $payload['category_full_name'], $payload['level'], [$targetId]);
    }

    /** 子分类自身名称和归属不改，仅刷新由父级派生的路径与层级。 */
    protected function refreshChildPaths(int $siteId, int $parentId, string $path, int $level, array $visited): void
    {
        $children = Db::name('phone_shop_goods_category')->where([
            ['site_id', '=', $siteId], ['pid', '=', $parentId],
        ])->field('category_id,category_name')->select()->toArray();
        foreach ($children as $child) {
            $id = (int)$child['category_id'];
            if (in_array($id, $visited, true) || count($visited) >= 32) throw new \RuntimeException('子站分类层级存在循环');
            $fullName = $path . '/' . $child['category_name'];
            Db::name('phone_shop_goods_category')->where([
                ['site_id', '=', $siteId], ['category_id', '=', $id],
            ])->update(['category_full_name' => $fullName, 'level' => $level + 1, 'update_time' => time()]);
            $this->refreshChildPaths($siteId, $id, $fullName, $level + 1, array_merge($visited, [$id]));
        }
    }
}
