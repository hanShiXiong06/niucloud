<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device;

use addon\hsx_recycle\app\service\core\device\DeviceCatalogPolicy;
use addon\hsx_recycle\app\service\core\device\DeviceCatalogWriteLock;
use app\dict\sys\AppTypeDict;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/** 修复手工转公共库时被一并移动的打印关联；模板归属是恢复依据。 */
final class DeviceCatalogPrintRecoveryService extends BaseAdminService
{
    public function preview(): array
    {
        $this->assertSite();
        return $this->publicPreview($this->snapshot(false));
    }

    public function restore(string $fingerprint): array
    {
        $this->assertSite();
        $lock = new DeviceCatalogWriteLock();
        $lock->acquire();
        return Db::transaction(function () use ($fingerprint) {
            $snapshot = $this->snapshot(true);
            $preview = $this->publicPreview($snapshot);
            if ($fingerprint === '' || !hash_equals($preview['fingerprint'], $fingerprint)) {
                throw new CommonException('打印关联已变化，请重新检查后再恢复；本次未修改数据');
            }
            $created = 0;
            $cleaned = 0;
            foreach ($snapshot as $item) {
                if (!in_array($item['action'], ['restore', 'clean'], true)) continue;
                $source = $item['source'];
                if ($item['action'] === 'restore') {
                    Db::name('recycle_template_binding')->insert([
                        'site_id' => (int)$this->site_id,
                        'target_type' => $source['target_type'], 'target_id' => (int)$source['target_id'],
                        'scene_key' => $source['scene_key'], 'check_template_id' => 0,
                        'print_template_id' => (int)$source['print_template_id'],
                        'inherit_enabled' => (int)$source['inherit_enabled'], 'status' => (int)$source['status'],
                        'sort' => (int)$source['sort'], 'remark' => (string)$source['remark'],
                        'create_at' => (int)$source['create_at'], 'update_at' => time(),
                    ]);
                    $created++;
                }
                // 只清理已经安全归还本站的打印引用；公共质检及其它公共字段原样保留。
                Db::name('recycle_template_binding')->where('site_id', 0)->where('id', $source['id'])
                    ->update(['print_template_id' => 0, 'update_at' => time()]);
                $cleaned++;
            }
            return ['restored' => $created, 'cleaned' => $cleaned, 'skipped' => $preview['blocked'],
                'message' => '已恢复 ' . $created . ' 条本站打印关联，清理 ' . $cleaned . ' 条公共打印引用；跳过 ' . $preview['blocked'] . ' 条。未触发打印。'];
        });
    }

    private function assertSite(): void
    {
        if ((int)$this->site_id <= 0 || $this->app_type !== AppTypeDict::SITE) {
            throw new CommonException('请进入需要恢复打印配置的站点操作');
        }
        if (!(new DeviceCatalogPolicy())->forSite((int)$this->site_id)['effective']) {
            throw new CommonException('本站尚未使用公共资料库，无需执行此项恢复');
        }
    }

    private function snapshot(bool $forUpdate): array
    {
        // 不接受请求指定站点；仅恢复当前登录站点自己拥有的打印模板。
        $query = Db::name('recycle_template_binding')->alias('b')
            ->join('recycle_printer_template p', 'p.template_id = b.print_template_id')
            ->where('b.site_id', 0)->where('p.site_id', (int)$this->site_id)
            ->field('b.*,p.template_name,p.delete_time AS print_deleted')->order('b.id asc');
        if ($forUpdate) $query->lock(true);
        $result = [];
        foreach ($query->select()->toArray() as $source) {
            $target = Db::name('recycle_template_binding')->where('site_id', (int)$this->site_id)
                ->where('target_type', $source['target_type'])->where('target_id', $source['target_id'])->where('scene_key', $source['scene_key']);
            if ($forUpdate) $target->lock(true);
            $local = $target->find() ?: [];
            $node = $source['target_type'] === 'model_dict'
                ? Db::name('recycle_device_model_dict')->where('site_id', 0)->where('id', $source['target_id'])->field('id,model_full_name,node_name')->find()
                : null;
            $validTarget = ($source['target_type'] === 'global' && (int)$source['target_id'] === 0) || !empty($node);
            $action = 'restore';
            $reason = '恢复本站打印关联，公共质检不变';
            if (!$validTarget || (int)$source['print_deleted'] > 0) {
                $action = 'blocked';
                $reason = !$validTarget ? '绑定对象不在公共库，请先核对' : '原打印模板已删除，不自动恢复';
            } elseif ($local) {
                $same = true;
                foreach (['print_template_id', 'inherit_enabled', 'status'] as $key) {
                    if ((int)$local[$key] !== (int)$source[$key]) $same = false;
                }
                $action = $same ? 'clean' : 'blocked';
                $reason = $same ? '本站已有相同配置，仅清理公共打印引用' : '本站已有不同配置，保留现状，不自动覆盖';
            }
            $result[] = ['source' => $source, 'local' => $local, 'action' => $action, 'reason' => $reason,
                'target_name' => $source['target_type'] === 'global' ? '本站通用规则' : (string)($node['model_full_name'] ?? $node['node_name'] ?? '型号不存在')];
        }
        return $result;
    }

    private function publicPreview(array $snapshot): array
    {
        $result = ['restore' => 0, 'clean' => 0, 'blocked' => 0, 'items' => [],
            'fingerprint' => hash('sha256', json_encode([(int)$this->site_id, $snapshot], JSON_UNESCAPED_UNICODE))];
        foreach ($snapshot as $item) {
            $result[$item['action']]++;
            $result['items'][] = ['target_name' => $item['target_name'], 'template_name' => $item['source']['template_name'],
                'action' => $item['action'], 'reason' => $item['reason']];
        }
        return $result;
    }
}
