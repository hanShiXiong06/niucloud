<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;

/** Financial facts, not marketing messages. Every scene has its own consent and channel. */
final class NoticeEventCatalog
{
    public const CHANNELS = ['weapp', 'wechat', 'sms'];
    public const EVENTS = [
        'account_balance_changed' => ['account_type' => 'balance', 'name' => '余额变动', 'account_name' => '余额'],
        'account_money_changed' => ['account_type' => 'money', 'name' => '可提现余额变动', 'account_name' => '可提现余额'],
        'account_commission_changed' => ['account_type' => 'commission', 'name' => '佣金变动', 'account_name' => '佣金'],
        'cooperation_acceptance_reminder' => ['account_type' => '', 'account_name' => '', 'group' => 'cooperation', 'name' => '接单倒计时提醒'],
        'cooperation_response_overdue' => ['account_type' => '', 'account_name' => '', 'group' => 'cooperation', 'name' => '门店尚未接单提醒'],
        'cooperation_assignment_accepted' => ['account_type' => '', 'account_name' => '', 'group' => 'cooperation', 'name' => '门店接单成功提醒'],
    ];
    public const VARIABLES = ['account_name' => '账户类型', 'change_direction' => '变动方向', 'change_amount' => '变动金额',
        'account_balance' => '变动后余额', 'occurred_at' => '变动时间', 'record_no' => '账目编号'];
    public const COOP_VARIABLES = ['task_no' => '线索编号', 'status_text' => '处理状态', 'notice_remark' => '操作提示', 'occurred_at' => '发生时间',
        'response_deadline' => '接单提醒时间', 'remaining_minutes' => '剩余分钟', 'store_name' => '相关门店'];
    public static function variables(string $event): array
    {
        if (str_starts_with($event, 'account_'))return self::VARIABLES;
        return $event === 'cooperation_assignment_accepted' ? array_diff_key(self::COOP_VARIABLES, array_flip(['response_deadline', 'remaining_minutes'])) : self::COOP_VARIABLES;
    }
    public static function fieldTypes(string $event, bool $wechat = false): array
    {
        $account = str_starts_with($event, 'account_');
        $all = array_keys(self::variables($event));
        $rules = ['thing' => $all,
            'character_string' => $account ? ['record_no'] : ['task_no'], 'phrase' => $account ? ['change_direction'] : ['status_text'],
            'time' => $account ? ['occurred_at'] : ['occurred_at', 'response_deadline'], 'date' => $account ? ['occurred_at'] : ['occurred_at', 'response_deadline'],
            'number' => $account ? [] : ['remaining_minutes']];
        if ($wechat)$rules['keyword'] = $all;
        foreach ($rules as $key => $variables)$rules[$key] = array_values(array_intersect($variables, $all));
        return $rules;
    }
    public static function key(string $event): string
    {
        return 'hsx_lead_'.$event;
    }
    public static function fromAccount(string $type): ?string
    {
        foreach (self::EVENTS as $event => $definition)if ($type !== '' && $definition['account_type'] === $type)return $event;
        return null;
    }
    public static function definitions(): array
    {
        $result = [];
        foreach (self::EVENTS as $event => $definition) {
            $key = self::key($event);
            $result[$key] = [
                'addon' => 'hsx_lead', 'key' => $key, 'receiver_type' => 1, 'name' => str_ends_with($definition['name'], '提醒') ? $definition['name'] : $definition['name'].'提醒',
                'title' => '业务保存后通知相关本人；服务商受理不代表终端已送达', 'async' => false, 'variable' => self::variables($event)];
        }
        return $result;
    }
    public static function smsDefinitions(): array
    {
        $result = [];
        foreach (self::EVENTS as $event => $unused)$result[self::key($event)] = ['is_need_closure_content' => 0,
            'content' => str_starts_with($event, 'account_') ? '您的{account_name}{change_direction}{change_amount}元，变动后为{account_balance}元，请登录核对。' : match ($event) {
                'cooperation_acceptance_reminder' => '线索{task_no}还有{remaining_minutes}分钟到接单提醒时间，请登录查看。',
                'cooperation_response_overdue' => '线索{task_no}的门店尚未接单，您可继续等待或重新选择门店。',
                default => '线索{task_no}已由{store_name}接单，请登录查看进度。'
            }];
        return $result;
    }
    /** Decimal validation without binary floating point, scientific notation or silent truncation. */
    public static function decimal($value): string
    {
        if ((!is_string($value) && !is_int($value) && !is_float($value)) || !preg_match('/^(-?)(\d{1,8})(?:\.(\d{1,2}))?$/D', (string) $value, $parts))
            throw new \InvalidArgumentException('资金流水金额格式不正确');
        $whole = ltrim($parts[2], '0');
        $whole = $whole === '' ? '0' : $whole;
        $fraction = str_pad($parts[3] ?? '', 2, '0');
        return (($parts[1] ?? '') === '-' && ($whole !== '0' || $fraction !== '00') ? '-' : '').$whole.'.'.$fraction;
    }
}
