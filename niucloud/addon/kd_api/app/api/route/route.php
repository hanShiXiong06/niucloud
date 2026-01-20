<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use app\api\middleware\ApiChannel;
use think\facade\Route;

//Route::group('kd_api', function () {
//    Route::post('getlink', 'addon\kd_api\app\api\controller\open\Demo@getLink');
//    Route::post('getorder', 'addon\kd_api\app\api\controller\open\Demo@getOrder');
//})->middleware(ApiChannel::class)
//    ->middleware(ApiCheckToken::class, false) //表示验证登录
//    ->middleware(ApiLog::class);

Route::group('kd_api', function () {
    Route::get('getconfig', 'addon\kd_api\app\api\controller\index\Index@getConfig');
    Route::post('restkey', 'addon\kd_api\app\api\controller\index\Index@resetKey');
    Route::get('getorder', 'addon\kd_api\app\api\controller\index\Index@getOrder');
    Route::get('getlink', 'addon\kd_api\app\api\controller\open\Open@getLink');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //表示验证登录
    ->middleware(ApiLog::class);

