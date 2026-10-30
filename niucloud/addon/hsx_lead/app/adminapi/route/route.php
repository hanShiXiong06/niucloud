<?php
declare(strict_types=1);
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
use addon\hsx_lead\app\adminapi\middleware\PermissionGate;
use addon\hsx_lead\app\middleware\SafeErrors;

// No AdminLog: it records full request bodies, including ERP passwords. Services write whitelist audits instead.
Route::group('hsx_lead',function(){
    $c='addon\\hsx_lead\\app\\adminapi\\controller\\Workbench@';
    foreach(['dashboard'=>'dashboard','members'=>'members','merchants'=>'merchants','inventory'=>'inventory',
        'leads'=>'leads','workorders'=>'tasks','commissions'=>'commissions'] as $url=>$action) Route::get($url,$c.$action);
    Route::get('catalog',$c.'catalog'); Route::put('catalog',$c.'saveCatalog');
    Route::get('providers',$c.'providers');
    Route::get('notice/config',$c.'noticeConfig'); Route::put('notice/config',$c.'saveNoticeConfig');
    Route::get('merchants/:id/connection',$c.'connection');
    Route::post('merchants/:id/connection/probe',$c.'probeConnection');
    Route::post('merchants/:id/connection/activate',$c.'activateConnection');
    Route::put('merchants/:id/connection/settings',$c.'connectionSettings');
    Route::get('merchants/:id',$c.'merchant');
    Route::post('merchants',$c.'addMerchant'); Route::put('merchants/:id',$c.'editMerchant');
    Route::post('merchants/:id/sync',$c.'sync');
    Route::get('sync/:id',$c.'syncInfo'); Route::post('sync/:id/advance',$c.'advanceSync');
    Route::get('leads/:id',$c.'lead'); Route::post('leads',$c.'addLead'); Route::put('leads/:id',$c.'editLead');
    Route::get('leads/:id/matches',$c.'matches'); Route::post('leads/:id/assign',$c.'assign');
    Route::get('workorders/:id',$c.'task');
    Route::get('workorders/:id/notice',$c.'taskNotice'); Route::post('workorders/:id/notice/retry',$c.'retryNotice');
    foreach(['accept','reject','follow','deal','verify','close','cancel'] as $action) Route::post('workorders/:id/'.$action,$c.$action);
    Route::post('commissions/:id/receive',$c.'receive'); Route::post('commissions/:id/refund',$c.'refund');
})->middleware([SafeErrors::class,AdminCheckToken::class,AdminCheckRole::class,PermissionGate::class])->completeMatch();
