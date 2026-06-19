<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\RolePresetDict;
use app\model\sys\SysRole;
use app\service\admin\sys\RoleService;
use core\base\BaseAdminService;

/**
 * 推荐角色「一键生成」。
 *
 * 不入侵框架：仅调用框架 RoleService 在本站点创建/更新角色(sys_role)。
 * 幂等：按角色名匹配——已存在则更新其权限点(合并/覆盖可选)，不存在则新建。
 * 生成后管理员仍可在后台「角色管理」自由调整。
 */
class ErpRolePresetService extends BaseAdminService
{
    /** 预览：返回各推荐角色及其权限点数量、是否已存在 */
    public function preview(): array
    {
        $existing = SysRole::where([['site_id', '=', $this->site_id]])->column('role_name');
        $existing = array_map('strval', $existing);
        $out = [];
        foreach (RolePresetDict::all() as $p) {
            $keys = $this->resolveKeys($p);
            $out[] = [
                'role_name' => $p['role_name'],
                'desc'      => $p['desc'],
                'key_count' => count($keys),
                'exists'    => in_array($p['role_name'], $existing, true),
            ];
        }
        return $out;
    }

    /**
     * 生成/更新推荐角色。
     * @param bool $overwrite 已存在的角色是否用预设覆盖其权限点(默认 true)；false 则跳过已存在的
     * @return array ['created'=>[], 'updated'=>[], 'skipped'=>[]]
     */
    public function generate(bool $overwrite = true): array
    {
        $roleService = new RoleService();
        $created = [];
        $updated = [];
        $skipped = [];

        foreach (RolePresetDict::all() as $p) {
            $name = (string)$p['role_name'];
            $keys = $this->resolveKeys($p);
            $role = SysRole::where([['site_id', '=', $this->site_id], ['role_name', '=', $name]])->findOrEmpty();

            if ($role->isEmpty()) {
                $roleService->add([
                    'role_name' => $name,
                    'rules'     => $keys,
                    'status'    => 1,
                ]);
                $created[] = $name;
            } elseif ($overwrite) {
                $roleService->edit((int)$role->role_id, [
                    'role_name' => $name,
                    'rules'     => $keys,
                    'status'    => (int)$role->status === 0 ? 0 : 1,
                ]);
                $updated[] = $name;
            } else {
                $skipped[] = $name;
            }
        }

        return ['created' => $created, 'updated' => $updated, 'skipped' => $skipped];
    }

    /** 解析某预设的权限点：all_erp=true 时并入本插件全部权限点 */
    private function resolveKeys(array $preset): array
    {
        $keys = (array)($preset['keys'] ?? []);
        if (!empty($preset['all_erp'])) {
            $keys = array_merge($keys, $this->allErpMenuKeys());
        }
        return array_values(array_unique(array_filter(array_map('strval', $keys))));
    }

    /** 从本插件菜单字典收集全部 hsx_erp 权限点(不依赖数据库) */
    private function allErpMenuKeys(): array
    {
        $path = dirname(__DIR__, 2) . '/dict/menu/site.php';
        $tree = is_file($path) ? include $path : [];
        $keys = [];
        $walk = function ($nodes) use (&$walk, &$keys) {
            if (!is_array($nodes)) {
                return;
            }
            foreach ($nodes as $n) {
                if (!empty($n['menu_key'])) {
                    $keys[] = (string)$n['menu_key'];
                }
                if (!empty($n['children'])) {
                    $walk($n['children']);
                }
            }
        };
        $walk(is_array($tree) ? $tree : []);
        return array_values(array_unique($keys));
    }
}
