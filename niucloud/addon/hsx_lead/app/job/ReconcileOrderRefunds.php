<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\job;
use addon\hsx_lead\app\service\cooperation\{MembershipDepositService,PaymentRefundService};
use core\base\BaseJob;

/** Query persisted original refunds only. Never initiates or retries a transfer. */
final class ReconcileOrderRefunds extends BaseJob
{
    public function doJob($data = []): string
    {
        $orders = PaymentRefundService::tick(20);
        $deposits = MembershipDepositService::tick(20);
        $result = json_encode([
                'orders' => $orders,
                'deposits' => $deposits,
                'message' => '仅核对已提交的原退款；attention为待核对，不代表再次退款或退款失败'
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        \think\facade\Log::info('商户协作原退款补偿结果：'.$result);
        return $result;
    }
}
