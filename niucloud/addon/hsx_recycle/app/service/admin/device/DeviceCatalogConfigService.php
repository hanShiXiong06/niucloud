<?php
declare(strict_types=1);
namespace addon\hsx_recycle\app\service\admin\device;

use addon\hsx_recycle\app\service\core\device\DeviceCatalogPolicy;
use addon\hsx_recycle\app\service\core\device\DeviceCatalogWriteLock;
use app\dict\sys\AppTypeDict;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Cache;

final class DeviceCatalogConfigService extends BaseAdminService
{
    public const TABLES = [
        'recycle_device_model_dict', 'recycle_device_model_alias', 'recycle_check_template',
        'recycle_check_group', 'recycle_check_field', 'recycle_check_option', 'recycle_check_dict',
    ];

    public function info(): array
    {
        return (new DeviceCatalogPolicy())->forSite((int)$this->site_id);
    }

    public function choose(bool $enabled): array
    {
        if ((int)$this->site_id <= 0) throw new CommonException('请在站点端选择资料来源');
        $lock = new DeviceCatalogWriteLock(); $lock->acquire();
        if (Db::name('recycle_device_model_import_task')->where('site_id', $this->site_id)->whereIn('status', ['pending', 'queued', 'processing'])->count()) {
            throw new CommonException('本站仍有型号导入任务，请完成或删除待执行任务后再切换资料来源');
        }
        if ($enabled && empty((new DeviceCatalogPolicy())->platform()['initialized'])) {
            throw new CommonException('公共资料库尚未初始化，请联系站点管理员');
        }
        (new CoreConfigService())->setConfig((int)$this->site_id, DeviceCatalogPolicy::CHOICE_KEY, ['enabled' => $enabled]);
        Cache::tag(RecycleDeviceModelDictService::CACHE_TAG . $this->site_id)->clear();
        return $this->info();
    }

    public function preview(): array
    {
        $this->assertPlatform();
        $policy = (new DeviceCatalogPolicy())->platform();
        $counts = []; $signature = [];
        foreach (array_merge(self::TABLES, ['recycle_template_binding']) as $table) {
            $q = Db::name($table)->where('site_id', DeviceCatalogPolicy::SEED_SITE_ID);
            $counts[$table] = (int)(clone $q)->count();
            $signature[$table] = [$counts[$table], (int)(clone $q)->max('id'), (int)(clone $q)->max('update_at')];
        }
        $blockers = [];
        if (empty($policy['initialized'])) {
            foreach (array_merge(self::TABLES, ['recycle_template_binding']) as $table) {
                if (Db::name($table)->where('site_id', 0)->count()) $blockers[] = '公共库已有资料，不能覆盖：' . $table;
            }
            if (!$counts['recycle_device_model_dict'] || !$counts['recycle_check_template']) $blockers[] = '100005 缺少型号或质检模板，不能初始化';
            if (Db::name('recycle_device_model_import_task')->where('site_id', 100005)->whereIn('status', ['pending', 'queued', 'processing'])->count()
                || Db::name('recycle_check_import_batch')->where('site_id', 100005)->where('status', 'processing')->count()) {
                $blockers[] = '100005 仍有型号或模板导入任务，请完成或处理后再初始化';
            }
        }
        return ['initialized' => !empty($policy['initialized']), 'source_site_id' => 100005,
            'counts' => $counts, 'blockers' => $blockers, 'fingerprint' => hash('sha256', json_encode($signature)),
            'message' => '将 100005 的基础资料转为公共库，保留所有型号、模板和选项编号；订单、设备、价格、质检结果、打印机不迁移。'];
    }

    public function sites(array $filter): array
    {
        $this->assertPlatform();
        $query = Db::name('site')->where('site_id', '>', 0);
        $keyword = trim((string)($filter['keyword'] ?? ''));
        if ($keyword !== '') $query->where(function ($q) use ($keyword) {
            $q->whereLike('site_name', '%' . $keyword . '%');
            if (ctype_digit($keyword)) $q->whereOr('site_id', (int)$keyword);
        });
        $page = max(1, (int)($filter['page'] ?? 1));
        $total = (clone $query)->count();
        $rows = $query->field('site_id,site_name,status')->order('site_id desc')->page($page, 20)->select()->toArray();
        $policy = new DeviceCatalogPolicy();
        foreach ($rows as &$row) $row['catalog'] = $policy->forSite((int)$row['site_id']);
        return ['data' => $rows, 'total' => $total];
    }

    public function grant(int $siteId, bool $allowed): array
    {
        $this->assertPlatform();
        if ($siteId <= 0 || !Db::name('site')->where('site_id', $siteId)->count()) throw new CommonException('站点不存在');
        $lock = new DeviceCatalogWriteLock(); $lock->acquire();
        (new CoreConfigService())->setConfig($siteId, DeviceCatalogPolicy::GRANT_KEY, [
            'allowed' => $allowed, 'operator_uid' => (int)$this->uid, 'update_at' => time(),
        ]);
        return (new DeviceCatalogPolicy())->forSite($siteId);
    }

    public function promote(string $fingerprint): array
    {
        $this->assertPlatform();
        $lock = new DeviceCatalogWriteLock(); $lock->acquire();
        $preview = $this->preview();
        if ($preview['initialized']) return ['initialized' => true, 'already_initialized' => true];
        if ($preview['blockers']) throw new CommonException(implode('；', $preview['blockers']));
        if ($fingerprint === '' || !hash_equals($preview['fingerprint'], $fingerprint)) throw new CommonException('资料已变化，请重新预检后再确认');
        $this->validateReferences();
        try {
            Db::transaction(function () use ($preview) {
                foreach (self::TABLES as $table) Db::name($table)->where('site_id', 100005)->update(['site_id' => 0]);
                // 原站打印绑定保留。公共库只复制质检关系，不分享打印机或标签模板。
                $bindings = Db::name('recycle_template_binding')->where('site_id', 100005)->where('check_template_id', '>', 0)->select()->toArray();
                foreach ($bindings as $row) {
                    unset($row['id']); $row['site_id'] = 0; $row['print_template_id'] = 0; $row['remark'] = '';
                    Db::name('recycle_template_binding')->insert($row);
                }
                Db::name('recycle_template_binding')->where('site_id', 100005)->where('check_template_id', '>', 0)->update(['check_template_id' => 0]);
                $config = new CoreConfigService();
                $config->setConfig(0, DeviceCatalogPolicy::PLATFORM_KEY, ['initialized' => true, 'source_site_id' => 100005,
                    'initialized_at' => time(), 'operator_uid' => (int)$this->uid, 'counts' => $preview['counts']]);
                $config->setConfig(100005, DeviceCatalogPolicy::CHOICE_KEY, ['enabled' => true]);
            });
        } finally {
            foreach ([0, 100005] as $siteId) {
                Cache::tag(CoreConfigService::$cache_tag_name . $siteId)->clear();
                Cache::tag(RecycleDeviceModelDictService::CACHE_TAG . $siteId)->clear();
            }
        }
        return ['initialized' => true, 'already_initialized' => false, 'counts' => $preview['counts']];
    }

    private function validateReferences(): void
    {
        $sets = [];
        foreach (['nodes' => 'recycle_device_model_dict', 'templates' => 'recycle_check_template', 'groups' => 'recycle_check_group', 'fields' => 'recycle_check_field'] as $key => $table) {
            $sets[$key] = array_fill_keys(Db::name($table)->where('site_id', 100005)->column('id'), true);
        }
        $checks = [
            ['recycle_device_model_dict', 'pid', 'nodes', true], ['recycle_device_model_alias', 'category_id', 'nodes', false],
            ['recycle_check_group', 'template_id', 'templates', false], ['recycle_check_field', 'template_id', 'templates', false],
            ['recycle_check_field', 'group_id', 'groups', true], ['recycle_check_option', 'field_id', 'fields', false],
            ['recycle_template_binding', 'check_template_id', 'templates', true],
        ];
        foreach ($checks as [$table, $field, $set, $optional]) {
            foreach (Db::name($table)->where('site_id', 100005)->field('id,' . $field)->select()->toArray() as $row) {
                $id = (int)$row[$field];
                if (($optional && !$id) || isset($sets[$set][$id])) continue;
                throw new CommonException('初始化未执行：存在缺失关联 ' . $table . '#' . $row['id'] . '，请先核对');
            }
        }
        foreach (Db::name('recycle_template_binding')->where('site_id', 100005)->where('check_template_id', '>', 0)->select()->toArray() as $row) {
            if ($row['target_type'] === 'global' && (int)$row['target_id'] === 0) continue;
            if ($row['target_type'] === 'model_dict' && isset($sets['nodes'][(int)$row['target_id']])) continue;
            throw new CommonException('初始化未执行：质检模板绑定对象异常 #' . $row['id']);
        }
    }

    private function assertPlatform(): void
    {
        if ((int)$this->site_id !== 0 || $this->app_type !== AppTypeDict::ADMIN) throw new CommonException('仅平台管理员可以初始化公共资料库');
    }
}
