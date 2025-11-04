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
 * 报价数据模型
 * Class RecycleQuotationData
 * @package addon\recycle\app\model\quotation
 */
class RecycleQuotationData extends BaseModel
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
    protected $name = 'recycle_quotation_data';

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
     * 搜索器:商品ID
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchGoodsIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            // 支持逗号分隔的多个ID
            if (is_string($value) && strpos($value, ',') !== false) {
                $ids = array_filter(array_map('intval', explode(',', $value)));
                if (!empty($ids)) {
                    $query->whereIn("goods_id", $ids);
                }
            } else {
                $query->where("goods_id", $value);
            }
        }
    }

    /**
     * 搜索器:商品名称
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchGoodsNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("goods_name", "like", "%" . $value . "%");
        }
    }

    /**
     * 搜索器:内存容量
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCapacityAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("capacity", "like", "%" . $value . "%");
        }
    }

    /**
     * 搜索器:价格日期
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchPriceDateAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("price_date", $value);
        }
    }

    /**
     * 搜索器:是否当前价格
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchIsCurrentAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("is_current", $value);
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
