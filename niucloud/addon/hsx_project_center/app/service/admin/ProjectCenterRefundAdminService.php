<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\admin;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterRefund;
use addon\hsx_project_center\app\model\ProjectCenterReviewLog;
use addon\hsx_project_center\app\service\core\ProjectCenterDistributionService;
use app\service\core\notice\NoticeService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 线下收款场景的退款状态台账。
 *
 * 系统不判断客户是否真实付款；工作人员依据群内流水发起退款，完成时必须留凭证。
 * “客户放弃但未付款”仍走 group close(abandoned)，不会制造一笔虚假退款。
 */
final class ProjectCenterRefundAdminService extends BaseAdminService
{
    public function infoByGroup(int $groupId): array
    {
        $group = $this->group($groupId);
        (new ProjectCenterGroupAdminService())->assertCanAccess($group);
        $row = ProjectCenterRefund::where([
            ['site_id', '=', $this->site_id], ['group_id', '=', $groupId],
        ])->findOrEmpty()->toArray();
        return $this->decorate($row);
    }

    public function request(int $groupId, array $data): array
    {
        $amount = round((float)($data['amount'] ?? 0), 2);
        $reason = trim((string)($data['reason'] ?? ''));
        if ($amount <= 0) throw new CommonException('请填写实际待退金额');
        if ($reason === '') throw new CommonException('请填写客户终止办理及退款原因');

        $refundId = Db::transaction(function () use ($groupId, $amount, $reason) {
            $group = ProjectCenterGroup::where([
                ['site_id', '=', $this->site_id], ['id', '=', $groupId],
            ])->lock(true)->findOrEmpty();
            if ($group->isEmpty()) throw new CommonException('客户群台账不存在');
            (new ProjectCenterGroupAdminService())->assertCanAccess($group);
            if ((string)$group->status === 'refunded') throw new CommonException('该客户已经完成退款，请勿重复操作');

            $application = ProjectCenterApplication::where([
                ['site_id', '=', $this->site_id], ['group_id', '=', $groupId],
            ])->order('id desc')->lock(true)->findOrEmpty();
            $refund = ProjectCenterRefund::where([
                ['site_id', '=', $this->site_id], ['group_id', '=', $groupId],
            ])->lock(true)->findOrEmpty();
            if (!$refund->isEmpty() && (string)$refund->status === 'refunded') {
                throw new CommonException('该客户已经完成退款，请勿重复操作');
            }

            $now = time();
            $applicationId = $application->isEmpty() ? 0 : (int)$application->id;
            $originApplicationStatus = $application->isEmpty() ? '' : (string)$application->status;
            $originGroupStatus = (string)$group->status;
            $payload = [
                'site_id' => (int)$this->site_id,
                'project_id' => (int)$group->project_id,
                'group_id' => $groupId,
                'application_id' => $applicationId,
                'member_id' => (int)$group->member_id,
                'amount' => $amount,
                'status' => 'pending',
                'reason' => mb_substr($reason, 0, 1000),
                'proof' => '', 'remark' => '',
                'requested_at' => $now, 'refunded_at' => 0, 'cancelled_at' => 0,
                'request_operator_id' => (int)$this->uid,
                'request_operator_name' => mb_substr((string)$this->username, 0, 100),
                'complete_operator_id' => 0, 'complete_operator_name' => '',
                'update_at' => $now,
            ];
            if ($refund->isEmpty()) {
                $payload['refund_no'] = create_no('PR');
                $payload['origin_group_status'] = $originGroupStatus;
                $payload['origin_application_status'] = $originApplicationStatus;
                $payload['create_at'] = $now;
                $refund = ProjectCenterRefund::create($payload);
            } else {
                // 已取消后可重新发起；正在退款时修改金额/原因也不丢失第一次进入流程前的状态。
                if ((string)$refund->status !== 'pending') {
                    $payload['origin_group_status'] = $originGroupStatus;
                    $payload['origin_application_status'] = $originApplicationStatus;
                }
                $refund->save($payload);
            }

            $group->save(['status' => 'refund_pending', 'update_at' => $now]);
            if (!$application->isEmpty()) {
                $from = (string)$application->status;
                $application->save([
                    'status' => ProjectCenterDict::APPLICATION_REFUND_PENDING,
                    'update_at' => $now,
                ]);
                $this->reviewLog($application->toArray(), 'refund_request', $from, ProjectCenterDict::APPLICATION_REFUND_PENDING, $reason, $now);
            }
            return (int)$refund->id;
        });

        $result = $this->infoById($refundId);
        try {
            (new ProjectCenterDistributionService())->freezeByApplication(
                (int)$this->site_id, (int)($result['application_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            Log::error('[hsx_project_center] 退款申请冻结佣金失败，等待补偿', ['refund_id' => $refundId, 'message' => $e->getMessage()]);
        }
        return $result;
    }

    public function complete(int $groupId, array $data): array
    {
        $proof = trim((string)($data['proof'] ?? ''));
        $remark = trim((string)($data['remark'] ?? ''));
        if ($proof === '') throw new CommonException('请上传退款凭证后再确认完成');

        $refundId = Db::transaction(function () use ($groupId, $proof, $remark) {
            $group = ProjectCenterGroup::where([
                ['site_id', '=', $this->site_id], ['id', '=', $groupId],
            ])->lock(true)->findOrEmpty();
            if ($group->isEmpty()) throw new CommonException('客户群台账不存在');
            (new ProjectCenterGroupAdminService())->assertCanAccess($group);
            $application = ProjectCenterApplication::where([
                ['site_id', '=', $this->site_id], ['group_id', '=', $groupId],
            ])->order('id desc')->lock(true)->findOrEmpty();
            $refund = ProjectCenterRefund::where([
                ['site_id', '=', $this->site_id], ['group_id', '=', $groupId],
            ])->lock(true)->findOrEmpty();
            if ($refund->isEmpty() || (string)$refund->status !== 'pending') throw new CommonException('没有待处理的退款记录');

            $now = time();
            $refund->save([
                'status' => 'refunded', 'proof' => mb_substr($proof, 0, 1000),
                'remark' => mb_substr($remark, 0, 1000), 'refunded_at' => $now,
                'complete_operator_id' => (int)$this->uid,
                'complete_operator_name' => mb_substr((string)$this->username, 0, 100),
                'update_at' => $now,
            ]);
            $group->save(['status' => 'refunded', 'completed_at' => $now, 'update_at' => $now]);
            if (!$application->isEmpty()) {
                $from = (string)$application->status;
                $application->save([
                    'status' => ProjectCenterDict::APPLICATION_REFUNDED,
                    'last_reject_summary' => '', 'reviewed_at' => $now, 'update_at' => $now,
                ]);
                $this->reviewLog($application->toArray(), 'refund_complete', $from, ProjectCenterDict::APPLICATION_REFUNDED, $remark, $now);
            }
            return (int)$refund->id;
        });

        $this->sendRefundNotice($refundId);
        $result = $this->infoById($refundId);
        try {
            (new ProjectCenterDistributionService())->refundByApplication(
                (int)$this->site_id, (int)($result['application_id'] ?? 0), (float)($result['amount'] ?? 0)
            );
        } catch (\Throwable $e) {
            Log::error('[hsx_project_center] 退款完成冲红佣金失败，等待补偿', ['refund_id' => $refundId, 'message' => $e->getMessage()]);
        }
        return $result;
    }

    public function cancel(int $groupId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') throw new CommonException('请填写取消退款的原因');
        $refundId = Db::transaction(function () use ($groupId, $reason) {
            $group = ProjectCenterGroup::where([
                ['site_id', '=', $this->site_id], ['id', '=', $groupId],
            ])->lock(true)->findOrEmpty();
            if ($group->isEmpty()) throw new CommonException('客户群台账不存在');
            (new ProjectCenterGroupAdminService())->assertCanAccess($group);
            $application = ProjectCenterApplication::where([
                ['site_id', '=', $this->site_id], ['group_id', '=', $groupId],
            ])->order('id desc')->lock(true)->findOrEmpty();
            $refund = ProjectCenterRefund::where([
                ['site_id', '=', $this->site_id], ['group_id', '=', $groupId],
            ])->lock(true)->findOrEmpty();
            if ($refund->isEmpty() || (string)$refund->status !== 'pending') throw new CommonException('只有待退款记录可以取消');

            $now = time();
            $groupStatus = $this->restorableGroupStatus((string)$refund->origin_group_status);
            $applicationStatus = $this->restorableApplicationStatus((string)$refund->origin_application_status);
            $refund->save([
                'status' => 'cancelled', 'remark' => mb_substr($reason, 0, 1000),
                'cancelled_at' => $now, 'update_at' => $now,
            ]);
            $group->save(['status' => $groupStatus, 'update_at' => $now]);
            if (!$application->isEmpty()) {
                $from = (string)$application->status;
                $application->save(['status' => $applicationStatus, 'update_at' => $now]);
                $this->reviewLog($application->toArray(), 'refund_cancel', $from, $applicationStatus, $reason, $now);
            }
            return (int)$refund->id;
        });
        $result = $this->infoById($refundId);
        try {
            (new ProjectCenterDistributionService())->resumeByApplication(
                (int)$this->site_id, (int)($result['application_id'] ?? 0)
            );
        } catch (\Throwable $e) {
            Log::error('[hsx_project_center] 取消退款恢复佣金失败，等待补偿', ['refund_id' => $refundId, 'message' => $e->getMessage()]);
        }
        return $result;
    }

    private function group(int $groupId): ProjectCenterGroup
    {
        $group = ProjectCenterGroup::where([
            ['site_id', '=', $this->site_id], ['id', '=', $groupId],
        ])->findOrEmpty();
        if ($group->isEmpty()) throw new CommonException('客户群台账不存在');
        return $group;
    }

    private function infoById(int $id): array
    {
        $row = ProjectCenterRefund::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty()->toArray();
        return $this->decorate($row);
    }

    private function decorate(array $row): array
    {
        if ($row === []) return [];
        $row['status_name'] = ProjectCenterDict::refundStatuses()[(string)$row['status']] ?? (string)$row['status'];
        $row['pending_seconds'] = (string)$row['status'] === 'pending' ? max(0, time() - (int)$row['requested_at']) : 0;
        $project = ProjectCenterProject::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$row['project_id']],
        ])->field('title')->findOrEmpty()->toArray();
        $row['project_title'] = (string)($project['title'] ?? '');
        return $row;
    }

    private function reviewLog(array $application, string $action, string $from, string $to, string $remark, int $now): void
    {
        ProjectCenterReviewLog::create([
            'site_id' => (int)$this->site_id, 'application_id' => (int)$application['id'],
            'submit_version' => (int)$application['submit_version'], 'action' => $action,
            'from_status' => $from, 'to_status' => $to, 'field_issues_json' => [], 'form_snapshot_json' => [],
            'remark' => mb_substr($remark, 0, 1000), 'operator_type' => 'admin',
            'operator_id' => (int)$this->uid, 'operator_name' => mb_substr((string)$this->username, 0, 100),
            'create_at' => $now,
        ]);
    }

    private function restorableGroupStatus(string $status): string
    {
        return in_array($status, ['reserved', 'created', 'active', 'completed', 'create_failed', 'abandoned', 'dissolved'], true)
            ? $status : 'active';
    }

    private function restorableApplicationStatus(string $status): string
    {
        return array_key_exists($status, ProjectCenterDict::applicationStatuses()) && !in_array($status, [
            ProjectCenterDict::APPLICATION_REFUND_PENDING, ProjectCenterDict::APPLICATION_REFUNDED,
        ], true) ? $status : ProjectCenterDict::APPLICATION_SUBMITTED;
    }

    private function sendRefundNotice(int $refundId): void
    {
        try {
            NoticeService::send((int)$this->site_id, 'project_center_refund_completed', ['refund_id' => $refundId]);
        } catch (\Throwable $e) {
            Log::warning('[hsx_project_center] 退款完成通知失败', ['refund_id' => $refundId, 'message' => $e->getMessage()]);
        }
    }
}
