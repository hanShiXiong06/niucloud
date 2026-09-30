<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\model\WecomCorpAuthorization;
use addon\hsx_wecom\app\model\WecomProviderSuite;
use addon\hsx_wecom\app\support\WecomProviderCipher;
use core\exception\CommonException;
use EasyWeChat\Kernel\Encryptor;
use EasyWeChat\Kernel\Support\Xml;
use Nyholm\Psr7\ServerRequest;

final class WecomProviderCallbackService
{
    /** @return array{body:string,status:int,headers:array} */
    public function serve(string $channel, string $method, string $uri, string $body): array
    {
        return $this->receive($channel, $method, $uri, $body, false);
    }

    /** 数据回调只确认安全接收，不保存消息正文、不运行聊天业务。 */
    public function serveData(string $channel, string $method, string $uri, string $body): array
    {
        return $this->receive($channel, $method, $uri, $body, true);
    }

    /** @return array{body:string,status:int,headers:array} */
    private function receive(string $channel, string $method, string $uri, string $body, bool $data): array
    {
        $suite = (new WecomProviderConfigService())->byChannel($channel);
        if ($suite->isEmpty() || !in_array((string)$suite->status, ['preparing', 'enabled'], true)) {
            throw new CommonException('企业微信服务商回调通道不存在或已停用');
        }
        $method = strtoupper($method);
        $query = (new ServerRequest($method, $uri))->getQueryParams();
        if ($method === 'GET') {
            // 创建应用时尚无 SuiteID / Secret；官方 URL 验证的接收方是服务商 CorpID。
            // preparing 仅开放此分支，不启用票据、授权、消息等业务入口。
            $echo = $this->requiredString($query, 'echostr');
            return $this->response($this->decrypt($suite, $query, $echo, trim((string)$suite->provider_corp_id)));
        }
        if ($method !== 'POST' || (string)$suite->status !== 'enabled') {
            throw new CommonException('企业微信通道当前仅允许 GET 回调地址验证');
        }
        if (array_key_exists('echostr', $query)) throw new CommonException('企业微信事件请求不能包含地址验证参数');
        $suiteId = trim((string)$suite->suite_id);
        if ($suiteId === '') throw new CommonException('企业微信服务商 SuiteID 未配置');

        $envelope = $this->parseXml($body);
        $encrypted = $this->requiredString($envelope, 'Encrypt');
        if ($data) {
            $corpId = $this->requiredString($envelope, 'ToUserName');
            $payload = $this->parseXml($this->decrypt($suite, $query, $encrypted, $corpId));
            $this->validateDataIdentity($suite, $envelope, $payload, $corpId);
            // 不写 callback_event：接收成功不代表处理了用户消息，也不应持久化其正文。
            return $this->response('success');
        }

        $payload = $this->parseXml($this->decrypt($suite, $query, $encrypted, $suiteId));
        $this->assertOptionalIdentity($envelope, 'ToUserName', $suiteId);
        $this->assertOptionalIdentity($payload, 'ToUserName', $suiteId);
        if ($this->requiredString($payload, 'SuiteId') !== $suiteId) {
            throw new CommonException('企业微信指令回调 SuiteID 与当前通道不一致');
        }
        $this->requiredString($payload, 'InfoType');
        if ($this->optionalString($payload, 'MsgType') !== '') {
            throw new CommonException('企业微信用户消息必须发送到数据回调地址');
        }
        (new WecomProviderAuthorizationService())->handleCallbackEvent($suite, $payload);
        return $this->response('success');
    }

    private function validateDataIdentity(WecomProviderSuite $suite, array $envelope, array $payload, string $corpId): void
    {
        if ($this->requiredString($payload, 'ToUserName') !== $corpId) {
            throw new CommonException('企业微信数据回调内外层企业身份不一致');
        }
        $this->requiredString($payload, 'MsgType');
        if ($this->optionalString($payload, 'InfoType') !== '') {
            throw new CommonException('企业微信服务商指令必须发送到指令回调地址');
        }
        $this->assertOptionalIdentity($payload, 'SuiteId', trim((string)$suite->suite_id));
        $this->assertOptionalIdentity($payload, 'AuthCorpId', $corpId);
        $authorization = WecomCorpAuthorization::where([
            ['provider_suite_id', '=', (int)$suite->id],
            ['auth_corpid', '=', $corpId],
            ['status', '=', 'authorized'],
        ])->findOrEmpty();
        if ($authorization->isEmpty()) {
            throw new CommonException('企业微信数据回调企业尚未授权当前服务商通道');
        }
        foreach ([$envelope, $payload] as $part) {
            $agentId = $this->optionalString($part, 'AgentID');
            if ($agentId !== '' && (!ctype_digit($agentId) || (int)$agentId <= 0 || (int)$agentId !== (int)$authorization->agent_id)) {
                throw new CommonException('企业微信数据回调应用身份与企业授权不一致');
            }
        }
    }

    private function decrypt(WecomProviderSuite $suite, array $query, string $encrypted, string $receiveId): string
    {
        $token = trim((string)$suite->callback_token);
        $key = WecomProviderCipher::decrypt((string)$suite->encoding_aes_key_cipher);
        if ($receiveId === '' || $token === '' || strlen($key) !== 43 || strlen(base64_decode($key . '=', true) ?: '') !== 32) {
            throw new CommonException('企业微信回调验证配置不完整');
        }
        $signature = $this->requiredString($query, 'msg_signature');
        $timestamp = $this->requiredString($query, 'timestamp');
        $nonce = $this->requiredString($query, 'nonce');
        if (!preg_match('/^[a-f0-9]{40}$/', $signature) || !ctype_digit($timestamp)) {
            throw new CommonException('企业微信回调签名参数格式不正确');
        }
        $ciphertext = base64_decode($encrypted, true);
        if ($ciphertext === false || strlen($ciphertext) < 32 || strlen($ciphertext) % 16 !== 0) {
            throw new CommonException('企业微信回调密文格式不正确');
        }
        // OpenWork 的默认 Encryptor 不校验 receiveId；直接复用 SDK 核心加解密并指定接收身份。
        return (new Encryptor($receiveId, $token, $key, $receiveId))->decrypt($encrypted, $signature, $nonce, $timestamp);
    }

    private function parseXml(string $body): array
    {
        if ($body === '' || strlen($body) > 1024 * 1024 || preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i', $body)) {
            throw new CommonException('企业微信回调 XML 格式不正确');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $payload = Xml::parse($body);
            if (!is_array($payload)) throw new CommonException('企业微信回调 XML 内容为空');
            return $payload;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function requiredString(array $values, string $name): string
    {
        $value = $this->optionalString($values, $name);
        if ($value === '') throw new CommonException('企业微信回调缺少必要参数');
        return $value;
    }

    private function optionalString(array $values, string $name): string
    {
        if (!array_key_exists($name, $values)) return '';
        if (!is_scalar($values[$name])) throw new CommonException('企业微信回调参数格式不正确');
        return trim((string)$values[$name]);
    }

    private function assertOptionalIdentity(array $payload, string $name, string $expected): void
    {
        $actual = $this->optionalString($payload, $name);
        if ($actual !== '' && $actual !== $expected) throw new CommonException('企业微信回调身份与当前通道不一致');
    }

    /** @return array{body:string,status:int,headers:array} */
    private function response(string $body): array
    {
        return [
            'body' => $body,
            'status' => 200,
            'headers' => ['Content-Type' => ['text/plain;charset=utf-8']],
        ];
    }
}
