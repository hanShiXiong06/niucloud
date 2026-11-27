<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\member;

use addon\hsx_lead\app\service\notice\FinancialNoticeService;
use think\facade\Log;

final class AccountNoticeListener
{
    public function handle(array $event): void
    {
        try {
            (new FinancialNoticeService())->recordAccountEvent($event);
        } catch (\Throwable $error) {
            // Notifications must never break a money transaction. A post-commit scan repairs lost intent.
            try {
                Log::warning('hsx_lead资金通知意图暂未记录', [
                        'site_id' => (int) ($event['site_id'] ?? 0),
                        'account_log_id' => (int) ($event['account_log_id'] ?? 0),
                        'error_type' => get_class($error)
                    ]);
            } catch (\Throwable $ignored) {
            }
        }
    }
}
