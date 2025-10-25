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

// 概况页面路由
Route::group('recycle', function () {
    // 概况页面
    Route::get('recycle_overview', 'addon\recycle\app\adminapi\controller\Stats@getDashboardStats');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);

// USER_CODE_BEGIN -- recycle_recycle_category
Route::group('recycle', function () {
    //二手机分类列表
    Route::get('recycle_category', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleCategory@lists');
    //二手机分类详情
    Route::get('recycle_category/:id', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleCategory@info');
    //添加二手机分类
    Route::post('recycle_category', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleCategory@add');
    //编辑二手机分类
    Route::put('recycle_category/:id', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleCategory@edit');
    //删除二手机分类
    Route::delete('recycle_category/:id', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleCategory@del');
    //二手机分类列表
    Route::get('recycle_category_tree', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleCategory@tree');
    //编辑商品分类
    Route::post('recycle_category/category/update', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleCategory@updateCategory');
})->middleware([
    AdminCheckToken::class,
   
    AdminLog::class
]);



// USER_CODE_BEGIN -- recycle_recycle_banner
Route::group('recycle', function () {
    // Banner管理
    Route::get('recycle_banner', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleBanner@lists');
    Route::get('recycle_banner/:id', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleBanner@info');
    Route::post('recycle_banner', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleBanner@add');
    Route::put('recycle_banner/:id', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleBanner@edit');
    Route::delete('recycle_banner/:id', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleBanner@del');
    Route::put('recycle_banner/change_sort/:id/:sort', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleBanner@changeSort');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_recycle_banner


// USER_CODE_BEGIN -- recycle_recycle_order
Route::group('recycle', function () {

    // 订单基础操作
   // recycle_return_order/1/device_info
   Route::get('recycle_order/staff_count', 'addon\recycle\app\adminapi\controller\Stats@inspectorPerformance');
   // priceConfirmerPerformance
   Route::get('recycle_order/price_confirmer_performance', 'addon\recycle\app\adminapi\controller\Stats@priceConfirmerPerformance');

    // 代下单
    Route::post('recycle_order/create', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@create');
    Route::get('recycle_order/lists', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@lists');
    Route::get('recycle_order/detail/:id', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@detail');
    Route::get('recycle_order/:id/devices', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@devices');
    // 订单状态操作
    Route::put('/recycle_order/:id', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@update');
    Route::delete('recycle_order/:id', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@delete');
     // 查询手机imei的信息
     Route::get('recycle_device/imei_info/:imei', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@getImeiInfo');
     // 设备信息打印 printer/print_device_label
     Route::post('printer/print_device_label/:id', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@printDeviceLabel');

    // 设备管理
    Route::get('recycle_device/:id', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@getInfo');
    Route::post('recycle_device', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@add');

   // confirmPrice recycle_order/device
   Route::put('recycle_device/:id/confirm_price', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@confirmPrice');
    Route::put('recycle_device/:id', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@update');
    Route::delete('recycle_device/:id', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@delete');
    // 批量设备操作
    Route::post('recycle_device/batch_update_status', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@batchUpdateStatus');
    Route::post('recycle_device/batch_recycle', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@batchRecycle');
    Route::post('recycle_device/batch_return', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@batchReturn');
    // 设备质检流程
    Route::put('recycle_device/:id/start_check', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@startCheck');
    Route::put('recycle_device/:id/complete_check', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleDevice@completeCheck');
    // 获取订单及设备的状态信息
    Route::get('recycle_order/status', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@getStatus');
    // 获取商户的收款信息
    // recycle_order/merchant_pay_info
    Route::get('recycle_order/merchant_pay_info/:id', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@getMerchantPayInfo');
    // payment_confirm 财务确认打款
    Route::put('recycle_order/:id/payment_confirm', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@paymentConfirm');
    
    // 推送订单确认通知
    Route::post('recycle_order/:id/push_notify', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@pushNotify');
    
    // 订单设备管理路由
    Route::post('recycle_order/:id/add_device', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@addDevice');
    Route::post('recycle_order/:id/batch_add_devices', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@batchAddDevices');
    Route::delete('recycle_order/:id/device/:device_id', 'addon\recycle\app\adminapi\controller\recycle_order\RecycleOrder@removeDevice');

     //商家地址库列表
     Route::get('shop_address', 'addon\recycle\app\adminapi\controller\shop_address\ShopAddress@lists');

     //商家地址库详情
     Route::get('shop_address/:id', 'addon\recycle\app\adminapi\controller\shop_address\ShopAddress@info');
 
     //添加商家地址库
     Route::post('shop_address', 'addon\recycle\app\adminapi\controller\shop_address\ShopAddress@add');
 
     //编辑商家地址库
     Route::put('shop_address/:id', 'addon\recycle\app\adminapi\controller\shop_address\ShopAddress@edit');
 
     //删除商家地址库
     Route::delete('shop_address/:id', 'addon\recycle\app\adminapi\controller\shop_address\ShopAddress@del');
 
     // 默认发货地址
     Route::get('shop_address/default/delivery', 'addon\recycle\app\adminapi\controller\shop_address\ShopAddress@defaultDelivery');
 
     //获取商家收货地址库
    //  Route::get('order/refund/address', 'addon\recycle\app\adminapi\controller\shop_address\ShopAddress@getList');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_recycle_order

// USER_CODE_BEGIN -- recycle_recycle_return_order
Route::group('recycle', function () {
    // 退回订单基础操作
    Route::get('recycle_return_order/lists', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@lists');

    Route::post('recycle_return_order', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@create');
    Route::post('recycle_return_order/batch', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@batchCreate');
    
    // 退回订单状态操作
    Route::put('recycle_return_order/:id/status', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@updateStatus');
    Route::put('recycle_return_order/:id/confirm', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@confirm');
    Route::put('recycle_return_order/:id/cancel', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@cancel');
    Route::delete('recycle_return_order/:id', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@delete');
    
    // 退回订单状态信息
    Route::get('recycle_return_order/status', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@getStatus');
    Route::get('recycle_return_order/status_list', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@getStatusList');
    Route::get('recycle_return_order/:id/device_info', 'addon\recycle\app\adminapi\controller\recycle_return_order\RecycleReturnOrder@detail');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_recycle_return_order


// USER_CODE_BEGIN -- recycle_user_address

Route::group('recycle', function () {

    //用户退货地址列表
    Route::get('recycle_user_address', 'addon\recycle\app\adminapi\controller\recycle_user_address\RecycleUserAddress@lists');
    //用户退货地址详情
    Route::get('recycle_user_address/:id', 'addon\recycle\app\adminapi\controller\recycle_user_address\RecycleUserAddress@info');
    //添加用户退货地址
    Route::post('recycle_user_address', 'addon\recycle\app\adminapi\controller\recycle_user_address\RecycleUserAddress@add');
    //编辑用户退货地址
    Route::put('recycle_user_address/:id', 'addon\recycle\app\adminapi\controller\recycle_user_address\RecycleUserAddress@edit');
    //删除用户退货地址
    Route::delete('recycle_user_address/:id', 'addon\recycle\app\adminapi\controller\recycle_user_address\RecycleUserAddress@del');
    
    Route::get('member_all','addon\recycle\app\adminapi\controller\recycle_user_address\RecycleUserAddress@getMemberAll');

   
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_user_address

// USER_CODE_BEGIN -- recycle_excel_import
Route::group('recycle', function () {
    // Excel导入管理
    Route::post('excel_import/upload', 'addon\recycle\app\adminapi\controller\ExcelImport@upload');
    Route::get('excel_import/history', 'addon\recycle\app\adminapi\controller\ExcelImport@history');
    Route::get('excel_import/detail/:id', 'addon\recycle\app\adminapi\controller\ExcelImport@detail');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_excel_import

// USER_CODE_BEGIN -- recycle_device_export
Route::group('recycle', function () {
    // 设备导出管理
    Route::get('device_export/list', 'addon\recycle\app\adminapi\controller\DeviceExportController@list');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_device_export

// Excel数据管理路由
Route::group('recycle', function () {
    // Excel数据列表
    Route::get('excel/lists', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@lists');
    Route::get('excel/brands', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@getBrandList');
    Route::get('excel/statistics', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@getStatistics');
    Route::put('excel/price', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@updatePrice');
    Route::delete('excel/batch', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@batchDelete');
    
    // Excel文件上传和导入
    Route::post('excel/upload', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@uploadExcel');
    Route::post('excel/parse', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@parseExcel');
    Route::post('excel/preview', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@previewImport');
    Route::post('excel/import', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@importData');
    Route::get('excel/template', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@downloadTemplate');
    Route::get('excel/history', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@getImportHistory');
    Route::get('excel/export', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@exportData');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);

// USER_CODE_BEGIN -- recycle_printer
Route::group('recycle', function () {
    // 打印机管理
    // 获取打印机品牌列表
    Route::get('printer/brand_list', 'addon\recycle\app\adminapi\controller\printer\Printer@getBrandList');
    // 获取打印机列表
    Route::get('printer/lists', 'addon\recycle\app\adminapi\controller\printer\Printer@lists');
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
    // 测试打印模板
    Route::post('printer_template/test_print/:id', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@testPrint');
    // 获取模板类型列表
    Route::get('printer_template/type_list', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@getTypeList');
    // 获取默认模板
    Route::get('printer_template/default', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@getDefaultTemplate');
    // 测试JSON格式
    Route::post('printer_template/test_json_format', 'addon\recycle\app\adminapi\controller\printer\PrinterTemplate@testJsonFormat');
    // 调试变量映射
    // 获取可用变量列表
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_printer

// USER_CODE_BEGIN -- recycle_stats
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

// USER_CODE_BEGIN -- device_query
Route::group('recycle', function () {
    // 设备查询配置管理
    Route::get('device_query_config/lists', 'addon\recycle\app\adminapi\controller\DeviceQueryConfigController@lists');
    Route::get('device_query_config/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryConfigController@info');
    Route::post('device_query_config', 'addon\recycle\app\adminapi\controller\DeviceQueryConfigController@add');
    Route::put('device_query_config/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryConfigController@edit');
    Route::delete('device_query_config/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryConfigController@del');
    Route::put('device_query_config/status/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryConfigController@modifyStatus');
    Route::post('device_query_config/test/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryConfigController@testConnection');
    Route::get('device_query_config/stats/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryConfigController@getStats');

    // API接口清单管理
    Route::get('device_query_api/lists', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@lists');
    Route::get('device_query_api/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@info');
    Route::post('device_query_api', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@add');
    Route::put('device_query_api/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@edit');
    Route::delete('device_query_api/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@del');
    Route::put('device_query_api/status/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@modifyStatus');
    Route::get('device_query_api/default', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@getDefaultApiList');
    Route::post('device_query_api/init', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@initDefaultApiList');
    Route::get('device_query_api/by_endpoint', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@getApiByEndpoint');
    Route::get('device_query_api/by_category', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@getApisByCategory');
    // 获取设备的基本信息 coverage
    Route::get('device_query_api/coverage', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@getCoverage');
    // 获取设备的激活锁 activationlock
    Route::get('device_query_api/activationlock', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@getActivationlock');
    // 获取设备的mdm 监管锁 mdm
    Route::get('device_query_api/mdm', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@getMdm');
    Route::get('device_query_api/express', 'addon\recycle\app\adminapi\controller\DeviceQueryApiController@getExpress');
    // 查询结果管理
    Route::get('device_query_result/lists', 'addon\recycle\app\adminapi\controller\DeviceQueryResultController@lists');
    Route::get('device_query_result/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryResultController@info');
    Route::delete('device_query_result/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryResultController@del');
    Route::post('device_query_result/batch_del', 'addon\recycle\app\adminapi\controller\DeviceQueryResultController@batchDel');
    Route::get('device_query_result/stats', 'addon\recycle\app\adminapi\controller\DeviceQueryResultController@getStats');
    Route::post('device_query_result/clean_cache', 'addon\recycle\app\adminapi\controller\DeviceQueryResultController@cleanCache');
    Route::post('device_query_result/requery/:id', 'addon\recycle\app\adminapi\controller\DeviceQueryResultController@requery');
    Route::post('device_query_result/export', 'addon\recycle\app\adminapi\controller\DeviceQueryResultController@export');
    Route::get('device_query_result/total_consumption', 'addon\recycle\app\adminapi\controller\DeviceQueryResultController@getTotalConsumption');
    // Excel数据管理
    Route::get('excel_data/device_list', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@deviceList');
    Route::get('excel_data/:id', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@info');
    Route::get('excel_data/brand_list', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@brands');
    Route::get('excel_data/statistics', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@statistics');
    Route::get('excel_data/records', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@records');
    Route::get('excel_data/search', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@search');
    Route::get('excel_data/export', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@export');
    Route::get('excel_data/chart', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@chart');
    Route::post('excel_data/batch_del', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@batchDel');
    Route::post('excel_data/update_price', 'addon\recycle\app\adminapi\controller\recycle_category\RecycleExcelController@updatePrice');

    // 回收设备品牌列表
    Route::get('recycle_device_brand', 'addon\recycle\app\adminapi\controller\recycle_device_brand\RecycleDeviceBrand@lists');
    Route::get('recycle_device_brand/:id', 'addon\recycle\app\adminapi\controller\recycle_device_brand\RecycleDeviceBrand@info');
    Route::post('recycle_device_brand', 'addon\recycle\app\adminapi\controller\recycle_device_brand\RecycleDeviceBrand@add');
    Route::put('recycle_device_brand/:id', 'addon\recycle\app\adminapi\controller\recycle_device_brand\RecycleDeviceBrand@edit');
    Route::delete('recycle_device_brand/:id', 'addon\recycle\app\adminapi\controller\recycle_device_brand\RecycleDeviceBrand@del');

    // 设备型号列表
    Route::get('recycle_device_model', 'addon\recycle\app\adminapi\controller\recycle_device_model\RecycleDeviceModel@lists');
    Route::get('recycle_device_model/:id', 'addon\recycle\app\adminapi\controller\recycle_device_model\RecycleDeviceModel@info');
    Route::post('recycle_device_model', 'addon\recycle\app\adminapi\controller\recycle_device_model\RecycleDeviceModel@add');
    Route::put('recycle_device_model/:id', 'addon\recycle\app\adminapi\controller\recycle_device_model\RecycleDeviceModel@edit');
    Route::delete('recycle_device_model/:id', 'addon\recycle\app\adminapi\controller\recycle_device_model\RecycleDeviceModel@del');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_excel_data

// USER_CODE_BEGIN -- recycle_price_image
Route::group('recycle', function () {
    // 报价单图片生成
    // 环境检查
    Route::get('price_image/check_environment', 'addon\recycle\app\adminapi\controller\price_image\PriceImageController@checkEnvironment');
    
    // 生成图片
    Route::post('price_image/generate', 'addon\recycle\app\adminapi\controller\price_image\PriceImageController@generate');
    
    // 批量生成图片
    Route::post('price_image/batch_generate', 'addon\recycle\app\adminapi\controller\price_image\PriceImageController@batchGenerate');
    
    // 预览图片(base64)
    Route::post('price_image/preview', 'addon\recycle\app\adminapi\controller\price_image\PriceImageController@preview');
    
    // 下载图片
    Route::get('price_image/download/:filename', 'addon\recycle\app\adminapi\controller\price_image\PriceImageController@download');
    
    // 获取历史记录
    Route::get('price_image/history', 'addon\recycle\app\adminapi\controller\price_image\PriceImageController@getHistory');
    
    // 清理临时文件
    Route::post('price_image/clean_temp', 'addon\recycle\app\adminapi\controller\price_image\PriceImageController@cleanTemp');
    
    // 测试数据映射
    Route::get('price_image/test_mapping', 'addon\recycle\app\adminapi\controller\price_image\PriceImageController@testMapping');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_price_image

