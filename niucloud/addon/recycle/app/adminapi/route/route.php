<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------
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

// ✅ 概况页面路由
Route::group('recycle', function () {
    // 概况页面
    Route::get('recycle_overview', 'addon\recycle\app\adminapi\controller\Stats@getDashboardStats');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);

// ✅ USER_CODE_BEGIN -- recycle_recycle_category
/**
 * 设备分类管理
 * */ 
Route::group('recycle', function () {
    //二手机分类列表
    Route::get('recycle_category', 'addon\recycle\app\adminapi\controller\category\RecycleCategory@lists');
    //二手机分类详情
    Route::get('recycle_category/:id', 'addon\recycle\app\adminapi\controller\category\RecycleCategory@info');
    //添加二手机分类
    Route::post('recycle_category', 'addon\recycle\app\adminapi\controller\category\RecycleCategory@add');
    //编辑二手机分类
    Route::put('recycle_category/:id', 'addon\recycle\app\adminapi\controller\category\RecycleCategory@edit');
    //删除二手机分类
    Route::delete('recycle_category/:id', 'addon\recycle\app\adminapi\controller\category\RecycleCategory@del');
    //二手机分类列表
    Route::get('recycle_category_tree', 'addon\recycle\app\adminapi\controller\category\RecycleCategory@tree');
    //编辑商品分类
    Route::post('recycle_category/category/update', 'addon\recycle\app\adminapi\controller\category\RecycleCategory@updateCategory');
})->middleware([
    AdminCheckToken::class,
    AdminLog::class
]);

//  ✅ USER_CODE_BEGIN -- recycle_recycle_order
/**
 * 核心业务 ： 订单管理 
 * */ 
Route::group('recycle', function () {

    // 订单基础操作
    // 获取审核员绩效
   Route::get('recycle_order/staff_count', 'addon\recycle\app\adminapi\controller\Stats@inspectorPerformance');
    Route::get('recycle_order/price_confirmer_performance', 'addon\recycle\app\adminapi\controller\Stats@priceConfirmerPerformance');

    // 代下单
    Route::post('recycle_order/create', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@create');
    Route::get('recycle_order/lists', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@lists');
    Route::get('recycle_order/detail/:id', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@detail');
    Route::get('recycle_order/:id/devices', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@devices');


    // 订单状态操作
    Route::put('/recycle_order/:id', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@update');
    Route::delete('recycle_order/:id', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@delete');
     // 查询手机imei的信息
    Route::get('recycle_device/imei_info/:imei', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@getImeiInfo');
     // 设备信息打印 
    Route::post('printer/print_device_label/:id', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@printDeviceLabel');

    // 设备管理
    Route::get('recycle_device/:id', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@getInfo');
    Route::post('recycle_device', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@add');

    // 确认设备价格
    Route::put('recycle_device/:id/confirm_price', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@confirmPrice');
    Route::put('recycle_device/:id', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@update');
    Route::delete('recycle_device/:id', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@delete');
    // 批量设备操作
    Route::post('recycle_device/batch_update_status', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@batchUpdateStatus');
    Route::post('recycle_device/batch_recycle', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@batchRecycle');
    Route::post('recycle_device/batch_return', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@batchReturn');
    // 设备质检流程
    Route::put('recycle_device/:id/start_check', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@startCheck');
    Route::put('recycle_device/:id/complete_check', 'addon\recycle\app\adminapi\controller\order\RecycleDevice@completeCheck');
    // 获取订单及设备的状态信息
    Route::get('recycle_order/status', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@getStatus');
    // 获取商户的收款信息
    Route::get('recycle_order/merchant_pay_info/:id', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@getMerchantPayInfo');
    // 财务确认打款
    Route::put('recycle_order/:id/payment_confirm', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@paymentConfirm');
    
    // 推送订单确认通知
    Route::post('recycle_order/:id/push_notify', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@pushNotify');
    
    // 订单设备管理路由
    Route::post('recycle_order/:id/add_device', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@addDevice');
    Route::post('recycle_order/:id/batch_add_devices', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@batchAddDevices');
    Route::delete('recycle_order/:id/device/:device_id', 'addon\recycle\app\adminapi\controller\order\RecycleOrder@removeDevice');

     //商家地址库列表
     Route::get('shop_address', 'addon\recycle\app\adminapi\controller\address\ShopAddress@lists');

     //商家地址库详情
     Route::get('shop_address/:id', 'addon\recycle\app\adminapi\controller\address\ShopAddress@info');
 
     //添加商家地址库
     Route::post('shop_address', 'addon\recycle\app\adminapi\controller\address\ShopAddress@add');
 
     //编辑商家地址库
     Route::put('shop_address/:id', 'addon\recycle\app\adminapi\controller\address\ShopAddress@edit');
 
     //删除商家地址库
     Route::delete('shop_address/:id', 'addon\recycle\app\adminapi\controller\address\ShopAddress@del');
 
     // 默认发货地址
     Route::get('shop_address/default/delivery', 'addon\recycle\app\adminapi\controller\address\ShopAddress@defaultDelivery');
 
     //获取商家收货地址库
    //  Route::get('order/refund/address', 'addon\recycle\app\adminapi\controller\address\ShopAddress@getList');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_recycle_order

// ✅ USER_CODE_BEGIN -- recycle_recycle_return_order
/**
 * 退回订单相关接口
*/
Route::group('recycle', function () {
    // 退回订单基础操作
    Route::get('recycle_return_order/lists', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@lists');

    Route::post('recycle_return_order', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@create');
    Route::post('recycle_return_order/batch', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@batchCreate');
    
    // 退回订单状态操作
    Route::put('recycle_return_order/:id/status', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@updateStatus');
    Route::put('recycle_return_order/:id/confirm', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@confirm');
    Route::put('recycle_return_order/:id/cancel', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@cancel');
    Route::delete('recycle_return_order/:id', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@delete');
    
    // 退回订单状态信息
    Route::get('recycle_return_order/status', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@getStatus');
    Route::get('recycle_return_order/status_list', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@getStatusList');
    Route::get('recycle_return_order/:id/device_info', 'addon\recycle\app\adminapi\controller\order\RecycleReturnOrder@detail');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_recycle_return_order


// ✅  USER_CODE_BEGIN -- address
/**
 * 用户退货地址相关接口
*/

Route::group('recycle', function () {

    //用户退货地址列表
    Route::get('recycle_user_address', 'addon\recycle\app\adminapi\controller\address\RecycleUserAddress@lists');
    //用户退货地址详情
    Route::get('recycle_user_address/:id', 'addon\recycle\app\adminapi\controller\address\RecycleUserAddress@info');
    //添加用户退货地址
    Route::post('recycle_user_address', 'addon\recycle\app\adminapi\controller\address\RecycleUserAddress@add');
    //编辑用户退货地址
    Route::put('recycle_user_address/:id', 'addon\recycle\app\adminapi\controller\address\RecycleUserAddress@edit');
    //删除用户退货地址
    Route::delete('recycle_user_address/:id', 'addon\recycle\app\adminapi\controller\address\RecycleUserAddress@del');
    
    Route::get('member_all','addon\recycle\app\adminapi\controller\address\RecycleUserAddress@getMemberAll');

   
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_user_address


//  ✅  USER_CODE_BEGIN -- recycle_device_export
/**
 * 设备导出相关接口
*/

Route::group('recycle', function () {
    // 设备导出管理
    Route::get('device_export/list', 'addon\recycle\app\adminapi\controller\device\export\DeviceExportController@list');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_device_export

// ✅ USER_CODE_BEGIN -- recycle_printer
/**
 * 打印机相关接口
*/

Route::group('recycle', function () {
    // 打印机管理
    // 获取打印机品牌列表
    Route::get('printer/brand_list', 'addon\recycle\app\adminapi\controller\printer\Printer@getBrandList');
    // 获取打印机列表
    Route::get('printer/lists', 'addon\recycle\app\adminapi\controller\printer\Printer@lists');
    // 批量查询打印机状态
    Route::post('printer/batch_status', 'addon\recycle\app\adminapi\controller\printer\Printer@batchQueryStatus');
    // 获取打印机详情
    Route::get('printer/:id', 'addon\recycle\app\adminapi\controller\printer\Printer@info');
    // 添加打印机
    Route::post('printer', 'addon\recycle\app\adminapi\controller\printer\Printer@add');
    // 更新打印机
    Route::put('printer/:id', 'addon\recycle\app\adminapi\controller\printer\Printer@edit');
    // 删除打印机
    Route::delete('printer/:id', 'addon\recycle\app\adminapi\controller\printer\Printer@del');
    // 切换打印机状态
    Route::post('printer/user/toggle/:id', 'addon\recycle\app\adminapi\controller\printer\Printer@toggleStatus');
    // 查询打印机状态
    Route::get('printer/status/:id', 'addon\recycle\app\adminapi\controller\printer\Printer@queryPrinterStatus');
    
    // 获取用户绑定的打印机
    Route::get('printer/user', 'addon\recycle\app\adminapi\controller\printer\Printer@getUserPrinter');
    // 绑定打印机
    Route::post('printer/bind', 'addon\recycle\app\adminapi\controller\printer\Printer@bindPrinter');
    // 解绑打印机
    Route::post('printer/unbind', 'addon\recycle\app\adminapi\controller\printer\Printer@unbindPrinter');
    // 测试打印机
    Route::post('printer/test', 'addon\recycle\app\adminapi\controller\printer\Printer@testPrint');
    // 打印标签
    Route::post('printer/print_label', 'addon\recycle\app\adminapi\controller\printer\Printer@printLabel');
    // 打印设备标签
    Route::post('printer/print_device_label/:id', 'addon\recycle\app\adminapi\controller\printer\Printer@printDeviceLabel');
    
    // 打印模板管理
    // 获取模板列表
    Route::get('printer_template/lists', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@lists');
    // 获取模板详情
    Route::get('printer_template/:id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@info');
    // 添加模板
    Route::post('printer_template', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@add');
    // 编辑模板
    Route::put('printer_template/:id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@edit');
    // 删除模板
    Route::delete('printer_template/:id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@del');
    // 修改模板状态
    Route::post('printer_template/status/:id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@modifyStatus');
    // 设置默认模板
    Route::post('printer_template/default/:id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@setDefault');
    // 预览模板
    Route::get('printer_template/preview/:id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@preview');
    // 验证模板数据
    Route::post('printer_template/validate', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@validateTemplate');
    // 验证XML格式
    Route::post('printer_template/validate_xml', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@validateXml');
    // 提取模板变量
    Route::post('printer_template/extract_variables', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@extractVariables');
    // 渲染模板
    Route::post('printer_template/render', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@render');
    // 测试打印模板
    Route::post('printer_template/test_print/:id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@testPrint');
    // 获取模板类型列表
    Route::get('printer_template/type_list', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@getTypeList');
    // 获取默认模板
    Route::get('printer_template/default', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@getDefaultTemplate');
    // 获取设备打印数据
    Route::get('printer_template/device_print_data/:device_id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@getDevicePrintData');
    // 打印设备标签
    Route::post('printer_template/print_device_label/:device_id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@printDeviceLabel');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_printer

// ✅  USER_CODE_BEGIN -- recycle_stats
/**
 * 统计相关接口
*/

Route::group('recycle', function () {
    // 统计相关接口
    // 获取今日统计数据
    Route::get('stats/getTodayStats', 'addon\recycle\app\adminapi\controller\Stats@getTodayStats');
    // 获取用户统计数据
    Route::get('stats/getUserStats', 'addon\recycle\app\adminapi\controller\Stats@getUserStats');
    // 获取分类统计数据
    Route::get('stats/getCategoryStats', 'addon\recycle\app\adminapi\controller\Stats@getCategoryStats');
    // 获取签收统计数据
    Route::get('stats/getSignStats', 'addon\recycle\app\adminapi\controller\Stats@getSignStats');
    // 获取签收分类统计
    Route::get('stats/getSignCategoryStats', 'addon\recycle\app\adminapi\controller\Stats@getSignCategoryStats');
    // 获取普通用户签收统计
    Route::get('stats/getUserSignStats', 'addon\recycle\app\adminapi\controller\Stats@getUserSignStats');
    // 获取管理员概况统计
    Route::get('stats/getOverviewStats', 'addon\recycle\app\adminapi\controller\Stats@getOverviewStats');
    // 获取用户列表
    Route::get('stats/getUserList', 'addon\recycle\app\adminapi\controller\Stats@getUserList');
    // 获取用户详细统计
    Route::get('stats/getUserDetailStats', 'addon\recycle\app\adminapi\controller\Stats@getUserDetailStats');
    // 获取排行榜数据
    Route::get('stats/getRankingStats', 'addon\recycle\app\adminapi\controller\Stats@getRankingStats');
    // 获取质检员分类统计
    Route::get('stats/getCheckerCategoryStats', 'addon\recycle\app\adminapi\controller\Stats@getCheckerCategoryStats');
    // 获取质检员今日工作量
    Route::get('stats/getCheckerTodayWork', 'addon\recycle\app\adminapi\controller\Stats@getCheckerTodayWork');
    // 获取统计概览
    Route::get('stats/getDashboardStats', 'addon\recycle\app\adminapi\controller\Stats@getDashboardStats');
    // 兼容旧接口
    Route::get('stats/inspectorPerformance', 'addon\recycle\app\adminapi\controller\Stats@inspectorPerformance');
    Route::get('stats/priceConfirmerPerformance', 'addon\recycle\app\adminapi\controller\Stats@priceConfirmerPerformance');
    // 会员统计接口
    Route::get('stats/getMemberStatsOverview', 'addon\recycle\app\adminapi\controller\Stats@getMemberStatsOverview');
    Route::get('stats/getMemberRegisterTrend', 'addon\recycle\app\adminapi\controller\Stats@getMemberRegisterTrend');
    Route::get('stats/getMemberChannelStats', 'addon\recycle\app\adminapi\controller\Stats@getMemberChannelStats');
    Route::get('stats/getMemberInviteRank', 'addon\recycle\app\adminapi\controller\Stats@getMemberInviteRank');
    Route::get('stats/getMemberActivityStats', 'addon\recycle\app\adminapi\controller\Stats@getMemberActivityStats');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_stats

// ✅  USER_CODE_BEGIN -- device_query
/**
 * 设备查询相关接口
*/
Route::group('recycle', function () {
    // 设备查询配置管理
    Route::get('device_query_config/lists', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@lists');
    Route::get('device_query_config/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@info');
    Route::post('device_query_config', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@add');
    Route::put('device_query_config/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@edit');
    Route::delete('device_query_config/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@del');
    Route::put('device_query_config/status/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@modifyStatus');
    Route::post('device_query_config/test/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@testConnection');
    Route::get('device_query_config/stats/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@getStats');

    // API接口清单管理
    Route::get('device_query_api/lists', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@lists');
    Route::get('device_query_api/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@info');
    Route::post('device_query_api', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@add');
    Route::put('device_query_api/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@edit');
    Route::delete('device_query_api/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@del');
    Route::put('device_query_api/status/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@modifyStatus');
    Route::get('device_query_api/default', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getDefaultApiList');
    Route::post('device_query_api/init', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@initDefaultApiList');
    Route::get('device_query_api/by_endpoint', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getApiByEndpoint');
    Route::get('device_query_api/by_category', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getApisByCategory');
    // 获取设备的基本信息 coverage
    Route::get('device_query_api/coverage', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getCoverage');
    // 获取设备的激活锁 activationlock
    Route::get('device_query_api/activationlock', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getActivationlock');
    // 获取设备的mdm 监管锁 mdm
    Route::get('device_query_api/mdm', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getMdm');
    Route::get('device_query_api/express', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getExpress');
    // 查询结果管理
    Route::get('device_query_result/lists', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryResultController@lists');
    Route::get('device_query_result/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryResultController@info');
    Route::delete('device_query_result/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryResultController@del');
    Route::post('device_query_result/batch_del', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryResultController@batchDel');
    Route::get('device_query_result/stats', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryResultController@getStats');
    Route::post('device_query_result/clean_cache', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryResultController@cleanCache');
    Route::post('device_query_result/requery/:id', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryResultController@requery');
    Route::post('device_query_result/export', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryResultController@export');
    Route::get('device_query_result/total_consumption', 'addon\recycle\app\adminapi\controller\device_query\DeviceQueryResultController@getTotalConsumption');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_excel_data

// ✅  USER_CODE_BEGIN -- third_party
/**
 * 第三方服务管理相关接口
*/
Route::group('recycle', function () {
    // 第三方服务配置管理
    Route::get('third_party_service/lists', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyService@lists');
    Route::get('third_party_service/:id', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyService@info');
    Route::post('third_party_service', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyService@add');
    Route::put('third_party_service/:id', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyService@edit');
    Route::delete('third_party_service/:id', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyService@del');
    Route::put('third_party_service/status/:id', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyService@modifyStatus');

    // API调用日志管理
    Route::get('third_party_api_log/lists', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyApiLog@lists');
    Route::get('third_party_api_log/:id', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyApiLog@info');
    Route::delete('third_party_api_log/clean', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyApiLog@clean');

    // 费用统计管理
    Route::get('third_party_cost_stats/lists', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyCostStats@lists');
    Route::post('third_party_cost_stats/rebuild', 'addon\recycle\app\adminapi\controller\third_party\ThirdPartyCostStats@rebuild');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- third_party

// ✅  USER_CODE_BEGIN -- express_order
/**
 * 快递下单相关接口
*/
Route::group('recycle', function () {
    // 快递下单管理
    Route::post('express_order/quote', 'addon\recycle\app\adminapi\controller\express\ExpressOrder@quote');
    Route::post('express_order/create', 'addon\recycle\app\adminapi\controller\express\ExpressOrder@create');
    Route::post('express_order/cancel', 'addon\recycle\app\adminapi\controller\express\ExpressOrder@cancel');
    Route::get('express_order/track', 'addon\recycle\app\adminapi\controller\express\ExpressOrder@track');
    Route::get('express_order/balance', 'addon\recycle\app\adminapi\controller\express\ExpressOrder@balance');
    // 统一快递服务 - 为回收订单创建/取消/查询快递
    Route::post('express_order/create_for_order', 'addon\recycle\app\adminapi\controller\express\ExpressOrder@createForOrder');
    Route::post('express_order/cancel_for_order', 'addon\recycle\app\adminapi\controller\express\ExpressOrder@cancelForOrder');
    Route::get('express_order/track_for_order', 'addon\recycle\app\adminapi\controller\express\ExpressOrder@trackForOrder');
    Route::post('express_order/unified_quote', 'addon\recycle\app\adminapi\controller\express\ExpressOrder@unifiedQuote');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- express_order

// ✅  USER_CODE_BEGIN -- express_provider_config
/**
 * 快递服务商配置管理接口
*/
Route::group('recycle', function () {
    // 服务商配置列表
    Route::get('express_provider_config/lists', 'addon\recycle\app\adminapi\controller\express\ExpressProviderConfig@lists');
    // 服务商配置详情
    Route::get('express_provider_config/:id', 'addon\recycle\app\adminapi\controller\express\ExpressProviderConfig@info');
    // 编辑服务商配置
    Route::put('express_provider_config/:id', 'addon\recycle\app\adminapi\controller\express\ExpressProviderConfig@edit');
    // 设置默认服务商
    Route::put('express_provider_config/set_default/:id', 'addon\recycle\app\adminapi\controller\express\ExpressProviderConfig@setDefault');
    // 切换启用状态
    Route::put('express_provider_config/toggle_status/:id', 'addon\recycle\app\adminapi\controller\express\ExpressProviderConfig@toggleStatus');
    // 获取当前启用的服务商
    Route::get('express_provider_config/active', 'addon\recycle\app\adminapi\controller\express\ExpressProviderConfig@getActiveProvider');
    // 检查快递服务状态
    Route::get('express_provider_config/check_status', 'addon\recycle\app\adminapi\controller\express\ExpressProviderConfig@checkStatus');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- express_provider_config

// ✅  USER_CODE_BEGIN -- yisu_product
/**
 * 易速产品配置相关接口
*/
Route::group('recycle', function () {
    // 易速产品配置管理
    Route::get('yisu_product/lists', 'addon\recycle\app\adminapi\controller\yisu\YisuProduct@lists');
    Route::post('yisu_product/batch_update', 'addon\recycle\app\adminapi\controller\yisu\YisuProduct@batchUpdate');
    Route::post('yisu_product/modify_status', 'addon\recycle\app\adminapi\controller\yisu\YisuProduct@modifyStatus');
    Route::get('yisu_product/enabled', 'addon\recycle\app\adminapi\controller\yisu\YisuProduct@enabled');

    // yisu 业务相关接口
    // 下单 
    Route::post('yisu_order/create_order', 'addon\recycle\app\adminapi\controller\yisu\YisuOrder@createOrder');
    // 取消
    Route::post('yisu_order/cancel', 'addon\recycle\app\adminapi\controller\yisu\YisuOrder@cancel');
    // 查询
    Route::get('yisu_order/query', 'addon\recycle\app\adminapi\controller\yisu\YisuOrder@query');
    // 查询物流
    Route::get('yisu_order/track', 'addon\recycle\app\adminapi\controller\yisu\YisuOrder@track');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- yisu_product

// ✅  USER_CODE_BEGIN -- express_order_record
/**
 * 快递订单记录相关接口
*/
Route::group('recycle', function () {
    // 快递订单记录管理
    Route::get('express_order_record/lists', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@lists');
    Route::get('express_order_record/:id', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@info');
    Route::post('express_order_record', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@add');
    Route::put('express_order_record/:id', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@edit');
    Route::delete('express_order_record/:id', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@del');

    // 订单状态和信息更新
    Route::post('express_order_record/update_status', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@updateStatus');
    Route::post('express_order_record/update_actual_info', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@updateActualInfo');

    // 差异和统计
    Route::get('express_order_record/weight_diff_list', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@weightDiffList');
    Route::get('express_order_record/cost_diff_list', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@costDiffList');
    Route::get('express_order_record/statistics', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@statistics');

    // 关联查询
    Route::get('express_order_record/by_recycle_order', 'addon\recycle\app\adminapi\controller\express\ExpressOrderRecord@getByRecycleOrderId');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- express_order_record

// ✅  USER_CODE_BEGIN -- recycle_quotation
/**
 * 爬虫报价相关接口
*/

Route::group('recycle', function () {
    // 报价单配置管理
    Route::get('quotation_config', 'addon\recycle\app\adminapi\controller\quotation\QuotationConfig@lists');
    Route::get('quotation_config/:id', 'addon\recycle\app\adminapi\controller\quotation\QuotationConfig@info');
    Route::post('quotation_config', 'addon\recycle\app\adminapi\controller\quotation\QuotationConfig@add');
    Route::put('quotation_config/:id', 'addon\recycle\app\adminapi\controller\quotation\QuotationConfig@edit');
    Route::delete('quotation_config/:id', 'addon\recycle\app\adminapi\controller\quotation\QuotationConfig@del');
    Route::put('quotation_config/:id/modify_status', 'addon\recycle\app\adminapi\controller\quotation\QuotationConfig@modifyStatus');
    
    // 报价请求管理
    Route::get('quotation_request', 'addon\recycle\app\adminapi\controller\quotation\QuotationRequest@lists');
    Route::get('quotation_request/:id', 'addon\recycle\app\adminapi\controller\quotation\QuotationRequest@info');
    Route::post('quotation_request/send_request', 'addon\recycle\app\adminapi\controller\quotation\QuotationRequest@sendRequest');
    
    // 报价数据管理
    Route::get('quotation_data', 'addon\recycle\app\adminapi\controller\quotation\QuotationData@lists');
    Route::get('quotation_data/all', 'addon\recycle\app\adminapi\controller\quotation\QuotationData@getAll');
    Route::get('quotation_data/:id', 'addon\recycle\app\adminapi\controller\quotation\QuotationData@info');
    Route::get('quotation_data/cascade_options', 'addon\recycle\app\adminapi\controller\quotation\QuotationData@getCascadeOptions');
    
    // 价格配置管理
    Route::get('quotation_price_config', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@lists');
    Route::get('quotation_price_config/:id', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@info');
    Route::post('quotation_price_config', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@add');
    Route::put('quotation_price_config/:id', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@edit');
    Route::delete('quotation_price_config/:id', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@del');
    Route::put('quotation_price_config/:id/modify_status', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@modifyStatus');
    Route::post('quotation_price_config/batch_add_sku', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@batchAddSku');
    Route::post('quotation_price_config/batch_del', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@batchDel');
    Route::post('quotation_price_config/clear_all', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@clearAll');
    Route::put('quotation_price_config/:id/modify_status', 'addon\recycle\app\adminapi\controller\quotation\QuotationPriceConfig@modifyStatus');

    // 扣费配置
    Route::get('deduction_config/pages', 'addon\recycle\app\adminapi\controller\quotation\DeductionConfig@pages');
    Route::get('deduction_config/lists', 'addon\recycle\app\adminapi\controller\quotation\DeductionConfig@lists');
    Route::get('deduction_config/:id', 'addon\recycle\app\adminapi\controller\quotation\DeductionConfig@info');
    Route::post('deduction_config', 'addon\recycle\app\adminapi\controller\quotation\DeductionConfig@add');
    Route::put('deduction_config/:id', 'addon\recycle\app\adminapi\controller\quotation\DeductionConfig@edit');
    Route::delete('deduction_config/:id', 'addon\recycle\app\adminapi\controller\quotation\DeductionConfig@del');
    Route::put('deduction_config/modify_status', 'addon\recycle\app\adminapi\controller\quotation\DeductionConfig@modifyStatus');

    // 报价型号
    Route::get('quotation_model/lists', 'addon\recycle\app\adminapi\controller\quotation\QuotationModel@lists');

    // 规格管理
    // 型号管理
    Route::get('quotation_spec/model/lists', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@modelLists');
    Route::put('quotation_spec/model/:id/sync_status', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@setModelSyncStatus');
    Route::post('quotation_spec/model/batch_sync_status', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@batchSetModelSyncStatus');
    // 内存管理
    Route::get('quotation_spec/capacity/lists', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@capacityLists');
    Route::put('quotation_spec/capacity/:id/sync_status', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@setCapacitySyncStatus');
    Route::post('quotation_spec/capacity/batch_sync_status', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@batchSetCapacitySyncStatus');
    // 等级规格管理
    Route::get('quotation_spec/grade_spec/lists', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@gradeSpecLists');
    Route::put('quotation_spec/grade_spec/:id/sync_status', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@setGradeSpecSyncStatus');
    Route::post('quotation_spec/grade_spec/batch_sync_status', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@batchSetGradeSpecSyncStatus');
    // 同步统计
    Route::get('quotation_spec/sync_stats', 'addon\recycle\app\adminapi\controller\quotation\QuotationSpec@getSyncStats');


})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_quotation


// yisu 