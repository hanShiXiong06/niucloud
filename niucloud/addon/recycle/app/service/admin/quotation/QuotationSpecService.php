<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\quotation;

use addon\recycle\app\model\quotation\RecycleQuotationModel;
use addon\recycle\app\model\quotation\RecycleQuotationCapacity;
use addon\recycle\app\model\quotation\RecycleQuotationGradeSpec;
use addon\recycle\app\dict\quotation\QuotationDict;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 报价规格服务（初始化、同步管理）
 * Class QuotationSpecService
 * @package addon\recycle\app\service\admin\quotation
 */
class QuotationSpecService extends BaseAdminService
{
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
        $this->modelModel = new RecycleQuotationModel();
        $this->capacityModel = new RecycleQuotationCapacity();
        $this->gradeSpecModel = new RecycleQuotationGradeSpec();
    }

    /**
     * 初始化规格信息（从接口返回的数据中提取）
     * @param array $skuData 接口返回的sku数据
     * @return array 统计信息
     */
    public function initSpecs(array $skuData): array
    {
        $stats = [
            'models_added' => 0,
            'capacities_added' => 0,
            'grade_specs_added' => 0,
        ];

        // 收集所有需要添加的型号
        $modelsToAdd = [];
        // 收集所有需要添加的内存规格（按型号分组）
        $capacitiesToAdd = []; // [model_id => [capacity_info1, capacity_info2, ...]]
        // 收集所有需要添加的等级规格
        $gradeSpecsToAdd = []; // [spec_name => true]

        foreach ($skuData as $group) {
            foreach ($group as $item) {
                $goodsId = $item['goods_id'] ?? 0;
                $goodsName = $item['goods_name'] ?? '';
                
                if ($goodsId > 0 && !empty($goodsName)) {
                    // 收集型号
                    $modelsToAdd[$goodsId] = [
                        'goods_id' => $goodsId,
                        'goods_name' => $goodsName,
                    ];

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

                    // 解析等级规格（从config_attr中提取）
                    $configItems = $item['config_attr'] ?? [];
                    foreach ($configItems as $configItem) {
                        $configName = $configItem['question_name'] ?? '';
                        if (!empty($configName)) {
                            $gradeSpecsToAdd[$configName] = true;
                        }
                    }
                }
            }
        }

        // 批量添加型号
        if (!empty($modelsToAdd)) {
            $addedModels = $this->batchAddModels($modelsToAdd);
            $stats['models_added'] = count($addedModels);
            
            // 建立goods_id到model_id的映射
            $goodsIdToModelId = [];
            foreach ($addedModels as $model) {
                $goodsIdToModelId[$model['goods_id']] = $model['id'];
            }

            // 批量添加内存规格（需要model_id）
            foreach ($skuData as $group) {
                foreach ($group as $item) {
                    $goodsId = $item['goods_id'] ?? 0;
                    if ($goodsId > 0 && isset($goodsIdToModelId[$goodsId])) {
                        $modelId = $goodsIdToModelId[$goodsId];
                        
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

                        if (!empty($capacity) && $capacityAnswerId > 0) {
                            $key = $modelId . '_' . $capacityAnswerId;
                            if (!isset($capacitiesToAdd[$key])) {
                                $capacitiesToAdd[$key] = [
                                    'model_id' => $modelId,
                                    'goods_id' => $goodsId,
                                    'capacity' => $capacity,
                                    'capacity_answer_id' => $capacityAnswerId,
                                ];
                            }
                        }
                    }
                }
            }

            if (!empty($capacitiesToAdd)) {
                $addedCount = $this->batchAddCapacities(array_values($capacitiesToAdd));
                $stats['capacities_added'] = $addedCount;
            }
        }

        // 批量添加等级规格
        if (!empty($gradeSpecsToAdd)) {
            $addedCount = $this->batchAddGradeSpecs(array_keys($gradeSpecsToAdd));
            $stats['grade_specs_added'] = $addedCount;
        }

        Log::info('规格初始化完成', [
            'site_id' => $this->site_id,
            'stats' => $stats,
        ]);

        return $stats;
    }

    /**
     * 批量添加型号
     * @param array $models
     * @return array 返回添加成功的型号列表（包含id）
     */
    protected function batchAddModels(array $models): array
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
                    'sync_enable' => QuotationDict::STATUS_ENABLED, // 默认同步
                    'create_at' => time(),
                    'update_at' => time(),
                ];
            }
        }

        $addedModels = [];
        if (!empty($toInsert)) {
            // 每批100条
            $chunks = array_chunk($toInsert, 100);
            foreach ($chunks as $chunk) {
                $this->modelModel->insertAll($chunk);
            }

            // 查询刚添加的型号，返回包含id的列表
            $goodsIds = array_column($toInsert, 'goods_id');
            $addedModels = $this->modelModel
                ->where('site_id', $this->site_id)
                ->whereIn('goods_id', $goodsIds)
                ->select()
                ->toArray();
        }

        // 合并已存在的型号
        if (!empty($existingGoodsIds)) {
            $existingModels = $this->modelModel
                ->where('site_id', $this->site_id)
                ->whereIn('goods_id', $existingGoodsIds)
                ->select()
                ->toArray();
            $addedModels = array_merge($addedModels, $existingModels);
        }

        return $addedModels;
    }

    /**
     * 批量添加内存规格
     * @param array $capacities
     * @return int 添加的数量
     */
    protected function batchAddCapacities(array $capacities): int
    {
        if (empty($capacities)) {
            return 0;
        }

        // 构建查询条件，检查已存在的记录
        $modelIds = array_column($capacities, 'model_id');
        $capacityAnswerIds = array_column($capacities, 'capacity_answer_id');

        $existing = $this->capacityModel
            ->where('site_id', $this->site_id)
            ->whereIn('model_id', $modelIds)
            ->whereIn('capacity_answer_id', $capacityAnswerIds)
            ->select()
            ->toArray();

        // 构建已存在的键集合
        $existingKeys = [];
        foreach ($existing as $exist) {
            $key = $exist['model_id'] . '_' . $exist['capacity_answer_id'];
            $existingKeys[$key] = true;
        }

        $toInsert = [];
        foreach ($capacities as $capacity) {
            $key = $capacity['model_id'] . '_' . $capacity['capacity_answer_id'];
            if (!isset($existingKeys[$key])) {
                $toInsert[] = [
                    'site_id' => $this->site_id,
                    'model_id' => $capacity['model_id'],
                    'goods_id' => $capacity['goods_id'],
                    'capacity' => $capacity['capacity'],
                    'capacity_answer_id' => $capacity['capacity_answer_id'],
                    'sync_enable' => QuotationDict::STATUS_ENABLED, // 默认同步
                    'create_at' => time(),
                    'update_at' => time(),
                ];
            }
        }

        if (!empty($toInsert)) {
            // 每批100条
            $chunks = array_chunk($toInsert, 100);
            foreach ($chunks as $chunk) {
                $this->capacityModel->insertAll($chunk);
            }
        }

        return count($toInsert);
    }

    /**
     * 批量添加等级规格
     * @param array $specNames
     * @return int 添加的数量
     */
    protected function batchAddGradeSpecs(array $specNames): int
    {
        if (empty($specNames)) {
            return 0;
        }

        // 查询已存在的规格
        $existing = $this->gradeSpecModel
            ->where('site_id', $this->site_id)
            ->whereIn('spec_name', $specNames)
            ->column('spec_name');

        $toInsert = [];
        foreach ($specNames as $specName) {
            if (!in_array($specName, $existing)) {
                $toInsert[] = [
                    'site_id' => $this->site_id,
                    'spec_name' => $specName,
                    'spec_code' => '',
                    'sync_enable' => QuotationDict::STATUS_ENABLED, // 默认同步
                    'sort' => 0,
                    'create_at' => time(),
                    'update_at' => time(),
                ];
            }
        }

        if (!empty($toInsert)) {
            // 每批100条
            $chunks = array_chunk($toInsert, 100);
            foreach ($chunks as $chunk) {
                $this->gradeSpecModel->insertAll($chunk);
            }
        }

        return count($toInsert);
    }

    /**
     * 获取需要同步的规格列表（用于价格请求）
     * @param int|null $modelId 型号ID（可选）
     * @return array
     */
    public function getSyncSpecs(?int $modelId = null): array
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['sync_enable', '=', QuotationDict::STATUS_ENABLED],
        ];

        if ($modelId !== null) {
            $where[] = ['model_id', '=', $modelId];
        }

        // 查询需要同步的型号
        $models = $this->modelModel
            ->where($where)
            ->select()
            ->toArray();

        $modelIds = array_column($models, 'id');
        $goodsIdToModelId = [];
        foreach ($models as $model) {
            $goodsIdToModelId[$model['goods_id']] = $model['id'];
        }

        // 查询需要同步的内存规格
        $capacityWhere = [
            ['site_id', '=', $this->site_id],
            ['sync_enable', '=', QuotationDict::STATUS_ENABLED],
        ];
        if (!empty($modelIds)) {
            $capacityWhere[] = ['model_id', 'in', $modelIds];
        }
        $capacities = $this->capacityModel
            ->where($capacityWhere)
            ->select()
            ->toArray();

        // 查询需要同步的等级规格
        $gradeSpecs = $this->gradeSpecModel
            ->where([
                ['site_id', '=', $this->site_id],
                ['sync_enable', '=', QuotationDict::STATUS_ENABLED],
            ])
            ->order('sort asc, id asc')
            ->select()
            ->toArray();

        return [
            'models' => $models,
            'capacities' => $capacities,
            'grade_specs' => $gradeSpecs,
            'goods_id_to_model_id' => $goodsIdToModelId,
        ];
    }

    /**
     * 设置型号同步状态
     * @param int $id 型号ID
     * @param int $syncEnable 是否同步：1-同步，0-不同步
     * @return bool
     */
    public function setModelSyncStatus(int $id, int $syncEnable): bool
    {
        $model = $this->modelModel
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id],
            ])
            ->findOrEmpty();

        if ($model->isEmpty()) {
            throw new CommonException('型号不存在');
        }

        $model->sync_enable = $syncEnable;
        $model->update_at = time();
        return $model->save();
    }

    /**
     * 设置内存规格同步状态
     * @param int $id 内存ID
     * @param int $syncEnable 是否同步：1-同步，0-不同步
     * @return bool
     */
    public function setCapacitySyncStatus(int $id, int $syncEnable): bool
    {
        $capacity = $this->capacityModel
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id],
            ])
            ->findOrEmpty();

        if ($capacity->isEmpty()) {
            throw new CommonException('内存规格不存在');
        }

        $capacity->sync_enable = $syncEnable;
        $capacity->update_at = time();
        return $capacity->save();
    }

    /**
     * 设置等级规格同步状态
     * @param int $id 等级规格ID
     * @param int $syncEnable 是否同步：1-同步，0-不同步
     * @return bool
     */
    public function setGradeSpecSyncStatus(int $id, int $syncEnable): bool
    {
        $gradeSpec = $this->gradeSpecModel
            ->where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id],
            ])
            ->findOrEmpty();

        if ($gradeSpec->isEmpty()) {
            throw new CommonException('等级规格不存在');
        }

        $gradeSpec->sync_enable = $syncEnable;
        $gradeSpec->update_at = time();
        return $gradeSpec->save();
    }

    /**
     * 获取规格列表（用于管理）
     * @param array $where
     * @return array
     */
    public function getSpecList(array $where = []): array
    {
        $result = [
            'models' => [],
            'capacities' => [],
            'grade_specs' => [],
        ];

        // 查询型号列表
        $modelQuery = $this->modelModel->where([['site_id', '=', $this->site_id]]);
        if (!empty($where['model_goods_name'])) {
            $modelQuery->where('goods_name', 'like', '%' . $where['model_goods_name'] . '%');
        }
        if (isset($where['model_sync_enable']) && $where['model_sync_enable'] !== '') {
            $modelQuery->where('sync_enable', $where['model_sync_enable']);
        }
        $result['models'] = $modelQuery->order('id desc')->select()->toArray();

        // 查询内存规格列表
        $capacityQuery = $this->capacityModel->where([['site_id', '=', $this->site_id]]);
        if (!empty($where['capacity_model_id'])) {
            $capacityQuery->where('model_id', $where['capacity_model_id']);
        }
        if (isset($where['capacity_sync_enable']) && $where['capacity_sync_enable'] !== '') {
            $capacityQuery->where('sync_enable', $where['capacity_sync_enable']);
        }
        $result['capacities'] = $capacityQuery->with(['model'])->order('id desc')->select()->toArray();

        // 查询等级规格列表
        $gradeSpecQuery = $this->gradeSpecModel->where([['site_id', '=', $this->site_id]]);
        if (!empty($where['grade_spec_name'])) {
            $gradeSpecQuery->where('spec_name', 'like', '%' . $where['grade_spec_name'] . '%');
        }
        if (isset($where['grade_spec_sync_enable']) && $where['grade_spec_sync_enable'] !== '') {
            $gradeSpecQuery->where('sync_enable', $where['grade_spec_sync_enable']);
        }
        $result['grade_specs'] = $gradeSpecQuery->order('sort asc, id asc')->select()->toArray();

        return $result;
    }

    /**
     * 获取型号列表
     * @param array $where
     * @return array
     */
    public function getModelList(array $where = []): array
    {
        $query = $this->modelModel->where([['site_id', '=', $this->site_id]]);
        
        if (!empty($where['goods_name'])) {
            $query->where('goods_name', 'like', '%' . $where['goods_name'] . '%');
        }
        if (isset($where['sync_enable']) && $where['sync_enable'] !== '') {
            $query->where('sync_enable', $where['sync_enable']);
        }
        
        return $query->order('id desc')->select()->toArray();
    }

    /**
     * 获取内存列表
     * @param array $where
     * @return array
     */
    public function getCapacityList(array $where = []): array
    {
        $query = $this->capacityModel->where([['site_id', '=', $this->site_id]]);
        
        if (!empty($where['model_id'])) {
            $query->where('model_id', $where['model_id']);
        }
        if (!empty($where['goods_id'])) {
            $query->where('goods_id', $where['goods_id']);
        }
        if (!empty($where['capacity'])) {
            $query->where('capacity', 'like', '%' . $where['capacity'] . '%');
        }
        if (isset($where['sync_enable']) && $where['sync_enable'] !== '') {
            $query->where('sync_enable', $where['sync_enable']);
        }
        
        return $query->with(['model'])->order('id desc')->select()->toArray();
    }

    /**
     * 获取等级规格列表
     * @param array $where
     * @return array
     */
    public function getGradeSpecList(array $where = []): array
    {
        $query = $this->gradeSpecModel->where([['site_id', '=', $this->site_id]]);
        
        if (!empty($where['spec_name'])) {
            $query->where('spec_name', 'like', '%' . $where['spec_name'] . '%');
        }
        if (isset($where['sync_enable']) && $where['sync_enable'] !== '') {
            $query->where('sync_enable', $where['sync_enable']);
        }
        
        return $query->order('sort asc, id asc')->select()->toArray();
    }

    /**
     * 批量设置型号同步状态
     * @param array $ids
     * @param int $syncEnable
     * @return bool
     */
    public function batchSetModelSyncStatus(array $ids, int $syncEnable): bool
    {
        if (empty($ids)) {
            return true;
        }

        return $this->modelModel
            ->where([
                ['id', 'in', $ids],
                ['site_id', '=', $this->site_id],
            ])
            ->update([
                'sync_enable' => $syncEnable,
                'update_at' => time(),
            ]);
    }

    /**
     * 批量设置内存同步状态
     * @param array $ids
     * @param int $syncEnable
     * @return bool
     */
    public function batchSetCapacitySyncStatus(array $ids, int $syncEnable): bool
    {
        if (empty($ids)) {
            return true;
        }

        return $this->capacityModel
            ->where([
                ['id', 'in', $ids],
                ['site_id', '=', $this->site_id],
            ])
            ->update([
                'sync_enable' => $syncEnable,
                'update_at' => time(),
            ]);
    }

    /**
     * 批量设置等级规格同步状态
     * @param array $ids
     * @param int $syncEnable
     * @return bool
     */
    public function batchSetGradeSpecSyncStatus(array $ids, int $syncEnable): bool
    {
        if (empty($ids)) {
            return true;
        }

        return $this->gradeSpecModel
            ->where([
                ['id', 'in', $ids],
                ['site_id', '=', $this->site_id],
            ])
            ->update([
                'sync_enable' => $syncEnable,
                'update_at' => time(),
            ]);
    }

    /**
     * 获取同步规格统计
     * @return array
     */
    public function getSyncStats(): array
    {
        $totalModels = $this->modelModel->where('site_id', $this->site_id)->count();
        $syncModels = $this->modelModel->where([
            ['site_id', '=', $this->site_id],
            ['sync_enable', '=', QuotationDict::STATUS_ENABLED],
        ])->count();

        $totalCapacities = $this->capacityModel->where('site_id', $this->site_id)->count();
        $syncCapacities = $this->capacityModel->where([
            ['site_id', '=', $this->site_id],
            ['sync_enable', '=', QuotationDict::STATUS_ENABLED],
        ])->count();

        $totalGradeSpecs = $this->gradeSpecModel->where('site_id', $this->site_id)->count();
        $syncGradeSpecs = $this->gradeSpecModel->where([
            ['site_id', '=', $this->site_id],
            ['sync_enable', '=', QuotationDict::STATUS_ENABLED],
        ])->count();

        return [
            'models' => [
                'total' => $totalModels,
                'sync' => $syncModels,
                'not_sync' => $totalModels - $syncModels,
            ],
            'capacities' => [
                'total' => $totalCapacities,
                'sync' => $syncCapacities,
                'not_sync' => $totalCapacities - $syncCapacities,
            ],
            'grade_specs' => [
                'total' => $totalGradeSpecs,
                'sync' => $syncGradeSpecs,
                'not_sync' => $totalGradeSpecs - $syncGradeSpecs,
            ],
        ];
    }
}

