<?php
require __DIR__ . '/pay.php';
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use app\api\middleware\ApiChannel;
use think\facade\Route;


/**
 * 快速支付
 */
Route::group('fast_pay', function() {
    /***************************************************** 快速支付配置获取 ****************************************************/
    Route::get('config/getconfig', 'addon\fast_pay\app\api\controller\config\Config@getConfig');
    //获取商户配置
    Route::get('config/getbusinessconfig/:id', 'addon\fast_pay\app\api\controller\config\Config@getBusinessConfig');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false) //false表示不验证登录
    ->middleware(ApiLog::class);


Route::group('fast_pay', function() {
    Route::get('order/lists', 'addon\fast_pay\app\api\controller\order\Order@lists');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //表示验证登录
    ->middleware(ApiLog::class);

