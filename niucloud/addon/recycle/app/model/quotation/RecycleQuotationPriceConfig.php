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
 * 价格配置模型
 * Class RecycleQuotationPriceConfig
 * @package addon\recycle\app\model\quotation
 */
class RecycleQuotationPriceConfig extends BaseModel
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
    protected $name = 'recycle_quotation_price_config';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * JSON字段
     * @var array
     */
    protected $json = ['sku_list'];

    /**
     * JSON字段自动转换为数组
     * @var bool
     */
    protected $jsonAssoc = true;

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
     * 搜索器:配置类型
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchConfigTypeAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("config_type", $value);
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
            $query->where("goods_id", $value);
        }
    }

    /**
     * 搜索器:容量
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCapacityAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("capacity", $value);
        }
    }

    /**
     * 搜索器:配置项名称
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchConfigItemNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("config_item_name", "like", "%" . $value . "%");
        }
    }

    /**
     * 搜索器:分组key
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchGroupKeyAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("group_key", $value);
        }
    }

    /**
     * 搜索器:是否启用
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchIsEnableAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("is_enable", $value);
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

