<?php

namespace addon\phone_shop\app\listener\member;

use addon\phone_shop\app\model\member\ForwardApplication;

/** 企业微信真正发出待办前，确认申请仍处于待审核状态。 */
class ForwardApplicationTaskValidate
{
    public function handle(array $event = []): array
    {
        if ((string)($event['source_plugin'] ?? '') !== 'phone_shop'
            || (string)($event['source_type'] ?? '') !== 'forward_application') {
            return ['valid' => true];
        }

        $application = ForwardApplication::where([
            ['site_id', '=', (int)($event['site_id'] ?? 0)],
            ['application_id', '=', (int)($event['source_id'] ?? 0)],
        ])->field('status')->findOrEmpty();

        return $application->isEmpty() || (string)$application->status !== 'pending'
            ? ['valid' => false, 'reason' => '同行身份申请已处理，无需继续提醒']
            : ['valid' => true];
    }
}
