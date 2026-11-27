<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\model;

use core\base\BaseModel;

/**
 * 设备资产操作日志
 */
class DeviceAssetOperationLog extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'device_asset_operation_log';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = false;

    protected $json = ['payload'];
}
