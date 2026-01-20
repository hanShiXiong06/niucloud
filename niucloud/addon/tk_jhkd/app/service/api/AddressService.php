<?php

namespace addon\tk_jhkd\app\service\api;

use addon\tk_jhkd\app\model\MemberAddress;
use app\service\core\sys\CoreConfigService;
use core\base\BaseApiService;
use think\Exception;
use app\model\sys\SysArea;

/**
 * 聚合快递通知列表服务层
 * Class TkjhkdNoticeService
 * @package app\service\admin\tkjhkd_notice
 */
class AddressService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->MemberAddressModel = new MemberAddress();
    }

    public function fanyiAddress($address)
    {
        $addressInfo = $this->fanyiNameAddress($address);
        $addressInfo['province'] = $addressInfo['province'] ?? '';
        $addressInfo['city'] = $addressInfo['city'] ?? '';
        $addressInfo['county'] = $addressInfo['county'] ?? '';
        $areaModel = new SysArea();
        //新数据组装
        $data = [
            'name' => $addressInfo['name'] ?? '',
            'mobile' => $addressInfo['mobile'] ?? '',
            'province_id' => $this->nameToId($areaModel,$addressInfo['province']),
            'city_id' => $this->nameToId($areaModel,$addressInfo['city']),
            'district_id' => $this->nameToId($areaModel,$addressInfo['county']),
            'address'=>$addressInfo['info']??'',
            'area'=>$addressInfo['area']??'',
            'address_name'=>$addressInfo['area']??'',
            'full_address'=>$addressInfo['area'].$addressInfo['info'],
        ];
        return $data;

    }
    public function getConfig()
    {
        $info = (new CoreConfigService())->getConfig($this->site_id, 'tk_jhkd_config');
        if (empty($info)) {
           throw new \Exception('请先完成基本配置');
        }
        return $info['value'];
    }
    public function nameToId($areaModel,$name)
    {
        if($name=='') return '';
        $res = $areaModel->where(['name' => $name])->findOrEmpty();
        if ($res->isEmpty()) return $name;
        return $res['id'];
    }

    public function fanyiNameAddress($address)
    {
        $config = $this->getConfig();
        if(!isset($config['address_use'])||$config['address_use']==0) throw new \Exception('地址解析未启用，请手动填写');
        $secretId = $config['tx_id'];
        $secretKey = $config['tx_secret'];
        $source = 'market';
        $datetime = gmdate('D, d M Y H:i:s T');
        $signStr = sprintf("x-date: %s\nx-source: %s", $datetime, $source);
        $sign = base64_encode(hash_hmac('sha1', $signStr, $secretKey, true));
        $auth = sprintf('hmac id="%s", algorithm="hmac-sha1", headers="x-date x-source", signature="%s"', $secretId, $sign);
        $method = 'GET';
        $headers = array(
            'X-Source' => $source,
            'X-Date' => $datetime,
            'Authorization' => $auth,
        );
        $queryParams = array(
            'address' => $address,
        );
        $bodyParams = array();
        $url = 'https://service-3qtg8hpi-1256115871.gz.apigw.tencentcs.com/release/identify_address';
        if (count($queryParams) > 0) {
            $url .= '?' . http_build_query($queryParams);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_map(function ($v, $k) {
            return $k . ': ' . $v;
        }, array_values($headers), array_keys($headers)));
        if (in_array($method, array('POST', 'PUT', 'PATCH'), true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($bodyParams));
        }
        $data = curl_exec($ch);
        curl_close($ch);
        if (curl_errno($ch)) {
            throw new Exception("Error: " . curl_error($ch));
        } else {
            $data = json_decode($data, true);
            if (isset($data['code'])&&$data['code'] == 1) {
                return $data['data'];
            } else {
                throw new Exception($data['msg']);
            }
        }
    }

    public function getMemberAddressInfo($id)
    {
        $info = $this->MemberAddressModel->where(['id' => $id])
            ->findOrEmpty()->append(['province_name', 'city_name', 'district_name']);
        //适配框架地址信息获取省/市/区
        $info['full_address'] = str_replace($info['province_name'] . $info['city_name'] . $info['district_name'], "", $info['full_address']);;
        $info['address'] = $info['province_name'] . '-' . $info['city_name'] . '-' . $info['district_name'];
        // 删除指定键
        $info = array_diff_key($info->toArray(), array_flip(['province_name', 'city_name', 'district_name']));
        return $info;
    }
}