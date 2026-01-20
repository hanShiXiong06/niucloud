<?php

namespace addon\tk_jhkd\app\service\core;

use addon\tk_jhkd\app\service\core\CommonService;
use core\base\BaseAdminService;
use app\service\core\sys\CoreConfigService;

/**
 * 配置信息服务层
 * Class ConfigService
 * @package addon\tk_jhkd\service\core\config
 */
class ConfigService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
    }
    public function addPlugin()
    {
        $site_id = $this->request->siteId();
        $plugin_appids = [
            'express-plugin' => 'wx1b1f66316b427c74',
        ];
        foreach ($plugin_appids as $plugin_name => $appid) {
            (new CoreWeappService())->addPlugin($site_id, $appid);
        }
        return true;
    }
    /**
     * 获取配置信息
     * @param $site_id
     * @param string $config_key
     */
    public function getConfig()
    {
        $info = (new CoreConfigService())->getConfig($this->site_id, 'TK_JHKD_CONFIG');
        $url_data = [
            [
                'name' => '易达178',
                'url' => (new CommonService())->getUrl() . '/api/tk_jhkd/yidanotice',
            ],
            [
                'name' => '云洋',
                'url' => (new CommonService())->getUrl() . '/api/tk_jhkd/yunyangnotice',
            ],
            [
                'name' => '辛达',
                'url' => (new CommonService())->getUrl() . '/api/tk_jhkd/xindanotice',
            ],
            [
                'name' => '快递鸟',
                'url' => (new CommonService())->getUrl() . '/api/tk_jhkd/kdniaonotice',
            ],
            [
                'name' => '亿速',
                'url' => (new CommonService())->getUrl() . '/api/tk_jhkd/yisunotice',
            ],
        ];
        if(empty($info)){
            $info['value']['floatWay'] = '';
        }
        $info['value']['url_data'] = $url_data;
        return $info;
    }

    /**
     * 设置配置信息
     * @param $site_id
     * @param string $config_key
     * @return string|null
     */
    public function setConfig($value)
    {
        return (new CoreConfigService())->setConfig($this->site_id, 'TK_JHKD_CONFIG', $value);
    }

    public function getSiteId()
    {
        return $this->site_id;
    }
}
