<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 回收设备型号模型
 * Class RecycleDeviceModel
 * @package addon\recycle\app\model
 */
class RecycleDeviceModel extends BaseModel
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
    protected $name = 'recycle_device_model';

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
     * 追加属性
     * @var array
     */
    // protected $append = [
    //     'status_name',
    //     'device_type_name',
    //     'brand_name'
    // ];

    /**
     * 获取状态名称
     * @param $value
     * @param $data
     * @return string
     */
    // public function getStatusNameAttr($value, $data)
    // {
    //     $status = [
    //         0 => '禁用',
    //         1 => '启用'
    //     ];
    //     return $status[$data['status']] ?? '未知';
    // }

    /**
     * 获取设备类型名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getDeviceTypeNameAttr($value, $data)
    {
        $types = [
            'phone' => '手机',
            'tablet' => '平板',
            'watch' => '手表'
        ];
        return $types[$data['device_type']] ?? '手机';
    }

    /**
     * 获取品牌名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getBrandNameAttr($value, $data)
    {
        if (isset($data['brand']) && $data['brand']) {
            return $data['brand']['brand_name'] ?? '';
        }
        return '';
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
     * 品牌ID搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchBrandIdAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('brand_id', $value);
        }
    }

    /**
     * 型号名称搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchModelNameAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('model_name', 'like', "%{$value}%");
        }
    }

    /**
     * 网络型号搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchNetworkModelAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('network_model', 'like', "%{$value}%");
        }
    }

    /**
     * 容量搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCapacityAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('capacity', 'like', "%{$value}%");
        }
    }

    /**
     * 设备类型搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchDeviceTypeAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('device_type', $value);
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
     * 关键词搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchKeywordAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where(function($q) use ($value) {
                $q->where('model_name', 'like', "%{$value}%")
                  ->whereOr('network_model', 'like', "%{$value}%")
                  ->whereOr('capacity', 'like', "%{$value}%");
            });
        }
    }

    /**
     * 关联品牌
     * @return \think\model\relation\BelongsTo
     */
    public function brand()
    {
        return $this->belongsTo(RecycleDeviceBrand::class, 'brand_id', 'id')->field('id,brand_name,brand_code');
    }

    /**
     * 关联价格
     * @return \think\model\relation\HasMany
     */
    public function prices()
    {
        return $this->hasMany(RecycleDevicePrice::class, 'device_model_id', 'id');
    }

    /**
     * 关联当前价格
     * @return \think\model\relation\HasOne
     */
    public function currentPrice()
    {
        return $this->hasOne(RecycleDevicePrice::class, 'device_model_id', 'id')
            ->where('is_current', 1)
            ->order('price_date desc');
    }

    /**
     * 检查设备型号是否存在
     * @param int $siteId
     * @param int $brandId
     * @param string $modelName
     * @param string $networkModel
     * @param string $capacity
     * @return array|null
     */
    public function checkModelExists(int $siteId, int $brandId, string $modelName, string $networkModel = '', string $capacity = '')
    {
        return $this->where([
            'site_id' => $siteId,
            'brand_id' => $brandId,
            'model_name' => $modelName,
            'network_model' => $networkModel,
            'capacity' => $capacity
        ])->findOrEmpty()->toArray();
    }

    /**
     * 获取设备型号列表
     * @param array $condition
     * @param int $page
     * @param int $page_size
     * @param string $order
     * @param string $field
     * @return array
     */
    public function getModelList(array $condition = [], int $page = 1, int $page_size = 10, string $order = 'id desc', string $field = '*'): array
    {
        $data = $this->where($condition)
            ->field($field)
            ->with(['brand'])
            ->order($order)
            ->page($page, $page_size)
            ->select()
            ->toArray();

        $count = $this->where($condition)->count();
        return ['list' => $data, 'count' => $count];
    }

    /**
     * 添加设备型号
     * @param array $data
     * @return mixed
     */
    public function addModel(array $data)
    {
        return $this->save($data);
    }

    /**
     * 编辑设备型号
     * @param int $id
     * @param array $data
     * @return RecycleDeviceModel
     */
    public function editModel(int $id, array $data)
    {
        $this->where(['id' => $id])->update($data);
        return $this->find($id);
    }

    /**
     * 删除设备型号
     * @param int $id
     * @return bool
     */
    public function deleteModel(int $id)
    {
        return $this->destroy($id);
    }

    /**
     * 获取设备型号信息
     * @param int $id
     * @return array
     */
    public function getModelInfo(int $id)
    {
        $field = 'id,site_id,brand_id,model_name,network_model,capacity,device_type,status,create_at,update_at';
        return $this->field($field)->where(['id' => $id])->with(['brand'])->findOrEmpty()->toArray();
    }
} 