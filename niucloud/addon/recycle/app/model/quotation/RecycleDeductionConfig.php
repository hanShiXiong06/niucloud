<?php

namespace addon\recycle\app\model\quotation;

use core\base\BaseModel;

/**
 * 扣费配置模型
 */
class RecycleDeductionConfig extends BaseModel
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
    protected $name = 'recycle_deduction_config';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

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
     * 状态类型
     */
    public function getStatusTextAttr($value, $data)
    {
        return $data['is_enable'] == 1 ? '启用' : '禁用';
    }

    /**
     * 搜索器: 站点ID
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('site_id', $value);
        }
    }

    /**
     * 搜索器: 配置名称
     */
    public function searchConfigNameAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('config_name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器: 型号ID
     */
    public function searchModelIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('model_id', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器: 报价ID
     */
    public function searchPriceIdAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('price_id', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器: 是否启用
     */
    public function searchIsEnableAttr($query, $value, $data)
    {
        if ($value !== '') {
            $query->where('is_enable', $value);
        }
    }
}

