<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\model\WecomCorpAuthorization;
use addon\hsx_wecom\app\model\WecomProviderSuite;
use addon\hsx_wecom\app\support\WecomProviderCipher;
use core\exception\CommonException;
use think\facade\Cache;

/** 企业微信第三方应用票据、授权码与企业访问令牌。 */
final class WecomProviderCredentialService
{
    private const API_BASE = 'https://qyapi.weixin.qq.com/cgi-bin';

    public function suiteAccessToken(WecomProviderSuite $suite, bool $refresh = false): string
    {
        $this->assertSuiteReady($suite, true);
        $cacheKey = 'hsx_wecom_suite_token_' . md5((string)$suite->suite_id);
        if (!$refresh) {
            $cached = trim((string)Cache::get($cacheKey, ''));
            if ($cached !== '') return $cached;
        }

        $response = $this->request('POST', '/service/get_suite_token', [], [
            'suite_id' => trim((string)$suite->suite_id),
            'suite_secret' => WecomProviderCipher::decrypt((string)$suite->suite_secret_cipher),
            'suite_ticket' => trim((string)$suite->suite_ticket),
        ]);
        $token = trim((string)($response['suite_access_token'] ?? ''));
        if ($token === '') throw new CommonException('企业微信没有返回 suite_access_token');
        Cache::set($cacheKey, $token, max(60, (int)($response['expires_in'] ?? 7200) - 300));
        return $token;
    }

    /** @return array{pre_auth_code:string,expires_in:int} */
    public function preAuthCode(WecomProviderSuite $suite): array
    {
        $response = $this->request('GET', '/service/get_pre_auth_code', [
            'suite_access_token' => $this->suiteAccessToken($suite),
        ]);
        $code = trim((string)($response['pre_auth_code'] ?? ''));
        if ($code === '') throw new CommonException('企业微信没有返回预授权码，请检查 SuiteTicket 是否已推送');
        return ['pre_auth_code' => $code, 'expires_in' => max(60, (int)($response['expires_in'] ?? 1200))];
    }

    public function setSessionInfo(WecomProviderSuite $suite, string $preAuthCode): void
    {
        $this->request('POST', '/service/set_session_info', [
            'suite_access_token' => $this->suiteAccessToken($suite),
        ], [
            'pre_auth_code' => $preAuthCode,
            // 0 表示管理员安装时可选择授权范围；避免代码写死通讯录范围。
            'session_info' => ['auth_type' => 0],
        ]);
    }

    /** 使用安装回调 auth_code 换取永久授权信息。 */
    public function permanentCode(WecomProviderSuite $suite, string $authCode): array
    {
        $authCode = trim($authCode);
        if ($authCode === '') throw new CommonException('企业微信授权码不能为空');
        return $this->request('POST', '/service/get_permanent_code', [
            'suite_access_token' => $this->suiteAccessToken($suite),
        ], ['auth_code' => $authCode]);
    }

    public function authInfo(WecomProviderSuite $suite, string $authCorpId, string $permanentCode): array
    {
        return $this->request('POST', '/service/get_auth_info', [
            'suite_access_token' => $this->suiteAccessToken($suite),
        ], [
            'auth_corpid' => trim($authCorpId),
            'permanent_code' => trim($permanentCode),
        ]);
    }

    public function corpAccessToken(WecomProviderSuite $suite, WecomCorpAuthorization $authorization, bool $refresh = false): string
    {
        if ((string)$authorization->status !== 'authorized') {
            throw new CommonException('客户企业微信尚未授权或授权已失效');
        }
        $corpId = trim((string)$authorization->auth_corpid);
        $permanentCode = WecomProviderCipher::decrypt((string)$authorization->permanent_code_cipher);
        if ($corpId === '' || $permanentCode === '') throw new CommonException('客户企业微信授权凭据不完整，请重新授权');

        $cacheKey = 'hsx_wecom_corp_token_' . (int)$authorization->id . '_' . md5($corpId);
        if (!$refresh) {
            $cached = trim((string)Cache::get($cacheKey, ''));
            if ($cached !== '') return $cached;
        }
        $response = $this->request('POST', '/service/get_corp_token', [
            'suite_access_token' => $this->suiteAccessToken($suite),
        ], [
            'auth_corpid' => $corpId,
            'permanent_code' => $permanentCode,
        ]);
        $token = trim((string)($response['access_token'] ?? ''));
        if ($token === '') throw new CommonException('企业微信没有返回客户企业 access_token');
        Cache::set($cacheKey, $token, max(60, (int)($response['expires_in'] ?? 7200) - 300));
        return $token;
    }

    /** 第三方应用 OAuth code 换取当前成员的 open_userid。 */
    public function userInfo3rd(WecomProviderSuite $suite, string $code): array
    {
        $code = trim($code);
        if ($code === '') throw new CommonException('企业微信成员授权码不能为空');
        return $this->request('GET', '/service/auth/getuserinfo3rd', [
            'suite_access_token' => $this->suiteAccessToken($suite),
            'code' => $code,
        ]);
    }

    public function installUrl(WecomProviderSuite $suite, string $preAuthCode, string $state): string
    {
        $redirectUri = trim((string)$suite->auth_callback_url);
        if ($redirectUri === '' || !filter_var($redirectUri, FILTER_VALIDATE_URL)) {
            throw new CommonException('服务商授权回调地址未配置');
        }
        return 'https://open.work.weixin.qq.com/3rdapp/install?' . http_build_query([
            'suite_id' => trim((string)$suite->suite_id),
            'pre_auth_code' => trim($preAuthCode),
            'redirect_uri' => $redirectUri,
            'state' => $state,
        ]);
    }

    public function memberOauthUrl(WecomProviderSuite $suite, WecomCorpAuthorization $authorization, string $state): string
    {
        $base = rtrim(trim((string)$suite->web_base_url), '/');
        if ($base === '' || !filter_var($base, FILTER_VALIDATE_URL)) throw new CommonException('服务商管理端域名未配置');
        $redirectUri = $base . '/api/wecom/provider/member/complete/' . rawurlencode((string)$suite->channel_code);
        $query = http_build_query([
            // 第三方应用成员授权属于服务商应用域，appid 必须使用 SuiteID。
            // 客户企业 CorpID 会在 code 换取的身份结果中返回并再次校验。
            'appid' => trim((string)$suite->suite_id),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'snsapi_base',
            'state' => $state,
        ]);
        return 'https://open.weixin.qq.com/connect/oauth2/authorize?' . $query . '#wechat_redirect';
    }

    public function clearSuiteToken(WecomProviderSuite $suite): void
    {
        Cache::delete('hsx_wecom_suite_token_' . md5((string)$suite->suite_id));
    }

    public function clearCorpToken(WecomCorpAuthorization $authorization): void
    {
        $corpId = trim((string)$authorization->auth_corpid);
        if ((int)$authorization->id <= 0 || $corpId === '') return;
        Cache::delete('hsx_wecom_corp_token_' . (int)$authorization->id . '_' . md5($corpId));
    }

    private function assertSuiteReady(WecomProviderSuite $suite, bool $requireTicket): void
    {
        if ($suite->isEmpty() || (string)$suite->status !== 'enabled') throw new CommonException('企业微信服务商通道尚未启用');
        if (trim((string)$suite->suite_id) === '' || trim((string)$suite->suite_secret_cipher) === '') {
            throw new CommonException('企业微信 SuiteID 或 SuiteSecret 未配置');
        }
        if ($requireTicket && trim((string)$suite->suite_ticket) === '') {
            throw new CommonException('尚未收到企业微信 SuiteTicket，请先在服务商后台保存事件回调地址');
        }
    }

    private function request(string $method, string $path, array $query = [], array $json = []): array
    {
        $url = self::API_BASE . $path . ($query ? '?' . http_build_query($query) : '');
        $curl = curl_init($url);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        ];
        if (strtoupper($method) === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        if ($body === false || $error !== '') throw new CommonException('请求企业微信服务商接口失败：' . $error);
        if ($status < 200 || $status >= 300) throw new CommonException('企业微信服务商接口 HTTP 状态异常：' . $status);

        $result = json_decode((string)$body, true);
        if (!is_array($result)) throw new CommonException('企业微信服务商接口返回格式异常');
        if ((int)($result['errcode'] ?? 0) !== 0) {
            $code = (int)$result['errcode'];
            $message = trim((string)($result['errmsg'] ?? ''));
            throw new CommonException('企业微信服务商接口错误 ' . $code . '：' . ($message !== '' ? $message : '未知错误'));
        }
        return $result;
    }
}
