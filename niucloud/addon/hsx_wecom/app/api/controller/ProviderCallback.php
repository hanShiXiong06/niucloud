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
        try {
            $result = (new WecomProviderCallbackService())->serve(
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
            Log::error('企业微信服务商回调处理失败', ['channel' => $channel, 'message' => $e->getMessage()]);
            return response('failed', 500)->header(['Content-Type' => 'text/plain;charset=utf-8']);
        }
    }
}
