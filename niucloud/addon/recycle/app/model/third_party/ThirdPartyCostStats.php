<?php
declare(strict_types=1);

namespace addon\recycle\app\model\third_party;

use addon\recycle\app\dict\third_party\ThirdPartyDict;
use core\base\BaseModel;

/**
 * 第三方服务费用统计模型
 * Class ThirdPartyCostStats
 * @package addon\recycle\app\model\third_party
 */
class ThirdPartyCostStats extends BaseModel
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
    protected $name = 'third_party_cost_stats';

    /**
     * 自动写入时间戳
     * @var bool
     */
    protected $autoWriteTimestamp = false;

    /**
     * 追加属性
     * @var array
     */
    protected $append = ['service_type_name', 'provider_name_text', 'success_rate'];

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
     * 获取成功率
     * @param $value
     * @param $data
     * @return float
     */
    public function getSuccessRateAttr($value, $data)
    {
        $totalCalls = $data['total_calls'] ?? 0;
        $successCalls = $data['success_calls'] ?? 0;

        if ($totalCalls == 0) {
            return 0;
        }

        return round($successCalls / $totalCalls * 100, 2);
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
     * 日期搜索器
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchDateAttr($query, $value, $data)
    {
        if (!empty($value)) {
            if (is_array($value)) {
                $query->whereBetween('date', $value);
            } else {
                $query->where('date', $value);
            }
        }
    }

    /**
     * 更新或创建统计记录
     * @param int $siteId
     * @param string $serviceType
     * @param string $providerName
     * @param string $date
     * @param array $stats
     * @return bool
     */
    public static function updateOrCreate(int $siteId, string $serviceType, string $providerName, string $date, array $stats): bool
    {
        $record = self::where([
            ['site_id', '=', $siteId],
            ['service_type', '=', $serviceType],
            ['provider_name', '=', $providerName],
            ['date', '=', $date],
        ])->find();

        $data = [
            'total_calls' => $stats['total_calls'] ?? 0,
            'success_calls' => $stats['success_calls'] ?? 0,
            'failed_calls' => $stats['failed_calls'] ?? 0,
            'total_cost' => $stats['total_cost'] ?? 0,
            'avg_duration' => $stats['avg_duration'] ?? 0,
        ];

        if ($record) {
            // 更新
            return $record->save($data) !== false;
        } else {
            // 创建
            $data['site_id'] = $siteId;
            $data['service_type'] = $serviceType;
            $data['provider_name'] = $providerName;
            $data['date'] = $date;
            return self::create($data) !== false;
        }
    }

    /**
     * 获取日期范围内的统计汇总
     * @param int $siteId
     * @param string $serviceType
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getSummary(int $siteId, string $serviceType = '', string $startDate = '', string $endDate = ''): array
    {
        $query = self::where('site_id', $siteId);

        if (!empty($serviceType)) {
            $query->where('service_type', $serviceType);
        }

        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        $totalCalls = $query->sum('total_calls');
        $successCalls = $query->sum('success_calls');
        $failedCalls = $query->sum('failed_calls');
        $totalCost = $query->sum('total_cost');

        return [
            'total_calls' => $totalCalls,
            'success_calls' => $successCalls,
            'failed_calls' => $failedCalls,
            'success_rate' => $totalCalls > 0 ? round($successCalls / $totalCalls * 100, 2) : 0,
            'total_cost' => round($totalCost, 2),
        ];
    }

    /**
     * 获取按日期分组的统计数据
     * @param int $siteId
     * @param string $serviceType
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getByDate(int $siteId, string $serviceType = '', string $startDate = '', string $endDate = ''): array
    {
        $query = self::where('site_id', $siteId);

        if (!empty($serviceType)) {
            $query->where('service_type', $serviceType);
        }

        if (!empty($startDate) && !empty($endDate)) {
            $query->whereBetween('date', [$startDate, $endDate]);
        }

        return $query->order('date', 'desc')->select()->toArray();
    }
}
