<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;
use core\exception\CommonException;

/** Integer minor units throughout; no float arithmetic in settlement calculations. */
final class Rules
{
    /** Only a verified subject payment ledger grants a dynamic catalog tier. */
    public static function membershipState(array $partner, array $config, ?int $now = null): array
    {
        $current = false;
        $site = (int) ($partner['site_id'] ?? 0);
        $merchant = (int) ($partner['merchant_id'] ?? 0);
        if ($site <= 0 || $merchant <= 0) return ['active' => false, 'level_code' => '', 'level_name' => '', 'level_id' => 0, 'rank' => 0, 'display_rank' => 0, 'expires_at' => 0, 'capabilities' => self::emptyCapabilities(), 'benefits' => []];
        $pdo = \think\facade\Db::connect()->getPdo();
        $current = $pdo instanceof \PDO && $pdo->inTransaction();
        // RR may retain a read view made before waiting for the tenant lock.
        // Write gates therefore ask the membership service for current facts.
        return EntityMembershipService::state($site, $merchant, $current ? [] : $config, $now, $current);
    }
    public static function membershipActive(array $partner, array $config, ?int $now = null): bool
    {
        return empty($config['publishing_required']) || !empty(self::membershipState($partner, $config, $now)['active']);
    }
    private static function emptyCapabilities(): array
    {
        return ['can_publish' => false, 'can_accept' => false, 'can_view_inventory' => false, 'all_models' => false];
    }
    /** Global free operation bypasses fees, never invents full-model delivery. */
    public static function capabilities(array $partner, array $config, ?array $membership = null): array
    {
        $membership = $membership ?? self::membershipState($partner, $config);
        $out = self::emptyCapabilities();
        if (!empty($membership['cooperation_exited'])) return $out;
        if (!empty($membership['active'])) foreach ($out as $key => $unused) $out[$key] = ($membership['capabilities'][$key] ?? false) === true;
        if (empty($config['publishing_required'])) foreach (['can_publish', 'can_accept', 'can_view_inventory'] as $key) $out[$key] = true;
        return $out;
    }
    public static function allows(array $partner, array $config, string $capability): bool
    {
        if (!array_key_exists($capability, self::emptyCapabilities())) throw new CommonException('无效的协作权益');
        return self::capabilities($partner, $config)[$capability];
    }
    public static function assertCapability(array $partner, array $config, string $capability): void
    {
        if (self::allows($partner, $config, $capability)) return;
        $action = ['can_publish' => '发布需求或选择交付门店', 'can_accept' => '接收新的客户线索', 'can_view_inventory' => '查看其他门店的库存参考', 'all_models' => '使用全型号交付能力'][$capability] ?? '进行此操作';
        throw new CommonException('当前门店会员不包含此项权益或已到期，暂不能'.$action.'；请查看会员权益。已有工单与账款仍可处理');
    }
    public static function assertMembership(array $partner, array $config): void
    {
        if (!self::membershipActive($partner, $config)) throw new CommonException('请先开通或续费门店会员，已有工单与账款仍可处理');
    }
    public static function demandLimit($value): int
    {
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^(?:0|[1-9][0-9]{0,3})$/D', (string) $value) || (int) $value > 5000) throw new CommonException('需求型号数量上限请填写0—5000的整数');
        return (int) $value;
    }
    /** Zero removes the business limit; the bounded directory still protects request size. */
    public static function demandIds($ids, int $limit): array
    {
        if (!is_array($ids) || !$ids || !\addon\hsx_lead\app\support\PhpRuntime::isList($ids) || count($ids) > 5000) throw new CommonException('请从型号清单选择客户需求，单次不得超过完整清单范围');
        $unique = [];
        foreach ($ids as $id) {
            if (!is_string($id) || $id === '' || strlen($id) > 100) throw new CommonException('所选型号格式不正确，请重新选择');
            $unique[$id] = $id;
        }
        if ($limit > 0 && count($unique) > $limit) throw new CommonException('本次最多选择'.$limit.'个客户需求型号，请减少后重试');
        return array_values($unique);
    }
    public static function cents($value): int
    {
        if ((!is_string($value) && !is_int($value)) || !preg_match('/^(0|[1-9][0-9]{0,11})(?:\.([0-9]{1,2}))?$/D', (string) $value, $m)) throw new CommonException('金额请填写非负数字，最多两位小数');
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }
    public static function amount(int $cents): string
    {
        if ($cents < 0) return '-'.self::amount(-$cents);
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
    public static function split(int $gross, array $config): array
    {
        if ($gross < 0 || $gross > 99999999999999) throw new CommonException('金额超出系统可处理的精度范围');
        $minimum = self::cents($config['commission_minimum'] ?? '50.00');
        if ($minimum < 1) throw new CommonException('请设置大于0的成交最低协作费用');
        if ($gross < $minimum) throw new CommonException('成交后协作佣金最低为'.self::amount($minimum).'元');
        if (empty($config['fee_confirmed'])) throw new CommonException('平台尚未确认收费规则，暂不能创建付佣订单，请联系平台');
        $basis = self::cents((string) ($config['fee_percent'] ?? '10.00'));
        if ($basis > 10000) throw new CommonException('平台费比例无效');
        $fee = max(self::cents($config['fee_minimum'] ?? '9.90'), intdiv($gross * $basis + 5000, 10000));
        if ($fee >= $gross) throw new CommonException('平台费须低于本次佣金，确保发布方有可得收益，请联系平台核对规则');
        return ['amount' => self::amount($gross), 'fee_amount' => self::amount($fee), 'net_amount' => self::amount($gross - $fee)];
    }
    public static function addMonths(int $timestamp, int $months = 1): int
    {
        if ($months < 1 || $months > 120) throw new CommonException('有效期月份应为1—120');
        $at = (new \DateTimeImmutable('@'.$timestamp))->setTimezone(new \DateTimeZone(date_default_timezone_get()));
        $first = $at->modify('first day of this month')->modify('+'.$months.' months');
        return $first->setDate((int) $first->format('Y'), (int) $first->format('m'), min((int) $at->format('d'), (int) $first->format('t')))->getTimestamp();
    }
    /** The paid order's duration, never the site's subsequently changed plan. */
    public static function entitlementMonths(string $purpose, array $snapshot): int
    {
        $months = $snapshot['months'] ?? null;
        if ($purpose !== 'membership' || !is_int($months) || $months < 1 || $months > 120) throw new CommonException('主体会员期限快照无效，请联系平台核对原单');
        return $months;
    }
    public static function version(array $row, array $input): void
    {
        $value = $input['assignment_version'] ?? null;
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^[0-9]{1,10}$/D', (string) $value) || (int) $value !== (int) $row['assignment_version']) throw new CommonException('交付对象或状态已变化，请刷新详情后操作');
    }
    public static function status(string $status): string
    {
        return ['selecting' => '待选门店', 'assigned' => '待接单', 'following' => '跟进中', 'unpaid' => '成交待付佣', 'paid' => '已付佣', 'no_deal' => '未成交', 'cancelled' => '已取消'][$status] ?? '待核对';
    }
}
