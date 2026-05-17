<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\model\third_party;

use core\base\BaseModel;

/**
 * 设备查询接口清单模型
 * Class DeviceQueryApi
 * @package addon\hsx_recycle\app\model
 */
class DeviceQueryApi extends BaseModel
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
    protected $name = 'device_query_api';

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
    // protected $json = ['api_list'];

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
        return $status[$data['status'] ?? 1] ?? '启用';
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
     * 搜索器 本表 version 模糊匹配
     * @param $query
     * @param $value
     * @param $data
     */
    public function searchVersionAttr($query, $value, $data)
    {
        if ($value !== '' && $value !== null) {
            $query->where('version', 'like', '%' . $value . '%');
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
     * 获取默认API清单
     * @return array
     */
    public static function getDefaultApiList(): array
    {
        return self::groupApiRows(self::getDefaultApiRows());
    }

    /**
     * 获取默认API行数据（用于初始化表数据）
     * @return array
     */
    public static function getDefaultApiRows(): array
    {
        $v = '1.0.0';
        return [
            // Apple
            [ 'name' => '苹果保修查询', 'version' => $v, 'api_list' => '/apple/coverage', 'remark' => '0.2-0.8元', 'status' => 1 ],
            [ 'name' => '苹果保修查询（容量/颜色）', 'version' => $v, 'api_list' => '/apple/coverage-capacity', 'remark' => '1元', 'status' => 1 ],
            [ 'name' => '苹果保修查询（备用）', 'version' => $v, 'api_list' => '/apple/coverage-backup', 'remark' => '1.2元', 'status' => 1 ],
            [ 'name' => '激活锁查询', 'version' => $v, 'api_list' => '/apple/activationlock', 'remark' => '0.4元', 'status' => 1 ],
            [ 'name' => 'ID黑白查询', 'version' => $v, 'api_list' => '/apple/icloud', 'remark' => '0.8元', 'status' => 1 ],
            [ 'name' => '序列号转换', 'version' => $v, 'api_list' => '/apple/serial', 'remark' => '1元', 'status' => 1 ],
            [ 'name' => '维修状态查询', 'version' => $v, 'api_list' => '/apple/repair', 'remark' => '0.2元', 'status' => 1 ],
            [ 'name' => '网络锁查询', 'version' => $v, 'api_list' => '/apple/simlock', 'remark' => '1元', 'status' => 1 ],
            [ 'name' => '运营商查询', 'version' => $v, 'api_list' => '/apple/carrier', 'remark' => '1.2元', 'status' => 1 ],
            [ 'name' => '销售地查询', 'version' => $v, 'api_list' => '/apple/country', 'remark' => '1.2元', 'status' => 1 ],
            [ 'name' => '型号号码查询', 'version' => $v, 'api_list' => '/apple/partnumber', 'remark' => '1.6元', 'status' => 1 ],
            [ 'name' => '购买日期查询', 'version' => $v, 'api_list' => '/apple/purchase', 'remark' => '1.2元', 'status' => 1 ],
            [ 'name' => '监管锁查询', 'version' => $v, 'api_list' => '/apple/mdm', 'remark' => '10元', 'status' => 1 ],
            [ 'name' => 'Mac激活锁查询', 'version' => $v, 'api_list' => '/apple/mac-activationlock', 'remark' => '2元', 'status' => 1 ],
            [ 'name' => '苹果验机报告（网络锁）', 'version' => $v, 'api_list' => '/apple/details', 'remark' => '2.5元', 'status' => 1 ],
            [ 'name' => '苹果验机报告（购买日期）', 'version' => $v, 'api_list' => '/apple/details-purchase', 'remark' => '3元', 'status' => 1 ],
            [ 'name' => '苹果验机报告（旗舰版）', 'version' => $v, 'api_list' => '/apple/details-ultimate', 'remark' => '3.5元', 'status' => 1 ],
            [ 'name' => '苹果型号查询', 'version' => $v, 'api_list' => '/apple/model', 'remark' => '0.05元', 'status' => 1 ],

            // Android coverage (from doc)
            [ 'name' => '华为保修查询', 'version' => $v, 'api_list' => '/huawei/coverage', 'remark' => '0.4元', 'status' => 1 ],
            [ 'name' => '荣耀保修查询', 'version' => $v, 'api_list' => '/honor/coverage', 'remark' => '0.4元', 'status' => 1 ],
            [ 'name' => '小米保修查询', 'version' => $v, 'api_list' => '/xiaomi/coverage', 'remark' => '1元', 'status' => 1 ],
            [ 'name' => 'OPPO保修查询', 'version' => $v, 'api_list' => '/oppo/coverage', 'remark' => '0.8元', 'status' => 1 ],
            [ 'name' => 'vivo保修查询', 'version' => $v, 'api_list' => '/vivo/coverage', 'remark' => '1元', 'status' => 1 ],
            [ 'name' => '三星保修查询', 'version' => $v, 'api_list' => '/samsung/coverage', 'remark' => '1元', 'status' => 1 ],
            [ 'name' => '真我保修查询', 'version' => $v, 'api_list' => '/realme/coverage', 'remark' => '0.8元', 'status' => 1 ],
            [ 'name' => '努比亚保修查询', 'version' => $v, 'api_list' => '/nubia/coverage', 'remark' => '1元', 'status' => 1 ],
            [ 'name' => 'moto保修查询', 'version' => $v, 'api_list' => '/motorola/coverage', 'remark' => '1元', 'status' => 1 ],
            [ 'name' => '中兴保修查询', 'version' => $v, 'api_list' => '/zte/coverage', 'remark' => '0.6元', 'status' => 1 ],

            // IMEI
            [ 'name' => 'IMEI查询(型号)', 'version' => $v, 'api_list' => '/imei/model', 'remark' => '0.2元', 'status' => 1 ],
            [ 'name' => 'IMEI查询(生产日期)', 'version' => $v, 'api_list' => '/imei/manufacture', 'remark' => '0.6元', 'status' => 1 ],
            [ 'name' => 'IMEI查询(黑名单)', 'version' => $v, 'api_list' => '/imei/blacklist', 'remark' => '0.4元', 'status' => 1 ],
            [ 'name' => 'AT&T状态查询', 'version' => $v, 'api_list' => '/imei/att', 'remark' => '0.8元', 'status' => 1 ],
            [ 'name' => 'T-Mobile状态查询', 'version' => $v, 'api_list' => '/imei/t-mobile', 'remark' => '0.8元', 'status' => 1 ],
            [ 'name' => 'Verizon状态查询', 'version' => $v, 'api_list' => '/imei/verizon', 'remark' => '0.6元', 'status' => 1 ],

            // Other
            [ 'name' => '条码查询', 'version' => $v, 'api_list' => '/item/barcode', 'remark' => '0.02元', 'status' => 1 ],
            [ 'name' => 'IP地址查询', 'version' => $v, 'api_list' => '/ip/location', 'remark' => '0.001元', 'status' => 1 ],
            [ 'name' => '号码归属地查询', 'version' => $v, 'api_list' => '/phone/location', 'remark' => '0.001元', 'status' => 1 ],
        ];
    }

    /**
     * 获取API清单（从数据库按行读取，并分组返回）
     * @return array
     */
    public static function getApiList(): array
    {
        $rows = self::where('status', 1)
            ->field('id,name,api_list,remark,version,status')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        if (!empty($rows)) {
            return self::groupApiRows($rows);
        }

        // 数据库没有数据时，返回默认清单
        return self::getDefaultApiList();
    }

    /**
     * 初始化默认API清单
     * @return bool
     */
    public static function initDefaultApiList(): bool
    {
        $existing = self::count();
        if ($existing > 0) return true;

        $rows = self::getDefaultApiRows();
        foreach ($rows as $row) {
            self::create($row);
        }
        return true;
    }

    /**
     * 根据端点获取API信息
     * @param string $endpoint
     * @return array|null
     */
    public static function getApiByEndpoint(string $endpoint)
    {
        $endpoint = trim($endpoint);
        if ($endpoint === '') return null;

        $apiList = self::getApiList();

        foreach ($apiList as $category => $apis) {
            foreach ($apis as $key => $api) {
                if (($api['endpoint'] ?? '') === $endpoint) {
                    return array_merge($api, ['category' => $category, 'key' => $key]);
                }
            }
        }

        return null;
    }

    /**
     * 将行数据按分类聚合，返回结构：
     * [ 'apple' => [key => ['name','endpoint','price']], 'android' => [...], 'imei' => [...], 'other' => [...] ]
     */
    private static function groupApiRows(array $rows): array
    {
        $grouped = [
            'apple' => [],
            'android' => [],
            'imei' => [],
            'other' => [],
        ];

        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $endpoint = (string)($row['api_list'] ?? $row['endpoint'] ?? '');
            if ($endpoint === '') continue;

            $category = self::inferCategory($endpoint);
            $key = ltrim($endpoint, '/');
            $key = str_replace('/', '_', $key);

            $grouped[$category][$key] = [
                'name' => (string)($row['name'] ?? ''),
                'endpoint' => $endpoint,
                // remark 字段存储“价格/说明”
                'price' => (string)($row['remark'] ?? $row['price'] ?? ''),
                'id' => $row['id'] ?? 0,
                'version' => $row['version'] ?? '',
                'status' => $row['status'] ?? 1,
            ];
        }

        return $grouped;
    }

    private static function inferCategory(string $endpoint): string
    {
        $endpoint = trim($endpoint);
        if (str_starts_with($endpoint, '/apple/')) return 'apple';
        if (str_starts_with($endpoint, '/imei/')) return 'imei';
        // 非 apple/imei 的 /coverage 归入 android（包含华为/荣耀/小米等）
        if (str_contains($endpoint, '/coverage')) return 'android';
        return 'other';
    }
}
