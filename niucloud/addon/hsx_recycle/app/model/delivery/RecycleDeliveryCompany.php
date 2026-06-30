<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\delivery;

use core\base\BaseModel;

/**
 * 回收-快递公司字典模型
 * @package addon\hsx_recycle\app\model\delivery
 */
class RecycleDeliveryCompany extends BaseModel
{
    protected $pk = 'company_id';
    protected $name = 'recycle_delivery_company';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

    // 业务类型 / 打印样式以 JSON 存储，读写自动转数组
    protected $json = ['exp_type', 'print_style'];
    protected $jsonAssoc = true;

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('site_id', '=', $value);
        }
    }

    public function searchCompanyNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->whereLike('company_name', '%' . $value . '%');
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', '=', $value);
        }
    }

    public function searchElectronicSheetSwitchAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('electronic_sheet_switch', '=', $value);
        }
    }
}
