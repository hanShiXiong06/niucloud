<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

/**
 * 可选的公共 HTTP 传输扩展。
 * 事件只登记处理器，绝不在广播阶段请求快递；同一供应商只允许一个处理器。
 * 回收仍负责预约契约、幂等、账号快照、回调及通知，不依赖任何特定插件。
 */
final class ExpressTransportRegistry
{
    public static function resolve(string $provider, int $siteId, string $environment): ?callable
    {
        if (!function_exists('event')) return null;
        $responses = (array)event('HsxExpressTransportRegistry', [
            'site_id' => $siteId,
            'environment' => $environment,
        ]);
        $selected = null;
        foreach ($responses as $response) {
            if (!is_array($response) || !isset($response['transports'][$provider])) continue;
            if ($selected !== null) {
                throw new \RuntimeException('同一快递服务注册了多个传输处理器，未执行预约，请联系管理员');
            }
            $handler = $response['transports'][$provider];
            if (!is_string($handler) || !is_callable([$handler, 'send'])) {
                throw new \RuntimeException('快递传输扩展未就绪，未执行预约，请检查插件文件');
            }
            $selected = [$handler, 'send'];
        }
        return $selected;
    }
}
