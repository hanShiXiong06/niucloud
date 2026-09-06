<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\admin;

use addon\hsx_project_center\app\model\ProjectCenterDistributionDebt;
use addon\hsx_project_center\app\model\ProjectCenterDistributionDetail;
use addon\hsx_project_center\app\model\ProjectCenterDistributionOrder;
use addon\hsx_project_center\app\model\ProjectCenterInvite;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterRelationLog;
use addon\hsx_project_center\app\service\core\ProjectCenterDistributionService;
use app\model\member\Member;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class ProjectCenterDistributionAdminService extends BaseAdminService
{
    private const STATUS_NAMES = [
        'pending' => '待结算', 'frozen' => '退款冻结', 'settled' => '已结算',
        'cancelled' => '已取消', 'partial_reversed' => '部分冲红', 'reversed' => '已冲红',
        'exception' => '处理异常',
    ];

    public function overview(): array
    {
        $pendingWhere = [['site_id', '=', $this->site_id], ['status', 'in', ['pending', 'frozen', 'exception']]];
        $pendingAmount = (float)ProjectCenterDistributionDetail::where($pendingWhere)->sum('commission_amount')
            - (float)ProjectCenterDistributionDetail::where($pendingWhere)->sum('reversed_amount');
        return [
            'order_count' => ProjectCenterDistributionOrder::where('site_id', '=', $this->site_id)->count(),
            'invite_count' => ProjectCenterInvite::where([['site_id', '=', $this->site_id], ['status', '=', 1]])->count(),
            'bound_count' => ProjectCenterRelationLog::where([['site_id', '=', $this->site_id], ['action', '=', 'bind']])->count(),
            'pending_count' => ProjectCenterDistributionOrder::where([['site_id', '=', $this->site_id], ['status', 'in', ['pending', 'frozen', 'exception']]])->count(),
            'pending_amount' => round(max(0, $pendingAmount), 2),
            'settled_amount' => round((float)ProjectCenterDistributionDetail::where('site_id', '=', $this->site_id)->sum('settled_amount'), 2),
            'reversed_amount' => round((float)ProjectCenterDistributionDetail::where('site_id', '=', $this->site_id)->sum('reversed_amount'), 2),
            'debt_amount' => round(
                (float)ProjectCenterDistributionDebt::where([['site_id', '=', $this->site_id], ['status', '=', 'pending']])->sum('amount')
                - (float)ProjectCenterDistributionDebt::where([['site_id', '=', $this->site_id], ['status', '=', 'pending']])->sum('offset_amount'),
                2
            ),
        ];
    }

    public function page(array $where): array
    {
        $query = ProjectCenterDistributionOrder::where('site_id', '=', $this->site_id);
        if ((int)($where['project_id'] ?? 0) > 0) $query->where('project_id', '=', (int)$where['project_id']);
        if (trim((string)($where['status'] ?? '')) !== '') $query->where('status', '=', trim((string)$where['status']));
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') {
            $memberIds = Member::where('site_id', '=', $this->site_id)
                ->whereLike('nickname|username|mobile|member_no', '%' . $keyword . '%')->column('member_id');
            $beneficiaryOrderIds = $memberIds === [] ? [] : ProjectCenterDistributionDetail::where([
                ['site_id', '=', $this->site_id], ['beneficiary_member_id', 'in', array_map('intval', $memberIds)],
            ])->column('order_id');
            $query->where(function ($scope) use ($keyword, $memberIds, $beneficiaryOrderIds) {
                $scope->whereLike('order_no', '%' . $keyword . '%');
                if ($memberIds !== []) $scope->whereIn('buyer_member_id', $memberIds, 'OR');
                if ($beneficiaryOrderIds !== []) $scope->whereIn('id', array_map('intval', $beneficiaryOrderIds), 'OR');
            });
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        $page[$key] = $this->enrich((array)($page[$key] ?? []));
        return $page;
    }

    public function info(int $id): array
    {
        $row = ProjectCenterDistributionOrder::where([
            ['site_id', '=', $this->site_id], ['id', '=', $id],
        ])->findOrEmpty()->toArray();
        if ($row === []) throw new CommonException('分销佣金单不存在');
        return $this->enrich([$row])[0];
    }

    public function settle(int $id): void
    {
        $row = ProjectCenterDistributionOrder::where([
            ['site_id', '=', $this->site_id], ['id', '=', $id],
        ])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('分销佣金单不存在');
        if ((string)$row->status === 'frozen') throw new CommonException('该佣金单处于退款冻结状态，不能结算');
        if (!in_array((string)$row->status, ['pending', 'exception'], true)) throw new CommonException('当前状态不需要重复结算');
        (new ProjectCenterDistributionService())->settleOrder($id, true);
    }

    public function projects(): array
    {
        return ProjectCenterProject::where('site_id', '=', $this->site_id)
            ->field('id,title,distribution_enabled')->order('id desc')->select()->toArray();
    }

    private function enrich(array $rows): array
    {
        if ($rows === []) return [];
        $projectIds = array_values(array_unique(array_map('intval', array_column($rows, 'project_id'))));
        $buyerIds = array_values(array_unique(array_map('intval', array_column($rows, 'buyer_member_id'))));
        $projects = ProjectCenterProject::where([['site_id', '=', $this->site_id], ['id', 'in', $projectIds]])->column('title', 'id');
        $members = $buyerIds === [] ? [] : Member::where([['site_id', '=', $this->site_id], ['member_id', 'in', $buyerIds]])
            ->field('member_id,nickname,username,mobile')->select()->toArray();
        $memberMap = [];
        foreach ($members as $member) $memberMap[(int)$member['member_id']] = $member;
        foreach ($rows as &$row) {
            $row['project_title'] = (string)($projects[(int)$row['project_id']] ?? '');
            $buyer = $memberMap[(int)$row['buyer_member_id']] ?? [];
            $row['buyer_name'] = (string)($buyer['nickname'] ?? $buyer['username'] ?? '');
            $row['buyer_mobile'] = (string)($buyer['mobile'] ?? '');
            $row['status_name'] = self::STATUS_NAMES[(string)$row['status']] ?? (string)$row['status'];
            $details = ProjectCenterDistributionDetail::where([
                ['site_id', '=', $this->site_id], ['order_id', '=', (int)$row['id']],
            ])->order('relation_level asc')->select()->toArray();
            $beneficiaryIds = array_values(array_unique(array_map('intval', array_column($details, 'beneficiary_member_id'))));
            $beneficiaries = $beneficiaryIds === [] ? [] : Member::where([
                ['site_id', '=', $this->site_id], ['member_id', 'in', $beneficiaryIds],
            ])->field('member_id,nickname,username,mobile')->select()->toArray();
            $beneficiaryMap = [];
            foreach ($beneficiaries as $member) $beneficiaryMap[(int)$member['member_id']] = $member;
            foreach ($details as &$detail) {
                $member = $beneficiaryMap[(int)$detail['beneficiary_member_id']] ?? [];
                $detail['beneficiary_name'] = (string)($member['nickname'] ?? $member['username'] ?? '');
                $detail['beneficiary_mobile'] = (string)($member['mobile'] ?? '');
                $detail['status_name'] = self::STATUS_NAMES[(string)$detail['status']] ?? (string)$detail['status'];
                $detail['relation_level_name'] = (int)$detail['relation_level'] === 1 ? '一级佣金' : '二级佣金';
            }
            unset($detail);
            $row['details'] = $details;
        }
        unset($row);
        return $rows;
    }
}
