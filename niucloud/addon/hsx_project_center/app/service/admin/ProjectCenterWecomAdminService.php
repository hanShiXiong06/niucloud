<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\admin;

use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use app\model\addon\Addon;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Cache;

/**
 * 项目中心只消费企微能力，不复制企微鉴权实现。
 * hsx_wecom 未安装或未启用时返回明确配置状态，保证两个插件仍可独立升级。
 */
final class ProjectCenterWecomAdminService extends BaseAdminService
{
    private const CONFIG_SERVICE = 'addon\\hsx_wecom\\app\\service\\core\\WecomConfigService';
    private const CLIENT = 'addon\\hsx_wecom\\app\\service\\core\\WecomClient';
    private const STAFF_SERVICE = 'addon\\hsx_wecom\\app\\service\\admin\\WecomStaffService';

    public function readiness(): array
    {
        $addon = Addon::where([['key', '=', 'hsx_wecom'], ['status', '=', 1]])->findOrEmpty();
        if ($addon->isEmpty() || !class_exists(self::CONFIG_SERVICE) || !class_exists(self::CLIENT)) {
            return [
                'ready' => 0,
                'reason' => '请先安装并启用企业微信协同插件',
                'current_uid' => (int)$this->uid,
                'current_bound' => 0,
                'current_wecom_userid' => '',
                'can_view_all' => (new ProjectCenterGroupAdminService())->canViewAll() ? 1 : 0,
                'staff' => [],
            ];
        }

        $configService = self::CONFIG_SERVICE;
        $config = (new $configService())->get((int)$this->site_id, true);
        $ready = !empty($config['enabled'])
            && trim((string)($config['corp_id'] ?? '')) !== ''
            && (int)($config['agent_id'] ?? 0) > 0
            && !empty($config['secret_configured'])
            && trim((string)($config['web_base_url'] ?? '')) !== '';
        $staff = [];
        if (class_exists(self::STAFF_SERVICE)) {
            $staffService = self::STAFF_SERVICE;
            $staff = (new $staffService())->lists();
        }
        $current = [];
        foreach ($staff as $item) {
            if ((int)($item['uid'] ?? 0) === (int)$this->uid) {
                $current = $item;
                break;
            }
        }
        return [
            'ready' => $ready ? 1 : 0,
            'reason' => $ready ? '' : '请先在企业微信协同中启用应用，并完整配置企业 ID、AgentId、Secret 和网页管理端地址',
            'current_uid' => (int)$this->uid,
            'corp_id' => (string)($config['corp_id'] ?? ''),
            'agent_id' => (int)($config['agent_id'] ?? 0),
            'current_bound' => !empty($current['bound']) && (int)($current['status'] ?? 0) === 1 ? 1 : 0,
            'current_wecom_userid' => (string)($current['wecom_userid'] ?? ''),
            'can_view_all' => (new ProjectCenterGroupAdminService())->canViewAll() ? 1 : 0,
            'staff' => $staff,
        ];
    }

    public function oauthUrl(string $redirectUri): array
    {
        $this->assertReady();
        $redirectUri = trim($redirectUri);
        $state = 'pcbind_' . bin2hex(random_bytes(12));
        Cache::set($this->oauthStateKey($state), [
            'site_id' => (int)$this->site_id,
            'uid' => (int)$this->uid,
        ], 600);
        $configService = self::CONFIG_SERVICE;
        $clientClass = self::CLIENT;
        $config = (new $configService())->get((int)$this->site_id);
        return [
            'url' => (new $clientClass())->oauthAuthorizeUrl($config, $redirectUri, $state),
            'state' => $state,
        ];
    }

    public function bindCurrent(string $code, string $state): array
    {
        $this->assertReady();
        $state = trim($state);
        $expected = (array)Cache::get($this->oauthStateKey($state), []);
        if ((int)($expected['site_id'] ?? 0) !== (int)$this->site_id || (int)($expected['uid'] ?? 0) !== (int)$this->uid) {
            throw new CommonException('企业微信身份绑定已过期，请重新发起');
        }
        Cache::delete($this->oauthStateKey($state));
        $configService = self::CONFIG_SERVICE;
        $clientClass = self::CLIENT;
        $config = (new $configService())->get((int)$this->site_id);
        $userId = (new $clientClass())->userIdByOauthCode($config, $code);
        $staffService = self::STAFF_SERVICE;
        (new $staffService())->save((int)$this->uid, ['wecom_userid' => $userId, 'status' => 1]);
        return ['uid' => (int)$this->uid, 'wecom_userid' => $userId, 'bound' => 1];
    }

    /**
     * 原生选客发生在群编号生成之前，因此单独提供当前页面的 JS-SDK 配置。
     * 业务页面不接触企微 Secret，也不需要先创建一条空的群台账。
     */
    public function selectorConfig(string $url): array
    {
        $this->assertReady();
        $url = trim($url);
        if ($url === '') throw new CommonException('缺少当前页面地址，无法初始化企业微信选客能力');

        $configService = self::CONFIG_SERVICE;
        $clientClass = self::CLIENT;
        $config = (new $configService())->get((int)$this->site_id);

        return [
            'sdk' => (new $clientClass())->jsSdkConfig($config, $url),
        ];
    }

    /** 生成建群参数。客户 external_userid 可由前端企微选人后再回传。 */
    public function prepare(int $id, string $url): array
    {
        $this->assertReady();
        $group = $this->group($id);
        if (in_array((string)$group->status, ['abandoned', 'dissolved', 'completed'], true)) {
            throw new CommonException('当前群流程已结束，不能再次建群');
        }

        $uids = array_values(array_unique(array_filter(array_merge(
            [(int)$group->owner_uid],
            array_map('intval', (array)$group->collaborator_uids)
        ))));
        if ($uids === []) throw new CommonException('请先选择群负责人');

        $bindingModel = 'addon\\hsx_wecom\\app\\model\\WecomStaffBinding';
        $bindings = $bindingModel::where([['site_id', '=', $this->site_id], ['uid', 'in', $uids], ['status', '=', 1]])
            ->column('wecom_userid', 'uid');
        $missing = array_values(array_filter($uids, static fn(int $uid): bool => trim((string)($bindings[$uid] ?? '')) === ''));
        if ($missing !== []) {
            throw new CommonException('负责人或协作员工尚未绑定企业微信 UserID，请先完成员工绑定');
        }

        $wecomUserIds = [];
        foreach ($uids as $uid) {
            $wecomUserIds[] = trim((string)$bindings[$uid]);
        }

        $configService = self::CONFIG_SERVICE;
        $clientClass = self::CLIENT;
        $config = (new $configService())->get((int)$this->site_id);
        $groupName = $this->groupName($group);

        return [
            'group_id' => (int)$group->id,
            'group_no' => (string)$group->group_no,
            'group_name' => $groupName,
            'chat_id' => (string)$group->wecom_chat_id,
            'external_userid' => (string)$group->wecom_external_userid,
            'user_ids' => $wecomUserIds,
            'sdk' => (new $clientClass())->jsSdkConfig($config, $url),
        ];
    }

    public function complete(int $id, array $data): array
    {
        $chatId = trim((string)($data['wecom_chat_id'] ?? ''));
        if ($chatId === '') throw new CommonException('企业微信未返回群聊 Chat ID，请重新建群');
        $externalUserId = trim((string)($data['wecom_external_userid'] ?? ''));

        (new ProjectCenterGroupAdminService())->bindWecom($id, [
            'wecom_chat_id' => $chatId,
            'create_mode' => 'wecom',
        ]);
        $group = $this->group($id);
        $group->save([
            'wecom_external_userid' => mb_substr($externalUserId, 0, 120),
            'error_message' => '',
            'update_at' => time(),
        ]);

        $group = $this->group($id);
        return [
            'group' => $group->toArray(),
            'material' => $this->material($group),
        ];
    }

    public function failed(int $id, string $message): bool
    {
        $group = $this->group($id);
        if ((string)$group->wecom_chat_id !== '') return true;
        // 企微失败回调只能影响尚在建群阶段的台账。已经开始办理或已经结束的
        // 普通微信群，不能被一次外部 SDK 失败重新降级为 create_failed。
        if (!in_array((string)$group->status, ['reserved', 'create_failed'], true)) return true;
        ProjectCenterGroup::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
            ['wecom_chat_id', '=', ''],
            ['status', 'in', ['reserved', 'create_failed']],
        ])->update([
            'status' => 'create_failed',
            'create_mode' => 'wecom',
            'error_message' => mb_substr(trim($message) ?: '企业微信建群未完成', 0, 500),
            'update_at' => time(),
        ]);
        // 条件更新未命中表示群状态已被其他业务推进，不应再回退，也不算接口失败。
        return true;
    }

    private function assertReady(): void
    {
        $state = $this->readiness();
        if (empty($state['ready'])) throw new CommonException((string)$state['reason']);
    }

    private function oauthStateKey(string $state): string
    {
        return 'hsx_project_center_wecom_oauth_' . hash('sha256', $state);
    }

    private function group(int $id): ProjectCenterGroup
    {
        $group = ProjectCenterGroup::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($group->isEmpty()) throw new CommonException('群编号不存在');
        (new ProjectCenterGroupAdminService())->assertCanAccess($group);
        return $group;
    }

    private function groupName(ProjectCenterGroup $group): string
    {
        $customer = trim((string)$group->store_name) ?: trim((string)$group->member_name);
        $name = trim((string)$group->group_no . ($customer !== '' ? '-' . $customer : ''));
        return mb_substr($name, 0, 50);
    }

    private function material(ProjectCenterGroup $group): array
    {
        $project = ProjectCenterProject::where([['site_id', '=', $this->site_id], ['id', '=', $group->project_id]])
            ->field('id,title')->findOrEmpty()->toArray();
        $path = '/wap/' . (int)$this->site_id . '/addon/hsx_project_center/pages/project/detail?id=' . (int)$group->project_id
            . '&group_no=' . rawurlencode((string)$group->group_no);
        $text = "【群编号】{$group->group_no}\n"
            . '【办理项目】' . (string)($project['title'] ?? '') . "\n"
            . "请先阅读项目说明；付款二维码由工作人员在本群发送。付款后请把完整流水截图发到群内，再填写资料表单。";
        return [
            'title' => (string)($project['title'] ?? ''),
            'group_no' => (string)$group->group_no,
            'path' => $path,
            'text' => $text,
        ];
    }
}
