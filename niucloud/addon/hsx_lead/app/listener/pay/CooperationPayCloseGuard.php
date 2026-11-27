<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\pay;

use addon\hsx_lead\app\service\cooperation\{PaymentCloseScope,PaymentService};
use core\exception\CommonException;

/** The existing PayClose event executes inside the core's local transaction.
 * Veto blind cashier/PayReturnTo fallbacks; only plugin-verified closure commits.
 * Throw, do not return false: current check_event_result() treats any array
 * (including event results [false]) as success. Only an exception rolls back
 * CorePayService::payClose's already-written CANCEL status on this version.
 */
final class CooperationPayCloseGuard
{
    public function handle(array $event): bool
    {
        if(($event['trade_type']??'')!==PaymentService::TRADE_TYPE)return true;
        if(!PaymentCloseScope::allowed((int)($event['site_id']??0),(string)($event['out_trade_no']??'')))throw new CommonException('此付款需先核对渠道状态，请在支付订单中核对后取消，当前未关闭原支付');
        return true;
    }
}
