<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use app\model\sys\{SysUser,SysUserRole};
use app\service\admin\auth\AuthService;
use core\base\BaseAdminService;
use core\exception\CommonException;
final class PermissionService extends BaseAdminService
{
    public function assertApi(string $method,string $api): void
    {
        if ($this->site_id<=0 || $this->uid<=0 || !SysUser::where('uid',$this->uid)->where('status',1)->count()) throw new CommonException('请登录本站有效员工账号');
        if (!(new ConfigService())->installed((int)$this->site_id)) throw new CommonException('本站需安装ERP及博远插件');
        if (AuthService::isSuperAdmin()) return;
        $role=SysUserRole::where('site_id',$this->site_id)->where('uid',$this->uid)->where('status',1)->findOrEmpty();
        if ($role->isEmpty()) throw new CommonException('没有本站管理权限');
        if ((int)$role->is_admin===1) return;
        if (!in_array($api,(new AuthService())->getAuthApiList()[strtolower($method)]??[],true)) throw new CommonException('没有该博远操作权限');
    }
}
