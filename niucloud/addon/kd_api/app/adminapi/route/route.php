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

use think\facade\Route;

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;

/**
 * 聚合快递API
 */
Route::group('kd_api', function () {


})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_BEGIN -- kdapi_api

Route::group('kd_api', function () {

    //api对接列表
    Route::get('kdapi_api', 'addon\kd_api\app\adminapi\controller\kdapi_api\KdapiApi@lists');
    //api对接详情
    Route::get('kdapi_api/:id', 'addon\kd_api\app\adminapi\controller\kdapi_api\KdapiApi@info');
    //添加api对接
    Route::post('kdapi_api', 'addon\kd_api\app\adminapi\controller\kdapi_api\KdapiApi@add');
    //编辑api对接
    Route::put('kdapi_api/:id', 'addon\kd_api\app\adminapi\controller\kdapi_api\KdapiApi@edit');
    //删除api对接
    Route::delete('kdapi_api/:id', 'addon\kd_api\app\adminapi\controller\kdapi_api\KdapiApi@del');
    
    Route::get('member_all','addon\kd_api\app\adminapi\controller\kdapi_api\KdapiApi@getMemberAll');
    //获取状态
    Route::get('getstatus','addon\kd_api\app\adminapi\controller\kdapi_api\KdapiApi@getStatus');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- kdapi_api

// USER_CODE_BEGIN -- kdapi_order

Route::group('kd_api', function () {

    //订单列列表
    Route::get('kdapi_order', 'addon\kd_api\app\adminapi\controller\kdapi_order\KdapiOrder@lists');
    //订单列详情
    Route::get('kdapi_order/:id', 'addon\kd_api\app\adminapi\controller\kdapi_order\KdapiOrder@info');
    //添加订单列
    Route::post('kdapi_order', 'addon\kd_api\app\adminapi\controller\kdapi_order\KdapiOrder@add');
    //编辑订单列
    Route::put('kdapi_order/:id', 'addon\kd_api\app\adminapi\controller\kdapi_order\KdapiOrder@edit');
    //删除订单列
    Route::delete('kdapi_order/:id', 'addon\kd_api\app\adminapi\controller\kdapi_order\KdapiOrder@del');
    
    Route::get('member_all','addon\kd_api\app\adminapi\controller\kdapi_order\KdapiOrder@getMemberAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- kdapi_order
