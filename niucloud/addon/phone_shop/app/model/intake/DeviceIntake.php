<?php
// +----------------------------------------------------------------------
// | 二手商城 phone_shop · 待上架货源(中台定价设备)模型
// +----------------------------------------------------------------------

namespace addon\phone_shop\app\model\intake;

use core\base\BaseModel;

/**
 * 待上架货源暂存模型
 * Class DeviceIntake
 * @package addon\phone_shop\app\model\intake
 */
class DeviceIntake extends BaseModel
{
    protected $pk = 'intake_id';

    protected $name = 'phone_shop_device_intake';

    // json 字段
    protected $json = [ 'images', 'qc_info', 'raw_payload' ];
    protected $jsonAssoc = true;

    /**
     * 状态：0待建品 1已建品 2忽略
     */
    const STATUS_PENDING = 0;
    const STATUS_BUILT = 1;
    const STATUS_IGNORED = 2;

    public function getStatusNameAttr($value, $data)
    {
        $map = [ 0 => '待建品', 1 => '已建品', 2 => '已忽略' ];
        return $map[ $data[ 'status' ] ?? 0 ] ?? '';
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }

    public function searchErpAssetIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('erp_asset_id', $value);
        }
    }

    public function searchModelNameAttr($query, $value, $data)
    {
        if ($value != '') {
            $query->where('model_name', 'like', '%' . $value . '%');
        }
    }
}
