<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;

use addon\hsx_lead\app\service\virtualpay\VirtualPayOrderService;
use core\base\BaseJob;

/** Query original trades and replay persisted authenticated external refunds.
 * No new collection, outbound refund or payment fallback is dispatched. */
final class ReconcileVirtualPayments extends BaseJob
{
    public function doJob($data = []): string
    {
        $result = json_encode((new VirtualPayOrderService())->tick(30), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        \think\facade\Log::info('会员虚拟支付原单核对：'.$result);
        return $result;
    }
}
