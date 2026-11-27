<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\model;

use core\base\BaseModel;

/**
 * 库位责任分配（人 ↔ 库位 多对多）
 *
 * 一个库位可分配给多个员工，一个员工也可负责多个库位。
 * 员工端「我的待办」据此过滤：只看自己负责库位里的设备；管理员看全部。
 */
class DeviceAssetLocationAssign extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'device_asset_location_assign';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = false;
}
