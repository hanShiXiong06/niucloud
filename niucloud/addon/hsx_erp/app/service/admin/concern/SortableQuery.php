<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin\concern;

/**
 * 列表查询通用排序 trait —— 收敛各 getPage 里重复的"白名单字段 + asc/desc"排序逻辑。
 *
 * 用法：
 *   use SortableQuery;
 *   ...
 *   $this->applySort($query, $where, ['id', 'out_at', 'total_amount', 'qty']);
 *
 * 白名单防注入：只有列在白名单里才允许排序，否则用默认字段。
 */
trait SortableQuery
{
    /**
     * @param mixed  $query     ThinkPHP 查询对象
     * @param array  $where     请求参数(含 sort_field / sort_order)
     * @param array  $whitelist 允许排序的字段名(= 列名)
     * @param string $default   默认排序字段
     * @param string $defaultOrder 默认方向 asc|desc
     */
    protected function applySort($query, array $where, array $whitelist, string $default = 'id', string $defaultOrder = 'desc'): void
    {
        $field = (string)($where['sort_field'] ?? '');
        $field = in_array($field, $whitelist, true) ? $field : $default;
        $order = strtolower((string)($where['sort_order'] ?? '')) === 'asc' ? 'asc'
            : (strtolower((string)($where['sort_order'] ?? '')) === 'desc' ? 'desc' : $defaultOrder);
        $query->order($field, $order);
    }
}
