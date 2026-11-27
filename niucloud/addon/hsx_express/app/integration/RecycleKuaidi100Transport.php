<?php
declare(strict_types=1);

namespace addon\hsx_express\app\integration;

use addon\hsx_express\app\service\core\Kuaidi100Client;

/**
 * 回收传入已校验的原业务账号。本适配器不读取商城配置、不更换账号、不修改回收单。
 * 未安装/未授权物流插件时，回收保持原有传输；启用后失败不会偷偷重试原传输。
 */
final class RecycleKuaidi100Transport
{
    public static function send(array $config, string $method, array $params): array
    {
        if (($config['environment'] ?? '') !== 'production') {
            throw new \InvalidArgumentException('公共物流传输只处理正式渠道，测试预约仍使用回收沙箱');
        }
        $endpoints = [
            'online' => 'https://poll.kuaidi100.com/order/borderapi.do',
            'offline' => 'https://order.kuaidi100.com/order/corderapi.do',
        ];
        $endpoint = $endpoints[$config['mode'] ?? ''] ?? '';
        if ($endpoint === '') throw new \InvalidArgumentException('未识别回收寄件模式，未发起请求');
        return (new Kuaidi100Client())->request($endpoint, $method, $params, [
            'key' => (string)($config['api_key'] ?? ''),
            'secret' => (string)($config['secret'] ?? ''),
            'timeout' => max(3, min(60, (int)($config['timeout'] ?? 30))),
        ]);
    }
}
