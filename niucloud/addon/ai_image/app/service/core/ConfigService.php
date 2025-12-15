<?php

namespace addon\ai_image\app\service\core;

use app\service\core\site\CoreSiteService;
use app\service\core\sys\CoreSysConfigService;
use core\base\BaseAdminService;
use app\service\core\sys\CoreConfigService;
use addon\ai_image\app\dict\config\ConfigDict;

/**
 * 配置信息服务层
 */
class ConfigService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();

    }
    public function isTkPoint()
    {
        $addons = (new CoreSiteService())->getAddonKeysBySiteId($this->site_id);
        if (in_array('tk_point', $addons)) {
            return true;
        }
        return false;
    }
    public function getSxfConfig()
    {
        $info = (new CoreConfigService())->getConfig(0, 'sxf_config_admin');
        if (empty($info)) {
            $config = $this->getConfig();
            $data = [
                'public_key' => $config['public_key'] ?? '',
                'private_key' => $config['private_key'] ?? '',
                'org_id' => $config['private_key'] ?? '',
            ];
            (new ConfigService())->setSxfConfig($data);
            $info = (new CoreConfigService())->getConfig(0, 'sxf_config_admin');
        }
        return $info['value'];
    }

    public function setSxfConfig($value)
    {
        (new CoreConfigService())->setConfig(0, 'sxf_config_admin', $value);
        return true;
    }

    /**
     * 获取配置信息
     * @param $site_id
     * @param string $config_key
     */
    public function getConfig()
    {
        $info = (new CoreConfigService())->getConfig($this->site_id, ConfigDict::getType());
        if (empty($info)) {
            $info['value']['ai_model'] = '';
            $info['value']['ai_key'] = '';
            $info['value']['ai_host'] = '';
            $info['value']['api_key'] = '';
        }
        $web_url = (new CoreSysConfigService())->getSceneDomain($this->site_id)['web_url'];
        $info['value']['url'] = $web_url . '/ai_image/index';
        return $info['value'];
    }

    /**
     * 设置配置信息
     * @param $site_id
     * @param string $config_key
     * @return string|null
     */
    public function setConfig($value)
    {
        (new CoreConfigService())->setConfig($this->site_id, ConfigDict::getType(), $value);
        return true;
    }

    public function getConfigSite($site_id)
    {
        $this->site_id = $site_id;
        $info = (new CoreConfigService())->getConfig($this->site_id, ConfigDict::getType());
        if (empty($info)) {
            $info['value']['ai_model'] = '';
            $info['value']['ai_key'] = '';
            $info['value']['ai_host'] = '';
            $info['value']['api_key'] = '';
        }
        return $info['value'];
    }

}