<?php
declare(strict_types=1);
use think\facade\Route;
use app\api\middleware\{ApiChannel,ApiCheckToken};
use addon\hsx_lead\app\middleware\SafeErrors;
Route::group('hsx_lead/cooperation',function():void {
    $c='addon\\hsx_lead\\app\\api\\controller\\Cooperation@';
    Route::get('bootstrap',$c.'bootstrap');Route::post('profile',$c.'profile');Route::put('profile',$c.'profile');
    Route::get('catalog',$c.'catalog');Route::get('leads',$c.'leads');Route::post('leads',$c.'createLead');
    Route::get('leads/:id',$c.'detail');Route::get('leads/:id/stores',$c.'leadStores');Route::get('stores',$c.'stores');
    Route::get('stores/:id/inventory',$c.'storeInventory');
    Route::post('leads/:id/assign',$c.'assign');Route::post('leads/:id/action',$c.'action');
    Route::post('orders',$c.'createOrder');Route::get('orders/:id',$c.'order');Route::get('wallet',$c.'wallet');
    Route::post('attachments',$c.'attachment');Route::post('storefront',$c.'storefront');Route::get('connection',$c.'connection');Route::post('connection/challenge',$c.'challenge');
    Route::post('business-license',$c.'businessLicenseUpload');Route::post('business-license/preview',$c.'businessLicensePreview');
    Route::post('connection/probe',$c.'probe');Route::post('connection/activate',$c.'activate');
    Route::put('connection/settings',$c.'connectionSettings');
    Route::post('sync',$c.'sync');Route::get('sync/:id',$c.'syncInfo');Route::post('sync/:id/advance',$c.'advanceSync');
})->middleware(SafeErrors::class)->middleware(ApiChannel::class)->middleware(ApiCheckToken::class,true)->completeMatch();
