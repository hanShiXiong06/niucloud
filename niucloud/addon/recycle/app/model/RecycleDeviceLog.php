<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use app\model\sys\SysUser;
use core\base\BaseModel;
use addon\recycle\app\dict\order\RecycleOrderDict;

/**
 * 回收设备日志模型
 * Class RecycleDeviceLog
 * @package addon\recycle\app\model
 */
class RecycleDeviceLog extends BaseModel
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
    protected $name = 'recycle_device_log';

    /**
     * 设置创建时间字段
     * @var string
     */
    protected $createTime = 'create_at';

    /**
     * 设置更新时间字段
     * @var bool
     */
    protected $updateTime = false;

    /**
     * 关联操作人表
     * @return \think\model\relation\BelongsTo
     */
    public function operator()
    {
        return $this->belongsTo(SysUser::class, 'operator_id', 'uid')->field('uid','username');
    }

    /**
     * 获取设备日志列表
     * @param array $condition
     * @param int $page
     * @param int $page_size
     * @param string $order
     * @param string $field
     * @return array
     */
    public function getDeviceLogList(array $condition = [], int $page = 1, int $page_size = 10, string $order = ' id desc', string $field = '*'): array
    {
        $data = $this->where($condition)
            ->field($field)
            ->order($order)
            ->with(['operator' => function($query) {
                $query->field('username');
            }])
            ->page($page, $page_size)
            ->select()
            ->toArray();

        $count = $this->where($condition)->count();
        $data = array_map(function($item) {
            $item['status_name'] = RecycleOrderDict::getDeviceOpType($item['new_status']);
            // 检查 $item['operator'] 是否存在且不为 null，再获取 username
            $item['operator_name'] = isset($item['operator']) && $item['operator'] ? $item['operator']['username'] : ($item['operator_name'] ?? '未知操作员'); // 保留模型中可能已有的 operator_name 或设为默认值
            // 删除 operator 字段
            unset($item['operator']);
            return $item;
        }, $data);
        return ['list' => $data, 'count' => $count];
    }

    /**
     * 添加设备日志
     * @param array $data
     * @return mixed
     */
    public function addDeviceLog(array $data)
    {
        return $this->save($data);
    }
}
