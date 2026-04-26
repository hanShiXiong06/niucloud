<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationData;
use addon\recycle\app\model\quotation\RecycleQuotationModel;
use addon\recycle\app\model\quotation\RecycleQuotationCapacity;
use addon\recycle\app\model\quotation\RecycleQuotationGradeSpec;
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

    /**
     * @var RecycleQuotationCapacity
     */
    protected $capacityModel;

    /**
     * @var RecycleQuotationGradeSpec
     */
    protected $gradeSpecModel;

    public function __construct()
    {
        parent::__construct();
        $this->dataModel = new RecycleQuotationData();
        $this->modelModel = new RecycleQuotationModel();
        $this->capacityModel = new RecycleQuotationCapacity();
        $this->gradeSpecModel = new RecycleQuotationGradeSpec();
    }

    /**
     * 手动设置站点ID，兼容任务或脚本场景
     * @param int|null $siteId
     * @return static
     */
    public function setSiteId(?int $siteId)
    {
        $this->site_id = $siteId;
        return $this;
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

        // 检查是否需要更新爬虫备注
        $this->updateCrawlerRemarkIfNeeded($quotationId, $data);

        // 每次爬取新数据前，都将旧的当前记录置为非当前
        // 添加死锁重试机制
        $maxRetries = 3;
        $retryCount = 0;
        $success = false;

        while (!$success && $retryCount < $maxRetries) {
            try {
                Db::startTrans();
                try {
                    $affectedRows = $this->dataModel
                        ->where([
                            ['site_id', '=', $this->site_id],
                            ['quotation_id', '=', $quotationId],
                            ['price_name', '=', $priceName],
                            ['is_current', '=', QuotationDict::IS_CURRENT_YES],
                        ])
                        ->update(['is_current' => QuotationDict::IS_CURRENT_NO]);

                    Db::commit();
                    $success = true;

                    Log::info('旧报价数据已标记为非当前', [
                        'affected_rows' => $affectedRows,
                        'site_id' => $this->site_id,
                        'quotation_id' => $quotationId,
                        'price_name' => $priceName,
                        'retry_count' => $retryCount,
                    ]);
                } catch (\Exception $e) {
                    Db::rollback();

                    // 检查是否是死锁错误
                    if (strpos($e->getMessage(), 'Deadlock') !== false && $retryCount < $maxRetries - 1) {
                        $retryCount++;
                        // 随机延迟 50-200ms 后重试，避免多个请求同时重试
                        usleep(rand(50000, 200000));
                        Log::warning('检测到死锁，正在重试', [
                            'site_id' => $this->site_id,
                            'quotation_id' => $quotationId,
                            'price_name' => $priceName,
                            'retry_count' => $retryCount,
                            'error' => $e->getMessage(),
                        ]);
                        continue;
                    }

                    Log::error('更新旧报价数据状态失败', [
                        'site_id' => $this->site_id,
                        'quotation_id' => $quotationId,
                        'price_name' => $priceName,
                        'retry_count' => $retryCount,
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            } catch (\Exception $e) {
                if (!$success && $retryCount >= $maxRetries - 1) {
                    throw new CommonException('更新旧数据状态失败: ' . $e->getMessage());
                }
            }
        }

        // 解析sku数据
        $skuData = $data['sku'] ?? [];
        $stats = [
            'models_added' => 0,
            'capacities_added' => 0,
            'grade_specs_added' => 0,
            'data_saved' => 0,
        ];

        // 初始化规格信息（型号、内存、等级规格）
        $specService = new QuotationSpecService();
        $specStats = $specService->initSpecs($skuData);
        $stats['models_added'] = $specStats['models_added'];
        $stats['capacities_added'] = $specStats['capacities_added'];
        $stats['grade_specs_added'] = $specStats['grade_specs_added'];

        // 获取规格映射关系
        // 注意：查询映射时应该查询所有有goods_id的记录，而不仅仅是sync_enable=1的记录
        // 因为即使某个型号不同步，在解析数据时也需要能找到对应的model_id
        $models = $this->modelModel
            ->where([
                ['site_id', '=', $this->site_id],
                ['goods_id', '>', 0], // 确保goods_id存在且大于0
            ])
            ->select()
            ->toArray();
        $goodsIdToModelId = [];
        $modelIdToModel = [];
        foreach ($models as $model) {
            // 只有当goods_id不为0且model_id（id）有效时才建立映射
            if (!empty($model['goods_id']) && $model['goods_id'] > 0 && !empty($model['id']) && $model['id'] > 0) {
                $goodsIdToModelId[$model['goods_id']] = $model['id'];
                $modelIdToModel[$model['id']] = $model;
                }
            }
        


        $capacities = RecycleQuotationCapacity::where([
            ['site_id', '=', $this->site_id],
            ['sync_enable', '=', QuotationDict::STATUS_ENABLED],
        ])->select()->toArray();
        $capacityMap = []; // [model_id_capacity_answer_id => capacity_id]
        foreach ($capacities as $capacity) {
            $key = $capacity['model_id'] . '_' . $capacity['capacity_answer_id'];
            $capacityMap[$key] = $capacity['id'];
        }

        $gradeSpecs = RecycleQuotationGradeSpec::where([
            ['site_id', '=', $this->site_id],
            ['sync_enable', '=', QuotationDict::STATUS_ENABLED],
        ])->select()->toArray();
        $gradeSpecMap = []; // [spec_name => grade_spec_id]
        foreach ($gradeSpecs as $spec) {
            $gradeSpecMap[$spec['spec_name']] = $spec['id'];
        }

        // 解析并存储报价数据
        $batchData = [];
        // 收集每个型号使用的内存ID和规格ID：model_id => [capacity_ids => [], grade_spec_ids => []]
        $modelRelations = [];
        
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

                // 获取型号ID
                if (!isset($goodsIdToModelId[$goodsId])) {
                    
                    continue;
                }
                $modelId = $goodsIdToModelId[$goodsId];
                
                // 验证model_id是否有效
                if (empty($modelId) || $modelId <= 0) {
                    continue;
                }
                

                // 获取内存ID
                $capacityKey = $modelId . '_' . $capacityAnswerId;
                if (!isset($capacityMap[$capacityKey])) {
                    continue;
                }
                $capacityId = $capacityMap[$capacityKey];

                // 初始化型号关联关系
                if (!isset($modelRelations[$modelId])) {
                    $modelRelations[$modelId] = [
                        'capacity_ids' => [],
                        'grade_spec_ids' => []
                    ];
                }
                // 收集内存ID
                if (!in_array($capacityId, $modelRelations[$modelId]['capacity_ids'])) {
                    $modelRelations[$modelId]['capacity_ids'][] = $capacityId;
                }

                // 解析配置项和价格
                $configItems = $item['config_attr'] ?? [];
                $configSelected = $item['config_selected'] ?? [];
                

                // 根据报价类型名称获取报价类型ID
                $priceTypeId = $this->getPriceTypeId($priceName);
                
                // 根据型号ID和报价类型ID查找匹配的扣费配置
                $deductionConfigId = null;
                $deductionService = (new DeductionConfigService())->setSiteId($this->site_id);
                $configId = $deductionService->getMatchingConfigId($goodsId, $priceTypeId);
                if ($configId !== null) {
                    $deductionConfigId = $configId;
                    \think\facade\Log::write("[扣费配置匹配] goods_id={$goodsId}, price_name={$priceName}, price_type_id={$priceTypeId}, config_id={$configId}", 'info');
                }

                // 解析每个配置项的价格，创建单独的价格记录
                foreach ($configItems as $configItem) {
                    $configName = $configItem['question_name'] ?? '';
                    $originalPrice = $configItem['answer_name'] ?? 0;
                    
                    if (empty($configName) || !is_numeric($originalPrice)) {
                        continue;
                    }

                    // 获取等级规格ID
                    if (!isset($gradeSpecMap[$configName])) {
                        continue;
                    }
                    $gradeSpecId = $gradeSpecMap[$configName];
                    
                    // 收集规格ID
                    if (!in_array($gradeSpecId, $modelRelations[$modelId]['grade_spec_ids'])) {
                        $modelRelations[$modelId]['grade_spec_ids'][] = $gradeSpecId;
                    }

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

                        }
                        
                    // 再次验证关键字段，确保数据完整性
                    if (empty($modelId) || $modelId <= 0) {
                        
                        continue;
                }

                    // 创建价格记录（使用关联方式）
                $batchData[] = [
                    'site_id' => $this->site_id,
                    'request_id' => $requestId,
                    'quotation_id' => $quotationId,
                    'price_name' => $priceName,
                        'model_id' => $modelId,
                        'capacity_id' => $capacityId,
                        'grade_spec_id' => $gradeSpecId,
                        'deduction_config_id' => $deductionConfigId,// 目前没有用
                        'goods_id' => $goodsId, // 冗余字段
                        // 'goods_name' => $goodsName, // 冗余字段
                        // 'capacity' => $capacity, // 冗余字段
                        // 'grade_spec_name' => $configName, // 冗余字段
                        'prices' => $originalPrice,
                        'price' => $finalPrice,
                    'add_value_info' => $deductionConfigId,
                    'price_date' => $priceDate,
                    'is_current' => QuotationDict::IS_CURRENT_YES,
                    'create_at' => time(),
                    'update_at' => time(),
                ];
                }
            }
        }

        // 分批插入数据,避免长时间锁表
        if (!empty($batchData)) {
            $totalCount = count($batchData);
            $chunkSize = 100; // 每批插入100条
            $chunks = array_chunk($batchData, $chunkSize);

            Log::info('开始分批插入报价数据', [
                'total_count' => $totalCount,
                'chunk_size' => $chunkSize,
                'chunk_count' => count($chunks),
            ]);

            foreach ($chunks as $index => $chunk) {
                try {
                    // 使用带重试的数据库操作,处理死锁等临时性错误
                    DatabaseHelper::executeWithRetry(function() use ($chunk, $index) {
                        // 每批数据使用独立的小事务
                        Db::startTrans();
                        try {
                            $this->dataModel->insertAll($chunk);
                            Db::commit();

                            Log::info("批次插入成功", [
                                'batch_index' => $index + 1,
                                'batch_size' => count($chunk),
                            ]);
                        } catch (\Exception $e) {
                            Db::rollback();
                            Log::error("批次插入失败", [
                                'batch_index' => $index + 1,
                                'error' => $e->getMessage(),
                            ]);
                            throw $e;
                        }
                    }, 3, 100); // 最多重试3次,初始延迟100ms

                    // 每批插入后稍微延迟,释放数据库压力
                    if ($index < count($chunks) - 1) {
                        usleep(10000); // 延迟10毫秒
                    }
                } catch (\Exception $e) {
                    Log::error('批量插入数据失败', [
                        'batch_index' => $index + 1,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    throw new CommonException('数据插入失败: ' . $e->getMessage());
                }
            }

            $stats['data_saved'] = $totalCount;
        }

        // 优化型号表更新:一次性查询所有需要更新的型号,减少数据库往返
        if (!empty($modelRelations)) {
            $modelIds = array_keys($modelRelations);

            // 一次性查询所有需要更新的型号
            $models = $this->modelModel->whereIn('id', $modelIds)->select()->toArray();
            $existingModels = [];
            foreach ($models as $model) {
                $existingModels[$model['id']] = $model;
            }

            // 批量更新型号关联ID
            $updateBatch = [];
            foreach ($modelRelations as $modelId => $relations) {
                $capacityIds = array_unique($relations['capacity_ids']);
                $gradeSpecIds = array_unique($relations['grade_spec_ids']);

                // 如果型号已存在,合并已有的ID
                if (isset($existingModels[$modelId])) {
                    $existingCapacityIds = $existingModels[$modelId]['capacity_ids'] ?? [];
                    $existingGradeSpecIds = $existingModels[$modelId]['grade_spec_ids'] ?? [];

                    $capacityIds = array_unique(array_merge($existingCapacityIds, $capacityIds));
                    $gradeSpecIds = array_unique(array_merge($existingGradeSpecIds, $gradeSpecIds));
                }

                $updateBatch[] = [
                    'id' => $modelId,
                    'capacity_ids' => $capacityIds,
                    'grade_spec_ids' => $gradeSpecIds,
                    'update_at' => time()
                ];
            }

            // 使用小事务批量更新型号表
            if (!empty($updateBatch)) {
                Db::startTrans();
                try {
                    foreach ($updateBatch as $updateData) {
                        $this->modelModel->where('id', $updateData['id'])->update([
                            'capacity_ids' => $updateData['capacity_ids'],
                            'grade_spec_ids' => $updateData['grade_spec_ids'],
                            'update_at' => $updateData['update_at']
                        ]);
                    }
                    Db::commit();

                    Log::info('型号表批量更新成功', [
                        'update_count' => count($updateBatch),
                    ]);
                } catch (\Exception $e) {
                    Db::rollback();
                    Log::error('型号表更新失败', [
                        'error' => $e->getMessage(),
                    ]);
                    throw $e;
                }
            }
        }

        // 清除相关缓存
        $this->clearCache();

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

        // 生成缓存key
        $cacheKey = 'quotation_data_all:' . $this->site_id . ':' . md5(json_encode($where));
        $cacheTag = 'quotation_data_' . $this->site_id;
        
        // 尝试从缓存获取
        $cacheData = Cache::get($cacheKey);
        if ($cacheData !== null) {
            return $cacheData;
        }

        // 从 Model 获取格式化后的数据列表
        $list = $this->dataModel->getFormattedList($where, $this->site_id);
        $originalCount = count($list);
        
        // 合并同型号同容量的多条记录：将不同等级规格的价格合并到 prices 对象中
        $mergedData = [];
        $processedCount = 0; // 记录处理的记录数
        $skippedCount = 0; // 记录跳过的记录数（没有 grade_spec_name 或 price 的记录）
        $mergeStats = []; // 记录每个合并键的合并统计
        
        foreach ($list as $item) {
            $processedCount++;
            $rawPriceValue = isset($item['original_price']) && $item['original_price'] !== null
                ? floatval($item['original_price'])
                : null;
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
                            $priceItem = $this->normalizePriceEntry($price, $rawPriceValue);
                            break;
                        }
                    }
                    
                    // 如果没有找到，创建一个新的价格项
                    if ($priceItem === null) {
                        $priceItem = $this->normalizePriceEntry([
                            'id' => $item['id'],
                            'name' => $item['grade_spec_name'],
                            'price' => floatval($item['price']),
                        ], $rawPriceValue);
                    }
                    
                    $mergedData[$mergeKey]['prices'][] = $priceItem;
                    $mergeStats[$mergeKey]['grade_specs'][] = $item['grade_spec_name'];
                } else {
                    // 如果没有 grade_spec_name，直接使用 item 的 prices 数组
                    if (!empty($item['prices']) && is_array($item['prices'])) {
                        $mergedData[$mergeKey]['prices'] = array_map(function ($price) use ($rawPriceValue) {
                            return $this->normalizePriceEntry($price, $rawPriceValue);
                        }, $item['prices']);
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
                    
                    // 如果存在相同的配置项且价格不同，记录警告
                    if ($existingIndex !== null && 
                        $mergedData[$mergeKey]['prices'][$existingIndex]['price'] != floatval($item['price'])) {
                        
                        // 更新价格
                        $mergedData[$mergeKey]['prices'][$existingIndex]['price'] = floatval($item['price']);
                        if ($rawPriceValue !== null) {
                            $mergedData[$mergeKey]['prices'][$existingIndex]['original_price'] = $rawPriceValue;
                        }
                    } elseif ($existingIndex === null) {
                        // 如果不存在，添加新的价格项
                        // 从 item 的 prices 数组中查找对应的配置项，如果没有则创建新的
                        $priceItem = null;
                        foreach ($item['prices'] as $price) {
                            if (isset($price['name']) && $price['name'] === $item['grade_spec_name']) {
                                $priceItem = $this->normalizePriceEntry($price, $rawPriceValue);
                                break;
                            }
                        }
                        
                        // 如果没有找到，创建一个新的价格项
                        if ($priceItem === null) {
                            $priceItem = $this->normalizePriceEntry([
                                'id' => $item['id'] ,
                                'name' => $item['grade_spec_name'],
                                'price' => floatval($item['price'])
                            ], $rawPriceValue);
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
                                    $mergedData[$mergeKey]['prices'][] = $this->normalizePriceEntry($price, $rawPriceValue);
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
                    // 解析现有的 add_value_info（可能是 JSON 字符串）
                    $addValueInfo = [];
                    if (!empty($item['add_value_info'])) {
                        if (is_string($item['add_value_info'])) {
                            $addValueInfo = json_decode($item['add_value_info'], true) ?: [];
                        } elseif (is_array($item['add_value_info'])) {
                            $addValueInfo = $item['add_value_info'];
                        }
                    }
                    
                    // 将匹配的配置信息添加到 add_value_info
                    // 格式：{model_id: {id: config_id, remark_text: remark_text}, ...}

                    
                    // 将 add_value_info  仅 = id 不要json 
                    $item['add_value_info'] = $config['id'];
                }
            }
        }
        unset($item);
        
        // 记录合并后的记录数（用于调试）
        $mergedCount = count($list);
        
        // 记录每个合并键的详细信息（如果合并数量异常）
        foreach ($mergeStats as $mergeKey => $stats) {
            if ($stats['count'] > 1) {
                // 记录合并了多条记录的合并键
                
            }
        }
        
        // 如果合并后的记录数异常减少，记录警告日志
        // 注意：合并后数量应该小于等于原始数量（因为合并会减少记录数）
        // 但如果合并键有问题，可能会导致记录丢失
        // 检查：如果原始记录数 > 合并后记录数 + 跳过的记录数，说明有数据丢失
        $expectedCount = $mergedCount + $skippedCount;
        if ($originalCount > 0 && $originalCount > $expectedCount) {
            
        }
        
        // 设置缓存（使用tag，当天数据24小时，历史数据1小时）
        $cacheTime = isset($where['price_date']) && $where['price_date'] == date('Y-m-d') ? 86400 : 3600;
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
        $priceConfigService = (new QuotationPriceConfigService())->setSiteId($this->site_id);
        $config = $priceConfigService->getMatchingConfig($goodsId, $capacity, $configItemName);
        
        if (empty($config) || $config['is_enable'] != QuotationDict::STATUS_ENABLED) {
            return $originalPrice;
        }

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
     * 规范化价格条目，补充原始价格信息
     * @param array|float|int $price
     * @param float|null $rawPrice
     * @return array
     */
    protected function normalizePriceEntry($price, ?float $rawPrice = null): array
    {
        if (!is_array($price)) {
            $price = [
                'price' => is_numeric($price) ? floatval($price) : $price
            ];
        }
        if (isset($price['price'])) {
            $price['price'] = floatval($price['price']);
        }
        if (!isset($price['original_price'])) {
            $price['original_price'] = $rawPrice ?? ($price['price'] ?? null);
        }
        return $price;
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
     * 根据报价类型名称获取报价类型ID
     * @param string $priceName 报价类型名称
     * @return string 报价类型ID（114 或 115），如果未匹配返回空字符串
     */
    private function getPriceTypeId(string $priceName): string
    {
        // 报价类型名称到ID的映射
        $priceTypeMap = [
            '靓机/小花' => '114',
            '花机/内爆' => '115',
            '卡贴外版' => '116',
            '外版无锁' => '117',
            '资源机' => '121',
        ];

        // 完全匹配
        if (isset($priceTypeMap[$priceName])) {
            return $priceTypeMap[$priceName];
        }

        // 模糊匹配（兼容性处理）
        foreach ($priceTypeMap as $name => $id) {
            if (strpos($priceName, $name) !== false || strpos($name, $priceName) !== false) {
                return $id;
            }
        }

        // 未匹配到，返回空字符串
        return '';
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
            ->with(['deductionConfig'])  // 加载关联的扣费配置
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new CommonException('数据不存在');
        }

        // 转换 prices 为 price_detail 格式
        // prices 字段在数据导入时已经应用了价格配置
        $prices = json_decode($info['prices'], true) ?: [];

        // $info['price_detail'] = $finalPrices;

        return $prices;
    }

    /**
     * 获取所有可用的选项（型号、内存、配置项）
     * 用于级联选择器 - 从型号表的关联字段中获取
     * @param int|null $quotationId 报价单ID（如果指定，只返回该报价单使用的型号）
     * @param string|null $priceName 价格名称（如果指定，只返回该价格名称的型号）
     * @return array
     */
    public function getCascadeOptions(?int $quotationId = null, ?string $priceName = null): array
    {
        // 如果指定了报价单ID，从data表中获取该报价单实际使用的型号、内存、规格组合
        // 存储格式：model_id => [capacity_id => [grade_spec_id1, grade_spec_id2, ...]]
        $quotationRelations = []; // 报价单实际使用的关联关系
        
        if ($quotationId !== null && $quotationId > 0) {
        $where = [
            ['site_id', '=', $this->site_id],
                ['quotation_id', '=', $quotationId]
        ];
        
        // 如果指定了价格名称，添加过滤条件
        if ($priceName !== null && $priceName !== '') {
            $where[] = ['price_name', '=', $priceName];
        }
        
            // 从data表中获取该报价单使用的所有型号、内存、规格组合
            $dataRows = $this->dataModel
            ->where($where)
                ->field('model_id,capacity_id,grade_spec_id')
            ->select()
            ->toArray();

            foreach ($dataRows as $row) {
                $modelId = $row['model_id'] ?? 0;
                $capacityId = $row['capacity_id'] ?? 0;
                $gradeSpecId = $row['grade_spec_id'] ?? 0;
                
                if ($modelId <= 0 || $capacityId <= 0 || $gradeSpecId <= 0) {
                    continue;
                }
                
                if (!isset($quotationRelations[$modelId])) {
                    $quotationRelations[$modelId] = [];
                }
                if (!isset($quotationRelations[$modelId][$capacityId])) {
                    $quotationRelations[$modelId][$capacityId] = [];
                }
                if (!in_array($gradeSpecId, $quotationRelations[$modelId][$capacityId])) {
                    $quotationRelations[$modelId][$capacityId][] = $gradeSpecId;
                }
            }
        }
        
        // 如果指定了报价单ID，只查询该报价单使用的型号
        $filterModelIds = [];
        if (!empty($quotationRelations)) {
            $filterModelIds = array_keys($quotationRelations);
        }
        
        // 从型号表中查询型号信息
        $modelQuery = $this->modelModel->where([['site_id', '=', $this->site_id]]);
        if (!empty($filterModelIds)) {
            $modelQuery->whereIn('id', $filterModelIds);
        }
        $models = $modelQuery->order('id desc')->select()->toArray();
        
        // 收集需要查询的内存ID和规格ID
        $allCapacityIds = [];
        $allGradeSpecIds = [];
        
        if (!empty($quotationRelations)) {
            // 如果指定了报价单ID，使用报价单实际使用的关联关系
            foreach ($quotationRelations as $modelId => $capacityMap) {
                foreach ($capacityMap as $capacityId => $gradeSpecIds) {
                    if (!in_array($capacityId, $allCapacityIds)) {
                        $allCapacityIds[] = $capacityId;
                    }
                    foreach ($gradeSpecIds as $gradeSpecId) {
                        if (!in_array($gradeSpecId, $allGradeSpecIds)) {
                            $allGradeSpecIds[] = $gradeSpecId;
                }
            }
                }
            }
        } else {
            // 如果没有指定报价单ID，使用型号表的关联字段
            foreach ($models as $model) {
                $capacityIds = $model['capacity_ids'] ?? [];
                $gradeSpecIds = $model['grade_spec_ids'] ?? [];
                
                if (!empty($capacityIds) && is_array($capacityIds)) {
                    $allCapacityIds = array_merge($allCapacityIds, $capacityIds);
                }
                if (!empty($gradeSpecIds) && is_array($gradeSpecIds)) {
                    $allGradeSpecIds = array_merge($allGradeSpecIds, $gradeSpecIds);
                }
            }
        }

        // 去重
        $allCapacityIds = array_unique($allCapacityIds);
        $allGradeSpecIds = array_unique($allGradeSpecIds);
        
        // 从内存表中查询内存信息
            $capacities = [];
        if (!empty($allCapacityIds)) {
            $capacities = $this->capacityModel
                ->where([['site_id', '=', $this->site_id]])
                ->whereIn('id', $allCapacityIds)
                ->with(['model'])
                ->order('id desc')
                ->select()
                ->toArray();
        }
        
        // 从等级规格表中查询规格信息
        $gradeSpecs = [];
        if (!empty($allGradeSpecIds)) {
            $gradeSpecs = $this->gradeSpecModel
                ->where([['site_id', '=', $this->site_id]])
                ->whereIn('id', $allGradeSpecIds)
                ->order('sort asc, id asc')
                ->select()
                ->toArray();
                        }
        
        // 按ID建立索引
        $capacityMapById = []; // capacity_id => capacity_info
        foreach ($capacities as $capacity) {
            $capacityMapById[$capacity['id'] ?? 0] = $capacity;
            }
            
        $gradeSpecMapById = []; // grade_spec_id => grade_spec_info
        foreach ($gradeSpecs as $gradeSpec) {
            $gradeSpecMapById[$gradeSpec['id'] ?? 0] = $gradeSpec;
    }

        // 组织成级联结构：型号 -> 内存 -> 规格
        $modelMap = []; // goods_id => model_info
        $cascadeOptions = [];

        foreach ($models as $model) {
            $goodsId = $model['goods_id'] ?? 0;
            $modelId = $model['id'] ?? 0;
            if ($goodsId <= 0) {
                    continue;
                }

            $modelMap[$goodsId] = $model;
            
            // 确定该型号下需要显示的内存和规格
            if (!empty($quotationRelations) && isset($quotationRelations[$modelId])) {
                // 如果指定了报价单ID，只显示该报价单实际使用的组合
                $modelCapacityMap = $quotationRelations[$modelId];
            } else {
                // 如果没有指定报价单ID，使用型号表的关联字段
                $modelCapacityIds = $model['capacity_ids'] ?? [];
                $modelCapacityMap = [];
                foreach ($modelCapacityIds as $capacityId) {
                    $modelCapacityMap[$capacityId] = $model['grade_spec_ids'] ?? [];
                }
                }
                
            $modelCapacities = [];
            foreach ($modelCapacityMap as $capacityId => $gradeSpecIds) {
                if (!isset($capacityMapById[$capacityId])) {
                    continue;
                }

                $capacity = $capacityMapById[$capacityId];
                
                // 获取该型号+内存组合下的规格
                    $configItems = [];
                foreach ($gradeSpecIds as $gradeSpecId) {
                    if (!isset($gradeSpecMapById[$gradeSpecId])) {
                    continue;
                }

                    $gradeSpec = $gradeSpecMapById[$gradeSpecId];
                            $configItems[] = [
                        'value' => $gradeSpec['spec_name'] ?? '',
                        'label' => $gradeSpec['spec_name'] ?? '',
                        'grade_spec_id' => $gradeSpecId
                    ];
                }
                
                $modelCapacities[] = [
                    'value' => $capacity['capacity'] ?? '',
                    'label' => $capacity['capacity'] ?? '',
                    'capacity_id' => $capacityId,
                    'capacity_answer_id' => $capacity['capacity_answer_id'] ?? 0,
                        'children' => $configItems
                    ];
            }
            
            $cascadeOptions[] = [
                'value' => $goodsId,
                'label' => $model['goods_name'] ?? '',
                'goods_id' => $goodsId,
                'goods_name' => $model['goods_name'] ?? '',
                'model_id' => $modelId,
                'children' => $modelCapacities
            ];
        }

        return [
            'models' => array_values($modelMap),
            'cascade_options' => $cascadeOptions
        ];
    }

    /**
     * 根据配置决定是否更新爬虫备注
     * @param string|int $quotationId 报价单ID
     * @param array $data 爬虫返回的数据
     * @return void
     */
    private function updateCrawlerRemarkIfNeeded($quotationId, array $data): void
    {
        try {
            // 1. 查询报价单配置，检查是否开启了"使用爬虫备注"
            $configModel = new \addon\recycle\app\model\quotation\RecycleQuotationConfig();
            $config = $configModel
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['quotation_id', '=', $quotationId],
                ])
                ->findOrEmpty();

            if ($config->isEmpty()) {
                Log::info('未找到报价单配置，跳过备注更新', [
                    'quotation_id' => $quotationId,
                    'site_id' => $this->site_id,
                ]);
                return;
            }

            // 2. 检查是否开启了"使用爬虫备注"
            $useCrawlerRemark = $config->use_crawler_remark ?? 0;
            if ($useCrawlerRemark != 1) {
                Log::info('未开启使用爬虫备注，跳过备注更新', [
                    'quotation_id' => $quotationId,
                    'use_crawler_remark' => $useCrawlerRemark,
                ]);
                return;
            }

            // 3. 解析爬虫数据中的备注信息
            $remarkData = $this->extractRemarkFromCrawlerData($data);
            if (empty($remarkData)) {
                Log::info('爬虫数据中未找到备注信息，跳过备注更新', [
                    'quotation_id' => $quotationId,
                ]);
                return;
            }

            // 4. 更新扣费配置表的备注信息（传入 quotation_id）
            $this->updateDeductionConfigRemark($remarkData, $quotationId);

            Log::info('爬虫备注更新成功', [
                'quotation_id' => $quotationId,
                'updated_count' => count($remarkData),
            ]);
        } catch (\Exception $e) {
            // 备注更新失败不影响主流程，只记录日志
            Log::error('更新爬虫备注失败', [
                'quotation_id' => $quotationId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * 从爬虫数据中提取备注信息
     * 支持多个字段的备注信息整合，根据报价单ID配置不同的提取规则
     * @param array $data 爬虫返回的数据
     * @return array 备注数据 [goods_id => remark_text]
     */
    private function extractRemarkFromCrawlerData(array $data): array
    {
        $remarkData = [];

        // 获取报价单ID（从请求信息中获取）
        $quotationId = $data['quotation_id'] ?? '';

        // 根据报价单ID获取候选的备注字段（作为参考）
        $candidateFields = $this->getRemarkFieldsConfig($quotationId);

        // 从 SKU 数据中提取备注信息
        $skuData = $data['sku'] ?? [];
        foreach ($skuData as $groupKey => $group) {
            foreach ($group as $item) {
                $goodsId = $item['goods_id'] ?? 0;
                if ($goodsId <= 0) {
                    continue;
                }

                // 收集该商品的所有备注信息
                $remarkParts = [];

                // 从 config_attr 中提取备注信息
                $configAttr = $item['config_attr'] ?? [];

                // 先收集该 SKU 实际存在的字段名称
                $existingFields = [];
                foreach ($configAttr as $attr) {
                    $questionName = $attr['question_name'] ?? '';
                    if (!empty($questionName)) {
                        $existingFields[] = $questionName;
                    }
                }

                // 遍历 config_attr，提取候选字段中实际存在的字段
                foreach ($configAttr as $attr) {
                    $questionName = $attr['question_name'] ?? '';
                    $answerName = $attr['answer_name'] ?? '';

                    // 检查是否是候选的备注字段，并且该字段实际存在
                    if (in_array($questionName, $candidateFields) && !empty($answerName)) {
                        // 清理 HTML 标签，保留纯文本
                        $cleanText = strip_tags($answerName);
                        // 去除多余的空格和换行
                        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
                        $cleanText = trim($cleanText);

                        if (!empty($cleanText)) {
                            // 添加标签，格式：【字段名】内容
                            $remarkParts[] = '【' . $questionName . '】' . $cleanText;
                        }
                    }
                }

                // 将所有备注部分整合成一个完整的备注
                if (!empty($remarkParts)) {
                    // 使用换行符分隔不同的备注部分，保持清晰的格式
                    $remarkData[$goodsId] = implode("\n", $remarkParts);

                    // 记录日志，方便调试
                    Log::info('提取商品备注信息', [
                        'goods_id' => $goodsId,
                        'quotation_id' => $quotationId,
                        'extracted_fields' => array_map(function($part) {
                            return explode('】', $part)[0] . '】';
                        }, $remarkParts),
                        'remark_length' => mb_strlen($remarkData[$goodsId]),
                    ]);
                }
            }
        }

        return $remarkData;
    }

    /**
     * 根据报价单ID获取需要提取的备注字段配置
     * @param string|int $quotationId 报价单ID
     * @return array 需要提取的字段名称数组
     */
    private function getRemarkFieldsConfig($quotationId): array
    {
        // 将 quotationId 转换为字符串，确保数组键匹配
        $quotationId = (string)$quotationId;

        // 根据报价单ID配置需要提取的备注字段
        $config = [
            '114' => ['颜色加/扣前项', '加/扣钱项'],           // 靓机/小花
            '115' => ['加/扣钱项', '彩点', '蓝光'],           // 花机/内爆
            '116' => ['颜色加/扣前项', '加/扣钱项'],           // 卡贴外版
            '117' => ['加/扣钱项'],                           // 外版无锁
            '121' => ['加/扣钱项'],                           // 资源机
        ];

        // 返回对应报价单的配置，如果没有配置则返回默认配置
        return $config[$quotationId] ?? ['加/扣钱项'];
    }

    /**
     * 更新扣费配置表的备注信息
     * @param array $remarkData 备注数据 [goods_id => remark_text]
     * @param string|int $quotationId 报价单ID
     * @return void
     */
    private function updateDeductionConfigRemark(array $remarkData, $quotationId): void
    {
        if (empty($remarkData)) {
            return;
        }

        $deductionConfigModel = new \addon\recycle\app\model\quotation\RecycleDeductionConfig();

        // 批量更新备注信息
        foreach ($remarkData as $goodsId => $remarkText) {
            try {
                // 查找匹配的扣费配置
                // 1. 根据 site_id 匹配站点
                // 2. 根据 price_id 匹配报价单（price_id 字段存储的是报价类型，如：114, 115）
                // 3. 根据 model_id 匹配商品（model_id 可能是逗号分隔的多个ID）
                $config = $deductionConfigModel
                    ->where([
                        ['site_id', '=', $this->site_id],
                        ['price_id', 'like', '%' . $quotationId . '%'], // 匹配报价单ID
                    ])
                    ->where(function($query) use ($goodsId) {
                        // model_id 可能是逗号分隔的多个ID，需要模糊匹配
                        $query->where('model_id', 'like', '%' . $goodsId . '%');
                    })
                    ->findOrEmpty();

                if (!$config->isEmpty()) {
                    // 找到配置，更新备注信息
                    $config->save([
                        'remark_text' => $remarkText,
                        'update_at' => time(),
                    ]);

                    Log::info('扣费配置备注更新成功', [
                        'config_id' => $config->id,
                        'config_name' => $config->config_name,
                        'quotation_id' => $quotationId,
                        'goods_id' => $goodsId,
                        'remark_length' => mb_strlen($remarkText),
                    ]);
                } else {
                    // 未找到配置，自动创建新配置
                    Log::info('未找到匹配的扣费配置，自动创建新配置', [
                        'quotation_id' => $quotationId,
                        'goods_id' => $goodsId,
                        'site_id' => $this->site_id,
                    ]);

                    // 获取商品名称（从 model 表中查询）
                    $modelModel = new \addon\recycle\app\model\quotation\RecycleQuotationModel();
                    $model = $modelModel
                        ->where([
                            ['site_id', '=', $this->site_id],
                            ['goods_id', '=', $goodsId],
                        ])
                        ->findOrEmpty();

                    $goodsName = !$model->isEmpty() ? $model->goods_name : '未知商品';

                    // 创建新的扣费配置
                    $newConfig = $deductionConfigModel->create([
                        'site_id' => $this->site_id,
                        'config_name' => $goodsName,
                        'model_id' => (string)$goodsId,
                        'price_id' => (string)$quotationId,
                        'remark_text' => $remarkText,
                        'sort' => 0,
                        'is_enable' => 1,
                        'create_at' => time(),
                        'update_at' => time(),
                    ]);

                    Log::info('自动创建扣费配置成功', [
                        'config_id' => $newConfig->id,
                        'config_name' => $goodsName,
                        'quotation_id' => $quotationId,
                        'goods_id' => $goodsId,
                        'remark_length' => mb_strlen($remarkText),
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('更新或创建扣费配置备注失败', [
                    'quotation_id' => $quotationId,
                    'goods_id' => $goodsId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}

