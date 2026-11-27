<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace app\service\core\weapp;

use core\base\BaseCoreService;
use EasyWeChat\Kernel\Exceptions\BadRequestException;
use EasyWeChat\Kernel\Exceptions\InvalidArgumentException;
use EasyWeChat\Kernel\Exceptions\InvalidConfigException;
use ReflectionException;
use Symfony\Component\HttpFoundation\Response;

/**
 * 微信服务提供
 * Class CoreWechatServeService
 * @package app\service\core\wechat
 */
class CoreWeappServeService extends BaseCoreService
{
    /**
     * 事件推送
     * @param int $site_id
     * @return Response
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws InvalidConfigException
     */
    public function serve(int $site_id)
    {
        $app = CoreWeappService::app($site_id);
        $message_security = CoreWeappMessageSecurity::configuration($site_id);
        $authenticated_encryptor = null;
        if ($message_security['encrypted_ready'] && hash_equals($message_security['app_id'], $app->getAccount()->getAppId())) {
            $authenticated_encryptor = new CoreWeappMessageEncryptor(
                $app->getEncryptor(), $site_id, $app->getAccount()->getAppId(),
                $message_security['original_id'], $message_security['source']
            );
            $app->setEncryptor($authenticated_encryptor);
        }
        $server = $app->getServer();
        $server->with(function($message, \Closure $next)  use ($site_id, $authenticated_encryptor){
            // Optional application consumers must perform their own business
            // checks. A PSR response allows an acknowledgement without turning
            // it into a text-message reply; other messages keep the SDK chain.
            foreach (event('WeappMessage', [
                'site_id' => $site_id, 'message' => $message->toArray(),
                // Legacy consumers keep their message; sensitive consumers
                // require the native SDK authentication result, never a flag.
                'authenticated_message' => $authenticated_encryptor?->authenticatedMessage()
            ]) as $result) {
                if ($result instanceof \Psr\Http\Message\ResponseInterface) return $result;
            }
            return $next($message);
        });
        $response = $server->serve();
        return $response;

    }

}
