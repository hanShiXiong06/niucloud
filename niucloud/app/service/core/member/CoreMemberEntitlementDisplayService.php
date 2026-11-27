<?php
// +----------------------------------------------------------------------
// | Niucloud-admin: optional, read-only member entitlement presentation
// +----------------------------------------------------------------------
namespace app\service\core\member;

use think\facade\Log;

/**
 * Plugin display data only. This service never grants rights or changes a
 * member's personal level. List/detail share the same batched extension.
 */
class CoreMemberEntitlementDisplayService
{
    public const EVENT = 'MemberAdminEntitlementDisplay';
    private const STATUSES = ['active', 'expired', 'not_opened', 'review', 'inactive', 'not_joined', 'unavailable'];

    public function forMembers(int $site_id, array $member_ids): array
    {
        $ids = [];
        foreach ($member_ids as $value) {
            if ((is_int($value) || (is_string($value) && ctype_digit($value))) && (int)$value > 0) $ids[(int)$value] = (int)$value;
        }
        $ids = array_values($ids);
        $sources = [];
        $members = array_fill_keys($ids, []);
        if ($site_id <= 0 || !$ids) return ['sources' => [], 'members' => $members];

        foreach (array_chunk($ids, 500) as $chunk) {
            try {
                $results = event(self::EVENT, ['site_id' => $site_id, 'member_ids' => $chunk]);
            } catch (\Throwable $error) {
                // Do not leak provider credentials or raw database errors into
                // member APIs. A failed extension must not look like no rights.
                Log::warning('Member entitlement display extension failed', ['site_id' => $site_id, 'error_type' => get_class($error)]);
                $results = [['key' => 'extension_unavailable', 'title' => '扩展权益', 'members' => []]];
            }
            $seen = [];
            foreach (is_array($results) ? $results : [] as $result) {
                if (!is_array($result) || !$result) continue;
                $key = $result['key'] ?? '';
                $title = $this->text($result['title'] ?? '', 60);
                if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $key) || $title === '') continue;
                if (isset($seen[$key])) {
                    // Two extensions may not silently overwrite the same card.
                    foreach ($chunk as $id) $members[$id][$key] = $this->unavailable($key, $sources[$key]['title']);
                    continue;
                }
                $seen[$key] = true;
                if (!isset($sources[$key])) $sources[$key] = ['key' => $key, 'title' => $title];
                foreach ($chunk as $id) {
                    $card = is_array($result['members'] ?? null) ? ($result['members'][$id] ?? null) : null;
                    $members[$id][$key] = $this->card($key, $sources[$key]['title'], $card);
                }
            }
        }
        // A source omitted on one batch is unavailable, not implicitly absent.
        foreach ($members as &$cards) {
            foreach ($sources as $key => $source) if (!isset($cards[$key])) $cards[$key] = $this->unavailable($key, $source['title']);
            $cards = array_values($cards);
        }
        unset($cards);
        return ['sources' => array_values($sources), 'members' => $members];
    }

    private function card(string $key, string $title, $value): array
    {
        if (!is_array($value) || !in_array($value['status'] ?? null, self::STATUSES, true)) return $this->unavailable($key, $title);
        $label = $this->text($value['label'] ?? '', 120);
        $status_text = $this->text($value['status_text'] ?? '', 60);
        $expires_at = $value['expires_at'] ?? null;
        if ($label === '' || $status_text === '' || !is_int($expires_at) || $expires_at < 0 || $expires_at > 253402300799) return $this->unavailable($key, $title);
        return ['key' => $key, 'title' => $title, 'label' => $label, 'status' => $value['status'],
            'status_text' => $status_text, 'context' => $this->text($value['context'] ?? '', 200),
            'expires_at' => $expires_at, 'reason' => $this->text($value['reason'] ?? '', 300)];
    }

    private function unavailable(string $key, string $title): array
    {
        return ['key' => $key, 'title' => $title, 'label' => '权益读取失败', 'status' => 'unavailable',
            'status_text' => '读取失败', 'context' => '', 'expires_at' => 0, 'reason' => '请刷新后重试；仍失败请联系管理员核对'];
    }

    private function text($value, int $limit): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) return '';
        return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', strip_tags($value)) ?? ''), 0, $limit, 'UTF-8');
    }
}
