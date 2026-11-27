<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('marketing', function () {
    Route::get('campaigns', 'addon\hsx_marketing\app\adminapi\controller\Campaign@lists');
    Route::get('campaigns/metadata', 'addon\hsx_marketing\app\adminapi\controller\Campaign@metadata');
    Route::get('campaigns/provider-options', 'addon\hsx_marketing\app\adminapi\controller\Campaign@providerOptions');
    Route::get('campaigns/:id', 'addon\hsx_marketing\app\adminapi\controller\Campaign@info');
    Route::post('campaigns', 'addon\hsx_marketing\app\adminapi\controller\Campaign@add');
    Route::put('campaigns/:id', 'addon\hsx_marketing\app\adminapi\controller\Campaign@edit');
    Route::delete('campaigns/:id', 'addon\hsx_marketing\app\adminapi\controller\Campaign@delete');
    Route::put('campaigns/:id/status', 'addon\hsx_marketing\app\adminapi\controller\Campaign@status');
    Route::get('rewards', 'addon\hsx_marketing\app\adminapi\controller\Reward@lists');
    Route::post('rewards/:id/retry', 'addon\hsx_marketing\app\adminapi\controller\Reward@retry');
    Route::get('facts', 'addon\hsx_marketing\app\adminapi\controller\Fact@lists');
    Route::post('facts/:id/retry', 'addon\hsx_marketing\app\adminapi\controller\Fact@retry');
})->middleware([AdminCheckToken::class, AdminCheckRole::class, AdminLog::class]);
