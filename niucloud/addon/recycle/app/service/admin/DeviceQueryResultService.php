<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin;

use addon\recycle\app\model\DeviceQueryResult;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 设备查询结果服务类
 * Class DeviceQueryResultService
 * @package addon\recycle\app\service\admin
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
        $field = 'id,site_id,query_code,query_type,api_endpoint,api_name,status,cost_amount,response_time,error_code,error_message,operator_name,remark,create_at';
        $order = 'create_at desc';

        $search_model = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['query_code', 'api_endpoint', 'status', 'query_type', 'create_at'], $where)
            ->field($field)
            ->order($order)
            ->append(['status_name', 'query_type_name']);

        return $this->pageQuery($search_model);
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

        return $info;
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
        $query = $this->model->where([['site_id', '=', $this->site_id]]);

        // 添加时间范围筛选
        if (!empty($where['create_at']) && is_array($where['create_at'])) {
            $query->whereBetweenTime('create_at', $where['create_at'][0], $where['create_at'][1]);
        }

        $stats = [
            'total_queries' => $query->count(),
            'success_queries' => $query->where('status', 1)->count(),
            'failed_queries' => $query->where('status', 0)->count(),
            'total_cost' => $query->sum('cost_amount'),
            'avg_response_time' => $query->avg('response_time')
        ];

        // 计算成功率
        $stats['success_rate'] = $stats['total_queries'] > 0 
            ? round(($stats['success_queries'] / $stats['total_queries']) * 100, 2) 
            : 0;

        return $stats;
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
            $query->whereBetweenTime('create_at', $where['create_at'][0], $where['create_at'][1]);
        }

        $apiStats = $query->field('api_endpoint,api_name,count(*) as query_count,sum(cost_amount) as total_cost,avg(response_time) as avg_response_time')
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
        
        $list = $this->model->where([['site_id', '=', $this->site_id]])
            ->withSearch(['query_code', 'api_endpoint', 'status', 'query_type', 'create_at'], $where)
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
    // 查询当前站点的总消费
    // 可以根据不同的 时间 筛选
    // 返回的数据  有 总消费 和 总次数 
    public function getTotalConsumption(array $where = []){
        $query = $this->model->where([['site_id', '=', $this->site_id]]);
        if(!empty($where['create_at'][0]) && !empty($where['create_at'][1])){
            $query->whereBetweenTime('create_at', $where['create_at'][0], $where['create_at'][1]);
        }
        $total_consumption = $query->sum('cost_amount');
        $total_count = $query->count();
        return ['total_consumption' => $total_consumption, 'total_count' => $total_count];
    }
} 