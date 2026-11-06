<?php
declare(strict_types=1);
use think\facade\Route;
use app\api\middleware\{ApiChannel,ApiCheckToken};
use addon\hsx_lead\app\middleware\SafeErrors;

Route::get('hsx_lead/cooperation/regions','addon\\hsx_lead\\app\\api\\controller\\Region@search')
    ->middleware(SafeErrors::class)->middleware(ApiChannel::class)->middleware(ApiCheckToken::class,true)->completeMatch();
