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
use addon\recycle\app\model\quotation\RecycleDeductionConfig;
use addon\recycle\app\model\quotation\RecycleQuotationModel;
use addon\recycle\app\model\quotation\RecycleQuotationCapacity;
use addon\recycle\app\model\quotation\RecycleQuotationGradeSpec;

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

    // json字段

    // protected $json = ['config_selected'];
    // protected $json = ['config_items'];
    // protected $json = ['add_value_info'];
    // protected $json = ['prices'];


    // json字段自动转换
    // protected $jsonAssoc = true;

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
    /**
     * 关联扣费配置表
     * 本表字段: deduction_config_id (int) 存储 deduction_config 的 id
     * 关联表: recycle_deduction_config
     */
    public function deductionConfig()
    {
        return $this->belongsTo(RecycleDeductionConfig::class, 'deduction_config_id', 'id')
            ->bind(['value_info' => 'remark_text']);
    }

    /**
     * 关联型号表
     */
    public function model()
    {
        return $this->belongsTo(RecycleQuotationModel::class, 'model_id', 'id');
    }

    /**
     * 关联内存表
     */
    public function capacity()
    {
        return $this->belongsTo(RecycleQuotationCapacity::class, 'capacity_id', 'id');
    }

    /**
     * 关联等级规格表
     */
    public function gradeSpec()
    {
        return $this->belongsTo(RecycleQuotationGradeSpec::class, 'grade_spec_id', 'id');
    }

    /**
     * 格式化 prices 数据：从键值对格式转换为对象数组格式
     * @param array $item 数据项（包含 prices 和 config_items 字段）
     * @return array 格式化后的 prices 数组
     */
    public function formatPrices(array $item): array
    {
        // 解析 prices JSON
        // 支持两种情况：
        // 1. prices 是 JSON 字符串（需要解析）
        // 2. prices 已经是数组（ThinkPHP 可能自动解析了 JSON 字段）
        $pricesRaw = $item['prices'] ?? null;
        $prices = [];
        
        if ($pricesRaw !== null && $pricesRaw !== '') {
            if (is_string($pricesRaw)) {
                // 如果是字符串，尝试解析 JSON
                $prices = json_decode($pricesRaw, true) ?: [];
            } elseif (is_array($pricesRaw)) {
                // 如果已经是数组，直接使用
                $prices = $pricesRaw;
            }
        }
        
        // 将 prices 从键值对格式转换为对象数组格式
        // 从 {花机:1000,内爆:2000} 转换为 [{id:xxx, name:"花机", price:1000}, {id:xxx, name:"内爆", price:2000}]
        $pricesArray = [];
        if (!empty($prices) && is_array($prices)) {
            // 解析 config_items 获取配置项详细信息（包含 question_id）
            $configItems = [];
            $configItemsJson = $item['config_items'] ?? '';
            if (!empty($configItemsJson)) {
                if (is_string($configItemsJson)) {
                    $configItems = json_decode($configItemsJson, true) ?: [];
                } elseif (is_array($configItemsJson)) {
                    $configItems = $configItemsJson;
                }
            }
            
            // 构建配置项名称到 question_id 的映射
            $configNameToIdMap = [];
            foreach ($configItems as $configItem) {
                if (isset($configItem['question_name']) && isset($configItem['question_id'])) {
                    $configNameToIdMap[$configItem['question_name']] = $configItem['question_id'];
                }
            }
            
            // 转换 prices 格式
            $index = 1;
            foreach ($prices as $configName => $price) {
                // 优先使用 config_items 中的 question_id，如果没有则使用递增索引
                $configId = $configNameToIdMap[$configName] ?? ($item['id'] * 1000 + $index);
                $pricesArray[] = [
                    'id' => $configId,
                    'name' => $configName,
                    'price' => floatval($price)
                ];
                $index++;
            }
        }
        
        return $pricesArray;
    }
    
    /**
     * 获取格式化后的报价数据列表（包含关联数据）
     * @param array $where 查询条件
     * @param int $siteId 站点ID
     * @return array
     */
    public function getFormattedList(array $where, int $siteId): array
    {
        // 构建查询
        $query = $this->where([['site_id', '=', $siteId]]);
        
        // 处理通过关联表字段的搜索
        if (!empty($where['goods_name'])) {
            $query->whereHas('model', function($q) use ($where) {
                $q->where('goods_name', 'like', '%' . $where['goods_name'] . '%');
            });
            unset($where['goods_name']);
        }
        
        if (!empty($where['capacity'])) {
            $query->whereHas('capacity', function($q) use ($where) {
                $q->where('capacity', 'like', '%' . $where['capacity'] . '%');
            });
            unset($where['capacity']);
        }
        
        // 使用搜索器处理其他条件
        $field = 'id,site_id,request_id,quotation_id,price_name,model_id,capacity_id,grade_spec_id,deduction_config_id,price,grade_spec_name,goods_id,goods_name,group_key,capacity as data_capacity,capacity_answer_id as data_capacity_answer_id,config_items,config_selected,prices,add_value_info,price_date,is_current,create_at,update_at';
        
        $list = $query->withSearch(['quotation_id', 'price_name', 'goods_id', 'goods_name', 'capacity', 'price_date', 'is_current'], $where)
            ->with([
                'model' => function($q) {
                    $q->field('id,goods_id,goods_name');
                },
                'capacity' => function($q) {
                    $q->field('id,model_id,capacity,capacity_answer_id');
                },
                'gradeSpec' => function($q) {
                    $q->field('id,spec_name');
                },
                'deductionConfig' => function($q) {
                    $q->field('id,remark_text');
                }
            ])
            ->field($field)
            ->order('id asc')
            ->select()
            ->toArray();
        
        // 格式化数据：优先使用关联表的数据，如果没有关联数据则使用冗余字段（兼容旧数据）
        foreach ($list as &$item) {
            // 格式化 prices
            $item['prices'] = $this->formatPrices($item);
            
            // 优先使用关联表的数据覆盖冗余字段
            if (!empty($item['model'])) {
                $item['goods_id'] = $item['model']['goods_id'] ?? $item['goods_id'];
                $item['goods_name'] = $item['model']['goods_name'] ?? $item['goods_name'];
            }
            
            // 处理容量信息（使用别名避免字段名冲突）
            $mainCapacityId = $item['capacity_id'] ?? 0;
            $redundantCapacity = $item['data_capacity'] ?? '';
            $redundantCapacityAnswerId = $item['data_capacity_answer_id'] ?? 0;
            
            if (isset($item['capacity']) && is_array($item['capacity']) && !empty($item['capacity']['id'])) {
                $capacityData = $item['capacity'];
                $item['capacity_id'] = $capacityData['id'] ?? $mainCapacityId;
                $item['capacity'] = $capacityData['capacity'] ?? $redundantCapacity;
                $item['capacity_answer_id'] = $capacityData['capacity_answer_id'] ?? $redundantCapacityAnswerId;
            } else {
                $item['capacity_id'] = $mainCapacityId;
                $item['capacity'] = $redundantCapacity ?: '';
                $item['capacity_answer_id'] = $redundantCapacityAnswerId ?: 0;
            }
            
            // 清理临时字段
            unset($item['data_capacity'], $item['data_capacity_answer_id']);
            
            // 处理等级规格信息
            if (!empty($item['gradeSpec'])) {
                $item['grade_spec_name'] = $item['gradeSpec']['spec_name'] ?? $item['grade_spec_name'] ?? '';
            }
            
            // 处理扣费配置信息
            if (!empty($item['deductionConfig'])) {
                $item['value_info'] = $item['deductionConfig']['remark_text'] ?? ($item['value_info'] ?? '');
            }
            
            // 使用冗余字段 price（如果存在）
            if (!isset($item['price']) || $item['price'] == 0) {
                if (!empty($prices)) {
                    $item['price'] = reset($prices);
                }
            }
            
            // 清理关联数据
            unset($item['model'], $item['gradeSpec'], $item['deductionConfig']);
        }
        
        return $list;
    }
    
}
