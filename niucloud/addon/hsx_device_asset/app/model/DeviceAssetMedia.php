<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\model;

use addon\hsx_device_asset\app\dict\DeviceAssetDict;
use core\base\BaseModel;

/**
 * 设备图片/视频
 */
class DeviceAssetMedia extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'device_asset_media';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = 'update_at';

    protected $append = ['status_name'];

    public function getStatusNameAttr($value, $data): string
    {
        return DeviceAssetDict::mediaStatusName((string)($data['status'] ?? ''));
    }

    public function asset()
    {
        return $this->belongsTo(DeviceAssetItem::class, 'asset_id', 'id');
    }

    public function task()
    {
        return $this->belongsTo(DeviceAssetPhotoTask::class, 'task_id', 'id');
    }
}
