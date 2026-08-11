<?php
declare(strict_types=1);

use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;

Route::group('marketing', function () {
    Route::get('overview', 'addon\hsx_marketing\app\api\controller\Portal@overview');
    Route::get('tasks', 'addon\hsx_marketing\app\api\controller\Portal@tasks');
    Route::post('tasks/:id/claim', 'addon\hsx_marketing\app\api\controller\Portal@claimTask');
    Route::get('rewards', 'addon\hsx_marketing\app\api\controller\Portal@rewards');
    Route::post('rewards/:id/claim', 'addon\hsx_marketing\app\api\controller\Portal@claimReward');
})->middleware(ApiChannel::class)->middleware(ApiCheckToken::class, true)->middleware(ApiLog::class);
