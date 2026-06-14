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

/**
 * 店铺移动管理端
 */
Route::group('adminapp', function () {
    // 首页应用
    Route::get('site/apps_of_index', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Apps@getAppsOfIndex');
    // 设置首页应用
    Route::post('site/apps_of_index', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Apps@setAppsOfIndex');
    // 全部应用
    Route::get('site/apps', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Apps@getApps');
    // 个人中心应用
    Route::get('site/apps_of_user_center', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Apps@getAppOfUserCenter');
    // 底部导航
    Route::get('site/navs', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Apps@getBottomNav');

    // 待办
    Route::get('site/todo', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Index@getTodoList');
    // 全部待办
    Route::get('site/todo_of_all', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Index@getAllTodoList');
    // 设置待办
    Route::post('site/todo', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Index@setTodoList');

    // 统计
    Route::get('site/stat', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Index@getStatList');
    // 全部统计
    Route::get('site/stat_of_all', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Index@getAllStatList');
    // 设置统计
    Route::post('site/stat', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Index@setStatList');

    // 附件配置
    Route::get('site/attachment_config', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Attachment@getConfig');
    // 附件分类列表
    Route::get('site/attachment_category_list', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Attachment@categoryLists');
    // 附件列表
    Route::get('site/attachment_list', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Attachment@lists');
    // 上传Base64图片
    Route::post('site/upload_image_base64', 'addon\hsx_recycle\app\adminapi\controller\adminapp\site\Attachment@uploadImageBase64');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);

// ✅ 概况页面路由
Route::group('recycle', function () {
    // 概况页面
    Route::get('recycle_overview', 'addon\hsx_recycle\app\adminapi\controller\Stats@getDashboardStats');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);

// 回收首页配置
Route::group('recycle', function () {
    Route::get('dashboard/overview', 'addon\hsx_recycle\app\adminapi\controller\dashboard\RecycleDashboard@overview');
    Route::get('dashboard/trend', 'addon\hsx_recycle\app\adminapi\controller\dashboard\RecycleDashboard@trend');
    Route::get('dashboard/metrics', 'addon\hsx_recycle\app\adminapi\controller\dashboard\RecycleDashboard@metrics');
    Route::get('dashboard/filters', 'addon\hsx_recycle\app\adminapi\controller\dashboard\RecycleDashboard@filters');
    Route::get('dashboard/device_progress_groups', 'addon\hsx_recycle\app\adminapi\controller\dashboard\RecycleDashboard@deviceProgressGroups');
    Route::get('dashboard/widgets', 'addon\hsx_recycle\app\adminapi\controller\dashboard\DashboardConfig@widgets');
    Route::post('dashboard/widgets', 'addon\hsx_recycle\app\adminapi\controller\dashboard\DashboardConfig@save');
    Route::get('dashboard/visible', 'addon\hsx_recycle\app\adminapi\controller\dashboard\DashboardConfig@visible');
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
    Route::get('recycle_category', 'addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory@lists');
    //二手机分类详情
    Route::get('recycle_category/:id', 'addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory@info');
    //添加二手机分类
    Route::post('recycle_category', 'addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory@add');
    //编辑二手机分类
    Route::put('recycle_category/:id', 'addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory@edit');
    //删除二手机分类
    Route::delete('recycle_category/:id', 'addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory@del');
    //二手机分类列表
    Route::get('recycle_category_tree', 'addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory@tree');
    //编辑商品分类
    Route::post('recycle_category/category/update', 'addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory@updateCategory');
    //报价单历史列表
    Route::get('recycle_category/quote_history', 'addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory@quoteHistory');
    //单分类报价单历史
    Route::get('recycle_category/:id/quote_history', 'addon\hsx_recycle\app\adminapi\controller\category\RecycleCategory@quoteHistoryByCategory');
})->middleware([
    AdminCheckToken::class,
    AdminLog::class
]);

//  ✅ USER_CODE_BEGIN -- recycle_recycle_order
/**
 * 核心业务 ： 订单管理 
 * */ 
Route::group('recycle', function () {

    // 下单配置
    Route::get('order_submit_config', 'addon\hsx_recycle\app\adminapi\controller\order\OrderSubmitConfig@info');
    Route::post('order_submit_config', 'addon\hsx_recycle\app\adminapi\controller\order\OrderSubmitConfig@save');

    // 订单基础操作
    // 获取审核员绩效
   Route::get('recycle_order/staff_count', 'addon\hsx_recycle\app\adminapi\controller\Stats@inspectorPerformance');
    Route::get('recycle_order/price_confirmer_performance', 'addon\hsx_recycle\app\adminapi\controller\Stats@priceConfirmerPerformance');

    // 代下单
    Route::post('recycle_order/create', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@create');
    Route::get('recycle_order/lists', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@lists');
    Route::get('recycle_order/detail/:id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@detail');
    Route::get('recycle_order/:id/devices', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@devices');


    // 订单状态操作
    Route::put('/recycle_order/:id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@update');
    Route::delete('recycle_order/:id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@delete');
     // 查询手机imei的信息
    Route::get('recycle_device/imei_info/:imei', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@getImeiInfo');
    Route::get('recycle_device/scan_search', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@scanSearch');
    Route::get('recycle_device/refurbishment_options', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@refurbishmentOptions');
    Route::get('recycle_device/sale_destination_options', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@saleDestinationOptions');
    Route::get('recycle_device/refurbishment_assignee_options', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@refurbishmentAssigneeOptions');
    // 设备管理
    Route::get('recycle_device/:id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@getInfo');
    Route::get('recycle_device/:id/cost_adjust_ability', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@costAdjustAbility');
    Route::get('recycle_device/:id/cost_adjust_logs', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@costAdjustLogs');
    Route::post('recycle_device/:id/cost_adjust', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@costAdjust');
    Route::post('recycle_device', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@add');
    // 回收设备型号字典
    Route::get('recycle_device_model_dict/options', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@options');
    Route::get('recycle_device_model_dict/children', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@children');
    Route::get('recycle_device_model_dict/tree', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@tree');
    Route::get('recycle_device_model_dict', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@lists');
    Route::post('recycle_device_model_dict/quick_add', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@quickAdd');
    Route::post('recycle_device_model_dict/external_import', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@externalImport');
    Route::post('recycle_device_model_dict/sort/update', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@updateSort');
    Route::post('recycle_device_model_dict', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@add');
    Route::put('recycle_device_model_dict/:id', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@edit');
    Route::delete('recycle_device_model_dict/:id', 'addon\hsx_recycle\app\adminapi\controller\device\RecycleDeviceModelDict@del');
    Route::get('template_binding/info', 'addon\hsx_recycle\app\adminapi\controller\template\RecycleTemplateBinding@info');
    Route::get('template_binding/resolve_device', 'addon\hsx_recycle\app\adminapi\controller\template\RecycleTemplateBinding@resolveDevice');
    Route::post('template_binding/save', 'addon\hsx_recycle\app\adminapi\controller\template\RecycleTemplateBinding@save');
    Route::post('template_binding/reset', 'addon\hsx_recycle\app\adminapi\controller\template\RecycleTemplateBinding@reset');

    // 确认设备价格
    Route::put('recycle_device/:id/confirm_price', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@confirmPrice');
    Route::post('recycle_device/:id/transfer_consignment', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@transferDevice');
    Route::put('recycle_device/:id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@update');
    Route::delete('recycle_device/:id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@delete');
    // 批量设备操作
    Route::post('recycle_device/batch_update_status', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@batchUpdateStatus');
    Route::post('recycle_device/batch_recycle', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@batchRecycle');
    Route::post('recycle_device/batch_return', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@batchReturn');
    // 设备质检流程
    Route::put('recycle_device/:id/start_check', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@startCheck');
    Route::put('recycle_device/:id/complete_check', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleDevice@completeCheck');
    // 获取订单及设备的状态信息
    Route::get('recycle_order/status', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@getStatus');
    Route::get('recycle_order/business_stage_options', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@getBusinessStageOptions');
    // 获取商户的收款信息
    Route::get('recycle_order/merchant_pay_info/:id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@getMerchantPayInfo');
    // 财务确认打款
    Route::put('recycle_order/:id/payment_confirm', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@paymentConfirm');
    Route::post('recycle_order/:id/device_payment_confirm', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@devicePaymentConfirm');
    Route::get('recycle_order/:id/device_payment_logs', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@devicePaymentLogs');
    Route::get('recycle_order/:id/notice_logs', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@noticeLogs');
    Route::post('recycle_order/:id/device_confirm', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@deviceConfirm');
    
    // 推送订单确认通知
    Route::post('recycle_order/:id/push_notify', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@pushNotify');
    
    // 订单设备管理路由
    Route::post('recycle_order/:id/add_device', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@addDevice');
    Route::post('recycle_order/:id/batch_add_devices', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@batchAddDevices');
    Route::delete('recycle_order/:id/device/:device_id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleOrder@removeDevice');

     //商家地址库列表
     Route::get('shop_address', 'addon\hsx_recycle\app\adminapi\controller\address\ShopAddress@lists');

     //商家地址库详情
     Route::get('shop_address/:id', 'addon\hsx_recycle\app\adminapi\controller\address\ShopAddress@info');
 
     //添加商家地址库
     Route::post('shop_address', 'addon\hsx_recycle\app\adminapi\controller\address\ShopAddress@add');
 
     //编辑商家地址库
     Route::put('shop_address/:id', 'addon\hsx_recycle\app\adminapi\controller\address\ShopAddress@edit');
 
     //删除商家地址库
     Route::delete('shop_address/:id', 'addon\hsx_recycle\app\adminapi\controller\address\ShopAddress@del');
 
     // 默认发货地址
     Route::get('shop_address/default/delivery', 'addon\hsx_recycle\app\adminapi\controller\address\ShopAddress@defaultDelivery');
 
     //获取商家收货地址库
    //  Route::get('order/refund/address', 'addon\hsx_recycle\app\adminapi\controller\address\ShopAddress@getList');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_recycle_order

// 代卖订单
Route::group('recycle', function () {
    Route::get('consignment_order/lists', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@lists');
    Route::get('consignment_order/status', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@status');
    Route::get('consignment_order/:id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@info');
    Route::get('consignment_order/:id/logs', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@logs');
    Route::put('consignment_order/:id/listing', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@listing');
    Route::put('consignment_order/:id/sold', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@sold');
    Route::put('consignment_order/:id/settle', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@settle');
    Route::put('consignment_order/:id/close', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@close');
    Route::post('consignment_order/:id/push_notify', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleConsignmentOrder@pushNotify');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);

// ✅ USER_CODE_BEGIN -- recycle_recycle_return_order
/**
 * 退回订单相关接口
*/
Route::group('recycle', function () {
    // 退回订单基础操作
    Route::get('recycle_return_order/lists', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@lists');

    Route::post('recycle_return_order', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@create');
    Route::post('recycle_return_order/batch', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@batchCreate');
    
    // 退回订单状态操作
    Route::put('recycle_return_order/:id/status', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@updateStatus');
    Route::put('recycle_return_order/:id/confirm', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@confirm');
    Route::put('recycle_return_order/:id/cancel', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@cancel');
    Route::delete('recycle_return_order/:id', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@delete');
    
    // 退回订单状态信息
    Route::get('recycle_return_order/status', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@getStatus');
    Route::get('recycle_return_order/status_list', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@getStatusList');
    Route::get('recycle_return_order/shipment_modes', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@getShipmentModeList');
    Route::get('recycle_return_order/:id/device_info', 'addon\hsx_recycle\app\adminapi\controller\order\RecycleReturnOrder@detail');
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
    Route::get('recycle_user_address', 'addon\hsx_recycle\app\adminapi\controller\address\RecycleUserAddress@lists');
    //用户退货地址详情
    Route::get('recycle_user_address/:id', 'addon\hsx_recycle\app\adminapi\controller\address\RecycleUserAddress@info');
    //添加用户退货地址
    Route::post('recycle_user_address', 'addon\hsx_recycle\app\adminapi\controller\address\RecycleUserAddress@add');
    //编辑用户退货地址
    Route::put('recycle_user_address/:id', 'addon\hsx_recycle\app\adminapi\controller\address\RecycleUserAddress@edit');
    //删除用户退货地址
    Route::delete('recycle_user_address/:id', 'addon\hsx_recycle\app\adminapi\controller\address\RecycleUserAddress@del');
    
    Route::get('member_all','addon\hsx_recycle\app\adminapi\controller\address\RecycleUserAddress@getMemberAll');

   
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
    Route::get('device_export/list', 'addon\hsx_recycle\app\adminapi\controller\device\export\DeviceExportController@list');
    Route::post('device_export/sync_erp', 'addon\hsx_recycle\app\adminapi\controller\device\export\DeviceExportController@syncErp');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_device_export

// ✅ USER_CODE_BEGIN -- recycle_check_template
/**
 * 质检模板配置
 */
Route::group('recycle', function () {
    Route::get('check_template/pages', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@pages');
    Route::get('check_template/all', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@all');
    Route::get('check_template/schema', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@schema');
    Route::post('check_template/init_default', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@initDefault');
    Route::get('check_template/:id', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@info');
    Route::post('check_template', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@add');
    Route::put('check_template/:id', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@edit');
    Route::delete('check_template/:id', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@del');
    Route::put('check_template/:id/default', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@setDefault');

    Route::get('check_template_group', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@groups');
    Route::post('check_template_group', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@saveGroup');
    Route::delete('check_template_group/:id', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@deleteGroup');

    Route::get('check_template_field', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@fields');
    Route::post('check_template_field', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@saveField');
    Route::delete('check_template_field/:id', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@deleteField');

    Route::post('check_template_option', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@saveOption');
    Route::post('check_template_option/:id/default', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@setOptionDefault');
    Route::delete('check_template_option/:id', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckTemplate@deleteOption');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_check_template

// ✅ USER_CODE_BEGIN -- recycle_check_catalog
/**
 * 质检检测目录(扁平) + 选项级别字典
 */
Route::group('recycle', function () {
    Route::post('check_catalog/import', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckCatalog@import');
    Route::get('check_catalog/lists', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckCatalog@lists');
    Route::get('check_catalog/by_model', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckCatalog@byModel');
    Route::get('check_catalog/batches', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckCatalog@batches');
    Route::get('check_catalog/severity', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckCatalog@severityLists');
    Route::post('check_catalog/severity/:id', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckCatalog@severitySet');
    Route::post('check_catalog/severity_batch', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckCatalog@severityBatchSet');
    Route::post('check_catalog/severity_keyword', 'addon\hsx_recycle\app\adminapi\controller\check\RecycleCheckCatalog@severityByKeyword');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_check_catalog

// ✅ USER_CODE_BEGIN -- recycle_printer
/**
 * 打印机相关接口
*/

Route::group('recycle', function () {
    // 打印机管理
    // 获取打印机品牌列表
    Route::get('printer/brand_list', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@getBrandList');
    // 获取打印机列表
    Route::get('printer/lists', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@lists');
    // 批量查询打印机状态
    Route::post('printer/batch_status', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@batchQueryStatus');
    // 获取打印机详情
    Route::get('printer/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@info');
    // 添加打印机
    Route::post('printer', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@add');
    // 更新打印机
    Route::put('printer/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@edit');
    // 删除打印机
    Route::delete('printer/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@del');
    // 切换打印机状态
    Route::post('printer/user/toggle/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@toggleStatus');
    // 查询打印机状态
    Route::get('printer/status/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@queryPrinterStatus');
    
    // 获取用户绑定的打印机
    Route::get('printer/user', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@getUserPrinter');
    // 绑定打印机
    Route::post('printer/bind', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@bindPrinter');
    // 解绑打印机
    Route::post('printer/unbind', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@unbindPrinter');
    // 测试打印机
    Route::post('printer/test', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@testPrint');
    // 打印标签
    Route::post('printer/print_label', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@printLabel');
    // 获取设备标签打印计划
    Route::get('printer/print_device_label_plan/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@getDeviceLabelPrintPlan');
    // 打印设备标签
    Route::post('printer/print_device_label/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\Printer@printDeviceLabel');

    // 打印场景配置
    Route::get('print_scene/lists', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@lists');
    Route::get('print_scene/options', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@options');
    Route::get('print_scene/manual_actions', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@manualActions');
    Route::post('print_scene', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@add');
    Route::get('print_scene/:sceneKey/plan', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@plan');
    Route::post('print_scene/:sceneKey/print', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@print');
    Route::get('print_scene/:sceneKey', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@info');
    Route::put('print_scene/:sceneKey', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@edit');
    Route::delete('print_scene/:sceneKey', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@del');
    Route::post('print_scene/status/:sceneKey', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintScene@modifyStatus');

    // 打印日志
    Route::get('print_log/lists', 'addon\hsx_recycle\app\adminapi\controller\printer\PrintLog@lists');
    
    // 打印模板管理
    // 获取模板列表
    Route::get('printer_template/lists', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@lists');
    // 获取模板详情
    Route::get('printer_template/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@info');
    // 添加模板
    Route::post('printer_template', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@add');
    // 编辑模板
    Route::put('printer_template/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@edit');
    // 删除模板
    Route::delete('printer_template/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@del');
    // 修改模板状态
    Route::post('printer_template/status/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@modifyStatus');
    // 设置默认模板
    Route::post('printer_template/default/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@setDefault');
    // 预览模板
    Route::get('printer_template/preview/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@preview');
    // 验证模板数据
    Route::post('printer_template/validate', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@validateTemplate');
    // 验证XML格式
    Route::post('printer_template/validate_xml', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@validateXml');
    // 提取模板变量
    Route::post('printer_template/extract_variables', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@extractVariables');
    // 渲染模板
    Route::post('printer_template/render', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@render');
    // 测试打印模板
    Route::post('printer_template/test_print/:id', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@testPrint');
    // 获取模板类型列表
    Route::get('printer_template/type_list', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@getTypeList');
    // 获取打印模板变量列表
    Route::get('printer_template/variables', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@getVariables');
    // 获取默认模板
    Route::get('printer_template/default', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@getDefaultTemplate');
    // 获取设备打印数据
    Route::get('printer_template/device_print_data/:device_id', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@getDevicePrintData');
    // 打印设备标签
    Route::post('printer_template/print_device_label/:device_id', 'addon\hsx_recycle\app\adminapi\controller\printer\PrinterTemplate@printDeviceLabel');
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
    Route::get('stats/getTodayStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getTodayStats');
    // 获取用户统计数据
    Route::get('stats/getUserStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getUserStats');
    // 获取分类统计数据
    Route::get('stats/getCategoryStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getCategoryStats');
    // 获取签收统计数据
    Route::get('stats/getSignStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getSignStats');
    // 获取签收分类统计
    Route::get('stats/getSignCategoryStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getSignCategoryStats');
    // 获取普通用户签收统计
    Route::get('stats/getUserSignStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getUserSignStats');
    // 获取管理员概况统计
    Route::get('stats/getOverviewStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getOverviewStats');
    // 获取用户列表
    Route::get('stats/getUserList', 'addon\hsx_recycle\app\adminapi\controller\Stats@getUserList');
    // 获取用户详细统计
    Route::get('stats/getUserDetailStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getUserDetailStats');
    // 员工考核看板（计数+时效+金额）
    Route::get('stats/getStaffKpiBoard', 'addon\hsx_recycle\app\adminapi\controller\Stats@getStaffKpiBoard');
    // 获取排行榜数据
    Route::get('stats/getRankingStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getRankingStats');
    // 获取质检员分类统计
    Route::get('stats/getCheckerCategoryStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getCheckerCategoryStats');
    // 获取质检员今日工作量
    Route::get('stats/getCheckerTodayWork', 'addon\hsx_recycle\app\adminapi\controller\Stats@getCheckerTodayWork');
    // 获取统计概览
    Route::get('stats/getDashboardStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getDashboardStats');
    // 兼容旧接口
    Route::get('stats/inspectorPerformance', 'addon\hsx_recycle\app\adminapi\controller\Stats@inspectorPerformance');
    Route::get('stats/priceConfirmerPerformance', 'addon\hsx_recycle\app\adminapi\controller\Stats@priceConfirmerPerformance');
    // 会员统计接口
    Route::get('stats/getMemberStatsOverview', 'addon\hsx_recycle\app\adminapi\controller\Stats@getMemberStatsOverview');
    Route::get('stats/getMemberRegisterTrend', 'addon\hsx_recycle\app\adminapi\controller\Stats@getMemberRegisterTrend');
    Route::get('stats/getMemberChannelStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getMemberChannelStats');
    Route::get('stats/getMemberInviteRank', 'addon\hsx_recycle\app\adminapi\controller\Stats@getMemberInviteRank');
    Route::get('stats/getMemberActivityStats', 'addon\hsx_recycle\app\adminapi\controller\Stats@getMemberActivityStats');
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
    Route::get('device_query_config/lists', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@lists');
    Route::get('device_query_config/config', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@getConfig');
    Route::post('device_query_config/config', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@setConfig');
    Route::put('device_query_config/status/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@modifyStatus');
    Route::post('device_query_config/test/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@testConnection');
    Route::get('device_query_config/stats/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@getStats');
    Route::get('device_query_config/balance/:channelKey', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@balance');
    Route::get('device_query_config/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@info');
    Route::post('device_query_config', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@add');
    Route::put('device_query_config/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@edit');
    Route::delete('device_query_config/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryConfigController@del');

    // API接口清单管理
    Route::get('device_query_api/lists', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@lists');
    Route::get('device_query_api/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@info');
    Route::post('device_query_api', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@add');
    Route::put('device_query_api/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@edit');
    Route::delete('device_query_api/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@del');
    Route::put('device_query_api/status/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@modifyStatus');
    Route::get('device_query_api/default', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getDefaultApiList');
    Route::post('device_query_api/init', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@initDefaultApiList');
    Route::get('device_query_api/by_endpoint', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getApiByEndpoint');
    Route::get('device_query_api/by_category', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getApisByCategory');
    Route::post('device_query_api/query', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@query');
    // 获取设备的基本信息 coverage
    Route::get('device_query_api/coverage', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getCoverage');
    // 获取设备的激活锁 activationlock
    Route::get('device_query_api/activationlock', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getActivationlock');
    // 获取设备的mdm 监管锁 mdm
    Route::get('device_query_api/mdm', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getMdm');
    Route::get('device_query_api/express', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryApiController@getExpress');
    // 查询结果管理
    Route::get('device_query_result/lists', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@lists');
    Route::get('device_query_result/overview', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@overview');
    Route::get('device_query_result/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@info');
    Route::delete('device_query_result/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@del');
    Route::post('device_query_result/batch_del', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@batchDel');
    Route::get('device_query_result/stats', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@getStats');
    Route::post('device_query_result/clean_cache', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@cleanCache');
    Route::post('device_query_result/requery/:id', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@requery');
    Route::post('device_query_result/export', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@export');
    Route::get('device_query_result/total_consumption', 'addon\hsx_recycle\app\adminapi\controller\device_query\DeviceQueryResultController@getTotalConsumption');
    
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
    // 第三方配置中心（sys_config）
    Route::get('third_party_config/overview', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyConfig@overview');
    Route::get('third_party_config', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyConfig@getConfig');
    Route::post('third_party_config', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyConfig@setConfig');
    Route::get('third_party_config/default', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyConfig@getDefaultConfig');
    Route::post('third_party/address_parse', 'addon\hsx_recycle\app\adminapi\controller\third_party\AddressParse@parse');

    // 第三方服务配置管理
    Route::get('third_party_service/lists', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyService@lists');
    Route::get('third_party_service/:id', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyService@info');
    Route::post('third_party_service', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyService@add');
    Route::put('third_party_service/:id', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyService@edit');
    Route::delete('third_party_service/:id', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyService@del');
    Route::put('third_party_service/status/:id', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyService@modifyStatus');

    // API调用日志管理
    Route::get('third_party_api_log/lists', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyApiLog@lists');
    Route::get('third_party_api_log/:id', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyApiLog@info');
    Route::delete('third_party_api_log/clean', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyApiLog@clean');

    // 费用统计管理
    Route::get('third_party_cost_stats/lists', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyCostStats@lists');
    Route::post('third_party_cost_stats/rebuild', 'addon\hsx_recycle\app\adminapi\controller\third_party\ThirdPartyCostStats@rebuild');
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
    Route::post('express_order/quote', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@quote');
    Route::post('express_order/create', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@create');
    Route::post('express_order/cancel', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@cancel');
    Route::get('express_order/track', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@track');
    Route::get('express_order/detail', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@detail');
    Route::post('express_order/modify', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@modify');
    Route::post('express_order/waybill_pdf', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@waybillPdf');
    Route::get('express_order/balance', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@balance');
    // 统一快递服务 - 为回收订单创建/取消/查询快递
    Route::post('express_order/create_for_order', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@createForOrder');
    Route::post('express_order/cancel_for_order', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@cancelForOrder');
    Route::get('express_order/track_for_order', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@trackForOrder');
    Route::post('express_order/unified_quote', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@unifiedQuote');
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
    Route::get('express_provider_config/lists', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressProviderConfig@lists');
    // 服务商配置详情
    Route::get('express_provider_config/:id', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressProviderConfig@info');
    // 编辑服务商配置
    Route::put('express_provider_config/:id', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressProviderConfig@edit');
    // 设置默认服务商
    Route::put('express_provider_config/set_default/:id', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressProviderConfig@setDefault');
    // 切换启用状态
    Route::put('express_provider_config/toggle_status/:id', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressProviderConfig@toggleStatus');
    // 获取当前启用的服务商
    Route::get('express_provider_config/active', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressProviderConfig@getActiveProvider');
    // 检查快递服务状态
    Route::get('express_provider_config/check_status', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressProviderConfig@checkStatus');
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
    Route::get('yisu_product/lists', 'addon\hsx_recycle\app\adminapi\controller\yisu\YisuProduct@lists');
    Route::post('yisu_product/batch_update', 'addon\hsx_recycle\app\adminapi\controller\yisu\YisuProduct@batchUpdate');
    Route::post('yisu_product/modify_status', 'addon\hsx_recycle\app\adminapi\controller\yisu\YisuProduct@modifyStatus');
    Route::get('yisu_product/enabled', 'addon\hsx_recycle\app\adminapi\controller\yisu\YisuProduct@enabled');
    Route::post('yisu/create_order', 'addon\hsx_recycle\app\adminapi\controller\yisu\Yisu@createOrder');

    // yisu 业务相关接口
    // 下单 
    Route::post('yisu_order/create_order', 'addon\hsx_recycle\app\adminapi\controller\yisu\Yisu@createOrder');
    // 取消
    Route::post('yisu_order/cancel', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@cancel');
    // 查询
    Route::get('yisu_order/query', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@detail');
    // 查询物流
    Route::get('yisu_order/track', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrder@track');

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
    Route::get('express_order_record/lists', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@lists');
    Route::get('express_order_record/status_options', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@statusOptions');
    Route::get('express_order_record/:id', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@info');
    Route::post('express_order_record', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@add');
    Route::put('express_order_record/:id', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@edit');
    Route::delete('express_order_record/:id', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@del');

    // 订单状态和信息更新
    Route::post('express_order_record/update_status', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@updateStatus');
    Route::post('express_order_record/update_actual_info', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@updateActualInfo');

    // 差异和统计
    Route::get('express_order_record/weight_diff_list', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@weightDiffList');
    Route::get('express_order_record/cost_diff_list', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@costDiffList');
    Route::get('express_order_record/statistics', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@statistics');

    // 关联查询
    Route::get('express_order_record/by_recycle_order', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressOrderRecord@getByRecycleOrderId');

    // 常用寄收件地址
    Route::get('express_address_book/lists', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressAddressBook@lists');
    Route::post('express_address_book/save', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressAddressBook@save');
    Route::delete('express_address_book/:id', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressAddressBook@del');
    Route::post('express_address_book/:id/default', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressAddressBook@setDefault');
    Route::post('express_address_book/:id/top', 'addon\hsx_recycle\app\adminapi\controller\express\ExpressAddressBook@setTop');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- express_order_record

// ✅ 小程序分享短链接
Route::group('recycle', function () {
    // 生成通用 Short Link
    Route::post('sys/short_link/generate', 'addon\hsx_recycle\app\adminapi\controller\sys\ShortLink@generate');
    // 生成回收订单分享链接
    Route::post('sys/short_link/order', 'addon\hsx_recycle\app\adminapi\controller\sys\ShortLink@generateOrderLink');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);

// ✅ USER_CODE_BEGIN -- recycle_order_reward
Route::group('recycle', function () {
    Route::get('order_reward/getconfig', 'addon\hsx_recycle\app\adminapi\controller\order\OrderReward@getConfig');
    Route::post('order_reward/setconfig', 'addon\hsx_recycle\app\adminapi\controller\order\OrderReward@setConfig');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- recycle_order_reward

// yisu
