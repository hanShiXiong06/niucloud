<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\model;

use core\base\BaseModel;

/**
 * 设备定价工单
 */
class DeviceAssetPriceOrder extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'device_asset_price_order';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = 'update_at';

    public function asset()
    {
        return $this->belongsTo(DeviceAssetItem::class, 'asset_id', 'id');
    }
}
