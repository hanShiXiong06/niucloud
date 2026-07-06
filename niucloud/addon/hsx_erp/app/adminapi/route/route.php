<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('erp', function () {
    Route::get('dicts', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@dicts');
    Route::get('config', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@info');
    Route::post('config', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@save');
    Route::get('config/sale_channels', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@saleChannels');
    Route::post('config/sale_channels', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@saveSaleChannels');
    Route::get('counterparty/options', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@options');
    Route::get('counterparty/member_options', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@memberOptions');
    Route::post('counterparty/quick_contact', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@quickContact');
    Route::post('counterparty/resolve_contact', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@resolveContact');
    Route::get('staff/options', 'addon\hsx_erp\app\adminapi\controller\ErpStaff@options');

    Route::get('purchase/lists', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@lists');
    Route::post('purchase/create', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@create');
    Route::post('purchase/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@cancel');
    Route::get('purchase/:id', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@info');
    Route::post('purchase/item/:item_id/adjust_cost', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@adjustCost');

    Route::get('stock/lists', 'addon\hsx_erp\app\adminapi\controller\ErpStock@lists');
    Route::get('stock/ledger', 'addon\hsx_erp\app\adminapi\controller\ErpStock@ledger');
    Route::post('stock/:id/adjust_cost', 'addon\hsx_erp\app\adminapi\controller\ErpStock@adjustCost');
    Route::get('stock/:id', 'addon\hsx_erp\app\adminapi\controller\ErpStock@info');
    Route::post('stock/:id/flow', 'addon\hsx_erp\app\adminapi\controller\ErpStock@flow');

    Route::get('warehouse/lists', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@lists');
    Route::get('warehouse/options', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@options');
    Route::post('warehouse/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@save');
    Route::post('warehouse/:warehouse_id/location/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@saveLocation');
    Route::delete('warehouse/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@delete');
    Route::delete('warehouse/location/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@deleteLocation');

    Route::get('sale/lists', 'addon\hsx_erp\app\adminapi\controller\ErpSale@lists');
    Route::get('sale/stock', 'addon\hsx_erp\app\adminapi\controller\ErpSale@stock');
    Route::post('sale/create', 'addon\hsx_erp\app\adminapi\controller\ErpSale@create');
    Route::post('sale/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\ErpSale@cancel');
    Route::get('sale/:id', 'addon\hsx_erp\app\adminapi\controller\ErpSale@info');

    Route::get('finance/payable/lists', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@payableLists');
    Route::get('finance/payable/party/:party_id/items', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@payablePartyItems');
    Route::get('finance/receivable/lists', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@receivableLists');
    Route::get('finance/receivable/:id/items', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@receivableItems');
    Route::post('finance/payable/:id/confirm_payment', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@confirmPayment');
    Route::post('finance/payable/party/:party_id/confirm_payment', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@confirmPartyPayment');
    Route::post('finance/payable/party/:party_id/confirm_items_payment', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@confirmPayableItemsPayment');
    Route::post('finance/receivable/:id/confirm_receipt', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@confirmReceipt');
    Route::post('finance/offset', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@offset');
    Route::get('finance/account_ledger', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@accountLedger');
    Route::get('finance/money_ledger', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@moneyLedger');
    Route::get('finance/settlement_lists', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@settlementLists');

    Route::get('capital_account/lists', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@lists');
    Route::post('capital_account/save/:id', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@save');
    Route::delete('capital_account/:id', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@delete');
    Route::post('capital_account/entry', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@entry');
    Route::get('capital_account/ledger', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@ledger');

    // ── 采购退货 ──────────────────────────────────────────────────────────────
    Route::get('purchase/return/lists', 'addon\hsx_erp\app\adminapi\controller\ErpPurchaseReturn@lists');
    Route::get('purchase/return/:id', 'addon\hsx_erp\app\adminapi\controller\ErpPurchaseReturn@info');
    Route::post('purchase/return/create', 'addon\hsx_erp\app\adminapi\controller\ErpPurchaseReturn@create');
    Route::post('purchase/return/:id/confirm', 'addon\hsx_erp\app\adminapi\controller\ErpPurchaseReturn@confirm');
    Route::post('purchase/return/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\ErpPurchaseReturn@cancel');

    // ── 销售退货 ──────────────────────────────────────────────────────────────
    Route::get('sale/return/lists', 'addon\hsx_erp\app\adminapi\controller\ErpSaleReturn@lists');
    Route::get('sale/return/:id', 'addon\hsx_erp\app\adminapi\controller\ErpSaleReturn@info');
    Route::post('sale/return/create', 'addon\hsx_erp\app\adminapi\controller\ErpSaleReturn@create');
    Route::post('sale/return/:id/confirm', 'addon\hsx_erp\app\adminapi\controller\ErpSaleReturn@confirm');
    Route::post('sale/return/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\ErpSaleReturn@cancel');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
])->mergeRuleRegex(false);
