<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;

class ErpStaffService extends BaseAdminService
{
    public function options(array $where = []): array
    {
        $keyword = trim((string)($where['keyword'] ?? ''));
        $uids = SysUserRole::where([
            ['site_id', '=', $this->site_id],
            ['delete_time', '=', 0],
        ])->column('uid');
        if (empty($uids)) {
            $uids = [(int)$this->uid];
        }
        $query = SysUser::whereIn('uid', array_values(array_unique(array_map('intval', $uids))))
            ->where('delete_time', '=', 0);
        if ($keyword !== '') {
            $query->whereLike('username|real_name', '%' . $keyword . '%');
        }
        $users = $query->field('uid,username,real_name')
            ->order('uid asc')
            ->limit(100)
            ->select()
            ->toArray();

        return [
            'current_uid' => (int)$this->uid,
            'users' => array_map(function ($user) {
                $name = (string)($user['real_name'] ?: $user['username'] ?: ('员工#' . $user['uid']));
                return [
                    'uid' => (int)$user['uid'],
                    'username' => (string)$user['username'],
                    'real_name' => (string)$user['real_name'],
                    'mobile' => (string)($user['mobile'] ?? ''),
                    'name' => $name,
                ];
            }, $users),
        ];
    }

    public function resolve(int $uid, string $label = '员工'): array
    {
        $uid = $uid > 0 ? $uid : (int)$this->uid;
        $relation = SysUserRole::where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $uid],
            ['delete_time', '=', 0],
        ])->findOrEmpty();
        if ($relation->isEmpty() && $uid !== (int)$this->uid) {
            throw new CommonException($label . '不属于当前站点');
        }
        $user = SysUser::where([
            ['uid', '=', $uid],
            ['delete_time', '=', 0],
        ])->field('uid,username,real_name')->findOrEmpty();
        if ($user->isEmpty()) {
            throw new CommonException($label . '不存在或已停用');
        }
        return [
            'uid' => (int)$user->uid,
            'name' => (string)($user->real_name ?: $user->username ?: ('员工#' . $user->uid)),
        ];
    }
}
