<?php
return [
     // ── ERP 管理 ────────────────────────────────────────────────────────────
    [
        'name' => '经营看板',
        'key' => 'hsx_erp_dashboard',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_workbench',
        'sort' => 9,
        'page' => '/addon/hsx_erp/pages/dashboard/index',
        'icon' => '/addon/hsx_erp/site-app/2_07.png'
    ],
    [
        'name' => '采购管理',
        'key' => 'hsx_erp_purchase',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_purchase',
        'sort' => 10,
        'page' => '/addon/hsx_erp/pages/purchase/list',
        'icon' => '/addon/hsx_erp/site-app/icon_01.png'
    ],
    [
        'name' => '销售出库',
        'key' => 'hsx_erp_sale',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_sale',
        'sort' => 11,
        'page' => '/addon/hsx_erp/pages/sale/list',
        'icon' => '/addon/hsx_erp/site-app/icon_03.png'
    ],
    [
        'name' => '应付款',
        'key' => 'hsx_erp_payable',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_payable',
        'sort' => 12,
        'page' => '/addon/hsx_erp/pages/payable/list',
        'icon' => '/addon/hsx_erp/site-app/icon_05.png'
    ],
    [
        'name' => '应收款',
        'key' => 'hsx_erp_receivable',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_receivable',
        'sort' => 17,
        'page' => '/addon/hsx_erp/pages/receivable/list',
        'icon' => '/addon/hsx_erp/site-app/icon_06.png'
    ],
    [
        'name' => '库存设备',
        'key' => 'hsx_erp_stock',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_stock',
        'sort' => 14,
        'page' => '/addon/hsx_erp/pages/stock/list',
        'icon' => '/addon/hsx_erp/site-app/icon_07.png'
    ],


    [
        'name' => '采购退货',
        'key' => 'hsx_erp_purchase_return',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_purchase_return',
        'sort' => 15,
        'page' => '/addon/hsx_erp/pages/purchase_return/list',
        'icon' => '/addon/hsx_erp/site-app/icon_02.png'
    ],
    [
        'name' => '销售退货',
        'key' => 'hsx_erp_sale_return',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_sale_return',
        'sort' => 16,
        'page' => '/addon/hsx_erp/pages/sale_return/list',
        'icon' => '/addon/hsx_erp/site-app/icon_04.png'
    ],
    [
        'name' => '经营收支',
        'key' => 'hsx_erp_operating_finance',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_operating_finance',
        'sort' => 13,
        'page' => '/addon/hsx_erp/pages/operating_finance/list',
        'icon' => '/addon/hsx_erp/site-app/icon_10.png'
    ],
    [
        'name' => '成本调整',
        'key' => 'hsx_erp_cost_adjust',
        'group' => 'hsx_erp',
        'menu_key' => '',
        'sort' => 17,
        'page' => '/addon/hsx_erp/pages/cost_adjust/list',
        'icon' => '/addon/hsx_erp/site-app/icon_08.png'
    ],
    // site-uniapp/src/addon/hsx_erp/pages/serial_trace/list.vue
     [
        'name' => '串号追踪',
        'key' => 'hsx_erp_serial_trace',
        'group' => 'hsx_erp',
        'menu_key' => '',
        'sort' => 18,
        'page' => '/addon/hsx_erp/pages/serial_trace/list',
        'icon' => '/addon/hsx_erp/site-app/icon_09.png'
    ],[
        'name' => '库存盘点',
        'key' => 'hsx_erp_stocktake',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_stocktake',
        'sort' => 19,
        'page' => '/addon/hsx_erp/pages/stocktake/list',
        'icon' => '/addon/hsx_erp/site-app/icon_11.png'
    ],
    [
        'name' => '移动打印台',
        'key' => 'hsx_erp_print',
        'group' => 'hsx_erp',
        'menu_key' => 'hsx_erp_print',
        'sort' => 20,
        'page' => '/addon/hsx_erp/pages/print/index',
        'icon' => '/addon/hsx_erp/site-app/icon_07.png'
    ],
];
