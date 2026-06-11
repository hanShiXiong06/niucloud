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
    Route::post('asset/manual_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@manualInbound');
    Route::get('asset/:id', 'addon\hsx_erp\app\adminapi\controller\Asset@info');
    Route::post('asset/batch_confirm_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@batchConfirmInbound');
    Route::post('asset/:id/confirm_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@confirmAssetInbound');
    Route::post('stock_order/:id/confirm_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@confirmInbound');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
]);
