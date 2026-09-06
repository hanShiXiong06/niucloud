<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('wecom', function () {
    Route::get('config', 'addon\hsx_wecom\app\adminapi\controller\Config@info');
    Route::post('config', 'addon\hsx_wecom\app\adminapi\controller\Config@save');
    Route::post('config/test', 'addon\hsx_wecom\app\adminapi\controller\Config@test');
    Route::get('provider/config', 'addon\hsx_wecom\app\adminapi\controller\Provider@info');
    Route::post('provider/config', 'addon\hsx_wecom\app\adminapi\controller\Provider@save');
    Route::post('provider/test', 'addon\hsx_wecom\app\adminapi\controller\Provider@test');
    Route::get('authorization/status', 'addon\hsx_wecom\app\adminapi\controller\Authorization@status');
    Route::post('authorization/start', 'addon\hsx_wecom\app\adminapi\controller\Authorization@start');
    Route::post('authorization/check', 'addon\hsx_wecom\app\adminapi\controller\Authorization@check');
    Route::get('staff', 'addon\hsx_wecom\app\adminapi\controller\Staff@lists');
    Route::put('staff/:uid', 'addon\hsx_wecom\app\adminapi\controller\Staff@save');
    Route::post('staff/:uid/bind-url', 'addon\hsx_wecom\app\adminapi\controller\Staff@bindUrl');
    Route::get('messages', 'addon\hsx_wecom\app\adminapi\controller\Message@lists');
    Route::post('messages/test', 'addon\hsx_wecom\app\adminapi\controller\Message@test');
    Route::post('messages/:id/retry', 'addon\hsx_wecom\app\adminapi\controller\Message@retry');
})->middleware([AdminCheckToken::class, AdminCheckRole::class, AdminLog::class]);

// 消息入口属于所有站点员工的基础能力：Token 中间件已校验员工拥有请求站点权限，
// 服务层还会再次核对签名票据中的 site_id，因此不依赖“企微配置”菜单权限。
Route::group('wecom', function () {
    Route::get('entry/resolve', 'addon\hsx_wecom\app\adminapi\controller\Entry@resolve');
})->middleware([AdminCheckToken::class, AdminLog::class]);
