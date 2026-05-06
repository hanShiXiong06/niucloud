<?php
declare(strict_types=1);

use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;

Route::group('recycle_quote_spider', function () {
    Route::get('category/tree', 'addon\recycle_quote_spider\app\api\controller\Quote@categoryTree');
    Route::get('item', 'addon\recycle_quote_spider\app\api\controller\Quote@items');
    Route::get('item/:id', 'addon\recycle_quote_spider\app\api\controller\Quote@detail');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false)
    ->middleware(ApiLog::class);
