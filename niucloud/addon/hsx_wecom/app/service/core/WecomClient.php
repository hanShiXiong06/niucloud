<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\model\WecomCorpAuthorization;
use addon\hsx_wecom\app\model\WecomProviderSuite;
use core\exception\CommonException;
use think\facade\Cache;

final class WecomClient
{
    private const API_BASE = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function test(array $config): array
    {
        $token = $this->accessToken($config, true);
        $result = $this->request('GET', '/agent/get', [
            'access_token' => $token,
            'agentid' => (int)$config['agent_id'],
        ]);
        return [
            'connected' => true,
            'agent_name' => (string)($result['name'] ?? ''),
            'agent_id' => (int)$config['agent_id'],
        ];
    }

    public function sendTextCard(array $config, string $userId, array $card): array
    {
        $payload = [
            'touser' => $userId,
            'msgtype' => 'textcard',
            'agentid' => (int)$config['agent_id'],
            'textcard' => [
                'title' => (string)($card['title'] ?? '新的待办任务'),
                'description' => (string)($card['description'] ?? ''),
                'url' => (string)($card['url'] ?? ''),
                'btntxt' => (string)($card['button_text'] ?? '查看任务'),
            ],
            'enable_id_trans' => 0,
            'enable_duplicate_check' => 1,
            'duplicate_check_interval' => 1800,
        ];
        return $this->sendMessage($config, $userId, $payload);
    }

    public function sendTaskCard(array $config, string $userId, array $card): array
    {
        $jumpList = [];
        $miniappAppid = trim((string)($card['miniapp_appid'] ?? ''));
        $miniappPath = ltrim(trim((string)($card['miniapp_path'] ?? '')), '/');
        $webUrl = trim((string)($card['web_url'] ?? ''));
        if ($miniappAppid !== '' && $miniappPath !== '') {
            $jumpList[] = [
                'type' => 2,
                'title' => '小程序处理',
                'appid' => $miniappAppid,
                'pagepath' => $miniappPath,
            ];
        }
        if ($webUrl !== '') {
            $jumpList[] = [
                'type' => 1,
                'title' => '网页处理',
                'url' => $webUrl,
            ];
        }
        if ($jumpList === []) {
            return $this->sendTextCard($config, $userId, $card);
        }

        $primary = $jumpList[0];
        $cardAction = ['type' => (int)$primary['type']];
        if ((int)$primary['type'] === 2) {
            $cardAction['appid'] = (string)$primary['appid'];
            $cardAction['pagepath'] = (string)$primary['pagepath'];
        } else {
            $cardAction['url'] = (string)$primary['url'];
        }
        $payload = [
            'touser' => $userId,
            'msgtype' => 'template_card',
            'agentid' => (int)$config['agent_id'],
            'template_card' => [
                'card_type' => 'text_notice',
                'source' => ['desc' => (string)($card['source_desc'] ?? '业务待办')],
                'main_title' => [
                    'title' => (string)($card['title'] ?? '新的待办任务'),
                    'desc' => (string)($card['summary'] ?? '任务已分配给你，请及时处理'),
                ],
                'sub_title_text' => (string)($card['plain_content'] ?? ''),
                'jump_list' => $jumpList,
                'card_action' => $cardAction,
            ],
            'enable_id_trans' => 0,
            'enable_duplicate_check' => 1,
            'duplicate_check_interval' => 1800,
        ];
        return $this->sendMessage($config, $userId, $payload);
    }

    /**
     * 为具有客户联系权限的成员生成“联系我”二维码。
     * 调用方必须准备备用二维码；客户联系权限不足时本方法会明确抛错，由业务插件降级。
     */
    public function addContactWay(array $config, array $userIds, string $state, string $remark = ''): array
    {
        $userIds = array_values(array_unique(array_filter(array_map(
            static fn($item): string => trim((string)$item),
            $userIds
        ))));
        if ($userIds === []) throw new CommonException('企业微信联系我缺少使用成员');
        $payload = [
            'type' => count($userIds) > 1 ? 2 : 1,
            'scene' => 2,
            'style' => 1,
            'remark' => mb_substr(trim($remark), 0, 30),
            'skip_verify' => true,
            'state' => mb_substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $state) ?: '', 0, 30),
            'user' => $userIds,
        ];
        try {
            return $this->request('POST', '/externalcontact/add_contact_way', [
                'access_token' => $this->accessToken($config),
            ], $payload);
        } catch (CommonException $e) {
            if (!$this->isAccessTokenError($e->getMessage())) throw $e;
            return $this->request('POST', '/externalcontact/add_contact_way', [
                'access_token' => $this->accessToken($config, true),
            ], $payload);
        }
    }

    /** 构造企业微信自建应用的静默网页授权地址。 */
    public function oauthAuthorizeUrl(array $config, string $redirectUri, string $state): string
    {
        $corpId = trim((string)($config['corp_id'] ?? ''));
        $redirectUri = trim($redirectUri);
        if ($corpId === '' || $redirectUri === '' || !filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            throw new CommonException('企业微信身份绑定地址配置不正确');
        }
        $configuredHost = strtolower((string)parse_url((string)($config['web_base_url'] ?? ''), PHP_URL_HOST));
        $redirectHost = strtolower((string)parse_url($redirectUri, PHP_URL_HOST));
        if ($configuredHost !== '' && $configuredHost !== $redirectHost) {
            throw new CommonException('身份绑定回调域名与企业微信配置的管理端域名不一致');
        }
        $query = http_build_query([
            'appid' => $corpId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'snsapi_base',
            'state' => $state,
        ]);
        return 'https://open.weixin.qq.com/connect/oauth2/authorize?' . $query . '#wechat_redirect';
    }

    /** 使用 OAuth code 获取当前企业微信成员 UserID。 */
    public function userIdByOauthCode(array $config, string $code): string
    {
        $code = trim($code);
        if ($code === '') throw new CommonException('企业微信身份授权码不能为空');
        $result = $this->request('GET', '/user/getuserinfo', [
            'access_token' => $this->accessToken($config),
            'code' => $code,
        ]);
        $userId = trim((string)($result['UserId'] ?? $result['userid'] ?? ''));
        if ($userId === '') {
            throw new CommonException('当前访问者不是企业内部成员，无法绑定员工账号');
        }
        return $userId;
    }

    /**
     * 生成企业微信网页 JS-SDK 与应用级 agentConfig 签名。
     * openEnterpriseChat 必须使用 agentConfig 注入权限，才能在建群成功后返回 chatId。
     */
    public function jsSdkConfig(array $config, string $url): array
    {
        $corpId = trim((string)($config['corp_id'] ?? ''));
        if ($corpId === '') {
            throw new CommonException('企业微信 CorpID 未配置');
        }
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new CommonException('企业微信建群页面地址不正确');
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new CommonException('企业微信建群页面必须使用 http 或 https 地址');
        }
        $configuredHost = strtolower((string)parse_url((string)($config['web_base_url'] ?? ''), PHP_URL_HOST));
        $requestHost = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($configuredHost !== '' && $requestHost !== $configuredHost) {
            throw new CommonException('当前页面域名与企业微信配置的管理端域名不一致');
        }

        // 企业微信签名只包含 # 前面的完整 URL。
        $url = explode('#', $url, 2)[0];
        $timestamp = time();
        $nonce = bin2hex(random_bytes(12));
        $normalTicket = $this->jsApiTicket($config, false);
        $agentTicket = $this->jsApiTicket($config, true);
        $sign = static fn(string $ticket): string => sha1(
            'jsapi_ticket=' . $ticket . '&noncestr=' . $nonce . '&timestamp=' . $timestamp . '&url=' . $url
        );

        $configJsApiList = ['checkJsApi'];
        $agentJsApiList = ['selectExternalContact', 'openEnterpriseChat'];

        return [
            'corp_id' => $corpId,
            'agent_id' => (int)$config['agent_id'],
            'timestamp' => $timestamp,
            'nonce_str' => $nonce,
            'signature' => $sign($normalTicket),
            'agent_signature' => $sign($agentTicket),
            // 企业身份 wx.config 与应用身份 agentConfig 的能力清单必须分开。
            'config_js_api_list' => $configJsApiList,
            'agent_js_api_list' => $agentJsApiList,
            // 兼容已发布但尚未更新的管理端，它仍会读取 js_api_list。
            'js_api_list' => $agentJsApiList,
            // 仅返回实际参与签名的页面地址，便于前端区分 URL 不一致与权限问题。
            // ticket、Secret 等敏感信息不会暴露。
            'sign_url' => $url,
        ];
    }

    private function jsApiTicket(array $config, bool $agent): string
    {
        $token = $this->accessToken($config);
        // v2 用于隔离旧版本曾从错误端点取得并缓存的 agent ticket。
        $cacheKey = 'hsx_wecom_jsapi_ticket_v2_' . ($agent ? 'agent_' : 'normal_') . md5($token);
        $cached = (string)Cache::get($cacheKey, '');
        if ($cached !== '') return $cached;

        $query = ['access_token' => $token];
        // 企业身份 wx.config 与应用身份 agentConfig 使用两套 ticket 接口，不能混用。
        // 普通 ticket: /get_jsapi_ticket
        // 应用 ticket: /ticket/get?type=agent_config
        $path = '/get_jsapi_ticket';
        if ($agent) {
            $path = '/ticket/get';
            $query['type'] = 'agent_config';
        }
        $response = $this->request('GET', $path, $query);
        $ticket = trim((string)($response['ticket'] ?? ''));
        if ($ticket === '') throw new CommonException('企业微信未返回 JS-SDK ticket');
        Cache::set($cacheKey, $ticket, max(60, (int)($response['expires_in'] ?? 7200) - 300));
        return $ticket;
    }

    private function accessToken(array $config, bool $refresh = false): string
    {
        if (($config['credential_mode'] ?? $config['connection_mode'] ?? '') === 'provider') {
            $suiteId = (int)($config['provider_suite_id'] ?? 0);
            $authorizationId = (int)($config['corp_authorization_id'] ?? 0);
            $suite = WecomProviderSuite::where('id', '=', $suiteId)->findOrEmpty();
            $authorization = WecomCorpAuthorization::where('id', '=', $authorizationId)->findOrEmpty();
            if ($suite->isEmpty() || $authorization->isEmpty()) {
                throw new CommonException('企业微信服务商授权上下文不存在，请重新授权');
            }
            if ((int)$authorization->site_id !== (int)($config['site_id'] ?? 0)
                || (int)$authorization->provider_suite_id !== (int)$suite->id
                || trim((string)$authorization->auth_corpid) !== trim((string)($config['auth_corpid'] ?? $config['corp_id'] ?? ''))) {
                throw new CommonException('企业微信站点、授权企业与服务商通道不一致，已停止发送');
            }
            return (new WecomProviderCredentialService())->corpAccessToken($suite, $authorization, $refresh);
        }
        $corpId = trim((string)($config['corp_id'] ?? ''));
        $secret = trim((string)($config['secret'] ?? ''));
        if ($corpId === '' || $secret === '' || (int)($config['agent_id'] ?? 0) <= 0) {
            throw new CommonException('请先完整配置企业 ID、应用 AgentId 和 Secret');
        }
        $cacheKey = 'hsx_wecom_token_' . md5($corpId . ':' . $secret);
        if (!$refresh) {
            $cached = (string)Cache::get($cacheKey, '');
            if ($cached !== '') return $cached;
        }
        $response = $this->request('GET', '/gettoken', ['corpid' => $corpId, 'corpsecret' => $secret]);
        $token = trim((string)($response['access_token'] ?? ''));
        if ($token === '') throw new CommonException('企业微信未返回 access_token');
        Cache::set($cacheKey, $token, max(60, (int)($response['expires_in'] ?? 7200) - 300));
        return $token;
    }

    /**
     * 企业微信可能在 errcode=0 时通过 invaliduser/unlicenseduser 告知部分接收人未送达。
     * 当前插件每次只给一个员工发送，因此只要出现这些字段就必须按失败处理，不能写成“已送达”。
     */
    private function sendMessage(array $config, string $userId, array $payload): array
    {
        try {
            $response = $this->request('POST', '/message/send', [
                'access_token' => $this->accessToken($config),
            ], $payload);
        } catch (CommonException $e) {
            if (!$this->isAccessTokenError($e->getMessage())) throw $e;
            $response = $this->request('POST', '/message/send', [
                'access_token' => $this->accessToken($config, true),
            ], $payload);
        }

        $this->assertRecipientAccepted($response, $userId);
        return $response;
    }

    private function isAccessTokenError(string $message): bool
    {
        foreach (['40014', '42001', '40001', '42007', '42009'] as $code) {
            if (str_contains($message, $code)) return true;
        }
        return false;
    }

    private function assertRecipientAccepted(array $response, string $userId): void
    {
        $invalid = $this->recipientList($response['invaliduser'] ?? '');
        if ($invalid !== []) {
            throw new CommonException('企业微信未接收该员工消息：成员不在应用可见范围或身份绑定已失效（' . $userId . '）');
        }
        $unlicensed = $this->recipientList($response['unlicenseduser'] ?? '');
        if ($unlicensed !== []) {
            throw new CommonException('企业微信未接收该员工消息：该成员尚未分配接口调用许可（' . $userId . '）');
        }
    }

    private function recipientList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn($item): string => trim((string)$item), $value)));
        }
        $value = trim((string)$value);
        if ($value === '') return [];
        return array_values(array_filter(preg_split('/[|,;]+/', $value) ?: []));
    }

    private function request(string $method, string $path, array $query = [], array $json = []): array
    {
        $url = self::API_BASE . $path . ($query ? '?' . http_build_query($query) : '');
        $curl = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($body === false || $error !== '') throw new CommonException('请求企业微信失败：' . $error);
        if ($status < 200 || $status >= 300) throw new CommonException('企业微信接口 HTTP 状态异常：' . $status);
        $result = json_decode((string)$body, true);
        if (!is_array($result)) throw new CommonException('企业微信接口返回格式异常');
        if ((int)($result['errcode'] ?? 0) !== 0) {
            $code = (int)$result['errcode'];
            $message = trim((string)($result['errmsg'] ?? ''));
            throw new CommonException('企业微信接口错误 ' . $code . '：' . ($message !== '' ? $message : '未知错误'));
        }
        return $result;
    }
}
