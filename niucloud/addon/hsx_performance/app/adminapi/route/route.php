<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('performance', function () {
    Route::get('config', 'addon\hsx_performance\app\adminapi\controller\Config@info');
    Route::post('config', 'addon\hsx_performance\app\adminapi\controller\Config@save');
    Route::get('reports', 'addon\hsx_performance\app\adminapi\controller\Report@lists');
    Route::get('reports/:id', 'addon\hsx_performance\app\adminapi\controller\Report@info');
    Route::post('reports/generate', 'addon\hsx_performance\app\adminapi\controller\Report@generate');
    Route::post('reports/:id/retry', 'addon\hsx_performance\app\adminapi\controller\Report@retry');
})->middleware([AdminCheckToken::class, AdminCheckRole::class, AdminLog::class]);
