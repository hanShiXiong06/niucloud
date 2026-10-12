<?php
use think\facade\Route;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminCheckRole;

// Credential-bearing requests use the plugin's safe audit log, not raw-body AdminLog.
Route::group('kingdee', function () {
    $controller = 'addon\\hsx_kingdee\\app\\adminapi\\controller\\Workbench@';
    Route::get('config', $controller . 'config');
    Route::get('staff', $controller . 'staff');
    Route::get('logs', $controller . 'logs');
    Route::post('config', $controller . 'saveConfig');
    Route::post('connection/probe', $controller . 'probe');
    Route::get('tasks', $controller . 'tasks');
    Route::get('tasks/:id', $controller . 'detail');
    Route::post('tasks/:id/retry', $controller . 'retry');
    Route::post('tasks/:id/resolve', $controller . 'resolve');
    Route::post('tasks/:id/notice', $controller . 'notice');
    Route::get('mappings', $controller . 'mappings');
    Route::post('mappings', $controller . 'saveMapping');
})->middleware([AdminCheckToken::class, AdminCheckRole::class]);
