<?php
declare(strict_types=1);
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
use addon\hsx_lead\app\adminapi\middleware\PermissionGate;
use addon\hsx_lead\app\middleware\SafeErrors;
Route::group('hsx_lead/cooperation',function():void {
    $c='addon\\hsx_lead\\app\\adminapi\\controller\\Cooperation@';
    Route::get('config',$c.'config');Route::put('config',$c.'saveConfig');Route::get('dashboard',$c.'dashboard');Route::get('scope',$c.'scope');
    Route::get('owners',$c.'owners');
    Route::get('leads',$c.'leads');Route::get('leads/:id',$c.'detail');Route::post('leads/:id/owner',$c.'transferOwner');
    Route::get('partners',$c.'partners');Route::post('partners/:id/block',$c.'blockPartner');
    Route::get('partners/:id/business-license',$c.'businessLicensePreview');
    Route::get('orders',$c.'orders');
    Route::get('orders/options',$c.'orderOptions');Route::get('orders/:id/refund',$c.'orderRefund');Route::post('orders/:id/refund',$c.'requestOrderRefund');
    Route::post('orders/:id/refund/review',$c.'reviewOrderRefund');Route::post('orders/:id/refund/refresh',$c.'refreshOrderRefund');Route::post('orders/:id/refund/continue',$c.'continueOrderRefund');
    Route::post('orders/:id/close',$c.'closeOrder');
    Route::post('orders/:id/reprice',$c.'repriceOrder');Route::get('orders/:id/pricing/logs',$c.'orderPricingLogs');
    Route::get('reviews',$c.'reviews');Route::post('reviews',$c.'createReview');Route::post('reviews/:id/resolve',$c.'resolve');Route::post('attachments',$c.'attachment');
})->middleware([SafeErrors::class,AdminCheckToken::class,AdminCheckRole::class,PermissionGate::class])->completeMatch();
