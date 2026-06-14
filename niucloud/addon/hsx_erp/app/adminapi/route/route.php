<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('erp', function () {
    Route::get('counterparty/lists', 'addon\hsx_erp\app\adminapi\controller\Counterparty@lists');
    Route::get('counterparty/options', 'addon\hsx_erp\app\adminapi\controller\Counterparty@options');
    Route::get('counterparty/member_options', 'addon\hsx_erp\app\adminapi\controller\Counterparty@memberOptions');
    Route::get('counterparty/:id/members', 'addon\hsx_erp\app\adminapi\controller\Counterparty@members');
    Route::post('counterparty/save/:id', 'addon\hsx_erp\app\adminapi\controller\Counterparty@save');
    Route::get('reconciliation/scan', 'addon\hsx_erp\app\adminapi\controller\Reconciliation@scan');
    Route::get('reconciliation/asset/:id', 'addon\hsx_erp\app\adminapi\controller\Reconciliation@asset');
    Route::get('warehouse/lists', 'addon\hsx_erp\app\adminapi\controller\Warehouse@lists');
    Route::get('warehouse/options', 'addon\hsx_erp\app\adminapi\controller\Warehouse@options');
    Route::post('warehouse/save/:id', 'addon\hsx_erp\app\adminapi\controller\Warehouse@save');
    Route::delete('warehouse/:id', 'addon\hsx_erp\app\adminapi\controller\Warehouse@delete');
    Route::post('warehouse/:warehouse_id/location/save/:id', 'addon\hsx_erp\app\adminapi\controller\Warehouse@saveLocation');
    Route::delete('warehouse/location/:id', 'addon\hsx_erp\app\adminapi\controller\Warehouse@deleteLocation');
    // 库位责任分配（人↔库位）
    Route::get('location_assign/tree', 'addon\hsx_erp\app\adminapi\controller\LocationAssign@tree');
    Route::get('location_assign/staff_options', 'addon\hsx_erp\app\adminapi\controller\LocationAssign@staffOptions');
    Route::get('location_assign/lists', 'addon\hsx_erp\app\adminapi\controller\LocationAssign@lists');
    Route::post('location_assign/location/:location_id/staff', 'addon\hsx_erp\app\adminapi\controller\LocationAssign@setLocationStaff');
    Route::post('location_assign/staff/:uid/locations', 'addon\hsx_erp\app\adminapi\controller\LocationAssign@setStaffLocations');
    Route::get('refurbishment/lists', 'addon\hsx_erp\app\adminapi\controller\Refurbishment@lists');
    Route::get('refurbishment/user_options', 'addon\hsx_erp\app\adminapi\controller\Refurbishment@userOptions');
    Route::post('refurbishment/create', 'addon\hsx_erp\app\adminapi\controller\Refurbishment@create');
    Route::post('refurbishment/asset/:asset_id/skip', 'addon\hsx_erp\app\adminapi\controller\Refurbishment@skip');
    Route::get('refurbishment/:id', 'addon\hsx_erp\app\adminapi\controller\Refurbishment@info');
    Route::post('refurbishment/:id/complete', 'addon\hsx_erp\app\adminapi\controller\Refurbishment@complete');
    Route::post('refurbishment/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\Refurbishment@cancel');
    Route::get('pricing/lists', 'addon\hsx_erp\app\adminapi\controller\Pricing@lists');
    Route::get('pricing/asset/:asset_id', 'addon\hsx_erp\app\adminapi\controller\Pricing@info');
    Route::post('pricing/asset/:asset_id/price', 'addon\hsx_erp\app\adminapi\controller\Pricing@price');
    Route::get('stock_order/lists', 'addon\hsx_erp\app\adminapi\controller\StockOrder@lists');
    Route::get('stock_order/:id', 'addon\hsx_erp\app\adminapi\controller\StockOrder@info');
    Route::post('stock_order/:id/confirm_items', 'addon\hsx_erp\app\adminapi\controller\StockOrder@confirmItems');
    Route::post('stock_order/:id/reject_items', 'addon\hsx_erp\app\adminapi\controller\StockOrder@rejectItems');
    Route::post('stock_order/:id/item/:item_id/resubmit', 'addon\hsx_erp\app\adminapi\controller\StockOrder@resubmitItem');
    Route::get('asset/lists', 'addon\hsx_erp\app\adminapi\controller\Asset@lists');
    Route::get('asset/integration_status', 'addon\hsx_erp\app\adminapi\controller\Asset@integrationStatus');
    Route::post('asset/manual_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@manualInbound');
    Route::get('asset/:id', 'addon\hsx_erp\app\adminapi\controller\Asset@info');
    Route::post('asset/batch_confirm_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@batchConfirmInbound');
    Route::post('asset/:id/confirm_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@confirmAssetInbound');
    Route::post('stock_order/:id/confirm_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@confirmInbound');
    // 财务中心: 往来对账 / 应付应收 / 结算·折账
    // 出库 / 调拨(同行出货)
    Route::get('outbound/lists', 'addon\hsx_erp\app\adminapi\controller\Outbound@lists');
    Route::get('outbound/:id', 'addon\hsx_erp\app\adminapi\controller\Outbound@info');
    Route::post('outbound/create', 'addon\hsx_erp\app\adminapi\controller\Outbound@create');
    Route::post('outbound/:id/fill_price', 'addon\hsx_erp\app\adminapi\controller\Outbound@fillPrice');
    Route::post('outbound/transfer', 'addon\hsx_erp\app\adminapi\controller\Outbound@transfer');
    // 资金账户 / 账目往来
    Route::get('capital_account/lists', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@lists');
    Route::post('capital_account/save/:id', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@save');
    Route::delete('capital_account/:id', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@delete');
    Route::post('capital_account/entry', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@entry');
    Route::get('capital_account/ledger', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@ledger');
    Route::get('finance/board', 'addon\hsx_erp\app\adminapi\controller\Finance@board');
    Route::get('finance/counterparty_balance', 'addon\hsx_erp\app\adminapi\controller\Finance@counterpartyBalance');
    Route::get('finance/payable/lists', 'addon\hsx_erp\app\adminapi\controller\Finance@payableLists');
    Route::get('finance/receivable/lists', 'addon\hsx_erp\app\adminapi\controller\Finance@receivableLists');
    Route::get('finance/payable/outstanding', 'addon\hsx_erp\app\adminapi\controller\Finance@payableOutstanding');
    Route::get('finance/receivable/outstanding', 'addon\hsx_erp\app\adminapi\controller\Finance@receivableOutstanding');
    Route::post('finance/settlement/preview', 'addon\hsx_erp\app\adminapi\controller\Finance@settlementPreview');
    Route::post('finance/settlement/settle', 'addon\hsx_erp\app\adminapi\controller\Finance@settlementSettle');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
]);
