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
    Route::get('staff', 'addon\hsx_wecom\app\adminapi\controller\Staff@lists');
    Route::put('staff/:uid', 'addon\hsx_wecom\app\adminapi\controller\Staff@save');
    Route::get('messages', 'addon\hsx_wecom\app\adminapi\controller\Message@lists');
    Route::post('messages/:id/retry', 'addon\hsx_wecom\app\adminapi\controller\Message@retry');
})->middleware([AdminCheckToken::class, AdminCheckRole::class, AdminLog::class]);
