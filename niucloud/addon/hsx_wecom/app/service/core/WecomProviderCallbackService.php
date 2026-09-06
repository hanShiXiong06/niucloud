<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\service\core;

use addon\hsx_wecom\app\support\WecomProviderCipher;
use core\exception\CommonException;
use EasyWeChat\OpenWork\Application;
use EasyWeChat\OpenWork\Message;
use Nyholm\Psr7\ServerRequest;

final class WecomProviderCallbackService
{
    /** @return array{body:string,status:int,headers:array} */
    public function serve(string $channel, string $method, string $uri, string $body): array
    {
        $suite = (new WecomProviderConfigService())->byChannel($channel);
        if ($suite->isEmpty() || (string)$suite->status !== 'enabled') {
            throw new CommonException('企业微信服务商回调通道不存在或未启用');
        }
        $application = new Application([
            'corp_id' => (string)$suite->provider_corp_id,
            'provider_secret' => '',
            'suite_id' => (string)$suite->suite_id,
            'suite_secret' => WecomProviderCipher::decrypt((string)$suite->suite_secret_cipher),
            'token' => (string)$suite->callback_token,
            'aes_key' => WecomProviderCipher::decrypt((string)$suite->encoding_aes_key_cipher),
        ]);
        $application->setRequest(new ServerRequest(strtoupper($method), $uri, [], $body));
        $server = $application->getServer();
        $server->with(function (Message $message, \Closure $next) use ($suite): mixed {
            (new WecomProviderAuthorizationService())->handleCallbackEvent($suite, $message->toArray());
            return $next($message);
        });
        $response = $server->serve();
        return [
            'body' => (string)$response->getBody(),
            'status' => $response->getStatusCode(),
            'headers' => $response->getHeaders(),
        ];
    }
}
