<?php
declare(strict_types=1);
use think\facade\Route;
use app\api\middleware\{ApiChannel,ApiCheckToken};
use addon\hsx_lead\app\middleware\SafeErrors;
Route::group('hsx_lead/merchant',function(){
    $c='addon\\hsx_lead\\app\\api\\controller\\Merchant@';
    Route::get('workbench',$c.'workbench');
    Route::get('tasks/:id',$c.'detail');
    foreach(['accept','reject','follow','deal','close'] as $action) Route::post('tasks/:id/'.$action,$c.$action);
})->middleware(SafeErrors::class)->middleware(ApiChannel::class)->middleware(ApiCheckToken::class,true)->completeMatch();
