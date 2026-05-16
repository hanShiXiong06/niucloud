<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin;

use addon\recycle\app\model\DeviceQueryConfig;
use addon\recycle\app\model\DeviceQueryApi;
use addon\recycle\app\model\DeviceQueryResult;
use addon\recycle\app\service\core\device_query\CoreDeviceQueryService;
use addon\recycle\app\service\core\third_party\RecycleThirdPartyConfigService;
use core\base\BaseAdminService;
use think\facade\Cache;

use core\exception\CommonException;

/**
 * 设备查询服务类
 * Class DeviceQueryService
 * @package addon\recycle\app\service\admin
 */
class DeviceQueryService extends BaseAdminService
{
    /**
     * 查询设备信息
     * @param string $queryCode 查询码(IMEI/序列号等)
     * @param int $siteId 站点ID
     * @return array
     * @throws CommonException
     */
    public function queryDevice(string $queryCode, $siteId, $api='/apple/model'): array
    {
        $queryCode = trim($queryCode);
        if (empty($queryCode)) {
            throw new CommonException('IMEI或序列号不能为空');
        }

        $api = trim((string)$api) ?: '/apple/model';
        $params = ['query_code' => $queryCode];
        if (str_starts_with($api, '/')) {
            $params['api_endpoint'] = $api;
        } else {
            $params['service_code'] = $api;
        }

        return (new CoreDeviceQueryService())->query((int)$siteId, $params);
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
     * 执行查询请求
     * @param string $queryCode
     * @param array $config
     * @param string $api
     * @return array
     * @throws CommonException
     */
    private function executeQuery(string $queryCode, array $config, string $api): array
    {
        $url = rtrim($config['base_url'], '/') . $api . '?sn=' . $queryCode;
        
        // 初始化CURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['key: ' . $config['api_key']]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout'] ?? 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || !empty($error)) {
            throw new CommonException('查询请求失败: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new CommonException('查询请求失败，HTTP状态码: ' . $httpCode);
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new CommonException('查询响应解析失败');
        }
    
        return $result;
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
            $result = (new CoreDeviceQueryService())->query((int)$this->site_id, [
                'query_code' => $queryCode,
                'query_type' => $queryType,
                'service_code' => $queryType === 'imei' ? 'apple_model' : 'apple_coverage',
            ]);
            
            if ($result['success']) {
                return [
                    'query_result' => $result['data'],
                    'cost_amount' => $result['cost'],
                    'from_cache' => (bool)($result['from_cache'] ?? false)
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

    // 获取设备的基本信息 coverage
    public function getCoverage(array $data){
//coverage-capacity?sn=354817664998779 / sn=NK4H7P4F12
        //使用switch 判断品牌
        switch($data['brand']){
            case '华为':
                return $this->queryDevice($data['imei'], $this->site_id, '/huawei/coverage')['data'];
            case 'HUAWEI':
                return $this->queryDevice($data['imei'], $this->site_id, '/huawei/coverage')['data'];
            case '小米':
                return $this->queryDevice($data['imei'], $this->site_id, '/xiaomi/coverage')['data'];
            case 'Xiaomi':
                return $this->queryDevice($data['imei'], $this->site_id, '/xiaomi/coverage')['data'];
            case 'OPPO':
                return $this->queryDevice($data['imei'], $this->site_id, '/oppo/coverage')['data'];
            case 'vivo':
                return $this->queryDevice($data['imei'], $this->site_id, '/vivo/coverage')['data'];
            case '三星':
                return $this->queryDevice($data['imei'], $this->site_id, '/samsung/coverage')['data'];
            case 'Samsung':
                return $this->queryDevice($data['imei'], $this->site_id, '/samsung/coverage')['data'];
            case 'realme':
                return $this->queryDevice($data['imei'], $this->site_id, '/realme/coverage')['data'];
            case '努比亚':
                return $this->queryDevice($data['imei'], $this->site_id, '/nubia/coverage')['data'];
            case 'Nubia':
                return $this->queryDevice($data['imei'], $this->site_id, '/nubia/coverage')['data'];
            case 'moto':
                return $this->queryDevice($data['imei'], $this->site_id, '/moto/coverage')['data'];
            case '摩托':
                return $this->queryDevice($data['imei'], $this->site_id, '/moto/coverage')['data'];
            case '中兴':
                return $this->queryDevice($data['imei'], $this->site_id, '/zte/coverage')['data'];
            case 'ZTE':
                return $this->queryDevice($data['imei'], $this->site_id, '/zte/coverage')['data'];
            default:
                return $this->queryDevice($data['imei'], $this->site_id, '/apple/coverage-capacity')['data'];
        }
        
    }
    // 获取设备的激活锁 activationlock
    public function getActivationlock(string $imei = ''){
        return $this->queryDevice($imei, $this->site_id , '/apple/activationlock')['data'];
    }

    // 获取设备的mdm 监管锁 mdm
    public function getMdm(string $imei = ''){
        return $this->queryDevice($imei, $this->site_id,'/apple/mdm')['data'];
    }

// 查询 快递的物流信息
  public function getExpress(string $express_code = '', string $mobile = ''){
        return $this->queryExpress($express_code, $mobile);
      }
    
      private function queryExpress(string $express_code = '' , string $mobile = ''){
        $thirdPartyConfig = (new RecycleThirdPartyConfigService())->getProviderConfig($this->site_id, 'express_query', 'ali_express');
        if (!empty($thirdPartyConfig)) {
          $host = $thirdPartyConfig['base_url'] ?? '';
          $path = $thirdPartyConfig['api_path'] ?? '';
          $appcode = $thirdPartyConfig['api_key'] ?? '';
        } else {
          // 通过site_id 获取 数据库存储 的 host 和 path，兼容旧配置
        $config = (new DeviceQueryConfig())->where([['site_id', '=', $this->site_id],['enabled_apis','=','/api-mall/api/express/query']])->findOrEmpty()->toArray();
      
          $host = $config['base_url'] ?? "";
        $path = $config['enabled_apis'] ?? "/api-mall/api/express/query";
          $appcode = $config['api_key'] ?? "";
        }

        if (empty($host) || empty($path) || empty($appcode)) {
          throw new CommonException('阿里快递查询配置不完整');
        }
       
        $method = "POST";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
        // 设置返回的格式
        array_push($headers, "Accept".":"."application/json");
        $bodys = "expressNo={$express_code}&mobile={$mobile}";
        $url = rtrim($host, '/') . '/' . ltrim($path, '/');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $bodys);
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
          throw new CommonException($err);
        } else {
          
          $res = json_decode($response, true);
         return $res;
          if ($response == '') return [];
          if (!$res == null && $res['code'] == 200 && isset($res['data']['logisticsTraceDetailList'])) {
            $res['data']['logisticsTraceDetailList'] = array_reverse($res['data']['logisticsTraceDetailList']);
            foreach ($res['data']['logisticsTraceDetailList'] as $k => $v) {
              $res['data']['logisticsTraceDetailList'][$k]['time'] = date('Y-m-d H:i:s', $v['time'] / 1000);
            }
            Cache::set($express_code, $res['data']['logisticsTraceDetailList'], 60 * 3);
            return $res['data']['logisticsTraceDetailList'];
          } else {
            return [];
          }
        }
      }
    } 
    
