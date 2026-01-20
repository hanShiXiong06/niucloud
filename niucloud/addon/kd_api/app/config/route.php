<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

use addon\kd_api\app\api\middleware\ApiCheck;
use app\api\middleware\ApiLog;
use think\facade\Route;

Route::group('kdapi', function () {
    /***************************************************** V1.0接口 ****************************************************/
    Route::post('getlink', 'addon\kd_api\app\api\controller\open\Open@getLink');
    Route::post('getorder', 'addon\kd_api\app\api\controller\open\Open@getOrder');
})->middleware(ApiCheck::class)
    ->middleware(ApiLog::class);
