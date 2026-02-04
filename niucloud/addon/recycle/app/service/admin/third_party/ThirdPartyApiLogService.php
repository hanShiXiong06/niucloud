<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\third_party;

use addon\recycle\app\model\third_party\ThirdPartyApiLog;
use addon\recycle\app\model\third_party\ThirdPartyCostStats;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 第三方API调用日志服务类
 * Class ThirdPartyApiLogService
 * @package addon\recycle\app\service\admin\third_party
 */
class ThirdPartyApiLogService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ThirdPartyApiLog();
    }

    /**
     * 获取API调用日志分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = [])
    {
        $field = 'id,site_id,service_type,provider_name,method,request_params,response_data,cost,duration,status,error_msg,create_at';
        $order = 'create_at desc';

        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['service_type', 'provider_name', 'status'], $where)
            ->field($field)
            ->order($order);

        // 手动处理时间范围过滤
        if (!empty($where['start_time'])) {
            $search_model->where('create_at', '>=', strtotime($where['start_time']));
        }
        if (!empty($where['end_time'])) {
            $search_model->where('create_at', '<=', strtotime($where['end_time']));
        }

        $result = $this->pageQuery($search_model);

        // 添加统计信息
        $stats = $this->getStats($where);
        $result['stats'] = $stats;

        return $result;
    }

    /**
     * 获取API调用日志信息
     * @param int $id
     * @return array
     */
    public function getInfo(int $id)
    {
        $field = 'id,site_id,service_type,provider_name,method,request_params,response_data,cost,duration,status,error_msg,create_at';

        $info = $this->model->field($field)->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();

        if (empty($info)) {
            throw new CommonException('THIRD_PARTY_API_LOG_NOT_EXIST');
        }

        return $info;
    }

    /**
     * 获取统计信息
     * @param array $where
     * @return array
     */
    private function getStats(array $where = [])
    {
        $query = $this->model->where([['site_id', '=', $this->site_id]]);

        if (!empty($where['service_type'])) {
            $query->where('service_type', '=', $where['service_type']);
        }
        if (!empty($where['provider_name'])) {
            $query->where('provider_name', '=', $where['provider_name']);
        }
        if (!empty($where['start_time'])) {
            $query->where('create_at', '>=', strtotime($where['start_time']));
        }
        if (!empty($where['end_time'])) {
            $query->where('create_at', '<=', strtotime($where['end_time']));
        }

        $total_calls = $query->count();
        $success_calls = (clone $query)->where('status', '=', 1)->count();
        $failed_calls = $total_calls - $success_calls;
        $avg_duration = (clone $query)->avg('duration') ?: 0;

        return [
            'total_calls' => $total_calls,
            'success_calls' => $success_calls,
            'failed_calls' => $failed_calls,
            'avg_duration' => round($avg_duration)
        ];
    }

    /**
     * 清理日志
     * @param int $days 保留天数
     * @return bool
     */
    public function clean(int $days = 30)
    {
        $beforeTime = strtotime("-{$days} days");
        $this->model->where([
            ['site_id', '=', $this->site_id],
            ['create_at', '<', $beforeTime]
        ])->delete();

        return true;
    }
}
