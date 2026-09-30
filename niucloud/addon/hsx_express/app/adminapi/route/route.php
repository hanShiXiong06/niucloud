<?php
declare(strict_types=1);

use think\facade\Route;
use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;

// 不挂会将全部request参数明文入库的AdminLog，任务服务自行保存脱敏操作审计。
Route::group('hsx_express', function () {
    $controller = 'addon\\hsx_express\\app\\adminapi\\controller\\Express@';
    Route::get('config', $controller . 'config');
    Route::put('config', $controller . 'save');
    Route::post('config/check', $controller . 'check');
    Route::post('check', $controller . 'check');
    Route::get('tasks', $controller . 'tasks');
    Route::get('tasks/:id', $controller . 'detail');
    Route::post('tasks/:id/reprint', $controller . 'reprint');
    Route::post('tasks/:id/cancel', $controller . 'cancel');
    Route::post('tasks/:id/recover', $controller . 'recover');
})->middleware([AdminCheckToken::class, AdminCheckRole::class]);
