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
 * 快速支付
 */
Route::group('fast_pay', function () {

     /***************************************************** 快速支付配置 ****************************************************/
    //获取配置
    Route::get('config/getconfig', 'addon\fast_pay\app\adminapi\controller\config\Config@getConfig');
    //设置配置
    Route::post('config/setconfig', 'addon\fast_pay\app\adminapi\controller\config\Config@setConfig');
    //生成收款二维码
    Route::post('config/poster', 'addon\fast_pay\app\adminapi\controller\config\Config@poster');
    //获取配置
    Route::get('config/getadminconfig', 'addon\fast_pay\app\adminapi\controller\config\Config@getAdminConfig');
    //设置配置
    Route::post('config/setadminconfig', 'addon\fast_pay\app\adminapi\controller\config\Config@setAdminConfig');
    //获取配置
    Route::get('config/getbusinessconfig', 'addon\fast_pay\app\adminapi\controller\config\Config@getBusinessConfig');
    //设置配置
    Route::post('config/setbusinessconfig', 'addon\fast_pay\app\adminapi\controller\config\Config@setBusinessConfig');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_BEGIN -- fastpay_order

Route::group('fast_pay', function () {

    //快速支付订单列表
    Route::get('order', 'addon\fast_pay\app\adminapi\controller\order\Order@lists');
    //快速支付订单详情
    Route::get('order/:id', 'addon\fast_pay\app\adminapi\controller\order\Order@info');
    //添加快速支付订单
    Route::post('order', 'addon\fast_pay\app\adminapi\controller\order\Order@add');
    //编辑快速支付订单
    Route::put('order/:id', 'addon\fast_pay\app\adminapi\controller\order\Order@edit');
    //删除快速支付订单
    Route::delete('order/:id', 'addon\fast_pay\app\adminapi\controller\order\Order@del');
    
    Route::get('member_all','addon\fast_pay\app\adminapi\controller\order\Order@getMemberAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- fastpay_order

// USER_CODE_BEGIN -- fastpay_business

Route::group('fast_pay', function () {

    //商户列列表
    Route::get('business', 'addon\fast_pay\app\adminapi\controller\business\Business@lists');
    //商户列详情
    Route::get('business/:id', 'addon\fast_pay\app\adminapi\controller\business\Business@info');
    //添加商户列
    Route::post('business', 'addon\fast_pay\app\adminapi\controller\business\Business@add');
    //编辑商户列
    Route::put('business/:id', 'addon\fast_pay\app\adminapi\controller\business\Business@edit');
    //删除商户列
    Route::delete('business/:id', 'addon\fast_pay\app\adminapi\controller\business\Business@del');
    
    Route::get('member_all','addon\fast_pay\app\adminapi\controller\business\Business@getMemberAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- fastpay_business

// USER_CODE_BEGIN -- fastpay_business_order

Route::group('fast_pay', function () {

    //商户订单列列表
    Route::get('businessorder', 'addon\fast_pay\app\adminapi\controller\businessorder\BusinessOrder@lists');
    //商户订单列详情
    Route::get('businessorder/:id', 'addon\fast_pay\app\adminapi\controller\businessorder\BusinessOrder@info');
    //添加商户订单列
    Route::post('businessorder', 'addon\fast_pay\app\adminapi\controller\businessorder\BusinessOrder@add');
    //编辑商户订单列
    Route::put('businessorder/:id', 'addon\fast_pay\app\adminapi\controller\businessorder\BusinessOrder@edit');
    //删除商户订单列
    Route::delete('businessorder/:id', 'addon\fast_pay\app\adminapi\controller\businessorder\BusinessOrder@del');
    
    Route::get('member_all','addon\fast_pay\app\adminapi\controller\businessorder\BusinessOrder@getMemberAll');

    Route::get('member_all','addon\fast_pay\app\adminapi\controller\businessorder\BusinessOrder@getMemberAll');

    Route::get('business_all','addon\fast_pay\app\adminapi\controller\businessorder\BusinessOrder@getBusinessAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- fastpay_business_order

// USER_CODE_BEGIN -- fastpay_business_active

Route::group('fast_pay', function () {

    //商户活动列表
    Route::get('businessactive', 'addon\fast_pay\app\adminapi\controller\businessactive\BusinessActive@lists');
    //商户活动详情
    Route::get('businessactive/:id', 'addon\fast_pay\app\adminapi\controller\businessactive\BusinessActive@info');
    //添加商户活动
    Route::post('businessactive', 'addon\fast_pay\app\adminapi\controller\businessactive\BusinessActive@add');
    //编辑商户活动
    Route::put('businessactive/:id', 'addon\fast_pay\app\adminapi\controller\businessactive\BusinessActive@edit');
    //删除商户活动
    Route::delete('businessactive/:id', 'addon\fast_pay\app\adminapi\controller\businessactive\BusinessActive@del');
    
    Route::get('business_all','addon\fast_pay\app\adminapi\controller\businessactive\BusinessActive@getBusinessAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- fastpay_business_active

// USER_CODE_BEGIN -- fastpay_business_member

Route::group('fast_pay', function () {

    //商户会员列表
    Route::get('businessmember', 'addon\fast_pay\app\adminapi\controller\businessmember\BusinessMember@lists');
    //商户会员详情
    Route::get('businessmember/:id', 'addon\fast_pay\app\adminapi\controller\businessmember\BusinessMember@info');
    //添加商户会员
    Route::post('businessmember', 'addon\fast_pay\app\adminapi\controller\businessmember\BusinessMember@add');
    //编辑商户会员
    Route::put('businessmember/:id', 'addon\fast_pay\app\adminapi\controller\businessmember\BusinessMember@edit');
    //删除商户会员
    Route::delete('businessmember/:id', 'addon\fast_pay\app\adminapi\controller\businessmember\BusinessMember@del');
    
    Route::get('business_all','addon\fast_pay\app\adminapi\controller\businessmember\BusinessMember@getBusinessAll');

    Route::get('member_all','addon\fast_pay\app\adminapi\controller\businessmember\BusinessMember@getMemberAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- fastpay_business_member
