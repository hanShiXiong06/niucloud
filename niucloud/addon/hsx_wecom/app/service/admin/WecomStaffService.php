<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\admin;

use addon\hsx_wecom\app\model\WecomStaffBinding;
use addon\hsx_wecom\app\service\core\WecomConfigService;
use addon\hsx_wecom\app\service\core\WecomProviderAuthorizationService;
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
                'open_userid' => (string)($binding['open_userid'] ?? ''),
                'id_scope' => (string)($binding['id_scope'] ?? 'legacy'),
                'bind_source' => (string)($binding['bind_source'] ?? 'manual'),
                'verified_at' => (int)($binding['verified_at'] ?? 0),
                'status' => isset($binding['status']) ? (int)$binding['status'] : 1,
                'bound' => !empty($binding['wecom_userid']),
                'is_current_user' => $uid === (int)$this->uid,
            ];
        }
        return $result;
    }

    public function save(int $uid, array $data): bool
    {
        $exists = SysUserRole::where([['site_id', '=', $this->site_id], ['uid', '=', $uid]])->findOrEmpty();
        if ($exists->isEmpty()) throw new CommonException('该员工不属于当前站点');
        $userId = trim((string)($data['wecom_userid'] ?? ''));
        $where = [['site_id', '=', $this->site_id], ['uid', '=', $uid]];
        $binding = WecomStaffBinding::where($where)->findOrEmpty();
        $siteConfig = (new WecomConfigService())->get((int)$this->site_id);
        if ((string)($siteConfig['connection_mode'] ?? 'self_built') === 'provider') {
            if ($binding->isEmpty() || !in_array((string)$binding->id_scope, ['open_userid', 'userid'], true)) {
                throw new CommonException('服务商模式不支持手工填写 UserID，请让该员工完成企业微信授权绑定');
            }
            if ($userId !== '' && $userId !== trim((string)$binding->wecom_userid)) {
                throw new CommonException('服务商模式的员工身份由企业微信返回，不能手工修改');
            }
            $binding->save([
                'status' => (int)!empty($data['status']),
                'update_at' => time(),
            ]);
            return true;
        }
        $duplicate = WecomStaffBinding::where([['site_id', '=', $this->site_id], ['wecom_userid', '=', $userId]])
            ->where('uid', '<>', $uid)->findOrEmpty();
        if ($userId !== '' && !$duplicate->isEmpty()) throw new CommonException('该企业微信账号已绑定其他员工');
        $sameProviderIdentity = !$binding->isEmpty()
            && (string)$binding->id_scope === 'provider'
            && trim((string)$binding->wecom_userid) === $userId;
        $save = [
            'wecom_userid' => $userId,
            'status' => (int)!empty($data['status']),
            'update_at' => time(),
        ];
        if (!$sameProviderIdentity) {
            $save = array_merge($save, [
                'corp_authorization_id' => 0,
                'open_userid' => '',
                'id_scope' => 'legacy',
                'bind_source' => 'manual',
                'verified_at' => 0,
            ]);
        }
        if ($binding->isEmpty()) {
            WecomStaffBinding::create(array_merge(['site_id' => $this->site_id, 'uid' => $uid, 'create_at' => time()], $save));
        } else {
            $binding->save($save);
        }
        return true;
    }

    public function bindUrl(int $uid, string $returnUrl = ''): array
    {
        $exists = SysUserRole::where([['site_id', '=', $this->site_id], ['uid', '=', $uid]])->findOrEmpty();
        if ($exists->isEmpty()) throw new CommonException('该员工不属于当前站点');
        return (new WecomProviderAuthorizationService())->memberBindUrl((int)$this->site_id, $uid, $returnUrl);
    }
}
