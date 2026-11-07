<?php
declare(strict_types=1);
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
use addon\hsx_lead\app\adminapi\middleware\PermissionGate;
use addon\hsx_lead\app\middleware\SafeErrors;

Route::group('hsx_lead/catalog',function():void {
    $c='addon\\hsx_lead\\app\\adminapi\\controller\\Catalog@';
    Route::get('items',$c.'items');Route::post('items',$c.'add');
    Route::put('items/:id',$c.'update');Route::delete('items/:id',$c.'remove');
    Route::get('logs',$c.'logs');
})->middleware([SafeErrors::class,AdminCheckToken::class,AdminCheckRole::class,PermissionGate::class])->completeMatch();
