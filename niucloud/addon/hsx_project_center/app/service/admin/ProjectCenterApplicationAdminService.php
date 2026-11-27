<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\admin;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterReviewLog;
use addon\hsx_project_center\app\service\core\ProjectCenterGroupService;
use addon\hsx_project_center\app\service\core\ProjectCenterReviewService;
use app\model\member\Member;
use app\model\sys\SysUserRole;
use app\service\admin\auth\AuthService;
use app\service\core\diy_form\CoreDiyFormRecordsService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Log;

final class ProjectCenterApplicationAdminService extends BaseAdminService
{
    public function page(array $where): array
    {
        $query = ProjectCenterApplication::where('site_id', '=', $this->site_id);
        $this->applyVisibilityScope($query);
        if ((int)($where['project_id'] ?? 0) > 0) $query->where('project_id', '=', (int)$where['project_id']);
        if (trim((string)($where['status'] ?? '')) !== '') $query->where('status', '=', trim((string)$where['status']));
        if ((int)($where['assignee_uid'] ?? 0) > 0) $query->where('assignee_uid', '=', (int)$where['assignee_uid']);
        if (trim((string)($where['keyword'] ?? '')) !== '') {
            $keyword = trim((string)$where['keyword']);
            $groupIds = ProjectCenterGroup::where('site_id', '=', $this->site_id)
                ->whereLike('group_no|group_no_full|member_name|member_mobile|store_name', '%' . $keyword . '%')->column('id');
            $memberIds = Member::where('site_id', '=', $this->site_id)
                ->whereLike('nickname|username|mobile', '%' . $keyword . '%')->column('member_id');
            $query->where(function ($q) use ($keyword, $groupIds, $memberIds) {
                $q->whereLike('application_no', '%' . $keyword . '%');
                if ($groupIds !== []) $q->whereIn('group_id', $groupIds, 'OR');
                if ($memberIds !== []) $q->whereIn('member_id', $memberIds, 'OR');
            });
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))), 'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        $page[$key] = $this->enrich((array)($page[$key] ?? []));
        return $page;
    }

    public function info(int $id): array
    {
        $model = ProjectCenterApplication::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($model->isEmpty()) throw new CommonException('资料工单不存在');
        $this->assertCanAccess($model->toArray());
        $row = $model->toArray();
        $row = $this->enrich([$row])[0];
        $submitLog = ProjectCenterReviewLog::where([
            ['site_id', '=', $this->site_id], ['application_id', '=', $id],
            ['submit_version', '=', (int)$row['submit_version']], ['action', 'in', ['submit', 'resubmit']],
        ])->order('id desc')->findOrEmpty();
        $snapshot = $submitLog->isEmpty() ? [] : (array)$submitLog->form_snapshot_json;
        $row['form_record'] = $snapshot !== [] ? $snapshot : (new CoreDiyFormRecordsService())->getInfo([
            'site_id' => $this->site_id, 'record_id' => (int)$row['form_record_id'],
        ]);
        $row['form_record_source'] = $snapshot !== [] ? 'submission_snapshot' : 'current_record_fallback';
        $row['review_logs'] = ProjectCenterReviewLog::where([['site_id', '=', $this->site_id], ['application_id', '=', $id]])->order('id desc')->select()->toArray();
        return $row;
    }

    public function review(int $id, string $action, array $issues, string $remark, bool $paymentChecked = false): void
    {
        $row = ProjectCenterApplication::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('资料工单不存在');
        $this->assertCanAccess($row->toArray());
        (new ProjectCenterReviewService())->review(
            (int)$this->site_id, $id, $action, $issues, $remark,
            (int)$this->uid, (string)$this->username, $paymentChecked
        );
    }

    /**
     * 审核人员从工单上下文更正业务群编号。
     *
     * 该入口沿用资料工单的可见范围，不要求审核员同时拥有“客户群台账”菜单权限；
     * 工单仍通过不可变的 group_id 关联，所以更正展示编号不会移动或丢失资料。
     */
    public function correctGroupNo(int $id, array $data): array
    {
        $application = ProjectCenterApplication::where([
            ['site_id', '=', $this->site_id], ['id', '=', $id],
        ])->findOrEmpty();
        if ($application->isEmpty()) throw new CommonException('资料工单不存在');
        $this->assertCanAccess($application->toArray());
        if (!$this->canViewAll() && !in_array((string)$application->status, [
            ProjectCenterDict::APPLICATION_SUBMITTED,
            ProjectCenterDict::APPLICATION_REVIEWING,
            ProjectCenterDict::APPLICATION_REJECTED,
        ], true)) {
            throw new CommonException('该资料已结束审核，请由管理员在客户群台账中更正编号');
        }

        $groupId = (int)$application->group_id;
        if ($groupId <= 0) throw new CommonException('当前工单未关联客户群，不能更正群编号');
        $reason = trim((string)($data['reason'] ?? ''));
        if ($reason === '') throw new CommonException('请填写更正原因，便于后续追溯');
        $expectedGroupNoFull = trim((string)($data['expected_group_no_full'] ?? ''));
        if ($expectedGroupNoFull === '') throw new CommonException('页面数据已过期，请刷新后重新更正');

        $group = ProjectCenterGroup::where([
            ['site_id', '=', $this->site_id], ['id', '=', $groupId],
        ])->findOrEmpty();
        if ($group->isEmpty()) throw new CommonException('客户群台账不存在');
        $oldGroupNo = (string)$group->group_no;
        $updated = (new ProjectCenterGroupService())->correctGroupNo(
            (int)$this->site_id,
            $groupId,
            (string)($data['group_no'] ?? ''),
            $expectedGroupNoFull,
            [
                'reason' => $reason,
                'source' => 'application',
                'application_id' => $id,
                'operator_id' => (int)$this->uid,
                'operator_name' => (string)$this->username,
            ]
        );

        Log::info('[hsx_project_center] 审核人员更正客户群编号', [
            'site_id' => (int)$this->site_id,
            'application_id' => $id,
            'group_id' => $groupId,
            'old_group_no' => $oldGroupNo,
            'new_group_no' => (string)$updated->group_no,
            'reason' => mb_substr($reason, 0, 500),
            'operator_uid' => (int)$this->uid,
        ]);
        try {
            ProjectCenterReviewLog::create([
                'site_id' => (int)$this->site_id,
                'application_id' => $id,
                'submit_version' => (int)$application->submit_version,
                'action' => 'correct_group_no',
                'from_status' => (string)$application->status,
                'to_status' => (string)$application->status,
                'field_issues_json' => [[
                    'field_key' => 'group_no',
                    'field_label' => '客户群编号',
                    'message' => $oldGroupNo . ' → ' . (string)$updated->group_no,
                    'example' => '',
                ]],
                'form_snapshot_json' => [],
                'remark' => mb_substr($reason, 0, 1000),
                'operator_type' => 'admin',
                'operator_id' => (int)$this->uid,
                'operator_name' => mb_substr((string)$this->username, 0, 100),
                'create_at' => time(),
            ]);
        } catch (\Throwable $e) {
            // 别名表已在更正事务内保存完整审计信息；时间线写入失败不能把已成功更正误报为失败。
            Log::warning('[hsx_project_center] 群编号更正时间线写入失败', [
                'application_id' => $id,
                'group_id' => $groupId,
                'message' => $e->getMessage(),
            ]);
        }
        return $updated->toArray();
    }

    public function archive(int $id): array
    {
        $row = ProjectCenterApplication::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('资料工单不存在');
        $this->assertCanAccess($row->toArray());
        if ((string)$row->status !== ProjectCenterDict::APPLICATION_APPROVED) {
            throw new CommonException('资料审核通过后才能生成交付资料包');
        }
        return (new ProjectCenterApplicationArchiveService())->build((int)$this->site_id, $id);
    }

    private function enrich(array $rows): array
    {
        if ($rows === []) return [];
        $projectIds = array_values(array_unique(array_map('intval', array_column($rows, 'project_id'))));
        $groupIds = array_values(array_unique(array_map('intval', array_column($rows, 'group_id'))));
        $memberIds = array_values(array_unique(array_map('intval', array_column($rows, 'member_id'))));
        $projects = ProjectCenterProject::where([['site_id', '=', $this->site_id], ['id', 'in', $projectIds]])
            ->field('id,title,distribution_enabled,config_json')->select()->toArray();
        $projectMap = [];
        foreach ($projects as $project) $projectMap[(int)$project['id']] = $project;
        $groups = ProjectCenterGroup::where([['site_id', '=', $this->site_id], ['id', 'in', $groupIds]])->select()->toArray();
        $groupMap = [];
        foreach ($groups as $group) $groupMap[(int)$group['id']] = $group;
        $members = Member::where([['site_id', '=', $this->site_id], ['member_id', 'in', $memberIds]])->field('member_id,nickname,username,mobile')->select()->toArray();
        $memberMap = [];
        foreach ($members as $member) $memberMap[(int)$member['member_id']] = $member;
        foreach ($rows as &$row) {
            $group = $groupMap[(int)$row['group_id']] ?? [];
            $member = $memberMap[(int)$row['member_id']] ?? [];
            $project = $projectMap[(int)$row['project_id']] ?? [];
            $row['project_title'] = (string)($project['title'] ?? '');
            $row['distribution_enabled'] = (int)($project['distribution_enabled'] ?? 0);
            $config = is_array($project['config_json'] ?? null) ? $project['config_json'] : [];
            $rule = (new \addon\hsx_project_center\app\service\core\ProjectCenterDistributionRuleService())
                ->normalizeProjectRule((array)($config['distribution'] ?? []));
            $row['distribution_rule'] = $rule;
            $row['payment_check_required'] = (int)($row['distribution_enabled'] && !empty($rule['approval_requires_payment_check']));
            $row['group_no'] = (string)($group['group_no'] ?? '');
            $row['group_no_full'] = (string)($group['group_no_full'] ?? '');
            $row['store_name'] = (string)($group['store_name'] ?? '');
            $row['member_name'] = (string)($group['member_name'] ?? ($member['nickname'] ?? $member['username'] ?? ''));
            $row['member_mobile'] = (string)($group['member_mobile'] ?? ($member['mobile'] ?? ''));
            $row['status_name'] = ProjectCenterDict::applicationStatuses()[(string)$row['status']] ?? (string)$row['status'];
        }
        unset($row);
        return $rows;
    }

    private function canViewAll(): bool
    {
        // 平台超级管理员不一定在当前站点的 sys_user_role 中存在关联记录，
        // 但框架权限体系会赋予其全部站点管理权限，不能再被项目审核员范围过滤。
        if (AuthService::isSuperAdmin()) return true;
        return SysUserRole::where([
            ['site_id', '=', $this->site_id], ['uid', '=', $this->uid], ['is_admin', '=', 1], ['status', '=', 1],
        ])->count() > 0;
    }

    private function visibleGroupIds(): array
    {
        $uid = (int)$this->uid;
        return array_values(array_map('intval', ProjectCenterGroup::where('site_id', '=', $this->site_id)
            ->where(function ($scope) use ($uid) {
                $scope->where('owner_uid', '=', $uid)
                    ->whereOrRaw('JSON_CONTAINS(COALESCE(collaborator_uids, \'[]\'), ?)', [(string)$uid]);
            })->column('id')));
    }

    private function reviewerProjectIds(): array
    {
        $uid = (int)$this->uid;
        $activeRole = SysUserRole::where([
            ['site_id', '=', $this->site_id], ['uid', '=', $uid], ['status', '=', 1],
        ])->count() > 0;
        $activeUser = \app\model\sys\SysUser::where([['uid', '=', $uid], ['status', '=', 1]])->count() > 0;
        if (!$activeRole || !$activeUser) return [];
        return array_values(array_map('intval', ProjectCenterProject::where('site_id', '=', $this->site_id)
            ->whereRaw('JSON_CONTAINS(COALESCE(reviewer_uids, \'[]\'), ?)', [(string)$uid])
            ->column('id')));
    }

    private function applyVisibilityScope($query): void
    {
        if ($this->canViewAll()) return;
        $uid = (int)$this->uid;
        $groupIds = $this->visibleGroupIds();
        $projectIds = $this->reviewerProjectIds();
        $query->where(function ($scope) use ($uid, $groupIds, $projectIds) {
            $scope->where('assignee_uid', '=', $uid);
            if ($groupIds !== []) $scope->whereIn('group_id', $groupIds, 'OR');
            if ($projectIds !== []) $scope->whereIn('project_id', $projectIds, 'OR');
        });
    }

    private function assertCanAccess(array $application): void
    {
        if ($this->canViewAll() || (int)($application['assignee_uid'] ?? 0) === (int)$this->uid) return;
        if (in_array((int)($application['group_id'] ?? 0), $this->visibleGroupIds(), true)) return;
        if (in_array((int)($application['project_id'] ?? 0), $this->reviewerProjectIds(), true)) return;
        throw new CommonException('该资料工单未分配给你，无权查看或审核');
    }
}
