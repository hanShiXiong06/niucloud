<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\dict\pay\PayDict;
use core\exception\CommonException;
use think\facade\Db;

/** Query/recover the exact original payment. Never prepares, debits or closes it. */
final class PaymentOrderCheckService
{
    private ?\Closure $providerFactory;
    public function __construct(private Context $context,?callable $providerFactory=null)
    {
        $this->providerFactory=$providerFactory===null?null:\Closure::fromCallable($providerFactory);
    }
    public function check(int $id,bool $admin=false): array
    {
        if($admin){
            Store::permission($this->context,'finance');$this->context->permission('hsx_lead_cooperation_order_check');
            if(!$this->context->ownership()->scope()['all_data'])throw new CommonException('核对支付单还需要全站业务查看权限');
        }else (new PaymentService($this->context))->order($id);
        $site=$this->context->site;$db=Db::connect();
        if($db->getPdo()->inTransaction())throw new CommonException('付款核对需要独立执行，请稍后重试');
        $scope=(string)$db->getConfig('database').':'.(string)$db->getConfig('prefix');
        $key='lead_coop_pay:'.substr(hash('sha256',$scope.':'.$site.':'.$id),0,40);
        $locked=$db->query('SELECT GET_LOCK(:lock_key, 3) AS acquired',['lock_key'=>$key],true);
        if((int)($locked[0]['acquired']??0)!==1)throw new CommonException('正在核对这笔付款，请稍后重试');
        try{
            $row=Store::row('coop_order',$site,$id);
            if(!$admin&&(int)$row['member_id']!==$this->context->memberId)throw new CommonException('只能核对本人创建的付款订单');
            if($row['status']==='closed')return PaymentService::presentOrder($row);
            $pays=Db::name('pay')->where('site_id',$site)->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',$id)
                ->where('status','<>',PayDict::STATUS_CANCEL)->order('id')->select()->toArray();
            if(count($pays)>1)throw new CommonException('存在多笔原付款记录，请联系平台核对；未再次扣款');
            $result=['state'=>'ready','message'=>'尚未发起付款'];
            foreach($pays as $pay){
                if(($pay['main_type']??'')!==PayDict::MEMBER||(int)$pay['main_id']!==(int)$row['member_id']||Rules::cents((string)$pay['money'])!==Rules::cents((string)$row['amount']))
                    throw new CommonException('原付款归属或金额不一致，请联系平台核对');
                if((int)$pay['status']===(int)PayDict::STATUS_FINISH){
                    $result=(new PaymentGatewayReconciler($this->providerFactory))->recoverLocalReceipt($site,$pay);
                }elseif((int)$pay['status']===(int)PayDict::STATUS_ING){
                    $rule=Data::decode($row['rule_json']);
                    $reconciler=new PaymentGatewayReconciler($this->providerFactory);
                    $result=($rule['requires_manual_review']??'')==='gateway_payment_receipt_review'
                        ?$reconciler->recoverPaid($site,$pay):$reconciler->check($site,$pay);
                }elseif((int)$pay['status']!==(int)PayDict::STATUS_WAIT){
                    $result=['state'=>'uncertain','message'=>'原付款状态尚待核对，请勿重复付款'];
                }
            }
            $view=PaymentService::presentOrder(Store::row('coop_order',$site,$id));
            if(!empty($view['requires_manual_review']))$result=['state'=>'attention','message'=>$view['review_message']];
            elseif($view['status']==='paid')$result=['state'=>'paid','message'=>$view['purpose']==='membership'?'付款已确认，会员权益已更新':'付款已确认'];
            elseif($result['state']==='paid')$result=['state'=>'paid_pending','message'=>'已核实收款，正在恢复业务结果，请勿重复付款'];
            // CLOSED describes the native attempt, not the business order. The
            // explicit resume action revalidates expiry/config before checkout.
            elseif($result['state']==='closed')$result=['state'=>'ready','message'=>'原付款已结束，可继续付款或取消订单'];
            $view['payment_recovery']=$result;
            if(!in_array($result['state'],['ready','paid'],true))$view['can_pay']=false;
            if($admin)$this->context->audit('coop_order',$id,'payment_checked',['state'=>$result['state'],'message'=>$result['message']]);
            return $view;
        }finally{
            try{$db->query('SELECT RELEASE_LOCK(:lock_key) AS released',['lock_key'=>$key],true);}catch(\Throwable $ignored){}
        }
    }
}
