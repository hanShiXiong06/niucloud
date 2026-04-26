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
 * 物流回调接口
 */
Route::post('express_callback', 'addon\wj_books\app\api\controller\wj_books_express_log\ExpressCallback@index');

/**
 * 二手书旧书回收
 */
Route::group('wj_books', function() {
    /***************************************************** hello world ****************************************************/
    Route::get('hello_world', 'addon\wj_books\app\api\controller\hello_world\Index@index');

    /***************************************************** 图书信息 ****************************************************/
    // 通过ISBN查询图书信息
    Route::get('books/query', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksInfo@queryBookInfo');
    // 获取系统配置
    Route::get('config', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksInfo@getConfig');
    
    /***************************************************** 扫描记录 ****************************************************/
    // 添加扫描记录
    Route::post('scan/add', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksInfo@addScanRecord');
    // 更新扫描记录状态
    Route::post('scan/update_status', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksInfo@updateScanStatus');
    
    /***************************************************** 订单相关 ****************************************************/
    // 创建回收订单
    Route::post('order/create', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksOrder@createOrder');
    // 获取订单详情
    Route::get('order/detail', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksOrder@getOrderDetail');
    // 获取订单列表
    Route::get('order/list', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksOrder@getOrderList');
    // 取消订单
    Route::post('order/cancel', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksOrder@cancelOrder');
    // 删除订单
    Route::post('order/delete', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksOrder@deleteOrder');
    // 获取物流信息
    Route::get('order/express', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksOrder@getExpressInfo');
    // 申请取回不合格书籍
    Route::post('order/retrieve', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksOrder@applyRetrieve');
    
    /***************************************************** 需要登录的接口 ****************************************************/

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false) //false表示不验证登录
    ->middleware(ApiLog::class);



Route::group('wj_books', function() {
    /***************************************************** 回收车 ****************************************************/
    // 添加图书到回收车
    Route::post('cart/add', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksInfo@addToCart');
    // 获取回收车列表
    Route::get('cart/list', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksInfo@getCartList');
    // 从回收车中移除图书
    Route::post('cart/remove', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksInfo@removeFromCart');
    // 提交回收
    Route::post('cart/submit', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksInfo@submitRecycle');
    // 更新回收书籍数量
    Route::post('cart/update_quantity', 'addon\wj_books\app\api\controller\wj_books_info\WjBooksInfo@updateCartQuantity');

})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true) //表示验证登录
    ->middleware(ApiLog::class);

