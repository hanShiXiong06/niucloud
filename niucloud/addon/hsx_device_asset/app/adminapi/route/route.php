<?php
declare(strict_types=1);

use app\adminapi\middleware\AdminCheckRole;
use app\adminapi\middleware\AdminCheckToken;
use app\adminapi\middleware\AdminLog;
use think\facade\Route;

Route::group('device_asset', function () {
    Route::get('pool', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@pool');
    Route::get('lists', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@lists');
    Route::get('stats', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@stats');
    Route::get('info/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@info');
    Route::post('import', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@import');
    Route::post('scan', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@scan');
    Route::post('photo_task/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@createPhotoTask');
    Route::post('media/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@saveMedia');
    Route::post('media/review/:media_id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@reviewMedia');
    Route::post('media/review_batch/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@reviewMediaBatch');
    Route::post('photos/confirm/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@confirmPhotos');
    Route::post('location/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@setLocation');
    Route::post('price/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@price');
    Route::post('re_push/:id', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@rePush');
    Route::get('export', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@export');
    Route::post('export', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAsset@export');

    // 库位责任分配（管理员）
    Route::get('assign/warehouse_tree', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAssetAssign@warehouseTree');
    Route::get('assign/staff_options', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAssetAssign@staffOptions');
    Route::get('assign/list', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAssetAssign@assignments');
    Route::post('assign/location_staff', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAssetAssign@setLocationStaff');
    Route::post('assign/staff_locations', 'addon\hsx_device_asset\app\adminapi\controller\DeviceAssetAssign@setStaffLocations');
})->middleware([
    AdminCheckToken::class,
    AdminCheckRole::class,
    AdminLog::class,
]);
