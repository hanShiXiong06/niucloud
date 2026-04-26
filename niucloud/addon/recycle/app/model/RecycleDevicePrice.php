<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 回收设备价格模型
 * Class RecycleDevicePrice
 * @package addon\recycle\app\model
 */
class RecycleDevicePrice extends BaseModel
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
    protected $name = 'recycle_device_price';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = true;

    /**
     * JSON字段
     * @var array
     */
    protected $json = ['price_data'];

    /**
     * JSON字段自动转换
     * @var array
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
     * 追加属性
     * @var array
     */
    // protected $append = [
    //     'status_name',
    //     'is_current_name',
    //     'price_summary'
    // ];

    /**
     * 获取状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        $status = [
            0 => '禁用',
            1 => '启用'
        ];
        return $status[$data['status']] ?? '未知';
    }

    /**
     * 获取是否当前价格名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getIsCurrentNameAttr($value, $data)
    {
        return $data['is_current'] ? '是' : '否';
    }

    /**
     * 获取价格摘要（用于列表显示）
     * @param $value
     * @param $data
     * @return string
     */
    public function getPriceSummaryAttr($value, $data)
    {
        if (empty($data['price_data'])) {
            return '暂无价格';
        }
        
        $priceData = is_string($data['price_data']) ? json_decode($data['price_data'], true) : $data['price_data'];
        if (!is_array($priceData)) {
            return '数据格式错误';
        }
        
        $summary = [];
        foreach ($priceData as $key => $value) {
            if (is_numeric($value) && $value > 0) {
                $summary[] = $key . ':' . $value . '元';
            }
        }
        
        return implode(' | ', array_slice($summary, 0, 3)) . (count($summary) > 3 ? '...' : '');
    }

    /**
     * 价格数据修改器
     * @param $value
     * @return array|string
     */
    public function setPriceDataAttr($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded ?: [];
        }
        return is_array($value) ? $value : [];
    }

    /**
     * 价格数据获取器
     * @param $value
     * @return array
     */
    public function getPriceDataAttr($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded ?: [];
        }
        return is_array($value) ? $value : [];
    }

    /**
     * 站点隔离搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('site_id', $value);
        }
    }

    /**
     * 设备型号ID搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchDeviceModelIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('device_model_id', $value);
        }
    }

    /**
     * 价格日期搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchPriceDateAttr($query, $value, $data)
    {
        if (!empty($value)) {
            if (is_array($value) && count($value) == 2) {
                $query->whereBetween('price_date', [$value[0], $value[1]]);
            } else {
                $query->where('price_date', $value);
            }
        }
    }

    /**
     * 是否当前价格搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchIsCurrentAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('is_current', $value);
        }
    }

    /**
     * 状态搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchStatusAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('status', $value);
        }
    }

    /**
     * 关联设备型号
     * @return \think\model\relation\BelongsTo
     */
    public function deviceModel()
    {
        return $this->belongsTo(RecycleDeviceModel::class, 'device_model_id', 'id');
    }

    /**
     * 关联导入记录
     * @return \think\model\relation\BelongsTo
     */
    public function importRecord()
    {
        return $this->belongsTo(RecyclePriceImportRecord::class, 'import_record_id', 'id');
    }

    /**
     * 检查当天是否已有价格
     * @param int $siteId
     * @param int $deviceModelId
     * @param string $priceDate
     * @return array|null
     */
    public function checkTodayPrice(int $siteId, int $deviceModelId, string $priceDate)
    {
        return $this->where([
            'site_id' => $siteId,
            'device_model_id' => $deviceModelId,
            'price_date' => $priceDate
        ])->findOrEmpty()->toArray();
    }

    /**
     * 获取设备最新价格
     * @param int $siteId
     * @param int $deviceModelId
     * @return array|null
     */
    public function getLatestPrice(int $siteId, int $deviceModelId)
    {
        return $this->where([
            'site_id' => $siteId,
            'device_model_id' => $deviceModelId,
            'status' => 1
        ])->order('price_date desc')->findOrEmpty()->toArray();
    }

    /**
     * 设置当前价格
     * @param int $siteId
     * @param int $deviceModelId
     * @param int $priceId
     * @return bool
     */
    public function setCurrentPrice(int $siteId, int $deviceModelId, int $priceId): bool
    {
        // 先取消所有当前价格
        $this->where([
            'site_id' => $siteId,
            'device_model_id' => $deviceModelId
        ])->update(['is_current' => 0]);

        // 设置新的当前价格
        return $this->where([
            'id' => $priceId,
            'site_id' => $siteId,
            'device_model_id' => $deviceModelId
        ])->update(['is_current' => 1]) > 0;
    }

    /**
     * 批量更新价格数据
     * @param int $siteId
     * @param array $priceList
     * @return bool
     */
    public function batchUpdatePrices(int $siteId, array $priceList): bool
    {
        $this->startTrans();
        try {
            foreach ($priceList as $priceItem) {
                if (empty($priceItem['device_model_id']) || empty($priceItem['price_data'])) {
                    continue;
                }

                $priceItem['site_id'] = $siteId;
                $priceItem['price_date'] = $priceItem['price_date'] ?? date('Y-m-d');
                
                // 检查当日是否已有价格
                $existing = $this->checkTodayPrice($siteId, $priceItem['device_model_id'], $priceItem['price_date']);
                
                if (!empty($existing)) {
                    // 更新现有价格
                    $this->where('id', $existing['id'])->update([
                        'price_data' => $priceItem['price_data'],
                        'import_record_id' => $priceItem['import_record_id'] ?? 0,
                        'update_at' => date('Y-m-d H:i:s')
                    ]);
                } else {
                    // 创建新价格
                    $this->save($priceItem);
                }
            }
            $this->commit();
            return true;
        } catch (\Exception $e) {
            $this->rollback();
            return false;
        }
    }

    /**
     * 获取价格列表
     * @param array $condition
     * @param int $page
     * @param int $page_size
     * @param string $order
     * @param string $field
     * @return array
     */
    public function getPriceList(array $condition = [], int $page = 1, int $page_size = 10, string $order = 'price_date desc,id desc', string $field = '*'): array
    {
        $data = $this->where($condition)
            ->field($field)
            ->with(['deviceModel' => function($query) {
                $query->field('id,brand_id,model_name,network_model,capacity')
                     ->with(['brand' => function($q) {
                         $q->field('id,brand_name');
                     }]);
            }])
            ->order($order)
            ->page($page, $page_size)
            ->select()
            ->toArray();

        $count = $this->where($condition)->count();
        return ['list' => $data, 'count' => $count];
    }

    /**
     * 添加价格
     * @param array $data
     * @return mixed
     */
    public function addPrice(array $data)
    {
        return $this->save($data);
    }

    /**
     * 编辑价格
     * @param int $id
     * @param array $data
     * @return RecycleDevicePrice
     */
    public function editPrice(int $id, array $data)
    {
        $this->where(['id' => $id])->update($data);
        return $this->find($id);
    }

    /**
     * 删除价格
     * @param int $id
     * @return bool
     */
    public function deletePrice(int $id)
    {
        return $this->destroy($id);
    }

    /**
     * 获取价格信息
     * @param int $id
     * @return array
     */
    public function getPriceInfo(int $id)
    {
        $field = 'id,site_id,device_model_id,price_data,price_date,is_current,status,import_record_id,create_at,update_at';
        return $this->field($field)->where(['id' => $id])->with(['deviceModel'])->findOrEmpty()->toArray();
    }
} 