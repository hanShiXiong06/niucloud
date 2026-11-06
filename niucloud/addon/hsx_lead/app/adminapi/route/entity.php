<?php
declare(strict_types=1);
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
use addon\hsx_lead\app\adminapi\middleware\PermissionGate;
use addon\hsx_lead\app\middleware\SafeErrors;

Route::group('hsx_lead/entities',function():void {
    $controller='addon\\hsx_lead\\app\\adminapi\\controller\\Entity@';
    Route::get('',$controller.'lists');
    Route::get(':id',$controller.'detail');
    Route::post(':id/members/:member_id/review',$controller.'review');
    Route::delete(':id/members/:member_id',$controller.'remove');
})->middleware([SafeErrors::class,AdminCheckToken::class,AdminCheckRole::class,PermissionGate::class])->completeMatch();
