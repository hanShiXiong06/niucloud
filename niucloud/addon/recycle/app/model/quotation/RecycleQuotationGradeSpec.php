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
 * 报价等级规格模型
 * Class RecycleQuotationGradeSpec
 * @package addon\recycle\app\model\quotation
 */
class RecycleQuotationGradeSpec extends BaseModel
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
    protected $name = 'recycle_quotation_grade_spec';

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
     * 搜索器:规格名称
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchSpecNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("spec_name", "like", "%" . $value . "%");
        }
    }

    /**
     * 搜索器:是否同步
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchSyncEnableAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where("sync_enable", $value);
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

