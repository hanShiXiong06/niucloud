<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\dict\pay\{PayDict,RefundDict};
use core\exception\CommonException;
use think\facade\Db;

/** Entity-owned rights. Member identity remains the real payer, never member_level. */
final class EntityMembershipService
{
    public const SNAPSHOT_SCHEMA = 'hsx_lead.entity_membership.v4';
    private const TTL = 600;
    private const FINANCIAL_HOLD_MESSAGE = '门店存在额外到账款项待平台核对，原有效会员权益保留；核对完成前暂不创建新的会员收费';
    public function __construct(private Context $context)
    {
    }

    public static function state(int $site, int $merchant, array $config = [], ?int $now = null, bool $lock = false): array
    {
        $rows = self::ledger($site, $merchant, $lock);
        $partner = Store::partner($site, $merchant, $lock);
        // Actual grants are independent of whether the sale catalog is now
        // edited, unavailable or disabled. Catalog checks belong to new sales.
        $state = self::stateFrom($rows, $partner, [], $now ?? time());
        // A renamed directory label is presentation, not a new entitlement.
        // Keep paid rights, capacity, amount and expiry frozen; never rewrite the receipt.
        $exited = MembershipDepositService::exited($site, $merchant, $lock);
        return self::displayState($state, $site, $merchant, $config, $exited, self::ledgerNeedsReview($rows));
    }

    /** Request-scoped, read-only projection. Payment/write gates must keep using state(..., lock:true).
     * Loads the same receipts and runs the same snapshot/refund/dependency validation as a single read.
     * Does not create missing partner records or cache financial results across requests.
     */
    public static function states(int $site, array $merchantIds, array $config = [], ?int $now = null): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $merchantIds), static fn (int $id): bool => $id > 0)));
        if (!$ids)return [];
        $now = $now ?? time();
        $out = [];
        foreach (array_chunk($ids, 500) as $chunk) {
            $partners = Store::query('partner', $site)->whereIn('merchant_id', $chunk)->select()->toArray();
            $partnerMap = [];
            foreach ($partners as $partner)$partnerMap[(int) $partner['merchant_id']] = $partner;
            $orders = Store::query('coop_order', $site)->whereIn('merchant_id', $chunk)->where('purpose', 'membership')->where('status', 'paid')->order('id')->select()->toArray();
            $grouped = [];
            $payments = [];
            $refunds = [];
            foreach ($orders as $order)$grouped[(int) $order['merchant_id']][] = $order;
            if ($orders) {
                $paid = Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)->whereIn('trade_id', array_column($orders, 'id'))->where('status', PayDict::STATUS_FINISH)->order('id')->select()->toArray();
                foreach ($paid as $receipt) {
                    $key = (int) $receipt['trade_id'].':'.(string) $receipt['out_trade_no'];
                    if (!isset($payments[$key]))$payments[$key] = $receipt;
                }
                // Preserve the single-read refund lookup even for a malformed
                // empty receipt reference; dropping it could hide an existing refund.
                $nos = array_values(array_unique(array_map('strval', array_column($orders, 'out_trade_no'))));
                if ($nos)foreach (Db::name('pay_refund')->where('site_id', $site)->whereIn('out_trade_no', $nos)->select()->toArray() as $refund)$refunds[(string) $refund['out_trade_no']][] = $refund;
            }
            $exits = MembershipDepositService::exitedMany($site, $chunk);
            $epochs = MembershipDepositService::membershipEpochs($site, $chunk);
            foreach ($chunk as $id) {
                $partner = $partnerMap[$id] ?? ['site_id' => $site, 'merchant_id' => $id];
                $ledger = self::ledger($site, $id, false, [
                        'orders' => $grouped[$id] ?? [],
                        'payments' => $payments,
                        'refunds' => $refunds,
                        'membership_epoch' => $epochs[$id] ?? ''
                    ]);
                $out[$id] = self::displayState(self::stateFrom($ledger, $partner, [], $now), $site, $id, $config, !empty($exits[$id]), self::ledgerNeedsReview($ledger));
            }
        }
        return $out;
    }

    /** Financial reconciliation blocks a NEW charge, not existing paid rights. */
    private static function financialReceiptHold(array $rows): bool
    {
        foreach ($rows as $row)if (!empty($row['rule']['financial_requires_manual_review']))return true;
        return false;
    }
    private static function ledgerNeedsReview(array $rows): bool
    {
        foreach ($rows as $row)if (!empty($row['review']) || !empty($row['refund_pending']))return true;
        return false;
    }

    private static function displayState(array $state, int $site, int $merchant, array $config, bool $exited, bool $review): array
    {
        foreach ($config['membership_levels'] ?? [] as $tier) if (($tier['code'] ?? '') === $state['level_code']) {
            $state['level_name'] = $tier['name'];
            $state['level_visual'] = $tier['level_visual'];
            $state['level_color'] = $tier['level_visual']['accent_color'];
            break;
        }
        $state['cooperation_exited'] = $exited;
        // Read-only explanation for operators; never substitute it for the
        // existing active/capability and locked payment/renewal decisions.
        $state['review_required'] = $review;
        if ($exited) {
            $state['active'] = false;
            $state['capabilities'] = MembershipCatalogService::emptyCapabilities();
            $state['benefits'] = MembershipCatalogService::benefits($state['capabilities']);
        }
        // Presentation is shared by the subject page and optional member-admin
        // extension. It must not replace active/capabilities in authorization.
        if ($review) {
            $status = 'review';
            $label = '待核对';
        } elseif ($exited) {
            $status = 'inactive';
            $label = '协作已停用';
        } elseif ($state['active']) {
            $status = 'active';
            $label = '生效中';
        } elseif ((int) $state['expires_at'] > 0) {
            $status = 'expired';
            $label = '已到期';
        } else {
            $status = 'not_opened';
            $label = '未开通';
        }
        $state['display_status'] = $status;
        $state['display_status_text'] = $label;
        return $state + ['merchant_id' => $merchant, 'configuration_source' => 'framework_member_level'];
    }

    public function summary(): array
    {
        $m = TeamService::resolve($this->context);
        $site = $this->context->site;
        $id = (int) $m['id'];
        $config = (new ConfigService($this->context))->get();
        $state = self::state($site, $id, $config);
        $manage = (int) $m['member_id'] === $this->context->memberId;
        $used = TeamService::usedAccounts($site, $m);
        $financialHold = self::financialReceiptHold(self::ledger($site, $id));
        $experience = EntityExperienceService::summary($site, $id);
        $tiers = [];
        foreach (EntityExperienceService::decorate($config['membership_levels'] ?? [], $experience) as $tier) {
            $allowed = $manage && $tier['experience_eligible'] && !$financialHold;
            $tier['cycles'] = array_map(static fn (array $cycle): array => $cycle + ['can_purchase' => $allowed && !empty($cycle['enabled'])], $tier['cycles']);
            $tiers[] = $tier;
        }
        $costs = MembershipCostService::balances($site, $id);
        if ($financialHold) {
            $costs['review_required'] = true;
            $costs['review_message'] = self::FINANCIAL_HOLD_MESSAGE;
        }
        return $state + [
            'used_accounts' => $used,
            'can_manage' => $manage,
            'is_owner' => $manage,
            'tiers' => $tiers,
            'experience' => $experience,
            'deposit_balance' => $costs['balance'],
            'cost_review_required' => $costs['review_required'],
            'cost_review_message' => $costs['review_message'],
            'next_level' => EntityExperienceService::nextLevel($tiers, $state, $experience),
            'catalog_configured' => count($tiers) > 0,
            'entry_configured' => in_array(true, array_column($tiers, 'is_entry'), true),
            'renewable' => $manage && $state['level_code'] !== '' && in_array($state['level_code'], array_column($tiers, 'code'), true),
            'upgrade_available' => $manage && count($tiers) > 0
        ] + MembershipDepositService::purchaseState($site, $id);
    }

    public function quote(string $code, string $cycle, bool $buyExperience = false): array
    {
        $m = TeamService::assertOwner($this->context);
        $id = (int) $m['id'];
        $config = (new ConfigService($this->context))->get();
        $now = time();
        $experience = EntityExperienceService::summary($this->context->site, $id);
        $epoch = MembershipDepositService::membershipEpoch($this->context->site, $id);
        $ledger = self::ledger($this->context->site, $id);
        $quote = self::calculate($ledger, Store::partner($this->context->site, $id), $config['membership_levels'] ?? [], $code, $cycle, $now, $experience, MembershipCostService::balances($this->context->site, $id), $buyExperience, $epoch);
        $quote['merchant_id'] = $id;
        $quote['existing_order_id'] = 0;
        $quote['quote_token'] = '';
        if (self::financialReceiptHold($ledger))return $quote;
        if (MembershipDepositService::purchaseState($this->context->site, $id)['deposit_purchase_blocked']) {
            $quote['can_purchase'] = false;
            $quote['reason'] = '门店退押金申请仍在审核、退款或核对中，处理完成后可重新开通';
            return $quote;
        }
        $pending = Store::query('coop_order', $this->context->site)->where('merchant_id', $id)->where('purpose', 'membership')->where('status', 'unpaid')->order('id')->find();
        if ($pending) {
            $rule = Data::decode($pending['rule_json']);
            $quote['existing_order_id'] = (int) $pending['id'];
            if (($rule['level_code'] ?? '') === $code && ($rule['cycle'] ?? '') === $cycle && (int) $pending['member_id'] === $this->context->memberId) {
                $quote = array_replace($quote, array_intersect_key($rule, array_flip(array_merge([
                                    'level_code',
                                    'level_name',
                                    'level_id',
                                    'rank',
                                    'display_rank',
                                    'cycle',
                                    'cycle_name',
                                    'months',
                                    'entitlement_months',
                                    'account_limit',
                                    'unlimited',
                                    'capabilities',
                                    'benefits',
                                    'period_start_at',
                                    'period_end_at',
                                    'full_amount',
                                    'credit_amount',
                                    'payable_amount',
                                    'experience_value',
                                    'experience_remaining'
                                ], MembershipCostService::FIELDS))), [
                        'can_purchase' => true,
                        'reason' => '已有本套餐待支付订单，请继续核对原单',
                        'expires_at' => 0
                    ]);
                // An existing pending quote remains its original financial
                // contract in the UI, even when the current tier gate changed.
                $quote['experience_gate_enabled'] = EntityExperienceService::gateEnabled($rule);
                $quote['experience_required'] = EntityExperienceService::required($rule);
                $quote['experience_eligible'] = EntityExperienceService::eligible($experience, $rule);
                if ((int) ($rule['quoted_at'] ?? 0) + self::TTL < $now) {
                    $quote['can_purchase'] = false;
                    $quote['reason'] = '原会员报价已过期，请先取消未支付订单，再重新读取报价';
                } elseif (!self::catalogMatches($rule, $config['membership_levels'] ?? [])) {
                    $quote['can_purchase'] = false;
                    $quote['reason'] = '原套餐已调整或停售，请先取消未支付订单，再选择新套餐';
                } elseif (!$quote['experience_eligible'] && empty($rule['buy_experience'])) {
                    $quote['can_purchase'] = false;
                    $quote['reason'] = '当前门店经验'.$quote['experience_value'].'，本等级需要'.$quote['experience_required'].'；请先完成真实成交付佣，再核对原订单';
                }
            } else {
                $quote['can_purchase'] = false;
                $quote['reason'] = '该主体已有待支付会员订单，请先处理原订单，不会重复收费';
            }
            return $quote;
        }
        if (empty($config['enabled'])) {
            $quote['can_purchase'] = false;
            $quote['reason'] = '本站尚未启用商户协作';
        }
        if ((int) Store::partner($this->context->site, $id)['blocked_at'] > 0) {
            $quote['can_purchase'] = false;
            $quote['reason'] = '该主体已停止合作，不能购买新的权益';
        }
        if ($quote['can_purchase']) $quote['quote_token'] = $this->sign([
                'site' => $this->context->site,
                'member' => $this->context->memberId,
                'merchant' => $id,
                'code' => $code,
                'cycle' => $cycle,
                'buy_experience' => $buyExperience,
                'membership_deposit_epoch' => $epoch,
                'cost_fingerprint' => $quote['cost_fingerprint'],
                'experience_value' => $quote['experience_value'],
                'at' => $now,
                'fingerprint' => $quote['base_fingerprint'],
                'pricing' => self::pricingFingerprint($config['membership_levels'] ?? [])
            ]);
        return $quote;
    }

    public function createOrder(array $input): array
    {
        $m = TeamService::assertOwner($this->context);
        $request = Store::requestId($input);
        $code = Data::text($input, 'level_code', 16, true);
        $cycle = Data::text($input, 'cycle', 24, true);
        $buyExperience = MembershipCostService::flag($input['buy_experience'] ?? false, '购买经验');
        $id = Db::transaction(function () use ($m, $request, $code, $cycle, $input, $buyExperience): int {
            PromotionService::lockSite($this->context->site);
            TeamService::assertOwner($this->context, (int) $m['id'], true);
            $partner = Store::partner($this->context->site, (int) $m['id'], true);
            if (MembershipDepositService::purchaseState($this->context->site, (int) $m['id'], true)['deposit_purchase_blocked'])throw new CommonException('门店退押金申请仍在审核、退款或核对中，暂不开通新权益');
            $epoch = MembershipDepositService::membershipEpoch($this->context->site, (int) $m['id'], true);
            $old = Store::query('coop_order', $this->context->site)->where('merchant_id', $m['id'])->where('request_id', $request)->lock(true)->find();
            if ($old) {
                $rule = Data::decode($old['rule_json']);
                if ($old['purpose'] !== 'membership'
                    || (int) $old['member_id'] !== $this->context->memberId
                    || ($rule['level_code'] ?? '') !== $code
                    || ($rule['cycle'] ?? '') !== $cycle
                    || ($rule['buy_experience'] ?? false) !== $buyExperience) throw new CommonException('该请求已用于其他付款，请核对原订单');
                if ($old['status'] === 'unpaid')self::assertOrderPayable($this->context->site, $old);
                return (int) $old['id'];
            }
            $pending = Store::query('coop_order', $this->context->site)->where('merchant_id', $m['id'])->where('purpose', 'membership')->where('status', 'unpaid')->lock(true)->find();
            if ($pending) {
                $rule = Data::decode($pending['rule_json']);
                if (($rule['level_code'] ?? '') !== $code
                    || ($rule['cycle'] ?? '') !== $cycle
                    || (int) $pending['member_id'] !== $this->context->memberId
                    || ($rule['buy_experience'] ?? false) !== $buyExperience) throw new CommonException('该主体已有待支付会员订单，请先核对原单');
                self::assertOrderPayable($this->context->site, $pending);
                return (int) $pending['id'];
            }
            $config = (new ConfigService($this->context))->get(true);
            if (empty($config['enabled']) || (int) $partner['blocked_at'] > 0) throw new CommonException('该主体当前不能购买会员权益');
            $token = $this->verify(Data::text($input, 'quote_token', 2048, true));
            $now = time();
            if (($token['membership_deposit_epoch'] ?? '') !== $epoch)throw new CommonException('门店退出状态已变化，请重新读取开通报价；不会沿用退出前的付款单');
            if (($token['site'] ?? 0) !== $this->context->site
                || ($token['member'] ?? 0) !== $this->context->memberId
                || ($token['merchant'] ?? 0) !== (int) $m['id']
                || ($token['code'] ?? '') !== $code
                || ($token['cycle'] ?? '') !== $cycle
                || ($token['buy_experience'] ?? null) !== $buyExperience
                || (int) ($token['at'] ?? 0) > $now
                || (int) ($token['at'] ?? 0) < $now - self::TTL
                || ($token['pricing'] ?? '') !== self::pricingFingerprint($config['membership_levels'] ?? [])) throw new CommonException('会员报价已变化或不属于当前负责人，请重新读取报价');
            $quote = self::calculate(self::ledger($this->context->site, (int) $m['id'], true), $partner, $config['membership_levels'] ?? [], $code, $cycle, (int) $token['at'], EntityExperienceService::summary($this->context->site, (int) $m['id'], true), MembershipCostService::balances($this->context->site, (int) $m['id'], true), $buyExperience, $epoch);
            if (!$quote['can_purchase']
                || !hash_equals((string) $token['fingerprint'], $quote['base_fingerprint'])
                || ($token['cost_fingerprint'] ?? '') !== $quote['cost_fingerprint']
                || (EntityExperienceService::gateEnabled($quote) && ($token['experience_value'] ?? '') !== $quote['experience_value'])) throw new CommonException($quote['reason'] ?: '主体权益、押金或经验已变化，请重新读取报价');
            $rule = $quote + ['schema_version' => self::SNAPSHOT_SCHEMA, 'purpose' => 'membership'];
            unset($rule['can_purchase'],$rule['reason'],$rule['expires_at']);
            $rule['promotion_policy'] = PromotionService::policyForNewOrder($this->context->site);
            $key = 'membership:'.$m['id'].':'.substr(hash('sha256', $request), 0, 48);
            $id = (int) Store::query('coop_order', $this->context->site)->insertGetId([
                    'site_id' => $this->context->site,
                    'order_no' => Data::number('CO'),
                    'purpose' => 'membership',
                    'lead_id' => 0,
                    'merchant_id' => $m['id'],
                    'member_id' => $this->context->memberId,
                    'business_key' => $key,
                    'request_id' => $request,
                    'amount' => $quote['payable_amount'],
                    'fee_amount' => '0.00',
                    'net_amount' => '0.00',
                    'rule_json' => Data::json($rule),
                    'status' => 'unpaid',
                    'create_time' => $now,
                    'update_time' => $now
                ]);
            $this->context->audit('coop_order', $id, 'created', [
                    'purpose' => 'membership',
                    'level_code' => $code,
                    'cycle' => $cycle,
                    'amount' => $quote['payable_amount']
                ]);
            return $id;
        });
        return (new PaymentService($this->context))->order($id);
    }

    /** Pure quote calculation; every credited period has a verified original receipt. */
    public static function calculate(array $rows, array $partner, array $tiers, string $code, string $cycle, int $now, array $experience = ['value' => '0.00'], array $deposit = [], bool $buyExperience = false, string $epoch = ''): array
    {
        $tier = null;
        foreach ($tiers as $entry) if ($entry['code'] === $code) $tier = $entry;
        if (!$tier || !MembershipCatalogService::validCode($code)) throw new CommonException('请选择会员等级中已启用商户协作权益的套餐');
        $period = null;
        foreach ($tier['cycles'] as $entry) if ($entry['key'] === $cycle) $period = $entry;
        if (!$period) throw new CommonException('请选择该会员套餐提供的购买周期');
        $months = (int) $period['months'];
        $state = self::stateFrom($rows, $partner, $tiers, $now);
        $segments = self::availableSegments($rows);
        $reservedRank = 0;
        $sameEnd = 0;
        foreach ($segments as [$reserved,$a,$b]) if ($b > $now) {
            $reservedRank = max($reservedRank, (int) $reserved['rule']['rank']);
            if ($reserved['rule']['level_code'] === $code) $sameEnd = max($sameEnd, $b);
        }
        $start = $sameEnd > 0 ? max($now, $sameEnd) : ($state['active'] && $state['level_code'] === $code ? max($now, $state['expires_at']) : $now);
        $full = Rules::cents($period['price']);
        $end = Rules::addMonths($start, $months);
        $required = EntityExperienceService::required($tier);
        $value = (string) $experience['value'];
        $eligible = EntityExperienceService::eligible($experience, $tier);
        $out = [
            'purpose' => 'membership',
            'level_code' => $code,
            'level_id' => (int) $tier['level_id'],
            'level_name' => $tier['name'],
            'level_color' => $tier['level_color'],
            'rank' => (int) $tier['rank'],
            'display_rank' => (int) $tier['display_rank'],
            'capabilities' => $tier['capabilities'],
            'benefits' => $tier['benefits'],
            'cycle' => $cycle,
            'cycle_name' => $period['name'],
            'entitlement_months' => $months,
            'months' => $months,
            'account_limit' => (int) $tier['account_limit'],
            'unlimited' => (bool) $tier['unlimited'],
            'is_entry' => (bool) $tier['is_entry'],
            'experience_required' => $required,
            'experience_value' => $value,
            'experience_eligible' => $eligible,
            'experience_remaining' => Rules::amount(max(0, Rules::cents($required) - Rules::cents($value))),
            'period_start_at' => $start,
            'period_end_at' => $end,
            'quoted_at' => $now,
            'expires_at' => $now + self::TTL,
            'full_amount' => Rules::amount($full),
            'credit_amount' => '0.00',
            'payable_amount' => Rules::amount($full),
            'credit_sources' => [],
            'can_purchase' => true,
            'reason' => '',
            'base_fingerprint' => self::fingerprint($rows, $partner),
            'catalog_fingerprint' => self::tierFingerprint($tier)
        ];
        $out['experience_gate_enabled'] = EntityExperienceService::gateEnabled($tier);
        if ($epoch !== '')$out['membership_deposit_epoch'] = $epoch;
        $out = array_replace($out, MembershipCostService::calculate($tier, $period, $experience, $deposit, $buyExperience));
        $out['cost_fingerprint'] = $deposit['fingerprint'] ?? hash('sha256', '[]');
        if (empty($tier['enabled']) || empty($period['enabled']) || $full <= 0) {
            $out['can_purchase'] = false;
            $out['reason'] = '该等级尚未开通当前付费周期，请重新选择';
            return $out;
        }
        if (self::financialReceiptHold($rows)) {
            $out['can_purchase'] = false;
            $out['reason'] = self::FINANCIAL_HOLD_MESSAGE;
            return $out;
        }
        if (!empty($deposit['review_required']) || !empty($experience['cost_review_required'])) {
            $out['can_purchase'] = false;
            $out['reason'] = !empty($deposit['review_message']) ? $deposit['review_message'] : '原押金、购买经验或退款待平台核对，暂不重复收费';
            return $out;
        }
        if (!$eligible && !$buyExperience) {
            $out['can_purchase'] = false;
            $out['reason'] = '当前门店经验'.$value.'，本等级需要'.$required.'；可通过真实成交积累'.(!empty($tier['experience_purchase_enabled']) ? '，或选择购买本次缺少的经验' : '');
            return $out;
        }
        if (($state['active'] && (int) $tier['rank'] < $state['rank']) || $reservedRank > (int) $tier['rank']) {
            $out['can_purchase'] = false;
            $out['reason'] = '已有有效或预付的更高等级期间，不能用低等级订单覆盖；到期后再选择';
            return $out;
        }
        foreach ($rows as $row) if (!empty($row['refund_pending']) || !empty($row['review'])) {
            $out['can_purchase'] = false;
            $out['reason'] = '该主体存在退款或权益待核对记录，请先由平台处理，不会重复收费';
            return $out;
        }
        $credit = 0;
        if (($state['active'] && (int) $tier['rank'] > $state['rank']) || ($reservedRank > 0 && (int) $tier['rank'] > $reservedRank)) {
            if (!$rows) {
                $out['can_purchase'] = false;
                $out['reason'] = '原等级没有可核实的付款及期限记录，不能估算补差，请平台核对';
                return $out;
            }
            foreach ($segments as $segment) {
                [$row,$a,$b] = $segment;
                $a = max($a, $now);
                if ($b <= $a) continue;
                $value = self::prorate(Rules::cents($row['rule']['full_amount']), $b - $a, (int) $row['rule']['period_end_at'] - (int) $row['rule']['period_start_at']);
                if ($value <= 0) continue;
                $out['credit_sources'][] = [
                    'order_id' => (int) $row['id'],
                    'start_at' => $a,
                    'end_at' => $b,
                    'amount' => Rules::amount($value),
                    'source_fingerprint' => self::sourceFingerprint($row)
                ];
                $credit += $value;
            }
            if ($credit >= $full) {
                $out['can_purchase'] = false;
                $out['reason'] = '原权益剩余价值不低于所选套餐价格，请选择更长周期或联系平台核对；不会丢弃差额';
                return $out;
            }
        }
        $out['credit_amount'] = Rules::amount($credit);
        return array_replace($out, MembershipCostService::calculate($tier, $period, $experience, $deposit, $buyExperience, $credit));
    }

    public static function assertSnapshot(array $order, array $rule): void
    {
        if (array_key_exists('membership_deposit_epoch', $rule)
            && (!is_string($rule['membership_deposit_epoch']) || !preg_match('/^[a-f0-9]{64}$/D', $rule['membership_deposit_epoch'])))throw new CommonException('会员重新开通的退出来源快照无效');
        if (!in_array($rule['schema_version'] ?? '', [self::SNAPSHOT_SCHEMA, 'hsx_lead.entity_membership.v3'], true)
            || ($rule['purpose'] ?? '') !== 'membership'
            || !MembershipCatalogService::validCode($rule['level_code'] ?? null)
            || !is_string($rule['cycle'] ?? null)
            || !preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,23}$/D', $rule['cycle'])
            || !is_array($rule['credit_sources'] ?? null)) throw new CommonException('主体会员订单快照不完整，请联系平台核对');
        if (!is_int($rule['level_id'] ?? null)
            || $rule['level_code'] !== 'ML'.$rule['level_id']
            || !is_int($rule['rank'] ?? null)
            || $rule['rank'] < 1
            || $rule['rank'] > 2147483647
            || !is_int($rule['display_rank'] ?? null)
            || $rule['display_rank'] < 1
            || !is_string($rule['level_name'] ?? null)
            || trim($rule['level_name']) === ''
            || !is_string($rule['cycle_name'] ?? null)
            || trim($rule['cycle_name']) === ''
            || !is_bool($rule['unlimited'] ?? null)
            || !is_int($rule['account_limit'] ?? null)
            || $rule['account_limit'] < 1
            || $rule['account_limit'] > 99999
            || !is_int($rule['quoted_at'] ?? null)
            || $rule['quoted_at'] <= 0) throw new CommonException('会员等级或账号权益快照无效，请平台核对');
        if (!is_array($rule['capabilities'] ?? null)
            || !is_array($rule['benefits'] ?? null)
            || !is_string($rule['catalog_fingerprint'] ?? null)
            || !preg_match('/^[a-f0-9]{64}$/D', $rule['catalog_fingerprint'])) throw new CommonException('会员原单权益快照不完整');
        $required = Rules::cents($rule['experience_required'] ?? '');
        $gate = EntityExperienceService::gateEnabled($rule);
        if (!is_bool($rule['is_entry'] ?? null)
            || $required !== (!$gate || $rule['is_entry'] ? 0 : $rule['rank'] * 100))throw new CommonException('会员入门档及经验门槛快照不一致');
        if (!is_string($rule['level_color'] ?? null)
            || ($rule['level_color'] !== '' && !preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/iD', $rule['level_color'])))throw new CommonException('会员等级配色快照格式异常');
        foreach (MembershipCatalogService::CAPABILITIES as $key) if (!is_bool($rule['capabilities'][$key] ?? null)) throw new CommonException('会员原单权限快照不完整');
        // Keep original paid copy frozen too: a later UI title/icon change must
        // not revoke grants. Validate shape, trusted icon set and permissions.
        if (!\addon\hsx_lead\app\support\PhpRuntime::isList($rule['benefits'])
            || count($rule['benefits']) !== count(MembershipCatalogService::CAPABILITIES)) throw new CommonException('会员原单权益展示不完整');
        $seen = [];
        foreach ($rule['benefits'] as $benefit) {
            $key = is_array($benefit) ? ($benefit['key'] ?? null) : null;
            if (!is_string($key)
                || !in_array($key, MembershipCatalogService::CAPABILITIES, true)
                || isset($seen[$key])
                || !is_string($benefit['title'] ?? null)
                || trim($benefit['title']) === ''
                || mb_strlen($benefit['title']) > 80
                || !is_string($benefit['desc'] ?? null)
                || mb_strlen($benefit['desc']) > 200
                || !in_array($benefit['icon'] ?? null, MembershipCatalogService::BENEFIT_ICONS, true)
                || ($benefit['enabled'] ?? null) !== $rule['capabilities'][$key]) throw new CommonException('会员原单权益展示与权限不一致');
            $seen[$key] = true;
        }
        $months = $rule['months'] ?? 0;
        if (!is_int($months) || $months < 1 || $months > 120) throw new CommonException('会员原单购买月份无效');
        $start = (int) ($rule['period_start_at'] ?? 0);
        $end = (int) ($rule['period_end_at'] ?? 0);
        if (($rule['entitlement_months'] ?? 0) !== $months
            || $start <= 0
            || $end !== Rules::addMonths($start, $months)
            || $end > 4294967295) throw new CommonException('会员原单期限不一致，未开通权益');
        $full = Rules::cents($rule['full_amount'] ?? '');
        $credit = Rules::cents($rule['credit_amount'] ?? '');
        $cash = Rules::cents($rule['payable_amount'] ?? '');
        if ($cash <= 0
            || $cash > 9999999999
            || $credit > $full
            || $cash !== Rules::cents((string) $order['amount'])
            || (($rule['schema_version'] ?? '') === 'hsx_lead.entity_membership.v3' && $full - $credit !== $cash)) throw new CommonException('会员原价、抵扣和真实付款金额不一致');
        if (($rule['schema_version'] ?? '') === self::SNAPSHOT_SCHEMA) {
            MembershipCostService::assertSnapshot($order, $rule);
            if (!is_string($rule['cost_fingerprint'] ?? null)
                || !preg_match('/^[a-f0-9]{64}$/D', $rule['cost_fingerprint']))throw new CommonException('会员押金及经验来源快照不完整');
        }
        $sum = 0;
        foreach ($rule['credit_sources'] as $source) {
            if (!is_array($source)
                || (int) ($source['order_id'] ?? 0) <= 0
                || (int) ($source['end_at'] ?? 0) <= (int) ($source['start_at'] ?? 0)
                || !is_string($source['source_fingerprint'] ?? null)
                || !preg_match('/^[a-f0-9]{64}$/D', $source['source_fingerprint'])) throw new CommonException('会员抵扣来源不完整');
            $sum += Rules::cents($source['amount'] ?? '');
        }if ($sum !== $credit) throw new CommonException('会员抵扣合计不一致');
    }

    public static function assertOrderPayable(int $site, array $order): void
    {
        $ctx = new Context($site, 0, (int) $order['member_id']);
        TeamService::assertOwner($ctx, (int) $order['merchant_id'], true);
        if (MembershipDepositService::purchaseState($site, (int) $order['merchant_id'], true)['deposit_purchase_blocked'])throw new CommonException('门店退押金申请仍在处理中，未发起付款');
        $rule = Data::decode($order['rule_json']);
        self::assertSnapshot($order, $rule);
        self::assertPriceOverrideSource($site, $order, $rule);
        self::assertDepositEpoch($site, (int) $order['merchant_id'], $rule);
        if (!self::catalogMatches($rule, MembershipCatalogService::levels($site, true))) throw new CommonException('原会员套餐已调整或停售，请先取消未支付单后重新选择；当前未发起支付');
        if ((int) ($rule['quoted_at'] ?? 0) + self::TTL < time()) throw new CommonException('原会员报价已过期，请先取消未支付订单，再重新报价；当前未扣款');
        $rows = self::ledger($site, (int) $order['merchant_id'], true);
        $partner = Store::partner($site, (int) $order['merchant_id'], true);
        if (self::financialReceiptHold($rows))throw new CommonException(self::FINANCIAL_HOLD_MESSAGE);
        if (!hash_equals((string) ($rule['base_fingerprint'] ?? ''), self::fingerprint($rows, $partner))) throw new CommonException('主体权益或原付款状态已变化，请平台核对原订单，不会重新扣款');
        foreach ($rows as $row) if (!empty($row['refund_pending']) || !empty($row['review'])) throw new CommonException('原权益正在退款或核对，暂不能继续付款');
        self::assertFunding($site, (int) $order['merchant_id'], $rule);
    }

    /** Called after framework receipt verification under site, order and partner locks. */
    public static function grant(int $site, array $order, array &$rule, int $paidAt): array
    {
        self::assertSnapshot($order, $rule);
        self::assertPriceOverrideSource($site, $order, $rule);
        $partner = Store::partner($site, (int) $order['merchant_id'], true);
        if (MembershipDepositService::purchaseState($site, (int) $order['merchant_id'], true)['deposit_purchase_blocked'])throw new CommonException('实际付款时门店正在退出协作，请平台核对原付款');
        self::assertDepositEpoch($site, (int) $order['merchant_id'], $rule);
        $rows = self::ledger($site, (int) $order['merchant_id'], true);
        if (!hash_equals((string) ($rule['base_fingerprint'] ?? ''), self::fingerprint($rows, $partner))) throw new CommonException('原权益状态已变化，已收款须人工核对，不能覆盖新权益');
        foreach ($rows as $row) if (!empty($row['refund_pending']) || !empty($row['review'])) throw new CommonException('原权益正在退款或核对，暂未授予新等级');
        self::assertFunding($site, (int) $order['merchant_id'], $rule, (int) $order['id']);
        if ($paidAt <= 0 || $paidAt > time() + 60 || $paidAt < (int) $rule['quoted_at']) throw new CommonException('原会员付款时间异常，请平台核对已收款');
        if ($paidAt > (int) $rule['quoted_at'] + self::TTL
            && !self::signedVirtualReceipt($site, $order, $rule, $paidAt)) throw new CommonException('原会员付款时间超过报价有效期，且未核实原虚拟支付合同，请平台核对已收款');
        self::assertSources($rows, $rule);
        // Opening and upgrading start at the verified framework receipt time, not quotation/callback time.
        if ((int) $rule['period_start_at'] <= (int) $rule['quoted_at']) {
            $rule['quoted_period_start_at'] = $rule['period_start_at'];
            $rule['quoted_period_end_at'] = $rule['period_end_at'];
            $rule['period_start_at'] = $paidAt;
            $rule['period_end_at'] = Rules::addMonths($paidAt, (int) $rule['months']);
        }
        return ['membership_level' => $rule['level_code'], 'membership_until' => (int) $rule['period_end_at']];
    }

    /** Quote TTL controls issuing a NEW charge, not delivery of a verified
     * late receipt for an already signed, immutable virtual-payment contract.
     * No caller/client flag can relax this: require the native successful
     * receipt and the exact original server-side source prepared in time.
     */
    private static function signedVirtualReceipt(int $site, array $order, array $rule, int $paidAt): bool
    {
        $pays = Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)
            ->where('trade_id', (int) $order['id'])->where('status', PayDict::STATUS_FINISH)->lock(true)->select()->toArray();
        if (count($pays) !== 1)return false;
        $pay = $pays[0];
        $type = \addon\hsx_lead\app\service\virtualpay\VirtualPaySettings::TYPE;
        if (($pay['type'] ?? '') !== $type || ($pay['channel'] ?? '') !== 'weapp' || ($pay['main_type'] ?? '') !== PayDict::MEMBER
            || (int) $pay['main_id'] !== (int) $order['member_id'] || (int) $pay['pay_time'] !== $paidAt
            || Rules::cents((string) $pay['money']) !== Rules::cents((string) $order['amount']) || (string) $pay['out_trade_no'] === '')return false;
        $source = Db::name('hsx_lead_virtual_order')->where('site_id', $site)->where('out_trade_no', (string) $pay['out_trade_no'])->lock(true)->find();
        if (!$source || (int) $source['pay_id'] !== (int) $pay['id'] || (int) $source['order_id'] !== (int) $order['id']
            || (int) $source['member_id'] !== (int) $order['member_id'] || (int) $source['amount_cents'] !== Rules::cents((string) $order['amount'])
            || !in_array($source['status'], ['prepared', 'pending', 'receipt_pending', 'delivery_pending', 'paid'], true))return false;
        if (in_array($source['status'], ['receipt_pending', 'delivery_pending', 'paid'], true)
            && ((int) $source['paid_at'] !== $paidAt || !is_string($source['wx_order_id']) || $source['wx_order_id'] === ''
                || !hash_equals($source['wx_order_id'], (string) ($pay['trade_no'] ?? ''))))return false;
        $preparedAt = (int) $source['create_time'];
        $quotedAt = (int) $rule['quoted_at'];
        if ($preparedAt < $quotedAt
            || $preparedAt < (int) $order['create_time']
            || $preparedAt > PaymentOrderLifecycle::expiresAt($order)
            || $preparedAt > $paidAt)return false;
        return is_string($source['snapshot_hash']) && hash_equals($source['snapshot_hash'], (new MembershipVirtualPayAdapter())->fingerprint($order));
    }

    public function closeUnpaid(int $id): array
    {
        $merchant = TeamService::assertOwner($this->context);
        $order = Store::row('coop_order', $this->context->site, $id);
        if ($order['purpose'] !== 'membership'
            || (int) $order['merchant_id'] !== (int) $merchant['id']
            || (int) $order['member_id'] !== $this->context->memberId)throw new CommonException('只能关闭本人为当前主体创建的会员付款订单');
        return (new PaymentOrderLifecycle($this->context))->close($id, ['confirm' => true]);
    }

    /** Trusted admin orchestration calls before closing, then again before inserting a replacement. */
    public static function prepareRepricedSnapshot(int $site, array $old, string $serviceAmount, array $provenance): array
    {
        if ($site <= 0
            || (int) ($old['site_id'] ?? 0) !== $site
            || ($old['purpose'] ?? '') !== 'membership'
            || !in_array($old['status'] ?? '', ['unpaid', 'closed'], true))throw new CommonException('仅本站未付或已核实关闭的会员原单可以调整服务费');
        $ctx = new Context($site, 0, (int) $old['member_id']);
        TeamService::assertOwner($ctx, (int) $old['merchant_id'], true);
        $config = (new ConfigService($ctx))->get(true);
        $partner = Store::partner($site, (int) $old['merchant_id'], true);
        if (empty($config['enabled']) || (int) $partner['blocked_at'] > 0)throw new CommonException('当前门店不可购买会员，未创建改价替换单');
        $pending = Store::query('coop_order', $site)->where('merchant_id', (int) $old['merchant_id'])->where('purpose', 'membership')->where('status', 'unpaid')->where('id', '<>', (int) $old['id'])->lock(true)->find();
        if ($pending)throw new CommonException('门店已有另一笔待支付会员单，未创建改价替换单');
        $rule = Data::decode($old['rule_json']);
        if (($rule['schema_version'] ?? '') !== self::SNAPSHOT_SCHEMA)throw new CommonException('原会员单缺少三项费用快照，请重新报价，不能直接改价');
        if (!empty($rule['requires_manual_review']))throw new CommonException('原会员付款仍需人工核对，不能通过改价重新收费');
        if ((int) ($rule['quoted_at'] ?? 0) > time()
            || (int) ($rule['quoted_at'] ?? 0) + self::TTL <= time())throw new CommonException('原会员报价已过期或时间异常，请重新报价，不能通过改价延长原单期限');
        self::assertOrderPayable($site, $old);
        self::assertSources(self::ledger($site, (int) $old['merchant_id'], true), $rule);
        // Keep quoted_at and all period/credit-source timestamps. Resetting the
        // ten-minute TTL would silently extend an old upgrade-credit valuation.
        return MembershipCostService::repriceService($old, $rule, $serviceAmount, $provenance);
    }

    /** Repricing never trusts a detached amount or an arbitrary old trade ID. */
    private static function assertPriceOverrideSource(int $site, array $order, array $rule): void
    {
        if (!isset($rule['price_override']))return;
        $override = $rule['price_override'];
        $id = (int) $override['source_order_id'];
        if ($id === (int) $order['id'])throw new CommonException('改价必须使用独立替换付款单');
        $source = Store::row('coop_order', $site, $id, true);
        $sourceRule = Data::decode($source['rule_json']);
        if ($source['status'] !== 'closed'
            || $source['purpose'] !== 'membership'
            || !empty($sourceRule['requires_manual_review'])
            || (int) $source['member_id'] !== (int) $order['member_id']
            || (int) $source['merchant_id'] !== (int) $order['merchant_id']
            || !hash_equals($override['source_snapshot_fingerprint'], MembershipCostService::pricingFingerprint($source, $sourceRule)))throw new CommonException('会员改价原单未关闭或来源快照已变化，暂不继续付款或开通权益');
        $expected = MembershipCostService::repriceService($source, $sourceRule, $rule['service_amount'], [
                'operator_uid' => $override['operator_uid'],
                'reason' => $override['reason'],
                'at' => $override['at']
            ]);
        // Processing metadata may be appended by the payment/promotion services;
        // every immutable financial/rights field must match the trusted source.
        foreach ($expected['rule'] as $key => $value)if (($rule[$key] ?? null) !== $value)throw new CommonException('会员改价替换单与原权益、押金或经验明细不一致');
        if (Rules::cents((string) $order['amount']) !== Rules::cents($expected['amount']))throw new CommonException('会员改价合计与真实付款金额不一致');
    }

    /** Verified successful refund only; partial refunds freeze affected rights, never guess days. */
    public static function refund(int $site, array $source, array $refund): void
    {
        $rule = Data::decode($source['rule_json']);
        self::assertSnapshot($source, $rule);
        $seen = $rule['refund_receipts'] ?? [];
        if (in_array($refund['refund_no'], $seen, true)) return;
        $seen[] = $refund['refund_no'];
        $rule['refund_receipts'] = $seen;
        $refundRows = Db::name('pay_refund')->where('site_id', $site)->where('out_trade_no', $source['out_trade_no'])->where('status', RefundDict::SUCCESS)->lock(true)->select()->toArray();
        $parts = ($rule['schema_version'] ?? '') === self::SNAPSHOT_SCHEMA ? MembershipCostService::refundedComponents($source, $rule, $refundRows) : null;
        $total = 0;
        if ($parts !== null)$total = $parts['total'];
        else foreach ($refundRows as $receipt)$total += Rules::cents((string) $receipt['money']);
        if ($total > Rules::cents((string) $source['amount'])) throw new CommonException('会员累计退款超过原实付，未修改权益，请核对');
        $full = $total === Rules::cents((string) $source['amount']);
        $depositOnly = $parts !== null && !$parts['review'] && $parts['total'] > 0 && $parts['service'] === 0 && $parts['experience'] === 0;
        $rule['membership_refund_status'] = $full ? 'refunded' : ($depositOnly ? 'deposit_refunded' : 'partial_review');
        if (!$full && !$depositOnly) $rule['requires_manual_review'] = 'membership_partial_refund_review';
        elseif (in_array($rule['requires_manual_review'] ?? '', [
                    'membership_partial_refund_review',
                    'membership_credit_refund_review',
                    'membership_receipt_conflict'
                ], true)) {
            unset($rule['requires_manual_review']);
            $rule['membership_review_resolved_by_full_refund'] = $full;
        }
        Store::query('coop_order', $site)->where('id', $source['id'])->update(['rule_json' => Data::json($rule), 'update_time' => time()]);
        $rows = self::ledger($site, (int) $source['merchant_id'], true);
        foreach ($rows as $row) if (!empty($row['dependency_invalid']) && $row['id'] !== $source['id']) {
            $dependent = $row['rule'];
            $dependent['requires_manual_review'] = 'membership_credit_refund_review';
            Store::query('coop_order', $site)->where('id', $row['id'])->update(['rule_json' => Data::json($dependent), 'update_time' => time()]);
        }
        $state = self::stateFrom($rows, [], [], time());
        $partner = Store::partner($site, (int) $source['merchant_id'], true);
        Store::query('partner', $site)->where('id', $partner['id'])->update([
                'membership_level' => $state['level_code'] !== '' ? $state['level_code'] : ($partner['membership_level'] ?: $rule['level_code']),
                'membership_until' => $state['expires_at'],
                'update_time' => time()
            ]);
        Data::audit($site, 0, (int) $source['member_id'], 'coop_order', (int) $source['id'], 'membership_refund_processed', [
                'refund_no' => $refund['refund_no'],
                'status' => $rule['membership_refund_status'],
                'level_code' => $state['level_code'],
                'expires_at' => $state['expires_at']
            ]);
    }

    /** Close the financial exception only; a refunded closed purchase never gains rights. */
    public static function resolveClosedReceipt(int $site, array $source): bool
    {
        $rule = Data::decode($source['rule_json']);
        if ($source['status'] !== 'closed'
            || $source['purpose'] !== 'membership'
            || ($rule['requires_manual_review'] ?? '') !== 'membership_closed_receipt_conflict')return false;
        $receipts = Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', $source['id'])->where('status', PayDict::STATUS_FINISH)->where('main_type', PayDict::MEMBER)->where('main_id', (int) $source['member_id'])->where('money', (string) $source['amount'])->where('out_trade_no', '<>', '')->lock(true)->select()->toArray();
        $complete = count($receipts) > 0;
        $wasResolved = ($rule['closed_payment_refund_status'] ?? '') === 'refunded';
        foreach ($receipts as $receipt) {
            $settled = MembershipCostService::receiptRefund($site, $source, $receipt, true);
            $no = (string) $receipt['out_trade_no'];
            $entry = $rule['closed_payment_receipts'][$no] ?? [
                'out_trade_no' => $no,
                'money' => (string) $receipt['money'],
                'pay_time' => (int) $receipt['pay_time']
            ];
            if ($settled['full'])$entry['resolved_refund'] = ['amount' => $settled['amount'], 'receipts' => $settled['receipts']];
            else {
                $complete = false;
                unset($entry['resolved_refund']);
            }
            $rule['closed_payment_receipts'][$no] = $entry;
        }
        if ($complete) {
            unset($rule['requires_manual_review']);
            $rule['closed_payment_refund_status'] = 'refunded';
        } else $rule['closed_payment_refund_status'] = 'pending_review';
        Store::query('coop_order', $site)->where('id', $source['id'])->update(['rule_json' => Data::json($rule), 'update_time' => time()]);
        if ($complete
            && !$wasResolved)Data::audit($site, 0, (int) $source['member_id'], 'coop_order', (int) $source['id'], 'closed_membership_receipt_refunded', ['amount' => $source['amount'], 'receipt_count' => count($receipts)]);
        return $complete;
    }

    private static function ledger(int $site, int $merchant, bool $lock = false, ?array $preloaded = null): array
    {
        if ($preloaded === null) {
            $query = Store::query('coop_order', $site)->where('merchant_id', $merchant)->where('purpose', 'membership')->where('status', 'paid')->order('id');
            if ($lock) $query->lock(true);
            $rows = $query->select()->toArray();
        } else $rows = $preloaded['orders'];
        $epoch = $preloaded['membership_epoch'] ?? MembershipDepositService::membershipEpoch($site, $merchant, $lock);
        $out = [];
        foreach ($rows as $row) {
            $rule = Data::decode($row['rule_json']);
            $row['rule'] = $rule;
            $row['valid'] = false;
            $row['refund_pending'] = false;
            $row['review'] = !empty($rule['requires_manual_review']);
            $parts = null;
            try {
                self::assertSnapshot($row, $rule);
                if ($preloaded === null) {
                    $payment = Db::name('pay')->where('site_id', $site)->where('trade_type', PaymentService::TRADE_TYPE)->where('trade_id', $row['id'])->where('out_trade_no', $row['out_trade_no'])->where('status', PayDict::STATUS_FINISH);
                    if ($lock) $payment->lock(true);
                    $pay = $payment->find();
                } else $pay = $preloaded['payments'][(int) $row['id'].':'.(string) $row['out_trade_no']] ?? null;
                if (!$pay
                    || $pay['main_type'] !== PayDict::MEMBER
                    || (int) $pay['main_id'] !== (int) $row['member_id']
                    || Rules::cents((string) $pay['money']) !== Rules::cents((string) $row['amount'])) throw new CommonException('原会员收款事实不完整');
                if ($preloaded === null) {
                    $refundQuery = Db::name('pay_refund')->where('site_id', $site)->where('out_trade_no', $row['out_trade_no']);
                    if ($lock) $refundQuery->lock(true);
                    $refunds = $refundQuery->select()->toArray();
                } else $refunds = $preloaded['refunds'][(string) $row['out_trade_no']] ?? [];
                $refunded = 0;
                foreach ($refunds as $refund) {
                    if ($refund['status'] === RefundDict::SUCCESS) $refunded += Rules::cents((string) $refund['money']);
                    if (in_array($refund['status'], [RefundDict::WAIT, RefundDict::DEALING], true)) $row['refund_pending'] = true;
                }
                if (($rule['schema_version'] ?? '') === self::SNAPSHOT_SCHEMA) {
                    $parts = MembershipCostService::refundedComponents($row, $rule, $refunds);
                    $refunded = $parts['total'];
                    $row['refund_pending'] = $parts['pending'];
                    if ($parts['review'])$row['review'] = true;
                }
                $row['refunded'] = $refunded;
                $rightsRefunded = $refunded;
                if (isset($parts)
                    && ($rule['schema_version'] ?? '') === self::SNAPSHOT_SCHEMA
                    && !$parts['review'])$rightsRefunded = $parts['service'] + $parts['experience'];
                $row['valid'] = $rightsRefunded === 0 && !$row['review'] && ($rule['membership_deposit_epoch'] ?? '') === $epoch;
                if ($row['valid']) self::assertSources($out, $rule);
            } catch (CommonException $error) {
                $row['valid'] = false;
                $row['dependency_invalid'] = !empty($rule['credit_sources']);
                if (empty($row['refunded'])) $row['review'] = true;
            }
            $out[] = $row;
        }return $out;
    }

    private static function assertSources(array $rows, array $rule): void
    {
        $map = [];
        foreach ($rows as $row) $map[(int) $row['id']] = $row;
        foreach ($rule['credit_sources'] as $source) {
            $row = $map[(int) $source['order_id']] ?? null;
            if (!$row
                || empty($row['valid'])
                || !hash_equals((string) $source['source_fingerprint'], self::sourceFingerprint($row))) throw new CommonException('升级抵扣原收款已退款或变化，暂未开通高级权益');
            $a = (int) $source['start_at'];
            $b = (int) $source['end_at'];
            $raw = $row['rule'];
            if ($a < (int) $raw['period_start_at']
                || $b > (int) $raw['period_end_at']
                || Rules::cents($source['amount']) !== self::prorate(Rules::cents($raw['full_amount']), $b - $a, (int) $raw['period_end_at'] - (int) $raw['period_start_at'])) throw new CommonException('会员抵扣期限或金额与原实付不符');
        }
    }

    /** Remaining grant portions, subtracting only verified, non-refunded dependent upgrades. */
    private static function availableSegments(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (empty($row['valid'])) continue;
            $parts = [[(int) $row['rule']['period_start_at'], (int) $row['rule']['period_end_at']]];
            foreach ($rows as $consumer) if (!empty($consumer['valid'])) foreach ($consumer['rule']['credit_sources'] as $source) if ((int) $source['order_id'] === (int) $row['id']) {
                $next = [];
                foreach ($parts as [$a,$b]) {
                    $s = (int) $source['start_at'];
                    $e = (int) $source['end_at'];
                    if ($e <= $a || $s >= $b) {
                        $next[] = [$a, $b];
                        continue;
                    }if ($s > $a) $next[] = [$a, $s];
                    if ($e < $b) $next[] = [$e, $b];
                }$parts = $next;
            }
            foreach ($parts as [$a,$b]) $out[] = [$row, $a, $b];
        }return $out;
    }

    public static function stateFrom(array $rows, array $partner, array $tiers, int $now): array
    {
        $none = MembershipCatalogService::emptyCapabilities();
        $default = [
            'level_code' => '',
            'level_id' => 0,
            'level_name' => '普通会员',
            'level_visual' => MembershipCatalogService::visual([], 0),
            'level_color' => MembershipCatalogService::color(0),
            'rank' => 0,
            'display_rank' => 0,
            'active' => false,
            'expires_at' => 0,
            'account_limit' => 1,
            'unlimited' => false,
            'capabilities' => $none,
            'benefits' => MembershipCatalogService::benefits($none),
            'experience_gate_enabled' => true,
            'configuration_source' => 'framework_member_level'
        ];
        $current = null;
        $end = 0;
        $segments = self::availableSegments($rows);
        foreach ($segments as [$row,$a,$b]) if ($a <= $now && $b > $now && (!$current || (int) $row['rule']['rank'] > (int) $current['rank'])) {
            $current = $row['rule'];
            $end = $b;
        }
        if ($current) {
            do {
                $before = $end;
                foreach ($segments as [$row,$a,$b]) if ($row['rule']['level_code'] === $current['level_code'] && $a <= $end && $b > $end) $end = $b;
            } while ($before !== $end);
            // A deleted/edited sale definition cannot retrospectively remove
            // rights a merchant has already paid for. Only a verified refund
            // or grant expiry changes this frozen entitlement contract.
            return array_replace($default, [
                    'level_code' => $current['level_code'],
                    'level_id' => (int) $current['level_id'],
                    'level_name' => $current['level_name'],
                    'level_visual' => MembershipCatalogService::visual([], (int) $current['display_rank']),
                    'level_color' => MembershipCatalogService::color((int) $current['display_rank']),
                    'rank' => (int) $current['rank'],
                    'display_rank' => (int) $current['display_rank'],
                    'active' => true,
                    'expires_at' => $end,
                    'account_limit' => (int) $current['account_limit'],
                    'unlimited' => (bool) $current['unlimited'],
                    'capabilities' => $current['capabilities'],
                    'benefits' => $current['benefits'],
                    'experience_gate_enabled' => EntityExperienceService::gateEnabled($current)
                ]);
        }
        foreach ($rows as $row) if (!empty($row['valid'])
            && (int) $row['rule']['period_end_at'] <= $now
            && (int) $row['rule']['period_end_at'] > $default['expires_at']) $default = array_replace($default, [
                'level_code' => $row['rule']['level_code'],
                'level_id' => (int) $row['rule']['level_id'],
                'level_name' => $row['rule']['level_name'],
                'level_visual' => MembershipCatalogService::visual([], (int) $row['rule']['display_rank']),
                'level_color' => MembershipCatalogService::color((int) $row['rule']['display_rank']),
                'rank' => (int) $row['rule']['rank'],
                'display_rank' => (int) $row['rule']['display_rank'],
                'expires_at' => (int) $row['rule']['period_end_at'],
                'experience_gate_enabled' => EntityExperienceService::gateEnabled($row['rule'])
            ]);
        return $default;
    }

    private static function catalogMatches(array $rule, array $tiers): bool
    {
        foreach ($tiers as $tier) if ($tier['code'] === ($rule['level_code'] ?? '')) {
            $fingerprintTier = $tier;
            // Existing frozen snapshots had no gate field. Preserve their
            // exact fingerprint only while the current policy is unchanged.
            if (!array_key_exists('experience_gate_enabled', $rule)) {
                if (!EntityExperienceService::gateEnabled($tier))return false;
                unset($fingerprintTier['experience_gate_enabled']);
            }
            if (empty($tier['enabled']) || !hash_equals((string) ($rule['catalog_fingerprint'] ?? ''), self::tierFingerprint($fingerprintTier))) return false;
            foreach ($tier['cycles'] as $cycle) if ($cycle['key'] === ($rule['cycle'] ?? '')) return !empty($cycle['enabled'])
                && $cycle['months'] === ($rule['months'] ?? 0)
                && Rules::cents($cycle['price']) === Rules::cents($rule['price_override']['catalog_full_amount'] ?? $rule['full_amount'] ?? '');
        }return false;
    }
    /** Appearance and derived external product keys never change entitlement prices. */
    private static function fingerprintTier(array $tier): array
    {
        unset($tier['level_visual'],$tier['level_color']);
        foreach ($tier['cycles'] as &$cycle)unset($cycle['product_key']);
        unset($cycle);
        return $tier;
    }
    private static function tierFingerprint(array $tier): string
    {
        return hash('sha256', Data::json(self::fingerprintTier($tier)));
    }
    private static function pricingFingerprint(array $tiers): string
    {
        return hash('sha256', Data::json(array_map([self::class, 'fingerprintTier'], $tiers)));
    }
    private static function assertExperience(int $site, int $merchant, string $required): void
    {
        $experience = EntityExperienceService::summary($site, $merchant, true);
        if (!EntityExperienceService::eligible($experience, ['experience_required' => $required])) throw new CommonException('当前门店经验'.$experience['value'].'，本等级需要'.$required.'；尚未满足本次开通、升级或续费条件');
    }
    private static function assertDepositEpoch(int $site, int $merchant, array $rule): void
    {
        if (($rule['membership_deposit_epoch'] ?? '') !== MembershipDepositService::membershipEpoch($site, $merchant, true))throw new CommonException('原付款单属于退出前的权益，请重新核对开通报价；未恢复旧权益');
    }
    private static function assertFunding(int $site, int $merchant, array $rule, int $confirmingOrder = 0): void
    {
        if (($rule['schema_version'] ?? '') !== self::SNAPSHOT_SCHEMA) {
            self::assertExperience($site, $merchant, (string) $rule['experience_required']);
            return;
        }
        $costs = MembershipCostService::balances($site, $merchant, true, $confirmingOrder);
        $experience = EntityExperienceService::summary($site, $merchant, true);
        if ($costs['review_required']
            || !hash_equals($rule['cost_fingerprint'], $costs['fingerprint'])
            || (EntityExperienceService::gateEnabled($rule) && ($rule['experience_value'] ?? null) !== $experience['value']))throw new CommonException('原押金、经验或退款已变化，请取消未付款单后重新核对；不会重复收费');
        if (Rules::cents($experience['value']) + Rules::cents($rule['experience_quantity']) < Rules::cents($rule['experience_required']))throw new CommonException('本次经验补足后仍未满足等级要求');
    }
    private static function sourceFingerprint(array $row): string
    {
        $facts = [
            (int) $row['id'],
            (string) $row['out_trade_no'],
            (int) $row['member_id'],
            (string) $row['amount'],
            $row['rule']['period_start_at'] ?? 0,
            $row['rule']['period_end_at'] ?? 0,
            $row['rule']['full_amount'] ?? '',
            $row['rule']['credit_sources'] ?? [],
            $row['rule']['level_id'] ?? 0,
            $row['rule']['level_code'] ?? '',
            $row['rule']['rank'] ?? 0,
            $row['rule']['months'] ?? 0,
            $row['rule']['capabilities'] ?? [],
            $row['rule']['benefits'] ?? []
        ];
        if (array_key_exists('experience_gate_enabled', $row['rule']))$facts[] = $row['rule']['experience_gate_enabled'];
        if (array_key_exists('membership_deposit_epoch', $row['rule']))$facts[] = $row['rule']['membership_deposit_epoch'];
        return hash('sha256', Data::json($facts));
    }
    private static function fingerprint(array $rows, array $partner): string
    {
        $facts = [];
        foreach ($rows as $row) $facts[] = [
            (int) $row['id'],
            (bool) $row['valid'],
            (bool) $row['refund_pending'],
            (bool) $row['review'],
            (int) ($row['refunded'] ?? 0),
            self::sourceFingerprint($row)
        ];
        return hash('sha256', Data::json([$facts, $partner['membership_level'] ?? '', (int) ($partner['membership_until'] ?? 0)]));
    }
    private static function prorate(int $amount, int $covered, int $total): int
    {
        if ($covered < 0 || $total <= 0 || $covered > $total) throw new CommonException('会员剩余期限无效');
        return (int) bcdiv(bcmul((string) $amount, (string) $covered, 0), (string) $total, 0);
    }
    private function secret(): string
    {
        $key = (string) env('app.auth_key', '');
        if (strlen($key) < 16) throw new CommonException('平台尚未配置安全密钥');
        return hash('sha256', 'hsx_lead.entity_membership.quote.v3|'.$key, true);
    }
    private function sign(array $payload): string
    {
        $body = rtrim(strtr(base64_encode(Data::json($payload)), '+/', '-_'), '=');
        return $body.'.'.hash_hmac('sha256', $body, $this->secret());
    }
    private function verify(string $token): array
    {
        if (strlen($token) > 2048 || substr_count($token, '.') !== 1) throw new CommonException('会员报价无效，请重新读取');
        [$body,$mac] = explode('.', $token, 2);
        if (!hash_equals(hash_hmac('sha256', $body, $this->secret()), $mac)) throw new CommonException('会员报价签名无效');
        $row = json_decode((string) base64_decode(strtr($body, '-_', '+/'), true), true);
        if (!is_array($row)) throw new CommonException('会员报价无效');
        return $row;
    }
}
