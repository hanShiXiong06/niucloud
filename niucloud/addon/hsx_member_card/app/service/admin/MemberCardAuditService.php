<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\model\MemberCardOperationLog;
use core\base\BaseAdminService;

final class MemberCardAuditService extends BaseAdminService
{
    public function record(string $bizType, int $bizId, string $bizNo, string $action, array $before = [], array $after = []): int
    {
        $now = time();
        $row = MemberCardOperationLog::create([
            'site_id' => (int)$this->site_id,
            'biz_type' => mb_substr(trim($bizType), 0, 30),
            'biz_id' => $bizId,
            'biz_no' => mb_substr(trim($bizNo), 0, 40),
            'action' => mb_substr(trim($action), 0, 40),
            'before_json' => $this->encode($before),
            'after_json' => $this->encode($after),
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'ip' => mb_substr((string)request()->ip(), 0, 45),
            'occurred_at' => $now,
            'create_at' => $now,
        ]);
        return (int)$row->id;
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }
}
