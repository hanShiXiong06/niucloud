<?php
declare(strict_types=1);
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
use addon\hsx_lead\app\adminapi\middleware\PermissionGate;
use addon\hsx_lead\app\middleware\SafeErrors;
Route::group('hsx_lead/cooperation/deposits', function (): void {
    $c = 'addon\\hsx_lead\\app\\adminapi\\controller\\Deposit@';
    Route::get('', $c.'lists');
    Route::get(':id', $c.'detail');
    Route::post(':id/review', $c.'review');
    Route::post(':id/refresh', $c.'refresh');
})->middleware([SafeErrors::class, AdminCheckToken::class, AdminCheckRole::class, PermissionGate::class])->completeMatch();
