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
 * 报价型号模型
 * Class RecycleQuotationModel
 * @package addon\recycle\app\model\quotation
 */
class RecycleQuotationModel extends BaseModel
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
    protected $name = 'recycle_quotation_model';

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
     * JSON字段自动转换
     * @var bool
     */
    protected $jsonAssoc = true;

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
     * 搜索器:状态
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("status", $value);
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

