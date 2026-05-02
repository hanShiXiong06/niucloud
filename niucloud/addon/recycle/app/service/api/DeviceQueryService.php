<?php
declare(strict_types=1);

namespace addon\recycle\app\service\api;

use addon\recycle\app\model\third_party\DeviceQueryConfig;
use addon\recycle\app\service\core\third_party\RecycleThirdPartyConfigService;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Cache;

/**
 * 设备查询服务类
 * Class DeviceQueryService
 * @package addon\recycle\app\service\admin
 */
class DeviceQueryService extends BaseApiService
{


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
    
