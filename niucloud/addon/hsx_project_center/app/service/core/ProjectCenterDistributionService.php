<?php
declare(strict_types=1);

namespace addon\hsx_project_center\app\service\core;

use addon\hsx_project_center\app\dict\ProjectCenterDict;
use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterDistributionDebt;
use addon\hsx_project_center\app\model\ProjectCenterDistributionDetail;
use addon\hsx_project_center\app\model\ProjectCenterDistributionOrder;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\model\ProjectCenterRefund;
use app\dict\member\MemberAccountTypeDict;
use app\model\member\Member;
use app\service\core\member\CoreMemberAccountService;
use app\service\core\notice\NoticeService;
use think\facade\Db;
use think\facade\Log;

/** 两级项目分销的业务单、结算、退款冲红与补偿入口。 */
final class ProjectCenterDistributionService
{
    public function createForApprovedApplication(int $siteId, int $applicationId): int
    {
        $createdDetailIds = [];
        $orderId = Db::transaction(function () use ($siteId, $applicationId, &$createdDetailIds) {
            $existing = ProjectCenterDistributionOrder::where([
                ['site_id', '=', $siteId], ['application_id', '=', $applicationId],
            ])->lock(true)->findOrEmpty();
            if (!$existing->isEmpty()) return (int)$existing->id;

            $application = ProjectCenterApplication::where([
                ['site_id', '=', $siteId], ['id', '=', $applicationId],
            ])->lock(true)->findOrEmpty();
            if ($application->isEmpty() || (string)$application->status !== ProjectCenterDict::APPLICATION_APPROVED) return 0;
            $project = ProjectCenterProject::where([
                ['site_id', '=', $siteId], ['id', '=', (int)$application->project_id],
                ['distribution_enabled', '=', 1],
            ])->findOrEmpty();
            if ($project->isEmpty()) return 0;

            $ruleService = new ProjectCenterDistributionRuleService();
            $rule = $ruleService->projectRule($project);
            // 审核服务会校验付款事实，这里仍保留领域层兜底，避免脚本、补偿任务或未来入口
            // 绕过审核服务后为未核款工单生成佣金。
            if (!empty($rule['approval_requires_payment_check']) && (int)$application->payment_confirmed_at <= 0) return 0;
            $buyerId = (int)$application->member_id;
            $buyer = Member::where([
                ['site_id', '=', $siteId], ['member_id', '=', $buyerId],
            ])->field('member_id,pid')->findOrEmpty()->toArray();
            $firstId = (int)($buyer['pid'] ?? 0);
            $secondId = $firstId > 0 ? (int)Member::where([
                ['site_id', '=', $siteId], ['member_id', '=', $firstId],
            ])->value('pid') : 0;
            // 兼容历史会员数据中可能存在的自指或异常环，防止同一人同时获得一、二级两笔佣金。
            if ($secondId === $buyerId || $secondId === $firstId) $secondId = 0;

            $businessAmount = max(0, round((float)$project->payment_amount, 2));
            $approvedAt = max(1, (int)$application->approved_at ?: time());
            $settleAt = $approvedAt + ((int)$rule['settle_days'] * 86400);
            $snapshots = [];
            $details = [];
            $historicalReason = '';
            if ((int)$rule['enabled_at'] <= 0 || $approvedAt < (int)$rule['enabled_at']) {
                $historicalReason = '该工单在项目开启分销前已经审核通过，按规则不追溯发佣';
                $snapshots[] = ['relation_level' => 0, 'member_id' => 0, 'eligible' => 0, 'reason' => $historicalReason];
            }
            foreach ($historicalReason === '' ? [1 => $firstId, 2 => $secondId] : [] as $relationLevel => $beneficiaryId) {
                if ($beneficiaryId <= 0 || $beneficiaryId === $buyerId) {
                    $snapshots[] = ['relation_level' => $relationLevel, 'member_id' => $beneficiaryId, 'eligible' => 0, 'reason' => '推荐链路中没有该级受益人'];
                    continue;
                }
                $capability = $ruleService->memberCapability($siteId, $beneficiaryId, $relationLevel, $project);
                $snapshots[] = [
                    'relation_level' => $relationLevel, 'member_id' => $beneficiaryId,
                    'eligible' => (int)$capability['eligible'], 'reason' => (string)$capability['reason'],
                    'level_id' => (int)$capability['level_id'], 'level_name' => (string)$capability['level_name'],
                    'coefficient' => (float)$capability['coefficient'],
                ];
                if (empty($capability['eligible'])) continue;
                $calculated = $ruleService->commission($businessAmount, $rule, $relationLevel, (float)$capability['coefficient']);
                if ((float)$calculated['commission_amount'] <= 0) continue;
                $details[] = array_merge($calculated, [
                    'site_id' => $siteId, 'application_id' => $applicationId,
                    'project_id' => (int)$project->id, 'beneficiary_member_id' => $beneficiaryId,
                    'relation_level' => $relationLevel, 'beneficiary_level_id' => (int)$capability['level_id'],
                    'beneficiary_level_name' => (string)$capability['level_name'],
                    'level_snapshot' => [
                        'level_id' => (int)$capability['level_id'], 'level_name' => (string)$capability['level_name'],
                        'benefit' => (array)$capability['benefit'],
                    ],
                    'debt_offset_amount' => 0, 'settled_amount' => 0, 'reversed_amount' => 0,
                    'debt_amount' => 0, 'status' => 'pending', 'account_log_id' => 0, 'reverse_log_id' => 0,
                    'settle_at' => $settleAt, 'settled_at' => 0, 'create_at' => time(), 'update_at' => time(),
                ]);
            }

            $order = ProjectCenterDistributionOrder::create([
                'site_id' => $siteId, 'order_no' => create_no('PCD'),
                'project_id' => (int)$project->id, 'application_id' => $applicationId,
                'buyer_member_id' => $buyerId, 'base_amount' => $businessAmount,
                'rule_snapshot' => [
                    'project_title' => (string)$project->title, 'rule' => $rule,
                    'approval_at' => $approvedAt, 'payment_confirmed_at' => (int)$application->payment_confirmed_at,
                    'relations' => $snapshots,
                ],
                'status' => $details === [] ? 'cancelled' : 'pending',
                'settle_at' => $settleAt, 'settled_at' => 0, 'frozen_at' => 0,
                'cancelled_at' => $details === [] ? time() : 0, 'refund_amount' => 0,
                'error_message' => $details === []
                    ? ($historicalReason !== '' ? $historicalReason : '审批时未找到符合会员等级权益的一级或二级受益人')
                    : '',
                'create_at' => time(), 'update_at' => time(),
            ]);
            foreach ($details as $detail) {
                $detail['order_id'] = (int)$order->id;
                $created = ProjectCenterDistributionDetail::create($detail);
                $createdDetailIds[] = (int)$created->id;
            }
            return (int)$order->id;
        });

        if ($orderId > 0) {
            $order = ProjectCenterDistributionOrder::where('id', '=', $orderId)->findOrEmpty();
            if (!$order->isEmpty() && (int)$order->settle_at > time()) {
                foreach ($createdDetailIds as $detailId) {
                    $this->sendChangeNotice('project_center_distribution_pending', $detailId);
                }
            }
            if (!$order->isEmpty() && (string)$order->status === 'pending' && (int)$order->settle_at <= time()) {
                $this->settleOrder($orderId, false);
            }
        }
        return $orderId;
    }

    public function reconcileApproved(int $limit = 100, int $siteId = 0): int
    {
        $projectQuery = ProjectCenterProject::where('distribution_enabled', '=', 1);
        if ($siteId > 0) $projectQuery->where('site_id', '=', $siteId);
        $projectScopes = $projectQuery->field('site_id,id,config_json')->select()->toArray();
        $rows = [];
        foreach ($projectScopes as $scope) {
            $rule = (new ProjectCenterDistributionRuleService())->projectRule($scope);
            $enabledAt = (int)$rule['enabled_at'];
            if ($enabledAt <= 0) continue;
            $remaining = max(1, $limit - count($rows));
            $applications = ProjectCenterApplication::alias('a')
                ->leftJoin('project_center_distribution_order d', 'd.site_id=a.site_id AND d.application_id=a.id')
                ->where([
                    ['a.site_id', '=', (int)$scope['site_id']], ['a.project_id', '=', (int)$scope['id']],
                    ['a.status', '=', ProjectCenterDict::APPLICATION_APPROVED], ['a.approved_at', '>=', $enabledAt],
                ])->whereNull('d.id')->field('a.site_id,a.id')->order('a.id asc')->limit($remaining)->select()->toArray();
            array_push($rows, ...$applications);
            if (count($rows) >= $limit) break;
        }
        $count = 0;
        foreach ($rows as $row) {
            try {
                if ($this->createForApprovedApplication((int)$row['site_id'], (int)$row['id']) > 0) $count++;
            } catch (\Throwable $e) {
                Log::error('[hsx_project_center] 补建分销佣金单失败', ['application_id' => (int)$row['id'], 'message' => $e->getMessage()]);
            }
        }
        return $count;
    }

    public function settleDue(int $limit = 100, int $siteId = 0): int
    {
        $query = ProjectCenterDistributionOrder::where([
            ['status', '=', 'pending'], ['settle_at', '<=', time()],
        ]);
        if ($siteId > 0) $query->where('site_id', '=', $siteId);
        $ids = $query->order('settle_at asc,id asc')->limit(max(1, min(500, $limit)))->column('id');
        $count = 0;
        foreach ($ids as $id) if ($this->settleOrder((int)$id, false)) $count++;
        return $count;
    }

    public function reconcileRefunds(int $limit = 100, int $siteId = 0): int
    {
        // 只取佣金状态与退款事实不一致的行；避免每 5 分钟反复扫描最新 100 条，
        // 导致更早的一条失败记录永远得不到补偿。
        $query = ProjectCenterRefund::alias('r')
            ->join('project_center_distribution_order d', 'd.site_id=r.site_id AND d.application_id=r.application_id')
            ->where('r.application_id', '>', 0);
        if ($siteId > 0) $query->where('r.site_id', '=', $siteId);
        $rows = $query
            ->where(function ($scope) {
                $scope->where(function ($pending) {
                    $pending->where('r.status', '=', 'pending')->where('d.status', 'in', ['pending', 'exception']);
                })->whereOr(function ($refunded) {
                    $refunded->where('r.status', '=', 'refunded')->whereRaw('d.refund_amount < r.amount');
                })->whereOr(function ($cancelled) {
                    $cancelled->where('r.status', '=', 'cancelled')->where('d.status', '=', 'frozen');
                });
            })->field('r.*')->order('r.update_at asc,r.id asc')
            ->limit(max(1, min(500, $limit)))->select()->toArray();
        $count = 0;
        foreach ($rows as $row) {
            try {
                $siteId = (int)$row['site_id'];
                $applicationId = (int)$row['application_id'];
                if ((string)$row['status'] === 'pending') $this->freezeByApplication($siteId, $applicationId);
                elseif ((string)$row['status'] === 'refunded') $this->refundByApplication($siteId, $applicationId, (float)$row['amount']);
                else $this->resumeByApplication($siteId, $applicationId);
                $count++;
            } catch (\Throwable $e) {
                Log::error('[hsx_project_center] 分销退款状态补偿失败', ['refund_id' => (int)$row['id'], 'message' => $e->getMessage()]);
            }
        }
        return $count;
    }

    public function settleOrder(int $orderId, bool $force = false): bool
    {
        $details = ProjectCenterDistributionDetail::where([
            ['order_id', '=', $orderId], ['status', 'in', ['pending', 'exception']],
        ])->order('relation_level asc')->select()->toArray();
        $handled = false;
        foreach ($details as $detail) {
            if (!$force && (int)$detail['settle_at'] > time()) continue;
            try {
                if ($this->settleDetail((int)$detail['id'], $force)) {
                    $handled = true;
                    $this->sendChangeNotice('project_center_distribution_settled', (int)$detail['id']);
                }
            } catch (\Throwable $e) {
                ProjectCenterDistributionDetail::where('id', '=', (int)$detail['id'])->update([
                    'status' => 'exception', 'update_at' => time(),
                ]);
                ProjectCenterDistributionOrder::where('id', '=', $orderId)->update([
                    'status' => 'exception', 'error_message' => mb_substr($e->getMessage(), 0, 1000), 'update_at' => time(),
                ]);
                Log::error('[hsx_project_center] 分销佣金结算失败', ['detail_id' => (int)$detail['id'], 'message' => $e->getMessage()]);
            }
        }
        $this->refreshOrderStatus($orderId);
        return $handled;
    }

    private function settleDetail(int $detailId, bool $force): bool
    {
        return (bool)Db::transaction(function () use ($detailId, $force) {
            $detail = ProjectCenterDistributionDetail::where('id', '=', $detailId)->lock(true)->findOrEmpty();
            if ($detail->isEmpty() || !in_array((string)$detail->status, ['pending', 'exception'], true)) return false;
            if (!$force && (int)$detail->settle_at > time()) return false;
            $order = ProjectCenterDistributionOrder::where('id', '=', (int)$detail->order_id)->lock(true)->findOrEmpty();
            if ($order->isEmpty() || !in_array((string)$order->status, ['pending', 'exception'], true)) return false;

            $gross = max(0, round((float)$detail->commission_amount - (float)$detail->reversed_amount, 2));
            $remaining = $gross;
            $offset = 0.0;
            $debts = ProjectCenterDistributionDebt::where([
                ['site_id', '=', (int)$detail->site_id], ['member_id', '=', (int)$detail->beneficiary_member_id],
                ['status', '=', 'pending'],
            ])->order('id asc')->lock(true)->select();
            foreach ($debts as $debt) {
                if ($remaining <= 0) break;
                $unpaid = max(0, round((float)$debt->amount - (float)$debt->offset_amount, 2));
                $used = min($remaining, $unpaid);
                if ($used <= 0) continue;
                $newOffset = round((float)$debt->offset_amount + $used, 2);
                $debt->save([
                    'offset_amount' => $newOffset,
                    'status' => $newOffset >= (float)$debt->amount ? 'settled' : 'pending',
                    'update_at' => time(),
                ]);
                $remaining = round($remaining - $used, 2);
                $offset = round($offset + $used, 2);
            }
            $accountLogId = 0;
            if ($remaining > 0) {
                $accountLogId = (int)(new CoreMemberAccountService())->addLog(
                    (int)$detail->site_id, (int)$detail->beneficiary_member_id,
                    MemberAccountTypeDict::COMMISSION, $remaining,
                    'project_center_distribution_settle',
                    '项目推广' . ((int)$detail->relation_level === 1 ? '一级' : '二级') . '佣金结算',
                    (int)$detail->id
                );
            }
            $detail->save([
                'debt_offset_amount' => $offset, 'settled_amount' => $remaining,
                'status' => 'settled', 'account_log_id' => $accountLogId,
                'settled_at' => time(), 'update_at' => time(),
            ]);
            return true;
        });
    }

    public function freezeByApplication(int $siteId, int $applicationId): void
    {
        $order = ProjectCenterDistributionOrder::where([
            ['site_id', '=', $siteId], ['application_id', '=', $applicationId],
        ])->findOrEmpty();
        if ($order->isEmpty()) return;
        Db::transaction(function () use ($order) {
            ProjectCenterDistributionDetail::where([
                ['order_id', '=', (int)$order->id], ['status', 'in', ['pending', 'exception']],
            ])->update(['status' => 'frozen', 'update_at' => time()]);
            if (in_array((string)$order->status, ['pending', 'exception'], true)) {
                $order->save(['status' => 'frozen', 'frozen_at' => time(), 'update_at' => time()]);
            }
        });
    }

    public function resumeByApplication(int $siteId, int $applicationId): void
    {
        $order = ProjectCenterDistributionOrder::where([
            ['site_id', '=', $siteId], ['application_id', '=', $applicationId],
        ])->findOrEmpty();
        if ($order->isEmpty() || (string)$order->status !== 'frozen') return;
        Db::transaction(function () use ($order) {
            ProjectCenterDistributionDetail::where([
                ['order_id', '=', (int)$order->id], ['status', '=', 'frozen'],
            ])->update(['status' => 'pending', 'update_at' => time()]);
            $order->save(['status' => 'pending', 'frozen_at' => 0, 'update_at' => time()]);
        });
        if ((int)$order->settle_at <= time()) $this->settleOrder((int)$order->id, false);
    }

    public function refundByApplication(int $siteId, int $applicationId, float $refundAmount): void
    {
        $order = ProjectCenterDistributionOrder::where([
            ['site_id', '=', $siteId], ['application_id', '=', $applicationId],
        ])->findOrEmpty();
        if ($order->isEmpty()) return;
        $baseAmount = max(0.01, (float)$order->base_amount);
        $ratio = min(1, max(0, $refundAmount / $baseAmount));
        if ($ratio <= 0) return;

        $detailIds = ProjectCenterDistributionDetail::where('order_id', '=', (int)$order->id)->column('id');
        foreach ($detailIds as $detailId) {
            $changedAmount = $this->refundDetail((int)$detailId, $ratio);
            if ($changedAmount > 0) {
                $this->sendChangeNotice('project_center_distribution_reversed', (int)$detailId, $changedAmount);
            }
        }
        ProjectCenterDistributionOrder::where('id', '=', (int)$order->id)->update([
            'refund_amount' => round($refundAmount, 2), 'update_at' => time(),
        ]);
        $this->refreshOrderStatus((int)$order->id);
    }

    private function refundDetail(int $detailId, float $ratio): float
    {
        return (float)Db::transaction(function () use ($detailId, $ratio) {
            $detail = ProjectCenterDistributionDetail::where('id', '=', $detailId)->lock(true)->findOrEmpty();
            if ($detail->isEmpty() || in_array((string)$detail->status, ['cancelled', 'reversed'], true)) return 0.0;
            $target = round((float)$detail->commission_amount * $ratio, 2);
            $remaining = max(0, round($target - (float)$detail->reversed_amount, 2));
            if ($remaining <= 0) return 0.0;

            if (in_array((string)$detail->status, ['pending', 'frozen', 'exception'], true)) {
                $newReversed = round((float)$detail->reversed_amount + $remaining, 2);
                $detail->save([
                    'reversed_amount' => $newReversed,
                    'status' => $newReversed >= (float)$detail->commission_amount ? 'cancelled' : 'pending',
                    'update_at' => time(),
                ]);
                return $remaining;
            }

            $member = Member::where([
                ['site_id', '=', (int)$detail->site_id], ['member_id', '=', (int)$detail->beneficiary_member_id],
            ])->field('member_id,commission')->lock(true)->findOrEmpty();
            $available = $member->isEmpty() ? 0 : max(0, (float)$member->commission);
            $deduct = min($available, $remaining);
            $reverseLogId = 0;
            if ($deduct > 0) {
                $reverseLogId = (int)(new CoreMemberAccountService())->addLog(
                    (int)$detail->site_id, (int)$detail->beneficiary_member_id,
                    MemberAccountTypeDict::COMMISSION, -$deduct,
                    'project_center_distribution_reverse',
                    '项目退款，推广佣金冲红', (int)$detail->id
                );
            }
            $debtAmount = round($remaining - $deduct, 2);
            if ($debtAmount > 0) {
                $debt = ProjectCenterDistributionDebt::where('detail_id', '=', $detailId)->lock(true)->findOrEmpty();
                if ($debt->isEmpty()) {
                    ProjectCenterDistributionDebt::create([
                        'site_id' => (int)$detail->site_id, 'member_id' => (int)$detail->beneficiary_member_id,
                        'detail_id' => $detailId, 'amount' => $debtAmount, 'offset_amount' => 0,
                        'status' => 'pending', 'reason' => '项目退款时可用佣金不足，后续佣金自动抵扣',
                        'create_at' => time(), 'update_at' => time(),
                    ]);
                } else {
                    $debt->save(['amount' => round((float)$debt->amount + $debtAmount, 2), 'status' => 'pending', 'update_at' => time()]);
                }
            }
            $newReversed = round((float)$detail->reversed_amount + $remaining, 2);
            $detail->save([
                'reversed_amount' => $newReversed,
                'debt_amount' => round((float)$detail->debt_amount + $debtAmount, 2),
                'reverse_log_id' => $reverseLogId ?: (int)$detail->reverse_log_id,
                'status' => $newReversed >= (float)$detail->commission_amount ? 'reversed' : 'partial_reversed',
                'update_at' => time(),
            ]);
            return $remaining;
        });
    }

    private function sendChangeNotice(string $key, int $detailId, float $changeAmount = 0): void
    {
        try {
            $siteId = (int)ProjectCenterDistributionDetail::where('id', '=', $detailId)->value('site_id');
            if ($siteId > 0) NoticeService::send($siteId, $key, [
                'detail_id' => $detailId, 'change_amount' => round($changeAmount, 2),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[hsx_project_center] 分销佣金通知发送失败', [
                'key' => $key, 'detail_id' => $detailId, 'message' => $e->getMessage(),
            ]);
        }
    }

    private function refreshOrderStatus(int $orderId): void
    {
        $statuses = ProjectCenterDistributionDetail::where('order_id', '=', $orderId)->column('status');
        if ($statuses === []) return;
        if (in_array('exception', $statuses, true)) $status = 'exception';
        elseif (in_array('frozen', $statuses, true)) $status = 'frozen';
        elseif (in_array('pending', $statuses, true)) $status = 'pending';
        elseif (in_array('partial_reversed', $statuses, true)) $status = 'partial_reversed';
        elseif (array_diff($statuses, ['cancelled', 'reversed']) === []) $status = in_array('reversed', $statuses, true) ? 'reversed' : 'cancelled';
        else $status = 'settled';
        $data = ['status' => $status, 'update_at' => time(), 'error_message' => $status === 'exception' ? (string)ProjectCenterDistributionOrder::where('id', '=', $orderId)->value('error_message') : ''];
        if ($status === 'settled') $data['settled_at'] = time();
        if (in_array($status, ['cancelled', 'reversed'], true)) $data['cancelled_at'] = time();
        ProjectCenterDistributionOrder::where('id', '=', $orderId)->update($data);
    }
}
