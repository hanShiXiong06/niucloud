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

namespace addon\recycle\app\service\api\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationData;
use addon\recycle\app\dict\quotation\QuotationDict;
use addon\recycle\app\service\admin\quotation\DeductionConfigService;
use core\base\BaseApiService;
use think\facade\Cache;

/**
 * 报价查询服务（移动端）
 * Class QuotationPriceService
 * @package addon\recycle\app\service\api\quotation
 */
class QuotationPriceService extends BaseApiService
{
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleQuotationData();
    }

    /**
     * 获取报价数据列表
     * @param array $where
     * @return array
     */
    public function getList(array $where = []): array
    {
        // 如果没有传入任何有效的筛选条件，默认查询最新价格（不限日期）
        $hasAnyFilter = (!empty($where['quotation_id']) && $where['quotation_id'] !== '') || 
                       (!empty($where['price_name']) && $where['price_name'] !== '') || 
                       (!empty($where['goods_id']) && $where['goods_id'] !== '') || 
                       (!empty($where['goods_name']) && $where['goods_name'] !== '') || 
                       (!empty($where['capacity']) && $where['capacity'] !== '') || 
                       (!empty($where['price_date']) && $where['price_date'] !== '') || 
                       (isset($where['is_current']) && $where['is_current'] !== '');
        
        // if (!$hasAnyFilter) {
        //     // 没有任何筛选条件时，默认只查询最新价格
        //     $where['is_current'] = QuotationDict::IS_CURRENT_YES;
        // }

        // // 如果没有指定日期，查询今天的报价
        // if (empty($where['price_date'])) {
        //     $where['price_date'] = date('Y-m-d');
        // }

        // 生成缓存key
        $cacheKey = 'quotation_price_api:' . $this->site_id . ':' . md5(json_encode($where));
        $cacheTag = 'quotation_data_' . $this->site_id;
        
        // 尝试从缓存获取
        $cacheData = Cache::get($cacheKey);
        if ($cacheData !== null) {
            return $cacheData;
        }

        // 从 Model 获取格式化后的数据列表
        $list = $this->model->getFormattedList($where, $this->site_id);
        $originalCount = count($list);
        
        // 合并同型号同容量的多条记录：将不同等级规格的价格合并到 prices 对象中
        $mergedData = [];
        $processedCount = 0; // 记录处理的记录数
        $skippedCount = 0; // 记录跳过的记录数（没有 grade_spec_name 或 price 的记录）
        $mergeStats = []; // 记录每个合并键的合并统计
        
        foreach ($list as $item) {
            $processedCount++;
            // 使用 quotation_id + price_name + goods_id + capacity_answer_id 作为合并键
            // 注意：如果 capacity_answer_id 为 0，使用 capacity_id 作为备用，确保合并键的唯一性
            $capacityKey = $item['capacity_answer_id'] ?? 0;
            if ($capacityKey <= 0) {
                $capacityKey = $item['capacity_id'] ?? 0;
            }
            
            $mergeKey = sprintf(
                '%d_%s_%d_%d',
                $item['quotation_id'] ?? 0,
                $item['price_name'] ?? '',
                $item['goods_id'] ?? 0,
                $capacityKey
            );
            
            // 记录合并键信息（用于调试）
            if (!isset($mergeStats[$mergeKey])) {
                $mergeStats[$mergeKey] = [
                    'count' => 0,
                    'grade_specs' => [],
                    'quotation_id' => $item['quotation_id'] ?? 0,
                    'goods_id' => $item['goods_id'] ?? 0,
                    'goods_name' => $item['goods_name'] ?? '',
                    'capacity' => $item['capacity'] ?? '',
                    'price_name' => $item['price_name'] ?? ''
                ];
            }
            $mergeStats[$mergeKey]['count']++;
            
            if (!isset($mergedData[$mergeKey])) {
                // 第一条记录，初始化 prices 数组
                $mergedData[$mergeKey] = $item;
                $mergedData[$mergeKey]['prices'] = [];
                
                // 如果有 grade_spec_name 和 price，添加到 prices
                // 注意：即使 price 为 0 或负数，也要记录，因为可能是有意义的默认值
                if (!empty($item['grade_spec_name']) && isset($item['price'])) {
                    // 从 item 的 prices 数组中查找对应的配置项，如果没有则创建新的
                    $priceItem = null;
                    foreach ($item['prices'] as $price) {
                        if (isset($price['name']) && $price['name'] === $item['grade_spec_name']) {
                            $priceItem = $price;
                            break;
                        }
                    }
                    
                    // 如果没有找到，创建一个新的价格项
                    if ($priceItem === null) {
                        $priceItem = [
                            'id' => $item['id'],
                            'name' => $item['grade_spec_name'],
                            'price' => floatval($item['price'])
                        ];
                    }
                    
                    $mergedData[$mergeKey]['prices'][] = $priceItem;
                    $mergeStats[$mergeKey]['grade_specs'][] = $item['grade_spec_name'];
                } else {
                    // 如果没有 grade_spec_name，直接使用 item 的 prices 数组
                    if (!empty($item['prices']) && is_array($item['prices'])) {
                        $mergedData[$mergeKey]['prices'] = $item['prices'];
                    }
                    
                    // 记录缺少 grade_spec_name 或 price 的记录
                    if (empty($item['grade_spec_name']) || !isset($item['price'])) {
                        $skippedCount++;
                    }
                }
                
                // 清空 grade_spec_id 和 grade_spec_name，因为合并后包含多个等级规格
                $mergedData[$mergeKey]['grade_spec_id'] = null;
                $mergedData[$mergeKey]['grade_spec_name'] = null;
            } else {
                // 后续记录，将 grade_spec_name 和 price 合并到 prices 数组
                // 注意：即使 price 为 0 或负数，也要记录，因为可能是有意义的默认值
                if (!empty($item['grade_spec_name']) && isset($item['price'])) {
                    // 确保 prices 是数组
                    if (!is_array($mergedData[$mergeKey]['prices'])) {
                        $mergedData[$mergeKey]['prices'] = [];
                    }
                    
                    // 检查是否已经存在相同的 grade_spec_name（通过 name 字段匹配）
                    $existingIndex = null;
                    foreach ($mergedData[$mergeKey]['prices'] as $index => $existingPrice) {
                        if (isset($existingPrice['name']) && $existingPrice['name'] === $item['grade_spec_name']) {
                            $existingIndex = $index;
                            break;
                        }
                    }
                    
                    // 如果存在相同的配置项且价格不同，更新价格
                    if ($existingIndex !== null && 
                        $mergedData[$mergeKey]['prices'][$existingIndex]['price'] != floatval($item['price'])) {
                        // 更新价格
                        $mergedData[$mergeKey]['prices'][$existingIndex]['price'] = floatval($item['price']);
                    } elseif ($existingIndex === null) {
                        // 如果不存在，添加新的价格项
                        // 从 item 的 prices 数组中查找对应的配置项，如果没有则创建新的
                        $priceItem = null;
                        foreach ($item['prices'] as $price) {
                            if (isset($price['name']) && $price['name'] === $item['grade_spec_name']) {
                                $priceItem = $price;
                                break;
                            }
                        }
                        
                        // 如果没有找到，创建一个新的价格项
                        if ($priceItem === null) {
                            $priceItem = [
                                'id' => $item['id'],
                                'name' => $item['grade_spec_name'],
                                'price' => floatval($item['price'])
                            ];
                        }
                        
                        $mergedData[$mergeKey]['prices'][] = $priceItem;
                    }
                    
                    $mergeStats[$mergeKey]['grade_specs'][] = $item['grade_spec_name'];
                } else {
                    // 如果没有 grade_spec_name，直接合并 item 的 prices 数组（去重）
                    if (!empty($item['prices']) && is_array($item['prices'])) {
                        foreach ($item['prices'] as $price) {
                            if (isset($price['name'])) {
                                // 检查是否已存在
                                $exists = false;
                                foreach ($mergedData[$mergeKey]['prices'] as $existingPrice) {
                                    if (isset($existingPrice['name']) && $existingPrice['name'] === $price['name']) {
                                        $exists = true;
                                        break;
                                    }
                                }
                                if (!$exists) {
                                    $mergedData[$mergeKey]['prices'][] = $price;
                                }
                            }
                        }
                    }
                    
                    // 记录缺少 grade_spec_name 或 price 的记录
                    if (empty($item['grade_spec_name']) || !isset($item['price'])) {
                        $skippedCount++;
                    }
                }
            }
        }
        
        // 检查 prices 是否为空（prices 现在已经是数组格式）
        $emptyPricesCount = 0; // 记录 prices 为空的记录数
        foreach ($mergedData as &$mergedItem) {
            // 确保 prices 是数组格式
            if (!is_array($mergedItem['prices'])) {
                $mergedItem['prices'] = [];
            }
            
            // 检查 prices 是否为空
            if (empty($mergedItem['prices']) || count($mergedItem['prices']) === 0) {
                $emptyPricesCount++;
            }
        }
        unset($mergedItem);
        
        // 将合并后的数据转换为数组，并重置索引
        $list = array_values($mergedData);
        
        // 获取扣费配置服务实例，填充 add_value_info
        $deductionService = (new DeductionConfigService())->setSiteId($this->site_id);
        foreach ($list as &$item) {
            // 根据 model_id 和 goods_id 查找匹配的扣费配置，填充 add_value_info
            $modelId = $item['model_id'] ?? 0;
            $goodsId = $item['goods_id'] ?? 0;
            
            if ($modelId > 0 && $goodsId > 0) {
                $matchedConfigs = $deductionService->getMatchingConfigsByModelId($modelId, $goodsId);
                if (!empty($matchedConfigs)) {
                    // 取第一个匹配的配置
                    $config = $matchedConfigs[0];
                    // 将 add_value_info 仅 = id 不要json 
                    $item['add_value_info'] = $config['id'];
                }
            }
        }
        unset($item);
        
        // 处理数据格式，转换为 uni-app 前端需要的格式
        foreach ($list as &$item) {
            // 将 prices 从数组格式 [{id, name, price}] 转换为对象格式 {configName: {original, final}}
            $formattedPrices = [];
            
            if (!empty($item['prices']) && is_array($item['prices'])) {
                foreach ($item['prices'] as $priceItem) {
                    if (isset($priceItem['name']) && isset($priceItem['price'])) {
                        $configName = $priceItem['name'];
                        $priceValue = floatval($priceItem['price']);
                        $formattedPrices[$configName] = $priceValue;
                    }
                }
            }
            
            $item['prices'] = $formattedPrices;

            // 处理备注信息（value_info）
            // 优先使用 deductionConfig 的 remark_text，如果没有则使用 add_value_info
            if (!empty($item['deductionConfig']) && !empty($item['deductionConfig']['remark_text'])) {
                $item['value_info'] = $item['deductionConfig']['remark_text'];
            } elseif (!isset($item['value_info'])) {
                // 如果没有关联的扣费配置，value_info 为空（前端会使用 add_value_info 作为备用）
                $item['value_info'] = '';
            }
        }

        // 设置缓存（使用tag，当天数据24小时，历史数据1小时）
        $cacheTime = isset($where['price_date']) && $where['price_date'] == date('Y-m-d') ? 86400 : 3600;
        Cache::tag($cacheTag)->set($cacheKey, $list, $cacheTime);

        return $list;
    }

    /**
     * 获取报价类型列表
     * @return array
     */
    public function getPriceTypes(): array
    {
        $priceTypes = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['is_current', '=', QuotationDict::IS_CURRENT_YES]
            ])
            ->field('quotation_id, price_name')
            ->group('quotation_id, price_name')
            ->select()
            ->toArray();

        return $priceTypes;
    }
}

