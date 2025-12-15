<?php

namespace addon\ai_image\app\service\api\config;

use app\model\member\Member;
use core\base\BaseApiService;
use app\service\core\sys\CoreConfigService;
use addon\ai_image\app\dict\config\ConfigDict;

/**
 * 配置信息服务层
 */
class ConfigService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();

    }

    public function getPcMenu()
    {
        $menu = include root_path() . '/addon/ai_image/app/dict/pc/menu.php';
        return $menu;
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
        $point = (new Member())->where([['member_id', '=', $this->member_id]])->value('point') ?? 0;
        $data = [
            'alias_name' => '积分',
            'point' => $point ?? 0,
            'chat_point' => $info['value']['chat_point'] ?? 0,
            'notice' => $info['value']['notice'] ?? 'AI创作',
            'ios_pay' => $info['value']['ios_pay'] ?? 1,
            'ios_notice' => $info['value']['ios_notice'] ?? '',
            'is_scan' => $info['value']['is_scan'] ?? 0,
            'pc_pay' => $info['value']['pc_pay'] ?? 0,
            'pc_notice' => $info['value']['pc_notice'] ?? '暂未开通线上支付',
        ];
        return $data;
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