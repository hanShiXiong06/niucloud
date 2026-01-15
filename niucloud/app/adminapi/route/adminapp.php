<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;


/**
 * 店铺移动管理端
 */
Route::group('adminapp', function () {

    /************************************************** 物流公司 *****************************************************/

    //首页应用
    Route::get('site/apps_of_index', 'adminapp.site.Apps/getAppsOfIndex');

    //设置首页应用
    Route::post('site/apps_of_index', 'adminapp.site.Apps/setAppsOfIndex');

    //全部应用
    Route::get('site/apps', 'adminapp.site.Apps/getApps');

    //个人中心应用
    Route::get('site/apps_of_user_center', 'adminapp.site.Apps/getAppOfUserCenter');

    //底部导航
    Route::get('site/navs', 'adminapp.site.Apps/getBottomNav');

    //待办
    Route::get('site/todo', 'adminapp.site.Index/getTodoList');
    //全部待办
    Route::get('site/todo_of_all', 'adminapp.site.Index/getAllTodoList');
    //设置待办
    Route::post('site/todo', 'adminapp.site.Index/setTodoList');

    //统计
    Route::get('site/stat', 'adminapp.site.Index/getStatList');
    //全部统计
    Route::get('site/stat_of_all', 'adminapp.site.Index/getAllStatList');
    //设置统计
    Route::post('site/stat', 'adminapp.site.Index/setStatList');

    //附件配置
    Route::get('site/attachment_config', 'adminapp.site.Attachment/getConfig');
    //附件分类列表
    Route::get('site/attachment_category_list', 'adminapp.site.Attachment/categoryLists');
    //附件列表
    Route::get('site/attachment_list', 'adminapp.site.Attachment/lists');
    //上传Base64图片
    Route::post('site/upload_image_base64', 'adminapp.site.Attachment/uploadImageBase64');

})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class
]);

