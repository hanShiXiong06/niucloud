<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\recycle\app\model\quotation;

use core\base\BaseModel;

/**
 * 报价请求记录模型
 * Class RecycleQuotationRequest
 * @package addon\recycle\app\model\quotation
 */
class RecycleQuotationRequest extends BaseModel
{
    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'recycle_quotation_request';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * 创建时间字段
     * @var string
     */
    protected $createTime = 'create_at';

    /**
     * 更新时间字段
     * @var string
     */
    protected $updateTime = 'update_at';

    /**
     * 搜索器:报价单ID
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchQuotationIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("quotation_id", $value);
        }
    }

    /**
     * 搜索器:价格名称
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchPriceNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("price_name", "like", "%" . $value . "%");
        }
    }

    /**
     * 搜索器:请求状态
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchRequestStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("request_status", $value);
        }
    }

    /**
     * 搜索器:站点ID
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("site_id", $value);
        }
    }
}

