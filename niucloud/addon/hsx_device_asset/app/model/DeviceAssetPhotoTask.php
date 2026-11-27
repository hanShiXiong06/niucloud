<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\model;

use core\base\BaseModel;

/**
 * 设备拍照任务
 */
class DeviceAssetPhotoTask extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'device_asset_photo_task';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = 'update_at';

    public function asset()
    {
        return $this->belongsTo(DeviceAssetItem::class, 'asset_id', 'id');
    }

    public function media()
    {
        return $this->hasMany(DeviceAssetMedia::class, 'task_id', 'id')->order('sort asc,id asc');
    }
}
