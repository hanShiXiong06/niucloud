<?php
declare(strict_types=1);
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
use addon\hsx_lead\app\adminapi\middleware\PermissionGate;
use addon\hsx_lead\app\middleware\SafeErrors;
Route::group('hsx_lead/promotion', function (): void {
    $c = 'addon\\hsx_lead\\app\\adminapi\\controller\\Promotion@';
    Route::get('config', $c.'config');
    Route::put('config', $c.'saveConfig');
    Route::get('relations', $c.'relations');
    Route::get('providers', $c.'providers');
    Route::put('providers/:id', $c.'saveProvider');
    Route::post('providers/:id/review', $c.'reviewProvider');
    Route::post('orders/:id/retry', $c.'retryPromotion');
    Route::get('rewards', $c.'rewards');
    Route::post('settle', $c.'settle');
    Route::post('rewards/:id/hold', $c.'hold');
    Route::post('rewards/:id/resume', $c.'resume');
})->middleware([SafeErrors::class, AdminCheckToken::class, AdminCheckRole::class, PermissionGate::class])->completeMatch();
