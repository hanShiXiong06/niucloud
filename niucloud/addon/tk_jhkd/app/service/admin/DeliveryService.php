<?php

namespace addon\tk_jhkd\app\service\admin;

use addon\tk_jhkd\app\dict\delivery\DeliveryDict;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Cache;

/**
 * 短信配置服务层
 */
class DeliveryService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取短信配置列表(平台设置)
     */
    public function getList()
    {
        $sms_type_list = DeliveryDict::getType();
        $info = (new CoreConfigService())->getConfig($this->site_id, 'JHKD_DELIVERY');
        if(empty($info))
        {
            $config_type = ['default' => ''];//初始化
        }else
            $config_type = $info['value'];

        $list = [];
        foreach($sms_type_list as $k => $v)
        {
            $data = [];
            $data['delivery_type'] = $k;
            $data['is_use'] = $k == $config_type['default'] ? 1 : 0;
            $data['name'] = $v['name'];
            foreach ($v['params'] as $k_param => $v_param)
            {
                $data['params'][$k_param] = [
                    'name' => $v_param,
                    'value' => $config_type[$k][$k_param] ?? ''
                ];
            }
            $list[] = $data;
        }
        return $list;
    }

    /**
     * 获取短信配置
     * @param string $sms_type
     * @return array
     */
    public function getConfig(string $delivery_type)
    {
        $delivery_type_list = DeliveryDict::getType();
        if(!array_key_exists($delivery_type, $delivery_type_list)) throw new AdminException('DELIVERY_TYPE_NOT_EXIST');
        $info = (new CoreConfigService())->getConfig($this->site_id, 'JHKD_DELIVERY');
        if(empty($info))
        {
            $config_type = ['default' => ''];//初始化
        }else
            $config_type = $info['value'];

        $data = [
            'delivery_type' => $delivery_type,
            'is_use' => $delivery_type == $config_type['default'] ? 1 : 0,
            'name'   => $delivery_type_list[$delivery_type]['name'],
        ];
        foreach ($delivery_type_list[$delivery_type]['params'] as $k_param => $v_param)
        {
            $data['params'][$k_param] = [
                'name' => $v_param,
                'value' => $config_type[$delivery_type][$k_param] ?? ''
            ];
        }
        return $data;

    }

    /**
     * 短信配置
     * @param string $sms_type
     * @param array $data
     * @return bool
     */
    public function setConfig(string $delivery_type, array $data)
    {
        $delivery_type_list = DeliveryDict::getType();
        $info = (new CoreConfigService())->getConfig($this->site_id, 'JHKD_DELIVERY');
        if(empty($info))
        {
            $config['default'] = '';

        }else{
            $config = $info['value'];
        }
        //初始化数据
        if($data['is_use'])
        {
            $config['default'] = $delivery_type;
        }else{
            $config['default'] = '';
        }
        foreach ($delivery_type_list[$delivery_type]['params'] as $k_param => $v_param)
        {
            $config[$delivery_type][$k_param] = $data[$k_param] ?? '';
        }
        return (new CoreConfigService())->setConfig($this->site_id, 'JHKD_DELIVERY', $config);
    }


}
