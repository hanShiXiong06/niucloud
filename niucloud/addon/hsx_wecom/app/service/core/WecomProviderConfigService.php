<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\model\WecomCorpAuthorization;
use addon\hsx_wecom\app\model\WecomMessageLog;
use addon\hsx_wecom\app\model\WecomProviderSuite;
use addon\hsx_wecom\app\model\WecomStaffBinding;
use addon\hsx_wecom\app\support\WecomProviderCipher;
use core\exception\CommonException;
use think\facade\Db;

/** 每套 SaaS 部署只需由平台管理员配置一次的服务商通道。 */
final class WecomProviderConfigService
{
    public const SECRET_MASK = '******';

    public function active(): WecomProviderSuite
    {
        return WecomProviderSuite::where('status', '=', 'enabled')->order('id asc')->findOrEmpty();
    }

    public function byChannel(string $channel): WecomProviderSuite
    {
        $channel = trim($channel);
        if ($channel === '') return new WecomProviderSuite();
        return WecomProviderSuite::where('channel_code', '=', $channel)->findOrEmpty();
    }

    public function info(bool $maskSecrets = true): array
    {
        $suite = $this->active();
        if ($suite->isEmpty()) {
            $suite = WecomProviderSuite::order('id asc')->findOrEmpty();
        }
        if ($suite->isEmpty()) return $this->defaults();

        $data = $suite->toArray();
        $data['enabled'] = (string)$suite->status === 'enabled' ? 1 : 0;
        $data['suite_secret_configured'] = trim((string)$suite->suite_secret_cipher) !== '' ? 1 : 0;
        $data['encoding_aes_key_configured'] = trim((string)$suite->encoding_aes_key_cipher) !== '' ? 1 : 0;
        $data['callback_token_configured'] = trim((string)$suite->callback_token) !== '' ? 1 : 0;
        if ($maskSecrets) {
            $data['suite_secret'] = $data['suite_secret_configured'] ? self::SECRET_MASK : '';
            $data['encoding_aes_key'] = $data['encoding_aes_key_configured'] ? self::SECRET_MASK : '';
            $data['callback_token'] = $data['callback_token_configured'] ? self::SECRET_MASK : '';
        } else {
            $data['suite_secret'] = WecomProviderCipher::decrypt((string)$suite->suite_secret_cipher);
            $data['encoding_aes_key'] = WecomProviderCipher::decrypt((string)$suite->encoding_aes_key_cipher);
        }
        unset($data['suite_secret_cipher'], $data['encoding_aes_key_cipher'], $data['suite_ticket']);
        return array_replace($this->defaults(), $data);
    }

    public function save(array $input): array
    {
        $channel = strtolower(trim((string)($input['channel_code'] ?? '')));
        if ($channel === '' || !preg_match('/^[a-z][a-z0-9_-]{1,39}$/', $channel)) {
            throw new CommonException('通道编码只能使用小写字母、数字、短横线和下划线，且必须以字母开头');
        }
        $id = max(0, (int)($input['id'] ?? 0));
        $existing = $id > 0 ? WecomProviderSuite::where('id', '=', $id)->findOrEmpty() : $this->byChannel($channel);
        if ($id > 0 && $existing->isEmpty()) throw new CommonException('企业微信服务商通道不存在');
        if ($existing->isEmpty()) $existing = new WecomProviderSuite();

        $oldSuiteId = $existing->isEmpty() ? '' : trim((string)$existing->suite_id);
        $oldProviderCorpId = $existing->isEmpty() ? '' : trim((string)$existing->provider_corp_id);
        $storedSecret = $existing->isEmpty() ? '' : WecomProviderCipher::decrypt((string)$existing->suite_secret_cipher);
        $storedAesKey = $existing->isEmpty() ? '' : WecomProviderCipher::decrypt((string)$existing->encoding_aes_key_cipher);
        $storedToken = $existing->isEmpty() ? '' : trim((string)$existing->callback_token);
        $suiteSecret = $this->unmask((string)($input['suite_secret'] ?? ''), $storedSecret);
        $aesKey = $this->unmask((string)($input['encoding_aes_key'] ?? ''), $storedAesKey);
        $callbackToken = $this->unmask((string)($input['callback_token'] ?? ''), $storedToken);

        $webBaseUrl = rtrim(trim((string)($input['web_base_url'] ?? '')), '/');
        if ($webBaseUrl !== '' && !$this->validHttpUrl($webBaseUrl)) throw new CommonException('SaaS 管理端域名格式不正确');
        $eventCallbackUrl = '';
        $authCallbackUrl = '';
        if ($webBaseUrl !== '') {
            // 回调地址是部署域名和通道编码的确定性结果，不接受旧表单值，避免换域名后回调仍指向旧系统。
            $eventCallbackUrl = $webBaseUrl . '/api/wecom/provider/event/' . rawurlencode($channel);
            $authCallbackUrl = $webBaseUrl . '/api/wecom/provider/authorize/complete/' . rawurlencode($channel);
        }
        if ($eventCallbackUrl !== '' && !$this->validHttpUrl($eventCallbackUrl)) throw new CommonException('事件回调地址格式不正确');
        if ($authCallbackUrl !== '' && !$this->validHttpUrl($authCallbackUrl)) throw new CommonException('授权回调地址格式不正确');

        $enabled = !empty($input['enabled']);
        $providerCorpId = trim((string)($input['provider_corp_id'] ?? ''));
        $suiteId = trim((string)($input['suite_id'] ?? ''));
        $miniappAppid = trim((string)($input['admin_miniapp_appid'] ?? ''));
        if ($enabled) {
            if ($providerCorpId === '' || $suiteId === '' || $suiteSecret === '' || $callbackToken === '' || $aesKey === '') {
                throw new CommonException('启用服务商通道前，请完整填写服务商 CorpID、SuiteID、SuiteSecret、Token 和 EncodingAESKey');
            }
            if ($webBaseUrl === '' || $eventCallbackUrl === '' || $authCallbackUrl === '') {
                throw new CommonException('启用服务商通道前，请完整填写当前 SaaS 域名和回调地址');
            }
            if ($miniappAppid === '' || !preg_match('/^wx[a-zA-Z0-9]{16}$/', $miniappAppid)) {
                throw new CommonException('请填写当前 SaaS 后台管理小程序的正确 AppID');
            }
            if (strlen($aesKey) !== 43) throw new CommonException('企业微信 EncodingAESKey 应为 43 位字符');
        }

        // 同一部署只允许一个启用通道，避免站点授权与小程序入口串线。
        if ($enabled) {
            WecomProviderSuite::where('id', '<>', (int)($existing->id ?? 0))->update(['status' => 'disabled', 'update_at' => time()]);
        }
        $data = [
            'channel_code' => $channel,
            'provider_corp_id' => $providerCorpId,
            'suite_id' => $suiteId,
            'suite_secret_cipher' => WecomProviderCipher::encrypt($suiteSecret),
            'callback_token' => $callbackToken,
            'encoding_aes_key_cipher' => WecomProviderCipher::encrypt($aesKey),
            'admin_miniapp_appid' => $miniappAppid,
            'admin_miniapp_name' => trim((string)($input['admin_miniapp_name'] ?? '后台管理端')),
            'web_base_url' => $webBaseUrl,
            'event_callback_url' => $eventCallbackUrl,
            'auth_callback_url' => $authCallbackUrl,
            'status' => $enabled ? 'enabled' : 'disabled',
            'last_error' => '',
            'update_at' => time(),
        ];
        $identityChanged = (int)($existing->id ?? 0) > 0
            && (($oldSuiteId !== '' && $oldSuiteId !== $suiteId)
                || ($oldProviderCorpId !== '' && $oldProviderCorpId !== $providerCorpId));
        if ($identityChanged) {
            // SuiteTicket、永久授权码和成员身份都属于旧服务商应用域，不能跨 Suite 复用。
            $data['suite_ticket'] = '';
            $data['suite_ticket_at'] = 0;
        }

        Db::transaction(function () use ($existing, $data, $identityChanged): void {
            if ((int)($existing->id ?? 0) > 0) {
                if ($identityChanged) $this->invalidateSuiteAuthorizations((int)$existing->id);
                $existing->save($data);
            } else {
                WecomProviderSuite::create(array_merge($data, ['create_at' => time()]));
            }
        });

        $credential = new WecomProviderCredentialService();
        if ($oldSuiteId !== '') {
            // 构造仅用于清除旧 SuiteID 缓存的临时模型，避免换 Suite 后旧 token 残留。
            $oldSuite = new WecomProviderSuite();
            $oldSuite->suite_id = $oldSuiteId;
            $credential->clearSuiteToken($oldSuite);
        }
        $savedSuite = $id > 0 ? WecomProviderSuite::where('id', '=', $id)->findOrEmpty() : $this->byChannel($channel);
        if (!$savedSuite->isEmpty()) $credential->clearSuiteToken($savedSuite);
        return $this->info(true);
    }

    public function siteStatus(int $siteId): array
    {
        $suite = $this->active();
        $base = [
            'configured' => !$suite->isEmpty() ? 1 : 0,
            'status' => $suite->isEmpty() ? 'not_configured' : 'not_authorized',
            'channel_code' => $suite->isEmpty() ? '' : (string)$suite->channel_code,
            'corp_name' => '',
            'agent_id' => 0,
            'authorized_at' => 0,
            'last_error' => $suite->isEmpty() ? '平台尚未配置企业微信服务商应用' : '',
            'admin_miniapp_appid' => $suite->isEmpty() ? '' : $this->maskAppid((string)$suite->admin_miniapp_appid),
            'admin_miniapp_name' => $suite->isEmpty() ? '' : (string)$suite->admin_miniapp_name,
            'suite_ticket_at' => $suite->isEmpty() ? 0 : (int)$suite->suite_ticket_at,
        ];
        if ($suite->isEmpty() || $siteId <= 0) return $base;

        $authorization = WecomCorpAuthorization::where([
            ['site_id', '=', $siteId],
            ['provider_suite_id', '=', (int)$suite->id],
        ])->findOrEmpty();
        if ($authorization->isEmpty()) return $base;
        return array_replace($base, [
            'status' => (string)$authorization->status,
            'corp_name' => (string)$authorization->corp_name,
            'agent_id' => (int)$authorization->agent_id,
            'authorized_at' => (int)$authorization->authorized_at,
            'last_error' => (string)$authorization->last_error,
        ]);
    }

    private function defaults(): array
    {
        return [
            'id' => 0, 'enabled' => 0, 'channel_code' => 'default', 'provider_corp_id' => '',
            'suite_id' => '', 'suite_secret' => '', 'callback_token' => '', 'encoding_aes_key' => '',
            'admin_miniapp_appid' => '', 'admin_miniapp_name' => '后台管理端', 'web_base_url' => '',
            'event_callback_url' => '', 'auth_callback_url' => '', 'suite_ticket_at' => 0,
            'suite_secret_configured' => 0, 'callback_token_configured' => 0,
            'encoding_aes_key_configured' => 0, 'last_error' => '',
        ];
    }

    private function unmask(string $value, string $stored): string
    {
        $value = trim($value);
        return $value === '' || $value === self::SECRET_MASK ? $stored : $value;
    }

    private function validHttpUrl(string $value): bool
    {
        $scheme = strtolower((string)parse_url($value, PHP_URL_SCHEME));
        $host = strtolower((string)parse_url($value, PHP_URL_HOST));
        if (filter_var($value, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) return false;
        return $scheme === 'https' || in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    private function maskAppid(string $appid): string
    {
        $appid = trim($appid);
        if (strlen($appid) <= 8) return $appid;
        return substr($appid, 0, 5) . str_repeat('*', max(4, strlen($appid) - 9)) . substr($appid, -4);
    }

    private function invalidateSuiteAuthorizations(int $suiteRecordId): void
    {
        $authorizations = WecomCorpAuthorization::where('provider_suite_id', '=', $suiteRecordId)
            ->field('id,site_id')->select()->toArray();
        if ($authorizations === []) return;
        $authorizationIds = array_values(array_unique(array_map('intval', array_column($authorizations, 'id'))));
        $siteIds = array_values(array_unique(array_map('intval', array_column($authorizations, 'site_id'))));
        $reason = '平台企业微信服务商应用已变更，请重新授权并重新绑定接收员工';

        WecomCorpAuthorization::whereIn('id', $authorizationIds)->update([
            'status' => 'error',
            'last_error' => $reason,
            'update_at' => time(),
        ]);
        WecomStaffBinding::whereIn('corp_authorization_id', $authorizationIds)->update([
            'status' => 0,
            'verified_at' => 0,
            'update_at' => time(),
        ]);
        WecomMessageLog::whereIn('site_id', $siteIds)
            ->whereIn('status', ['pending', 'failed'])
            ->update([
                'status' => 'skipped',
                'next_retry_at' => 0,
                'error_message' => $reason,
                'update_at' => time(),
            ]);
    }
}
