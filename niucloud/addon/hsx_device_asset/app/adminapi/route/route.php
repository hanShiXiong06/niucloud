<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('device_asset', function () {
    Route::get('pool', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@pool');
    Route::get('lists', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@lists');
    Route::get('info/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@info');
    Route::post('import', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@import');
    Route::post('scan', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@scan');
    Route::post('photo_task/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@createPhotoTask');
    Route::post('media/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@saveMedia');
    Route::post('media/review/:media_id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@reviewMedia');
    Route::post('photos/confirm/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@confirmPhotos');
    Route::post('price/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@price');
    Route::get('export', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@export');
    Route::post('export', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@export');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
]);
