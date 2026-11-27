<?php
return ['listen' => [
    'PhoneShopElectronicSheetProviders' => [
        'addon\\hsx_express\\app\\integration\\PhoneShopElectronicSheetProviders',
    ],
    'HsxExpressTaskOperationGuard' => ['addon\\hsx_express\\app\\integration\\PhoneShopTaskGuard'],
    'HsxExpressTransportRegistry' => ['addon\\hsx_express\\app\\listener\\RecycleTransportRegistry'],
    'HsxExpressProviderRegistry' => ['addon\\hsx_express\\app\\integration\\RecycleSfProviderRegistry'],
]];
