<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\third_party;

use addon\hsx_recycle\app\dict\third_party\ThirdPartyDict;
use core\base\BaseModel;

/**
 * 第三方服务配置模型
 * Class ThirdPartyService
 * @package addon\hsx_recycle\app\model\third_party
 */
class ThirdPartyService extends BaseModel
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
    protected $name = 'third_party_service';

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
     * JSON字段
     * @var array
     */
    protected $json = ['config'];

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
        return ThirdPartyDict::getStatusName($data['status'] ?? 0);
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
     * 获取站点的服务配置
     * @param int $siteId 站点ID
     * @param string $serviceType 服务类型
     * @param string $providerName 服务提供商（可选）
     * @return array|null
     */
    public static function getSiteConfig(int $siteId, string $serviceType, string $providerName = '')
    {
        $query = self::where('site_id', $siteId)
            ->where('service_type', $serviceType)
            ->where('status', ThirdPartyDict::STATUS_ENABLED)
            ->order('priority', 'asc');

        if (!empty($providerName)) {
            $query->where('provider_name', $providerName);
        }

        return $query->find();
    }

    /**
     * 获取站点的所有可用服务提供商（按优先级排序）
     * @param int $siteId
     * @param string $serviceType
     * @return array
     */
    public static function getAvailableProviders(int $siteId, string $serviceType): array
    {
        return self::where('site_id', $siteId)
            ->where('service_type', $serviceType)
            ->where('status', ThirdPartyDict::STATUS_ENABLED)
            ->order('priority', 'asc')
            ->select()
            ->toArray();
    }

    /**
     * 更新余额
     * @param int $id
     * @param float $balance
     * @return bool
     */
    public static function updateBalance(int $id, float $balance): bool
    {
        return self::where('id', $id)->update(['balance' => $balance]) !== false;
    }

    /**
     * 检查余额是否低于告警阈值
     * @param int $id
     * @return bool
     */
    public static function isBalanceLow(int $id): bool
    {
        $service = self::find($id);
        if (empty($service)) {
            return false;
        }

        return $service['balance'] < $service['min_balance_alert'];
    }
}
