<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationPriceConfig;
use addon\recycle\app\dict\quotation\QuotationDict;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;
use think\facade\Cache;

/**
 * 价格配置服务
 * Class QuotationPriceConfigService
 * @package addon\recycle\app\service\admin\quotation
 */
class QuotationPriceConfigService extends BaseAdminService
{
    /**
     * @var RecycleQuotationPriceConfig
     */
    protected $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleQuotationPriceConfig();
    }

    /**
     * 获取价格配置列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = []): array
    {
        $field = 'pc.id,pc.site_id,pc.config_type,pc.goods_id,pc.capacity,pc.capacity_answer_id,pc.config_item_name,pc.group_key,pc.sku_list,pc.adjustment_type,pc.adjustment_value,pc.is_enable,pc.title,pc.create_at,pc.update_at';
        
        // 关联查询型号表获取型号名称
        $search_model = $this->model->alias('pc')
            ->leftJoin('recycle_quotation_model qm', 'pc.goods_id = qm.goods_id AND pc.site_id = qm.site_id')
            ->where([['pc.site_id', '=', $this->site_id]])
            ->field($field . ',qm.goods_name')
            ->order('pc.id desc');
        
        // 手动处理搜索条件（因为使用了别名）
        if (!empty($where['config_type'])) {
            $search_model->where('pc.config_type', $where['config_type']);
        }
        if (!empty($where['goods_id'])) {
            $search_model->where('pc.goods_id', $where['goods_id']);
        }
        if (isset($where['is_enable']) && $where['is_enable'] !== '') {
            $search_model->where('pc.is_enable', $where['is_enable']);
        }
        
        $list = $this->pageQuery($search_model);
        
        // 处理SKU列表显示
        if (!empty($list['data'])) {
            foreach ($list['data'] as &$item) {
                // 如果有sku_list，解析并显示SKU数量
                if (!empty($item['sku_list'])) {
                    // 如果sku_list已经是数组或对象，直接使用；如果是字符串，则解码
                    if (is_string($item['sku_list'])) {
                        $skuList = json_decode($item['sku_list'], true) ?: [];
                    } elseif (is_array($item['sku_list'])) {
                        $skuList = $item['sku_list'];
                    } elseif (is_object($item['sku_list'])) {
                        $skuList = json_decode(json_encode($item['sku_list']), true) ?: [];
                    } else {
                        $skuList = [];
                    }
                    $item['sku_count'] = count($skuList);
                    $item['sku_list_parsed'] = $skuList;
                } else {
                    $item['sku_count'] = 0;
                    $item['sku_list_parsed'] = [];
                }
                
                // 如果型号名称为空，尝试从报价数据表中获取
                if (empty($item['goods_name']) && !empty($item['goods_id'])) {
                    $goodsName = \think\facade\Db::name('recycle_quotation_data')
                        ->where([
                            ['site_id', '=', $this->site_id],
                            ['goods_id', '=', $item['goods_id']],
                            ['is_current', '=', 1]
                        ])
                        ->order('id desc')
                        ->value('goods_name');
                    $item['goods_name'] = $goodsName ?: '';
                }
            }
        }
        
        return $list;
    }

    /**
     * 获取价格配置详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id): array
    {
        $info = $this->model
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new CommonException('配置不存在');
        }

        // 处理 sku_list：补充 goods_name
        if (!empty($info['sku_list'])) {
            $skuList = $info['sku_list'];
            if (!is_array($skuList)) {
                $skuList = is_string($skuList) ? json_decode($skuList, true) : [];
                $skuList = $skuList ?: [];
            }
            
            // 为每个 SKU 补充 goods_name（如果缺失）
            foreach ($skuList as &$sku) {
                if (empty($sku['goods_name']) && !empty($sku['goods_id'])) {
                    $goodsName = \think\facade\Db::name('recycle_quotation_data')
                        ->where([
                            ['site_id', '=', $this->site_id],
                            ['goods_id', '=', $sku['goods_id']],
                            ['is_current', '=', 1]
                        ])
                        ->order('id desc')
                        ->value('goods_name');
                    $sku['goods_name'] = $goodsName ?: '';
                }
            }
            unset($sku);
            
            $info['sku_list'] = $skuList;
        }

        return $info;
    }

    /**
     * 添加价格配置
     * @param array $data
     * @return int
     */
    public function add(array $data): int
    {
        $data['site_id'] = $this->site_id;
        
        // 处理 sku_list：如果是数组，模型会自动编码；如果是字符串，需要先解码
        if (isset($data['sku_list']) && is_string($data['sku_list'])) {
            $data['sku_list'] = json_decode($data['sku_list'], true) ?: [];
        }
        
        // 验证配置类型和数据完整性
        $this->validateConfigData($data);

        $result = $this->model->save($data);
        if (!$result) {
            throw new CommonException('添加失败');
        }

        // 清除缓存
        $this->clearCache();

        return $this->model->id;
    }

    /**
     * 编辑价格配置
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data): bool
    {
        $info = $this->model
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])
            ->findOrEmpty();

        if ($info->isEmpty()) {
            throw new CommonException('配置不存在');
        }

        // 如果是 SKU 级别配置且有 sku_list，保留原有的 sku_list（如果前端没有传递）
        if (($data['config_type'] ?? 0) == QuotationDict::CONFIG_TYPE_SKU && empty($data['sku_list'])) {
            // 如果原来有 sku_list，保留它
            if (!empty($info->sku_list)) {
                $data['sku_list'] = $info->sku_list;
            }
        }

        // 验证配置类型和数据完整性
        $this->validateConfigData($data);

        // 处理 sku_list：如果是数组，模型会自动编码；如果是字符串，需要先解码
        if (isset($data['sku_list']) && is_string($data['sku_list'])) {
            $data['sku_list'] = json_decode($data['sku_list'], true) ?: [];
        }

        $result = $info->save($data);
        
        // 清除缓存
        if ($result) {
            $this->clearCache();
        }

        return $result;
    }

    /**
     * 删除价格配置
     * @param int $id
     * @return bool
     */
    public function del(int $id): bool
    {
        $info = $this->model
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id]
            ])
            ->findOrEmpty();

        if ($info->isEmpty()) {
            throw new CommonException('配置不存在');
        }

        $result = $info->delete();
        
        // 清除缓存
        if ($result) {
            $this->clearCache();
        }

        return $result;
    }

    /**
     * 批量添加SKU配置（智能合并）
     * 如果已有相同配置规则的记录，则合并SKU列表；否则创建新记录
     * 如果SKU在其他配置中存在，会从旧配置中移除，避免重复配置
     * @param array $skus SKU数组：[{goods_id, capacity, capacity_answer_id, config_item_name, goods_name}, ...]
     * @param int $adjustmentType 调整类型
     * @param float $adjustmentValue 调整值
     * @param int $isEnable 是否启用
     * @param bool $autoRemoveFromOtherConfigs 是否自动从其他配置中移除重复的SKU（默认true）
     * @return array 返回创建或更新的配置ID列表，以及冲突信息
     */
    public function batchAddSkuConfig(array $skus, int $adjustmentType, float $adjustmentValue, int $isEnable = 1, bool $autoRemoveFromOtherConfigs = true): array
    {
        if (empty($skus)) {
            throw new CommonException('SKU列表不能为空');
        }

        // 检查要添加的SKU是否在其他配置中存在（不同的调整规则）
        $conflictInfo = [
            'removed_from_configs' => [], // 从哪些配置中移除了SKU
            'new_skus' => [], // 新增的SKU
            'duplicate_skus' => [] // 重复的SKU（相同配置规则中的）
        ];

        if ($autoRemoveFromOtherConfigs) {
            // 查询所有SKU级别的配置（排除相同配置规则的）
            $otherConfigs = $this->model
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['config_type', '=', QuotationDict::CONFIG_TYPE_SKU],
                    ['is_enable', '=', $isEnable]
                ])
                ->where(function ($query) use ($adjustmentType, $adjustmentValue) {
                    $query->where('adjustment_type', '<>', $adjustmentType)
                        ->whereOr(function ($q) use ($adjustmentType, $adjustmentValue) {
                            $q->where('adjustment_type', '=', $adjustmentType)
                                ->where('adjustment_value', '<>', $adjustmentValue);
                        });
                })
                ->select();

            // 从其他配置中移除重复的SKU
            foreach ($otherConfigs as $otherConfig) {
                $otherSkuList = $otherConfig->sku_list ?? [];
                if (!is_array($otherSkuList)) {
                    $otherSkuList = is_string($otherSkuList) ? json_decode($otherSkuList, true) : [];
                    $otherSkuList = $otherSkuList ?: [];
                }

                $updatedSkuList = [];
                $removedSkus = [];

                foreach ($otherSkuList as $otherSku) {
                    $otherSkuKey = $otherSku['goods_id'] . '_' . ($otherSku['capacity'] ?? '') . '_' . ($otherSku['config_item_name'] ?? '');
                    $shouldRemove = false;

                    // 检查是否在新SKU列表中
                    foreach ($skus as $newSku) {
                        $newSkuKey = $newSku['goods_id'] . '_' . ($newSku['capacity'] ?? '') . '_' . ($newSku['config_item_name'] ?? '');
                        if ($newSkuKey === $otherSkuKey) {
                            $shouldRemove = true;
                            $removedSkus[] = $otherSku;
                            break;
                        }
                    }

                    if (!$shouldRemove) {
                        $updatedSkuList[] = $otherSku;
                    }
                }

                // 如果有SKU被移除，更新配置
                if (count($removedSkus) > 0) {
                    $otherConfig->sku_list = $updatedSkuList;
                    $otherConfig->save();

                    $conflictInfo['removed_from_configs'][] = [
                        'config_id' => $otherConfig->id,
                        'adjustment_type' => $otherConfig->adjustment_type,
                        'adjustment_value' => $otherConfig->adjustment_value,
                        'removed_skus' => $removedSkus,
                        'remaining_count' => count($updatedSkuList)
                    ];
                }
            }
        }

        // 检查是否已存在相同配置规则的记录
        $existingConfig = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['config_type', '=', QuotationDict::CONFIG_TYPE_SKU],
                ['adjustment_type', '=', $adjustmentType],
                ['adjustment_value', '=', $adjustmentValue],
                ['is_enable', '=', $isEnable]
            ])
            ->find();

        $configIds = [];

        if (!empty($existingConfig)) {
            // 合并到现有配置
            $existingSkuList = $existingConfig->sku_list ?? [];
            if (!is_array($existingSkuList)) {
                $existingSkuList = is_string($existingSkuList) ? json_decode($existingSkuList, true) : [];
                $existingSkuList = $existingSkuList ?: [];
            }

            // 合并SKU列表（去重）
            $mergedSkuList = $existingSkuList;
            foreach ($skus as $sku) {
                $skuKey = $sku['goods_id'] . '_' . ($sku['capacity'] ?? '') . '_' . ($sku['config_item_name'] ?? '');
                $exists = false;

                foreach ($mergedSkuList as $existingSku) {
                    $existingKey = $existingSku['goods_id'] . '_' . ($existingSku['capacity'] ?? '') . '_' . ($existingSku['config_item_name'] ?? '');
                    if ($existingKey === $skuKey) {
                        $exists = true;
                        $conflictInfo['duplicate_skus'][] = $sku;
                        break;
                    }
                }

                if (!$exists) {
                    $mergedSkuList[] = [
                        'goods_id' => $sku['goods_id'],
                        'goods_name' => $sku['goods_name'] ?? '',
                        'capacity' => $sku['capacity'] ?? '',
                        'capacity_answer_id' => $sku['capacity_answer_id'] ?? 0,
                        'config_item_name' => $sku['config_item_name'] ?? ''
                    ];
                    $conflictInfo['new_skus'][] = $sku;
                }
            }

            // 更新配置
            $existingConfig->sku_list = $mergedSkuList;
            $existingConfig->save();

            $configIds[] = $existingConfig->id;
        } else {
            // 创建新配置
            $skuListData = [];
            foreach ($skus as $sku) {
                $skuListData[] = [
                    'goods_id' => $sku['goods_id'],
                    'goods_name' => $sku['goods_name'] ?? '',
                    'capacity' => $sku['capacity'] ?? '',
                    'capacity_answer_id' => $sku['capacity_answer_id'] ?? 0,
                    'config_item_name' => $sku['config_item_name'] ?? ''
                ];
                $conflictInfo['new_skus'][] = $sku;
            }

            $configData = [
                'site_id' => $this->site_id,
                'config_type' => QuotationDict::CONFIG_TYPE_SKU,
                'goods_id' => 0,
                'capacity' => '',
                'capacity_answer_id' => 0,
                'config_item_name' => '',
                'group_key' => 0,
                'adjustment_type' => $adjustmentType,
                'adjustment_value' => $adjustmentValue,
                'is_enable' => $isEnable,
                'sku_list' => $skuListData
            ];

            // 验证配置数据（与添加逻辑保持一致）
            $this->validateConfigData($configData);

            $result = $this->model->save($configData);
            if (!$result) {
                throw new CommonException('添加失败');
            }

            $configIds[] = $this->model->id;
        }

        // 清除缓存
        $this->clearCache();

        return [
            'config_ids' => $configIds,
            'conflict_info' => $conflictInfo
        ];
    }

    /**
     * 批量删除价格配置
     * @param array $ids
     * @return bool
     */
    public function batchDel(array $ids): bool
    {
        if (empty($ids)) {
            throw new CommonException('请选择要删除的配置');
        }

        $result = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['id', 'in', $ids]
            ])
            ->delete();

        // 清除缓存
        if ($result) {
            $this->clearCache();
        }

        return $result !== false;
    }

    /**
     * 清空所有价格配置
     * @return bool
     */
    public function clearAll(): bool
    {
        $result = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->delete();

        // 清除缓存
        if ($result) {
            $this->clearCache();
        }

        return $result !== false;
    }

    /**
     * 获取匹配的价格配置（按优先级）
     * @param int $goodsId
     * @param string $capacity
     * @param string $configItemName
     * @return array
     */
    public function getMatchingConfig(int $goodsId, string $capacity, string $configItemName): array
    {
        // 按优先级查询：1-SKU级别 > 2-批量管理 > 3-按型号 > 4-按分组
        
        // 1. 查询SKU级别配置（最高优先级）- 支持sku_list数组
        $skuConfigs = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['config_type', '=', QuotationDict::CONFIG_TYPE_SKU],
                ['is_enable', '=', QuotationDict::STATUS_ENABLED],
            ])
            ->order('id desc')
            ->select();

        \think\facade\Log::write("[SKU匹配] 查找配置 goods_id={$goodsId}, capacity={$capacity}, config_item={$configItemName}, SKU配置数: " . count($skuConfigs), 'info');
        
        foreach ($skuConfigs as $config) {
            // 尝试获取原始数据（不经过模型转换）
            $rawData = $config->getData();
            $skuListRawValue = $rawData['sku_list'] ?? null;
            
            \think\facade\Log::write("[SKU匹配-原始] 配置ID={$config->id}, getData中sku_list类型=" . gettype($skuListRawValue) . ", 值=" . (is_string($skuListRawValue) ? substr($skuListRawValue, 0, 200) : json_encode($skuListRawValue)), 'info');
            
            // sku_list 字段在模型中已设置为 JSON 字段，会自动转换为数组
            $skuListRaw = $config->sku_list ?? null;
            \think\facade\Log::write("[SKU匹配-模型] 配置ID={$config->id}, sku_list类型=" . gettype($skuListRaw) . ", 值=" . (is_string($skuListRaw) ? substr($skuListRaw, 0, 200) : json_encode($skuListRaw)), 'info');
            
            // 如果模型自动转换失败，手动解析原始值
            $skuList = $skuListRaw;
            if (!is_array($skuList) && is_string($skuListRawValue)) {
                \think\facade\Log::write("[SKU匹配] 模型转换失败，尝试手动解析JSON", 'info');
                $skuList = json_decode($skuListRawValue, true);
                if (!is_array($skuList)) {
                    \think\facade\Log::write("[SKU匹配] JSON解析失败: " . json_last_error_msg(), 'info');
                    $skuList = [];
                }
            } else if (!is_array($skuList)) {
                $skuList = [];
            }
            
            \think\facade\Log::write("[SKU匹配-最终] 配置ID={$config->id}, SKU数量=" . count($skuList), 'info');
            
            // 检查SKU是否在列表中
            foreach ($skuList as $index => $sku) {
                $skuGoodsId = $sku['goods_id'] ?? null;
                $skuCapacity = $sku['capacity'] ?? '';
                $skuConfigItem = $sku['config_item_name'] ?? '';
                
                $match1 = $skuGoodsId == $goodsId;
                $match2 = $skuCapacity == $capacity;
                $match3 = $skuConfigItem == $configItemName;
                
                if ($match1 && $match2 && $match3) {
                    \think\facade\Log::write("[SKU匹配成功] 找到匹配项: goods_id={$skuGoodsId}, capacity={$skuCapacity}, config_item={$skuConfigItem}", 'info');
                    return $config->toArray();
                } else if ($match1 && $match2) {
                    \think\facade\Log::write("[SKU部分匹配] SKU#{$index}: goods_id匹配={$match1}, capacity匹配={$match2}, config_item匹配={$match3} (期望:{$configItemName}, 实际:{$skuConfigItem})", 'info');
                }
            }
        }

        // 2. 查询批量管理配置（高优先级）
        $config = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['config_type', '=', QuotationDict::CONFIG_TYPE_MODEL_CAPACITY],
                ['goods_id', '=', $goodsId],
                ['capacity', '=', $capacity],
                ['config_item_name', '=', ''],
                ['is_enable', '=', QuotationDict::STATUS_ENABLED],
            ])
            ->order('id desc')
            ->find();

        if (!empty($config)) {
            return $config->toArray();
        }

        // 3. 查询型号级别配置（中等优先级）
        $config = $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['config_type', '=', QuotationDict::CONFIG_TYPE_MODEL],
                ['goods_id', '=', $goodsId],
                ['capacity', '=', ''],
                ['is_enable', '=', QuotationDict::STATUS_ENABLED],
            ])
            ->order('id desc')
            ->find();

        if (!empty($config)) {
            return $config->toArray();
        }

        // 4. 查询分组级别配置（最低优先级）- 需要从报价数据中获取group_key
        // 这里暂时返回空，如果需要分组配置，需要传入group_key参数

        return [];
    }

    /**
     * 验证配置数据
     * @param array $data
     * @return void
     */
    protected function validateConfigData(array $data): void
    {
        $configType = $data['config_type'] ?? 0;

        switch ($configType) {
            case QuotationDict::CONFIG_TYPE_SKU:
                // SKU级别：如果有sku_list，则不需要单独验证goods_id等字段；否则需要提供goods_id, capacity, config_item_name
                if (!empty($data['sku_list'])) {
                    // 使用sku_list，验证sku_list格式
                    $skuList = is_string($data['sku_list']) ? json_decode($data['sku_list'], true) : $data['sku_list'];
                    if (empty($skuList) || !is_array($skuList)) {
                        throw new CommonException('SKU级别配置的sku_list必须是非空数组');
                    }
                } else {
                    // 兼容旧数据：必须提供goods_id, capacity, config_item_name
                    if (empty($data['goods_id']) || empty($data['capacity']) || empty($data['config_item_name'])) {
                        throw new CommonException('SKU级别配置必须提供型号、内存和配置项，或提供sku_list');
                    }
                }
                break;

            case QuotationDict::CONFIG_TYPE_MODEL_CAPACITY:
                // 批量管理：必须提供goods_id, capacity
                if (empty($data['goods_id']) || empty($data['capacity'])) {
                    throw new CommonException('批量管理配置必须提供型号和内存');
                }
                break;

            case QuotationDict::CONFIG_TYPE_MODEL:
                // 按型号：必须提供goods_id
                if (empty($data['goods_id'])) {
                    throw new CommonException('按型号配置必须提供型号');
                }
                break;

            case QuotationDict::CONFIG_TYPE_GROUP:
                // 按分组：必须提供group_key
                if (empty($data['group_key'])) {
                    throw new CommonException('按分组配置必须提供分组key');
                }
                break;

            default:
                throw new CommonException('无效的配置类型');
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
     * 修改价格配置状态
     * modifyStatus
    */
    public function modifyStatus(int $id, int $status): bool
    {
        $result = $this->model
            ->where([['site_id', '=', $this->site_id], ['id', '=', $id]])
            ->update(['is_enable' => $status]);
        return $result !== false;
    }
}

