<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use app\api\middleware\ApiChannel;
use think\facade\Route;


/**
 * 手机保修查询
 */
Route::group('hsx_phone_query', function() {
    /***************************************************** hello world ****************************************************/
    Route::get('hello_world', 'addon\hsx_phone_query\app\api\controller\hello_world\Index@index');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false) //false表示不验证登录
    ->middleware(ApiLog::class);

Route::group('hsx_phone_query', function () {
    //分类列表
    Route::get('category', 'addon\hsx_phone_query\app\api\controller\hsx_phone_query\HsxPhoneQueryCategory@lists');   
    // hsx_phone_query/model/list 查询
    Route::get('query', 'addon\hsx_phone_query\app\api\controller\hsx_phone_query\HsxPhoneQuery@query');
    // 创建现金支付查询订单，订单支付交给框架支付组件
    Route::post('order/create', 'addon\hsx_phone_query\app\api\controller\hsx_phone_query\HsxPhoneQuery@createOrder');
    // 积分查询，直接扣积分并执行查询
    Route::post('query/point', 'addon\hsx_phone_query\app\api\controller\hsx_phone_query\HsxPhoneQuery@pointQuery');
    // list 获取个人用户查询的列表
    Route::get('list', 'addon\hsx_phone_query\app\api\controller\hsx_phone_query\HsxPhoneQuery@lists');
    // detail/:id
    Route::get('detail/:id', 'addon\hsx_phone_query\app\api\controller\hsx_phone_query\HsxPhoneQuery@detail');
    //config/watermark
    Route::get('config/watermark', 'addon\hsx_phone_query\app\api\controller\hsx_phone_query\HsxPhoneQuery@watermark');
    Route::get('config/display', 'addon\hsx_phone_query\app\api\controller\hsx_phone_query\HsxPhoneQuery@displayConfig');
})
->middleware(ApiChannel::class)
->middleware(ApiCheckToken::class, true) //表示验证登录
->middleware(ApiLog::class);
