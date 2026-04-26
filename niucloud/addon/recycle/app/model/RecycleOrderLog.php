<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 回收订单日志模型
 * Class RecycleOrderLog
 * @package addon\recycle\app\model
 */
class RecycleOrderLog extends BaseModel
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
    protected $name = 'recycle_order_log';

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
     * 获取订单日志列表
     * @param array $condition
     * @param int $page
     * @param int $page_size
     * @param string $order
     * @param string $field
     * @return array
     */
    public function getOrderLogList(array $condition = [], int $page = 1, int $page_size = 10, string $order = 'create_at desc', string $field = '*'): array
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
     * 添加订单日志
     * @param array $data
     * @return mixed
     */
    public function addOrderLog(array $data)
    {
        return $this->save($data);
    }
}
