<?php
return ['listen'=>[
    'ErpDomainEvent'=>['addon\\hsx_boyuan\\app\\listener\\DomainEvent'],
    'HsxErpPurchaseActions'=>['addon\\hsx_boyuan\\app\\listener\\PurchaseActions'],
    'HsxErpCostMaintenanceHint'=>['addon\\hsx_boyuan\\app\\listener\\CostMaintenanceHint'],
    'HsxErpSaleActions'=>['addon\\hsx_boyuan\\app\\listener\\PurchaseActions'],
    'HsxErpFinanceDocumentLinks'=>['addon\\hsx_boyuan\\app\\listener\\FinanceDocumentLinks'],
    'HsxErpWarehouseMasterPolicy'=>['addon\\hsx_boyuan\\app\\listener\\WarehouseMasterPolicy'],
    'HsxErpCatalogMasterPolicy'=>['addon\\hsx_boyuan\\app\\listener\\CatalogMasterPolicy'],
    'HsxBusinessTaskValidate'=>['addon\\hsx_boyuan\\app\\listener\\TaskNoticeValidate'],
]];
