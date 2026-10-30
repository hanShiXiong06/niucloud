<?php
use think\facade\Route;
use app\adminapi\middleware\{AdminCheckToken,AdminCheckRole};
Route::group('boyuan',function () {
    $c='addon\\hsx_boyuan\\app\\adminapi\\controller\\Workbench@';
    Route::get('config',$c.'config'); Route::post('config',$c.'saveConfig'); Route::post('probe',$c.'probe');
    Route::get('tasks',$c.'tasks'); Route::get('tasks/:id',$c.'detail'); Route::post('tasks/:id/action',$c.'action');
    Route::get('mappings',$c.'mappings'); Route::post('mappings',$c.'saveMapping'); Route::get('references',$c.'references');
    Route::get('staff',$c.'staff'); Route::get('logs',$c.'logs');
    Route::get('reference-sync',$c.'referenceJobs'); Route::post('reference-sync',$c.'syncReferences'); Route::post('reference-sync/:id/retry',$c.'retryReference');
    Route::get('mapping-sources',$c.'mappingSources');
    Route::post('reference-search',$c.'references');
    Route::post('task-batch',$c.'batch'); Route::post('mapping-candidates',$c.'mappingCandidates');
    Route::post('task-batch-status',$c.'batchStatus');
})->middleware([AdminCheckToken::class,AdminCheckRole::class]);
