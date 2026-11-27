<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\pay;

use addon\hsx_lead\app\service\virtualpay\VirtualPayOrderService;
use app\service\core\weapp\CoreWeappAuthenticatedMessage;
use core\exception\CommonException;
use Nyholm\Psr7\Response;

/** Keep the framework's single WeChat callback URL and all unrelated messages. */
final class VirtualPayMessage
{
    public function handle(array $params): ?Response
    {
        $authenticated = $params['authenticated_message'] ?? null;
        $message = $authenticated instanceof CoreWeappAuthenticatedMessage ? $authenticated->message() : ($params['message'] ?? []);
        $event = is_array($message) && is_string($message['Event'] ?? null) ? $message['Event'] : '';
        if (!in_array($event, ['xpay_goods_deliver_notify', 'xpay_refund_notify'], true))return null;
        $site = (int) ($params['site_id'] ?? 0);
        try {
            if (!$authenticated instanceof CoreWeappAuthenticatedMessage || !$authenticated->belongsTo($site))throw new CommonException('虚拟支付消息尚未通过本站小程序原生认证');
            // The trusted context captures only signed INNER fields. The SDK
            // merged message may contain untrusted outer-envelope fields.
            $message['AppId'] = $authenticated->appId();
            (new VirtualPayOrderService())->handleEvent($site, $message);
        } catch (\Throwable $error) {
            // Never log raw envelopes, openids, AppKeys or login/session data.
            \think\facade\Log::warning('会员虚拟支付回调未确认完成，site_id='.$site.'，event='.$event);
            throw new CommonException('虚拟支付通知暂未确认完成');
        }
        return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], 'success');
    }
}
