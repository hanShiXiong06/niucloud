<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('recycle_daheng_quote', function () {
    Route::get('quotation_crawler_config', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation\QuotationCrawlerConfig@getConfig');
    Route::post('quotation_crawler_config', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation\QuotationCrawlerConfig@setConfig');
    Route::get('quotation_crawler_config/default', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation\QuotationCrawlerConfig@getDefaultConfig');

    Route::get('quotation_v2/display_config', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\DisplayConfig@info');
    Route::post('quotation_v2/display_config', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\DisplayConfig@save');
    Route::get('quotation_v2/dataset', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Dataset@lists');
    Route::get('quotation_v2/dataset/all', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Dataset@all');
    Route::post('quotation_v2/dataset/init_chaoniu_defaults', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Dataset@initChaoniuDefaults');
    Route::get('quotation_v2/dataset/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Dataset@info');
    Route::post('quotation_v2/dataset', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Dataset@add');
    Route::put('quotation_v2/dataset/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Dataset@edit');
    Route::delete('quotation_v2/dataset/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Dataset@del');

    Route::post('quotation_v2/sync/preview/:datasetId', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Sync@preview');
    Route::post('quotation_v2/sync/import/:logId', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Sync@import');
    Route::post('quotation_v2/sync/now/:datasetId', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Sync@syncNow');

    Route::get('quotation_v2/model', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Query@models');
    Route::get('quotation_v2/capacity', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Query@capacities');
    Route::get('quotation_v2/field', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Query@fields');
    Route::get('quotation_v2/price/matrix', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Query@priceMatrix');
    Route::get('quotation_v2/price', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Query@prices');
    Route::get('quotation_v2/note', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Query@notes');
    Route::get('quotation_v2/log', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Query@logs');

    Route::post('quotation_v2/model', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@addModel');
    Route::put('quotation_v2/model/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@editModel');
    Route::delete('quotation_v2/model/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@deleteModel');
    Route::post('quotation_v2/capacity', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@addCapacity');
    Route::put('quotation_v2/capacity/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@editCapacity');
    Route::delete('quotation_v2/capacity/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@deleteCapacity');
    Route::post('quotation_v2/field', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@addField');
    Route::put('quotation_v2/field/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@editField');
    Route::delete('quotation_v2/field/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@deleteField');
    Route::post('quotation_v2/note', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@addNote');
    Route::put('quotation_v2/note/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@editNote');
    Route::delete('quotation_v2/note/:id', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Manage@deleteNote');
    Route::put('quotation_v2/price/:id/adjust', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Price@adjust');
    Route::post('quotation_v2/price/batch_adjust', 'addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2\Price@batchAdjust');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
]);
