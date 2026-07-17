<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\admin;

use addon\hsx_wecom\app\model\WecomStaffBinding;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class WecomStaffService extends BaseAdminService
{
    public function lists(): array
    {
        $roles = SysUserRole::where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->field('uid,is_admin,role_ids')->order('is_admin desc,id asc')->select()->toArray();
        $uids = array_values(array_unique(array_map('intval', array_column($roles, 'uid'))));
        if ($uids === []) return [];
        $users = SysUser::whereIn('uid', $uids)->where('status', '=', 1)
            ->field('uid,username,real_name,mobile,status')->select()->toArray();
        $userMap = [];
        foreach ($users as $row) $userMap[(int)$row['uid']] = $row;
        $bindings = WecomStaffBinding::where('site_id', '=', $this->site_id)->select()->toArray();
        $bindingMap = [];
        foreach ($bindings as $row) $bindingMap[(int)$row['uid']] = $row;
        $result = [];
        foreach ($roles as $role) {
            $uid = (int)$role['uid'];
            $user = $userMap[$uid] ?? [];
            if ($user === []) continue;
            $binding = $bindingMap[$uid] ?? [];
            $username = trim((string)($user['username'] ?? ''));
            $realName = trim((string)($user['real_name'] ?? ''));
            $result[] = [
                'uid' => $uid,
                'name' => $realName !== '' ? $realName : ($username !== '' ? $username : ('员工' . $uid)),
                'username' => $username,
                'mobile' => (string)($user['mobile'] ?? ''),
                'wecom_userid' => (string)($binding['wecom_userid'] ?? ''),
                'status' => isset($binding['status']) ? (int)$binding['status'] : 1,
                'bound' => !empty($binding['wecom_userid']),
            ];
        }
        return $result;
    }

    public function save(int $uid, array $data): bool
    {
        $exists = SysUserRole::where([['site_id', '=', $this->site_id], ['uid', '=', $uid]])->findOrEmpty();
        if ($exists->isEmpty()) throw new CommonException('该员工不属于当前站点');
        $userId = trim((string)($data['wecom_userid'] ?? ''));
        $duplicate = WecomStaffBinding::where([['site_id', '=', $this->site_id], ['wecom_userid', '=', $userId]])
            ->where('uid', '<>', $uid)->findOrEmpty();
        if ($userId !== '' && !$duplicate->isEmpty()) throw new CommonException('该企业微信账号已绑定其他员工');
        $where = [['site_id', '=', $this->site_id], ['uid', '=', $uid]];
        $binding = WecomStaffBinding::where($where)->findOrEmpty();
        $save = [
            'wecom_userid' => $userId,
            'status' => (int)!empty($data['status']),
            'update_at' => time(),
        ];
        if ($binding->isEmpty()) {
            WecomStaffBinding::create(array_merge(['site_id' => $this->site_id, 'uid' => $uid, 'create_at' => time()], $save));
        } else {
            $binding->save($save);
        }
        return true;
    }
}
