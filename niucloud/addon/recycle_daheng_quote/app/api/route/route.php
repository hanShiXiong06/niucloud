<?php
declare(strict_types=1);

use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;

Route::group('recycle_daheng_quote', function () {
    Route::get('quotation_v2/lists', 'addon\recycle_daheng_quote\app\api\controller\quotation\QuotationV2@lists');
    Route::get('quotation_v2/types', 'addon\recycle_daheng_quote\app\api\controller\quotation\QuotationV2@types');
})->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, false)
    ->middleware(ApiLog::class);
