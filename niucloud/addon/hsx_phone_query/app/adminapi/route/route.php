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
 * 手机保修查询
 */
Route::group('hsx_phone_query', function () {

     /***************************************************** hello world ****************************************************/
    Route::get('hello_world', 'addon\hsx_phone_query\app\adminapi\controller\hello_world\Index@index');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);



// USER_CODE_BEGIN -- hsx_phone_query_config

Route::group('hsx_phone_query', function () {

    //配置信息列表
    Route::get('hsx_phone_query_config', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_config\HsxPhoneQueryConfig@lists');
    //服务商配置
    Route::get('hsx_phone_query_config/provider', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_config\HsxPhoneQueryConfig@config');
    //保存服务商配置
    Route::post('hsx_phone_query_config/provider', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_config\HsxPhoneQueryConfig@saveConfig');
    //配置信息详情
    Route::get('hsx_phone_query_config/:id', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_config\HsxPhoneQueryConfig@info');
    //添加配置信息
    Route::post('hsx_phone_query_config', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_config\HsxPhoneQueryConfig@add');
    //编辑配置信息
    Route::put('hsx_phone_query_config/:id', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_config\HsxPhoneQueryConfig@edit');
    //删除配置信息
    Route::delete('hsx_phone_query_config/:id', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_config\HsxPhoneQueryConfig@del');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- hsx_phone_query_config


// USER_CODE_BEGIN -- hsx_phone_query_category

Route::group('hsx_phone_query', function () {

    //分类列表
    Route::get('hsx_phone_query_category', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_category\HsxPhoneQueryCategory@lists');
    //添加分类
    Route::post('hsx_phone_query_category', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_category\HsxPhoneQueryCategory@add');
    //修改排序
    Route::post('hsx_phone_query_category/modify_sort/:id', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_category\HsxPhoneQueryCategory@modifySort');
    //修改显示状态
    Route::post('hsx_phone_query_category/modify_show/:id', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_category\HsxPhoneQueryCategory@modifyShow');
    //同步初始化查询项目
    Route::post('hsx_phone_query_category/sync_items', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_category\HsxPhoneQueryCategory@syncItems');
    //修复查询项目渠道基础信息
    Route::post('hsx_phone_query_category/repair_items', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_category\HsxPhoneQueryCategory@repairItems');
    //分类详情
    Route::get('hsx_phone_query_category/:id', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_category\HsxPhoneQueryCategory@info');
    //编辑分类
    Route::put('hsx_phone_query_category/:id', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_category\HsxPhoneQueryCategory@edit');
    //删除分类
    Route::delete('hsx_phone_query_category/:id', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_category\HsxPhoneQueryCategory@del');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- hsx_phone_query_category


// 查询手机信息列表
Route::group('hsx_phone_query', function () {

    //查询运营看板
    Route::get('hsx_phone_query_dashboard', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_list\HsxPhoneQueryList@dashboard');
    //查询手机信息列表
    Route::get('hsx_phone_query_list', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_list\HsxPhoneQueryList@lists');
    //查询手机信息详情
    Route::get('hsx_phone_query_list/:id', 'addon\hsx_phone_query\app\adminapi\controller\hsx_phone_query_list\HsxPhoneQueryList@info');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
