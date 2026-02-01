<?php

namespace addon\fast_pay\app\service\core\config;

use addon\fast_pay\app\model\business\Business;
use core\base\BaseAdminService;
use app\service\core\sys\CoreConfigService;
use addon\fast_pay\app\dict\config\ConfigDict;
use core\exception\CommonException;

/**
 * 配置信息服务层
 */
class ConfigService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();

    }
    public function getBusinessConfig($id)
    {
         $info=(new Business())
             ->field('id,name,desc,type,page,banner,address')
             ->where(['id'=>$id])->findOrEmpty();
         if($info->isEmpty()) throw new CommonException('商户不存在');
         return $info;
    }
    public function poster($data)
    {
       return poster($this->site_id, 0,'fast_pay_poster',[], $data['channel']);
    }
    /**
     * 获取配置信息
     */
    public function getConfig()
    {
        $info = (new CoreConfigService())->getConfig($this->site_id, ConfigDict::getConfigType());
        if (empty($info)) {
            $info['value']['type'] = '0';
            $info['value']['page'] = [
                "name" => ""
            ];
        }
        if($info['value']['page']==''){
            $info['value']['page']=[
                "name" => ""
            ];
        }
        return $info['value'];
    }
    public function getAdminConfig()
    {
        $info = (new CoreConfigService())->getConfig(0, ConfigDict::getPartnerConfigType());
        if (empty($info)) {
            $info['value']['mch_id'] = '';
            $info['value']['mch_secret_key'] = '';
            $info['value']['mch_secret_cert'] = '';
            $info['value']['mch_public_cert_path'] = '';
        }
        return $info['value'];
    }

    /**
     * 设置配置信息
     * @param $site_id
     * @param string $config_key
     * @return string|null
     */
    public function setAdminConfig($value)
    {

        (new CoreConfigService())->setConfig(0, ConfigDict::getPartnerConfigType(), $value);

        return true;
    }
    public function setBusinessConfig($value)
    {

        (new CoreConfigService())->setConfig($this->site_id, ConfigDict::getBusinessConfigType(), $value);

        return true;
    }
    public function getBusinessPayConfig()
    {
        $info = (new CoreConfigService())->getConfig($this->site_id, ConfigDict::getBusinessConfigType());
        if (empty($info)) {
            $info['value']['mch_id'] = '';
            $info['value']['mch_secret_key'] = '';
            $info['value']['mch_secret_cert'] = '';
            $info['value']['mch_public_cert_path'] = '';
        }
        return $info['value'];
    }
    public function setConfig($value)
    {

        (new CoreConfigService())->setConfig($this->site_id, ConfigDict::getConfigType(), $value);

        return true;
    }

}