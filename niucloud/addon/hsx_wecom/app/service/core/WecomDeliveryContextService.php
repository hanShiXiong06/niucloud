<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\model\WecomCorpAuthorization;
use core\exception\CommonException;

/**
 * 发送前解析唯一投递链路。
 *
 * 站点只决定通知开关；Corp、AgentId、凭据和管理小程序 AppID 必须来自同一
 * 服务商授权记录。只有站点明确选择自建应用时才允许使用旧版配置；服务商
 * 通道缺失或失效必须失败关闭，避免残留 Secret 从错误企业发送消息。
 */
final class WecomDeliveryContextService
{
    public function resolve(int $siteId, bool $requireUsable = false): array
    {
        $siteConfig = (new WecomConfigService())->get($siteId);
        $suite = (new WecomProviderConfigService())->active();
        $wantsProvider = (string)($siteConfig['connection_mode'] ?? 'self_built') === 'provider';
        if ($wantsProvider && $suite->isEmpty()) {
            $context = array_replace($siteConfig, [
                'site_id' => $siteId,
                'connection_mode' => 'provider',
                'credential_mode' => 'provider',
                'provider_suite_id' => 0,
                'corp_authorization_id' => 0,
                'channel_code' => '',
                'provider_status' => 'not_configured',
            ]);
            if ($requireUsable) throw new CommonException($this->providerStatusMessage('not_configured'));
            return $context;
        }
        if ($wantsProvider && !$suite->isEmpty()) {
            $authorization = WecomCorpAuthorization::where([
                ['site_id', '=', $siteId],
                ['provider_suite_id', '=', (int)$suite->id],
            ])->findOrEmpty();
            if (!$authorization->isEmpty() && (string)$authorization->status === 'authorized') {
                $context = array_replace($siteConfig, [
                    'site_id' => $siteId,
                    'connection_mode' => 'provider',
                    'credential_mode' => 'provider',
                    'provider_suite_id' => (int)$suite->id,
                    'corp_authorization_id' => (int)$authorization->id,
                    'channel_code' => (string)$suite->channel_code,
                    'corp_id' => (string)$authorization->auth_corpid,
                    'auth_corpid' => (string)$authorization->auth_corpid,
                    'agent_id' => (int)$authorization->agent_id,
                    'secret' => '',
                    'miniapp_appid' => (string)$suite->admin_miniapp_appid,
                    'web_base_url' => rtrim((string)$suite->web_base_url, '/'),
                    'admin_base_url' => rtrim((string)$suite->web_base_url, '/'),
                    'provider_status' => 'authorized',
                    'provider_corp_name' => (string)$authorization->corp_name,
                ]);
                if ($requireUsable) $this->assertUsable($context);
                return $context;
            }

            if ((string)($siteConfig['connection_mode'] ?? 'provider') === 'provider') {
                $status = $authorization->isEmpty() ? 'not_authorized' : (string)$authorization->status;
                $context = array_replace($siteConfig, [
                    'site_id' => $siteId,
                    'connection_mode' => 'provider',
                    'credential_mode' => 'provider',
                    'provider_suite_id' => (int)$suite->id,
                    'corp_authorization_id' => (int)($authorization->id ?? 0),
                    'channel_code' => (string)$suite->channel_code,
                    'miniapp_appid' => (string)$suite->admin_miniapp_appid,
                    'web_base_url' => rtrim((string)$suite->web_base_url, '/'),
                    'admin_base_url' => rtrim((string)$suite->web_base_url, '/'),
                    'provider_status' => $status,
                ]);
                if ($requireUsable) throw new CommonException($this->providerStatusMessage($status));
                return $context;
            }
        }

        $context = array_replace($siteConfig, [
            'site_id' => $siteId,
            'connection_mode' => 'self_built',
            'credential_mode' => 'self_built',
            'provider_suite_id' => 0,
            'corp_authorization_id' => 0,
            'channel_code' => '',
            'provider_status' => 'fallback',
        ]);
        if ($requireUsable) $this->assertUsable($context);
        return $context;
    }

    public function assertUsable(array $context): void
    {
        if (empty($context['enabled'])) throw new CommonException('企业微信通知尚未启用');
        if (($context['credential_mode'] ?? 'self_built') === 'provider') {
            if (($context['provider_status'] ?? '') !== 'authorized' || (int)($context['corp_authorization_id'] ?? 0) <= 0) {
                throw new CommonException($this->providerStatusMessage((string)($context['provider_status'] ?? 'not_authorized')));
            }
        } elseif (trim((string)($context['corp_id'] ?? '')) === ''
            || trim((string)($context['secret'] ?? '')) === ''
            || (int)($context['agent_id'] ?? 0) <= 0) {
            throw new CommonException('企业微信自建应用配置不完整');
        }
        if ((int)($context['agent_id'] ?? 0) <= 0) throw new CommonException('企业微信授权应用 AgentId 缺失，请重新授权');
        if (($context['jump_mode'] ?? 'miniapp') !== 'web' && trim((string)($context['miniapp_appid'] ?? '')) === '') {
            throw new CommonException('当前 SaaS 未绑定后台管理小程序');
        }
    }

    private function providerStatusMessage(string $status): string
    {
        return match ($status) {
            'not_configured' => '平台企业微信服务商通道未配置或已停用，请联系平台管理员',
            'cancelled' => '客户企业已取消企业微信授权，请重新授权',
            'changed' => '客户企业微信授权范围已变化，请检查并重新确认',
            'error' => '客户企业微信授权异常，请在企业微信页面修复授权',
            default => '客户企业微信尚未授权，请先点击“一键授权企业微信”',
        };
    }
}
