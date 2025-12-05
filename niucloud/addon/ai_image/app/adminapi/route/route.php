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
 * AI设计大师
 */
Route::group('ai_image', function () {

     /***************************************************** AI设计 ****************************************************/
    //获取配置
    Route::get('config/getconfig', 'addon\ai_image\app\adminapi\controller\config\Config@getConfig');
    //设置配置
    Route::post('config/setconfig', 'addon\ai_image\app\adminapi\controller\config\Config@setConfig');
    //获取配置
    Route::get('config/getsxfconfig', 'addon\ai_image\app\adminapi\controller\config\Config@getSxfConfig');
    //设置配置
    Route::post('config/setsxfconfig', 'addon\ai_image\app\adminapi\controller\config\Config@setSxfConfig');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_BEGIN -- aiimage_model

Route::group('ai_image', function () {

    //智能体列表
    Route::get('aiimagemodel', 'addon\ai_image\app\adminapi\controller\aiimagemodel\AiimageModel@lists');
    //智能体详情
    Route::get('aiimagemodel/:id', 'addon\ai_image\app\adminapi\controller\aiimagemodel\AiimageModel@info');
    //添加智能体
    Route::post('aiimagemodel', 'addon\ai_image\app\adminapi\controller\aiimagemodel\AiimageModel@add');
    //编辑智能体
    Route::put('aiimagemodel/:id', 'addon\ai_image\app\adminapi\controller\aiimagemodel\AiimageModel@edit');
    //删除智能体
    Route::delete('aiimagemodel/:id', 'addon\ai_image\app\adminapi\controller\aiimagemodel\AiimageModel@del');
    //同步智能体
    Route::post('aiimagemodel/asyncModel', 'addon\ai_image\app\adminapi\controller\aiimagemodel\AiimageModel@asyncModel');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- aiimage_model

// USER_CODE_BEGIN -- aiimage_card

Route::group('ai_image', function () {

    //卡密兑换列表
    Route::get('aiimagecard', 'addon\ai_image\app\adminapi\controller\aiimagecard\AiimageCard@lists');
    //卡密兑换详情
    Route::get('aiimagecard/:id', 'addon\ai_image\app\adminapi\controller\aiimagecard\AiimageCard@info');
    //添加卡密兑换
    Route::post('aiimagecard', 'addon\ai_image\app\adminapi\controller\aiimagecard\AiimageCard@add');
    //编辑卡密兑换
    Route::put('aiimagecard/:id', 'addon\ai_image\app\adminapi\controller\aiimagecard\AiimageCard@edit');
    //删除卡密兑换
    Route::delete('aiimagecard/:id', 'addon\ai_image\app\adminapi\controller\aiimagecard\AiimageCard@del');
    
    Route::get('member_all','addon\ai_image\app\adminapi\controller\aiimagecard\AiimageCard@getMemberAll');
    Route::post('delcardselect', 'addon\ai_image\app\adminapi\controller\aiimagecard\AiimageCard@delselect');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- aiimage_card

// USER_CODE_BEGIN -- aiimage_package

Route::group('ai_image', function () {

    //套餐列列表
    Route::get('aiimagepackage', 'addon\ai_image\app\adminapi\controller\aiimagepackage\AiimagePackage@lists');
    //套餐列详情
    Route::get('aiimagepackage/:id', 'addon\ai_image\app\adminapi\controller\aiimagepackage\AiimagePackage@info');
    //添加套餐列
    Route::post('aiimagepackage', 'addon\ai_image\app\adminapi\controller\aiimagepackage\AiimagePackage@add');
    //编辑套餐列
    Route::put('aiimagepackage/:id', 'addon\ai_image\app\adminapi\controller\aiimagepackage\AiimagePackage@edit');
    //删除套餐列
    Route::delete('aiimagepackage/:id', 'addon\ai_image\app\adminapi\controller\aiimagepackage\AiimagePackage@del');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- aiimage_package

// USER_CODE_BEGIN -- aiimage_order

Route::group('ai_image', function () {

    //订单列列表
    Route::get('aiimageorder', 'addon\ai_image\app\adminapi\controller\aiimageorder\AiimageOrder@lists');
    //订单列详情
    Route::get('aiimageorder/:id', 'addon\ai_image\app\adminapi\controller\aiimageorder\AiimageOrder@info');
    //添加订单列
    Route::post('aiimageorder', 'addon\ai_image\app\adminapi\controller\aiimageorder\AiimageOrder@add');
    //编辑订单列
    Route::put('aiimageorder/:id', 'addon\ai_image\app\adminapi\controller\aiimageorder\AiimageOrder@edit');
    //删除订单列
    Route::delete('aiimageorder/:id', 'addon\ai_image\app\adminapi\controller\aiimageorder\AiimageOrder@del');
    
    Route::get('member_all','addon\ai_image\app\adminapi\controller\aiimageorder\AiimageOrder@getMemberAll');

    Route::get('aiimage_package_all','addon\ai_image\app\adminapi\controller\aiimageorder\AiimageOrder@getAiimagePackageAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- aiimage_order


// USER_CODE_BEGIN -- aiimage_create

Route::group('ai_image', function () {

    //作品列列表
    Route::get('aiimagecreate', 'addon\ai_image\app\adminapi\controller\aiimagecreate\AiimageCreate@lists');
    //作品列详情
    Route::get('aiimagecreate/:id', 'addon\ai_image\app\adminapi\controller\aiimagecreate\AiimageCreate@info');
    //添加作品列
    Route::post('aiimagecreate', 'addon\ai_image\app\adminapi\controller\aiimagecreate\AiimageCreate@add');
    //编辑作品列
    Route::put('aiimagecreate/:id', 'addon\ai_image\app\adminapi\controller\aiimagecreate\AiimageCreate@edit');
    //删除作品列
    Route::delete('aiimagecreate/:id', 'addon\ai_image\app\adminapi\controller\aiimagecreate\AiimageCreate@del');
    
    Route::get('member_all','addon\ai_image\app\adminapi\controller\aiimagecreate\AiimageCreate@getMemberAll');

    Route::get('aiimage_model_all','addon\ai_image\app\adminapi\controller\aiimagecreate\AiimageCreate@getAiimageModelAll');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- aiimage_create

// USER_CODE_BEGIN -- aiimage_help

Route::group('ai_image', function () {

    //帮助中心列表
    Route::get('aiimagehelp', 'addon\ai_image\app\adminapi\controller\aiimagehelp\AiimageHelp@lists');
    //帮助中心详情
    Route::get('aiimagehelp/:id', 'addon\ai_image\app\adminapi\controller\aiimagehelp\AiimageHelp@info');
    //添加帮助中心
    Route::post('aiimagehelp', 'addon\ai_image\app\adminapi\controller\aiimagehelp\AiimageHelp@add');
    //编辑帮助中心
    Route::put('aiimagehelp/:id', 'addon\ai_image\app\adminapi\controller\aiimagehelp\AiimageHelp@edit');
    //删除帮助中心
    Route::delete('aiimagehelp/:id', 'addon\ai_image\app\adminapi\controller\aiimagehelp\AiimageHelp@del');
    
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);
// USER_CODE_END -- aiimage_help
