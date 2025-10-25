<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 设备查询配置模型
 * Class DeviceQueryConfig
 * @package addon\recycle\app\model
 */
class DeviceQueryConfig extends BaseModel
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
    protected $name = 'device_query_config';

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
    protected $append = [
        'status_name'
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
            0 => '禁用',
            1 => '启用'
        ];
        return $status[$data['status'] ?? 0] ?? '禁用';
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
     * 搜索器 本表 name 模糊匹配
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchNameAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('name', 'like', '%' . $value . '%');
        }
    }

    /**
     * 搜索器 本表 create_at 时间范围匹配
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchCreateAtAttr($query, $value, $data)
    {
        if (!empty($value) && is_array($value) && count($value) == 2) {
            $query->whereBetweenTime('create_at', $value[0], $value[1]);
        }
    }

    /**
     * 获取站点配置
     * @param int $siteId
     * @return array|null
     */
    public static function getSiteConfig(int $siteId)
    {
        $config = self::where('site_id', $siteId)
            ->where('status', 1)
            ->find();
        
        return $config ? $config->toArray() : null;
    }

    /**
     * 验证API密钥格式
     * @param string $apiKey
     * @return bool
     */
    public static function validateApiKey(string $apiKey): bool
    {
        // 简单验证API密钥格式
        return !empty($apiKey) && strlen($apiKey) >= 16;
    }
} 