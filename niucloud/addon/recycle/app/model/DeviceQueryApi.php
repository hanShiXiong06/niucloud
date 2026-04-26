<?php
declare(strict_types=1);

namespace addon\recycle\app\model;

use core\base\BaseModel;

/**
 * 设备查询接口清单模型
 * Class DeviceQueryApi
 * @package addon\recycle\app\model
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
        return [
            'apple' => [
                'coverage' => ['name' => '苹果保修查询', 'endpoint' => '/apple/coverage', 'price' => '0.2-0.8'],
                'coverage_capacity' => ['name' => '苹果保修查询(容量/颜色)', 'endpoint' => '/apple/coverage-capacity', 'price' => '1'],
                'coverage_backup' => ['name' => '苹果保修查询(备用)', 'endpoint' => '/apple/coverage-backup', 'price' => '1.2'],
                'activationlock' => ['name' => '激活锁查询', 'endpoint' => '/apple/activationlock', 'price' => '0.4'],
                'icloud' => ['name' => 'ID黑白查询', 'endpoint' => '/apple/icloud', 'price' => '0.8'],
                'serial' => ['name' => '序列号转换', 'endpoint' => '/apple/serial', 'price' => '1'],
                'repair' => ['name' => '维修状态查询', 'endpoint' => '/apple/repair', 'price' => '0.2'],
                'simlock' => ['name' => '网络锁查询', 'endpoint' => '/apple/simlock', 'price' => '1'],
                'carrier' => ['name' => '运营商查询', 'endpoint' => '/apple/carrier', 'price' => '1.2'],
                'country' => ['name' => '销售地查询', 'endpoint' => '/apple/country', 'price' => '1.2'],
                'partnumber' => ['name' => '型号号码查询', 'endpoint' => '/apple/partnumber', 'price' => '1.6'],
                'purchase' => ['name' => '购买日期查询', 'endpoint' => '/apple/purchase', 'price' => '1.2'],
                'mdm' => ['name' => '监管锁查询', 'endpoint' => '/apple/mdm', 'price' => '10'],
                'mac_activationlock' => ['name' => 'Mac激活锁查询', 'endpoint' => '/apple/mac-activationlock', 'price' => '2'],
                'details' => ['name' => '苹果验机报告(网络锁)', 'endpoint' => '/apple/details', 'price' => '2.5'],
                'details_purchase' => ['name' => '苹果验机报告(购买日期)', 'endpoint' => '/apple/details-purchase', 'price' => '3'],
                'details_ultimate' => ['name' => '苹果验机报告(旗舰版)', 'endpoint' => '/apple/details-ultimate', 'price' => '3.5'],
                'model' => ['name' => '苹果型号查询', 'endpoint' => '/apple/model', 'price' => '0.05']
            ],
            'android' => [
                'huawei_coverage' => ['name' => '华为保修查询', 'endpoint' => '/huawei/coverage', 'price' => '0.4'],
                'honor_coverage' => ['name' => '荣耀保修查询', 'endpoint' => '/honor/coverage', 'price' => '0.4'],
                'xiaomi_coverage' => ['name' => '小米保修查询', 'endpoint' => '/xiaomi/coverage', 'price' => '0.8'],
                'oppo_coverage' => ['name' => 'OPPO保修查询', 'endpoint' => '/oppo/coverage', 'price' => '0.8'],
                'vivo_coverage' => ['name' => 'vivo保修查询', 'endpoint' => '/vivo/coverage', 'price' => '1'],
                'samsung_coverage' => ['name' => '三星保修查询', 'endpoint' => '/samsung/coverage', 'price' => '1'],
                'realme_coverage' => ['name' => 'realme保修查询', 'endpoint' => '/realme/coverage', 'price' => '0.8'],
                'nubia_coverage' => ['name' => '努比亚保修查询', 'endpoint' => '/nubia/coverage', 'price' => '1'],
                'motorola_coverage' => ['name' => 'moto保修查询', 'endpoint' => '/motorola/coverage', 'price' => '1'],
                'zte_coverage' => ['name' => '中兴保修查询', 'endpoint' => '/zte/coverage', 'price' => '0.6'],
                'xiaomi_activationlock' => ['name' => '小米账号锁查询', 'endpoint' => '/xiaomi/activationlock', 'price' => '0.02']
            ],
            'imei' => [
                'model' => ['name' => 'IMEI查询(型号)', 'endpoint' => '/imei/model', 'price' => '0.2'],
                'manufacture' => ['name' => 'IMEI查询(生产日期)', 'endpoint' => '/imei/manufacture', 'price' => '0.6'],
                'blacklist' => ['name' => 'IMEI查询(黑名单)', 'endpoint' => '/imei/blacklist', 'price' => '0.4'],
                'att' => ['name' => 'AT&T状态查询', 'endpoint' => '/imei/att', 'price' => '0.8'],
                't_mobile' => ['name' => 'T-Mobile状态查询', 'endpoint' => '/imei/t-mobile', 'price' => '0.8'],
                'verizon' => ['name' => 'Verizon状态查询', 'endpoint' => '/imei/verizon', 'price' => '0.6']
            ],
            'other' => [
                'barcode' => ['name' => '条码查询', 'endpoint' => '/item/barcode', 'price' => '0.02'],
                'ip_location' => ['name' => 'IP地址查询', 'endpoint' => '/ip/location', 'price' => '0.001'],
                'phone_location' => ['name' => '号码归属地查询', 'endpoint' => '/phone/location', 'price' => '0.001']
            ]
        ];
    }

    /**
     * 获取API清单
     * @return array|null
     */
    public static function getApiList()
    {
        $apiData = self::where('status', 1)->find();
        if ($apiData && !empty($apiData->api_list)) {
            return $apiData->api_list;
        }
        
        // 如果数据库中没有数据，返回默认清单
        return self::getDefaultApiList();
    }

    /**
     * 根据端点获取API信息
     * @param string $endpoint
     * @return array|null
     */
    public static function getApiByEndpoint(string $endpoint)
    {
        $apiList = self::getApiList();
        
        foreach ($apiList as $category => $apis) {
            foreach ($apis as $key => $api) {
                if ($api['endpoint'] === $endpoint) {
                    return array_merge($api, ['category' => $category, 'key' => $key]);
                }
            }
        }
        
        return null;
    }

    /**
     * 初始化默认API清单
     * @return bool
     */
    public static function initDefaultApiList(): bool
    {
        $existing = self::find();
        if (!$existing) {
            $data = [
                'name' => '默认API接口清单',
                'api_list' => self::getDefaultApiList(),
                'status' => 1,
                'remark' => '系统默认初始化的API接口清单，更新日期：2024-09-20'
            ];
            
            return (bool) self::create($data);
        }
        
        return true;
    }
} 