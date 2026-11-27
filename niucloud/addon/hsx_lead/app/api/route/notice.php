<?php
declare(strict_types=1);
use think\facade\Route;
use app\api\middleware\{ApiChannel,ApiCheckToken};
use addon\hsx_lead\app\middleware\SafeErrors;

Route::group('hsx_lead/notice', function (): void {
    $controller = 'addon\\hsx_lead\\app\\api\\controller\\Notice@';
    Route::get('preferences', $controller.'preferences');
    Route::put('preferences', $controller.'savePreferences');
    Route::post('authorization', $controller.'authorization');
    Route::post('cooperation-authorization', $controller.'cooperationAuthorization');
})->middleware(SafeErrors::class)->middleware(ApiChannel::class)->middleware(ApiCheckToken::class, true)->completeMatch();
