<?php
use think\facade\Route;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminCheckRole;

// Do not attach raw-request AdminLog to routes accepting credentials.
Route::group('kingdee_jdy', function () {
    $controller = 'addon\\hsx_kingdee_jdy\\app\\adminapi\\controller\\Workbench@';
    Route::get('config', $controller . 'config');
    Route::post('config', $controller . 'saveConfig');
    Route::get('staff', $controller . 'staff');
    Route::post('connection/probe', $controller . 'probe');
    Route::get('tasks', $controller . 'tasks');
    Route::get('tasks/:id', $controller . 'detail');
    Route::post('tasks/:id/retry', $controller . 'retry');
    Route::post('tasks/:id/resolve', $controller . 'resolve');
    Route::post('tasks/:id/notice', $controller . 'notice');
    Route::get('mappings', $controller . 'mappings');
    Route::post('mappings', $controller . 'saveMapping');
    Route::get('logs', $controller . 'logs');
})->middleware([AdminCheckToken::class, AdminCheckRole::class]);
