<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterGroup;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterReviewLog;
use app\service\core\notice\NoticeService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/** 资料逐字段审核。审核事务与通知解耦，通知失败不回滚结果。 */
final class ProjectCenterReviewService
{
    public function review(int $siteId, int $applicationId, string $action, array $fieldIssues, string $remark, int $operatorId, string $operatorName, bool $paymentChecked = false): void
    {
        if (!in_array($action, ['approve', 'reject'], true)) throw new CommonException('审核动作不正确');
        $issues = $this->normalizeIssues($fieldIssues);
        if ($action === 'reject' && $issues === [] && trim($remark) === '') throw new CommonException('请标记有问题的字段或填写退回原因');

        $preview = ProjectCenterApplication::where([
            ['site_id', '=', $siteId], ['id', '=', $applicationId],
        ])->field('id,group_id')->findOrEmpty();
        if ($preview->isEmpty()) throw new CommonException('资料工单不存在');
        $expectedGroupId = (int)$preview->group_id;

        $application = Db::transaction(function () use ($siteId, $applicationId, $expectedGroupId, $action, $issues, $remark, $operatorId, $operatorName, $paymentChecked) {
            // 所有同时修改“客户群 + 工单”的事务统一按 group -> application 加锁，
            // 避免审核与结束客户群并发时形成反向锁序。
            $group = null;
            if ($expectedGroupId > 0) {
                $group = ProjectCenterGroup::where([
                    ['site_id', '=', $siteId], ['id', '=', $expectedGroupId],
                ])->lock(true)->findOrEmpty();
                if ($group->isEmpty()) throw new CommonException('客户群台账不存在，请联系管理员核对');
            }

            $row = ProjectCenterApplication::where([['site_id', '=', $siteId], ['id', '=', $applicationId]])->lock(true)->findOrEmpty();
            if ($row->isEmpty()) throw new CommonException('资料工单不存在');
            if ((int)$row->group_id <= 0) {
                throw new CommonException('历史无群工单仅供查看，请先在客户群台账完成关联后再审核');
            }
            if ((int)$row->group_id !== $expectedGroupId) {
                throw new CommonException('资料关联的客户群已发生变化，请刷新后重试');
            }
            if (!in_array((string)$row->status, [ProjectCenterDict::APPLICATION_SUBMITTED, ProjectCenterDict::APPLICATION_REVIEWING], true)) {
                throw new CommonException('该资料已处理，请勿重复审核');
            }
            if ($group !== null && in_array((string)$group->status, ['completed', 'refund_pending', 'refunded', 'abandoned', 'dissolved'], true)) {
                throw new CommonException('该客户群流程已经结束，不能继续审核');
            }
            if ($action === 'approve') {
                $project = ProjectCenterProject::where([
                    ['site_id', '=', $siteId], ['id', '=', (int)$row->project_id],
                ])->field('distribution_enabled,config_json')->findOrEmpty()->toArray();
                $config = is_array($project['config_json'] ?? null) ? $project['config_json'] : [];
                $rule = (new ProjectCenterDistributionRuleService())->normalizeProjectRule((array)($config['distribution'] ?? []));
                if (!empty($project['distribution_enabled']) && !empty($rule['approval_requires_payment_check']) && !$paymentChecked) {
                    throw new CommonException('该项目会生成推广佣金，请先核对群内付款流水并勾选确认');
                }
            }
            $from = (string)$row->status;
            $to = $action === 'approve' ? ProjectCenterDict::APPLICATION_APPROVED : ProjectCenterDict::APPLICATION_REJECTED;
            $now = time();
            $summary = $action === 'reject' ? $this->issueSummary($issues, $remark) : '';
            $paymentData = $action === 'approve' && $paymentChecked ? [
                'payment_confirmed_at' => $now,
                'payment_confirmed_uid' => $operatorId,
                'payment_confirmed_name' => mb_substr($operatorName, 0, 100),
            ] : [];
            $row->save(array_merge([
                'status' => $to, 'last_reject_summary' => $summary, 'reviewed_at' => $now,
                'approved_at' => $action === 'approve' ? $now : 0, 'update_at' => $now,
            ], $paymentData));
            $auditRemark = trim($remark);
            if ($action === 'approve' && $paymentChecked) {
                $auditRemark = '【已核对群内付款流水】' . ($auditRemark !== '' ? ' ' . $auditRemark : '');
            }
            ProjectCenterReviewLog::create([
                'site_id' => $siteId, 'application_id' => $applicationId, 'submit_version' => (int)$row->submit_version,
                'action' => $action, 'from_status' => $from, 'to_status' => $to,
                'field_issues_json' => $issues, 'form_snapshot_json' => [], 'remark' => mb_substr($auditRemark, 0, 1000),
                'operator_type' => 'admin', 'operator_id' => $operatorId,
                'operator_name' => mb_substr($operatorName, 0, 100), 'create_at' => $now,
            ]);
            if ($action === 'approve' && $group !== null) {
                $group->save(['status' => 'completed', 'completed_at' => $now, 'update_at' => $now]);
            }
            return $row->toArray();
        });

        if ($action === 'approve') {
            try {
                (new ProjectCenterDistributionService())->createForApprovedApplication($siteId, (int)$application['id']);
            } catch (\Throwable $e) {
                // 审核事实已经提交，定时补偿任务会按 application_id 幂等补建佣金单。
                Log::error('[hsx_project_center] 审核通过后创建分销佣金单失败，等待补偿', [
                    'application_id' => (int)$application['id'], 'message' => $e->getMessage(),
                ]);
            }
        }

        $this->sendResultNotice($siteId, (int)$application['id'], $action);
    }

    private function normalizeIssues(array $issues): array
    {
        $result = [];
        foreach ($issues as $issue) {
            if (!is_array($issue)) continue;
            $message = trim((string)($issue['message'] ?? ''));
            if ($message === '') continue;
            $result[] = [
                'field_key' => mb_substr(trim((string)($issue['field_key'] ?? '')), 0, 100),
                'field_label' => mb_substr(trim((string)($issue['field_label'] ?? '资料项')), 0, 100),
                'message' => mb_substr($message, 0, 500),
                'example' => mb_substr(trim((string)($issue['example'] ?? '')), 0, 500),
            ];
        }
        return $result;
    }

    private function issueSummary(array $issues, string $remark): string
    {
        $parts = array_map(static fn(array $item) => $item['field_label'] . '：' . $item['message'], $issues);
        if (trim($remark) !== '') $parts[] = trim($remark);
        return mb_substr(implode('；', $parts), 0, 1000);
    }

    private function sendResultNotice(int $siteId, int $applicationId, string $action): void
    {
        try {
            NoticeService::send($siteId, $action === 'approve' ? 'project_center_application_approved' : 'project_center_application_rejected', [
                'application_id' => $applicationId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[hsx_project_center] 审核结果通知失败', ['application_id' => $applicationId, 'message' => $e->getMessage()]);
        }
    }
}
