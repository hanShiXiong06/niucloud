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
    Route::get('counterparty/:id/detail', 'addon\hsx_erp\app\adminapi\controller\Counterparty@detail');
    Route::post('counterparty/quick_contact', 'addon\hsx_erp\app\adminapi\controller\Counterparty@quickContact');
    Route::post('counterparty/resolve_contact', 'addon\hsx_erp\app\adminapi\controller\Counterparty@resolveContact');
    Route::post('counterparty/:id/member/add', 'addon\hsx_erp\app\adminapi\controller\Counterparty@addMember');
    Route::post('counterparty/:id/member/remove', 'addon\hsx_erp\app\adminapi\controller\Counterparty@removeMember');
    Route::post('counterparty/save/:id', 'addon\hsx_erp\app\adminapi\controller\Counterparty@save');
    Route::delete('counterparty/:id', 'addon\hsx_erp\app\adminapi\controller\Counterparty@delete');
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
    // 推荐角色「一键生成」(不入侵框架, 调框架 RoleService)
    Route::get('role_preset/preview', 'addon\hsx_erp\app\adminapi\controller\RolePreset@preview');
    Route::post('role_preset/generate', 'addon\hsx_erp\app\adminapi\controller\RolePreset@generate');
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
    Route::get('dashboard/data', 'addon\hsx_erp\app\adminapi\controller\Dashboard@data');
    Route::get('asset/lists', 'addon\hsx_erp\app\adminapi\controller\Asset@lists');
    Route::get('asset/overview', 'addon\hsx_erp\app\adminapi\controller\Asset@overview');
    Route::get('asset/integration_status', 'addon\hsx_erp\app\adminapi\controller\Asset@integrationStatus');
    Route::post('asset/manual_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@manualInbound');
    Route::post('asset/:id/adjust_cost', 'addon\hsx_erp\app\adminapi\controller\Asset@adjustCost');
    Route::post('asset/:id/complete_photo', 'addon\hsx_erp\app\adminapi\controller\Asset@completePhoto');
    Route::get('asset/:id', 'addon\hsx_erp\app\adminapi\controller\Asset@info');
    Route::post('asset/batch_confirm_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@batchConfirmInbound');
    Route::post('asset/:id/confirm_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@confirmAssetInbound');
    Route::post('stock_order/:id/confirm_inbound', 'addon\hsx_erp\app\adminapi\controller\Asset@confirmInbound');
    // 财务中心: 往来对账 / 应付应收 / 结算·折账
    // 出库 / 调拨(同行出货)
    Route::get('outbound/lists', 'addon\hsx_erp\app\adminapi\controller\Outbound@lists');
    Route::get('outbound/peer_sale_todo', 'addon\hsx_erp\app\adminapi\controller\Outbound@peerSaleTodo');
    Route::get('outbound/:id', 'addon\hsx_erp\app\adminapi\controller\Outbound@info');
    Route::post('outbound/create', 'addon\hsx_erp\app\adminapi\controller\Outbound@create');
    Route::post('outbound/:id/fill_price', 'addon\hsx_erp\app\adminapi\controller\Outbound@fillPrice');
    Route::post('outbound/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\Outbound@cancel');
    Route::post('outbound/transfer', 'addon\hsx_erp\app\adminapi\controller\Outbound@transfer');
    // 库存盘点
    Route::get('stocktake/lists', 'addon\hsx_erp\app\adminapi\controller\Stocktake@lists');
    Route::get('stocktake/:id', 'addon\hsx_erp\app\adminapi\controller\Stocktake@info');
    Route::post('stocktake/create', 'addon\hsx_erp\app\adminapi\controller\Stocktake@create');
    Route::post('stocktake/:id/scan', 'addon\hsx_erp\app\adminapi\controller\Stocktake@scan');
    Route::post('stocktake/:id/finish', 'addon\hsx_erp\app\adminapi\controller\Stocktake@finish');
    Route::post('stocktake/item/:itemId/restore', 'addon\hsx_erp\app\adminapi\controller\Stocktake@restore');
    // 资金账户 / 账目往来
    Route::get('capital_account/lists', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@lists');
    Route::post('capital_account/save/:id', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@save');
    Route::delete('capital_account/:id', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@delete');
    Route::post('capital_account/entry', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@entry');
    Route::get('capital_account/ledger', 'addon\hsx_erp\app\adminapi\controller\CapitalAccount@ledger');
    Route::get('finance/board', 'addon\hsx_erp\app\adminapi\controller\Finance@board');
    Route::get('finance/reconciliation', 'addon\hsx_erp\app\adminapi\controller\Finance@reconciliation');
    Route::get('finance/counterparty_balance', 'addon\hsx_erp\app\adminapi\controller\Finance@counterpartyBalance');
    Route::get('finance/payable/lists', 'addon\hsx_erp\app\adminapi\controller\Finance@payableLists');
    Route::get('finance/receivable/lists', 'addon\hsx_erp\app\adminapi\controller\Finance@receivableLists');
    Route::get('finance/payable/outstanding', 'addon\hsx_erp\app\adminapi\controller\Finance@payableOutstanding');
    Route::get('finance/receivable/outstanding', 'addon\hsx_erp\app\adminapi\controller\Finance@receivableOutstanding');
    Route::post('finance/settlement/preview', 'addon\hsx_erp\app\adminapi\controller\Finance@settlementPreview');
    Route::get('finance/group_outstanding', 'addon\hsx_erp\app\adminapi\controller\Finance@groupOutstanding');
    Route::post('finance/settlement/settle', 'addon\hsx_erp\app\adminapi\controller\Finance@settlementSettle');
    Route::get('finance/settlement/lists', 'addon\hsx_erp\app\adminapi\controller\Finance@settlementLists');
    Route::get('finance/settlement/detail/:id', 'addon\hsx_erp\app\adminapi\controller\Finance@settlementDetail');
    Route::get('finance/summary', 'addon\hsx_erp\app\adminapi\controller\Finance@summary');
    // 设备全链路追溯
    Route::get('device_trace/search', 'addon\hsx_erp\app\adminapi\controller\DeviceTrace@search');
    Route::get('device_trace/detail', 'addon\hsx_erp\app\adminapi\controller\DeviceTrace@detail');
    Route::post('finance/expense', 'addon\hsx_erp\app\adminapi\controller\Finance@recordExpense');
    Route::get('finance/prepay_balance', 'addon\hsx_erp\app\adminapi\controller\Finance@prepayBalance');
    Route::post('finance/prepay', 'addon\hsx_erp\app\adminapi\controller\Finance@prepay');
    // AI 助手
    Route::get('ai/config', 'addon\hsx_erp\app\adminapi\controller\Ai@config');
    Route::post('ai/config', 'addon\hsx_erp\app\adminapi\controller\Ai@saveConfig');
    Route::get('ai/models', 'addon\hsx_erp\app\adminapi\controller\Ai@models');
    Route::post('ai/ping', 'addon\hsx_erp\app\adminapi\controller\Ai@ping');
    Route::post('ai/chat', 'addon\hsx_erp\app\adminapi\controller\Ai@chat');
    Route::get('ai/scenes', 'addon\hsx_erp\app\adminapi\controller\Ai@scenes');
    Route::post('ai/run', 'addon\hsx_erp\app\adminapi\controller\Ai@run');
    Route::post('ai/stream', 'addon\hsx_erp\app\adminapi\controller\Ai@stream');
    Route::get('ai/conversations', 'addon\hsx_erp\app\adminapi\controller\Ai@conversations');
    Route::get('ai/conversation/:id', 'addon\hsx_erp\app\adminapi\controller\Ai@conversationDetail');
    Route::post('ai/conversation/save', 'addon\hsx_erp\app\adminapi\controller\Ai@conversationSave');
    Route::delete('ai/conversation/:id', 'addon\hsx_erp\app\adminapi\controller\Ai@conversationDelete');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
]);
