<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\admin;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterReviewLog;
use addon\hsx_project_center\app\service\core\ProjectCenterGroupService;
use app\model\sys\SysUserRole;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

final class ProjectCenterGroupAdminService extends BaseAdminService
{
    public function page(array $where): array
    {
        $query = ProjectCenterGroup::where('site_id', '=', $this->site_id);
        $this->applyVisibilityScope($query);
        if ((int)($where['project_id'] ?? 0) > 0) $query->where('project_id', '=', (int)$where['project_id']);
        if (trim((string)($where['status'] ?? '')) !== '') $query->where('status', '=', trim((string)$where['status']));
        if (trim((string)($where['keyword'] ?? '')) !== '') {
            $keyword = '%' . trim((string)$where['keyword']) . '%';
            $query->whereLike('group_no|group_no_full|member_name|member_mobile|store_name|wecom_chat_id', $keyword);
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))), 'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        $projectRows = ProjectCenterProject::where('site_id', '=', $this->site_id)->field('id,title,payment_amount')->select()->toArray();
        $projects = [];
        foreach ($projectRows as $project) $projects[(int)$project['id']] = $project;
        $rows = (array)($page[$key] ?? []);
        foreach ($rows as &$row) {
            $project = $projects[(int)$row['project_id']] ?? [];
            $row['project_title'] = (string)($project['title'] ?? '');
            $row['project_payment_amount'] = (string)($project['payment_amount'] ?? '0.00');
            $row['status_name'] = ProjectCenterDict::groupStatuses()[(string)$row['status']] ?? (string)$row['status'];
        }
        unset($row);
        $page[$key] = $rows;
        return $page;
    }

    public function reserve(array $data): array
    {
        $ownerUid = (int)($data['owner_uid'] ?? 0);
        if ($ownerUid <= 0) $ownerUid = (int)$this->uid;
        if (!$this->canViewAll()) $ownerUid = (int)$this->uid;
        $row = (new ProjectCenterGroupService())->reserve(
            (int)$this->site_id, (int)($data['project_id'] ?? 0),
            [
                'member_id' => (int)($data['member_id'] ?? 0), 'member_name' => (string)($data['member_name'] ?? ''),
                'member_mobile' => (string)($data['member_mobile'] ?? ''), 'store_name' => (string)($data['store_name'] ?? ''),
                'wecom_external_userid' => (string)($data['wecom_external_userid'] ?? ''),
            ],
            $ownerUid,
            (array)($data['collaborator_uids'] ?? []),
            (string)($data['group_no'] ?? '')
        );
        return $row->toArray();
    }

    public function correctGroupNo(int $id, array $data): array
    {
        $row = ProjectCenterGroup::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('客户群台账不存在');
        $this->assertCanAccess($row);
        $reason = trim((string)($data['reason'] ?? ''));
        if ($reason === '') throw new CommonException('请填写更正原因，便于后续追溯');
        $expectedGroupNoFull = trim((string)($data['expected_group_no_full'] ?? ''));
        if ($expectedGroupNoFull === '') throw new CommonException('页面数据已过期，请刷新后重新更正');
        $oldGroupNo = (string)$row->group_no;
        $updated = (new ProjectCenterGroupService())->correctGroupNo(
            (int)$this->site_id,
            $id,
            (string)($data['group_no'] ?? ''),
            $expectedGroupNoFull,
            [
                'reason' => $reason,
                'source' => 'group_ledger',
                'operator_id' => (int)$this->uid,
                'operator_name' => (string)$this->username,
            ]
        );
        Log::info('[hsx_project_center] 管理员更正客户群编号', [
            'site_id' => (int)$this->site_id,
            'group_id' => $id,
            'old_group_no' => $oldGroupNo,
            'new_group_no' => (string)$updated->group_no,
            'reason' => mb_substr($reason, 0, 500),
            'operator_uid' => (int)$this->uid,
        ]);
        return $updated->toArray();
    }

    /** 自动建群完成后统一回写；人工录入仅保留为异常兜底。 */
    public function bindWecom(int $id, array $data): bool
    {
        return Db::transaction(function () use ($id, $data) {
            $row = ProjectCenterGroup::where([['site_id', '=', $this->site_id], ['id', '=', $id]])
                ->lock(true)->findOrEmpty();
            if ($row->isEmpty()) throw new CommonException('群编号不存在');
            $this->assertCanAccess($row);
            if (in_array((string)$row->status, ['completed', 'refund_pending', 'refunded', 'abandoned', 'dissolved'], true)) {
                throw new CommonException('该客户群流程已结束，不能重新绑定企业微信群');
            }
            $chatId = trim((string)($data['wecom_chat_id'] ?? ''));
            if ($chatId !== '') {
                $duplicate = ProjectCenterGroup::where([
                    ['site_id', '=', $this->site_id], ['wecom_chat_id', '=', $chatId], ['id', '<>', $id],
                ])->lock(true)->findOrEmpty();
                if (!$duplicate->isEmpty()) throw new CommonException('该企业微信群已绑定其他编号');
            }
            $currentStatus = (string)$row->status;
            $nextStatus = $currentStatus === 'active'
                ? $currentStatus
                : ($chatId !== '' ? 'created' : 'reserved');
            return $row->save([
                'wecom_chat_id' => mb_substr($chatId, 0, 120),
                'create_mode' => trim((string)($data['create_mode'] ?? 'manual')) === 'wecom' ? 'wecom' : 'manual',
                'status' => $nextStatus, 'created_at' => $chatId !== '' ? ((int)$row->created_at ?: time()) : 0,
                'error_message' => '', 'update_at' => time(),
            ]);
        });
    }

    /** 客户取消或群解散时结束当前流程，并完整保留历史资料和审核记录。 */
    public function close(int $id, string $status, string $reason, bool $confirmedUnpaid = false): void
    {
        if (!in_array($status, ['abandoned', 'dissolved'], true)) throw new CommonException('群结束状态不正确');
        $reason = trim($reason);
        if ($reason === '') throw new CommonException('请填写结束原因，便于后续追溯');

        Db::transaction(function () use ($id, $status, $reason, $confirmedUnpaid) {
            $group = ProjectCenterGroup::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock(true)->findOrEmpty();
            if ($group->isEmpty()) throw new CommonException('群编号不存在');
            $this->assertCanAccess($group);
            if (in_array((string)$group->status, ['completed', 'refund_pending', 'refunded', 'abandoned', 'dissolved'], true)) throw new CommonException('该群已经进入结束或退款流程，不能重复关闭');
            if ($status === 'abandoned' && !$confirmedUnpaid) {
                $declaredPaid = ProjectCenterApplication::where([
                    ['site_id', '=', $this->site_id], ['group_id', '=', $id], ['payment_declared_at', '>', 0],
                ])->count() > 0;
                if ($declaredPaid) throw new CommonException('客户曾声明已付款，请先核对群内流水；已收款请走退款流程，确认未到账后才能放弃');
            }
            $now = time();
            $group->save([
                'status' => $status,
                'error_message' => mb_substr($reason, 0, 500),
                'dissolved_at' => $status === 'dissolved' ? $now : 0,
                'update_at' => $now,
            ]);

            $applications = ProjectCenterApplication::where([
                ['site_id', '=', $this->site_id], ['group_id', '=', $id],
                ['status', 'in', [ProjectCenterDict::APPLICATION_SUBMITTED, ProjectCenterDict::APPLICATION_REVIEWING, ProjectCenterDict::APPLICATION_REJECTED]],
            ])->select();
            foreach ($applications as $application) {
                $from = (string)$application->status;
                $application->save([
                    'status' => ProjectCenterDict::APPLICATION_ABANDONED,
                    'last_reject_summary' => mb_substr($reason, 0, 1000),
                    'update_at' => $now,
                ]);
                ProjectCenterReviewLog::create([
                    'site_id' => $this->site_id,
                    'application_id' => (int)$application->id,
                    'submit_version' => (int)$application->submit_version,
                    'action' => 'abandon',
                    'from_status' => $from,
                    'to_status' => ProjectCenterDict::APPLICATION_ABANDONED,
                    'field_issues_json' => [],
                    'form_snapshot_json' => [],
                    'remark' => mb_substr($reason, 0, 1000),
                    'operator_type' => 'admin',
                    'operator_id' => (int)$this->uid,
                    'operator_name' => mb_substr((string)$this->username, 0, 100),
                    'create_at' => $now,
                ]);
            }
        });
    }

    public function assertCanAccess(ProjectCenterGroup $group): void
    {
        if ($this->canViewAll()) return;
        $collaborators = array_values(array_filter(array_map('intval', (array)$group->collaborator_uids)));
        if ((int)$group->owner_uid !== (int)$this->uid && !in_array((int)$this->uid, $collaborators, true)) {
            throw new CommonException('你不是该客户群的负责人或协作人，无权操作');
        }
    }

    public function canViewAll(): bool
    {
        if (AuthService::isSuperAdmin()) return true;
        return SysUserRole::where([
            ['site_id', '=', $this->site_id], ['uid', '=', $this->uid], ['is_admin', '=', 1], ['status', '=', 1],
        ])->count() > 0;
    }

    private function applyVisibilityScope($query): void
    {
        if ($this->canViewAll()) return;
        $uid = (int)$this->uid;
        $query->where(function ($scope) use ($uid) {
            $scope->where('owner_uid', '=', $uid)
                ->whereOrRaw('JSON_CONTAINS(COALESCE(collaborator_uids, \'[]\'), ?)', [(string)$uid]);
        });
    }
}
