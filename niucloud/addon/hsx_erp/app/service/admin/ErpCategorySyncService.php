<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpCategoryMapping;
use addon\hsx_erp\app\model\ErpGoodsCategory;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/** 分类本地ID与插件分类ID解耦；Hook只传稳定快照，不共享主键。 */
class ErpCategorySyncService extends BaseAdminService
{
    public function status(): array
    {
        $rules = (new ErpConfigService())->getRules();
        $providers = $this->providers();
        $mapped = (int)ErpCategoryMapping::where('site_id', '=', $this->site_id)->count();
        $failed = (int)ErpCategoryMapping::where([['site_id', '=', $this->site_id], ['sync_status', '=', 'failed']])->count();
        return [
            'config' => (array)($rules['category_sync'] ?? []),
            'providers' => $providers,
            'erp_count' => (int)ErpGoodsCategory::where('site_id', '=', $this->site_id)->count(),
            'mapped_count' => $mapped,
            'failed_count' => $failed,
            'last_synced_at' => (int)ErpCategoryMapping::where('site_id', '=', $this->site_id)->max('last_synced_at'),
        ];
    }

    public function sync(string $action, string $provider = 'phone_shop'): array
    {
        if (!in_array($action, ['pull', 'push', 'reconcile'], true)) throw new CommonException('分类同步动作不正确');
        if (!$this->providerExists($provider)) throw new CommonException('当前站点未安装或未启用对应的分类提供插件');
        $result = ['pulled' => 0, 'pushed' => 0, 'linked' => 0, 'failed' => 0];
        if (in_array($action, ['pull', 'reconcile'], true)) $result = array_merge($result, $this->pull($provider));
        if (in_array($action, ['push', 'reconcile'], true)) {
            $push = $this->push($provider);
            foreach ($push as $key => $value) $result[$key] = (int)($result[$key] ?? 0) + (int)$value;
        }
        $config = new ErpConfigService();
        $rules = $config->getRules();
        $completed = (int)($result['failed'] ?? 0) === 0;
        $rules['category_sync'] = array_merge((array)($rules['category_sync'] ?? []), [
            'enabled' => $completed ? 1 : (int)($rules['category_sync']['enabled'] ?? 0),
            'provider' => $provider,
            'mode' => $completed ? 'two_way' : (string)($rules['category_sync']['mode'] ?? 'disabled'),
            'initialized' => $completed ? 1 : (int)($rules['category_sync']['initialized'] ?? 0),
            'last_action' => $action,
        ]);
        $config->saveRules($rules);
        return array_merge($result, ['status' => $this->status()]);
    }

    public function receiveExternalChange(array $payload): array
    {
        if ((int)($payload['site_id'] ?? 0) !== $this->site_id) return ['status' => 'skipped'];
        $provider = trim((string)($payload['provider'] ?? ''));
        $category = (array)($payload['category'] ?? []);
        if ($provider === '' || empty($category['category_id'])) return ['status' => 'skipped'];
        $rules = (new ErpConfigService())->getRules();
        $syncRules = (array)($rules['category_sync'] ?? []);
        if ((int)($syncRules['enabled'] ?? 0) !== 1 || !in_array((string)($syncRules['mode'] ?? ''), ['two_way', 'shop_master'], true)) {
            return ['status' => 'disabled'];
        }
        $id = $this->upsertExternalCategory($provider, $category);
        return ['status' => 'synced', 'erp_category_id' => $id];
    }

    public function pushLocalCategory(int $erpCategoryId, string $provider = ''): array
    {
        $rules = (new ErpConfigService())->getRules();
        $syncRules = (array)($rules['category_sync'] ?? []);
        $provider = $provider ?: (string)($syncRules['provider'] ?? 'phone_shop');
        if ((int)($syncRules['enabled'] ?? 0) !== 1 || !in_array((string)($syncRules['mode'] ?? ''), ['two_way', 'erp_master'], true)) {
            return ['status' => 'disabled'];
        }
        return $this->pushCategory($erpCategoryId, $provider);
    }

    private function providers(): array
    {
        $rows = [];
        foreach ((array)event('HsxErpCategoryProviders', ['site_id' => $this->site_id]) as $result) {
            foreach ((array)($result['providers'] ?? []) as $row) {
                if (!is_array($row) || empty($row['key'])) continue;
                $rows[(string)$row['key']] = $row;
            }
        }
        return array_values($rows);
    }

    private function providerExists(string $provider): bool
    {
        return (bool)array_filter($this->providers(), static fn(array $row): bool => (string)$row['key'] === $provider && (int)($row['enabled'] ?? 1) === 1);
    }

    private function pull(string $provider): array
    {
        $categories = [];
        foreach ((array)event('HsxErpCategoryPull', ['site_id' => $this->site_id, 'provider' => $provider]) as $result) {
            if ((string)($result['provider'] ?? '') === $provider) $categories = array_merge($categories, (array)($result['categories'] ?? []));
        }
        usort($categories, static fn(array $a, array $b): int => (int)($a['level'] ?? 1) <=> (int)($b['level'] ?? 1));
        $count = 0;
        Db::transaction(function () use ($provider, $categories, &$count) {
            foreach ($categories as $category) {
                if (!is_array($category) || empty($category['category_id'])) continue;
                $this->upsertExternalCategory($provider, $category);
                $count++;
            }
        });
        return ['pulled' => $count, 'linked' => $count];
    }

    private function upsertExternalCategory(string $provider, array $category): int
    {
        $targetId = (string)$category['category_id'];
        $targetPid = (string)($category['pid'] ?? '0');
        $parentMapping = $targetPid !== '0' ? ErpCategoryMapping::where([
            ['site_id', '=', $this->site_id], ['target_plugin', '=', $provider], ['target_category_id', '=', $targetPid],
        ])->findOrEmpty() : null;
        $pid = $parentMapping && !$parentMapping->isEmpty() ? (int)$parentMapping->erp_category_id : 0;
        $mapping = ErpCategoryMapping::where([
            ['site_id', '=', $this->site_id], ['target_plugin', '=', $provider], ['target_category_id', '=', $targetId],
        ])->findOrEmpty();
        $name = trim((string)($category['category_name'] ?? ''));
        if ($name === '') throw new CommonException('外部分类名称不能为空');
        $erpId = !$mapping->isEmpty() ? (int)$mapping->erp_category_id : 0;
        if ($erpId <= 0) {
            $same = ErpGoodsCategory::where([['site_id', '=', $this->site_id], ['pid', '=', $pid], ['category_name', '=', $name]])->findOrEmpty();
            $erpId = $same->isEmpty() ? 0 : (int)$same->category_id;
        }
        $erpId = (new ErpGoodsCategoryService())->save($erpId, [
            'category_name' => $name, 'pid' => $pid, 'is_show' => (int)($category['is_show'] ?? 1),
            'sort' => (int)($category['sort'] ?? 0), 'source_plugin' => $provider, 'source_id' => $targetId,
        ], false);
        $this->saveMapping($erpId, $provider, $targetId, 'pull', $category);
        return $erpId;
    }

    private function push(string $provider): array
    {
        $rows = ErpGoodsCategory::where('site_id', '=', $this->site_id)->order('level asc,sort desc,category_id asc')->column('category_id');
        $pushed = $failed = 0;
        foreach ($rows as $id) {
            try { $this->pushCategory((int)$id, $provider); $pushed++; }
            catch (\Throwable $e) { $failed++; $this->markFailed((int)$id, $provider, $e->getMessage()); }
        }
        return ['pushed' => $pushed, 'failed' => $failed];
    }

    private function pushCategory(int $erpCategoryId, string $provider): array
    {
        if (!$this->providerExists($provider)) return ['status' => 'provider_unavailable'];
        $category = ErpGoodsCategory::where([['site_id', '=', $this->site_id], ['category_id', '=', $erpCategoryId]])->findOrEmpty();
        if ($category->isEmpty()) throw new CommonException('ERP分类不存在');
        $mapping = ErpCategoryMapping::where([['site_id', '=', $this->site_id], ['erp_category_id', '=', $erpCategoryId], ['target_plugin', '=', $provider]])->findOrEmpty();
        $parentTargetId = '';
        if ((int)$category->pid > 0) {
            $parent = ErpCategoryMapping::where([['site_id', '=', $this->site_id], ['erp_category_id', '=', (int)$category->pid], ['target_plugin', '=', $provider]])->findOrEmpty();
            if ($parent->isEmpty()) $this->pushCategory((int)$category->pid, $provider);
            $parent = ErpCategoryMapping::where([['site_id', '=', $this->site_id], ['erp_category_id', '=', (int)$category->pid], ['target_plugin', '=', $provider]])->findOrEmpty();
            $parentTargetId = $parent->isEmpty() ? '' : (string)$parent->target_category_id;
        }
        $snapshot = ['category_id' => $erpCategoryId, 'target_category_id' => $mapping->isEmpty() ? '' : (string)$mapping->target_category_id,
            'category_name' => (string)$category->category_name, 'pid' => (int)$category->pid, 'target_pid' => $parentTargetId,
            'level' => (int)$category->level, 'category_full_name' => (string)$category->category_full_name,
            'is_show' => (int)$category->is_show, 'sort' => (int)$category->sort];
        foreach ((array)event('HsxErpCategoryPush', ['site_id' => $this->site_id, 'provider' => $provider, 'category' => $snapshot]) as $result) {
            if ((string)($result['provider'] ?? '') !== $provider || empty($result['target_category_id'])) continue;
            $targetId = (string)$result['target_category_id'];
            $this->saveMapping($erpCategoryId, $provider, $targetId, 'push', $snapshot);
            ErpGoodsCategory::where([['site_id', '=', $this->site_id], ['category_id', '=', $erpCategoryId]])->update(['source_plugin' => $provider, 'source_id' => $targetId, 'update_at' => time()]);
            return ['status' => 'synced', 'target_category_id' => $targetId];
        }
        throw new CommonException('分类提供插件未返回同步结果');
    }

    private function saveMapping(int $erpId, string $provider, string $targetId, string $direction, array $snapshot): void
    {
        $now = time();
        $payload = ['target_category_id' => $targetId, 'sync_direction' => $direction === 'pull' ? 'two_way' : 'two_way',
            'sync_status' => 'synced', 'last_sync_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE) ?: ''),
            'last_error' => '', 'last_synced_at' => $now, 'update_at' => $now];
        $mapping = ErpCategoryMapping::where([['site_id', '=', $this->site_id], ['erp_category_id', '=', $erpId], ['target_plugin', '=', $provider]])->findOrEmpty();
        if ($mapping->isEmpty()) ErpCategoryMapping::create(array_merge($payload, ['site_id' => $this->site_id, 'erp_category_id' => $erpId, 'target_plugin' => $provider, 'create_at' => $now]));
        else $mapping->save($payload);
    }

    private function markFailed(int $erpId, string $provider, string $message): void
    {
        $mapping = ErpCategoryMapping::where([['site_id', '=', $this->site_id], ['erp_category_id', '=', $erpId], ['target_plugin', '=', $provider]])->findOrEmpty();
        if (!$mapping->isEmpty()) $mapping->save(['sync_status' => 'failed', 'last_error' => mb_substr($message, 0, 500), 'update_at' => time()]);
    }
}
