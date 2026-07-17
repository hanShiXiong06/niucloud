<?php
declare(strict_types=1);

namespace addon\hsx_performance\app\service\admin;

use addon\hsx_performance\app\service\core\PerformanceConfigService;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;

final class PerformanceConfigAdminService extends BaseAdminService
{
    public function info(): array
    {
        $config = (new PerformanceConfigService())->get($this->site_id);
        $uids = SysUserRole::where([['site_id', '=', $this->site_id], ['delete_time', '=', 0]])->column('uid');
        $users = empty($uids) ? [] : SysUser::whereIn('uid', array_values(array_unique(array_map('intval', $uids))))
            ->where('delete_time', '=', 0)->field('uid,username,real_name')->order('uid asc')->select()->toArray();
        $config['staff_options'] = array_map(static fn(array $user): array => [
            'uid' => (int)$user['uid'],
            'name' => (string)($user['real_name'] ?: $user['username'] ?: ('员工#' . $user['uid'])),
        ], $users);
        return $config;
    }

    public function save(array $data): array
    {
        (new PerformanceConfigService())->save($this->site_id, $data);
        return $this->info();
    }
}
