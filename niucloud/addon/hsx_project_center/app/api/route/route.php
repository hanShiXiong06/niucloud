<?php
declare(strict_types=1);
use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;
Route::group('project-center', function () {
    Route::get('projects/:id', 'addon\hsx_project_center\app\api\controller\Portal@project');
})->middleware(ApiChannel::class)->middleware(ApiCheckToken::class, false)->middleware(ApiLog::class);
Route::group('project-center', function () {
    Route::get('projects/:id/group-check', 'addon\hsx_project_center\app\api\controller\Portal@checkGroup');
    Route::post('projects/:id/group-resolve', 'addon\hsx_project_center\app\api\controller\Portal@resolveGroup');
    Route::post('projects/:id/submit', 'addon\hsx_project_center\app\api\controller\Portal@submit');
    Route::post('projects/:id/revise', 'addon\hsx_project_center\app\api\controller\Portal@revise');
    Route::get('projects/:id/status', 'addon\hsx_project_center\app\api\controller\Portal@status');
})->middleware(ApiChannel::class)->middleware(ApiCheckToken::class)->middleware(ApiLog::class);
