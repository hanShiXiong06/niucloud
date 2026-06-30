<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\delivery;

use core\base\BaseModel;

/**
 * 回收-电子面单模板模型
 *
 * 一个电子面单 = 一份可管理的发件配置：绑定快递公司 + 执行服务商(provider) +
 * 业务类型/打印样式 + 月结/网点/发件人 + 打印方式(print_channel)。
 * 业务发件时引用本模板，由对应 provider(易速/快递100) 去下单出面单。
 *
 * @package addon\hsx_recycle\app\model\delivery
 */
class RecycleExpressSheet extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'recycle_express_sheet';
    protected $autoWriteTimestamp = true;
    protected $createTime = 'create_at';
    protected $updateTime = 'update_at';

    /**
     * 关联快递公司
     */
    public function company()
    {
        return $this->belongsTo(RecycleDeliveryCompany::class, 'express_company_id', 'company_id');
    }

    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('site_id', '=', $value);
        }
    }

    public function searchTemplateNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->whereLike('template_name', '%' . $value . '%');
        }
    }

    public function searchExpressCompanyIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('express_company_id', '=', $value);
        }
    }

    public function searchProviderAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('provider', '=', $value);
        }
    }

    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', '=', $value);
        }
    }
}
