<?php
declare(strict_types=1);
use think\facade\Route;
use app\api\middleware\{ApiChannel,ApiCheckToken};
use addon\hsx_lead\app\middleware\SafeErrors;
Route::group('hsx_lead/cooperation/entity', function (): void {
    $c = 'addon\\hsx_lead\\app\\api\\controller\\Entity@';
    Route::get('', $c.'index');
    Route::get('lookup', $c.'lookup');
    Route::post('join', $c.'join');
    Route::post('leave', $c.'leave');
    Route::post('members/:memberId/review', $c.'review');
    Route::delete('members/:memberId', $c.'remove');
    Route::get('membership', $c.'membership');
    Route::get('quote', $c.'quote');
    Route::get('deposit', $c.'deposit');
    Route::post('deposit/apply', $c.'applyDeposit');
    Route::post('orders/:id/close', $c.'closeOrder');
})->middleware(SafeErrors::class)->middleware(ApiChannel::class)->middleware(ApiCheckToken::class, true)->completeMatch();
