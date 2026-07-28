<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\dict\MemberCardDict;
use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\model\MemberCardFinanceLink;
use addon\hsx_member_card\app\model\MemberCardItem;
use addon\hsx_member_card\app\model\MemberCardOperationLog;
use addon\hsx_member_card\app\model\MemberCardOrder;
use addon\hsx_member_card\app\model\MemberCardProduct;
use addon\hsx_member_card\app\model\MemberCardProductItem;
use addon\hsx_member_card\app\model\MemberCardRedemption;
use addon\hsx_member_card\app\model\MemberCardStaffFact;
use addon\hsx_member_card\app\service\core\MemberCardConfigService;
use addon\hsx_member_card\app\support\MemberCardIdempotency;
use addon\hsx_member_card\app\support\MemberCardBinding;
use addon\hsx_member_card\app\support\MemberCardMoney;
use addon\hsx_member_card\app\support\MemberCardNumber;
use addon\hsx_member_card\app\support\MemberCardValidity;
use app\model\member\Member;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/** 开卡命令编排：本地订单/卡 → ERP应收 → 现场收款或挂账 → 激活。 */
final class MemberCardOrderService extends BaseAdminService
{
    public function lists(array $where): array
    {
        $query = MemberCardOrder::where([['site_id', '=', $this->site_id]]);
        $keyword = trim((string)($where['keyword'] ?? ''));
        if ($keyword !== '') $query->whereLike('order_no|holder_name|holder_mobile|product_name|party_name', '%' . $keyword . '%');
        foreach (['business_status', 'finance_status', 'settlement_mode'] as $field) {
            if (trim((string)($where[$field] ?? '')) !== '') $query->where($field, '=', (string)$where[$field]);
        }
        if ((int)($where['member_id'] ?? 0) > 0) $query->where('member_id', '=', (int)$where['member_id']);
        return $query->order('id desc')->paginate([
            'list_rows' => min(100, max(1, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
    }

    public function info(int $id): array
    {
        $order = $this->find($id)->toArray();
        $card = MemberCard::where([['site_id', '=', $this->site_id], ['order_id', '=', $id]])->findOrEmpty();
        $order['card'] = $card->isEmpty() ? null : $card->toArray();
        $order['card_items'] = $card->isEmpty() ? [] : MemberCardItem::where([
            ['site_id', '=', $this->site_id], ['card_id', '=', (int)$card->id],
        ])->order('id asc')->select()->toArray();
        $order['finance_links'] = MemberCardFinanceLink::where([
            ['site_id', '=', $this->site_id], ['biz_type', '=', 'card_sale'], ['biz_id', '=', $id],
        ])->order('id asc')->select()->toArray();
        $order['operation_logs'] = MemberCardOperationLog::where([
            ['site_id', '=', $this->site_id], ['biz_type', '=', 'order'], ['biz_id', '=', $id],
        ])->order('id desc')->select()->toArray();
        return $order;
    }

    public function create(array $data): array
    {
        $requestId = MemberCardIdempotency::normalize($data['request_id'] ?? '');
        $existing = MemberCardOrder::where([['site_id', '=', $this->site_id], ['request_id', '=', $requestId]])->findOrEmpty();
        if (!$existing->isEmpty()) return $this->response((int)$existing->id, '重复提交已返回原开卡结果');

        $memberId = max(0, (int)($data['member_id'] ?? 0));
        $productId = max(0, (int)($data['product_id'] ?? 0));
        if ($memberId <= 0 || $productId <= 0) throw new CommonException('请选择客户和卡种');
        $member = Member::where([['site_id', '=', $this->site_id], ['member_id', '=', $memberId]])
            ->field('member_id,member_no,username,nickname,mobile')->findOrEmpty();
        if ($member->isEmpty()) throw new CommonException('会员不存在或不属于当前站点');
        $memberData = $member->toArray();
        $holderName = $this->memberName($memberData);
        $mobile = trim((string)($memberData['mobile'] ?? ''));
        if ($holderName === '' || !preg_match('/^1\d{10}$/', $mobile)) {
            throw new CommonException('开卡前请先完善客户姓名和11位手机号，便于后续核销');
        }

        $product = MemberCardProduct::where([
            ['site_id', '=', $this->site_id], ['id', '=', $productId], ['status', '=', MemberCardDict::PRODUCT_ENABLED],
        ])->findOrEmpty();
        if ($product->isEmpty()) throw new CommonException('卡种不存在或已停用');
        $item = MemberCardProductItem::where([
            ['site_id', '=', $this->site_id], ['product_id', '=', $productId], ['status', '=', 1],
        ])->order('sort desc,id asc')->findOrEmpty();
        if ($item->isEmpty()) throw new CommonException('卡种没有可用服务权益');
        $binding = MemberCardBinding::issue($item->toArray(), $data);

        $mode = (string)($data['settlement_mode'] ?? 'receivable');
        if (!in_array($mode, ['immediate', 'receivable'], true)) throw new CommonException('结算方式不正确');
        $config = (new MemberCardConfigService())->get((int)$this->site_id);
        $financeGateway = new MemberCardFinanceGateway();
        if ($mode === 'receivable' && (!$financeGateway->usesErp() || (int)$config['allow_receivable'] !== 1)) {
            throw new CommonException($financeGateway->usesErp() ? '当前配置不允许挂账开卡' : '独立使用会员卡时仅支持现场收款');
        }
        $account = ['id' => 0, 'name' => ''];
        if ($mode === 'immediate' && MemberCardMoney::compare($product->sale_price, '0') > 0) {
            $account = $financeGateway->requireCapitalAccount((int)($data['capital_account_id'] ?? 0));
        }
        $party = (new MemberCardMemberService())->resolveParty($memberData);

        $orderId = $this->createLocalOrder($requestId, $memberData, $product->toArray(), array_merge($item->toArray(), $binding), $party, $mode, $account, $data);
        return $this->runFinance($orderId, $data);
    }

    public function retryFinance(int $id, array $data = []): array
    {
        $order = $this->find($id);
        if (in_array((string)$order->business_status, [MemberCardDict::ORDER_CANCELLED, MemberCardDict::ORDER_REFUNDED], true)) {
            throw new CommonException('当前开卡单不能重试财务处理');
        }
        if ((string)$order->finance_status === 'settled' && (string)$order->business_status === MemberCardDict::ORDER_ACTIVE) {
            return $this->response($id, '该开卡单已处理完成');
        }
        if ((string)$order->settlement_mode === 'immediate') {
            $accountId = (int)($data['capital_account_id'] ?? $order->capital_account_id);
            $account = (new MemberCardFinanceGateway())->requireCapitalAccount($accountId);
            $order->save(['capital_account_id' => $accountId, 'capital_account_name' => (string)$account['name'], 'update_at' => time()]);
            $data['capital_account_id'] = $accountId;
        }
        return $this->runFinance($id, $data);
    }

    public function cancel(int $id, string $reason): array
    {
        $reason = mb_substr(trim($reason), 0, 255);
        if ($reason === '') throw new CommonException('请填写取消原因');
        $order = $this->find($id);
        if ((string)$order->business_status === MemberCardDict::ORDER_CANCELLED) return $this->response($id, '开卡单已取消');
        if ((float)$order->paid_amount > 0.0001) throw new CommonException('该订单已有实际收款，请走整卡退款流程');
        $card = MemberCard::where([['site_id', '=', $this->site_id], ['order_id', '=', $id]])->findOrEmpty();
        if (!$card->isEmpty() && MemberCardRedemption::where([
            ['site_id', '=', $this->site_id], ['card_id', '=', (int)$card->id], ['status', '=', 'success'],
        ])->count() > 0) throw new CommonException('该卡已有有效核销，必须先逐笔冲正');

        $link = MemberCardFinanceLink::where([
            ['site_id', '=', $this->site_id], ['biz_type', '=', 'card_sale'], ['biz_id', '=', $id], ['target_type', '=', 'receivable'],
        ])->findOrEmpty();
        if (!$link->isEmpty() && (int)$link->target_id > 0 && (string)$link->finance_status !== 'void') {
            (new MemberCardFinanceGateway())->voidFact($order->toArray(), $link->toArray(), $reason);
        }

        $before = $order->toArray();
        Db::transaction(function () use ($order, $card, $link, $reason): void {
            $now = time();
            $order->save([
                'business_status' => MemberCardDict::ORDER_CANCELLED,
                'finance_status' => 'void',
                'cancelled_at' => $now,
                'remark' => mb_substr(trim((string)$order->remark . '；取消：' . $reason), 0, 255),
                'update_at' => $now,
            ]);
            if (!$card->isEmpty()) $card->save(['status' => MemberCardDict::CARD_CANCELLED, 'finance_status' => 'void', 'update_at' => $now]);
            if (!$link->isEmpty()) $link->save(['finance_status' => 'void', 'remaining_amount' => 0, 'last_sync_at' => $now, 'update_at' => $now]);
        });
        (new MemberCardAuditService())->record('order', $id, (string)$order->order_no, 'cancel', $before, $order->toArray());
        $this->recordStaffFact($order->toArray(), 'cancel');
        return $this->response($id, '开卡单已取消，未发生实际收款');
    }

    private function createLocalOrder(string $requestId, array $member, array $product, array $item, array $party, string $mode, array $account, array $data): int
    {
        $orderId = 0;
        try {
            Db::transaction(function () use ($requestId, $member, $product, $item, $party, $mode, $account, $data, &$orderId): void {
                $now = time();
                $snapshot = ['product' => $product, 'items' => [$item], 'binding' => [
                    'binding_mode' => (string)($item['binding_mode'] ?? MemberCardBinding::MEMBER),
                    'bound_imei' => (string)($item['bound_imei'] ?? ''),
                    'bound_model' => (string)($item['bound_model'] ?? ''),
                ], 'snapshot_at' => $now];
                $order = MemberCardOrder::create([
                    'site_id' => (int)$this->site_id,
                    'order_no' => MemberCardNumber::make('MC'),
                    'request_id' => $requestId,
                    'member_id' => (int)$member['member_id'],
                    'holder_name' => $this->memberName($member),
                    'holder_mobile' => (string)$member['mobile'],
                    'holder_mobile_last4' => substr((string)$member['mobile'], -4),
                    'party_id' => (int)$party['party_id'],
                    'party_name' => (string)$party['party_name'],
                    'product_id' => (int)$product['id'],
                    'product_no' => (string)$product['product_no'],
                    'product_name' => (string)$product['product_name'],
                    'product_snapshot' => $this->encode($snapshot),
                    'order_amount' => MemberCardMoney::normalize($product['sale_price']),
                    'paid_amount' => 0,
                    'refunded_amount' => 0,
                    'settlement_mode' => $mode,
                    'capital_account_id' => (int)($account['id'] ?? 0),
                    'capital_account_name' => (string)($account['name'] ?? ''),
                    'business_status' => MemberCardDict::ORDER_PROCESSING,
                    'finance_status' => 'processing',
                    'issuer_uid' => (int)$this->uid,
                    'issuer_name' => (string)$this->username,
                    'remark' => mb_substr(trim((string)($data['remark'] ?? '')), 0, 255),
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $orderId = (int)$order->id;
                $card = MemberCard::create([
                    'site_id' => (int)$this->site_id,
                    'card_no' => MemberCardNumber::make('CARD'),
                    'order_id' => $orderId,
                    'order_no' => (string)$order->order_no,
                    'member_id' => (int)$member['member_id'],
                    'holder_name' => (string)$order->holder_name,
                    'holder_mobile' => (string)$order->holder_mobile,
                    'holder_mobile_last4' => (string)$order->holder_mobile_last4,
                    'product_id' => (int)$product['id'],
                    'product_no' => (string)$product['product_no'],
                    'product_name' => (string)$product['product_name'],
                    'rule_snapshot' => $this->encode($snapshot),
                    'status' => MemberCardDict::CARD_PENDING,
                    'finance_status' => 'processing',
                    'effective_mode' => (string)$product['effective_mode'],
                    'issuer_uid' => (int)$this->uid,
                    'issuer_name' => (string)$this->username,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
                $times = (string)$item['usage_mode'] === 'limited' ? max(0, (int)$item['total_times']) : 0;
                MemberCardItem::create([
                    'site_id' => (int)$this->site_id,
                    'card_id' => (int)$card->id,
                    'product_item_id' => (int)$item['id'],
                    'item_code' => (string)$item['item_code'],
                    'item_name' => (string)$item['item_name'],
                    'binding_mode' => (string)($item['binding_mode'] ?? MemberCardBinding::MEMBER),
                    'bound_imei' => (string)($item['bound_imei'] ?? ''),
                    'bound_model' => (string)($item['bound_model'] ?? ''),
                    'usage_mode' => (string)$item['usage_mode'],
                    'granted_times' => $times,
                    'used_times' => 0,
                    'remaining_times' => $times,
                    'reversed_times' => 0,
                    'daily_limit' => (int)$item['daily_limit'],
                    'allocated_amount' => MemberCardMoney::normalize($product['sale_price']),
                    'recognized_amount' => 0,
                    'consumable_code' => (string)($item['consumable_code'] ?? ''),
                    'consumable_name' => (string)($item['consumable_name'] ?? ''),
                    'consumable_unit' => (string)($item['consumable_unit'] ?? '张'),
                    'standard_consumable_qty' => (float)($item['standard_consumable_qty'] ?? 0),
                    'status' => 1,
                    'create_at' => $now,
                    'update_at' => $now,
                ]);
            });
        } catch (\Throwable $e) {
            $existing = MemberCardOrder::where([['site_id', '=', $this->site_id], ['request_id', '=', $requestId]])->findOrEmpty();
            if (!$existing->isEmpty()) return (int)$existing->id;
            throw $e;
        }
        (new MemberCardAuditService())->record('order', $orderId, (string)$this->find($orderId)->order_no, 'create', [], $this->find($orderId)->toArray());
        return $orderId;
    }

    private function runFinance(int $orderId, array $data): array
    {
        $order = $this->find($orderId);
        try {
            if (MemberCardMoney::compare($order->order_amount, '0') === 0) {
                $this->activate($orderId, 'settled', (string)$order->capital_account_name, '', 0);
                return $this->response($orderId, '免费卡开卡成功');
            }
            $gateway = new MemberCardFinanceGateway();
            if (!$gateway->usesErp()) {
                if ((string)$order->settlement_mode !== 'immediate') {
                    throw new CommonException('独立使用会员卡时仅支持现场收款');
                }
                $account = $gateway->requireCapitalAccount((int)($data['capital_account_id'] ?? $order->capital_account_id));
                $this->activate($orderId, 'settled', (string)$account['name'], '', (float)$order->order_amount);
                return $this->response($orderId, '开卡成功，本地收款已登记');
            }
            $link = $this->ensureFinanceFact($order->toArray());
            if ((string)$order->settlement_mode === 'receivable') {
                $this->activate($orderId, 'pending', '', '', 0);
                return $this->response($orderId, '开卡成功，已进入ERP待收款');
            }

            $accountId = (int)($data['capital_account_id'] ?? $order->capital_account_id);
            if ($accountId <= 0) throw new CommonException('现场收款必须选择资金账户');
            $settlement = $gateway->settleReceivable($order->toArray(), $link->toArray(), [
                'capital_account_id' => $accountId,
                'voucher_urls' => (array)($data['voucher_urls'] ?? []),
            ]);
            $this->activate(
                $orderId,
                (string)($settlement['target_status'] ?? 'settled'),
                (string)($settlement['capital_account_name'] ?? $order->capital_account_name),
                (string)($settlement['settlement_no'] ?? ''),
                (float)($settlement['settled_amount'] ?? $order->order_amount)
            );
            return $this->response($orderId, '开卡成功，现场收款已登记');
        } catch (\Throwable $e) {
            $this->markFinanceFailed($orderId, $e->getMessage());
            return $this->response($orderId, '开卡单已保存，但财务处理失败，请在订单中重试');
        }
    }

    private function ensureFinanceFact(array $order): MemberCardFinanceLink
    {
        $link = MemberCardFinanceLink::where([
            ['site_id', '=', $this->site_id], ['biz_type', '=', 'card_sale'], ['biz_id', '=', (int)$order['id']], ['target_type', '=', 'receivable'],
        ])->findOrEmpty();
        if (!$link->isEmpty() && (int)$link->target_id > 0) return $link;
        $gateway = new MemberCardFinanceGateway();
        $result = $gateway->createSaleFact($order);
        $now = time();
        $values = [
            'site_id' => (int)$this->site_id,
            'biz_type' => 'card_sale',
            'biz_id' => (int)$order['id'],
            'biz_no' => (string)$order['order_no'],
            'event_id' => $gateway->saleFactEventId((int)$order['id']),
            'target_type' => (string)($result['target_type'] ?? 'receivable'),
            'target_id' => (int)($result['target_id'] ?? 0),
            'target_no' => (string)($result['target_no'] ?? ''),
            'amount' => (string)$order['order_amount'],
            'settled_amount' => 0,
            'remaining_amount' => (string)$order['order_amount'],
            'finance_status' => 'pending',
            'payload_hash' => MemberCardIdempotency::hash([
                'order_id' => (int)$order['id'], 'order_no' => (string)$order['order_no'], 'amount' => (string)$order['order_amount'], 'party_id' => (int)$order['party_id'],
            ]),
            'last_sync_at' => $now,
            'create_at' => $now,
            'update_at' => $now,
        ];
        if ((int)$values['target_id'] <= 0) throw new CommonException('ERP未返回有效应收ID');
        if ($link->isEmpty()) $link = MemberCardFinanceLink::create($values);
        else $link->save($values);
        return $link;
    }

    private function activate(int $orderId, string $financeStatus, string $accountName, string $settlementNo, float $paidAmount): void
    {
        $order = $this->find($orderId);
        $snapshot = $this->decode((string)$order->product_snapshot);
        $productRule = (array)($snapshot['product'] ?? []);
        $validity = MemberCardValidity::calculate($productRule, (int)$order->create_at);
        $cardStatus = (string)($productRule['effective_mode'] ?? 'immediate') === 'first_use'
            ? MemberCardDict::CARD_ACTIVE
            : ((int)$validity['valid_start_at'] > time() ? MemberCardDict::CARD_PENDING : MemberCardDict::CARD_ACTIVE);
        Db::transaction(function () use ($order, $financeStatus, $accountName, $settlementNo, $paidAmount, $validity, $cardStatus): void {
            $now = time();
            $order->save([
                'business_status' => MemberCardDict::ORDER_ACTIVE,
                'finance_status' => $financeStatus,
                'paid_amount' => max((float)$order->paid_amount, $paidAmount),
                'capital_account_name' => $accountName !== '' ? $accountName : (string)$order->capital_account_name,
                'confirmed_at' => (int)$order->confirmed_at > 0 ? (int)$order->confirmed_at : $now,
                'last_error' => '',
                'update_at' => $now,
            ]);
            $card = MemberCard::where([['site_id', '=', $this->site_id], ['order_id', '=', (int)$order->id]])->lock(true)->findOrEmpty();
            if ($card->isEmpty()) throw new CommonException('开卡订单缺少会员卡记录');
            $card->save([
                'status' => $cardStatus,
                'finance_status' => $financeStatus,
                'activated_at' => (int)$validity['activated_at'],
                'valid_start_at' => (int)$validity['valid_start_at'],
                'valid_end_at' => (int)$validity['valid_end_at'],
                'update_at' => $now,
            ]);
            $link = MemberCardFinanceLink::where([
                ['site_id', '=', $this->site_id], ['biz_type', '=', 'card_sale'], ['biz_id', '=', (int)$order->id],
            ])->findOrEmpty();
            if (!$link->isEmpty()) {
                $settled = max((float)$link->settled_amount, $paidAmount);
                $link->save([
                    'settled_amount' => $settled,
                    'remaining_amount' => max(0, round((float)$link->amount - $settled, 2)),
                    'finance_status' => $financeStatus,
                    'settlement_no' => $settlementNo !== '' ? $settlementNo : (string)$link->settlement_no,
                    'last_error' => '',
                    'last_sync_at' => $now,
                    'update_at' => $now,
                ]);
            }
        });
        (new MemberCardAuditService())->record('order', $orderId, (string)$order->order_no, 'activate', [], $this->find($orderId)->toArray());
        $this->recordStaffFact($this->find($orderId)->toArray(), 'issue');
    }

    private function markFinanceFailed(int $orderId, string $message): void
    {
        $now = time();
        MemberCardOrder::where([['site_id', '=', $this->site_id], ['id', '=', $orderId]])->update([
            'business_status' => MemberCardDict::ORDER_FAILED,
            'finance_status' => 'failed',
            'last_error' => mb_substr(trim($message), 0, 1000),
            'update_at' => $now,
        ]);
        MemberCard::where([['site_id', '=', $this->site_id], ['order_id', '=', $orderId]])->update(['finance_status' => 'failed', 'update_at' => $now]);
        MemberCardFinanceLink::where([['site_id', '=', $this->site_id], ['biz_type', '=', 'card_sale'], ['biz_id', '=', $orderId]])->update([
            'finance_status' => 'failed', 'retry_count' => Db::raw('retry_count + 1'), 'last_error' => mb_substr(trim($message), 0, 1000), 'update_at' => $now,
        ]);
    }

    private function recordStaffFact(array $order, string $action): void
    {
        $cancel = $action === 'cancel';
        if ($cancel && MemberCardStaffFact::where([
            ['site_id', '=', $this->site_id],
            ['event_id', '=', 'hsx_member_card:order:' . (int)$order['id'] . ':issued'],
        ])->count() === 0) {
            return;
        }
        (new MemberCardStaffFactService())->record([
            'event_id' => 'hsx_member_card:order:' . (int)$order['id'] . ':' . ($cancel ? 'issue-cancel' : 'issued'),
            'fact_type' => $cancel ? 'sale_cancel' : 'sale',
            'biz_type' => 'card_order',
            'biz_id' => (int)$order['id'],
            'biz_no' => (string)$order['order_no'],
            'staff_role' => 'issuer',
            'staff_uid' => (int)$order['issuer_uid'],
            'staff_name' => (string)$order['issuer_name'],
            'metric_key' => $cancel ? 'member_card_issue_cancelled' : 'member_card_issued',
            'quantity' => 1,
            'amount' => (string)$order['order_amount'],
            'direction' => $cancel ? -1 : 1,
            'reversal_of_event_id' => $cancel ? 'hsx_member_card:order:' . (int)$order['id'] . ':issued' : '',
            'occurred_at' => time(),
        ]);
    }

    private function response(int $orderId, string $message): array
    {
        $order = $this->find($orderId)->toArray();
        $card = MemberCard::where([['site_id', '=', $this->site_id], ['order_id', '=', $orderId]])->findOrEmpty();
        $link = MemberCardFinanceLink::where([['site_id', '=', $this->site_id], ['biz_type', '=', 'card_sale'], ['biz_id', '=', $orderId]])->findOrEmpty();
        return [
            'order_id' => $orderId,
            'order_no' => (string)$order['order_no'],
            'card_id' => $card->isEmpty() ? 0 : (int)$card->id,
            'card_no' => $card->isEmpty() ? '' : (string)$card->card_no,
            'business_status' => (string)$order['business_status'],
            'finance_status' => (string)$order['finance_status'],
            'finance_no' => $link->isEmpty() ? '' : (string)$link->target_no,
            'settlement_no' => $link->isEmpty() ? '' : (string)$link->settlement_no,
            'amount' => (string)$order['order_amount'],
            'paid_amount' => (string)$order['paid_amount'],
            'last_error' => (string)$order['last_error'],
            'success' => (string)$order['business_status'] === MemberCardDict::ORDER_ACTIVE,
            'message' => $message,
        ];
    }

    private function find(int $id): MemberCardOrder
    {
        $row = MemberCardOrder::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('开卡订单不存在');
        return $row;
    }

    private function memberName(array $member): string
    {
        foreach (['nickname', 'username', 'mobile', 'member_no'] as $field) {
            $value = trim((string)($member[$field] ?? ''));
            if ($value !== '') return mb_substr($value, 0, 100);
        }
        return '';
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function decode(string $value): array
    {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
