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

// 易速快递推送回调：第三方不会携带 site-id，不能挂 ApiCheckToken/ApiChannel，否则会被站点校验拦截。
Route::any('recycle/yisunotice', 'addon\hsx_recycle\app\api\controller\express\ExpressController@yisuPush')
    ->middleware(ApiLog::class);
Route::any('recycle/express/yisu_push', 'addon\hsx_recycle\app\api\controller\express\ExpressController@yisuPush')
    ->middleware(ApiLog::class);
Route::any('tk_jhkd/yisunotice', 'addon\hsx_recycle\app\api\controller\express\ExpressController@yisuPush')
    ->middleware(ApiLog::class);

Route::group('tk_vip', function() {
    /***************************************************** vip 登录接口 ****************************************************/
    //获取开启等级权益的会员等级
    Route::get('member/level', 'addon\tk_vip\app\api\controller\member\Level@lists');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //false表示不验证登录
    ->middleware(ApiLog::class);

Route::group('tk_jhkd', function() {

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false)
    ->middleware(ApiLog::class);

Route::group('recycle', function() {
     // 获取热门分类
     Route::get('recycle_category/hot', 'addon\hsx_recycle\app\api\controller\category\RecycleCategory@hot');
     Route::get('recycle_category_tree', 'addon\hsx_recycle\app\api\controller\category\RecycleCategory@tree');
     // 报价单浏览埋点
     Route::post('recycle_category/:id/view', 'addon\hsx_recycle\app\api\controller\category\RecycleCategory@recordView');

})->middleware(ApiChannel::class)
->middleware(ApiCheckToken::class, false) //false表示不验证登录
->middleware(ApiLog::class);
/**
 * 二手机报价
 */
Route::group('recycle', function() {
    /***************************************************** hello world ****************************************************/
    Route::get('hello_world', 'addon\hsx_recycle\app\api\controller\hello_world\Index@index');
     //二手机分类列表
    Route::get('dict/:id', 'addon\hsx_recycle\app\api\controller\hello_world\Dict@getDict');

    // recycle/recycle_address_list 获取商家的回收地址
     Route::get('address_list','addon\hsx_recycle\app\api\controller\category\RecycleCategory@address_list');
    // device_status/list 获取设备状态
    Route::get('device_status/list', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@getDeviceStatus');

    // 获取订单的状态 recycle_order/status_count
    Route::get('recycle_order/status_count', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@getStatusCount');
    // 获取设备数量 recycle/device/count
    Route::get('recycle_device/count', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleDevice@getCount');

    // 回收订单相关接口
    Route::get('order_submit_config', 'addon\hsx_recycle\app\api\controller\recycle_order\OrderSubmitConfig@info');
    Route::get('recycle_order', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@lists');
    // 获取单条订单详情
    Route::get('recycle_order/:id', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@show');
    // 创建回收订单
    Route::post('recycle_order', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@store');

     //Route::put('recycle_order/:id', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@update');
    // 删除订单
    Route::delete('recycle_order/:id', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@delete');
    // 取消订单 (未完成)
    Route::put('recycle_order/:id/cancel', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@cancel');
    // 获取订单的状态
    Route::get('recycle_order/status', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@getStatus');
    // 更改订单的状态
    Route::put('recycle_order/update_status/:id', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleOrder@updateStatus');

    // ---------------------------------------------------------------------------------------------------------------
    // 回收设备相关接口
    // 获取单条的设备详情
    Route::get('recycle_device/:id', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleDevice@info');
    // 获取指定订单下的所有设备信息
    Route::get('recycle_device/order_devices/:order_id', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleDevice@orderDevices');
    // 确认价格
    Route::put('recycle_device/:id/confirm_price', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleDevice@confirmPrice');
    // 确认设备处理方式
    Route::put('recycle_device/:id/confirm', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleDevice@confirm');
    // 批量确认设备
    Route::put('recycle_device/all_confirm', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleDevice@deviceAllConfirm');
    // 获取用户退货地址
    Route::get('recycle_user_address', 'addon\hsx_recycle\app\api\controller\address\RecycleUserAddress@info');
    // 添加用户退货地址
    Route::post('recycle_user_address', 'addon\hsx_recycle\app\api\controller\address\RecycleUserAddress@add');
    // 编辑用户退货地址
    Route::put('recycle_user_address/:id', 'addon\hsx_recycle\app\api\controller\address\RecycleUserAddress@edit');
    // 删除用户退货地址
    Route::delete('recycle_user_address/:id', 'addon\hsx_recycle\app\api\controller\address\RecycleUserAddress@del');


    // 获取快递信息
    Route::get('device_query_api/express', 'addon\hsx_recycle\app\api\controller\recycle\DeviceQueryApiController@getExpress');

    // ---------------------------------------------------------------------------------------------------------------
    // 退货订单相关接口
    // 根据原订单ID查询退货订单
    Route::get('recycle_return_order/by_order/:order_id', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleReturnOrder@getByOrderId');
    // 获取退货订单详情
    Route::get('recycle_return_order/:id', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleReturnOrder@detail');

    // ---------------------------------------------------------------------------------------------------------------
    // 代卖订单相关接口
    Route::get('consignment_order/status_count', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleConsignmentOrder@statusCount');
    Route::get('consignment_order/status', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleConsignmentOrder@status');
    Route::get('consignment_order', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleConsignmentOrder@lists');
    Route::get('consignment_order/:id', 'addon\hsx_recycle\app\api\controller\recycle_order\RecycleConsignmentOrder@detail');

    // ---------------------------------------------------------------------------------------------------------------
    // 公众号关注状态检查
    Route::get('wechat_follow/check', 'addon\hsx_recycle\app\api\controller\recycle_order\WechatFollow@check');

    // ---------------------------------------------------------------------------------------------------------------
    // 统一快递服务接口（亿速）
    Route::post('express/quote', 'addon\hsx_recycle\app\api\controller\express\ExpressController@quote');
    Route::get('express/track/:order_id', 'addon\hsx_recycle\app\api\controller\express\ExpressController@track');
    Route::get('express/providers', 'addon\hsx_recycle\app\api\controller\express\ExpressController@providers');
    Route::get('express/check', 'addon\hsx_recycle\app\api\controller\express\ExpressController@check');
    Route::post('express/cancel', 'addon\hsx_recycle\app\api\controller\express\ExpressController@cancel');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //改为 true 表示需要验证登录
    ->middleware(ApiLog::class);


/**
 * 用户收款方式相关接口
 * */
Route::group('recycle_price', function() {
    // 收款方式管
    Route::get('payment/list', 'addon\hsx_recycle\app\api\controller\payment\Payment@lists');
    Route::post('payment/add', 'addon\hsx_recycle\app\api\controller\payment\Payment@add');
    Route::put('payment/update/:id', 'addon\hsx_recycle\app\api\controller\payment\Payment@edit');
    Route::delete('payment/delete/:id', 'addon\hsx_recycle\app\api\controller\payment\Payment@del');
    Route::put('payment/set_default/:id', 'addon\hsx_recycle\app\api\controller\payment\Payment@setDefault');
    // Route::get('member/level', 'addon\hsx_recycle\app\api\controller\payment\Level@lists');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //表示验证登录
    ->middleware(ApiLog::class);
