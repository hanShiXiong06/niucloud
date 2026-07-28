<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\dict\MemberCardDict;
use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\model\MemberCardFinanceLink;
use addon\hsx_member_card\app\model\MemberCardItem;
use addon\hsx_member_card\app\model\MemberCardOperationLog;
use addon\hsx_member_card\app\model\MemberCardOrder;
use addon\hsx_member_card\app\model\MemberCardRedemption;
use addon\hsx_member_card\app\service\core\MemberCardConfigService;
use addon\hsx_member_card\app\service\core\MemberCardNoticeService;
use addon\hsx_member_card\app\support\MemberCardIdempotency;
use addon\hsx_member_card\app\support\MemberCardMoney;
use addon\hsx_member_card\app\support\MemberCardNumber;
use addon\hsx_member_card\app\support\MemberCardValidity;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

final class MemberCardRedemptionService extends BaseAdminService
{
    public function search(array $where): array
    {
        $keyword = trim((string)($where['mobile_keyword'] ?? ''));
        if (!preg_match('/^\d{4}$|^1\d{10}$/', $keyword)) {
            throw new CommonException('请输入完整11位手机号或手机号后四位');
        }
        $query = MemberCard::where([['site_id', '=', $this->site_id]])
            ->whereNotIn('status', [MemberCardDict::CARD_CANCELLED, MemberCardDict::CARD_REFUNDED]);
        if (strlen($keyword) === 11) $query->where('holder_mobile', '=', $keyword);
        else $query->where('holder_mobile_last4', '=', $keyword);
        $name = trim((string)($where['name'] ?? ''));
        if ($name !== '') $query->where('holder_name', '=', $name);
        $cards = $query->order('status asc,id desc')->limit(100)->select()->toArray();
        $this->refreshTimeStatuses($cards);
        if ($cards === []) return ['candidates' => []];

        $ids = array_map('intval', array_column($cards, 'id'));
        $items = MemberCardItem::where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->whereIn('card_id', $ids)->order('id asc')->select()->toArray();
        $itemMap = [];
        foreach ($items as $item) $itemMap[(int)$item['card_id']][] = $item;
        $candidates = [];
        foreach ($cards as &$card) {
            $memberId = (int)$card['member_id'];
            $candidates[$memberId] ??= [
                'member_id' => $memberId,
                'holder_name' => (string)$card['holder_name'],
                'mobile_masked' => $this->maskMobile((string)$card['holder_mobile']),
                'available_card_count' => 0,
                'cards' => [],
            ];
            $cardItems = $itemMap[(int)$card['id']] ?? [];
            $firstItem = $cardItems[0] ?? [];
            $available = $this->isAvailableCard($card, $firstItem);
            if ($available) $candidates[$memberId]['available_card_count']++;
            $candidates[$memberId]['cards'][] = [
                'card_id' => (int)$card['id'],
                'card_no' => (string)$card['card_no'],
                'product_name' => (string)$card['product_name'],
                'status' => (string)$card['status'],
                'finance_status' => (string)$card['finance_status'],
                'item_id' => (int)($firstItem['id'] ?? 0),
                'item_name' => (string)($firstItem['item_name'] ?? ''),
                'usage_mode' => (string)($firstItem['usage_mode'] ?? ''),
                'remaining_times' => (int)($firstItem['remaining_times'] ?? 0),
                'validity_text' => $this->validityText($card),
                'available' => $available,
                'recommended' => false,
            ];
        }
        unset($card);
        foreach ($candidates as &$candidate) {
            foreach ($candidate['cards'] as &$card) {
                if ($card['available']) { $card['recommended'] = true; break; }
            }
            unset($card);
        }
        unset($candidate);
        return ['candidates' => array_values($candidates)];
    }

    public function cardInfo(int $cardId): array
    {
        $card = $this->findCard($cardId);
        $timeRows = [$card->toArray()];
        $this->refreshTimeStatuses($timeRows);
        $row = $this->findCard($cardId)->toArray();
        $row['mobile_masked'] = $this->maskMobile((string)$row['holder_mobile']);
        $row['validity_text'] = $this->validityText($row);
        $row['items'] = MemberCardItem::where([['site_id', '=', $this->site_id], ['card_id', '=', $cardId]])->order('id asc')->select()->toArray();
        $row['redemptions'] = MemberCardRedemption::where([['site_id', '=', $this->site_id], ['card_id', '=', $cardId]])->order('id desc')->limit(50)->select()->toArray();
        $row['order'] = MemberCardOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$row['order_id']]])->findOrEmpty()->toArray();
        $row['finance'] = MemberCardFinanceLink::where([['site_id', '=', $this->site_id], ['biz_type', '=', 'card_sale'], ['biz_id', '=', (int)$row['order_id']]])->findOrEmpty()->toArray();
        return $row;
    }

    public function redeem(int $cardId, array $data): array
    {
        $requestId = MemberCardIdempotency::normalize($data['request_id'] ?? '');
        $existing = MemberCardRedemption::where([['site_id', '=', $this->site_id], ['request_id', '=', $requestId]])->findOrEmpty();
        if (!$existing->isEmpty()) return $this->redeemResponse($existing->toArray(), '重复提交已返回原核销结果');
        if ((int)($data['verification_confirmed'] ?? 0) !== 1) throw new CommonException('请先向客户核对购卡姓名并确认一致');

        $redemption = [];
        Db::transaction(function () use ($cardId, $data, $requestId, &$redemption): void {
            $card = MemberCard::where([['site_id', '=', $this->site_id], ['id', '=', $cardId]])->lock(true)->findOrEmpty();
            if ($card->isEmpty()) throw new CommonException('会员卡不存在');
            $this->assertCardRedeemable($card->toArray());
            $itemQuery = MemberCardItem::where([['site_id', '=', $this->site_id], ['card_id', '=', $cardId], ['status', '=', 1]]);
            if ((int)($data['card_item_id'] ?? 0) > 0) $itemQuery->where('id', '=', (int)$data['card_item_id']);
            $item = $itemQuery->order('id asc')->lock(true)->findOrEmpty();
            if ($item->isEmpty()) throw new CommonException('会员卡没有可核销的服务权益');
            if ((string)$item->usage_mode === 'limited' && (int)$item->remaining_times <= 0) throw new CommonException('该卡服务次数已用完');
            if ((int)$item->daily_limit > 0) {
                $todayCount = MemberCardRedemption::where([
                    ['site_id', '=', $this->site_id], ['card_id', '=', $cardId], ['card_item_id', '=', (int)$item->id], ['status', '=', 'success'],
                ])->whereBetween('occurred_at', [strtotime('today'), strtotime('tomorrow') - 1])->count();
                if ($todayCount >= (int)$item->daily_limit) throw new CommonException('该服务今日核销次数已达到上限');
            }

            $now = time();
            $snapshot = $this->decode((string)$card->rule_snapshot);
            $rule = (array)($snapshot['product'] ?? []);
            $validity = ['valid_start_at' => (int)$card->valid_start_at, 'valid_end_at' => (int)$card->valid_end_at, 'activated_at' => (int)$card->activated_at];
            if ((string)$card->effective_mode === 'first_use' && (int)$card->first_used_at <= 0) {
                $validity = MemberCardValidity::calculate($rule, (int)$card->create_at, $now);
            }
            if ((int)$validity['valid_start_at'] > $now) throw new CommonException('该卡尚未到生效时间');
            if (MemberCardValidity::isExpired((int)$validity['valid_end_at'], $now)) throw new CommonException('该卡已过期，不能核销');

            $beforeRemaining = (int)$item->remaining_times;
            $afterRemaining = (string)$item->usage_mode === 'limited' ? $beforeRemaining - 1 : 0;
            $recognized = $this->recognizedAmount($item->toArray());
            $item->save([
                'used_times' => (int)$item->used_times + 1,
                'remaining_times' => $afterRemaining,
                'recognized_amount' => MemberCardMoney::add($item->recognized_amount, $recognized),
                'update_at' => $now,
            ]);
            $cardStatus = (string)$item->usage_mode === 'limited' && $afterRemaining <= 0 ? MemberCardDict::CARD_EXHAUSTED : MemberCardDict::CARD_ACTIVE;
            $card->save([
                'status' => $cardStatus,
                'first_used_at' => (int)$card->first_used_at > 0 ? (int)$card->first_used_at : $now,
                'activated_at' => (int)$validity['activated_at'],
                'valid_start_at' => (int)$validity['valid_start_at'],
                'valid_end_at' => (int)$validity['valid_end_at'],
                'update_at' => $now,
            ]);
            $row = MemberCardRedemption::create([
                'site_id' => (int)$this->site_id,
                'redeem_no' => MemberCardNumber::make('MR'),
                'request_id' => $requestId,
                'card_id' => $cardId,
                'card_no' => (string)$card->card_no,
                'card_item_id' => (int)$item->id,
                'member_id' => (int)$card->member_id,
                'holder_name' => (string)$card->holder_name,
                'holder_mobile' => (string)$card->holder_mobile,
                'item_code' => (string)$item->item_code,
                'item_name' => (string)$item->item_name,
                'redeem_times' => 1,
                'before_remaining' => $beforeRemaining,
                'after_remaining' => $afterRemaining,
                'recognized_amount' => $recognized,
                'verification_mode' => 'mobile_name_manual',
                'verification_confirmed' => 1,
                'operator_uid' => (int)$this->uid,
                'operator_name' => (string)$this->username,
                'status' => 'success',
                'remark' => mb_substr(trim((string)($data['remark'] ?? '')), 0, 255),
                'occurred_at' => $now,
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $redemption = $row->toArray();
            (new MemberCardAuditService())->record('redemption', (int)$row->id, (string)$row->redeem_no, 'redeem', [], $redemption);
        });
        $this->recordStaffFact($redemption, false);
        (new MemberCardNoticeService())->sendRedeemSuccess($redemption);
        return $this->redeemResponse($redemption, (string)$redemption['item_name'] . '核销成功');
    }

    public function reverse(int $id, array $data): array
    {
        MemberCardIdempotency::normalize($data['request_id'] ?? '');
        $reason = mb_substr(trim((string)($data['reason'] ?? '')), 0, 255);
        if ($reason === '') throw new CommonException('核销冲正必须填写原因');
        $result = [];
        $reversedNow = false;
        Db::transaction(function () use ($id, $reason, &$result, &$reversedNow): void {
            $redemption = MemberCardRedemption::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->lock(true)->findOrEmpty();
            if ($redemption->isEmpty()) throw new CommonException('核销记录不存在');
            if ((string)$redemption->status === 'reversed') { $result = $redemption->toArray(); return; }
            if ((string)$redemption->status !== 'success') throw new CommonException('只有成功核销记录可以冲正');
            $card = MemberCard::where([['site_id', '=', $this->site_id], ['id', '=', (int)$redemption->card_id]])->lock(true)->findOrEmpty();
            $item = MemberCardItem::where([['site_id', '=', $this->site_id], ['id', '=', (int)$redemption->card_item_id]])->lock(true)->findOrEmpty();
            if ($card->isEmpty() || $item->isEmpty()) throw new CommonException('核销关联的会员卡权益不存在');
            if (in_array((string)$card->status, [MemberCardDict::CARD_REFUND_PENDING, MemberCardDict::CARD_REFUNDED, MemberCardDict::CARD_CANCELLED], true)) throw new CommonException('退款或取消中的会员卡不能核销冲正');
            $before = $redemption->toArray();
            $now = time();
            $recognizedAfter = MemberCardMoney::subtract($item->recognized_amount, $redemption->recognized_amount);
            if (MemberCardMoney::compare($recognizedAfter, '0') < 0) $recognizedAfter = '0.00';
            $item->save([
                'used_times' => max(0, (int)$item->used_times - 1),
                'remaining_times' => (string)$item->usage_mode === 'limited' ? (int)$item->remaining_times + 1 : 0,
                'reversed_times' => (int)$item->reversed_times + 1,
                'recognized_amount' => $recognizedAfter,
                'update_at' => $now,
            ]);
            $status = MemberCardValidity::isExpired((int)$card->valid_end_at, $now) ? MemberCardDict::CARD_EXPIRED : MemberCardDict::CARD_ACTIVE;
            $card->save(['status' => $status, 'update_at' => $now]);
            $redemption->save([
                'status' => 'reversed',
                'reversed_at' => $now,
                'reverse_uid' => (int)$this->uid,
                'reverse_name' => (string)$this->username,
                'reverse_reason' => $reason,
                'update_at' => $now,
            ]);
            $result = $redemption->toArray();
            $reversedNow = true;
            (new MemberCardAuditService())->record('redemption', (int)$redemption->id, (string)$redemption->redeem_no, 'reverse', $before, $result);
        });
        if ($reversedNow) {
            $this->recordStaffFact($result, true);
            (new MemberCardNoticeService())->sendRedeemReversed($result);
        }
        return $this->redeemResponse($result, '核销已冲正，次数已恢复');
    }

    public function redemptionLists(array $where): array
    {
        $query = MemberCardRedemption::where([['site_id', '=', $this->site_id]]);
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') $query->whereLike('redeem_no|card_no|holder_name|holder_mobile|item_name|operator_name', '%' . $keyword . '%');
        if (trim((string)($where['status'] ?? '')) !== '') $query->where('status', '=', (string)$where['status']);
        return $query->order('id desc')->paginate([
            'list_rows' => min(100, max(1, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
    }

    public function redemptionInfo(int $id): array
    {
        $row = MemberCardRedemption::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('核销记录不存在');
        $data = $row->toArray();
        $data['operation_logs'] = MemberCardOperationLog::where([['site_id', '=', $this->site_id], ['biz_type', '=', 'redemption'], ['biz_id', '=', $id]])->order('id desc')->select()->toArray();
        return $data;
    }

    public function freeze(int $cardId, string $reason): bool
    {
        $reason = mb_substr(trim($reason), 0, 255);
        if ($reason === '') throw new CommonException('请填写冻结原因');
        $card = $this->findCard($cardId);
        if (!in_array((string)$card->status, [MemberCardDict::CARD_ACTIVE, MemberCardDict::CARD_PENDING, MemberCardDict::CARD_EXHAUSTED], true)) throw new CommonException('当前卡状态不能冻结');
        $before = $card->toArray();
        $card->save(['status' => MemberCardDict::CARD_FROZEN, 'freeze_reason' => $reason, 'update_at' => time()]);
        (new MemberCardAuditService())->record('card', $cardId, (string)$card->card_no, 'freeze', $before, $card->toArray());
        return true;
    }

    public function unfreeze(int $cardId): bool
    {
        $card = $this->findCard($cardId);
        if ((string)$card->status !== MemberCardDict::CARD_FROZEN) throw new CommonException('当前卡未冻结');
        $items = MemberCardItem::where([['site_id', '=', $this->site_id], ['card_id', '=', $cardId], ['status', '=', 1]])->select()->toArray();
        $hasAvailable = false;
        foreach ($items as $item) if ((string)$item['usage_mode'] === 'unlimited' || (int)$item['remaining_times'] > 0) $hasAvailable = true;
        $status = MemberCardValidity::isExpired((int)$card->valid_end_at) ? MemberCardDict::CARD_EXPIRED : ($hasAvailable ? MemberCardDict::CARD_ACTIVE : MemberCardDict::CARD_EXHAUSTED);
        $before = $card->toArray();
        $card->save(['status' => $status, 'freeze_reason' => '', 'update_at' => time()]);
        (new MemberCardAuditService())->record('card', $cardId, (string)$card->card_no, 'unfreeze', $before, $card->toArray());
        return true;
    }

    private function assertCardRedeemable(array $card): void
    {
        if (!in_array((string)$card['status'], [MemberCardDict::CARD_ACTIVE, MemberCardDict::CARD_PENDING], true)) {
            throw new CommonException('当前会员卡状态不能核销');
        }
        $config = (new MemberCardConfigService())->get((int)$this->site_id);
        if ((int)$config['allow_unpaid_redemption'] !== 1 && (string)$card['finance_status'] !== 'settled') {
            throw new CommonException('该卡尚未结清，当前配置不允许核销');
        }
    }

    private function recognizedAmount(array $item): string
    {
        $snapshot = MemberCard::where([['site_id', '=', $this->site_id], ['id', '=', (int)$item['card_id']]])->value('rule_snapshot');
        $rule = $this->decode((string)$snapshot);
        $productItems = (array)($rule['items'] ?? []);
        $source = [];
        foreach ($productItems as $row) if ((string)($row['item_code'] ?? '') === (string)$item['item_code']) { $source = (array)$row; break; }
        $mode = (string)($source['recognition_mode'] ?? 'average');
        if ($mode === 'none') return '0.00';
        if ($mode === 'fixed') return MemberCardMoney::normalize($source['recognition_amount'] ?? 0);
        if ((string)$item['usage_mode'] === 'limited' && (int)$item['granted_times'] > 0) {
            if ((int)$item['remaining_times'] === 1) return MemberCardMoney::subtract($item['allocated_amount'], $item['recognized_amount']);
            return MemberCardMoney::divide($item['allocated_amount'], (int)$item['granted_times']);
        }
        return '0.00';
    }

    private function recordStaffFact(array $redemption, bool $reverse): void
    {
        $uid = $reverse ? (int)($redemption['reverse_uid'] ?? 0) : (int)($redemption['operator_uid'] ?? 0);
        $name = $reverse ? (string)($redemption['reverse_name'] ?? '') : (string)($redemption['operator_name'] ?? '');
        (new MemberCardStaffFactService())->record([
            'event_id' => 'hsx_member_card:redemption:' . (int)$redemption['id'] . ':' . ($reverse ? 'reverse' : 'success'),
            'fact_type' => $reverse ? 'redeem_reverse' : 'redeem',
            'biz_type' => 'redemption',
            'biz_id' => (int)$redemption['id'],
            'biz_no' => (string)$redemption['redeem_no'],
            'staff_role' => $reverse ? 'reverse_operator' : 'redeemer',
            'staff_uid' => $uid,
            'staff_name' => $name,
            'metric_key' => $reverse ? 'member_card_redeem_reversed' : 'member_card_redeemed',
            'quantity' => 1,
            'amount' => (string)$redemption['recognized_amount'],
            'direction' => $reverse ? -1 : 1,
            'reversal_of_event_id' => $reverse ? 'hsx_member_card:redemption:' . (int)$redemption['id'] . ':success' : '',
            'occurred_at' => time(),
        ]);
    }

    private function refreshTimeStatuses(array &$cards): void
    {
        $now = time();
        foreach ($cards as &$card) {
            $status = (string)($card['status'] ?? '');
            if (!in_array($status, [MemberCardDict::CARD_ACTIVE, MemberCardDict::CARD_PENDING], true)) continue;
            if (MemberCardValidity::isExpired((int)($card['valid_end_at'] ?? 0), $now)) {
                MemberCard::where([['site_id', '=', $this->site_id], ['id', '=', (int)$card['id']]])->update(['status' => MemberCardDict::CARD_EXPIRED, 'update_at' => $now]);
                $card['status'] = MemberCardDict::CARD_EXPIRED;
            } elseif ($status === MemberCardDict::CARD_PENDING && (int)($card['valid_start_at'] ?? 0) > 0 && (int)$card['valid_start_at'] <= $now) {
                MemberCard::where([['site_id', '=', $this->site_id], ['id', '=', (int)$card['id']]])->update(['status' => MemberCardDict::CARD_ACTIVE, 'update_at' => $now]);
                $card['status'] = MemberCardDict::CARD_ACTIVE;
            }
        }
        unset($card);
    }

    private function isAvailableCard(array $card, array $item): bool
    {
        if ((string)$card['status'] !== MemberCardDict::CARD_ACTIVE) return false;
        if (MemberCardValidity::isExpired((int)$card['valid_end_at'])) return false;
        return (string)($item['usage_mode'] ?? '') === 'unlimited' || (int)($item['remaining_times'] ?? 0) > 0;
    }

    private function validityText(array $card): string
    {
        if ((int)($card['valid_start_at'] ?? 0) === 0 && (string)($card['effective_mode'] ?? '') === 'first_use') return '首次使用后开始计时';
        if ((int)($card['valid_end_at'] ?? 0) === 0) return '永久有效';
        return date('Y-m-d', (int)$card['valid_start_at']) . ' 至 ' . date('Y-m-d', (int)$card['valid_end_at']);
    }

    private function redeemResponse(array $row, string $message): array
    {
        $card = $this->findCard((int)$row['card_id']);
        return [
            'redeem_id' => (int)$row['id'],
            'redeem_no' => (string)$row['redeem_no'],
            'card_id' => (int)$row['card_id'],
            'item_name' => (string)$row['item_name'],
            'before_remaining' => (int)$row['before_remaining'],
            'after_remaining' => (int)$row['after_remaining'],
            'card_status' => (string)$card->status,
            'redemption_status' => (string)$row['status'],
            'message' => $message,
        ];
    }

    private function findCard(int $id): MemberCard
    {
        $row = MemberCard::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('会员卡不存在');
        return $row;
    }

    private function maskMobile(string $mobile): string
    {
        return preg_match('/^\d{11}$/', $mobile) ? substr($mobile, 0, 3) . '****' . substr($mobile, -4) : $mobile;
    }

    private function decode(string $value): array
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
