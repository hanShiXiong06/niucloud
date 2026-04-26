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
 * 二手书旧书回收
 */
Route::group('wj_books', function () {

     /***************************************************** hello world ****************************************************/
    Route::get('hello_world', 'addon\wj_books\app\adminapi\controller\hello_world\Index@index');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_BEGIN -- wj_books_info

Route::group('wj_books', function () {

    //图书信息列表
    Route::get('wj_books_info', 'addon\wj_books\app\adminapi\controller\wj_books_info\WjBooksInfo@lists');
    //通过ISBN查询图书信息
    Route::get('wj_books_info/query_by_isbn', 'addon\wj_books\app\adminapi\controller\wj_books_info\WjBooksInfo@queryBookInfo');
    //图书信息详情
    Route::get('wj_books_info/:id', 'addon\wj_books\app\adminapi\controller\wj_books_info\WjBooksInfo@info');
    //添加图书信息
    Route::post('wj_books_info', 'addon\wj_books\app\adminapi\controller\wj_books_info\WjBooksInfo@add');
    //编辑图书信息
    Route::put('wj_books_info/:id', 'addon\wj_books\app\adminapi\controller\wj_books_info\WjBooksInfo@edit');
    //删除图书信息
    Route::delete('wj_books_info/:id', 'addon\wj_books\app\adminapi\controller\wj_books_info\WjBooksInfo@del');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- wj_books_info

// USER_CODE_BEGIN -- wj_books_scan_records

Route::group('wj_books', function () {

    //图书扫描记录列表
    Route::get('wj_books_scan_records', 'addon\wj_books\app\adminapi\controller\wj_books_scan_records\WjBooksScanRecords@lists');
    //图书扫描记录详情
    Route::get('wj_books_scan_records/:id', 'addon\wj_books\app\adminapi\controller\wj_books_scan_records\WjBooksScanRecords@info');
    //添加图书扫描记录
    Route::post('wj_books_scan_records', 'addon\wj_books\app\adminapi\controller\wj_books_scan_records\WjBooksScanRecords@add');
    //编辑图书扫描记录
    Route::put('wj_books_scan_records/:id', 'addon\wj_books\app\adminapi\controller\wj_books_scan_records\WjBooksScanRecords@edit');
    //删除图书扫描记录
    Route::delete('wj_books_scan_records/:id', 'addon\wj_books\app\adminapi\controller\wj_books_scan_records\WjBooksScanRecords@del');
    
    Route::get('member_all','addon\wj_books\app\adminapi\controller\wj_books_scan_records\WjBooksScanRecords@getMemberAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- wj_books_scan_records

// USER_CODE_BEGIN -- wj_books_order

Route::group('wj_books', function () {
    // 订单相关路由
    // 获取订单列表
    Route::get('wj_books_order', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@lists');
    
    // 获取订单详情
    Route::get('wj_books_order/:id', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@info')
        ->pattern(['id' => '\d+']);
    
    // 更新订单状态
    Route::put('wj_books_order/status', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@status');
    
    // 更新订单审核进度
    Route::put('wj_books_order/audit_progress', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@auditProgress');
    
    // 完成订单
    Route::put('wj_books_order/complete/:id', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@complete')
        ->pattern(['id' => '\d+']);
    
    // 取消订单
    Route::put('wj_books_order/cancel/:id', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@cancel')
        ->pattern(['id' => '\d+']);
    
    // 更新物流信息
    Route::put('wj_books_order/express/:order_id', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@express')
        ->pattern(['order_id' => '\d+']);
    
    // 订单书籍相关路由
    // 获取订单书籍列表
    Route::get('wj_books_order_book', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@bookList');
    
    // 更新订单书籍最终价格
    Route::put('wj_books_order_book/price', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@bookPrice');
    
    // 拒收书籍相关路由
    // 添加拒收书籍
    Route::post('wj_books_rejected_book', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@rejectedBook');
    
    // 获取拒收书籍列表
    Route::get('wj_books_rejected_book', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@rejectedBookList');
    
    // 删除拒收书籍
    Route::delete('wj_books_rejected_book/:id', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@deleteRejectedBook')
        ->pattern(['id' => '\d+']);
    
    // 拒收书籍审核图片相关路由
    // 上传拒收书籍审核图片
    Route::post('wj_books_rejected_images/upload', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@uploadRejectedImage');
    
    // 获取拒收书籍审核图片列表
    Route::get('wj_books_rejected_images', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@rejectedImageList');
    
    // 删除拒收书籍审核图片
    Route::delete('wj_books_rejected_images/:id', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@deleteRejectedImage')
        ->pattern(['id' => '\d+']);
    
    // 更新拒收书籍信息
    Route::put('wj_books_rejected_book', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@updateRejectedBook');
    
    // 更新拒收书籍数量
    Route::put('wj_books_rejected_book/quantity', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@updateRejectedBookQuantity');
    
    // 物流渠道相关路由
    // 获取物流渠道列表
    Route::get('wj_books_express_channel', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@expressChannelList');
    
    // 物流日志相关路由
    // 获取物流日志列表
    Route::get('wj_books_express_log', 'addon\wj_books\app\adminapi\controller\wj_books_order\WjBooksOrder@expressLogList');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- wj_books_order

// USER_CODE_BEGIN -- wj_books_config
Route::group('wj_books', function () {
    // 获取旧书回收系统配置
    Route::get('wj_books_config', 'addon\wj_books\app\adminapi\controller\wj_books_config\WjBooksConfigController@get');
    
    // 更新旧书回收系统配置
    Route::put('wj_books_config', 'addon\wj_books\app\adminapi\controller\wj_books_config\WjBooksConfigController@update');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- wj_books_config

// USER_CODE_BEGIN -- wj_books_express_log
Route::group('wj_books', function () {
    // 物流回调日志相关路由
    // 获取物流回调日志列表
    Route::get('wj_books_express_log/lists', 'addon\wj_books\app\adminapi\controller\wj_books_express_log\WjBooksExpressLog@lists');
    
    // 获取物流回调日志详情
    Route::get('wj_books_express_log/info/:id', 'addon\wj_books\app\adminapi\controller\wj_books_express_log\WjBooksExpressLog@info')
        ->pattern(['id' => '\d+']);
    
    // 删除物流回调日志
    Route::delete('wj_books_express_log/del/:id', 'addon\wj_books\app\adminapi\controller\wj_books_express_log\WjBooksExpressLog@del')
        ->pattern(['id' => '\d+']);
    
    // 清空物流回调日志
    Route::post('wj_books_express_log/clear', 'addon\wj_books\app\adminapi\controller\wj_books_express_log\WjBooksExpressLog@clear');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- wj_books_express_log

// USER_CODE_BEGIN -- wj_books_retrieve_apply
// 取回申请管理
Route::group('wj_books', function () {
    // 取回申请管理
    // 获取取回申请列表
     Route::get('wj_books_retrieve_apply', 'addon\wj_books\app\adminapi\controller\wj_books_retrieve_apply\WjBooksRetrieveApply@index');
    
    // 获取取回申请详情
    Route::get('wj_books_retrieve_apply/:id', 'addon\wj_books\app\adminapi\controller\wj_books_retrieve_apply\WjBooksRetrieveApply@read');
    // 更新取回申请状态
     Route::put('wj_books_retrieve_apply/:id/status', 'addon\wj_books\app\adminapi\controller\wj_books_retrieve_apply\WjBooksRetrieveApply@updateStatus');
    // 发货处理
    Route::put('wj_books_retrieve_apply/:id/ship', 'addon\wj_books\app\adminapi\controller\wj_books_retrieve_apply\WjBooksRetrieveApply@ship');
    // 完成取回申请
    Route::put('wj_books_retrieve_apply/:id/complete', 'addon\wj_books\app\adminapi\controller\wj_books_retrieve_apply\WjBooksRetrieveApply@complete');
    // 取消取回申请
    Route::put('wj_books_retrieve_apply/:id/cancel', 'addon\wj_books\app\adminapi\controller\wj_books_retrieve_apply\WjBooksRetrieveApply@cancel');
    // 删除取回申请
    Route::delete('wj_books_retrieve_apply/:id', 'addon\wj_books\app\adminapi\controller\wj_books_retrieve_apply\WjBooksRetrieveApply@delete');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- wj_books_retrieve_apply

// USER_CODE_BEGIN -- wj_books_member
// 会员管理相关路由
Route::group('wj_books_member', function () {
    // 调整会员可提现余额
    Route::post('money', 'addon\wj_books\app\adminapi\controller\WjBooksMember@adjustMoney');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- wj_books_member
