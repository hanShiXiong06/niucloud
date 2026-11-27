<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\api\controller;

use addon\hsx_wecom\app\service\core\WecomProviderCallbackService;
use core\base\BaseController;
use think\facade\Log;
use think\Response;

final class ProviderCallback extends BaseController
{
    public function event(string $channel): Response
    {
        return $this->receive($channel, false);
    }

    public function data(string $channel): Response
    {
        return $this->receive($channel, true);
    }

    private function receive(string $channel, bool $data): Response
    {
        try {
            $method = $data ? 'serveData' : 'serve';
            $result = (new WecomProviderCallbackService())->{$method}(
                $channel,
                (string)$this->request->method(),
                (string)$this->request->url(true),
                (string)$this->request->getContent()
            );
            $response = response($result['body'], $result['status']);
            foreach ($result['headers'] as $name => $values) {
                $response->header([$name => implode(', ', (array)$values)]);
            }
            return $response;
        } catch (\Throwable $e) {
            // 不记录请求正文、查询串或第三方异常原文，避免泄露消息、签名和密钥。
            Log::error('企业微信服务商回调处理失败', ['channel' => $channel, 'callback' => $data ? 'data' : 'event', 'error_type' => get_class($e)]);
            return response('failed', 500)->header(['Content-Type' => 'text/plain;charset=utf-8']);
        }
    }
}
