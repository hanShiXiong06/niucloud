<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud-admin.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\dict\config\PlatformDict;
use app\model\dict\Dict;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\Exception;
use think\facade\Cache;
use think\facade\Log;

/**
 * 聚合快递公共服务层
 * Class CommonService
 * @package addon\tk_jhkd\service\core\common
 */
class ExpressService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }

    public function queryExpress($site_id, $params)
    {
        $data = Cache::get($params['delivery_id'], '');
        if ($data != '') return $data;
        $config = (new CommonService())->getConfig($site_id);
        if (!isset($config['appcode']) || $config['appcode'] == '') throw new Exception('阿里物流轨迹查询未配置，请联系管理员');
        $host = "https://kzexpress.market.alicloudapi.com";
        $path = "/api-mall/api/express/query";
        $method = "POST";
        $appcode = $config['appcode'];
        $headers = array();
        array_push($headers, "Authorization:APPCODE " . $appcode);
        //根据API的要求，定义相对应的Content-Type
        array_push($headers, "Content-Type" . ":" . "application/x-www-form-urlencoded; charset=UTF-8");
        $querys = "";
        $mobile = "";
        if (strpos($params['delivery_id'], 'SF') !== false) {
            $mobile = json_decode($params['end_address'], true)['mobile'];
            if ($mobile == '') throw new Exception('请复制物流单号前往顺丰官网查询');
            $mobile = substr($mobile, -4);
        }
        $bodys = "expressNo=" . $params['delivery_id'] . "&mobile=" . $mobile;
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
            if ($res&&$res['code'] == 200 && isset($res['data']['logisticsTraceDetailList'])) {
                $res['data']['logisticsTraceDetailList'] = array_reverse($res['data']['logisticsTraceDetailList']);
                foreach ($res['data']['logisticsTraceDetailList'] as $k => $v) {
                    $res['data']['logisticsTraceDetailList'][$k]['time'] = date('Y-m-d H:i:s', $v['time'] / 1000);
                }
                $tranceData = [];
                foreach ($res['data']['logisticsTraceDetailList'] as $k => $v) {
                    $tranceData[] = [
                        'time' => $v['time'],
                        'desc' => $v['desc'] ?? '',
                    ];
                }
                Cache::set($params['delivery_id'], $tranceData, 60 * 3);
                return $tranceData;
            } else {
                return [];
            }
        }
    }
}