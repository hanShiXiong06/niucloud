<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\marketing;

use addon\phone_shop\app\service\core\member\ForwardBenefitService;

final class MarketingQualificationOptions
{
    public function handle(array $payload): array
    {
        return [[
            'key' => ForwardBenefitService::KEY,
            'name' => '商城同行身份',
            'description' => '未满足时复用同行转发申请：万能表单提交、企微审核、用户结果通知。',
            'source_plugin' => 'phone_shop',
        ]];
    }
}
