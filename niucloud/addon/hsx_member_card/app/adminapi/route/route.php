<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('member_card', function () {
    Route::get('dashboard', 'addon\hsx_member_card\app\adminapi\controller\Dashboard@index');
    Route::get('dicts', 'addon\hsx_member_card\app\adminapi\controller\Config@dicts');
    Route::get('config', 'addon\hsx_member_card\app\adminapi\controller\Config@info');
    Route::post('config', 'addon\hsx_member_card\app\adminapi\controller\Config@save');

    Route::get('product/lists', 'addon\hsx_member_card\app\adminapi\controller\Product@lists');
    Route::get('product/options', 'addon\hsx_member_card\app\adminapi\controller\Product@options');
    Route::get('product/:id', 'addon\hsx_member_card\app\adminapi\controller\Product@info');
    Route::post('product/save/:id', 'addon\hsx_member_card\app\adminapi\controller\Product@save');
    Route::post('product/save', 'addon\hsx_member_card\app\adminapi\controller\Product@save');
    Route::post('product/:id/enable', 'addon\hsx_member_card\app\adminapi\controller\Product@enable');
    Route::post('product/:id/disable', 'addon\hsx_member_card\app\adminapi\controller\Product@disable');
    Route::delete('product/:id', 'addon\hsx_member_card\app\adminapi\controller\Product@delete');

    Route::get('member/lists', 'addon\hsx_member_card\app\adminapi\controller\Member@lists');
    Route::get('member/options', 'addon\hsx_member_card\app\adminapi\controller\Member@options');
    Route::post('member/quick_create', 'addon\hsx_member_card\app\adminapi\controller\Member@quickCreate');
    Route::get('member/:member_id/info', 'addon\hsx_member_card\app\adminapi\controller\Member@info');
    Route::get('member/:member_id/cards', 'addon\hsx_member_card\app\adminapi\controller\Member@cards');

    Route::get('order/lists', 'addon\hsx_member_card\app\adminapi\controller\Order@lists');
    Route::post('order/create', 'addon\hsx_member_card\app\adminapi\controller\Order@create');
    Route::get('order/:id', 'addon\hsx_member_card\app\adminapi\controller\Order@info');
    Route::post('order/:id/retry_finance', 'addon\hsx_member_card\app\adminapi\controller\Order@retryFinance');
    Route::post('order/:id/cancel', 'addon\hsx_member_card\app\adminapi\controller\Order@cancel');
    Route::post('order/:id/refund/apply', 'addon\hsx_member_card\app\adminapi\controller\Refund@apply');

    Route::get('card/search', 'addon\hsx_member_card\app\adminapi\controller\Card@search');
    Route::get('card/:id', 'addon\hsx_member_card\app\adminapi\controller\Card@info');
    Route::post('card/:id/redeem', 'addon\hsx_member_card\app\adminapi\controller\Card@redeem');
    Route::post('card/:id/freeze', 'addon\hsx_member_card\app\adminapi\controller\Card@freeze');
    Route::post('card/:id/unfreeze', 'addon\hsx_member_card\app\adminapi\controller\Card@unfreeze');

    Route::get('redemption/lists', 'addon\hsx_member_card\app\adminapi\controller\Redemption@lists');
    Route::get('redemption/:id', 'addon\hsx_member_card\app\adminapi\controller\Redemption@info');
    Route::post('redemption/:id/reverse', 'addon\hsx_member_card\app\adminapi\controller\Redemption@reverse');

    Route::get('refund/lists', 'addon\hsx_member_card\app\adminapi\controller\Refund@lists');
    Route::get('refund/:id', 'addon\hsx_member_card\app\adminapi\controller\Refund@info');
    Route::post('refund/:id/retry_finance', 'addon\hsx_member_card\app\adminapi\controller\Refund@retryFinance');
})->middleware([AdminCheckToken::class, AdminCheckRole::class, AdminLog::class]);
