<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\listener;

use addon\hsx_member_card\app\model\MemberCardOrder;
use addon\hsx_member_card\app\model\MemberCardRefund;

/**
 * 为 ERP 财务列表批量补齐会员卡历史单据的展示信息。
 *
 * 这是只读投影：ERP 不引用会员卡表，会员卡也不修改 ERP 财务事实；新单据仍以
 * 财务事实中的不可变快照为准，本监听器只解决升级前数据没有快照的问题。
 */
final class MemberCardFinanceDisplayRows
{
    public function handle($payload): array
    {
        if (!is_array($payload)) return ['rows' => []];
        $siteId = max(0, (int)($payload['site_id'] ?? 0));
        $rows = array_values(array_filter((array)($payload['rows'] ?? []), static function ($row): bool {
            return is_array($row) && (string)($row['origin_plugin'] ?? '') === 'hsx_member_card';
        }));
        if ($siteId <= 0 || empty($rows)) return ['rows' => []];

        $orderNos = [];
        $refundNos = [];
        foreach ($rows as $row) {
            $originNo = trim((string)($row['origin_no'] ?? $row['source_no'] ?? ''));
            if ($originNo === '') continue;
            $originType = (string)($row['origin_type'] ?? $row['source_type'] ?? '');
            if (str_contains($originType, 'card_refund')) $refundNos[] = $originNo;
            else $orderNos[] = $originNo;
        }

        $orders = empty($orderNos) ? [] : MemberCardOrder::where([['site_id', '=', $siteId]])
            ->whereIn('order_no', array_values(array_unique($orderNos)))
            ->field('order_no,settlement_mode,issuer_uid,issuer_name')
            ->select()->toArray();
        $orderMap = [];
        foreach ($orders as $order) $orderMap[(string)$order['order_no']] = $order;

        $refunds = empty($refundNos) ? [] : MemberCardRefund::where([['site_id', '=', $siteId]])
            ->whereIn('refund_no', array_values(array_unique($refundNos)))
            ->field('refund_no,refund_mode,apply_uid,apply_name')
            ->select()->toArray();
        $refundMap = [];
        foreach ($refunds as $refund) $refundMap[(string)$refund['refund_no']] = $refund;

        $result = [];
        foreach ($rows as $row) {
            $erpRowId = (int)($row['id'] ?? 0);
            $originNo = trim((string)($row['origin_no'] ?? $row['source_no'] ?? ''));
            if ($erpRowId <= 0 || $originNo === '') continue;
            if (isset($orderMap[$originNo])) {
                $order = $orderMap[$originNo];
                $result[$erpRowId] = [
                    'opening_settle_method' => (string)$order['settlement_mode'] === 'immediate' ? '现结' : '挂账',
                    'business_operator_uid' => (int)$order['issuer_uid'],
                    'business_operator_name' => (string)$order['issuer_name'],
                ];
            } elseif (isset($refundMap[$originNo])) {
                $refund = $refundMap[$originNo];
                $result[$erpRowId] = [
                    'opening_settle_method' => (string)$refund['refund_mode'] === 'immediate' ? '现退' : '财务退款',
                    'business_operator_uid' => (int)$refund['apply_uid'],
                    'business_operator_name' => (string)$refund['apply_name'],
                ];
            }
        }
        return ['rows' => $result];
    }
}
