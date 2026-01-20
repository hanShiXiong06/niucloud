<?php

// +----------------------------------------------------------------------
// | Author: TK
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\service\admin\platform;

use addon\tk_jhkd\app\dict\config\PlatformDict;
use addon\tk_jhkd\app\service\core\CommonService;
use addon\tk_jhkd\app\service\core\delivery\DeliveryLoader;

use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use core\exception\AdminException;
/**
 * 配置服务层
 */
class PlatformService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @Notes:获取开放平台列表
     * @Interface getList
     * @return array
     * @author: TK
     * @Time: 2024/9/20   上午1:07
     */
    public function getList()
    {
        $platform_type_list = event('JhkdPlatformType')[0];
        $driver_list=$platform_type_list;
        $list1=[];
        foreach ($platform_type_list as $k => $v){
            foreach ($v as $k1=>$v1){
                $list1[]=$v1;
            }
        }
        $platform_type_list=$list1;
        $info = (new CoreConfigService())->getConfig($this->site_id, PlatformDict::getType());
        if(empty($info))
        {
            $config_type = [];//初始化
        }else
            $config_type = $info['value'];
        $list = [];

        foreach($platform_type_list as $k => $v)
        {
            $data = [];
            $data['type'] = $v['type'];
            $data['is_use'] = isset($config_type[$v['type']]['is_use']) ?$config_type[$v['type']]['is_use']: '';
            $data['name'] = $v['name'];
            foreach ($v['params'] as $k_param => $v_param)
            {
                $data['params'][$k_param] = [
                    'name' => $v_param,
                    'value' => $config_type[$k][$k_param] ?? ''
                ];

            }
            if(isset($config_type[$v['type']])){
                $data['balance']=(new DeliveryLoader( $v['type'], $config_type[$v['type']]))->getBalance();
            }else{
                $data['balance']='未配置';
            }

            foreach ($driver_list as $k_driver => $v_driver) {
                foreach ($v_driver as $k1 => $v1) {
                    if ($v1['type'] == $v['type']) {
                        $data['callback'] = $v1['callback'];
                    }
                }
            }
            $data['callback']=(new CommonService())->getUrl().$data['callback'];
            $data['component'] = $v['component'] ?? '';
            $list[] = $data;
        }
        return $list;
    }

    /**
     * @Notes:获取三方平台配置信息
     * @Interface getConfig
     * @param string $type
     * @return array
     * @author: TK
     * @Time: 2024/9/20   上午1:06
     */
    public function getConfig(string $type)
    {
        $platform_type_list = event('JhkdPlatformType')[0];
        $list1=[];
        foreach ($platform_type_list as $k => $v){
            foreach ($v as $k1=>$v1){
                $list1[$v1['type']][]=$v1;
            }
        }
        $platform_type_list=$list1;
        if(!array_key_exists($type, $platform_type_list)) throw new AdminException('PLATFORM_TYPE_NOT_EXIST');
        $info = (new CoreConfigService())->getConfig($this->site_id, PlatformDict::getType());
        if(empty($info))
        {
            $config_type = [];//初始化
        }else
            $config_type = $info['value'];

        $data = [
            'type' => $type,
            'name'   =>  $platform_type_list[$type][0]['name'],
        ];
        foreach ($platform_type_list[$type][0]['params'] as $k_param => $v_param)
        {
            $data['params'][$k_param] = [
                'name' => $v_param,
                'value' => $config_type[$type][$k_param] ?? ''
            ];
        }
        return $data;

    }

    /**
     * @Notes:保存开放平台信息
     * @Interface setConfig
     * @param string $type
     * @param array $data
     * @return \app\model\sys\SysConfig|bool|\think\Model
     * @author: TK
     * @Time: 2024/9/20   上午1:08
     */
    public function setConfig(string $type, array $data,$site_id='')
    {
        if($site_id!=''){
            $this->site_id=$site_id;
        }
        $platform_type_list = event('JhkdPlatformType')[0];
        $list1=[];
        foreach ($platform_type_list as $k => $v){
            foreach ($v as $k1=>$v1){
                $list1[$v1['type']][]=$v1;
            }
        }
        $platform_type_list=$list1;
        if(!array_key_exists($type, $platform_type_list)) throw new AdminException('PLATFORM_TYPE_NOT_EXIST');
        $info = (new CoreConfigService())->getConfig($this->site_id, PlatformDict::getType());
        if(empty($info))
        {
            $config = [];

        }else{
            $config = $info['value'];
        }
        foreach ($platform_type_list[$type][0]['params'] as $k_param => $v_param)
        {
            $config[$type][$k_param] = $data[$k_param] ?? '';
        }
        return (new CoreConfigService())->setConfig($this->site_id, PlatformDict::getType(), $config);
    }

}
