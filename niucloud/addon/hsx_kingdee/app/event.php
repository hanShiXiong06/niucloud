<?php
return [
    'listen' => [
        'ErpDomainEvent' => ['addon\\hsx_kingdee\\app\\listener\\LedgerRecorded'],
        'HsxBusinessTaskValidate' => ['addon\\hsx_kingdee\\app\\listener\\TaskValidate'],
    ],
];
