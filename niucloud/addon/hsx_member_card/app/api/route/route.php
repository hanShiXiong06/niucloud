<?php
declare(strict_types=1);

use app\api\middleware\ApiChannel;
use app\api\middleware\ApiCheckToken;
use app\api\middleware\ApiLog;
use think\facade\Route;

Route::group('member_card/member', function () {
    Route::get('overview', 'addon\hsx_member_card\app\api\controller\MemberPortal@overview');
    Route::get('cards', 'addon\hsx_member_card\app\api\controller\MemberPortal@cards');
    Route::get('cards/:id', 'addon\hsx_member_card\app\api\controller\MemberPortal@cardInfo');
    Route::get('orders', 'addon\hsx_member_card\app\api\controller\MemberPortal@orders');
    Route::get('redemptions', 'addon\hsx_member_card\app\api\controller\MemberPortal@redemptions');
})
    ->middleware(ApiChannel::class)
    ->middleware(ApiCheckToken::class, true)
    ->middleware(ApiLog::class);
