<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 回收设备品牌模型
 * Class RecycleDeviceBrand
 * @package addon\recycle\app\model
 */
class RecycleDeviceBrand extends BaseModel
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
    protected $name = 'recycle_device_brand';

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
    //     'status_name'
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
     * 品牌名称搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchBrandNameAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('brand_name', 'like', "%{$value}%");
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
     * @return \think\model\relation\HasMany
     */
    public function deviceModels()
    {
        return $this->hasMany(RecycleDeviceModel::class, 'brand_id', 'id');
    }

    /**
     * 获取品牌列表
     * @param array $condition
     * @param int $page
     * @param int $page_size
     * @param string $order
     * @param string $field
     * @return array
     */
    public function getBrandList(array $condition = [], int $page = 1, int $page_size = 10, string $order = 'id desc', string $field = '*'): array
    {
        $data = $this->where($condition)
            ->field($field)
            ->order($order)
            ->page($page, $page_size)
            ->select()
            ->toArray();

        $count = $this->where($condition)->count();
        return ['list' => $data, 'count' => $count];
    }

    /**
     * 添加品牌
     * @param array $data
     * @return mixed
     */
    public function addBrand(array $data)
    {
        return $this->save($data);
    }

    /**
     * 编辑品牌
     * @param int $id
     * @param array $data
     * @return RecycleDeviceBrand
     */
    public function editBrand(int $id, array $data)
    {
        $this->where(['id' => $id])->update($data);
        return $this->find($id);
    }

    /**
     * 删除品牌
     * @param int $id
     * @return bool
     */
    public function deleteBrand(int $id)
    {
        return $this->destroy($id);
    }

    /**
     * 获取品牌信息
     * @param int $id
     * @return array
     */
    public function getBrandInfo(int $id)
    {
        $field = 'id,site_id,brand_name,brand_code,status,create_at,update_at';
        return $this->field($field)->where(['id' => $id])->findOrEmpty()->toArray();
    }
} 