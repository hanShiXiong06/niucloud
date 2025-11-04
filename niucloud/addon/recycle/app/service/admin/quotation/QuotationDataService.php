<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationData;
use addon\recycle\app\model\quotation\RecycleQuotationModel;
use addon\recycle\app\dict\quotation\QuotationDict;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Cache;

/**
 * 报价数据服务
 * Class QuotationDataService
 * @package addon\recycle\app\service\admin\quotation
 */
class QuotationDataService extends BaseAdminService
{
    /**
     * @var RecycleQuotationData
     */
    protected $dataModel;

    /**
     * @var RecycleQuotationModel
     */
    protected $modelModel;

    public function __construct()
    {
        parent::__construct();
        $this->dataModel = new RecycleQuotationData();
        $this->modelModel = new RecycleQuotationModel();
    }

    /**
     * 解析并存储接口返回的数据
     * @param int $requestId 请求记录ID
     * @param array $data 接口返回的data数据
     * @return array
     */
    public function parseAndSaveData(int $requestId, array $data): array
    {
        $requestModel = new \addon\recycle\app\model\quotation\RecycleQuotationRequest();
        $requestInfo = $requestModel->where('id', $requestId)->findOrEmpty();
        
        if ($requestInfo->isEmpty()) {
            throw new CommonException('请求记录不存在');
        }

        $requestInfo = $requestInfo->toArray();
        $quotationId = $requestInfo['quotation_id'];
        $priceName = $requestInfo['price_name'];
        $priceDate = date('Y-m-d');
        $today = date('Y-m-d');

        // 检查当天是否已有请求
        $existingRequests = $requestModel
            ->where([
                ['quotation_id', '=', $quotationId],
                ['price_name', '=', $priceName],
                ['request_time', '>=', strtotime($today . ' 00:00:00')],
                ['request_time', '<', strtotime($today . ' 23:59:59')],
                ['id', '<>', $requestId]
            ])
            ->select()
            ->toArray();

        // 如果当天已有请求，将旧数据标记为非当前
        if (!empty($existingRequests)) {
            $this->dataModel
                ->where([
                    ['quotation_id', '=', $quotationId],
                    ['price_name', '=', $priceName],
                    ['price_date', '=', $priceDate],
                    ['is_current', '=', QuotationDict::IS_CURRENT_YES],
                ])
                ->update(['is_current' => QuotationDict::IS_CURRENT_NO]);
        }

        // 解析sku数据
        $skuData = $data['sku'] ?? [];
        $stats = [
            'models_added' => 0,
            'data_saved' => 0,
        ];

        // 收集所有需要添加的型号
        $modelsToAdd = [];
        foreach ($skuData as $group) {
            foreach ($group as $item) {
                $goodsId = $item['goods_id'] ?? 0;
                $goodsName = $item['goods_name'] ?? '';
                
                if ($goodsId > 0 && !empty($goodsName)) {
                    $modelsToAdd[$goodsId] = [
                        'goods_id' => $goodsId,
                        'goods_name' => $goodsName,
                    ];
                }
            }
        }

        // 批量检查和添加型号
        if (!empty($modelsToAdd)) {
            $this->batchAddModels($modelsToAdd);
            $stats['models_added'] = count($modelsToAdd);
        }

        // 解析并存储报价数据
        $batchData = [];
        foreach ($skuData as $groupKey => $group) {
            foreach ($group as $item) {
                $goodsId = $item['goods_id'] ?? 0;
                $goodsName = $item['goods_name'] ?? '';
                
                // 解析容量信息
                $capacity = '';
                $capacityAnswerId = 0;
                if (!empty($item['general_attr'])) {
                    foreach ($item['general_attr'] as $attr) {
                        if (isset($attr['question_name']) && strpos($attr['question_name'], '内存') !== false) {
                            $capacity = $attr['answer_name'] ?? '';
                            $capacityAnswerId = $attr['answer_id'] ?? 0;
                            break;
                        }
                    }
                }

                // 解析配置项和价格
                $configItems = $item['config_attr'] ?? [];
                $configSelected = $item['config_selected'] ?? [];
                $prices = [];
                
                \think\facade\Log::write("[数据导入] 开始解析 goods_id={$goodsId}, goods_name={$goodsName}, capacity={$capacity}", 'info');
                
                foreach ($configItems as $configItem) {
                    $configName = $configItem['question_name'] ?? '';
                    $originalPrice = $configItem['answer_name'] ?? 0;
                    
                    if (!empty($configName) && is_numeric($originalPrice)) {
                        $originalPrice = floatval($originalPrice);
                        
                        // 判断是否有价格配置，如果有则应用配置计算最终价格
                        $finalPrice = $this->calculateFinalPrice(
                            $goodsId,
                            $capacity,
                            $configName,
                            $originalPrice
                        );
                        
                        if ($finalPrice != $originalPrice) {
                            $diff = $finalPrice - $originalPrice;
                            $diffPercent = $originalPrice > 0 ? round(($diff / $originalPrice) * 100, 2) : 0;
                            \think\facade\Log::write("[价格配置已应用] {$configName}: {$originalPrice} → {$finalPrice} (变动: {$diff}, {$diffPercent}%)", 'info');
                        } else {
                            \think\facade\Log::write("[无价格配置] {$configName}: {$originalPrice} (保持不变)", 'info');
                        }
                        
                        // 保存最终价格（应用配置后的价格）
                        $prices[$configName] = $finalPrice;
                    }
                }
                
                if (!empty($prices)) {
                    \think\facade\Log::write("[最终保存] prices=" . json_encode($prices, JSON_UNESCAPED_UNICODE), 'info');
                }

                // 解析加/扣钱项说明
                $addValueInfo = '';
                if (!empty($item['add_value_attr'])) {
                    foreach ($item['add_value_attr'] as $addValue) {
                        $addValueInfo = $addValue['answer_name'] ?? '';
                        break;
                    }
                }

                $batchData[] = [
                    'site_id' => $this->site_id,
                    'request_id' => $requestId,
                    'quotation_id' => $quotationId,
                    'price_name' => $priceName,
                    'goods_id' => $goodsId,
                    'goods_name' => $goodsName,
                    'group_key' => $groupKey + 1,
                    'capacity' => $capacity,
                    'capacity_answer_id' => $capacityAnswerId,
                    'config_items' => json_encode($configItems, JSON_UNESCAPED_UNICODE),
                    'config_selected' => json_encode($configSelected, JSON_UNESCAPED_UNICODE),
                    'prices' => json_encode($prices, JSON_UNESCAPED_UNICODE),
                    'add_value_info' => $addValueInfo,
                    'price_date' => $priceDate,
                    'is_current' => QuotationDict::IS_CURRENT_YES,
                    'create_at' => time(),
                    'update_at' => time(),
                ];
            }
        }

        // 批量插入数据
        if (!empty($batchData)) {
            $this->dataModel->insertAll($batchData);
            $stats['data_saved'] = count($batchData);
        }

        // 清除相关缓存
        $this->clearCache();

        Log::info('报价数据解析完成', [
            'request_id' => $requestId,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * 批量添加型号
     * @param array $models
     * @return void
     */
    protected function batchAddModels(array $models): void
    {
        $existingGoodsIds = $this->modelModel
            ->where('site_id', $this->site_id)
            ->whereIn('goods_id', array_column($models, 'goods_id'))
            ->column('goods_id');

        $toInsert = [];
        foreach ($models as $model) {
            if (!in_array($model['goods_id'], $existingGoodsIds)) {
                $toInsert[] = [
                    'site_id' => $this->site_id,
                    'goods_id' => $model['goods_id'],
                    'goods_name' => $model['goods_name'],
                    'status' => QuotationDict::STATUS_ENABLED,
                    'create_at' => time(),
                    'update_at' => time(),
                ];
            }
        }

        if (!empty($toInsert)) {
            // 每批100条
            $chunks = array_chunk($toInsert, 100);
            foreach ($chunks as $chunk) {
                $this->modelModel->insertAll($chunk);
            }
        }
    }

    /**
     * 获取所有报价数据（不分页，用于表格展示）
     * @param array $where
     * @return array
     */
    public function getAll(array $where = []): array
    {
        // 如果没有传入任何有效的筛选条件，默认查询最新价格（不限日期）
        $hasAnyFilter = (!empty($where['quotation_id']) && $where['quotation_id'] !== '') || 
                       (!empty($where['price_name']) && $where['price_name'] !== '') || 
                       (!empty($where['goods_id']) && $where['goods_id'] !== '') || 
                       (!empty($where['goods_name']) && $where['goods_name'] !== '') || 
                       (!empty($where['capacity']) && $where['capacity'] !== '') || 
                       (!empty($where['price_date']) && $where['price_date'] !== '') || 
                       (isset($where['is_current']) && $where['is_current'] !== '');
        
        if (!$hasAnyFilter) {
            // 没有任何筛选条件时，默认只查询最新价格
            $where['is_current'] = QuotationDict::IS_CURRENT_YES;
        }

        $field = 'id,site_id,request_id,quotation_id,price_name,goods_id,goods_name,group_key,capacity,capacity_answer_id,config_items,config_selected,prices,add_value_info,price_date,is_current,create_at,update_at';
        
        $search_model = $this->dataModel->where([['site_id', '=', $this->site_id]])
            ->withSearch(['quotation_id', 'price_name', 'goods_id', 'goods_name', 'capacity', 'price_date', 'is_current'], $where)
            ->field($field)
            ->order('id asc');
        
        $list = $search_model->select()->toArray();
        
        // 转换 prices 为 price_detail 格式
        // 注意：prices 字段在数据导入时已经应用了价格配置，这里只需要转换格式
        foreach ($list as &$item) {
            $prices = json_decode($item['prices'], true) ?: [];
            $finalPrices = [];
            
            foreach ($prices as $configItemName => $price) {
                // prices 存储的已经是最终价格（导入时应用了配置）
                $finalPrices[$configItemName] = [
                    'original_price' => $price,
                    'final_price' => $price,
                    'price_status' => 0,  // 价格未变化
                    'price_difference' => 0,
                    'price_diff_percent' => 0,
                ];
            }
            
            $item['price_detail'] = $finalPrices;
        }
        
        return $list;
    }

    /**
     * 查询报价数据（应用价格配置）
     * @param array $where
     * @return array
     */
    public function getPage(array $where = []): array
    {
        // 如果没有传入任何有效的筛选条件，默认查询最新价格（不限日期）
        $hasAnyFilter = (!empty($where['quotation_id']) && $where['quotation_id'] !== '') || 
                       (!empty($where['price_name']) && $where['price_name'] !== '') || 
                       (!empty($where['goods_id']) && $where['goods_id'] !== '') || 
                       (!empty($where['goods_name']) && $where['goods_name'] !== '') || 
                       (!empty($where['capacity']) && $where['capacity'] !== '') || 
                       (!empty($where['price_date']) && $where['price_date'] !== '') || 
                       (isset($where['is_current']) && $where['is_current'] !== '');
        
        if (!$hasAnyFilter) {
            // 没有任何筛选条件时，默认只查询最新价格
            $where['is_current'] = QuotationDict::IS_CURRENT_YES;
        }

        // 生成缓存key
        $cacheKey = 'quotation_data:' . $this->site_id . ':' . md5(json_encode($where));
        $cacheTag = 'quotation_data_' . $this->site_id;
        
        // 尝试从缓存获取
        $cacheData = Cache::get($cacheKey);
        if ($cacheData !== null) {
            return $cacheData;
        }

        $field = 'id,site_id,request_id,quotation_id,price_name,goods_id,goods_name,group_key,capacity,capacity_answer_id,config_items,config_selected,prices,add_value_info,price_date,is_current,create_at,update_at';
        
        $search_model = $this->dataModel->where([['site_id', '=', $this->site_id]])
            ->withSearch(['quotation_id', 'price_name', 'goods_id', 'goods_name', 'capacity', 'price_date', 'is_current'], $where)
            ->field($field)
            ->order('id asc');
        
        $list = $this->pageQuery($search_model, function ($item) {
            $prices = json_decode($item['prices'], true) ?: [];
            $finalPrices = [];
            
            // prices 字段在数据导入时已经应用了价格配置，这里只需要转换格式
            foreach ($prices as $configItemName => $price) {
                $finalPrices[$configItemName] = [
                    'original_price' => $price,
                    'final_price' => $price,
                    'price_status' => 0,  // 价格未变化
                    'price_difference' => 0,
                    'price_diff_percent' => 0,
                ];
            }
            
            $item['price_detail'] = $finalPrices;
            return $item;
        });

        // 设置缓存（使用tag）
        $cacheTime = $where['price_date'] == date('Y-m-d') ? 86400 : 3600; // 当天24小时，历史1小时
        Cache::tag($cacheTag)->set($cacheKey, $list, $cacheTime);

        return $list;
    }

    /**
     * 计算最终价格（应用价格配置）
     * @param int $goodsId
     * @param string $capacity
     * @param string $configItemName
     * @param float $originalPrice
     * @return float
     */
    protected function calculateFinalPrice(int $goodsId, string $capacity, string $configItemName, float $originalPrice): float
    {
        // 按优先级查询配置
        $priceConfigService = new QuotationPriceConfigService();
        $config = $priceConfigService->getMatchingConfig($goodsId, $capacity, $configItemName);
        
        if (empty($config) || $config['is_enable'] != QuotationDict::STATUS_ENABLED) {
            return $originalPrice;
        }

        \think\facade\Log::write("[找到价格配置] goods_id={$goodsId}, capacity={$capacity}, config_item={$configItemName}, 配置类型={$config['config_type']}, 调整类型={$config['adjustment_type']}, 调整值={$config['adjustment_value']}", 'info');

        // 应用配置规则
        $adjustmentType = $config['adjustment_type'];
        $adjustmentValue = floatval($config['adjustment_value']);

        switch ($adjustmentType) {
            case QuotationDict::ADJUSTMENT_TYPE_FIXED:
                // 固定金额调整
                return $originalPrice + $adjustmentValue;
                
            case QuotationDict::ADJUSTMENT_TYPE_PERCENT:
                // 百分比调整
                return $originalPrice * (1 + $adjustmentValue / 100);
                
            case QuotationDict::ADJUSTMENT_TYPE_COVER:
                // 固定价格覆盖
                return $adjustmentValue;
                
            default:
                return $originalPrice;
        }
    }

    /**
     * 获取价格状态（用于价格对比标识）
     * @param float $originalPrice
     * @param float $finalPrice
     * @return int
     */
    protected function getPriceStatus(float $originalPrice, float $finalPrice): int
    {
        if ($finalPrice == $originalPrice) {
            return QuotationDict::PRICE_STATUS_NORMAL;
        } elseif ($finalPrice > $originalPrice) {
            return QuotationDict::PRICE_STATUS_HIGH;
        } else {
            return QuotationDict::PRICE_STATUS_LOW;
        }
    }

    /**
     * 清除缓存
     * @return void
     */
    protected function clearCache(): void
    {
        try {
            // 清除该站点下的所有报价数据缓存
            $cacheTag = 'quotation_data_' . $this->site_id;
            Cache::tag($cacheTag)->clear();
        } catch (\Exception $e) {
            // 如果tag不支持，尝试直接清除
            try {
                $pattern = 'quotation_data:' . $this->site_id . ':*';
                $handler = Cache::handler();
                if (method_exists($handler, 'keys')) {
                    $keys = $handler->keys($pattern);
                    if (!empty($keys)) {
                        foreach ($keys as $key) {
                            Cache::delete($key);
                        }
                    }
                }
            } catch (\Exception $e2) {
                Log::warning('清除缓存失败：' . $e2->getMessage());
            }
        }
    }

    /**
     * 获取报价数据详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id): array
    {
        $info = $this->dataModel
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new CommonException('数据不存在');
        }

        // 转换 prices 为 price_detail 格式
        // prices 字段在数据导入时已经应用了价格配置
        $prices = json_decode($info['prices'], true) ?: [];
        $finalPrices = [];
        
        foreach ($prices as $configItemName => $price) {
            $finalPrices[$configItemName] = [
                'original_price' => $price,
                'final_price' => $price,
                'price_status' => 0,
                'price_difference' => 0,
                'price_diff_percent' => 0,
            ];
        }
        
        $info['price_detail'] = $finalPrices;

        return $info;
    }

    /**
     * 获取所有可用的选项（型号、内存、配置项）
     * 用于级联选择器
     * @param int|null $quotationId 报价单ID
     * @param string|null $priceName 价格名称
     * @return array
     */
    public function getCascadeOptions(?int $quotationId = null, ?string $priceName = null): array
    {
        // 查询当前价格数据，获取所有型号、内存、配置项
        $where = [
            ['site_id', '=', $this->site_id],
            ['is_current', '=', QuotationDict::IS_CURRENT_YES]
        ];
        
        // 如果指定了报价单ID，添加过滤条件
        if ($quotationId !== null && $quotationId > 0) {
            $where[] = ['quotation_id', '=', $quotationId];
        }
        
        // 如果指定了价格名称，添加过滤条件
        if ($priceName !== null && $priceName !== '') {
            $where[] = ['price_name', '=', $priceName];
        }
        
        $data = $this->dataModel
            ->where($where)
            ->field('goods_id,goods_name,capacity,capacity_answer_id,config_selected')
            ->select()
            ->toArray();

        $models = [];
        $capacityMap = []; // goods_id => [capacities]
        $configItemMap = []; // goods_id_capacity => [config_items]

        foreach ($data as $item) {
            $goodsId = $item['goods_id'];
            $goodsName = $item['goods_name'];
            $capacity = $item['capacity'] ?? '';
            $capacityAnswerId = $item['capacity_answer_id'] ?? 0;
            
            // 收集型号
            if ($goodsId > 0 && !empty($goodsName)) {
                if (!isset($models[$goodsId])) {
                    $models[$goodsId] = [
                        'goods_id' => $goodsId,
                        'goods_name' => $goodsName
                    ];
                }
            }

            // 收集内存（按型号分组）
            if ($goodsId > 0 && !empty($capacity)) {
                $key = $goodsId;
                if (!isset($capacityMap[$key])) {
                    $capacityMap[$key] = [];
                }
                
                $capacityKey = $capacity . '_' . $capacityAnswerId;
                if (!isset($capacityMap[$key][$capacityKey])) {
                    $capacityMap[$key][$capacityKey] = [
                        'capacity' => $capacity,
                        'capacity_answer_id' => $capacityAnswerId
                    ];
                }
            }

            // 收集配置项（按型号+内存分组）
            if ($goodsId > 0 && !empty($capacity)) {
                $key = $goodsId . '_' . $capacity;
                $configSelected = json_decode($item['config_selected'] ?? '[]', true) ?: [];
                
                if (!isset($configItemMap[$key])) {
                    $configItemMap[$key] = [];
                }
                
                foreach ($configSelected as $configItem) {
                    if (!empty($configItem) && !in_array($configItem, $configItemMap[$key])) {
                        $configItemMap[$key][] = $configItem;
                    }
                }
            }
        }

        // 组织成级联结构
        $cascadeOptions = [];
        foreach ($models as $goodsId => $model) {
            $capacities = [];
            if (isset($capacityMap[$goodsId])) {
                foreach ($capacityMap[$goodsId] as $capacityInfo) {
                    $capacity = $capacityInfo['capacity'];
                    $capacityKey = $goodsId . '_' . $capacity;
                    
                    $configItems = [];
                    if (isset($configItemMap[$capacityKey])) {
                        foreach ($configItemMap[$capacityKey] as $configItem) {
                            $configItems[] = [
                                'value' => $configItem,
                                'label' => $configItem
                            ];
                        }
                    }
                    
                    $capacities[] = [
                        'value' => $capacity,
                        'label' => $capacity,
                        'capacity_answer_id' => $capacityInfo['capacity_answer_id'],
                        'children' => $configItems
                    ];
                }
            }
            
            $cascadeOptions[] = [
                'value' => $goodsId,
                'label' => $model['goods_name'],
                'goods_id' => $goodsId,
                'goods_name' => $model['goods_name'],
                'children' => $capacities
            ];
        }

        return [
            'models' => array_values($models),
            'cascade_options' => $cascadeOptions
        ];
    }

    /**
     * 批量修改报价数据（调价）
     * @param array $items
     * @return array
     */
    public function batchUpdatePrice(array $items)
    {
        if (empty($items)) {
            throw new \Exception('没有需要更新的数据');
        }

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                // 查找对应的报价数据记录
                $quotationData = $this->dataModel
                    ->where([
                        ['site_id', '=', $this->site_id],
                        ['id', '=', $item['id']]
                    ])
                    ->findOrEmpty();

                if ($quotationData->isEmpty()) {
                    $errors[] = "ID {$item['id']} 的报价数据不存在";
                    $failedCount++;
                    continue;
                }

                // 解析原始价格数据（prices字段）
                $pricesJson = $quotationData->prices ?? '';
                if (empty($pricesJson)) {
                    $errors[] = "ID {$item['id']} 的价格数据为空";
                    $failedCount++;
                    continue;
                }
                
                $prices = json_decode($pricesJson, true);
                if (!is_array($prices)) {
                    $errors[] = "ID {$item['id']} 的价格数据格式错误";
                    $failedCount++;
                    continue;
                }

                // 检查配置项是否存在
                if (!isset($prices[$item['config_name']])) {
                    $errors[] = "ID {$item['id']} 不包含配置项 {$item['config_name']}";
                    $failedCount++;
                    continue;
                }

                // 更新原始价格（prices字段）
                // 注意：只更新 prices 字段，price_detail 是动态生成的，不存储到数据库
                $prices[$item['config_name']] = floatval($item['new_price']);
                
                // 保存到数据库
                $quotationData->prices = json_encode($prices, JSON_UNESCAPED_UNICODE);
                $quotationData->update_at = time();
                $quotationData->save();

                $successCount++;
            } catch (\Exception $e) {
                $errors[] = "ID {$item['id']} 更新失败: " . $e->getMessage();
                $failedCount++;
            }
        }

        // 清除缓存
        $cacheTag = 'quotation_data_' . $this->site_id;
        Cache::tag($cacheTag)->clear();

        return [
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'errors' => $errors
        ];
    }
}

