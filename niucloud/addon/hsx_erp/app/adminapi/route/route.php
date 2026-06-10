<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('erp', function () {
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
