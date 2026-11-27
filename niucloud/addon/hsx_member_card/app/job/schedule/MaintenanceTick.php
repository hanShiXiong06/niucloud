<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\job\schedule;

use addon\hsx_member_card\app\model\MemberCard;
use addon\hsx_member_card\app\service\admin\MemberCardStaffFactService;
use core\base\BaseJob;

final class MaintenanceTick extends BaseJob
{
    public function doJob(array $params = []): void
    {
        $now = time();
        MemberCard::whereIn('status', ['active', 'pending'])
            ->where('valid_end_at', '>', 0)
            ->where('valid_end_at', '<', $now)
            ->update(['status' => 'expired', 'update_at' => $now]);
        (new MemberCardStaffFactService())->retryPending(100);
    }
}
