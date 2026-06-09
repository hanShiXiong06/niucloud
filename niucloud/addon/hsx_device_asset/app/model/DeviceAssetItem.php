<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\model;

use addon\hsx_device_asset\app\dict\DeviceAssetDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use core\base\BaseModel;

/**
 * 设备资产
 */
class DeviceAssetItem extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'device_asset_item';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = 'update_at';

    protected $json = ['check_summary', 'ext_json'];

    protected $append = [
        'status_name',
        'photo_status_name',
        'price_status_name',
        'export_status_name',
    ];

    public function getStatusNameAttr($value, $data): string
    {
        return DeviceAssetDict::statusName((string)($data['status'] ?? ''));
    }

    public function getPhotoStatusNameAttr($value, $data): string
    {
        return DeviceAssetDict::photoStatusName((string)($data['photo_status'] ?? ''));
    }

    public function getPriceStatusNameAttr($value, $data): string
    {
        return DeviceAssetDict::priceStatusName((string)($data['price_status'] ?? ''));
    }

    public function getExportStatusNameAttr($value, $data): string
    {
        return DeviceAssetDict::exportStatusName((string)($data['export_status'] ?? ''));
    }

    public function media()
    {
        return $this->hasMany(DeviceAssetMedia::class, 'asset_id', 'id')->order('sort asc,id asc');
    }

    public function photoTasks()
    {
        return $this->hasMany(DeviceAssetPhotoTask::class, 'asset_id', 'id')->order('id desc');
    }

    public function priceOrders()
    {
        return $this->hasMany(DeviceAssetPriceOrder::class, 'asset_id', 'id')->order('id desc');
    }

    public function logs()
    {
        return $this->hasMany(DeviceAssetOperationLog::class, 'asset_id', 'id')->order('id desc');
    }

    public function recycleDevice()
    {
        return $this->belongsTo(RecycleDevice::class, 'device_id', 'id');
    }
}
