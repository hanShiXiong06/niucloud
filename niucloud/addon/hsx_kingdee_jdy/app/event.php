<?php
return ['listen' => [
    'ErpDomainEvent' => ['addon\\hsx_kingdee_jdy\\app\\listener\\LedgerRecorded'],
    'HsxBusinessTaskValidate' => ['addon\\hsx_kingdee_jdy\\app\\listener\\TaskValidate'],
]];
