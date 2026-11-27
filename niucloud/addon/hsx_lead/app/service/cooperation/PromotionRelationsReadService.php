<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\OwnershipScope;
use addon\hsx_lead\app\support\Data;
use app\dict\pay\{PayDict,RefundDict};
use core\exception\CommonException;
use think\facade\Db;

/** Read-only, direct member.pid relations. Staff seats never create referral relations. */
final class PromotionRelationsReadService
{
    private const PAYMENT_LABELS = ['paid' => '已付费', 'unpaid' => '尚未付费', 'refunded' => '已全额退款', 'review' => '待核对'];
    private const MEMBERSHIP_LABELS = ['active' => '生效中', 'expired' => '已到期', 'inactive' => '已暂停', 'not_opened' => '未开通', 'review' => '待核对'];
    private const CHUNK_SIZE = 200;

    public function __construct(private Context $context)
    {
    }

    /** Shared receipt-verified payment projection for bounded admin summaries. */
    public function memberPaymentFacts(array $memberIds): array
    {
        $this->context->permission('hsx_lead_promotion_relations');
        if (!$this->context->ownership()->scope()['all_data'])throw new CommonException('邀请关系需要全站业务查看权限');
        if (count($memberIds) > 500)throw new CommonException('请按每批最多500个会员核对付费情况');
        $ids = [];
        foreach ($memberIds as $id)$ids[] = OwnershipScope::ownerId($id);
        return $ids ? $this->payments(array_values(array_unique($ids))) : [];
    }
    /** Only the visible tree page; reuses receipt and subject truth, never lists() scans. */
    public function memberFacts(array $memberIds): array
    {
        $payments = $this->memberPaymentFacts($memberIds);
        if (!$memberIds)return [];
        $subjects = $this->subjects(array_map('intval', $memberIds));
        $rows = [];
        foreach ($subjects as $id => $subject)$rows[$id] = ['merchant' => $subject, 'membership' => self::membershipView(null, $subject), 'payment' => $payments[$id]];
        $this->attachMembership($rows);
        return $rows;
    }

    public function lists(array $params = []): array
    {
        $this->context->permission('hsx_lead_promotion_relations');
        if (!$this->context->ownership()->scope()['all_data'])throw new CommonException('邀请关系需要全站业务查看权限');
        $paymentFilter = Data::text($params, 'payment_state', 30);
        $membershipFilter = Data::text($params, 'membership_state', 30);
        if ($paymentFilter !== '' && !isset(self::PAYMENT_LABELS[$paymentFilter]))throw new CommonException('请选择有效的付费状态');
        if ($membershipFilter !== '' && !isset(self::MEMBERSHIP_LABELS[$membershipFilter]))throw new CommonException('请选择有效的会员状态');
        $q = $this->relationsQuery($params);
        $page = max(1, (int) ($params['page'] ?? 1));
        $size = max(1, min(100, (int) ($params['limit'] ?? 20)));
        $start = ($page - 1) * $size;
        $total = 0;
        $output = [];
        $cursor = PHP_INT_MAX;
        $totals = ['relation_count' => 0, 'paid_count' => 0, 'review_count' => 0, 'service_net' => 0, 'promotion_total' => 0];
        // Status filters and totals use the complete scoped result, not only the visible page.
        // Keyset chunks bound memory without expanding catalogs or calling external ERPs.
        do {
            $members = (clone $q)->where('m.member_id', '<', $cursor)->order('m.member_id desc')->limit(self::CHUNK_SIZE)->select()->toArray();
            if (!$members)break;
            $cursor = (int) end($members)['member_id'];
            $facts = $this->batch($members, $membershipFilter !== '');
            foreach ($members as $member) {
                $id = (int) $member['member_id'];
                $row = $facts[$id];
                if ($paymentFilter !== '' && $row['payment']['status'] !== $paymentFilter)continue;
                if ($membershipFilter !== '' && $row['membership']['status'] !== $membershipFilter)continue;
                $position = $total++;
                $totals['relation_count']++;
                if ($row['payment']['status'] === 'paid')$totals['paid_count']++;
                if ($row['payment']['status'] === 'review')$totals['review_count']++;
                if ($row['payment']['service_net'] === null)$totals['service_net'] = null;
                elseif ($totals['service_net'] !== null)$totals['service_net'] += Rules::cents($row['payment']['service_net']);
                $totals['promotion_total'] += Rules::cents($row['promotion']['total']);
                if ($position >= $start && count($output) < $size)$output[] = $row;
            }
        } while (count($members) === self::CHUNK_SIZE);
        if ($membershipFilter === '' && $output)$this->attachMembership($output);
        $totals['service_net'] = $totals['service_net'] === null ? null : Rules::amount($totals['service_net']);
        $totals['promotion_total'] = Rules::amount($totals['promotion_total']);
        return ['data' => $output, 'total' => $total, 'current_page' => $page, 'per_page' => $size, 'summary' => $totals,
            'filters' => ['payment_states' => self::options(self::PAYMENT_LABELS), 'membership_states' => self::options(self::MEMBERSHIP_LABELS)]];
    }

    private static function options(array $labels): array
    {
        $out = [];
        foreach ($labels as $value => $label)$out[] = ['value' => $value, 'label' => $label];
        return $out;
    }

    private function relationsQuery(array $params)
    {
        $site = $this->context->site;
        $prefix = (string) config('database.connections.mysql.prefix');
        $q = Db::name('member')->alias('m')->join([$prefix.'member' => 'p'], 'p.site_id=m.site_id AND p.member_id=m.pid')
            ->where('m.site_id', $site)->where('m.pid', '>', 0)->where('m.is_del', 0)->where('m.delete_time', 0)
            ->where('p.is_del', 0)->where('p.delete_time', 0)->whereRaw('m.member_id<>m.pid')
            ->field('m.member_id,m.member_no,m.pid,m.nickname,m.username,m.mobile,m.create_time,p.member_no AS inviter_no,p.nickname AS inviter_nickname,p.username AS inviter_username,p.mobile AS inviter_mobile');
        $inviter = $params['inviter_member_id'] ?? '';
        if (!in_array($inviter, ['', 0, '0'], true))$q->where('m.pid', OwnershipScope::ownerId($inviter));
        foreach (['keyword' => 'm', 'inviter_keyword' => 'p'] as $key => $alias) {
            $keyword = Data::text($params, $key, 100);
            if ($keyword === '')continue;
            $like = '%'.addcslashes($keyword, '\\%_').'%';
            $shops = Data::query('merchant', $site)->whereLike('name|contact_name|mobile', $like)->column('member_id');
            $q->where(function ($part) use ($alias, $keyword, $like, $shops): void {
                $part->whereLike($alias.'.nickname|'.$alias.'.username|'.$alias.'.mobile|'.$alias.'.member_no', $like);
                if (ctype_digit($keyword))$part->whereOr($alias.'.member_id', '=', (int) $keyword);
                if ($shops)$part->whereOr($alias.'.member_id', 'in', array_map('intval', $shops));
            });
        }
        return $q;
    }

    private function batch(array $members, bool $withMembership): array
    {
        $site = $this->context->site;
        $ids = array_map('intval', array_column($members, 'member_id'));
        $parents = array_values(array_unique(array_map('intval', array_column($members, 'pid'))));
        $shops = $this->subjects($ids);
        $payments = $this->payments($ids);
        $rewards = $this->rewards($ids, $parents);
        $counts = Db::name('member')->where('site_id', $site)->whereIn('pid', $parents)->where('is_del', 0)->where('delete_time', 0)->whereRaw('member_id<>pid')->field('pid,COUNT(*) AS n')->group('pid')->select()->toArray();
        $counts = array_column($counts, 'n', 'pid');
        $relationKeys = array_map(static fn (int $id): string => PromotionService::RELATION_PREFIX.$id, $ids);
        $bindings = Db::name('sys_config')->where('site_id', $site)->whereIn('config_key', $relationKeys)->field('config_key,value')->select()->toArray();
        $times = [];
        foreach ($bindings as $binding) {
            $value = Data::decode($binding['value']);
            $times[(int) substr($binding['config_key'], strlen(PromotionService::RELATION_PREFIX))] = $value;
        }
        $out = [];
        foreach ($members as $member) {
            $id = (int) $member['member_id'];
            $pid = (int) $member['pid'];
            $subject = $shops[$id];
            $binding = $times[$id] ?? [];
            $out[$id] = ['member' => self::memberView($member), 'inviter' => self::memberView(['member_id' => $pid, 'member_no' => $member['inviter_no'], 'nickname' => $member['inviter_nickname'], 'username' => $member['inviter_username'], 'mobile' => $member['inviter_mobile']]) + ['direct_invite_count' => (int) ($counts[$pid] ?? 0)],
                'merchant' => $subject, 'membership' => self::membershipView(null, $subject), 'payment' => $payments[$id],
                'promotion' => $rewards[$pid.':'.$id] ?? self::emptyRewards(), 'create_time' => (int) $member['create_time'],
                'bound_at' => (int) ($binding['pid'] ?? 0) === $pid && (int) ($binding['bound_at'] ?? 0) > 0 ? (int) $binding['bound_at'] : null];
        }
        if ($withMembership)$this->attachMembership($out);
        return $out;
    }

    private static function memberView(array $member): array
    {
        $phone = (string) ($member['mobile'] ?? '');
        return ['member_id' => (int) $member['member_id'], 'member_no' => (string) ($member['member_no'] ?? ''),
            'name' => trim((string) ($member['nickname'] ?? '')) ?: trim((string) ($member['username'] ?? '')) ?: '本站会员',
            'mobile_masked' => strlen($phone) >= 7 ? substr($phone, 0, 3).'****'.substr($phone, -4) : ''];
    }

    /** Resolve enterprise identity in batches; never auto-create a partner on a GET. */
    private function subjects(array $ids): array
    {
        $site = $this->context->site;
        $owned = [];
        $linked = [];
        $merchants = [];
        foreach (Data::query('merchant', $site)->whereIn('member_id', $ids)->field('id,member_id,name,status')->select()->toArray() as $merchant) {
            $owned[(int) $merchant['member_id']][] = $merchant;
            $merchants[(int) $merchant['id']] = $merchant;
        }
        $links = Db::name('hsx_lead_merchant_member')->where('site_id', $site)->whereIn('member_id', $ids)->where('status', 'active')->field('merchant_id,member_id')->select()->toArray();
        $missing = array_values(array_diff(array_unique(array_map('intval', array_column($links, 'merchant_id'))), array_keys($merchants)));
        if ($missing)foreach (Data::query('merchant', $site)->whereIn('id', $missing)->field('id,member_id,name,status')->select()->toArray() as $merchant)$merchants[(int) $merchant['id']] = $merchant;
        foreach ($links as $link)if (isset($merchants[(int) $link['merchant_id']]))$linked[(int) $link['member_id']][] = $merchants[(int) $link['merchant_id']];
        $out = [];
        foreach ($ids as $id) {
            $ownAll = $owned[$id] ?? [];
            $staffAll = $linked[$id] ?? [];
            $own = array_values(array_filter($ownAll, static fn (array $m): bool => (int) $m['status'] === 1));
            $staff = array_values(array_filter($staffAll, static fn (array $m): bool => (int) $m['status'] === 1));
            // Keep a paused enterprise visible for supervision, without allowing an
            // obsolete paused association to replace a currently enabled subject.
            if (!$own && !$staff) {
                $own = $ownAll;
                $staff = $staffAll;
            }$review = count($own) > 1 || count($staff) > 1;
            if ($own && $staff && (int) $own[0]['id'] !== (int) $staff[0]['id'])$review = true;
            $merchant = $review ? null : ($own[0] ?? $staff[0] ?? null);
            $out[$id] = ['merchant_id' => (int) ($merchant['id'] ?? 0), 'name' => (string) ($merchant['name'] ?? ''), 'shared' => $merchant && (int) $merchant['member_id'] !== $id, 'enabled' => !$merchant || (int) $merchant['status'] === 1, 'review_required' => $review];
        }return $out;
    }

    private function attachMembership(array &$rows): void
    {
        $ids = [];
        foreach ($rows as $row)if ($row['merchant']['merchant_id'] > 0)$ids[] = $row['merchant']['merchant_id'];
        $states = $ids ? EntityMembershipService::states($this->context->site, array_values(array_unique($ids))) : [];
        foreach ($rows as &$row)$row['membership'] = self::membershipView($states[$row['merchant']['merchant_id']] ?? null, $row['merchant']);
        unset($row);
    }

    private static function membershipView(?array $state, array $subject): array
    {
        $status = 'not_opened';
        if ($subject['review_required'] || !empty($state['review_required']))$status = 'review';
        elseif (!$subject['enabled'])$status = 'inactive';
        elseif (!empty($state['active']))$status = 'active';
        elseif (!empty($state['cooperation_exited']) || (int) ($state['expires_at'] ?? 0) > time())$status = 'inactive';
        elseif ((int) ($state['expires_at'] ?? 0) > 0 && (int) $state['expires_at'] <= time())$status = 'expired';
        return ['status' => $status, 'status_label' => self::MEMBERSHIP_LABELS[$status], 'level_name' => (string) ($state['level_name'] ?? ''), 'expires_at' => (int) ($state['expires_at'] ?? 0), 'shared' => (bool) $subject['shared']];
    }

    private function payments(array $ids): array
    {
        $site = $this->context->site;
        $orders = Store::query('coop_order', $site)->whereIn('member_id', $ids)->where('purpose', 'membership')->order('id')->select()->toArray();
        $orderIds = array_map('intval', array_column($orders, 'id'));
        $receipts = [];
        $refunds = [];
        if ($orderIds && (!OrderPaymentReadService::hasTable('pay') || !OrderPaymentReadService::hasTable('pay_refund')))throw new CommonException('会员支付或退款记录表缺失，请管理员先核对升级状态');
        if ($orderIds) {
            foreach (Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)->whereIn('trade_id', $orderIds)->order('id')->select()->toArray() as $pay)$receipts[(int) $pay['trade_id']][] = $pay;
            $numbers = [];
            foreach ($receipts as $entries)foreach ($entries as $pay)if ((string) $pay['out_trade_no'] !== '')$numbers[] = $pay['out_trade_no'];
            if ($numbers && OrderPaymentReadService::hasTable('pay_refund'))foreach (Db::name('pay_refund')->where('site_id', $site)->whereIn('out_trade_no', array_values(array_unique($numbers)))->order('id')->select()->toArray() as $refund)$refunds[(string) $refund['out_trade_no']][] = $refund;
        }
        $out = [];
        foreach ($ids as $id)$out[$id] = ['status' => 'unpaid', 'status_label' => self::PAYMENT_LABELS['unpaid'], 'paid_order_count' => 0, 'unpaid_order_count' => 0,
            'service_paid' => 0, 'service_refunded' => 0, 'service_net' => 0, 'paid_total' => 0, 'refunded_total' => 0, 'paid_net' => 0, 'last_paid_at' => 0, 'review_required' => false, 'refund_pending' => false];
        foreach ($orders as $order) {
            $id = (int) $order['member_id'];
            $fact = self::paymentFact($order, $receipts[(int) $order['id']] ?? [], $refunds);
            $row = &$out[$id];
            if ($fact['unpaid'])$row['unpaid_order_count']++;
            if ($fact['paid']) {
                $row['paid_order_count']++;
                $row['last_paid_at'] = max($row['last_paid_at'], $fact['pay_time']);
            }
            $row['service_paid'] += $fact['service_paid'];
            $row['service_refunded'] += $fact['service_refunded'];
            $row['paid_total'] += $fact['paid_total'];
            $row['refunded_total'] += $fact['refunded_total'];
            $row['review_required'] = $row['review_required'] || $fact['review'];
            $row['refund_pending'] = $row['refund_pending'] || $fact['pending'];
            unset($row);
        }
        foreach ($out as &$row) {
            $net = $row['service_paid'] - $row['service_refunded'];
            $cash = $row['paid_total'] - $row['refunded_total'];
            $row['service_net'] = $row['review_required'] ? null : Rules::amount($net);
            $row['status'] = $row['review_required'] ? 'review' : ($cash > 0 ? 'paid' : ($row['paid_total'] > 0 && $cash === 0 ? 'refunded' : 'unpaid'));
            $row['status_label'] = self::PAYMENT_LABELS[$row['status']];
            $row['service_paid'] = $row['review_required'] ? null : Rules::amount($row['service_paid']);
            $row['service_refunded'] = $row['review_required'] ? null : Rules::amount($row['service_refunded']);
            $row['paid_net'] = $row['review_required'] ? null : Rules::amount($cash);
            $row['paid_total'] = $row['review_required'] ? null : Rules::amount($row['paid_total']);
            $row['refunded_total'] = $row['review_required'] ? null : Rules::amount($row['refunded_total']);
        }unset($row);
        return $out;
    }

    /** Pure display facts; a paid business flag alone never proves collection. */
    public static function paymentFact(array $order, array $payments, array $refundsByNumber): array
    {
        $out = ['paid' => false, 'unpaid' => false, 'review' => false, 'pending' => false, 'service_paid' => 0, 'service_refunded' => 0, 'paid_total' => 0, 'refunded_total' => 0, 'pay_time' => 0];
        $rule = Data::decode($order['rule_json']);
        $finished = array_values(array_filter($payments, static fn (array $p): bool => (int) $p['status'] === (int) PayDict::STATUS_FINISH));
        if (!$finished) {
            $out['review'] = $order['status'] === 'paid' || !empty($rule['requires_manual_review']) || !empty($rule['closed_payment_receipts']);
            $out['unpaid'] = $order['status'] === 'unpaid' && !$out['review'];
            return $out;
        }
        if ($order['status'] === 'closed' && ($rule['closed_payment_refund_status'] ?? '') === 'refunded' && empty($rule['requires_manual_review']))return self::closedRefundFact($order, $rule, $finished, $refundsByNumber, $out);
        if (count($finished) !== 1) {
            $out['review'] = true;
            return $out;
        }$pay = $finished[0];
        try {
            if ((int) $pay['site_id'] !== (int) $order['site_id'] || $pay['trade_type'] !== PaymentService::TRADE_TYPE || (int) $pay['trade_id'] !== (int) $order['id']
                || $pay['main_type'] !== PayDict::MEMBER || (int) $pay['main_id'] !== (int) $order['member_id'] || Rules::cents((string) $pay['money']) !== Rules::cents((string) $order['amount']) || (string) $pay['out_trade_no'] === '')throw new CommonException('会员收款凭据不一致');
            EntityMembershipService::assertSnapshot($order, $rule);
            $service = MembershipCostService::promotionBase($order);
            $refunds = $refundsByNumber[(string) $pay['out_trade_no']] ?? [];
            foreach ($refunds as $refund)if (in_array($refund['status'], [RefundDict::WAIT, RefundDict::DEALING, RefundDict::SUCCESS], true)) {
                if ($refund['trade_type'] !== PaymentService::TRADE_TYPE || (string) $refund['trade_id'] !== (string) $order['id'] || (int) $refund['site_id'] !== (int) $order['site_id']
                    || $refund['type'] !== $pay['type'] || $refund['channel'] !== $pay['channel'])throw new CommonException('会员退款凭据不一致');
            }
            if (($rule['schema_version'] ?? '') === EntityMembershipService::SNAPSHOT_SCHEMA) {
                $parts = MembershipCostService::refundedComponents($order, $rule, $refunds);
                $returned = $parts['service'];
                $returnedTotal = $parts['total'];
                $out['review'] = $parts['review'];
                $out['pending'] = $parts['pending'];
            } else {
                $returned = 0;
                $seen = [];
                foreach ($refunds as $refund) {
                    if (in_array($refund['status'], [RefundDict::WAIT, RefundDict::DEALING], true))$out['pending'] = true;
                    if ($refund['status'] !== RefundDict::SUCCESS)continue;
                    $number = (string) $refund['refund_no'];
                    $money = Rules::cents((string) $refund['money']);
                    if ($number === '' || $money <= 0)throw new CommonException('退款凭据不完整');
                    if (isset($seen[$number])) {
                        if ($seen[$number] !== $money)throw new CommonException('退款凭据冲突');
                        continue;
                    }$seen[$number] = $money;
                    $returned += $money;
                }if ($returned > $service)throw new CommonException('退款超过原服务费');
            }
            $out['paid'] = true;
            $out['pay_time'] = (int) $pay['pay_time'];
            $out['service_paid'] = $service;
            $out['service_refunded'] = $returned;
            $out['paid_total'] = Rules::cents((string) $pay['money']);
            $out['refunded_total'] = $returnedTotal ?? $returned;
            // A callback still in reconciliation is not a normal paid membership order.
            $out['review'] = $out['review'] || $out['pending'] || !empty($rule['requires_manual_review']) || $order['status'] !== 'paid' || (string) $order['out_trade_no'] !== (string) $pay['out_trade_no'];
        } catch (CommonException$error) {
            $out['review'] = true;
        }
        return $out;
    }

    /** An explicitly reconciled closed purchase stays closed and has no rights. */
    private static function closedRefundFact(array $order, array $rule, array $payments, array $refundsByNumber, array $out): array
    {
        try {
            EntityMembershipService::assertSnapshot($order, $rule);
            $service = MembershipCostService::promotionBase($order);
            $seen = [];
            foreach ($payments as $pay) {
                $number = (string) $pay['out_trade_no'];
                if ($number === '' || isset($seen[$number]) || (int) $pay['site_id'] !== (int) $order['site_id'] || $pay['trade_type'] !== PaymentService::TRADE_TYPE || (int) $pay['trade_id'] !== (int) $order['id'] || $pay['main_type'] !== PayDict::MEMBER || (int) $pay['main_id'] !== (int) $order['member_id'] || Rules::cents((string) $pay['money']) !== Rules::cents((string) $order['amount']))throw new CommonException('关闭订单收款凭据冲突');
                $seen[$number] = true;
                $returned = 0;
                $refundIds = [];
                foreach ($refundsByNumber[$number] ?? [] as $refund) {
                    if (in_array($refund['status'], [RefundDict::WAIT, RefundDict::DEALING], true))throw new CommonException('关闭订单退款未完成');
                    if ($refund['status'] !== RefundDict::SUCCESS)continue;
                    $refundNo = (string) $refund['refund_no'];
                    $money = Rules::cents((string) $refund['money']);
                    if ($refundNo === '' || $money <= 0 || (int) $refund['site_id'] !== (int) $order['site_id'] || $refund['trade_type'] !== PaymentService::TRADE_TYPE || (string) $refund['trade_id'] !== (string) $order['id'] || $refund['type'] !== $pay['type'] || $refund['channel'] !== $pay['channel'])throw new CommonException('关闭订单退款凭据冲突');
                    if (isset($refundIds[$refundNo])) {
                        if ($refundIds[$refundNo] !== $money)throw new CommonException('关闭订单退款金额冲突');
                        continue;
                    }$refundIds[$refundNo] = $money;
                    $returned += $money;
                }
                if ($returned !== Rules::cents((string) $pay['money']))throw new CommonException('关闭订单尚未原路全退');
                $out['paid_total'] += $returned;
                $out['refunded_total'] += $returned;
                $out['service_paid'] += $service;
                $out['service_refunded'] += $service;
                $out['pay_time'] = max($out['pay_time'], (int) $pay['pay_time']);
            }
            $out['paid'] = true;
        } catch (CommonException$error) {
            $out['review'] = true;
        }
        return $out;
    }

    public static function emptyRewards(): array
    {
        return ['pending' => '0.00', 'settled' => '0.00', 'held' => '0.00', 'cancelled' => '0.00', 'total' => '0.00', 'review_required' => false];
    }
    /** Same read-only earning definition for relation rows and promoter totals. */
    public static function accumulateReward(array $total, array $row): array
    {
        $status = (string) $row['status'];
        if (!in_array($status, ['pending', 'settled', 'held'], true)) {
            $total['review_required'] = true;
            return $total;
        }
        $refund = Data::decode($row['rule_json'] ?? '')[PaymentRefundService::KEY] ?? [];
        if ($status === 'held' && empty($row['review_required']) && ($refund['status'] ?? '') === 'refunded') {
            $total['cancelled'] = Rules::amount(Rules::cents($total['cancelled']) + Rules::cents((string) $row['amount']));
            return $total;
        }
        $total[$status] = Rules::amount(Rules::cents($total[$status]) + Rules::cents((string) $row['amount']));
        $total['total'] = Rules::amount(Rules::cents($total['total']) + Rules::cents((string) $row['amount']));
        $total['review_required'] = $total['review_required'] || !empty($row['review_required']);
        return $total;
    }
    private function rewards(array $ids, array $parents): array
    {
        $prefix = (string) config('database.connections.mysql.prefix');
        $out = [];
        $rows = Db::name('hsx_lead_promotion_reward')->alias('r')->leftJoin([$prefix.'hsx_lead_coop_order' => 'o'], 'o.site_id=r.site_id AND o.id=r.coop_order_id')
            ->where('r.site_id', $this->context->site)->whereIn('r.payer_member_id', $ids)->whereIn('r.provider_member_id', $parents)
            ->field('r.provider_member_id,r.payer_member_id,r.status,r.amount,r.review_required,o.rule_json')->select()->toArray();
        foreach ($rows as $row) {
            $key = (int) $row['provider_member_id'].':'.(int) $row['payer_member_id'];
            $out[$key] = self::accumulateReward($out[$key] ?? self::emptyRewards(), $row);
        }return $out;
    }
}
