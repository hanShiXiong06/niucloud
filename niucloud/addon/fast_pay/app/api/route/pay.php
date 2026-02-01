<?php
// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

use addon\fast_pay\app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;

//支付异步回调
Route::any('fast_pay/pay/notify/:site_id/:channel/:type/:action', 'addon\fast_pay\app\api\controller\pay\Pay@notify')
    ->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class) //false表示不验证登录
    ->middleware(ApiLog::class);
Route::any('fast_pay/pay/partnernotify/:site_id/:channel/:type/:action', 'addon\fast_pay\app\api\controller\pay\Pay@partnernotify')
    ->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class) //false表示不验证登录
    ->middleware(ApiLog::class);
Route::any('fast_pay/pay/businessnotify/:site_id/:channel/:type/:action', 'addon\fast_pay\app\api\controller\pay\Pay@businessnotify')
    ->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class) //false表示不验证登录
    ->middleware(ApiLog::class);
/**
 * 路由
 */
Route::group('fast_pay',function () {
    //去支付
    Route::post('pay', 'addon\fast_pay\app\api\controller\pay\Pay@pay');
    Route::post('partnerpay', 'addon\fast_pay\app\api\controller\pay\Pay@partnerpay');
    Route::post('businesspay', 'addon\fast_pay\app\api\controller\pay\Pay@businesspay');
    //支付信息
    Route::get('info/:trade_type/:trade_id', 'addon\fast_pay\app\api\controller\pay\Pay@info');
    //获取支付类型
    Route::get('type/:trade_type', 'addon\fast_pay\app\api\controller\pay\Pay@getPayType');
    //支付关闭
    Route::post('close', 'addon\fast_pay\app\api\controller\pay\Pay@close');
    //创建快速支付订单
    Route::post('order/create', 'addon\fast_pay\app\api\controller\order\Order@create');
    //创建商家支付订单
    Route::post('business/order/create', 'addon\fast_pay\app\api\controller\order\BusinessOrder@create');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class,true)
    ->middleware(ApiLog::class);