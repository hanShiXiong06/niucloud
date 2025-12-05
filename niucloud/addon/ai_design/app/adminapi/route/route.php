<?php
/**
 * AI设计插件管理端路由
 */
use think\facade\Route;

Route::group('ai_design', function () {
    Route::get('design/index', 'addon\ai_design\app\adminapi\controller\design\Index@index');
    Route::post('design/create', 'addon\ai_design\app\adminapi\controller\design\Index@create');
    Route::get('design/detail', 'addon\ai_design\app\adminapi\controller\design\Index@detail');
    Route::put('design/update', 'addon\ai_design\app\adminapi\controller\design\Index@update');
    Route::delete('design/delete', 'addon\ai_design\app\adminapi\controller\design\Index@delete');
})->middleware([
    \app\http\middleware\AdminCheckToken::class,
    \app\http\middleware\AdminLog::class
]);

