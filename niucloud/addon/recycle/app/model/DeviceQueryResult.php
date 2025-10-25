<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 设备查询结果模型
 * Class DeviceQueryResult
 * @package addon\recycle\app\model
 */
class DeviceQueryResult extends BaseModel
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
    protected $name = 'device_query_result';

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
     * JSON字段自动转换
     * @var array
     */
    protected $json = ['query_result', 'raw_response'];

    /**
     * JSON字段设置为数组
     * @var array
     */
    protected $jsonAssoc = true;

    /**
     * 追加属性
     * @var array
     */
    protected $append = [
        'status_name',
        'query_type_name'
    ];

    /**
     * 获取状态名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getStatusNameAttr($value, $data)
    {
        $status = [
            0 => '查询失败',
            1 => '查询成功',
            2 => '查询中',
            -1 => '查询错误'
        ];
        return $status[$data['status'] ?? 0] ?? '未知';
    }

    /**
     * 获取查询类型名称
     * @param $value
     * @param $data
     * @return string
     */
    public function getQueryTypeNameAttr($value, $data)
    {
        $types = [
            'imei' => 'IMEI查询',
            'serial' => '序列号查询',
            'model' => '型号查询',
            'coverage' => '保修查询',
            'activationlock' => '激活锁查询',
            'other' => '其他查询'
        ];
        return $types[$data['query_type'] ?? 'other'] ?? '其他查询';
    }

    /**
     * 搜索器 本表 site_id 精确匹配
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
     * 搜索器 本表 query_code 模糊匹配
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchQueryCodeAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('query_code', 'like', "%{$value}%");
        }
    }

    /**
     * 搜索器 本表 api_endpoint 精确匹配
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchApiEndpointAttr($query, $value, $data)
    {
        if (!empty($value)) {
            $query->where('api_endpoint', $value);
        }
    }

    /**
     * 搜索器 本表 status 精确匹配
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
     * 搜索器 本表 query_type 精确匹配
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchQueryTypeAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('query_type', $value);
        }
    }

    /**
     * 搜索器 本表 create_at 日期范围查询
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCreateAtAttr($query, $value, $data)
    {
        if (is_array($value)) {
            if (!empty($value[0]) && !empty($value[1])) {
                $query->whereBetweenTime('create_at', $value[0], $value[1]);
            }
        }
    }

    /**
     * 根据查询码和端点获取缓存结果
     * @param string $queryCode
     * @param string $apiEndpoint
     * @param int $cacheTime 缓存时间（秒，默认1小时）
     * @return array|null
     */
    public static function getCacheResult(string $queryCode, string $apiEndpoint, int $cacheTime = 3600)
    {
        $result = self::where('query_code', $queryCode)
            ->where('api_endpoint', $apiEndpoint)
            ->where('status', 1)
            ->where('create_at', '>', time() - $cacheTime)
            ->order('create_at', 'desc')
            ->find();

        return $result ? $result->toArray() : null;
    }

    /**
     * 保存查询结果
     * @param array $data
     * @return bool
     */
    public static function saveQueryResult(array $data): bool
    {
        $requiredFields = ['site_id', 'query_code', 'api_endpoint', 'query_result'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                return false;
            }
        }

        $defaultData = [
            'query_type' => 'other',
            'api_name' => '',
            'status' => 1,
            'cost_amount' => 0.00,
            'response_time' => 0,
            'error_code' => '',
            'error_message' => '',
            'raw_response' => [],
            'operator_id' => 0,
            'operator_name' => '',
            'remark' => ''
        ];

        $data = array_merge($defaultData, $data);
        
        // 如果有balance信息，添加到备注中
        if (isset($data['balance']) && $data['balance'] > 0) {
            $balanceInfo = "余额: {$data['balance']}元";
            $data['remark'] = empty($data['remark']) ? $balanceInfo : $data['remark'] . ' | ' . $balanceInfo;
        }
        
        // 移除不属于数据库字段的数据
        unset($data['balance']);
        
        try {
            return (bool) self::create($data);
        } catch (\Exception $e) {
            \think\facade\Log::error('保存设备查询结果失败', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * 获取查询统计
     * @param int $siteId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public static function getQueryStats(int $siteId, string $startDate = '', string $endDate = ''): array
    {
        $query = self::where('site_id', $siteId);
        
        if ($startDate && $endDate) {
            $query->whereBetweenTime('create_at', $startDate, $endDate);
        }

        $stats = [
            'total_queries' => $query->count(),
            'success_queries' => $query->where('status', 1)->count(),
            'failed_queries' => $query->where('status', 0)->count(),
            'total_cost' => $query->sum('cost_amount'),
            'avg_response_time' => $query->avg('response_time')
        ];

        return $stats;
    }

    /**
     * 清理过期缓存
     * @param int $expireTime 过期时间（秒，默认7天）
     * @return int 清理的记录数
     */
    public static function cleanExpiredCache(int $expireTime = 604800): int
    {
        $expireTimestamp = time() - $expireTime;
        return self::where('create_at', '<', $expireTimestamp)->delete();
    }
} 