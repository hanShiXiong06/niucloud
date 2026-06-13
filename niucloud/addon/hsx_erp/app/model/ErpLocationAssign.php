<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

/**
 * 库位责任分配（人 ↔ 库位 多对多）
 *
 * 一个库位可分配给多个员工，一个员工也可负责多个库位（可跨仓）。
 * ERP 出库/调拨/暂存等操作据此过滤：普通员工只看自己负责库位里的设备；管理员看全部。
 * 与中台 device_asset_location_assign 各自独立、都引用同一套 ERP 库位 location_id。
 */
class ErpLocationAssign extends BaseModel
{
    protected $pk = 'id';

    protected $name = 'erp_location_assign';

    protected $autoWriteTimestamp = true;

    protected $createTime = 'create_at';

    protected $updateTime = false;
}
