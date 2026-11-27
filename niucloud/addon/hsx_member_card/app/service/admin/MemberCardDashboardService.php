<?php
declare(strict_types=1);
namespace addon\hsx_member_card\app\service\admin;
use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\model\MemberCardOrder;
use addon\hsx_member_card\app\model\MemberCardRedemption;
use addon\hsx_member_card\app\model\MemberCardRefund;
use core\base\BaseAdminService;
final class MemberCardDashboardService extends BaseAdminService
{
    public function overview(array $where): array
    {
        $startAt = max(0, (int)($where['start_at'] ?? strtotime('today')));
        $endAt = max($startAt, (int)($where['end_at'] ?? time()));
        $orders = MemberCardOrder::where([['site_id', '=', $this->site_id]])->whereBetween('create_at', [$startAt, $endAt])->whereIn('business_status', ['active', 'refund_pending', 'refunded']);
        $redemptions = MemberCardRedemption::where([['site_id', '=', $this->site_id], ['status', '=', 'success']])->whereBetween('occurred_at', [$startAt, $endAt]);
        return [
            'start_at' => $startAt, 'end_at' => $endAt,
            'issued_count' => (int)(clone $orders)->count(),
            'card_sale_amount' => round((float)(clone $orders)->sum('order_amount'), 2),
            'actual_received_amount' => round((float)(clone $orders)->sum('paid_amount'), 2),
            'pending_receivable_amount' => max(0, round((float)(clone $orders)->sum('order_amount') - (float)(clone $orders)->sum('paid_amount'), 2)),
            'active_card_count' => (int)MemberCard::where([['site_id', '=', $this->site_id], ['status', '=', 'active']])->count(),
            'redemption_count' => (int)(clone $redemptions)->count(),
            'recognized_amount' => round((float)(clone $redemptions)->sum('recognized_amount'), 2),
            'refund_amount' => round((float)MemberCardRefund::where([['site_id', '=', $this->site_id], ['status', '=', 'paid']])->whereBetween('paid_at', [$startAt, $endAt])->sum('refund_amount'), 2),
        ];
    }
}
