<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('erp', function () {
    Route::get('config', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@info');
    Route::get('counterparty/options', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@options');
    Route::get('counterparty/member_options', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@memberOptions');
    Route::post('counterparty/quick_contact', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@quickContact');
    Route::post('counterparty/resolve_contact', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@resolveContact');
    Route::get('staff/options', 'addon\hsx_erp\app\adminapi\controller\ErpStaff@options');

    Route::get('purchase/lists', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@lists');
    Route::get('purchase/:id', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@info');
    Route::post('purchase/create', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@create');
    Route::post('purchase/item/:item_id/adjust_cost', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@adjustCost');

    Route::get('warehouse/lists', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@lists');
    Route::get('warehouse/options', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@options');
    Route::post('warehouse/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@save');
    Route::post('warehouse/:warehouse_id/location/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@saveLocation');
    Route::delete('warehouse/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@delete');
    Route::delete('warehouse/location/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@deleteLocation');

    Route::get('sale/lists', 'addon\hsx_erp\app\adminapi\controller\ErpSale@lists');
    Route::get('sale/stock', 'addon\hsx_erp\app\adminapi\controller\ErpSale@stock');
    Route::get('sale/:id', 'addon\hsx_erp\app\adminapi\controller\ErpSale@info');
    Route::post('sale/create', 'addon\hsx_erp\app\adminapi\controller\ErpSale@create');

    Route::get('finance/payable/lists', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@payableLists');
    Route::get('finance/payable/party/:party_id/items', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@payablePartyItems');
    Route::get('finance/receivable/lists', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@receivableLists');
    Route::post('finance/payable/:id/confirm_payment', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@confirmPayment');
    Route::post('finance/payable/party/:party_id/confirm_payment', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@confirmPartyPayment');
    Route::post('finance/payable/party/:party_id/confirm_items_payment', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@confirmPayableItemsPayment');
    Route::post('finance/receivable/:id/confirm_receipt', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@confirmReceipt');
    Route::post('finance/offset', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@offset');
    Route::get('finance/account_ledger', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@accountLedger');
    Route::get('finance/money_ledger', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@moneyLedger');

    Route::get('capital_account/lists', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@lists');
    Route::post('capital_account/save/:id', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@save');
    Route::delete('capital_account/:id', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@delete');
    Route::post('capital_account/entry', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@entry');
    Route::get('capital_account/ledger', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@ledger');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
])->mergeRuleRegex(false);
