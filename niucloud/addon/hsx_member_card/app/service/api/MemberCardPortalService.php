<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\api;

use addon\hsx_member_card\app\dict\MemberCardDict;
use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\model\MemberCardItem;
use addon\hsx_member_card\app\model\MemberCardOrder;
use addon\hsx_member_card\app\model\MemberCardRedemption;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 会员自助查询门户。
 *
 * 这里只提供当前登录会员自己的只读数据，不承载购卡、核销或退款动作。
 */
final class MemberCardPortalService extends BaseApiService
{
    public function overview(): array
    {
        $cards = MemberCard::where($this->memberCondition())
            ->whereNotIn('status', [MemberCardDict::CARD_CANCELLED, MemberCardDict::CARD_REFUNDED])
            ->field('id,status,valid_end_at')
            ->select()
            ->toArray();
        $cardIds = array_map('intval', array_column($cards, 'id'));
        $items = $cardIds === [] ? [] : MemberCardItem::where([
            ['site_id', '=', (int)$this->site_id],
            ['status', '=', 1],
        ])->whereIn('card_id', $cardIds)
            ->field('card_id,binding_mode,bound_imei,bound_model,usage_mode,granted_times,used_times,remaining_times')
            ->select()
            ->toArray();

        $availableCards = 0;
        $remainingTimes = 0;
        $usedTimes = 0;
        foreach ($cards as $card) {
            if ($this->effectiveCardStatus($card) === MemberCardDict::CARD_ACTIVE) $availableCards++;
        }
        foreach ($items as $item) {
            if ((string)$item['usage_mode'] === 'limited') $remainingTimes += max(0, (int)$item['remaining_times']);
            $usedTimes += max(0, (int)$item['used_times']);
        }

        $latest = MemberCardRedemption::where($this->memberCondition())
            ->field('id,redeem_no,card_id,item_name,binding_mode,service_imei,service_model,after_remaining,status,operator_name,occurred_at,reversed_at')
            ->order('id desc')
            ->findOrEmpty()
            ->toArray();
        if ($latest !== []) $latest = $this->formatRedemption($latest);

        return [
            'card_count' => count($cards),
            'available_card_count' => $availableCards,
            'remaining_times' => $remainingTimes,
            'used_times' => $usedTimes,
            'latest_redemption' => $latest,
        ];
    }

    public function cards(array $where): array
    {
        $query = MemberCard::where($this->memberCondition());
        $status = trim((string)($where['status'] ?? ''));
        if ($status !== '') {
            if ($status === 'available') $query->whereIn('status', [MemberCardDict::CARD_ACTIVE, MemberCardDict::CARD_PENDING]);
            else $query->where('status', '=', $status);
        }
        $page = $query
            ->field('id,card_no,order_id,order_no,product_id,product_name,status,finance_status,effective_mode,activated_at,first_used_at,valid_start_at,valid_end_at,freeze_reason,issuer_name,create_at,update_at')
            ->order('id desc')
            ->paginate($this->pageOptions($where))
            ->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        $rows = (array)($page[$key] ?? []);
        $cardIds = array_map('intval', array_column($rows, 'id'));
        $items = $cardIds === [] ? [] : MemberCardItem::where([
            ['site_id', '=', (int)$this->site_id],
            ['status', '=', 1],
        ])->whereIn('card_id', $cardIds)
            ->field('id,card_id,item_code,item_name,binding_mode,bound_imei,bound_model,usage_mode,granted_times,used_times,remaining_times,reversed_times,daily_limit')
            ->order('id asc')
            ->select()
            ->toArray();
        $itemMap = [];
        foreach ($items as $item) $itemMap[(int)$item['card_id']][] = $this->formatItem($item);
        foreach ($rows as &$row) {
            $row = $this->formatCard($row);
            $row['items'] = $itemMap[(int)$row['id']] ?? [];
        }
        unset($row);
        $page[$key] = $rows;
        return $page;
    }

    public function orders(array $where): array
    {
        $query = MemberCardOrder::where($this->memberCondition());
        $status = trim((string)($where['status'] ?? ''));
        if ($status !== '') $query->where('business_status', '=', $status);
        $page = $query
            ->field('id,order_no,product_id,product_name,order_amount,paid_amount,refunded_amount,settlement_mode,capital_account_name,business_status,finance_status,issuer_name,confirmed_at,cancelled_at,remark,create_at,update_at')
            ->order('id desc')
            ->paginate($this->pageOptions($where))
            ->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        foreach (($page[$key] ?? []) as &$row) {
            $row['business_status_text'] = $this->orderStatusText((string)$row['business_status']);
            $row['finance_status_text'] = $this->financeStatusText((string)$row['finance_status']);
            $row['settlement_mode_text'] = $this->settlementModeText((string)$row['settlement_mode']);
        }
        unset($row);
        return $page;
    }

    public function redemptions(array $where): array
    {
        $query = MemberCardRedemption::where($this->memberCondition());
        $status = trim((string)($where['status'] ?? ''));
        if ($status !== '') $query->where('status', '=', $status);
        $page = $query
            ->field('id,redeem_no,card_id,card_no,card_item_id,item_code,item_name,binding_mode,service_imei,service_model,redeem_times,before_remaining,after_remaining,operator_name,status,reversed_at,reverse_name,reverse_reason,remark,occurred_at,create_at,update_at')
            ->order('id desc')
            ->paginate($this->pageOptions($where))
            ->toArray();
        $key = isset($page['data']) ? 'data' : 'list';
        foreach (($page[$key] ?? []) as &$row) $row = $this->formatRedemption($row);
        unset($row);
        return $page;
    }

    public function cardInfo(int $cardId): array
    {
        $card = MemberCard::where(array_merge($this->memberCondition(), [['id', '=', $cardId]]))
            ->field('id,card_no,order_id,order_no,product_id,product_name,status,finance_status,effective_mode,activated_at,first_used_at,valid_start_at,valid_end_at,freeze_reason,issuer_name,create_at,update_at')
            ->findOrEmpty();
        if ($card->isEmpty()) throw new CommonException('会员卡不存在或无权查看');
        $data = $this->formatCard($card->toArray());
        $data['items'] = array_map(
            fn(array $item): array => $this->formatItem($item),
            MemberCardItem::where([
                ['site_id', '=', (int)$this->site_id],
                ['card_id', '=', $cardId],
                ['status', '=', 1],
            ])->field('id,card_id,item_code,item_name,binding_mode,bound_imei,bound_model,usage_mode,granted_times,used_times,remaining_times,reversed_times,daily_limit')
                ->order('id asc')
                ->select()
                ->toArray()
        );
        $order = MemberCardOrder::where(array_merge($this->memberCondition(), [['id', '=', (int)$card->order_id]]))
            ->field('id,order_no,product_name,order_amount,paid_amount,refunded_amount,settlement_mode,business_status,finance_status,issuer_name,confirmed_at,remark,create_at')
            ->findOrEmpty()
            ->toArray();
        if ($order !== []) {
            $order['business_status_text'] = $this->orderStatusText((string)$order['business_status']);
            $order['finance_status_text'] = $this->financeStatusText((string)$order['finance_status']);
            $order['settlement_mode_text'] = $this->settlementModeText((string)$order['settlement_mode']);
        }
        $data['order'] = $order;
        $data['redemptions'] = array_map(
            fn(array $row): array => $this->formatRedemption($row),
            MemberCardRedemption::where(array_merge($this->memberCondition(), [['card_id', '=', $cardId]]))
                ->field('id,redeem_no,card_id,card_no,card_item_id,item_code,item_name,binding_mode,service_imei,service_model,redeem_times,before_remaining,after_remaining,operator_name,status,reversed_at,reverse_name,reverse_reason,remark,occurred_at,create_at,update_at')
                ->order('id desc')
                ->limit(100)
                ->select()
                ->toArray()
        );
        return $data;
    }

    private function memberCondition(): array
    {
        return [
            ['site_id', '=', (int)$this->site_id],
            ['member_id', '=', (int)$this->member_id],
        ];
    }

    private function pageOptions(array $where): array
    {
        return [
            'list_rows' => max(1, min(50, (int)($where['limit'] ?? 10))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ];
    }

    private function formatCard(array $row): array
    {
        $row['status'] = $this->effectiveCardStatus($row);
        $row['status_text'] = $this->cardStatusText((string)$row['status']);
        $row['validity_text'] = $this->validityText($row);
        return $row;
    }

    private function formatItem(array $row): array
    {
        $row['usage_mode_text'] = (string)$row['usage_mode'] === 'unlimited' ? '不限次数' : '按次使用';
        $row['binding_mode_text'] = [
            'imei' => '绑定设备',
            'model' => '限定型号',
            'member' => '按会员本人',
        ][(string)($row['binding_mode'] ?? 'member')] ?? '按会员本人';
        return $row;
    }

    private function formatRedemption(array $row): array
    {
        $row['status_text'] = (string)($row['status'] ?? '') === 'reversed' ? '已撤销' : '核销成功';
        return $row;
    }

    private function effectiveCardStatus(array $row): string
    {
        $status = (string)($row['status'] ?? '');
        if (in_array($status, [MemberCardDict::CARD_ACTIVE, MemberCardDict::CARD_PENDING], true)
            && (int)($row['valid_end_at'] ?? 0) > 0
            && (int)$row['valid_end_at'] < time()) {
            return MemberCardDict::CARD_EXPIRED;
        }
        return $status;
    }

    private function validityText(array $row): string
    {
        if ((int)($row['valid_end_at'] ?? 0) <= 0) return '长期有效';
        return date('Y-m-d', (int)$row['valid_end_at']) . ' 到期';
    }

    private function cardStatusText(string $status): string
    {
        return [
            MemberCardDict::CARD_PENDING => '待激活',
            MemberCardDict::CARD_ACTIVE => '可使用',
            MemberCardDict::CARD_EXHAUSTED => '已用完',
            MemberCardDict::CARD_EXPIRED => '已过期',
            MemberCardDict::CARD_FROZEN => '已冻结',
            MemberCardDict::CARD_REFUND_PENDING => '退款中',
            MemberCardDict::CARD_REFUNDED => '已退款',
            MemberCardDict::CARD_CANCELLED => '已取消',
        ][$status] ?? $status;
    }

    private function orderStatusText(string $status): string
    {
        return [
            MemberCardDict::ORDER_PROCESSING => '处理中',
            MemberCardDict::ORDER_ACTIVE => '已开卡',
            MemberCardDict::ORDER_CANCELLED => '已取消',
            MemberCardDict::ORDER_REFUND_PENDING => '退款中',
            MemberCardDict::ORDER_REFUNDED => '已退款',
            MemberCardDict::ORDER_FAILED => '处理失败',
        ][$status] ?? $status;
    }

    private function financeStatusText(string $status): string
    {
        return [
            'processing' => '处理中',
            'pending' => '待结算',
            'partial' => '部分结算',
            'settled' => '已结清',
            'failed' => '处理失败',
            'cancelled' => '已取消',
        ][$status] ?? $status;
    }

    private function settlementModeText(string $mode): string
    {
        return [
            'immediate' => '现场收款',
            'receivable' => '财务挂账',
            'opening' => '期初导入',
        ][$mode] ?? $mode;
    }
}
