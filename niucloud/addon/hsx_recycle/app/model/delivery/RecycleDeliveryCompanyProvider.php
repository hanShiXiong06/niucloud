<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\delivery;

use core\base\BaseModel;

/**
 * 回收-快递公司服务商绑定模型
 * 一家公司可对接多个服务商，每个服务商有各自编码与"是否出面单"能力。
 * @package addon\hsx_recycle\app\model\delivery
 */
class RecycleDeliveryCompanyProvider extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_delivery_company_provider';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

    protected $json = ['exp_type', 'print_style'];
    protected $jsonAssoc = true;

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('site_id', '=', $value);
        }
    }

    public function searchCompanyIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('company_id', '=', $value);
        }
    }

    public function searchProviderAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('provider', '=', $value);
        }
    }

    public function searchElectronicSheetSwitchAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('electronic_sheet_switch', '=', $value);
        }
    }
}
