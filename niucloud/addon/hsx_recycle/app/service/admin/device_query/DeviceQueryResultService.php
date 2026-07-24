<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device_query;

use addon\hsx_recycle\app\model\third_party\DeviceQueryResult;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 设备查询结果服务类
 * Class DeviceQueryResultService
 * @package addon\hsx_recycle\app\service\admin
 */
class DeviceQueryResultService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new DeviceQueryResult();
    }

    /**
     * 获取查询结果分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,query_code,query_type,api_endpoint,api_name,status,cost_amount,response_time,error_code,error_message,operator_name,remark,raw_response,create_at';
        $order = 'create_at desc';

        $search_model = $this->applyFilters($this->model->where([['site_id', '=', $this->site_id]]), $where)
            ->field($field)
            ->order($order)
            ->append(['status_name', 'query_type_name']);

        $result = $this->pageQuery($search_model);
        $list = $result['data'] ?? $result['list'] ?? [];
        foreach ($list as &$item) {
            $item = $this->formatResultItem($item);
        }
        unset($item);
        if (isset($result['data'])) {
            $result['data'] = $list;
        } elseif (isset($result['list'])) {
            $result['list'] = $list;
        }

        return $result;
    }

    /**
     * 获取查询结果详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,query_code,query_type,api_endpoint,api_name,query_result,raw_response,status,cost_amount,response_time,error_code,error_message,operator_id,operator_name,remark,create_at,update_at';

        $info = $this->model->field($field)
            ->where([['id', '=', $id], ['site_id', '=', $this->site_id]])
            ->append(['status_name', 'query_type_name'])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new CommonException('查询结果不存在');
        }

        return $this->formatResultItem($info);
    }

    private function formatResultItem(array $item): array
    {
        $meta = is_array($item['raw_response']['meta'] ?? null) ? $item['raw_response']['meta'] : [];
        $raw = is_array($item['raw_response']['raw'] ?? null) ? $item['raw_response']['raw'] : [];

        $item['service_code'] = (string)($meta['service_code'] ?? '');
        $item['service_name'] = (string)($meta['service_name'] ?? $item['api_name'] ?? '');
        $item['channel_name'] = (string)($meta['channel_name'] ?? '');
        $item['channel_key'] = (string)($meta['channel_key'] ?? '');
        $item['third_cost'] = (float)($meta['third_cost'] ?? $raw['cost'] ?? $item['cost_amount'] ?? 0);
        $item['balance'] = (float)($meta['balance'] ?? $raw['balance'] ?? 0);
        $item['create_at_text'] = $this->formatTimeValue($item['create_at'] ?? '');
        $item['update_at_text'] = $this->formatTimeValue($item['update_at'] ?? '');

        return $item;
    }

    private function formatTimeValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_numeric($value)) {
            $timestamp = (int)$value;
        } else {
            $timestamp = strtotime((string)$value);
        }

        return $timestamp > 0 ? date('Y-m-d H:i:s', $timestamp) : (string)$value;
    }

    /**
     * 删除查询结果
     * @param int $id
     * @return bool
     */
    public function del(int $id)
    {
        $model = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($model)) {
            throw new CommonException('查询结果不存在');
        }

        $res = $model->delete();
        return $res;
    }

    /**
     * 批量删除查询结果
     * @param array $ids
     * @return bool
     */
    public function batchDel(array $ids)
    {
        if (empty($ids)) {
            throw new CommonException('请选择要删除的记录');
        }

        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['id', 'in', $ids]
        ])->delete();

        return true;
    }

    /**
     * 获取查询统计信息
     * @param array $where
     * @return array
     */
    public function getStats(array $where = [])
    {
        $query = $this->applyFilters($this->model->where([['site_id', '=', $this->site_id]]), $where);

        $stats = [
            'total_queries' => (clone $query)->count(),
            'success_queries' => (clone $query)->where('status', 1)->count(),
            'failed_queries' => (clone $query)->where('status', 0)->count(),
            'total_cost' => (clone $query)->where('status', 1)->sum('cost_amount'),
            'avg_response_time' => (clone $query)->avg('response_time')
        ];

        // 计算成功率
        $stats['success_rate'] = $stats['total_queries'] > 0 
            ? round(($stats['success_queries'] / $stats['total_queries']) * 100, 2) 
            : 0;

        return $stats;
    }

    public function getQueryOverview(): array
    {
        $query = $this->model->where([['site_id', '=', $this->site_id]]);
        $total = $query->count();
        $success = (clone $query)->where('status', 1)->count();
        $failed = $total - $success;

        return [
            'total_queries' => $total,
            'success_queries' => $success,
            'failed_queries' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 0,
            'total_cost' => (clone $query)->where('status', 1)->sum('cost_amount'),
        ];
    }

    /**
     * 获取API使用统计
     * @param array $where
     * @return array
     */
    public function getApiStats(array $where = [])
    {
        $query = $this->model->where([['site_id', '=', $this->site_id]]);

        // 添加时间范围筛选
        if (!empty($where['create_at']) && is_array($where['create_at'])) {
            [$startTime, $endTime] = $this->normalizeDateRange($where['create_at'][0], $where['create_at'][1]);
            $query->whereBetweenTime('create_at', $startTime, $endTime);
        }

        $apiStats = $query->field('api_endpoint,api_name,count(*) as query_count,sum(cost_amount) as total_cost,avg(response_time) as avg_response_time')
            ->where('status', 1)
            ->group('api_endpoint')
            ->order('query_count desc')
            ->select()
            ->toArray();

        return $apiStats;
    }

    /**
     * 清理过期查询记录
     * @param int $days 保留天数，默认30天
     * @return int 清理的记录数
     */
    public function cleanExpiredRecords(int $days = 30): int
    {
        $expireTime = time() - ($days * 24 * 3600);
        
        $count = $this->model->where([
            ['site_id', '=', $this->site_id],
            ['create_at', '<', $expireTime]
        ])->count();

        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['create_at', '<', $expireTime]
        ])->delete();

        return $count;
    }

    /**
     * 导出查询结果
     * @param array $where
     * @return array
     */
    public function export(array $where = [])
    {
        $field = 'query_code,query_type,api_name,status,cost_amount,response_time,error_message,operator_name,create_at';
        
        $list = $this->applyFilters($this->model->where([['site_id', '=', $this->site_id]]), $where)
            ->field($field)
            ->order('create_at desc')
            ->append(['status_name', 'query_type_name'])
            ->select()
            ->toArray();

        // 格式化数据
        foreach ($list as &$item) {
            $item['create_at'] = date('Y-m-d H:i:s', $item['create_at']);
            $item['cost_amount'] = number_format($item['cost_amount'], 1);
            $item['response_time'] = $item['response_time'] . 'ms';
        }

        return $list;
    }

    /**
     * 查询记录本身就是成本事实。所有列表、统计和导出共用同一套筛选，
     * 避免页面明细已过滤但顶部成本仍显示全站口径。
     */
    private function applyFilters($query, array $where)
    {
        $query->withSearch(['query_code', 'api_endpoint', 'status', 'query_type', 'create_at'], $where);
        $serviceKeyword = trim((string)($where['service_keyword'] ?? ''));
        if ($serviceKeyword !== '') {
            $query->whereLike('api_name|api_endpoint', '%' . $serviceKeyword . '%');
        }
        $channelKeyword = trim((string)($where['channel_keyword'] ?? ''));
        if ($channelKeyword !== '') {
            $query->whereLike('raw_response', '%' . $channelKeyword . '%');
        }
        $operatorName = trim((string)($where['operator_name'] ?? ''));
        if ($operatorName !== '') {
            $query->whereLike('operator_name', '%' . $operatorName . '%');
        }
        if (($where['min_cost'] ?? '') !== '') {
            $query->where('cost_amount', '>=', max(0, (float)$where['min_cost']));
        }
        if (($where['max_cost'] ?? '') !== '') {
            $query->where('cost_amount', '<=', max(0, (float)$where['max_cost']));
        }
        return $query;
    }
    // 查询当前站点的总消费
    // 可以根据不同的 时间 筛选
    // 返回的数据  有 总消费 和 总次数 
    public function getTotalConsumption(array $where = []){
        $query = $this->model->where([['site_id', '=', $this->site_id]]);
        if(!empty($where['create_at'][0]) && !empty($where['create_at'][1])){
            [$startTime, $endTime] = $this->normalizeDateRange($where['create_at'][0], $where['create_at'][1]);
            $query->whereBetweenTime('create_at', $startTime, $endTime);
        }
        $total_consumption = (clone $query)->where('status', 1)->sum('cost_amount');
        $total_count = $query->count();
        return ['total_consumption' => $total_consumption, 'total_count' => $total_count];
    }

    private function normalizeDateRange($startDate, $endDate): array
    {
        $startTimestamp = strtotime((string)$startDate);
        $endTimestamp = strtotime((string)$endDate);

        return [
            $startTimestamp > 0 ? date('Y-m-d 00:00:00', $startTimestamp) : (string)$startDate,
            $endTimestamp > 0 ? date('Y-m-d 23:59:59', $endTimestamp) : (string)$endDate,
        ];
    }
}
