<?php
declare(strict_types=1);
use think\facade\Route;
// 外部回调没有登录Token；站点+随机任务号定位后必须使用该任务原salt验签。
Route::post('hsx_express/callback/:site_id/:task_no', 'addon\\hsx_express\\app\\api\\controller\\Callback@receive');
