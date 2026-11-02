<?php
declare(strict_types=1);
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
use addon\hsx_lead\app\adminapi\middleware\PermissionGate;
use addon\hsx_lead\app\middleware\SafeErrors;
Route::group('hsx_lead/cooperation',function():void {
    $c='addon\\hsx_lead\\app\\adminapi\\controller\\Cooperation@';
    Route::get('config',$c.'config');Route::put('config',$c.'saveConfig');Route::get('dashboard',$c.'dashboard');
    Route::get('owners',$c.'owners');
    Route::get('leads',$c.'leads');Route::get('leads/:id',$c.'detail');Route::post('leads/:id/owner',$c.'transferOwner');
    Route::get('partners',$c.'partners');Route::put('partners/:id',$c.'updatePartner');Route::post('partners/:id/block',$c.'blockPartner');
    Route::get('orders',$c.'orders');Route::get('withdrawals',$c.'withdrawals');Route::get('withdrawals/:id',$c.'withdrawalDetail');
    Route::post('withdrawals/:id/paid',$c.'paid');Route::post('withdrawals/:id/reject',$c.'reject');
    Route::get('reviews',$c.'reviews');Route::post('reviews',$c.'createReview');Route::post('reviews/:id/resolve',$c.'resolve');Route::post('attachments',$c.'attachment');
})->middleware([SafeErrors::class,AdminCheckToken::class,AdminCheckRole::class,PermissionGate::class])->completeMatch();
