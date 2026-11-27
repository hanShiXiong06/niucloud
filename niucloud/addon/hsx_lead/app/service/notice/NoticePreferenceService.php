<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;

use core\exception\CommonException;
use think\facade\Db;

final class NoticePreferenceService
{
    private static function member(int $site, int $member): void
    {
        if ($site <= 0 || $member <= 0 || !Db::name('member')->where('site_id', $site)->where('member_id', $member)->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->count())throw new CommonException('请先登录本站有效会员');
    }
    public static function enabled(int $site, int $member, string $event, string $channel): bool
    {
        $row = Db::name('hsx_lead_notice_preference')->where('site_id', $site)->where('member_id', $member)->where('event_key', $event)->where('channel', $channel)->find();
        // Site activation and WeChat authorization are still mandatory. SMS requires explicit opt-in.
        return $row ? (bool) $row['enabled'] : $channel !== 'sms';
    }
    public function get(int $site, int $member): array
    {
        self::member($site, $member);
        $policy = (new AccountNoticePolicyService())->get($site);
        $events = [];
        foreach ($policy['events'] as $event) {
            $channels = [];
            foreach (NoticeEventCatalog::CHANNELS as $channel)$channels[] = ['channel' => $channel, 'enabled' => self::enabled($site, $member, $event['event_key'], $channel),
                'configured' => $policy['enabled'] && $event['enabled'] && !empty($event['channels'][$channel]) && $event['readiness'][$channel]['status'] === 'ready', 'authorization_required' => $channel === 'weapp',
                'template_id' => $channel === 'weapp' && $event['readiness'][$channel]['status'] === 'ready' ? (string) $event['readiness'][$channel]['framework_template_id'] : ''];
            $events[] = ['event_key' => $event['event_key'], 'name' => $event['name'], 'channels' => $channels];
        }
        return ['events' => $events, 'message' => '开启提醒偏好不代表已授权订阅；请在小程序中主动确认微信订阅，拒绝或额度用尽后需重新授权。'];
    }
    public function save(int $site, int $member, array $input): array
    {
        self::member($site, $member);
        $rows = $input['preferences'] ?? null;
        if (!is_array($rows) || count($rows) > count(NoticeEventCatalog::EVENTS) * 3)throw new CommonException('通知偏好格式不正确');
        Db::transaction(function () use ($site, $member, $rows) {
            Db::name('member')->where('site_id', $site)->where('member_id', $member)->lock(true)->find();
            $seen = [];
            foreach ($rows as $row) {
                $event = $row['event_key'] ?? '';
                $channel = $row['channel'] ?? '';
                if (!isset(NoticeEventCatalog::EVENTS[$event]) || !in_array($channel, NoticeEventCatalog::CHANNELS, true) || !is_bool($row['enabled'] ?? null) || isset($seen[$event.'|'.$channel]))throw new CommonException('通知偏好事件或渠道不存在、重复');
                $seen[$event.'|'.$channel] = true;
                $query = Db::name('hsx_lead_notice_preference')->where('site_id', $site)->where('member_id', $member)->where('event_key', $event)->where('channel', $channel);
                if ($query->count())$query->update(['enabled' => $row['enabled'] ? 1 : 0, 'update_time' => time()]);
                else Db::name('hsx_lead_notice_preference')->insert(['site_id' => $site, 'member_id' => $member, 'event_key' => $event, 'channel' => $channel, 'enabled' => $row['enabled'] ? 1 : 0, 'create_time' => time(), 'update_time' => time()]);
                // A preference write is not proof of a new authorization quota. Only an explicit accepted native result may wake 43101 tasks.
            }
        });
        return $this->get($site, $member);
    }
    public function authorizationReported(int $site, int $member, array $events): array
    {
        self::member($site, $member);
        $keys = array_values(array_filter($events, static fn ($e) => is_string($e) && isset(NoticeEventCatalog::EVENTS[$e])));
        if (!$keys || count($keys) > 3)throw new CommonException('订阅场景不正确');
        // This client signal only permits a safe attempt following an explicit previous rejection. The provider remains authoritative.
        $count = Db::name('hsx_lead_notice_task')->where('site_id', $site)->where('recipient_member_id', $member)->whereIn('event_key', $keys)->where('channel', 'weapp')->where('status', 'pending_authorization')->where('expires_at', '>', time())->update(['status' => 'pending', 'next_retry_at' => time(), 'provider_code' => '', 'last_message' => '用户已重新确认订阅，等待服务商核验', 'update_time' => time()]);
        return ['resumed' => $count];
    }
}
