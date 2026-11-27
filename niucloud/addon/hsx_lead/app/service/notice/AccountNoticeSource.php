<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;
use addon\hsx_lead\app\service\{NoticePreflightException,NoticePortalEntry};
use addon\hsx_lead\app\support\Data;
use think\facade\Db;
final class AccountNoticeSource
{
    public static function verify(array $task): array
    {
        $snapshot = Data::decode($task['payload_json'] ?? '');
        $row = Db::name('member_account_log')->where('site_id', $task['site_id'])->where('id', $task['source_id'])->find();
        if (!$row || $task['source_type'] !== 'member_account_log' || (int) $row['member_id'] !== (int) $task['recipient_member_id'] || NoticeEventCatalog::fromAccount($row['account_type']) !== $task['event_key'])throw new NoticePreflightException('原资金流水与接收会员不一致，未发送');
        foreach (['account_type', 'account_data', 'account_sum'] as $field) {
            $actual = $field === 'account_type' ? $row[$field] : NoticeEventCatalog::decimal($row[$field]);
            if (($snapshot[$field] ?? null) !== $actual)throw new NoticePreflightException('原资金流水已变化，未发送');
        }
        if ((int) ($snapshot['source_id'] ?? 0) !== (int) $row['id'] || (int) ($snapshot['site_id'] ?? 0) !== (int) $row['site_id'] || (int) ($snapshot['member_id'] ?? 0) !== (int) $row['member_id'] || (int) ($snapshot['occurred_at'] ?? 0) !== (int) $row['create_time'])throw new NoticePreflightException('通知流水快照不一致，未发送');
        return $row;
    }
    public static function variables(array $task, array $source): array
    {
        $amount = NoticeEventCatalog::decimal($source['account_data']);
        $entry = NoticePortalEntry::get((int) $task['site_id']);
        return ['account_name' => NoticeEventCatalog::EVENTS[$task['event_key']]['account_name'], 'change_direction' => str_starts_with($amount, '-') ? '减少' : '增加',
            'change_amount' => ltrim($amount, '-'), 'account_balance' => NoticeEventCatalog::decimal($source['account_sum']), 'occurred_at' => date('Y-m-d H:i:s', (int) $source['create_time']), 'record_no' => 'AL'.$source['id'],
            '__weapp_page' => 'addon/hsx_lead/pages/task/index?task=wallet', '__wechat_page' => $entry['h5_ready'] ? str_replace('pages/workbench/index', 'pages/task/index?task=wallet', $entry['h5_url']) : ''];
    }
}
