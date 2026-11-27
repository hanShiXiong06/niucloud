<?php
declare(strict_types=1);
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
use addon\hsx_lead\app\adminapi\middleware\PermissionGate;
use addon\hsx_lead\app\middleware\SafeErrors;

Route::group('hsx_lead/notice',function():void {
    $controller='addon\\hsx_lead\\app\\adminapi\\controller\\Notice@';
    Route::get('tasks',$controller.'tasks');
    Route::get('tasks/:id/attempts',$controller.'attempts');
    Route::post('tasks/:id/retry',$controller.'retry');
    Route::get('settings',$controller.'settings');
    Route::put('settings',$controller.'saveSettings');
    Route::get('library',$controller.'library');
})->middleware([SafeErrors::class,AdminCheckToken::class,AdminCheckRole::class,PermissionGate::class])->completeMatch();
