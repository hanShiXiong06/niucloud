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


Route::group('tk_vip', function() {
    /***************************************************** vip 登录接口 ****************************************************/
    //获取开启等级权益的会员等级
    Route::get('member/level', 'addon\tk_vip\app\api\controller\member\Level@lists');
    
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //false表示不验证登录
    ->middleware(ApiLog::class);
Route::group('recycle', function() {
     // 获取热门分类
     Route::get('recycle_category/hot', 'addon\recycle\app\api\controller\recycle_category\RecycleCategory@hot');
     Route::get('recycle_category_tree', 'addon\recycle\app\api\controller\recycle_category\RecycleCategory@tree');

})->middleware(ApiChannel::class)
->middleware(ApiCheckToken::class, false) //false表示不验证登录
->middleware(ApiLog::class);
/**
 * 二手机报价
 */
Route::group('recycle', function() {
    /***************************************************** hello world ****************************************************/
    Route::get('hello_world', 'addon\recycle\app\api\controller\hello_world\Index@index');
     //二手机分类列表
     
    
         // recycle/recycle_address_list 获取商家的回收地址
     Route::get('address_list','addon\recycle\app\api\controller\recycle_category\RecycleCategory@address_list');
    // Banner相关接口
    Route::get('recycle_banner', 'addon\recycle\app\api\controller\recycle_category\RecycleBanner@lists');
    // device_status/list 获取设备状态
    Route::get('device_status/list', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@getDeviceStatus');

    // 获取订单的状态 recycle_order/status_count
    Route::get('recycle_order/status_count', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@getStatusCount');
    // 获取设备数量 recycle/device/count
    Route::get('recycle_device/count', 'addon\recycle\app\api\controller\recycle_order\RecycleDevice@getCount');

    // 回收订单相关接口
    // 获取请求用户的所有回收订单
    Route::get('recycle_order', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@lists');
    // 获取单条订单详情
    Route::get('recycle_order/:id', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@show');
    // 创建回收订单
    Route::post('recycle_order', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@store');

    // Route::put('recycle_order/:id', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@update');
    // 删除订单
    Route::delete('recycle_order/:id', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@delete');
    // 取消订单 (未完成)
    Route::put('recycle_order/:id/cancel', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@cancel');
    // 获取订单的状态
    Route::get('recycle_order/status', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@getStatus');
    // 更改订单的状态 recycle_order/update_status/:id
    Route::put('recycle_order/update_status/:id', 'addon\recycle\app\api\controller\recycle_order\RecycleOrder@updateStatus');

    // ---------------------------------------------------------------------------------------------------------------
    // 回收设备相关接口
    // 获取单条的设备详情
    Route::get('recycle_device/:id', 'addon\recycle\app\api\controller\recycle_order\RecycleDevice@info');
    // 获取指定订单下的所有设备信息
    Route::get('recycle_device/order_devices/:order_id', 'addon\recycle\app\api\controller\recycle_order\RecycleDevice@orderDevices');
    // 确认价格
    Route::put('recycle_device/:id/confirm_price', 'addon\recycle\app\api\controller\recycle_order\RecycleDevice@confirmPrice');
    // 批量确认设备
    Route::put('recycle_device/all_confirm', 'addon\recycle\app\api\controller\recycle_order\RecycleDevice@deviceAllConfirm');
    // 获取用户退货地址
    Route::get('recycle_user_address', 'addon\recycle\app\api\controller\recycle_user_address\RecycleUserAddress@info');
    // 添加用户退货地址
    Route::post('recycle_user_address', 'addon\recycle\app\api\controller\recycle_user_address\RecycleUserAddress@add');
    // 编辑用户退货地址
    Route::put('recycle_user_address/:id', 'addon\recycle\app\api\controller\recycle_user_address\RecycleUserAddress@edit');
    // 删除用户退货地址
    Route::delete('recycle_user_address/:id', 'addon\recycle\app\api\controller\recycle_user_address\RecycleUserAddress@del');

    Route::get('excel/lists', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@lists');
    Route::get('excel/brands', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@getBrandList');
    Route::get('excel/statistics', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@getStatistics');
   

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //改为 true 表示需要验证登录
    ->middleware(ApiLog::class);



Route::group('recycle_price', function() {
    // 收款方式管
    Route::get('payment/list', 'addon\recycle\app\api\controller\payment\Payment@lists');
    Route::post('payment/add', 'addon\recycle\app\api\controller\payment\Payment@add');
    Route::put('payment/update/:id', 'addon\recycle\app\api\controller\payment\Payment@edit');
    Route::delete('payment/delete/:id', 'addon\recycle\app\api\controller\payment\Payment@del');
    Route::put('payment/set_default/:id', 'addon\recycle\app\api\controller\payment\Payment@setDefault');
    // Route::get('member/level', 'addon\recycle\app\api\controller\payment\Level@lists');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //表示验证登录
    ->middleware(ApiLog::class);
