<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\api;

use addon\hsx_project_center\app\model\ProjectCenterDistributionDebt;
use addon\hsx_project_center\app\model\ProjectCenterDistributionDetail;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\service\core\ProjectCenterDistributionRuleService;
use addon\hsx_project_center\app\service\core\ProjectCenterInviteService;
use app\model\member\Member;
use core\base\BaseApiService;

final class ProjectCenterDistributionPortalService extends BaseApiService
{
    public function invite(int $projectId): array
    {
        return (new ProjectCenterInviteService())->create(
            (int)$this->site_id, $projectId, (int)$this->member_id
        );
    }

    public function bind(int $projectId, string $token): array
    {
        return (new ProjectCenterInviteService())->bind(
            (int)$this->site_id, $projectId, (int)$this->member_id, $token
        );
    }

    public function overview(int $projectId = 0): array
    {
        $member = Member::where([
            ['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id],
        ])->field('member_id,pid,member_level,commission,commission_get')->findOrEmpty()->toArray();
        $project = $projectId > 0 ? ProjectCenterProject::where([
            ['site_id', '=', $this->site_id], ['id', '=', $projectId],
        ])->findOrEmpty()->toArray() : [];
        $capability = (new ProjectCenterDistributionRuleService())->memberCapability(
            (int)$this->site_id, (int)$this->member_id, 1, $project === [] ? null : $project
        );
        // 会员资料与完整权益配置只用于服务端判定，移动端仅需要展示资格、等级和系数。
        // 避免把手机号、账户余额等内部字段跟随 capability 一并返回。
        unset($capability['member'], $capability['benefit']);
        $query = ProjectCenterDistributionDetail::where([
            ['site_id', '=', $this->site_id], ['beneficiary_member_id', '=', $this->member_id],
        ]);
        if ($projectId > 0) $query->where('project_id', '=', $projectId);
        $pendingQuery = (clone $query)->where('status', 'in', ['pending', 'frozen', 'exception']);
        $pending = (float)(clone $pendingQuery)->sum('commission_amount')
            - (float)(clone $pendingQuery)->sum('reversed_amount');
        $settled = (clone $query)->sum('settled_amount');
        $reversed = (clone $query)->sum('reversed_amount');
        $directIds = Member::where([
            ['site_id', '=', $this->site_id], ['pid', '=', $this->member_id],
        ])->column('member_id');
        $secondCount = $directIds === [] ? 0 : Member::where([
            ['site_id', '=', $this->site_id], ['pid', 'in', array_map('intval', $directIds)],
        ])->count();
        $debt = ProjectCenterDistributionDebt::where([
            ['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['status', '=', 'pending'],
        ])->select()->toArray();
        $debtAmount = 0.0;
        foreach ($debt as $item) $debtAmount += max(0, (float)$item['amount'] - (float)$item['offset_amount']);

        return [
            'capability' => $capability,
            'project' => $project === [] ? [] : (new ProjectCenterDistributionRuleService())->publicSummary($project),
            'commission_balance' => (float)($member['commission'] ?? 0),
            'pending_amount' => round(max(0, (float)$pending), 2), 'settled_amount' => round((float)$settled, 2),
            'reversed_amount' => round((float)$reversed, 2), 'debt_amount' => round($debtAmount, 2),
            'direct_count' => count($directIds), 'second_count' => $secondCount,
            'bound_inviter_member_id' => (int)($member['pid'] ?? 0),
        ];
    }

    public function details(array $where): array
    {
        $query = ProjectCenterDistributionDetail::where([
            ['site_id', '=', $this->site_id], ['beneficiary_member_id', '=', $this->member_id],
        ]);
        if ((int)($where['project_id'] ?? 0) > 0) $query->where('project_id', '=', (int)$where['project_id']);
        if (trim((string)($where['status'] ?? '')) !== '') $query->where('status', '=', trim((string)$where['status']));
        $page = $query->order('id desc')->paginate([
            'list_rows' => max(1, min(50, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        $rows = (array)($page[$key] ?? []);
        $projectIds = array_values(array_unique(array_map('intval', array_column($rows, 'project_id'))));
        $projects = $projectIds === [] ? [] : ProjectCenterProject::where([
            ['site_id', '=', $this->site_id], ['id', 'in', $projectIds],
        ])->column('title', 'id');
        $names = ['pending' => '待结算', 'frozen' => '退款冻结', 'settled' => '已结算', 'cancelled' => '已取消', 'partial_reversed' => '部分冲红', 'reversed' => '已冲红', 'exception' => '处理异常'];
        foreach ($rows as &$row) {
            $row['project_title'] = (string)($projects[(int)$row['project_id']] ?? '');
            $row['relation_level_name'] = (int)$row['relation_level'] === 1 ? '一级佣金' : '二级佣金';
            $row['status_name'] = $names[(string)$row['status']] ?? (string)$row['status'];
            unset($row['level_snapshot']);
        }
        unset($row);
        $page[$key] = $rows;
        return $page;
    }
}
