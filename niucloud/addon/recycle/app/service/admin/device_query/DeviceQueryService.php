<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\device_query;

use addon\recycle\app\model\third_party\DeviceQueryApi;
use addon\recycle\app\model\third_party\DeviceQueryConfig;
use addon\recycle\app\model\third_party\DeviceQueryResult;
use addon\recycle\app\service\admin\Exception;
use addon\recycle\app\service\core\third_party\CoreThirdPartyService;
use addon\recycle\app\dict\third_party\ThirdPartyDict;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Cache;

/**
 * 设备查询服务类
 * Class DeviceQueryService
 * @package addon\recycle\app\service\admin
 */
class DeviceQueryService extends BaseAdminService
{
    /**
     * 查询设备信息（使用新的第三方服务架构）
     * @param string $queryCode 查询码(IMEI/序列号等)
     * @param int $siteId 站点ID
     * @param string $api API端点
     * @return array
     * @throws CommonException
     */
    public function queryDevice(string $queryCode, $siteId, $api='/apple/model'): array
    {
        // 参数验证
        $queryCode = trim($queryCode);
        if (empty($queryCode)) {
            throw new CommonException('IMEI或序列号不能为空');
        }

        $api = trim((string)$api);
        if ($api === '') $api = '/apple/model';

        // 自动检索：只输入串号时，苹果优先；苹果查不到则轮询其它 /model 接口（配置驱动）
        $endpoints = ($api === '/apple/model')
            ? $this->getEnabledEndpointsByContains('/model', ['/apple/model'])
            : [ $api ];

        $service = new CoreThirdPartyService();
        $errors = [];

        foreach ($endpoints as $endpoint) {
            $endpoint = trim((string)$endpoint);
            if ($endpoint === '') continue;

            // 本地缓存
            $queryResult = $this->localQueryDevice($queryCode, $endpoint);
            if (!empty($queryResult)) {
                return [
                    'success' => true,
                    'data' => $queryResult,
                ];
            }

            $startTime = microtime(true);
            try {
                $result = $service->call(
                    ThirdPartyDict::SERVICE_TYPE_DEVICE_QUERY,
                    'queryByImei',
                    [
                        'imei' => $queryCode,
                        'api' => $endpoint
                    ],
                    (int)$siteId
                );

                $responseTime = round((microtime(true) - $startTime) * 1000);

                if (!$result['success']) {
                    $errors[] = $result['message'] ?? '查询失败';
                    continue;
                }

                $apiResult = $result['data'];

                // 命中判断：3023 返回 success 但 data 为空时，认为“未查到”，继续尝试下一个接口
                $rawData = isset($apiResult['data']) && is_array($apiResult['data']) ? $apiResult['data'] : [];
                if (empty($rawData)) {
                    $errors[] = "{$endpoint}: 未查询到数据";
                    continue;
                }

                $parsedResult = $this->parseNewArchitectureResult($apiResult);

                // 保存查询结果到数据库
                if ($parsedResult['success']) {
                    try {
                        DeviceQueryResult::saveQueryResult([
                            'site_id' => (int)$siteId,
                            'query_code' => $queryCode,
                            'api_endpoint' => $endpoint,
                            'query_result' => $parsedResult['data'],
                            'raw_response' => $apiResult,
                            'status' => 1,
                            'cost_amount' => $parsedResult['cost'],
                            'balance' => $parsedResult['balance'],
                            'remark' => '设备查询（新架构）'
                        ]);
                    } catch (\Exception $e) {
                        \think\facade\Log::warning('保存查询结果失败: ' . $e->getMessage());
                    }
                }

                return [
                    'success' => $parsedResult['success'],
                    'data' => $parsedResult['data'],
                    'cost' => $parsedResult['cost'],
                    'api_name' => $endpoint,
                    'response_time' => $responseTime,
                    'balance' => $parsedResult['balance'],
                    'provider' => $result['provider'] ?? 'unknown'
                ];

            } catch (\Exception $e) {
                // 自动检索模式下：失败继续尝试下一个；单接口模式保持原行为抛错
                if (count($endpoints) <= 1) {
                    throw new CommonException('设备查询失败: ' . $e->getMessage());
                }
                $errors[] = "{$endpoint}: " . $e->getMessage();
                continue;
            }
        }

        $msg = !empty($errors) ? implode('；', array_slice($errors, 0, 3)) : '查询失败';
        throw new CommonException('设备查询失败: ' . $msg);
    }

    /**
     * 从配置表 device_query_api 获取启用的 endpoint 列表（包含某个片段），并按 preferred 顺序置顶
     */
    private function getEnabledEndpointsByContains(string $contains, array $preferred = []): array
    {
        $contains = trim($contains);

        $rows = (new DeviceQueryApi())
            ->where('status', 1)
            ->field('api_list')
            ->order('id', 'asc')
            ->select()
            ->toArray();

        $endpoints = [];
        foreach ($preferred as $p) {
            $p = trim((string)$p);
            if ($p !== '') $endpoints[] = $p;
        }

        foreach ($rows as $row) {
            $endpoint = trim((string)($row['api_list'] ?? ''));
            if ($endpoint === '') continue;
            if ($contains !== '' && !str_contains($endpoint, $contains)) continue;
            $endpoints[] = $endpoint;
        }

        // 去重并保持顺序
        $seen = [];
        $uniq = [];
        foreach ($endpoints as $ep) {
            if (isset($seen[$ep])) continue;
            $seen[$ep] = true;
            $uniq[] = $ep;
        }
        return $uniq;
    }

    /**
     * 解析新架构返回的结果
     * @param array $result
     * @return array
     */
    private function parseNewArchitectureResult(array $result): array
    {
        $success = isset($result['success']) && $result['success'];
        $data = [];
        $cost = 0;
        $balance = 0;

        if ($success) {
            $rawData = $result['data'] ?? [];
            $cost = $result['cost'] ?? 0;
            $balance = $result['balance'] ?? 0;

            // 处理和规范化数据
            $data = $this->normalizeDeviceData($rawData);

            // 添加查询相关信息
            $data['source'] = $rawData['source'] ?? '';
            $data['balance'] = $balance;
            $data['maintenance'] = $rawData['maintenance'] ?? false;
        }

        return [
            'success' => $success,
            'data' => $data,
            'cost' => $cost,
            'balance' => $balance
        ];
    }

    // 本地查询
    private function localQueryDevice(string $queryCode, string $api)
    {
        $deviceQueryResult = (new DeviceQueryResult())->where( [ ['query_code', '=', $queryCode] , ['api_endpoint', '=', $api] ] ) -> field('query_result')->findOrEmpty()->toArray();
       
        if( empty($deviceQueryResult['query_result']) ){
            return null;
        }

        return   $deviceQueryResult['query_result'];
    }

    /**
     * 解析查询结果
     * @param array $result
     * @return array
     */
    private function parseQueryResult(array $result): array
    {
        $success = isset($result['code']) && $result['code'] === 0;
        $data = [];
        $cost = 0;
        $errorCode = '';
        $errorMessage = '';

        if ($success) {
            $rawData = $result['data'] ?? [];
            $cost = $result['cost'] ?? 0;
            
            // 处理和规范化数据
            $data = $this->normalizeDeviceData($rawData);
            
            // 添加查询相关信息
            $data['source'] = $result['source'] ?? '';
            $data['balance'] = $result['balance'] ?? 0;
            $data['maintenance'] = $result['data']['maintenance'] ?? false;
        } else {
            $errorCode = (string)($result['code'] ?? '');
            $errorMessage = $result['message'] ?? '查询失败';
        }
        
        return [
            'success' => $success,
            'data' => $data,
            'cost' => $cost,
            'error_code' => $errorCode,
            'error_message' => $errorMessage
        ];
    }

    /**
     * 规范化设备数据
     * @param array $rawData
     * @return array
     */
    private function normalizeDeviceData(array $rawData): array
    {
        $data = [];
        
        // 基础设备信息
        $data['sn'] = $rawData['sn'] ?? '';
        $data['model'] = $rawData['model'] ?? '';
        $data['capacity'] = $rawData['capacity'] ?? '';
        $data['color'] = $rawData['color'] ?? '';
        $data['modelnumber'] = $rawData['modelnumber'] ?? '';
        $data['identifier'] = $rawData['identifier'] ?? '';
        $data['activated'] = $rawData['activated'] ?? false;
        $data['image'] = $rawData['image'] ?? '';
        $data['fmi'] = $rawData['fmi'] ?? '';
        $data['locked'] = $rawData['locked'] ?? '';

        // 购买信息
        if (isset($rawData['purchase'])) {
            $data['purchase'] = [
                'date' => $rawData['purchase']['date'] ?? '',
                'validated' => $rawData['purchase']['validated'] ?? false
            ];
        }

        // 激活信息
        if (isset($rawData['activation'])) {
            $data['activation'] = [
                'date' => $rawData['activation']['date'] ?? ''
            ];
        }

        // 保修信息 - 特殊处理coverage.date
        if (isset($rawData['coverage'])) {
            $coverageData = $rawData['coverage'];
            $data['coverage'] = [
                'status' => $coverageData['status'] ?? '',
                'description' => $coverageData['description'] ?? '',
                'date' => $this->formatCoverageDate($coverageData['date'] ?? ''),
                'days-remaining' => $coverageData['days-remaining'] ?? 0
            ];
        }

        // 其他信息
        $data['support'] = $rawData['support'] ?? '';
        $data['applecare'] = $rawData['applecare'] ?? false;
        $data['applecare-eligible'] = $rawData['applecare-eligible'] ?? '';
        $data['brightstar'] = $rawData['brightstar'] ?? '';
        $data['pre-activated'] = $rawData['pre-activated'] ?? false;
        $data['registered'] = $rawData['registered'] ?? '';
        $data['loaner'] = $rawData['loaner'] ?? '';
        $data['replaced'] = $rawData['replaced'] ?? false;

        // 制造信息
        if (isset($rawData['manufacture'])) {
            $data['manufacture'] = [
                'date' => $rawData['manufacture']['date'] ?? ''
            ];
        }
        $data['manufacturer'] = $rawData['manufacturer'] ?? '';

        return $data;
    }

    /**
     * 格式化保修日期
     * @param string $date
     * @return string
     */
    private function formatCoverageDate(string $date): string
    {
        // 如果是特殊状态，直接返回
        if (in_array($date, ['Expired', 'Active', ''])) {
            return $date;
        }

        // 尝试格式化日期
        try {
            $timestamp = strtotime($date);
            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        } catch (\Exception $e) {
            // 日期格式化失败，返回原值
        }

        return $date;
    }

    /**
     * 简化的设备信息查询（主要用于回收订单）
     * @param string $queryCode 查询码
     * @param string $queryType 查询类型 (imei, serial, model等)
     * @return array|null
     */
    public function queryDeviceInfo(string $queryCode, string $queryType = 'imei'): ?array
    {
        $queryCode = trim($queryCode);
        if (empty($queryCode)) {
            return null;
        }

        try {
            // 获取站点配置
            $config = DeviceQueryConfig::getSiteConfig($this->site_id);
            if (!$config) {
                return null;
            }

            // 执行查询
            $result = $this->queryDevice($queryCode, $this->site_id);
            
            if ($result['success']) {
                return [
                    'query_result' => $result['data'],
                    'cost_amount' => $result['cost'],
                    'from_cache' => false
                ];
            }

            return null;
        } catch (\Exception $e) {
            trace('设备查询失败: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * 批量查询设备信息
     * @param array $queryCodes 查询码数组
     * @param int $siteId 站点ID
     * @return array
     */
    public function batchQuery(array $queryCodes, int $siteId = 0): array
    {
        $results = [];
        $errors = [];

        foreach ($queryCodes as $queryCode) {
            try {
                $result = $this->queryDevice($queryCode, $siteId);
                $results[$queryCode] = $result;
            } catch (\Exception $e) {
                $errors[$queryCode] = $e->getMessage();
            }
        }

        return [
            // 'success_count' => count($results),
            // 'error_count' => count($errors),
            // 'results' => $results,
            // 'errors' => $errors
        ];
    }

    /**
     * 获取可用的API接口列表
     * @return array
     */
    public function getAvailableApis(): array
    {
        return DeviceQueryApi::getApiList();
    }

    /**
     * 获取查询统计信息
     * @param int $siteId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getQueryStats(int $siteId, string $startDate = '', string $endDate = ''): array
    {
        return DeviceQueryResult::getQueryStats($siteId, $startDate, $endDate);
    }

    /**
     * 检测查询类型
     * @param string $queryCode
     * @return string
     */
    private function detectQueryType(string $queryCode): string
    {
        $queryCode = trim($queryCode);
        
        // IMEI通常是15位数字
        if (preg_match('/^\d{15}$/', $queryCode)) {
            return 'imei';
        }
        
        // 苹果序列号通常是10-12位字母数字组合
        if (preg_match('/^[A-Z0-9]{10,12}$/', $queryCode)) {
            return 'serial';
        }
        
        // 其他情况
        if (strlen($queryCode) >= 8 && strlen($queryCode) <= 20) {
            if (ctype_alnum($queryCode)) {
                return 'serial';
            }
        }
        
        return 'other';
    }

    /**
     * 获取API名称
     * @param string $endpoint
     * @return string
     */
    private function getApiName(string $endpoint): string
    {
        $apiList = DeviceQueryApi::getApiList();
        
        // 遍历所有分类查找对应的API名称
        foreach ($apiList as $category => $apis) {
            foreach ($apis as $apiKey => $apiInfo) {
                if ($apiInfo['endpoint'] === $endpoint) {
                    return $apiInfo['name'] ?? $endpoint;
                }
            }
        }
        
        return $endpoint;
    }

    /**
     * 获取操作人姓名
     * @return string
     */
    private function getOperatorName(): string
    {
        if (empty($this->uid)) {
            return '系统';
        }
        
        try {
            $user = \app\model\sys\SysUser::where('uid', $this->uid)
                ->field('username,real_name')
                ->findOrEmpty()
                ->toArray();
                
            return $user['real_name'] ?: $user['username'] ?: '未知用户';
        } catch (\Exception $e) {
            return '未知用户';
        }
    }

    // 获取设备的基本信息 coverage（有品牌时精准定位端点，无品牌时兜底轮询）
    public function getCoverage(array $data)
    {
        $imei  = trim((string)($data['imei'] ?? ''));
        $brand = strtolower(trim((string)($data['brand'] ?? '')));
        if ($imei === '') return [];

        // 品牌 → coverage 端点映射（按优先级排列，苹果优先尝试带容量的接口）
        $brandEndpointMap = [
            'apple'   => ['/apple/coverage-capacity', '/apple/coverage'],
            'huawei'  => ['/huawei/coverage'],
            'honor'   => ['/honor/coverage'],
            'xiaomi'  => ['/xiaomi/coverage'],
            'oppo'    => ['/oppo/coverage'],
            'vivo'    => ['/vivo/coverage'],
            'samsung' => ['/samsung/coverage'],
            'realme'  => ['/realme/coverage'],
            'nubia'   => ['/nubia/coverage'],
            'moto'    => ['/moto/coverage'],
            'zte'     => ['/zte/coverage'],
        ];

        // 品牌已知：优先查对应品牌端点，全部失败后回退到兜底轮询
        if ($brand !== '' && isset($brandEndpointMap[$brand])) {
            foreach ($brandEndpointMap[$brand] as $endpoint) {
                try {
                    $res = $this->queryDevice($imei, $this->site_id, $endpoint);
                    if (!empty($res['data'])) return $res['data'];
                } catch (\Exception $e) {
                    continue;
                }
            }
            // 品牌端点全部失败，回退兜底轮询（端点未启用或查无结果）
        }

        // 兜底轮询：品牌端点失败或品牌未知时使用
        // 若品牌已知，将其对应端点置顶，提升命中概率
        $preferred = isset($brandEndpointMap[$brand])
            ? $brandEndpointMap[$brand]
            : ['/apple/coverage-capacity', '/apple/coverage'];
        $endpoints = $this->getEnabledEndpointsByContains('/coverage', $preferred);
        foreach ($endpoints as $endpoint) {
            try {
                $res = $this->queryDevice($imei, $this->site_id, $endpoint);
                if (!empty($res['data'])) {
                    return $res['data'];
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        throw new CommonException('查询失败');
    }
    // 获取设备的激活锁 activationlock
    public function getActivationlock(string $imei = ''){
        return $this->queryDevice($imei, $this->site_id , '/apple/activationlock')['data'];
    }

    // 获取设备的mdm 监管锁 mdm
    public function getMdm(string $imei = ''){
        return $this->queryDevice($imei, $this->site_id,'/apple/mdm')['data'];
    }

// 查询 快递的物流信息（使用新架构）
  public function getExpress(string $express_code = '', string $mobile = ''){
        return $this->queryExpress($express_code, $mobile);
      }

      private function queryExpress(string $express_code = '' , string $mobile = ''){
        // 使用新的第三方服务架构
        $service = new CoreThirdPartyService();

        try {
            $result = $service->call(
                ThirdPartyDict::SERVICE_TYPE_EXPRESS_QUERY,
                'query',
                [
                    'express_no' => $express_code,
                    'mobile' => $mobile
                ],
                $this->site_id
            );

            if (!$result['success']) {
                return [];
            }

            // 返回查询结果
            return $result['data']['data'] ?? [];

        } catch (\Exception $e) {
            \think\facade\Log::error('快递查询失败: ' . $e->getMessage());
            return [];
        }
      }
    }
    
