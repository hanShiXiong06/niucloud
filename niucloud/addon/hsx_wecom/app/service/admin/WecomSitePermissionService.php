<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\admin;

use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/** 敏感绑定操作不能依赖“菜单未导入则放行”的框架默认行为。 */
final class WecomSitePermissionService extends BaseAdminService
{
    public function assertApi(string $method, string $api): void
    {
        if ((int)$this->site_id <= 0 || (int)$this->uid <= 0) throw new CommonException('请先登录具体站点后操作');
        $user = SysUser::where([['uid', '=', (int)$this->uid], ['status', '=', 1]])->findOrEmpty();
        if ($user->isEmpty()) throw new CommonException('当前员工账号已停用，请重新登录');
        // 与框架保持一致：平台超级管理员可管理已选择站点。
        if (AuthService::isSuperAdmin()) return;
        $role = SysUserRole::where([
            ['site_id', '=', (int)$this->site_id], ['uid', '=', (int)$this->uid], ['status', '=', 1],
        ])->findOrEmpty();
        if ($role->isEmpty()) throw new CommonException('当前账号没有本站有效管理身份');
        if ((int)$role->is_admin === 1) return;
        $permissions = (new AuthService())->getAuthApiList();
        $allowed = is_array($permissions[strtolower($method)] ?? null) ? $permissions[strtolower($method)] : [];
        if (!in_array($api, $allowed, true)) {
            throw new CommonException('没有此企业微信操作权限，请让站点管理员检查角色授权；覆盖升级后还需导入菜单权限');
        }
    }
}
