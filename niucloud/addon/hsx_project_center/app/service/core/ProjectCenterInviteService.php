<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterInvite;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterRelationLog;
use app\model\member\Member;
use core\exception\CommonException;
use think\facade\Db;

/** 邀请凭证与会员推荐关系绑定。关系本体复用框架 member.pid，插件只保存审计日志。 */
final class ProjectCenterInviteService
{
    public function create(int $siteId, int $projectId, int $inviterMemberId): array
    {
        $project = $this->enabledProject($siteId, $projectId);
        $capability = (new ProjectCenterDistributionRuleService())->memberCapability($siteId, $inviterMemberId, 1, $project);
        if (empty($capability['eligible'])) throw new CommonException((string)$capability['reason']);

        $row = ProjectCenterInvite::where([
            ['site_id', '=', $siteId], ['project_id', '=', $projectId],
            ['inviter_member_id', '=', $inviterMemberId],
        ])->findOrEmpty();
        $now = time();
        if ($row->isEmpty()) {
            $row = ProjectCenterInvite::create([
                'site_id' => $siteId, 'project_id' => $projectId,
                'inviter_member_id' => $inviterMemberId, 'token' => $this->token(),
                'status' => 1, 'use_count' => 0, 'last_used_at' => 0,
                'create_at' => $now, 'update_at' => $now,
            ]);
        } elseif ((int)$row->status !== 1) {
            $row->save(['status' => 1, 'token' => $this->token(), 'update_at' => $now]);
        }
        return [
            'token' => (string)$row->token, 'project_id' => $projectId,
            // 小程序码 scene 最多 32 个可见字符。完整随机 token 继续保留用于兼容
            // 已经发出的链接，新增短签名凭证专供分享链接与项目海报使用。
            'share_code' => $this->compactCode($row),
            'project_title' => (string)$project->title,
            'inviter_member_id' => $inviterMemberId,
            'level_name' => (string)$capability['level_name'],
            'rule' => (new ProjectCenterDistributionRuleService())->publicSummary($project->toArray()),
        ];
    }

    public function bind(int $siteId, int $projectId, int $memberId, string $token): array
    {
        $token = trim($token);
        if ($token === '') return ['bound' => 0, 'state' => 'no_invite', 'message' => '未携带邀请信息'];
        $project = $this->enabledProject($siteId, $projectId);

        $result = Db::transaction(function () use ($siteId, $projectId, $memberId, $token, $project) {
            $invite = $this->findActive($siteId, $projectId, $token, true);
            $inviterId = (int)$invite->inviter_member_id;
            if ($inviterId === $memberId) {
                return [
                    '__reject' => 1, 'invite_id' => (int)$invite->id,
                    'inviter_member_id' => $inviterId, 'remark' => '不能绑定自己为推荐人',
                    'exception' => '不能通过自己的邀请链接建立推荐关系',
                ];
            }
            $capability = (new ProjectCenterDistributionRuleService())->memberCapability($siteId, $inviterId, 1, $project);
            if (empty($capability['eligible'])) throw new CommonException('分享人的推广资格当前不可用：' . (string)$capability['reason']);

            $member = Member::where([
                ['site_id', '=', $siteId], ['member_id', '=', $memberId], ['status', '=', 1],
            ])->field('member_id,pid')->lock(true)->findOrEmpty();
            if ($member->isEmpty()) throw new CommonException('当前会员不存在或已停用');
            $currentPid = (int)$member->pid;
            if ($currentPid > 0) {
                $same = $currentPid === $inviterId;
                $this->log($siteId, $projectId, $memberId, $inviterId, (int)$invite->id, $same ? 'keep' : 'reject',
                    $same ? '推荐关系已经存在，无需重复绑定' : '会员已有推荐人，按首次有效关系保留');
                return [
                    'bound' => $same ? 1 : 0, 'state' => $same ? 'already_bound' : 'kept_existing',
                    'inviter_member_id' => $currentPid,
                    'message' => $same ? '推荐关系已绑定' : '你已有推荐人，本次邀请不会覆盖原关系',
                ];
            }
            if ($this->wouldCreateCycle($siteId, $memberId, $inviterId)) {
                return [
                    '__reject' => 1, 'invite_id' => (int)$invite->id,
                    'inviter_member_id' => $inviterId, 'remark' => '检测到循环推荐关系',
                    'exception' => '该邀请会形成循环推荐关系，不能绑定',
                ];
            }
            $member->save(['pid' => $inviterId]);
            $invite->save([
                'use_count' => (int)$invite->use_count + 1,
                'last_used_at' => time(), 'update_at' => time(),
            ]);
            $this->log($siteId, $projectId, $memberId, $inviterId, (int)$invite->id, 'bind', '客户通过项目分享入口首次绑定推荐关系');
            return [
                'bound' => 1, 'state' => 'bound', 'inviter_member_id' => $inviterId,
                'message' => '邀请关系已确认',
            ];
        });
        // 拒绝日志必须在绑定事务结束后单独落库，否则抛出异常会把审计事实一并回滚。
        if (!empty($result['__reject'])) {
            $this->log(
                $siteId, $projectId, $memberId, (int)$result['inviter_member_id'],
                (int)$result['invite_id'], 'reject', (string)$result['remark']
            );
            throw new CommonException((string)$result['exception']);
        }
        return $result;
    }

    /**
     * 同时兼容历史完整 token 与海报/小程序码使用的短签名凭证。
     * 短凭证中只包含项目、邀请记录的 base36 标识和不可伪造签名，不暴露会员 ID。
     */
    public function findActive(int $siteId, int $projectId, string $credential, bool $lock = false): ProjectCenterInvite
    {
        $credential = trim($credential);
        $query = ProjectCenterInvite::where([
            ['site_id', '=', $siteId], ['project_id', '=', $projectId], ['status', '=', 1],
        ]);

        if (preg_match('/^c_([0-9a-z]+)_([0-9a-z]+)_([a-f0-9]{10})$/', $credential, $matched)) {
            $credentialProjectId = (int)base_convert($matched[1], 36, 10);
            $inviteId = (int)base_convert($matched[2], 36, 10);
            if ($credentialProjectId !== $projectId || $inviteId <= 0) {
                throw new CommonException('邀请信息无效或已失效，请让分享人重新发送');
            }
            $query->where('id', '=', $inviteId);
        } else {
            $query->where('token', '=', $credential);
        }

        if ($lock) $query->lock(true);
        $invite = $query->findOrEmpty();
        if ($invite->isEmpty()) throw new CommonException('邀请信息无效或已失效，请让分享人重新发送');

        if (str_starts_with($credential, 'c_') && !hash_equals($this->compactCode($invite), $credential)) {
            throw new CommonException('邀请信息无效或已失效，请让分享人重新发送');
        }
        return $invite;
    }

    private function enabledProject(int $siteId, int $projectId): ProjectCenterProject
    {
        $project = ProjectCenterProject::where([
            ['site_id', '=', $siteId], ['id', '=', $projectId],
            ['status', '=', ProjectCenterDict::PROJECT_ENABLED], ['distribution_enabled', '=', 1],
        ])->findOrEmpty();
        if ($project->isEmpty()) throw new CommonException('该项目暂未开启邀请分佣');
        return $project;
    }

    private function wouldCreateCycle(int $siteId, int $memberId, int $inviterId): bool
    {
        $cursor = $inviterId;
        $visited = [];
        for ($depth = 0; $depth < 50 && $cursor > 0; $depth++) {
            if ($cursor === $memberId || isset($visited[$cursor])) return true;
            $visited[$cursor] = true;
            $cursor = (int)Member::where([
                ['site_id', '=', $siteId], ['member_id', '=', $cursor],
            ])->value('pid');
        }
        return false;
    }

    private function log(int $siteId, int $projectId, int $memberId, int $inviterId, int $inviteId, string $action, string $remark): void
    {
        ProjectCenterRelationLog::create([
            'site_id' => $siteId, 'project_id' => $projectId, 'member_id' => $memberId,
            'inviter_member_id' => $inviterId, 'invite_id' => $inviteId,
            'action' => $action, 'source' => 'project_share', 'remark' => mb_substr($remark, 0, 500),
            'operator_type' => 'member', 'operator_id' => $memberId, 'operator_name' => '', 'create_at' => time(),
        ]);
    }

    private function token(): string
    {
        return bin2hex(random_bytes(20));
    }

    private function compactCode(ProjectCenterInvite $invite): string
    {
        $projectPart = base_convert((string)(int)$invite->project_id, 10, 36);
        $invitePart = base_convert((string)(int)$invite->id, 10, 36);
        $signature = substr(hash_hmac(
            'sha256',
            (int)$invite->site_id . ':' . (int)$invite->project_id . ':' . (int)$invite->id,
            (string)$invite->token
        ), 0, 10);
        return 'c_' . $projectPart . '_' . $invitePart . '_' . $signature;
    }
}
