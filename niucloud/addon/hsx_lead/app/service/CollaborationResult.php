<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\service;

/** Result feedback is not a customer sale, a quantity confirmation or a payment. */
final class CollaborationResult
{
    public static function reported(array $task): bool
    {
        return in_array($task['completion_status'] ?? '', ['reported_deal', 'reported_no_deal', 'reported_cancelled'], true);
    }

    public static function legacy(array $task, bool $hasLedger = false): bool
    {
        if ((int)($task['deal_revision'] ?? 0) > 0 || (int)($task['last_verified_revision'] ?? 0) > 0
            || (int)($task['verified_at'] ?? 0) > 0 || (int)($task['verified_uid'] ?? 0) > 0) return true;
        if (($task['deal_amount'] ?? null) !== null && $task['deal_amount'] !== '') return true;
        if (trim((string)($task['deal_no'] ?? '')) !== '' || trim((string)($task['evidence'] ?? '')) !== '') return true;
        foreach (['deal_items_json', 'confirmed_items_json', 'deal_history_json'] as $key) {
            if (!in_array(trim((string)($task[$key] ?? '')), ['', '[]', 'null'], true)) return true;
        }
        if (self::reported($task)) return false;
        return $hasLedger || ($task['commission_amount'] ?? null) !== null || ($task['status'] ?? '') === 'deal_pending';
    }

    public static function voluntaryLedger(array $task, array $ledger): bool
    {
        return (int)($ledger['deal_revision'] ?? 0) === 0 && self::reported($task) && !self::legacy($task);
    }

    public static function view(array $task, array $lead, ?array $ledger, bool $canAct): array
    {
        $completion = (string)($task['completion_status'] ?? 'none');
        $outcome = in_array($completion, ['reported_deal', 'reported_cancelled'], true) ? 'deal' : ($completion === 'reported_no_deal' ? 'no_deal' : '');
        $amount = ($task['commission_amount'] ?? null) === null ? null : bcadd((string)$task['commission_amount'], '0', 2);
        $shared = $amount !== null && bccomp($amount, '0', 2) > 0;
        $shareStatus = $ledger ? (string)$ledger['status'] : 'none';
        $resultLabel = match ((string)$task['status']) {
            'assigned' => '待接单', 'following' => '跟进中', 'rejected' => '未接单', 'reassigned' => '已转派',
            'cancelled' => '已撤销',
            default => $outcome === 'deal' ? '已成单' : ($outcome === 'no_deal' ? '未成单' : '已结束（未记录结果）'),
        };
        $shareLabel = match ($shareStatus) {
            'unpaid' => '待平台确认到账', 'paid' => '平台已确认到账', 'void' => '分享登记已作废',
            'refund_pending' => '待退回分享收益', 'refunded' => '分享收益已退回', default => '未登记分享收益',
        };
        if ($task['status'] === 'assigned') $next = '接单后联系客户，客户交易款由商户直接收取，平台不代收。';
        elseif ($task['status'] === 'following') $next = '联系客户并自行成交，结束后反馈已成单或未成单；分享收益可不填。';
        elseif ($task['status'] === 'cancelled') $next = $shareStatus === 'refund_pending' ? '该合作已撤销；实际退回分享收益后，由平台登记退款。' : '该合作已撤销，不再继续跟进或登记新的分享收益。';
        elseif ($outcome === 'no_deal') $next = '已结束本次跟进，无需提交销售资料或登记分享收益。';
        elseif ($outcome === 'deal' && !$shared) $next = '成单结果已反馈，无需平台核验；如愿意分享收益，可稍后补登记。';
        elseif ($shareStatus === 'unpaid') $next = '已登记自愿分享收益，实际到账后由平台确认；系统不会自动扣款或代收客户交易款。';
        elseif ($shareStatus === 'paid') $next = '平台已确认分享收益实际到账，本次合作已完成。';
        else $next = '本次跟进已结束；没有记录的客户成交金额和数量不作推断。';
        $requirements = DealProgress::requirements($lead);
        foreach ($requirements as &$requirement) foreach (['confirmed_quantity', 'remaining_quantity', 'confirmed_amount', 'alternative_not_selected', 'can_report'] as $field) unset($requirement[$field]);
        unset($requirement);
        return ['collaboration_mode' => 'simple', 'outcome' => $outcome, 'share_amount' => $amount,
            'result_label' => $resultLabel, 'has_shared_revenue' => $shared, 'share_status' => $shareStatus,
            'share_status_label' => $shareLabel, 'share_is_received' => $shareStatus === 'paid', 'next_action' => $next,
            'can_report_outcome' => $canAct && $task['status'] === 'following',
            'can_share' => $canAct && $task['status'] === 'completed' && $completion === 'reported_deal' && !$shared && !$ledger,
            'requirements' => $requirements, 'total_confirmed_amount' => null, 'deal_amount' => null,
            'deal_items' => [], 'deal_history' => [], 'any_selected_catalog_id' => null, 'completion_status' => $completion];
    }
}
