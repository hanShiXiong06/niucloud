<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

final class WecomConfigService
{
    public const CONFIG_KEY = 'HSX_WECOM_CONFIG';
    public const SECRET_MASK = '******';

    public function get(int $siteId, bool $maskSecret = false): array
    {
        $value = (new CoreConfigService())->getConfigValue($siteId, self::CONFIG_KEY);
        $config = $this->normalize(is_array($value) ? $value : []);
        if ($maskSecret && $config['secret'] !== '') {
            $config['secret'] = self::SECRET_MASK;
            $config['secret_configured'] = 1;
        } else {
            $config['secret_configured'] = $config['secret'] !== '' ? 1 : 0;
        }
        return $config;
    }

    public function save(int $siteId, array $data): array
    {
        $stored = $this->get($siteId);
        if (($data['secret'] ?? '') === self::SECRET_MASK || trim((string)($data['secret'] ?? '')) === '') {
            $data['secret'] = $stored['secret'];
        }
        $config = $this->normalize(array_replace($stored, $data));
        if ($config['web_base_url'] !== '') {
            $scheme = strtolower((string)parse_url($config['web_base_url'], PHP_URL_SCHEME));
            if (!filter_var($config['web_base_url'], FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
                throw new CommonException('网页管理端地址必须是有效的 http 或 https 地址');
            }
        }
        if (!empty($config['enabled'])) {
            if ($config['corp_id'] === '' || (int)$config['agent_id'] <= 0 || $config['secret'] === '') {
                throw new CommonException('启用企业微信前，请完整填写企业 ID、AgentId 和 Secret');
            }
            if (!empty($config['task_notice_enabled']) || !empty($config['report_notice_enabled'])) {
                if ($config['jump_mode'] === 'web' && $config['web_base_url'] === '') {
                    throw new CommonException('网页打开模式必须填写网页管理端地址');
                }
                if ($config['jump_mode'] === 'miniapp' && $config['miniapp_appid'] === '') {
                    throw new CommonException('小程序打开模式必须填写后台管理小程序 AppID');
                }
                if ($config['jump_mode'] === 'dual' && ($config['web_base_url'] === '' || $config['miniapp_appid'] === '')) {
                    throw new CommonException('双入口模式必须同时填写网页管理端地址和后台管理小程序 AppID');
                }
            }
        }
        (new CoreConfigService())->setConfig($siteId, self::CONFIG_KEY, $config);
        return $this->get($siteId, true);
    }

    private function normalize(array $data): array
    {
        $legacyBaseUrl = trim((string)($data['admin_base_url'] ?? ''));
        $explicitWebBaseUrl = trim((string)($data['web_base_url'] ?? ''));
        $baseUrl = rtrim($explicitWebBaseUrl !== '' ? $explicitWebBaseUrl : $legacyBaseUrl, '/');
        // 旧版配置可能填到 /site 或 /adminapp；新事件自带完整业务路由，只保留站点根地址。
        if ($explicitWebBaseUrl === '' && preg_match('#/(?:site|adminapp)$#i', $baseUrl)) {
            $baseUrl = rtrim((string)preg_replace('#/(?:site|adminapp)$#i', '', $baseUrl), '/');
        }
        $jumpMode = trim((string)($data['jump_mode'] ?? 'web'));
        if (!in_array($jumpMode, ['dual', 'miniapp', 'web'], true)) $jumpMode = 'web';
        $recycleTaskTarget = trim((string)($data['recycle_task_target'] ?? 'detail'));
        if (!in_array($recycleTaskTarget, ['detail', 'list'], true)) $recycleTaskTarget = 'detail';
        return [
            'enabled' => (int)!empty($data['enabled']),
            'corp_id' => trim((string)($data['corp_id'] ?? '')),
            'agent_id' => max(0, (int)($data['agent_id'] ?? 0)),
            'secret' => trim((string)($data['secret'] ?? '')),
            'web_base_url' => $baseUrl,
            'admin_base_url' => $baseUrl,
            'miniapp_appid' => trim((string)($data['miniapp_appid'] ?? '')),
            'jump_mode' => $jumpMode,
            'recycle_task_target' => $recycleTaskTarget,
            'task_notice_enabled' => (int)!empty($data['task_notice_enabled']),
            'report_notice_enabled' => (int)($data['report_notice_enabled'] ?? 1) === 1 ? 1 : 0,
        ];
    }
}
