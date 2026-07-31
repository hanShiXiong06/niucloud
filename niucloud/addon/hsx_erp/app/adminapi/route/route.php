<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('erp', function () {
    Route::get('dashboard', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@dashboard');
    Route::get('goods/meta', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsMeta@meta');
    Route::get('goods/catalog/lists', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@lists');
    Route::get('goods/catalog/summary', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@summary');
    Route::get('goods/catalog/hierarchy', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@hierarchy');
    Route::post('goods/catalog/product/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@saveProduct');
    Route::delete('goods/catalog/product/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@deleteProduct');
    Route::post('goods/catalog/node/sort', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@sortNode');
    Route::get('goods/catalog/export', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@export');
    Route::post('goods/catalog/import/upload', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@importUpload');
    Route::get('goods/catalog/import/tasks', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@importTasks');
    Route::get('goods/catalog/import/tasks/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@importTaskInfo');
    Route::post('goods/catalog/import/tasks/:id/retry', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@importTaskRetry');
    Route::delete('goods/catalog/import/tasks/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsCatalog@importTaskDelete');
    Route::get('goods/spec/meta', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsSpec@meta');
    Route::post('goods/spec/group/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsSpec@saveGroup');
    Route::delete('goods/spec/group/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsSpec@deleteGroup');
    Route::post('goods/spec/item/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsSpec@saveItem');
    Route::delete('goods/spec/item/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsSpec@deleteItem');
    Route::post('goods/grade/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsSpec@saveGrade');
    Route::delete('goods/grade/:id', 'addon\hsx_erp\app\adminapi\controller\ErpGoodsSpec@deleteGrade');
    Route::get('dicts', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@dicts');
    Route::get('config', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@info');
    Route::post('config', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@save');
    Route::get('config/task_assignment', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@taskAssignmentSettings');
    Route::post('config/task_assignment', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@saveTaskAssignmentSettings');
    Route::post('config/refurbish_reminder/dismiss', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@dismissRefurbishReminder');
    Route::post('config/turnover_reminder/dismiss', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@dismissTurnoverReminder');
    Route::get('config/sale_channels', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@saleChannels');
    Route::post('config/sale_channels', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@saveSaleChannels');
    Route::get('config/sale_channel_options', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@saleChannelOptions');
    Route::post('config/sale_channel_options', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@saveSaleChannelOptions');
    Route::get('config/finance_categories', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@financeCategories');
    Route::post('config/finance_categories', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@saveFinanceCategories');
    Route::get('config/business_source_options', 'addon\hsx_erp\app\adminapi\controller\ErpConfig@businessSourceOptions');
    Route::get('print/meta', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@meta');
    Route::get('print/printers', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@printers');
    Route::post('print/printer/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@savePrinter');
    Route::delete('print/printer/:id', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@deletePrinter');
    Route::post('print/printer/:id/test', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@testPrinter');
    Route::get('print/templates', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@templates');
    Route::post('print/template/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@saveTemplate');
    Route::get('print/scenes', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@scenes');
    Route::post('print/scene/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@saveScene');
    Route::get('print/jobs', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@jobs');
    Route::post('print/manual', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@manual');
    Route::post('print/job/:id/retry', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@retry');
    Route::post('print/job/:id/client_complete', 'addon\hsx_erp\app\adminapi\controller\ErpPrint@clientComplete');
    Route::get('counterparty/options', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@options');
    Route::get('counterparty/member_options', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@memberOptions');
    Route::post('counterparty/quick_contact', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@quickContact');
    Route::post('counterparty/quick_party', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@quickParty');
    Route::post('counterparty/update/<id>', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@updateParty');
    Route::get('counterparty/credit/<id>', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@credit');
    Route::post('counterparty/credit/<id>', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@updateCredit');
    Route::post('counterparty/resolve_contact', 'addon\hsx_erp\app\adminapi\controller\ErpCounterparty@resolveContact');
    Route::get('staff/options', 'addon\hsx_erp\app\adminapi\controller\ErpStaff@options');
    Route::get('kpi/dashboard', 'addon\hsx_erp\app\adminapi\controller\ErpKpi@dashboard');
    Route::get('kpi/rules', 'addon\hsx_erp\app\adminapi\controller\ErpKpi@rules');
    Route::post('kpi/rules', 'addon\hsx_erp\app\adminapi\controller\ErpKpi@saveRules');

    Route::get('purchase/lists', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@lists');
    Route::post('purchase/create', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@create');
    Route::post('purchase/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@cancel');
    Route::get('purchase/:id', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@info');
    Route::post('purchase/item/:item_id/adjust_cost', 'addon\hsx_erp\app\adminapi\controller\ErpPurchase@adjustCost');

    Route::get('stock/lists', 'addon\hsx_erp\app\adminapi\controller\ErpStock@lists');
    Route::get('stock/quantity_products', 'addon\hsx_erp\app\adminapi\controller\ErpStock@quantityProducts');
    Route::post('stock/quantity_product', 'addon\hsx_erp\app\adminapi\controller\ErpStock@createQuantityProduct');
    Route::post('stock/quantity_product/:id/category', 'addon\hsx_erp\app\adminapi\controller\ErpStock@updateQuantityProductCategory');
    Route::get('stock/turnover_summary', 'addon\hsx_erp\app\adminapi\controller\ErpStock@turnoverSummary');
    Route::get('stock/listing_workload', 'addon\hsx_erp\app\adminapi\controller\ErpStock@listingWorkload');
    Route::post('stock/transfer/preview', 'addon\hsx_erp\app\adminapi\controller\ErpStock@transferPreview');
    Route::post('stock/transfer', 'addon\hsx_erp\app\adminapi\controller\ErpStock@transfer');
    Route::post('stock/consignment/buyout', 'addon\hsx_erp\app\adminapi\controller\ErpStock@buyoutConsignment');
    Route::get('stock/serial_trace', 'addon\hsx_erp\app\adminapi\controller\ErpStock@serialTrace');
    Route::get('stock/serial_trace/:id', 'addon\hsx_erp\app\adminapi\controller\ErpStock@serialTraceDetail');
    Route::get('stock/ledger', 'addon\hsx_erp\app\adminapi\controller\ErpStock@ledger');
    Route::post('stock/refurbish/send', 'addon\hsx_erp\app\adminapi\controller\ErpStock@sendRefurbish');
    Route::post('stock/:id/adjust_cost', 'addon\hsx_erp\app\adminapi\controller\ErpStock@adjustCost');
    Route::post('stock/:id/retail_price', 'addon\hsx_erp\app\adminapi\controller\ErpStock@adjustRetailPrice');
    Route::post('stock/:id/refurbish/complete', 'addon\hsx_erp\app\adminapi\controller\ErpStock@completeRefurbish');
    Route::get('stock/:id', 'addon\hsx_erp\app\adminapi\controller\ErpStock@info');
    Route::post('stock/:id/flow', 'addon\hsx_erp\app\adminapi\controller\ErpStock@flow');
    Route::post('stock/:id/listing_media/prepare', 'addon\hsx_erp\app\adminapi\controller\ErpStock@prepareListingMedia');
    Route::post('stock/:id/sync_listing', 'addon\hsx_erp\app\adminapi\controller\ErpStock@syncListing');

    Route::get('stocktake/lists', 'addon\hsx_erp\app\adminapi\controller\ErpStocktake@lists');
    Route::post('stocktake/create', 'addon\hsx_erp\app\adminapi\controller\ErpStocktake@create');
    Route::get('stocktake/:id/items', 'addon\hsx_erp\app\adminapi\controller\ErpStocktake@items');
    Route::get('stocktake/:id', 'addon\hsx_erp\app\adminapi\controller\ErpStocktake@info');
    Route::post('stocktake/:id/scan', 'addon\hsx_erp\app\adminapi\controller\ErpStocktake@scan');
    Route::post('stocktake/:id/submit', 'addon\hsx_erp\app\adminapi\controller\ErpStocktake@submit');
    Route::post('stocktake/:id/items/:item_id/resolve', 'addon\hsx_erp\app\adminapi\controller\ErpStocktake@resolve');
    Route::post('stocktake/:id/complete', 'addon\hsx_erp\app\adminapi\controller\ErpStocktake@complete');
    Route::post('stocktake/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\ErpStocktake@cancel');

    Route::get('warehouse/lists', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@lists');
    Route::get('warehouse/options', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@options');
    Route::post('warehouse/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@save');
    Route::post('warehouse/:warehouse_id/location/save/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@saveLocation');
    Route::delete('warehouse/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@delete');
    Route::delete('warehouse/location/:id', 'addon\hsx_erp\app\adminapi\controller\ErpWarehouse@deleteLocation');

    Route::get('sale/lists', 'addon\hsx_erp\app\adminapi\controller\ErpSale@lists');
    Route::get('sale/stock', 'addon\hsx_erp\app\adminapi\controller\ErpSale@stock');
    Route::get('sale/profit_report', 'addon\hsx_erp\app\adminapi\controller\ErpSale@profitReport');
    Route::get('sale/profit_report/export', 'addon\hsx_erp\app\adminapi\controller\ErpSale@profitReportExport');
    Route::get('sale/profit_report/meta', 'addon\hsx_erp\app\adminapi\controller\ErpSale@profitReportMeta');
    Route::post('sale/profit_report/view', 'addon\hsx_erp\app\adminapi\controller\ErpSale@saveProfitReportView');
    Route::post('sale/create', 'addon\hsx_erp\app\adminapi\controller\ErpSale@create');
    Route::post('sale/item/:item_id/cancel', 'addon\hsx_erp\app\adminapi\controller\ErpSale@cancelItem');
    Route::post('sale/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\ErpSale@cancel');
    Route::get('sale/:id', 'addon\hsx_erp\app\adminapi\controller\ErpSale@info');

    Route::get('finance/payable/lists', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@payableLists');
    Route::get('finance/payable/info/:id', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@payableInfo');
    Route::get('finance/payable/party/:party_id/items', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@payablePartyItems');
    Route::get('finance/receivable/lists', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@receivableLists');
    Route::get('finance/receivable/:id', 'addon\hsx_erp\app\adminapi\controller\ErpFinance@receivableInfo');
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
    Route::post('opening/upload', 'addon\hsx_erp\app\adminapi\controller\ErpOpening@upload');
    Route::get('opening/lists', 'addon\hsx_erp\app\adminapi\controller\ErpOpening@lists');
    Route::get('opening/:id', 'addon\hsx_erp\app\adminapi\controller\ErpOpening@info');
    Route::get('opening/:id/items', 'addon\hsx_erp\app\adminapi\controller\ErpOpening@items');
    Route::post('opening/:id/retry', 'addon\hsx_erp\app\adminapi\controller\ErpOpening@retry');
    Route::post('opening/:id/confirm', 'addon\hsx_erp\app\adminapi\controller\ErpOpening@confirm');
    Route::delete('opening/:id', 'addon\hsx_erp\app\adminapi\controller\ErpOpening@delete');
    Route::get('operating_finance/lists', 'addon\hsx_erp\app\adminapi\controller\ErpOperatingFinance@lists');
    Route::post('operating_finance/create', 'addon\hsx_erp\app\adminapi\controller\ErpOperatingFinance@create');

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
    Route::post('sale/return/create_and_confirm', 'addon\hsx_erp\app\adminapi\controller\ErpSaleReturn@createAndConfirm');
    Route::post('sale/return/compensate', 'addon\hsx_erp\app\adminapi\controller\ErpSaleReturn@compensate');
    Route::post('sale/return/:id/confirm', 'addon\hsx_erp\app\adminapi\controller\ErpSaleReturn@confirm');
    Route::post('sale/return/:id/cancel', 'addon\hsx_erp\app\adminapi\controller\ErpSaleReturn@cancel');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
])->mergeRuleRegex(false);
