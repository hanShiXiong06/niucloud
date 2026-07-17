<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\dict\ErpDict;
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\model\ErpReceivable;
use core\base\BaseAdminService;
use core\exception\CommonException;

/** 客户信用策略与实时未结应收汇总。欠款余额只以应收事实为准，不做重复存储。 */
class ErpCustomerCreditService extends BaseAdminService
{
    public const POLICIES = ['inherit', 'normal', 'remind', 'cash_only', 'blocked'];

    public function profile(int $partyId): array
    {
        return $this->profiles([$partyId])[$partyId] ?? $this->emptyProfile($partyId);
    }

    /** @return array<int,array<string,mixed>> */
    public function profiles(array $partyIds): array
    {
        $partyIds = array_values(array_unique(array_filter(array_map('intval', $partyIds))));
        if ($partyIds === []) return [];

        $parties = ErpParty::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $partyIds)
            ->field('id,party_name,credit_policy,credit_limit,credit_remark,credit_update_uid,credit_update_name,credit_update_at')
            ->select()->toArray();
        $summaryRows = ErpReceivable::where([['site_id', '=', $this->site_id]])
            ->whereIn('party_id', $partyIds)
            ->whereIn('status', [ErpDict::STATUS_PENDING, ErpDict::STATUS_PARTIAL])
            ->whereRaw('amount > settled_amount')
            ->field('party_id,COUNT(id) as outstanding_count,SUM(amount - settled_amount) as outstanding_amount,MIN(occurred_at) as oldest_at')
            ->group('party_id')->select()->toArray();
        $summaryMap = array_column($summaryRows, null, 'party_id');
        $rules = (array)((new ErpConfigService())->getRules()['sale']['credit_control'] ?? []);

        $result = [];
        foreach ($parties as $party) {
            $partyId = (int)$party['id'];
            $result[$partyId] = $this->buildProfile($party, (array)($summaryMap[$partyId] ?? []), $rules);
        }
        return $result;
    }

    public function updatePolicy(int $partyId, array $data): array
    {
        $party = ErpParty::where([['site_id', '=', $this->site_id], ['id', '=', $partyId]])->findOrEmpty();
        if ($party->isEmpty()) throw new CommonException('往来主体不存在');
        $policy = $this->normalizePolicy((string)($data['credit_policy'] ?? 'inherit'));
        $limit = max(0, round((float)($data['credit_limit'] ?? 0), 2));
        $party->save([
            'credit_policy' => $policy,
            'credit_limit' => $limit,
            'credit_remark' => mb_substr(trim((string)($data['credit_remark'] ?? '')), 0, 255),
            'credit_update_uid' => (int)$this->uid,
            'credit_update_name' => (string)$this->username,
            'credit_update_at' => time(),
            'update_at' => time(),
        ]);
        return $this->profile($partyId);
    }

    /** 销售事务内调用；party 行应已加锁，从而串行校验同一客户的信用额度。 */
    public function assertSaleAllowed(ErpParty $party, array $data, float $saleAmount): array
    {
        $profile = $this->profile((int)$party->id);
        $settleMode = (string)($data['settle_mode'] ?? '');
        if (!in_array($settleMode, ['cash', 'credit'], true)) {
            $settleMode = str_contains((string)($data['settle_method'] ?? ''), '现结') ? 'cash' : 'credit';
        }
        if (!$profile['can_sale']) {
            throw new CommonException($profile['message'] ?: '该客户已暂停交易，请先处理客户信用状态');
        }
        if ($settleMode === 'credit' && !$profile['can_credit']) {
            throw new CommonException($profile['message'] ?: '该客户当前不允许挂账，请改为现结');
        }
        $limit = (float)$profile['credit_limit'];
        $received = $settleMode === 'cash' ? max(0, round((float)($data['received_amount'] ?? 0), 2)) : 0.0;
        $newCredit = max(0, round($saleAmount - $received, 2));
        $projected = round((float)$profile['outstanding_amount'] + $newCredit, 2);
        if ($limit > 0 && $projected > $limit + 0.0001) {
            throw new CommonException(sprintf(
                '本单结算后未结应收将达到 ¥%.2f，超过客户信用额度 ¥%.2f，请增加本次收款或调整额度',
                $projected,
                $limit
            ));
        }
        if ($profile['policy'] === 'cash_only') {
            if ($settleMode !== 'cash' || $received + 0.0001 < $saleAmount) {
                throw new CommonException('该客户仅允许全额现结，本次收款必须等于销售总额');
            }
        }
        return $profile;
    }

    private function buildProfile(array $party, array $summary, array $rules): array
    {
        $partyId = (int)$party['id'];
        $configured = $this->normalizePolicy((string)($party['credit_policy'] ?? 'inherit'));
        $defaultPolicy = in_array((string)($rules['default_policy'] ?? 'remind'), ['normal', 'remind', 'cash_only', 'blocked'], true)
            ? (string)$rules['default_policy'] : 'remind';
        $outstandingCount = (int)($summary['outstanding_count'] ?? 0);
        $outstandingAmount = max(0, round((float)($summary['outstanding_amount'] ?? 0), 2));
        $oldestAt = (int)($summary['oldest_at'] ?? 0);
        $oldestDays = $oldestAt > 0 ? max(0, (int)floor((time() - $oldestAt) / 86400)) : 0;
        $hasOutstanding = $outstandingCount > 0 && $outstandingAmount > 0.0001;
        $thresholdTriggered = $hasOutstanding
            && $outstandingAmount + 0.0001 >= max(0, (float)($rules['min_outstanding_amount'] ?? 0))
            && $oldestDays >= max(0, (int)($rules['min_outstanding_days'] ?? 0));

        $manualRestriction = in_array($configured, ['cash_only', 'blocked'], true);
        $policy = $configured === 'inherit' ? $defaultPolicy : $configured;
        if ((int)($rules['enabled'] ?? 1) !== 1 && $configured === 'inherit') {
            $policy = 'normal';
        } elseif (!$manualRestriction && $policy !== 'normal' && !$thresholdTriggered) {
            $policy = 'normal';
        }

        $limit = max(0, round((float)($party['credit_limit'] ?? 0), 2));
        $limitReached = $limit > 0 && $outstandingAmount + 0.0001 >= $limit;
        $canSale = $policy !== 'blocked';
        $canCredit = !in_array($policy, ['cash_only', 'blocked'], true) && !$limitReached;
        $message = '';
        $severity = 'info';
        $debtText = $hasOutstanding
            ? sprintf('有 %d 笔未结应收，共 ¥%.2f，最早 %d 天', $outstandingCount, $outstandingAmount, $oldestDays)
            : '当前没有未结应收';
        if ($policy === 'blocked') {
            $message = '该客户已暂停交易。' . $debtText;
            $severity = 'error';
        } elseif ($policy === 'cash_only') {
            $message = '该客户仅允许全额现结，不允许继续挂账。' . $debtText;
            $severity = 'warning';
        } elseif ($limitReached) {
            $message = sprintf('该客户信用额度 ¥%.2f 已用尽，请改为现结。%s', $limit, $debtText);
            $severity = 'warning';
        } elseif ($policy === 'remind' && $hasOutstanding) {
            $message = '该客户' . $debtText . '，请确认后再继续销售。';
            $severity = 'warning';
        }
        $remark = trim((string)($party['credit_remark'] ?? ''));
        if ($message !== '' && $remark !== '') $message .= ' 原因：' . $remark;

        return [
            'party_id' => $partyId,
            'party_name' => (string)($party['party_name'] ?? ''),
            'configured_policy' => $configured,
            'policy' => $policy,
            'policy_label' => self::policyLabel($policy),
            'credit_limit' => $limit,
            'credit_remark' => $remark,
            'credit_update_uid' => (int)($party['credit_update_uid'] ?? 0),
            'credit_update_name' => (string)($party['credit_update_name'] ?? ''),
            'credit_update_at' => (int)($party['credit_update_at'] ?? 0),
            'outstanding_count' => $outstandingCount,
            'outstanding_amount' => $outstandingAmount,
            'oldest_at' => $oldestAt,
            'oldest_days' => $oldestDays,
            'has_outstanding' => $hasOutstanding,
            'threshold_triggered' => $thresholdTriggered,
            'can_sale' => $canSale,
            'can_credit' => $canCredit,
            'severity' => $severity,
            'message' => $message,
        ];
    }

    private function emptyProfile(int $partyId): array
    {
        return [
            'party_id' => $partyId, 'party_name' => '', 'configured_policy' => 'inherit', 'policy' => 'normal',
            'policy_label' => self::policyLabel('normal'), 'credit_limit' => 0.0, 'credit_remark' => '',
            'credit_update_uid' => 0, 'credit_update_name' => '', 'credit_update_at' => 0,
            'outstanding_count' => 0, 'outstanding_amount' => 0.0, 'oldest_at' => 0, 'oldest_days' => 0,
            'has_outstanding' => false, 'threshold_triggered' => false, 'can_sale' => true, 'can_credit' => true,
            'severity' => 'info', 'message' => '',
        ];
    }

    private function normalizePolicy(string $policy): string
    {
        return in_array($policy, self::POLICIES, true) ? $policy : 'inherit';
    }

    public static function policyLabel(string $policy): string
    {
        return match ($policy) {
            'inherit' => '跟随系统规则',
            'remind' => '欠款时提醒',
            'cash_only' => '仅允许现结',
            'blocked' => '暂停交易',
            default => '正常交易',
        };
    }
}
