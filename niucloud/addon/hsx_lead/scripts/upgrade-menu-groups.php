<?php
declare(strict_types=1);
namespace addon\hsx_lead\scripts;

use app\service\admin\sys\MenuService;
use app\service\admin\sys\RoleService;
use app\service\core\menu\CoreMenuService;
use RuntimeException;
use think\facade\Cache;
use think\facade\Db;
use Throwable;

if (!defined('HSX_LEAD_UPGRADE_LIBRARY_ONLY')) define('HSX_LEAD_UPGRADE_LIBRARY_ONLY', true);
require_once __DIR__.'/upgrade.php';

/** Explicit permission-preserving navigation upgrade; no DDL, business writes or new API grants. */
final class MenuGroupsUpgrade
{
    private string $plugin;
    private string $backend;
    private array $flat;
    private array $groups;
    private array $ancestors = [];

    public function __construct(string $plugin, string $backend)
    {
        $this->plugin = $plugin;
        $this->backend = $backend;
        $this->groups = array_keys(require $plugin.'/app/dict/menu/site-groups.php');
        $tree = require $plugin.'/app/dict/menu/site.php';
        $this->flat = array_column((new CoreMenuService())->loadMenu($tree, 'site', 'hsx_lead'), null, 'menu_key');
        if (count($this->groups) !== 6) throw new RuntimeException('必须完整上传本次六组菜单声明');
        foreach ($this->groups as $key) {
            $node = $this->flat[$key] ?? [];
            if (($node['parent_key'] ?? '') !== 'hsx_lead' || (int) ($node['menu_type'] ?? -1) !== 0
                || ($node['api_url'] ?? '') !== '' || ($node['methods'] ?? '') !== '' || ($node['view_path'] ?? '') !== '') {
                throw new RuntimeException('新目录不是纯导航，已阻止权限变更');
            }
        }
        foreach ($this->flat as $key => $node) {
            if (in_array($key, $this->groups, true)) continue;
            $parent = $node['parent_key'];
            while ($parent !== '' && isset($this->flat[$parent])) {
                if (in_array($parent, $this->groups, true)) $this->ancestors[$key][] = $parent;
                $parent = $this->flat[$parent]['parent_key'];
            }
        }
    }

    /** SELECT only: does not create backups/locks or invalidate any cache. */
    public function check(): array
    {
        $snapshot = $this->snapshot(false);
        return $this->summary($snapshot, $this->roleChanges($snapshot));
    }

    public function apply(): array
    {
        $this->check();
        $state = $this->backend.'/runtime/hsx_lead_upgrade';
        $this->directory($state);
        $lockPath = $state.'/menu-groups.lock';
        if (is_link($lockPath)) throw new RuntimeException('升级锁路径异常');
        $lock = fopen($lockPath, 'c');
        if (!$lock) throw new RuntimeException('无法创建菜单升级锁');
        chmod($lockPath, 0600);
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            throw new RuntimeException('菜单升级正在执行，请勿重复运行');
        }
        $backup = '';
        try {
            $result = Db::transaction(function () use ($state, &$backup): array {
                // Framework refresh uses the installed-addon lock as well. Readers see either
                // the old tree+rules or the new tree+rules when this transaction commits.
                $snapshot = $this->snapshot(true);
                $changes = $this->roleChanges($snapshot);
                $summary = $this->summary($snapshot, $changes);
                if (!$summary['pending_directories'] && !$summary['pending_reparents'] && !$changes) {
                    return $summary + ['status' => 'unchanged', 'backup' => ''];
                }
                $backup = $state.'/menu-groups-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
                $this->directory($backup);
                $this->write($backup.'/before.json', ['schema' => 'hsx_lead.menu_groups.v1', 'snapshot' => $snapshot, 'role_changes' => $changes]);
                foreach ($this->groups as $key) {
                    if (isset($snapshot['menus'][$key])) continue;
                    Db::name('sys_menu')->insert($this->flat[$key] + ['source' => 'system']);
                }
                foreach ($snapshot['menus'] as $key => $row) {
                    if (!isset($this->flat[$key]) || !isset($this->ancestors[$key]) || (int) $row['menu_type'] !== 1) continue;
                    // Do not overwrite API, visibility, status, icons or custom business metadata.
                    Db::name('sys_menu')->where('id', $row['id'])->update([
                        'parent_key' => $this->flat[$key]['parent_key'], 'sort' => $this->flat[$key]['sort'],
                    ]);
                }
                foreach ($changes as $id => $change) {
                    Db::name('sys_role')->where('role_id', $id)->update(['rules' => json_encode($change['after'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                }
                $after = $this->snapshot(true);
                foreach ($snapshot['roles'] as $id => $role) {
                    $expected = $changes[$id]['after'] ?? $this->rules($role['rules']);
                    if ($this->rules($after['roles'][$id]['rules'] ?? null) !== $expected) throw new RuntimeException('角色权限读回不一致，事务已回滚');
                    // Existing permissions remain byte-for-byte ordered; only six pure directory
                    // keys may be appended, never a page, button, API or all-data authority.
                    if (array_values(array_diff($expected, $this->groups)) !== array_values(array_diff($this->rules($role['rules']), $this->groups))) {
                        throw new RuntimeException('检测到业务权限变化，事务已回滚');
                    }
                }
                foreach ($snapshot['menus'] as $key => $row) {
                    $expected = $row;
                    if (isset($this->flat[$key], $this->ancestors[$key]) && (int) $row['menu_type'] === 1) {
                        $expected['parent_key'] = $this->flat[$key]['parent_key'];
                        $expected['sort'] = (int) $this->flat[$key]['sort'];
                    }
                    if ($after['menus'][$key] !== $expected) throw new RuntimeException('菜单非预期字段发生变化，事务已回滚');
                }
                if ($this->summary($after, $this->roleChanges($after))['pending_reparents']) throw new RuntimeException('菜单归组未完成，事务已回滚');
                return array_replace($summary, ['pending_directories' => 0, 'pending_reparents' => 0, 'affected_roles' => 0,
                    'created_directories' => $summary['pending_directories'], 'reparented_pages' => $summary['pending_reparents'],
                    'updated_roles' => count($changes), 'status' => 'complete', 'backup' => $backup,
                    'changed_sites' => array_values(array_unique(array_column($changes, 'site_id')))]);
            });
            // The shared menu tag is also part of the framework role-permission cache tags.
            Cache::tag(MenuService::$cache_tag_name)->clear();
            foreach ($result['changed_sites'] ?? [] as $siteId) Cache::tag(RoleService::$cache_tag_name.$siteId)->clear();
            if ($backup !== '') $this->write($backup.'/result.json', $result);
            return $result;
        } catch (Throwable $error) {
            if ($backup !== '') $this->write($backup.'/result.json', ['status' => 'requires_review', 'message' => '请先核对备份与数据库实际结果；勿卸载或覆盖角色权限']);
            throw $error;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function snapshot(bool $lock): array
    {
        $addon = Db::name('addon')->where('key', 'hsx_lead')->lock($lock)->find();
        if (!$addon) throw new RuntimeException('插件未安装；本命令不是安装程序');
        // Also check keys owned by another addon: directory insertion must not collide.
        $rows = Db::name('sys_menu')->where('app_type', 'site')->where(function ($query): void {
            $query->where('addon', 'hsx_lead')->whereOr('menu_key', 'in', $this->groups);
        })->order('id')->lock($lock)->select()->toArray();
        $menus = [];
        foreach ($rows as $row) {
            $key = $row['menu_key'];
            if (isset($menus[$key]) || $row['addon'] !== 'hsx_lead' || $row['source'] !== 'system') throw new RuntimeException('插件菜单重复或含自定义冲突，请先核对：'.$key);
            $menus[$key] = $row;
            if (in_array($key, $this->groups, true) && ((int) $row['menu_type'] !== 0 || $row['api_url'] !== '' || $row['methods'] !== '' || $row['parent_key'] !== 'hsx_lead')) throw new RuntimeException('目录权限发生冲突，未执行更新');
            if (isset($this->flat[$key]) && !in_array($key, $this->groups, true)) {
                $target = $this->flat[$key];
                foreach (['menu_type', 'api_url', 'methods', 'router_path', 'view_path'] as $field) {
                    if ((string) $row[$field] !== (string) $target[$field]) throw new RuntimeException('页面或API声明与安装菜单不同，请先核对后再归组：'.$key);
                }
            }
        }
        if (!isset($menus['hsx_lead'])) throw new RuntimeException('未找到原客资协作菜单，请先核对插件安装状态');
        // site is the tenant's menu entitlement source. Lock it before matching role rows.
        $sites = Db::name('site')->where('app_type', 'site')->order('site_id')->lock($lock)->column('site_id');
        $roles = $sites ? Db::name('sys_role')->whereIn('site_id', $sites)->whereLike('rules', '%hsx_lead%')->order('role_id')->lock($lock)->select()->toArray() : [];
        return ['menus' => $menus, 'roles' => array_column($roles, null, 'role_id')];
    }

    private function rules($value): array
    {
        if ($value === null || $value === '') return [];
        $rules = is_array($value) ? $value : json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($rules) || array_values($rules) !== $rules || array_filter($rules, static fn ($key): bool => !is_string($key))) throw new RuntimeException('角色权限格式异常，未执行更新');
        return $rules;
    }

    private function roleChanges(array $snapshot): array
    {
        $changes = [];
        foreach ($snapshot['roles'] as $id => $role) {
            $before = $this->rules($role['rules']);
            $needed = [];
            foreach ($before as $key) {
                if (!isset($snapshot['menus'][$key])) continue;
                foreach ($this->ancestors[$key] ?? [] as $group) $needed[$group] = true;
            }
            $after = $before;
            foreach ($this->groups as $group) if (isset($needed[$group]) && !in_array($group, $after, true)) $after[] = $group;
            if ($after !== $before) $changes[$id] = ['site_id' => $role['site_id'], 'after' => $after];
        }
        return $changes;
    }

    private function summary(array $snapshot, array $changes): array
    {
        $reparents = 0;
        foreach ($snapshot['menus'] as $key => $row) if (isset($this->ancestors[$key]) && (int) $row['menu_type'] === 1 && $row['parent_key'] !== $this->flat[$key]['parent_key']) $reparents++;
        return ['pending_directories' => count(array_diff($this->groups, array_keys($snapshot['menus']))), 'pending_reparents' => $reparents, 'affected_roles' => count($changes), 'ddl' => false, 'api_permission_changes' => false];
    }

    private function directory(string $path): void
    {
        if (is_link($path) || (!is_dir($path) && !mkdir($path, 0700, true)) || realpath($path) !== $path) throw new RuntimeException('无法创建私有备份目录或其路径含符号链接');
        if (!chmod($path, 0700)) throw new RuntimeException('无法保护私有备份目录');
    }

    private function write(string $path, array $data): void
    {
        $handle = fopen($path, 'x');
        if (!$handle) throw new RuntimeException('无法创建私有备份；数据库未提交，请先核对');
        try {
            if (!chmod($path, 0600)) throw new RuntimeException('无法保护私有备份文件');
            $text = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (fwrite($handle, $text) !== strlen($text) || !fflush($handle)) throw new RuntimeException('私有备份写入不完整，数据库未提交');
        } finally {
            fclose($handle);
        }
    }
}

function menuGroupsMain(array $args): int
{
    if (PHP_SAPI !== 'cli' || PHP_VERSION_ID < 80000) return 1;
    if (count($args) !== 1 || !in_array($args[0], ['--check', '--apply'], true)) {
        fwrite(STDERR, "php addon/hsx_lead/scripts/upgrade-menu-groups.php --check|--apply\n");
        return 2;
    }
    try {
        $plugin = dirname(__DIR__);
        $backend = dirname($plugin, 2);
        bootstrap($backend);
        $command = new MenuGroupsUpgrade($plugin, $backend);
        $result = $args[0] === '--check' ? $command->check() : $command->apply();
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
        echo $args[0] === '--check' ? "[只读] 未修改菜单、角色、数据表或缓存。\n" : "[完成] 仅归组本站管理菜单、补齐原已授权页面所需目录；未新增页面/API权限。请重新登录后台。\n";
        return 0;
    } catch (Throwable $error) {
        fwrite(STDERR, '[失败] '.(get_class($error) === RuntimeException::class ? $error->getMessage() : '菜单升级失败，已隐藏底层连接信息；请核对私有日志与备份，勿卸载重装').PHP_EOL);
        return 1;
    }
}
if (!defined('HSX_LEAD_MENU_GROUPS_LIBRARY_ONLY')) exit(menuGroupsMain(array_slice($_SERVER['argv'] ?? [], 1)));
