<?php
declare(strict_types=1);
use think\facade\Route;
use app\api\middleware\{ApiChannel,ApiCheckToken};
use addon\hsx_lead\app\middleware\SafeErrors;

Route::group('hsx_lead/cooperation',function():void {
    Route::post('poster','addon\\hsx_lead\\app\\api\\controller\\Poster@generate');
})->middleware(SafeErrors::class)->middleware(ApiChannel::class)->middleware(ApiCheckToken::class,true)->completeMatch();
