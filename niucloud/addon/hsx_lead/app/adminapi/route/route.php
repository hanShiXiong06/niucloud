<?php
declare(strict_types=1);
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
use addon\hsx_lead\app\adminapi\middleware\PermissionGate;
use addon\hsx_lead\app\middleware\SafeErrors;
require __DIR__.'/cooperation.php';
require __DIR__.'/promotion.php';
require __DIR__.'/entity.php';
require __DIR__.'/deposit.php';

// No AdminLog: it records full request bodies, including ERP passwords. Services write whitelist audits instead.
Route::group('hsx_lead',function(){
    $c='addon\\hsx_lead\\app\\adminapi\\controller\\Workbench@';
    foreach(['members','merchants','inventory'] as $action) Route::get($action,$c.$action);
    Route::get('catalog',$c.'catalog'); Route::put('catalog',$c.'saveCatalog');
    Route::get('providers',$c.'providers');
    Route::get('notice/config',$c.'noticeConfig'); Route::put('notice/config',$c.'saveNoticeConfig');
    Route::get('merchants/:id/connection',$c.'connection');
    Route::get('captcha/config',$c.'captchaConfig'); Route::put('captcha/config',$c.'saveCaptchaConfig');
    Route::post('captcha/balance',$c.'captchaBalance');
    Route::post('merchants/:id/connection/auth/challenge',$c.'authorizationChallenge');
    Route::post('merchants/:id/connection/auth/automatic',$c.'automaticAuthorization');
    Route::post('merchants/:id/connection/auth/automatic/advance',$c.'advanceAutomaticAuthorization');
    Route::post('merchants/:id/connection/auth/automatic/cancel',$c.'cancelAutomaticAuthorization');
    Route::post('merchants/:id/connection/probe',$c.'probeConnection');
    Route::post('merchants/:id/connection/activate',$c.'activateConnection');
    Route::put('merchants/:id/connection/settings',$c.'connectionSettings');
    Route::get('merchants/:id',$c.'merchant');
    Route::post('merchants',$c.'addMerchant'); Route::put('merchants/:id',$c.'editMerchant');
    Route::post('merchants/:id/sync',$c.'sync');
    Route::get('sync/:id',$c.'syncInfo'); Route::post('sync/:id/advance',$c.'advanceSync');
})->middleware([SafeErrors::class,AdminCheckToken::class,AdminCheckRole::class,PermissionGate::class])->completeMatch();
