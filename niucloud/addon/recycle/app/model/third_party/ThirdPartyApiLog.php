<?php
declare(strict_types=1);

namespace addon\recycle\app\model\third_party;

use addon\recycle\app\dict\third_party\ThirdPartyDict;
use core\base\BaseModel;

/**
 * 第三方API调用日志模型
 * Class ThirdPartyApiLog
 * @package addon\recycle\app\model\third_party
 */
class ThirdPartyApiLog extends BaseModel
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
    protected $name = 'third_party_api_log';

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
     * 更新时间字段（不需要）
     * @var false
     */
    protected $updateTime = false;

    /**
     * JSON字段
     * @var array
     */
    protected $json = ['request_params', 'response_data'];

    /**
     * JSON数据返回数组
     * @var bool
     */
    protected $jsonAssoc = true;

    /**
     * 追加属性
     * @var array
     */
    protected $append = ['status_name', 'service_type_name', 'provider_name_text'];

    /**
     * 获取状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        return ThirdPartyDict::getCallStatusName($data['status'] ?? 0);
    }

    /**
     * 获取服务类型名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getServiceTypeNameAttr($value, $data)
    {
        return ThirdPartyDict::getServiceTypeName($data['service_type'] ?? '');
    }

    /**
     * 获取服务提供商名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getProviderNameTextAttr($value, $data)
    {
        return ThirdPartyDict::getProviderName($data['provider_name'] ?? '');
    }

    /**
     * 站点搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchSiteIdAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('site_id', $value);
        }
    }

    /**
     * 服务类型搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchServiceTypeAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('service_type', $value);
        }
    }

    /**
     * 服务提供商搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchProviderNameAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('provider_name', $value);
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
     * 创建时间搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCreateAtAttr($query, $value, $data)
    {
        if (!empty($value)) {
            if (is_array($value)) {
                $query->whereBetweenTime('create_at', $value[0], $value[1]);
            }
        }
    }

    /**
     * 记录API调用日志
     * @param array $data
     * @return int
     */
    public static function log(array $data): int
    {
        $log = self::create([
            'site_id' => $data['site_id'] ?? 0,
            'service_type' => $data['service_type'] ?? '',
            'provider_name' => $data['provider_name'] ?? '',
            'method' => $data['method'] ?? '',
            'request_params' => $data['request_params'] ?? [],
            'response_data' => $data['response_data'] ?? [],
            'cost' => $data['cost'] ?? 0,
            'duration' => $data['duration'] ?? 0,
            'status' => $data['status'] ?? ThirdPartyDict::CALL_STATUS_SUCCESS,
            'error_msg' => $data['error_msg'] ?? '',
            'create_at' => time(),
        ]);

        return $log->id;
    }

    /**
     * 获取统计数据
     * @param int $siteId
     * @param string $serviceType
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getStats(int $siteId, string $serviceType = '', string $startDate = '', string $endDate = ''): array
    {
        $query = self::where('site_id', $siteId);

        if (!empty($serviceType)) {
            $query->where('service_type', $serviceType);
        }

        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetweenTime('create_at', strtotime($startDate), strtotime($endDate));
        }

        $totalCalls = $query->count();
        $successCalls = (clone $query)->where('status', ThirdPartyDict::CALL_STATUS_SUCCESS)->count();
        $failedCalls = $totalCalls - $successCalls;
        $totalCost = $query->sum('cost');
        $avgDuration = $query->avg('duration');

        return [
            'total_calls' => $totalCalls,
            'success_calls' => $successCalls,
            'failed_calls' => $failedCalls,
            'success_rate' => $totalCalls > 0 ? round($successCalls / $totalCalls * 100, 2) : 0,
            'total_cost' => round($totalCost, 2),
            'avg_duration' => round($avgDuration, 0),
        ];
    }
}
