<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use app\dict\pay\PayDict;
use addon\hsx_lead\app\service\virtualpay\VirtualPaySettings;

/** One action contract shared by member and operator UIs. Never payment authority. */
final class PaymentOrderActions
{
    public static function present(array $order,?array $pay,?array $source,array $view): array
    {
        $state='ready';$message='';$check=false;
        $virtual=($pay['type']??'')===VirtualPaySettings::TYPE;
        $sourceState=(string)($source['status']??'');
        $processing=(int)($pay['status']??-1)===(int)PayDict::STATUS_ING;
        $sourceUnresolved=$virtual&&in_array($sourceState,['prepared','pending'],true);
        $receipt=(int)($pay['status']??-1)===(int)PayDict::STATUS_FINISH
            ||in_array($sourceState,['receipt_pending','delivery_pending','paid'],true);
        if(!empty($view['requires_manual_review'])||$sourceState==='attention'){
            $state='attention';$message=trim((string)($view['review_message']??''))?:'付款或权益存在待核对事项，请联系平台核对原订单';
        }elseif(($order['status']??'')==='paid'){
            $state='paid';$message='付款已确认';
        }elseif(($order['status']??'')==='closed'){
            $state='closed';$message='订单已关闭';
        }elseif($receipt){
            $state='paid_pending';$message='已确认收款，正在恢复开通，请勿重复付款';$check=true;
        }elseif($processing||$sourceUnresolved){
            $state='pending';$check=true;
            $message=$virtual?'正在核对微信付款结果。暂不重复扣款；已扣款会自动恢复开通。':'本次付款尚未完成，可核对并继续付款或更换已启用的支付方式';
        }
        $view['payment_state']=$state;
        $view['payment_message']=$message;
        $view['payment_check_required']=$check;
        $view['payment_check_after_seconds']=5;
        $view['can_check_payment']=($processing||$receipt||$sourceUnresolved)&&($order['status']??'')!=='closed';
        if(in_array($state,['attention','paid_pending','pending'],true))$view['can_pay']=false;
        if($state==='paid_pending'||$state==='attention'||($virtual&&($processing||$sourceUnresolved))){
            $view['can_close']=false;
            $view['close_requires_check']=true;
        }
        return $view;
    }
}
