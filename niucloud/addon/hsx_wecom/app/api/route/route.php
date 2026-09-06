<?php
declare(strict_types=1);

use think\facade\Route;

// 企业微信服务器与浏览器回调均不携带 NiuCloud 登录态，不能挂站点或 Token 中间件。
Route::any('wecom/provider/event/:channel', 'addon\hsx_wecom\app\api\controller\ProviderCallback@event');
Route::get('wecom/provider/authorize/complete/:channel', 'addon\hsx_wecom\app\api\controller\ProviderAuthorize@complete');
Route::get('wecom/provider/member/complete/:channel', 'addon\hsx_wecom\app\api\controller\ProviderAuthorize@member');
