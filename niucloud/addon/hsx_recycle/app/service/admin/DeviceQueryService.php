<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin;

use addon\hsx_recycle\app\model\DeviceQueryApi;
use addon\hsx_recycle\app\model\DeviceQueryResult;
use addon\hsx_recycle\app\service\core\device_query\CoreDeviceQueryService;
use addon\hsx_recycle\app\service\core\express_query\ExpressQueryGatewayService;
use core\base\BaseAdminService;

use core\exception\CommonException;

/**
 * 设备查询服务类
 * Class DeviceQueryService
 * @package addon\hsx_recycle\app\service\admin
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
        return (new ExpressQueryGatewayService())->query($this->site_id, $express_code, $mobile);
      }
    } 
    
