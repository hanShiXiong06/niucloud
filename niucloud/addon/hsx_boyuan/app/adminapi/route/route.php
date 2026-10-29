<?php
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
Route::group('boyuan',function () {
    $c='addon\\hsx_boyuan\\app\\adminapi\\controller\\Workbench@';
    Route::get('config',$c.'config'); Route::post('config',$c.'saveConfig'); Route::post('probe',$c.'probe');
    Route::get('tasks',$c.'tasks'); Route::get('tasks/:id',$c.'detail'); Route::post('tasks/:id/action',$c.'action');
    Route::get('mappings',$c.'mappings'); Route::post('mappings',$c.'saveMapping'); Route::get('references',$c.'references');
    Route::get('staff',$c.'staff'); Route::get('logs',$c.'logs');
})->middleware([AdminCheckToken::class,AdminCheckRole::class]);
