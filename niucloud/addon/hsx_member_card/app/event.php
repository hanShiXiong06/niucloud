<?php
declare(strict_types=1);

return [
    'listen' => [
        'HsxErpFinanceCategories' => [
            'addon\hsx_member_card\app\listener\MemberCardFinanceCategories',
        ],
        'HsxErpBusinessSourceOptions' => [
            'addon\hsx_member_card\app\listener\MemberCardBusinessSources',
        ],
        'ErpDomainEvent' => [
            'addon\hsx_member_card\app\listener\ErpDomainEventListener',
        ],
    ],
];
