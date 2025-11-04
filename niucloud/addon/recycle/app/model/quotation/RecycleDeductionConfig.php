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
     * JSON字段
     * @var array
     */
    protected $json = ['deduction_items'];

    /**
     * JSON字段自动转换为数组
     * @var bool
     */
    protected $jsonAssoc = true;

    /**
     * 追加属性
     * @var array
     */
    protected $append = ['remark_text'];

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
     * 获取器: 生成备注文本
     * 将 deduction_items 转换为易读的文本格式
     */
    public function getRemarkTextAttr($value, $data)
    {
        if (empty($data['deduction_items'])) {
            return '';
        }

        $items = is_string($data['deduction_items']) 
            ? json_decode($data['deduction_items'], true) 
            : $data['deduction_items'];

        if (empty($items) || !is_array($items)) {
            return '';
        }

        $lines = [];
        foreach ($items as $item) {
            if (!empty($item['item']) && !empty($item['deduction'])) {
                $unit = $item['unit'] ?? '';
                $lines[] = $item['item'] . '：' . $item['deduction'] . $unit;
            }
        }

        return implode("\n", $lines);
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
     * 搜索器: 商品系列
     */
    public function searchGoodsSeriesAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('goods_series', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器: 报价类型
     */
    public function searchPriceTypeAttr($query, $value, $data)
    {
        if ($value) {
            $query->where('price_type', $value);
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

