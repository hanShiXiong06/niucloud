<?php

namespace addon\phone_shop\app\service\core\member;

use app\service\core\notice\NoticeService;
use think\facade\Log;

/** 审核结果通知。通知失败不回滚已经完成的审核。 */
class ForwardApplicationNoticeService
{
    public const APPROVED = 'phone_shop_forward_application_approved';
    public const REJECTED = 'phone_shop_forward_application_rejected';

    public function send(int $siteId, int $applicationId, bool $approved): void
    {
        try {
            NoticeService::send($siteId, $approved ? self::APPROVED : self::REJECTED, [
                'application_id' => $applicationId,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[phone_shop] 同行转发审核结果通知失败', [
                'application_id' => $applicationId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
