<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\dict\MemberCardDict;
use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\model\MemberCardFinanceLink;
use addon\hsx_member_card\app\model\MemberCardOrder;
use addon\hsx_member_card\app\model\MemberCardRedemption;
use addon\hsx_member_card\app\model\MemberCardRefund;
use addon\hsx_member_card\app\support\MemberCardIdempotency;
use addon\hsx_member_card\app\support\MemberCardMoney;
use addon\hsx_member_card\app\support\MemberCardNumber;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

final class MemberCardRefundService extends BaseAdminService
{
    public function apply(int $orderId, array $data): array
    {
        $requestId = MemberCardIdempotency::normalize($data['request_id'] ?? '');
        $existing = MemberCardRefund::where([['site_id', '=', $this->site_id], ['request_id', '=', $requestId]])->findOrEmpty();
        if (!$existing->isEmpty()) return $this->response((int)$existing->id, '重复提交已返回原退款结果');
        $mode = (string)($data['refund_mode'] ?? 'finance');
        if (!in_array($mode, ['immediate', 'finance'], true)) throw new CommonException('退款方式不正确');
        $reason = mb_substr(trim((string)($data['reason'] ?? '')), 0, 255);
        if ($reason === '') throw new CommonException('整卡退款必须填写原因');
        $account = ['id' => 0, 'name' => ''];
        if ($mode === 'immediate') $account = (new MemberCardFinanceGateway())->requireCapitalAccount((int)($data['capital_account_id'] ?? 0));

        $refundId = 0;
        Db::transaction(function () use ($orderId, $requestId, $mode, $reason, $account, &$refundId): void {
            $order = MemberCardOrder::where([['site_id', '=', $this->site_id], ['id', '=', $orderId]])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) throw new CommonException('开卡订单不存在');
            if ((string)$order->business_status !== MemberCardDict::ORDER_ACTIVE) throw new CommonException('只有已开卡订单可以申请退款');
            if (MemberCardMoney::compare($order->paid_amount, $order->order_amount) < 0) throw new CommonException('订单尚未全额收款；未收款请取消，部分收款请先由财务核对');
            $card = MemberCard::where([['site_id', '=', $this->site_id], ['order_id', '=', $orderId]])->lock(true)->findOrEmpty();
            if ($card->isEmpty()) throw new CommonException('开卡订单缺少会员卡');
            if (MemberCardRedemption::where([['site_id', '=', $this->site_id], ['card_id', '=', (int)$card->id], ['status', '=', 'success']])->count() > 0) {
                throw new CommonException('该卡已有有效核销，必须先逐笔冲正后再整卡退款');
            }
            if (MemberCardRefund::where([['site_id', '=', $this->site_id], ['order_id', '=', $orderId]])->whereIn('status', ['processing', 'pending', 'paid'])->count() > 0) {
                throw new CommonException('该订单已有进行中或已完成退款');
            }
            $now = time();
            $refund = MemberCardRefund::create([
                'site_id' => (int)$this->site_id,
                'refund_no' => MemberCardNumber::make('MCR'),
                'request_id' => $requestId,
                'order_id' => $orderId,
                'order_no' => (string)$order->order_no,
                'card_id' => (int)$card->id,
                'card_no' => (string)$card->card_no,
                'member_id' => (int)$order->member_id,
                'party_id' => (int)$order->party_id,
                'party_name' => (string)$order->party_name,
                'original_amount' => (string)$order->order_amount,
                'refund_amount' => (string)$order->order_amount,
                'refund_mode' => $mode,
                'capital_account_id' => (int)($account['id'] ?? 0),
                'capital_account_name' => (string)($account['name'] ?? ''),
                'status' => 'processing',
                'apply_uid' => (int)$this->uid,
                'apply_name' => (string)$this->username,
                'reason' => $reason,
                'create_at' => $now,
                'update_at' => $now,
            ]);
            $refundId = (int)$refund->id;
            $order->save(['business_status' => MemberCardDict::ORDER_REFUND_PENDING, 'finance_status' => 'refund_pending', 'update_at' => $now]);
            $card->save(['status' => MemberCardDict::CARD_REFUND_PENDING, 'finance_status' => 'refund_pending', 'update_at' => $now]);
        });
        (new MemberCardAuditService())->record('refund', $refundId, (string)$this->find($refundId)->refund_no, 'apply', [], $this->find($refundId)->toArray());
        $createdRefund = $this->find($refundId)->toArray();
        (new MemberCardStaffFactService())->record([
            'event_id' => 'hsx_member_card:refund:' . $refundId . ':applied',
            'fact_type' => 'refund_apply', 'biz_type' => 'card_refund', 'biz_id' => $refundId, 'biz_no' => (string)$createdRefund['refund_no'],
            'staff_role' => 'refund_apply', 'staff_uid' => (int)$createdRefund['apply_uid'], 'staff_name' => (string)$createdRefund['apply_name'],
            'metric_key' => 'member_card_refund_applied', 'quantity' => 1, 'amount' => (string)$createdRefund['refund_amount'], 'direction' => 1, 'occurred_at' => time(),
        ]);
        return $this->runFinance($refundId, $data);
    }

    public function retryFinance(int $id, array $data = []): array
    {
        $refund = $this->find($id);
        if ((string)$refund->status === 'paid') return $this->response($id, '退款已经完成');
        if ((string)$refund->status === 'cancelled') throw new CommonException('已取消退款不能重试');
        if ((string)$refund->refund_mode === 'immediate') {
            $accountId = (int)($data['capital_account_id'] ?? $refund->capital_account_id);
            $account = (new MemberCardFinanceGateway())->requireCapitalAccount($accountId);
            $refund->save(['capital_account_id' => $accountId, 'capital_account_name' => (string)$account['name'], 'update_at' => time()]);
            $data['capital_account_id'] = $accountId;
        }
        return $this->runFinance($id, $data);
    }

    public function lists(array $where): array
    {
        $query = MemberCardRefund::where([['site_id', '=', $this->site_id]]);
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') $query->whereLike('refund_no|order_no|card_no|party_name|reason', '%' . $keyword . '%');
        if (trim((string)($where['status'] ?? '')) !== '') $query->where('status', '=', (string)$where['status']);
        return $query->order('id desc')->paginate([
            'list_rows' => min(100, max(1, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
    }

    public function info(int $id): array
    {
        $row = $this->find($id)->toArray();
        $row['finance'] = MemberCardFinanceLink::where([['site_id', '=', $this->site_id], ['biz_type', '=', 'card_refund'], ['biz_id', '=', $id]])->findOrEmpty()->toArray();
        return $row;
    }

    private function runFinance(int $refundId, array $data): array
    {
        $refund = $this->find($refundId);
        try {
            $gateway = new MemberCardFinanceGateway();
            if (!$gateway->usesErp()) {
                if ((string)$refund->refund_mode !== 'immediate') {
                    throw new CommonException('独立使用会员卡时请选择现场退款');
                }
                $account = $gateway->requireCapitalAccount((int)($data['capital_account_id'] ?? $refund->capital_account_id));
                $this->completeRefund($refundId, '', (string)$account['name']);
                return $this->response($refundId, '整卡退款已完成，本地出账已登记');
            }
            $link = $this->ensureFinanceFact($refund->toArray());
            if ((string)$refund->refund_mode === 'finance') {
                $refund->save(['status' => 'pending', 'last_error' => '', 'update_at' => time()]);
                return $this->response($refundId, '退款应付已生成，等待财务出款');
            }
            $settlement = (new MemberCardFinanceGateway())->settlePayable($refund->toArray(), $link->toArray(), [
                'capital_account_id' => (int)($data['capital_account_id'] ?? $refund->capital_account_id),
                'voucher_urls' => (array)($data['voucher_urls'] ?? []),
            ]);
            $this->completeRefund($refundId, (string)($settlement['settlement_no'] ?? ''), (string)($settlement['capital_account_name'] ?? $refund->capital_account_name));
            return $this->response($refundId, '整卡退款已完成');
        } catch (\Throwable $e) {
            $refund->save(['status' => 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000), 'update_at' => time()]);
            MemberCardFinanceLink::where([['site_id', '=', $this->site_id], ['biz_type', '=', 'card_refund'], ['biz_id', '=', $refundId]])->update([
                'finance_status' => 'failed', 'retry_count' => Db::raw('retry_count + 1'), 'last_error' => mb_substr($e->getMessage(), 0, 1000), 'update_at' => time(),
            ]);
            return $this->response($refundId, '退款单已保存，但财务处理失败，请重试');
        }
    }

    private function ensureFinanceFact(array $refund): MemberCardFinanceLink
    {
        $link = MemberCardFinanceLink::where([['site_id', '=', $this->site_id], ['biz_type', '=', 'card_refund'], ['biz_id', '=', (int)$refund['id']], ['target_type', '=', 'payable']])->findOrEmpty();
        if (!$link->isEmpty() && (int)$link->target_id > 0) return $link;
        $gateway = new MemberCardFinanceGateway();
        $result = $gateway->createRefundFact($refund);
        $now = time();
        $values = [
            'site_id' => (int)$this->site_id,
            'biz_type' => 'card_refund',
            'biz_id' => (int)$refund['id'],
            'biz_no' => (string)$refund['refund_no'],
            'event_id' => $gateway->refundFactEventId((int)$refund['id']),
            'target_type' => 'payable',
            'target_id' => (int)($result['target_id'] ?? 0),
            'target_no' => (string)($result['target_no'] ?? ''),
            'amount' => (string)$refund['refund_amount'],
            'settled_amount' => 0,
            'remaining_amount' => (string)$refund['refund_amount'],
            'finance_status' => 'pending',
            'payload_hash' => MemberCardIdempotency::hash(['refund_id' => (int)$refund['id'], 'amount' => (string)$refund['refund_amount'], 'party_id' => (int)$refund['party_id']]),
            'last_sync_at' => $now,
            'create_at' => $now,
            'update_at' => $now,
        ];
        if ((int)$values['target_id'] <= 0) throw new CommonException('ERP未返回有效应付ID');
        if ($link->isEmpty()) $link = MemberCardFinanceLink::create($values); else $link->save($values);
        return $link;
    }

    public function completeRefund(int $refundId, string $settlementNo, string $accountName): void
    {
        $refund = $this->find($refundId);
        if ((string)$refund->status === 'paid') return;
        Db::transaction(function () use ($refund, $settlementNo, $accountName): void {
            $now = time();
            $refund->save(['status' => 'paid', 'capital_account_name' => $accountName ?: (string)$refund->capital_account_name, 'paid_at' => $now, 'last_error' => '', 'update_at' => $now]);
            MemberCardOrder::where([['site_id', '=', $this->site_id], ['id', '=', (int)$refund->order_id]])->update([
                'business_status' => MemberCardDict::ORDER_REFUNDED,
                'finance_status' => 'refunded',
                'refunded_amount' => (string)$refund->refund_amount,
                'update_at' => $now,
            ]);
            MemberCard::where([['site_id', '=', $this->site_id], ['id', '=', (int)$refund->card_id]])->update([
                'status' => MemberCardDict::CARD_REFUNDED,
                'finance_status' => 'refunded',
                'update_at' => $now,
            ]);
            MemberCardFinanceLink::where([['site_id', '=', $this->site_id], ['biz_type', '=', 'card_refund'], ['biz_id', '=', (int)$refund->id]])->update([
                'settled_amount' => (string)$refund->refund_amount,
                'remaining_amount' => 0,
                'finance_status' => 'settled',
                'settlement_no' => $settlementNo,
                'last_error' => '',
                'last_sync_at' => $now,
                'update_at' => $now,
            ]);
        });
        (new MemberCardAuditService())->record('refund', (int)$refund->id, (string)$refund->refund_no, 'paid', [], $this->find((int)$refund->id)->toArray());
        (new MemberCardStaffFactService())->record([
            'event_id' => 'hsx_member_card:refund:' . (int)$refund->id . ':paid',
            'fact_type' => 'refund', 'biz_type' => 'card_refund', 'biz_id' => (int)$refund->id, 'biz_no' => (string)$refund->refund_no,
            'staff_role' => 'refund_operator', 'staff_uid' => (int)$refund->apply_uid, 'staff_name' => (string)$refund->apply_name,
            'metric_key' => 'member_card_refunded', 'quantity' => 1, 'amount' => (string)$refund->refund_amount, 'direction' => -1, 'occurred_at' => time(),
        ]);
    }

    private function response(int $id, string $message): array
    {
        $refund = $this->find($id);
        $link = MemberCardFinanceLink::where([['site_id', '=', $this->site_id], ['biz_type', '=', 'card_refund'], ['biz_id', '=', $id]])->findOrEmpty();
        return [
            'refund_id' => $id, 'refund_no' => (string)$refund->refund_no, 'order_id' => (int)$refund->order_id,
            'status' => (string)$refund->status, 'refund_amount' => (string)$refund->refund_amount,
            'finance_no' => $link->isEmpty() ? '' : (string)$link->target_no,
            'settlement_no' => $link->isEmpty() ? '' : (string)$link->settlement_no,
            'last_error' => (string)$refund->last_error, 'message' => $message,
        ];
    }

    private function find(int $id): MemberCardRefund
    {
        $row = MemberCardRefund::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('退款单不存在');
        return $row;
    }
}
