<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('performance', function () {
    Route::get('output/overview', 'addon\hsx_performance\app\adminapi\controller\Output@overview');
    Route::get('output/employees', 'addon\hsx_performance\app\adminapi\controller\Output@employees');
    Route::get('output/employees/:uid', 'addon\hsx_performance\app\adminapi\controller\Output@employee');
    Route::get('output/facts', 'addon\hsx_performance\app\adminapi\controller\Output@facts');
    Route::get('output/anomalies', 'addon\hsx_performance\app\adminapi\controller\Output@anomalies');
    Route::post('output/anomalies/:id/resolve', 'addon\hsx_performance\app\adminapi\controller\Output@resolveAnomaly');
    Route::post('output/rebuild', 'addon\hsx_performance\app\adminapi\controller\Output@rebuild');
    Route::post('output/reconcile', 'addon\hsx_performance\app\adminapi\controller\Output@reconcile');
    Route::get('config', 'addon\hsx_performance\app\adminapi\controller\Config@info');
    Route::post('config', 'addon\hsx_performance\app\adminapi\controller\Config@save');
    Route::get('reports', 'addon\hsx_performance\app\adminapi\controller\Report@lists');
    Route::get('reports/:id', 'addon\hsx_performance\app\adminapi\controller\Report@info');
    Route::post('reports/generate', 'addon\hsx_performance\app\adminapi\controller\Report@generate');
    Route::post('reports/:id/retry', 'addon\hsx_performance\app\adminapi\controller\Report@retry');
})->middleware([AdminCheckToken::class, AdminCheckRole::class, AdminLog::class]);
