<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('recycle_quote_spider', function () {
    Route::get('source', 'addon\recycle_quote_spider\app\adminapi\controller\Source@lists');
    Route::get('source/all', 'addon\recycle_quote_spider\app\adminapi\controller\Source@all');
    Route::get('source/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Source@info');
    Route::post('source', 'addon\recycle_quote_spider\app\adminapi\controller\Source@add');
    Route::post('source/default', 'addon\recycle_quote_spider\app\adminapi\controller\Source@createDefault');
    Route::put('source/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Source@edit');
    Route::delete('source/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Source@del');
    Route::post('source/:id/sync', 'addon\recycle_quote_spider\app\adminapi\controller\Source@sync');

    Route::get('category', 'addon\recycle_quote_spider\app\adminapi\controller\Category@lists');
    Route::get('category/tree', 'addon\recycle_quote_spider\app\adminapi\controller\Category@tree');
    Route::post('category', 'addon\recycle_quote_spider\app\adminapi\controller\Category@add');
    Route::put('category/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Category@edit');
    Route::delete('category/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Category@del');

    Route::get('item', 'addon\recycle_quote_spider\app\adminapi\controller\Item@lists');
    Route::get('item/filter-options', 'addon\recycle_quote_spider\app\adminapi\controller\Item@filterOptions');
    Route::get('item/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Item@info');
    Route::post('item', 'addon\recycle_quote_spider\app\adminapi\controller\Item@add');
    Route::put('item/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Item@edit');
    Route::delete('item/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Item@del');
    Route::get('row', 'addon\recycle_quote_spider\app\adminapi\controller\Item@rows');
    Route::get('row/:id/price-history', 'addon\recycle_quote_spider\app\adminapi\controller\Item@priceHistory');
    Route::post('row', 'addon\recycle_quote_spider\app\adminapi\controller\Item@addRow');
    Route::put('row/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Item@editRow');
    Route::delete('row/:id', 'addon\recycle_quote_spider\app\adminapi\controller\Item@delRow');

    Route::post('import/upload', 'addon\recycle_quote_spider\app\adminapi\controller\Import@upload');
    Route::post('import/preview', 'addon\recycle_quote_spider\app\adminapi\controller\Import@preview');
    Route::post('import/confirm', 'addon\recycle_quote_spider\app\adminapi\controller\Import@confirm');
    Route::post('import/batch-confirm', 'addon\recycle_quote_spider\app\adminapi\controller\Import@batchConfirm');
    Route::get('import', 'addon\recycle_quote_spider\app\adminapi\controller\Import@lists');

    Route::get('log', 'addon\recycle_quote_spider\app\adminapi\controller\Log@lists');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
]);
