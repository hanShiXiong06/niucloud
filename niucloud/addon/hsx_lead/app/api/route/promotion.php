<?php
declare(strict_types=1);
use think\facade\Route;
use app\api\middleware\{ApiChannel,ApiCheckToken};
use addon\hsx_lead\app\middleware\SafeErrors;
Route::group('hsx_lead/promotion',function():void {
    $c='addon\\hsx_lead\\app\\api\\controller\\Promotion@';
    foreach(['overview','team','rewards','rules','invitation'] as $action) Route::get($action,$c.$action);
    Route::post('bind',$c.'bind');
    Route::get('application',$c.'application');Route::post('application',$c.'apply');
})->middleware(SafeErrors::class)->middleware(ApiChannel::class)->middleware(ApiCheckToken::class,true)->completeMatch();
