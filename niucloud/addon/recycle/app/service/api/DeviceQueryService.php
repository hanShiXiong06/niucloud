<?php
declare(strict_types=1);

namespace addon\recycle\app\service\api;

use addon\recycle\app\model\DeviceQueryConfig;
use addon\recycle\app\model\DeviceQueryApi;
use addon\recycle\app\model\DeviceQueryResult;
use core\base\BaseApiService;
use think\facade\Cache;

use core\exception\CommonException;

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
        // 通过site_id 获取 数据库存储 的 host 和 path
        $config = (new DeviceQueryConfig())->where([['site_id', '=', $this->site_id],['enabled_apis','=','/api-mall/api/express/query']])->findOrEmpty()->toArray();
      
        $host = $config['base_url'] ?? "https://kzexpress.market.alicloudapi.com";
        $path = $config['enabled_apis'] ?? "/api-mall/api/express/query";
       
        $method = "POST";
        $appcode = $config['api_key'] ?? "";
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type".":"."application/x-www-form-urlencoded; charset=UTF-8");
        // 设置返回的格式
        array_push($headers, "Accept".":"."application/json");
        $querys = "";
        $bodys = "expressNo={$express_code}&mobile={$mobile}";
        $url = $host . $path;
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
          throw new Exception($err);
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
    