<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\service;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminService;
use core\exception\CommonException;
final class PermissionService extends BaseAdminService
{
    public function assertApi(string $method, string $api): void
    {
        if ((int)$this->site_id <= 0 || (int)$this->uid <= 0) throw new CommonException('请先登录具体站点');
        if (!SysUser::where('uid', $this->uid)->where('status', 1)->count()) throw new CommonException('员工账号已停用');
        if (!(new ConfigService())->installed((int)$this->site_id)) throw new CommonException('本站未安装精斗云插件及ERP依赖');
        if (AuthService::isSuperAdmin()) return;
        $role = SysUserRole::where('site_id', $this->site_id)->where('uid', $this->uid)->where('status', 1)->findOrEmpty();
        if ($role->isEmpty()) throw new CommonException('没有本站管理权限');
        if ((int)$role->is_admin === 1) return;
        $permissions = (new AuthService())->getAuthApiList();
        if (!in_array($api, $permissions[strtolower($method)] ?? [], true)) throw new CommonException('没有此精斗云操作权限，请检查角色授权');
    }
}
