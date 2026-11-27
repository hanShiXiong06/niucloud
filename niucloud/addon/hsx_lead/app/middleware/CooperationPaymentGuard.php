<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\middleware;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\PaymentService;
use addon\hsx_lead\app\service\cooperation\PaymentOrderRecovery;
use addon\hsx_lead\app\service\cooperation\MembershipVirtualPayAdapter;
use addon\hsx_lead\app\service\cooperation\Store;
use addon\hsx_lead\app\service\virtualpay\VirtualPaySettings;
use app\Request;
use app\dict\pay\PayDict;
use app\service\core\pay\CorePayChannelService;
use core\exception\CommonException;
use think\facade\Db;
use Closure;

/** Plugin-only extension of the existing cashier; no route replacement or payment bypass. */
final class CooperationPaymentGuard
{
    private ?Closure $providerFactory;

    /** PHP-only provider double for isolated tests, never supplied by HTTP input. */
    public function __construct(?callable $providerFactory=null)
    {
        $this->providerFactory=$providerFactory===null?null:Closure::fromCallable($providerFactory);
    }

    public function handle(Request $request,Closure $next)
    {
        $controller=strtolower(str_replace(['\\','/'],'.',$request->controller()));
        $action=strtolower($request->action());
        if(app()->http->getName()==='adminapi'&&in_array($controller,['pay.payrefund','app.adminapi.controller.pay.payrefund'],true)&&$action==='transfer'){
            $number=$request->param('refund_no','');
            if(is_string($number)&&$number!==''&&Db::name('pay_refund')->where('site_id',(int)$request->siteId())->where('refund_no',$number)->where('trade_type',PaymentService::TRADE_TYPE)->find())throw new CommonException('请从商户协作支付订单或押金退还中处理原退款，不能从通用入口重复提交');
        }
        if (app()->http->getName()!=='api'||!in_array($controller,['pay.pay','app.api.controller.pay.pay'],true)||!in_array($action,['pay','info','friendspayinfo','getpaytype','close'],true)) return $next($request);
        $site=(int)$request->siteId();$type=(string)$request->param('trade_type','');$id=$request->param('trade_id',0);
        if ($action==='close') {
            $no=$request->param('out_trade_no','');
            if (is_string($no)&&$no!=='') {
                $pay=Db::name('pay')->where('site_id',$site)->where('out_trade_no',$no)->field('trade_type,trade_id,main_id')->find();
                if ($pay&&$pay['trade_type']===PaymentService::TRADE_TYPE) {
                    // The framework closes by out_trade_no, NOT by the submitted trade_id.
                    // Never let an owned id authorize closing somebody else's payment.
                    if ((int)$request->memberId()!==(int)$pay['main_id']) throw new CommonException('不能关闭其他会员的付款单');
                    $type=PaymentService::TRADE_TYPE;$id=$pay['trade_id'];
                } elseif ($type===PaymentService::TRADE_TYPE) throw new CommonException('付款流水与协作业务不对应，未执行关闭');
            } elseif ($type===PaymentService::TRADE_TYPE) {
                throw new CommonException('请使用原付款流水核对关闭，不接受仅凭业务编号关闭');
            }
        }
        if ($type!==PaymentService::TRADE_TYPE) return $next($request);
        if ($site<=0||(int)$request->memberId()<=0) throw new CommonException('请先使用当前商户的会员账号登录');
        if ($action==='friendspayinfo') throw new CommonException('商户协作由当前门店自行付款，不支持找朋友代付');
        if ($action==='getpaytype') {
            $order=null;
            if(ctype_digit((string)$id)&&(int)$id>0){(new PaymentService(new Context($site,0,(int)$request->memberId())))->order((int)$id);$order=Store::row('coop_order',$site,(int)$id);}
            return $this->filterChannels($next($request),$site,$order,$request->getChannel());
        }
        if ((!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$id)) throw new CommonException('支付业务编号无效，请返回详情');
        $context=new Context($site,0,(int)$request->memberId());$service=new PaymentService($context);
        $service->order((int)$id);$order=Store::row('coop_order',$site,(int)$id);
        $virtualSelected=false;
        if ($action==='pay') {
            if (!$request->isPost()) throw new CommonException('请通过确认付款按钮提交，当前未发起支付');
            $selected=$request->param('type');
            $allowed=MembershipVirtualPayAdapter::allowedTypes($site,$order,$request->getChannel());
            if (!in_array($selected,$allowed,true)) throw new CommonException('当前付款方式已更新，请重新打开付款窗口');
            $virtualSelected=$selected===VirtualPaySettings::TYPE;
            if($virtualSelected){
                if($request->getChannel()!=='weapp')throw new CommonException('请在微信小程序内完成本业务付款');
                VirtualPaySettings::get($site,true);
                $code=$request->param('login_code','');if(!is_string($code)||$code===''||strlen($code)>256)throw new CommonException('请重新点击微信支付获取登录凭据');
            }
            $channels=(new CorePayChannelService())->getAllowPayTypeByChannel($site,$request->getChannel(),PaymentService::TRADE_TYPE);
            if (!in_array($selected,array_column($channels,'key'),true)) throw new CommonException('当前付款方式暂不可用，请选择其他付款方式');
        }
        $connection=Db::connect();$scope=(string)$connection->getConfig('database').':'.(string)$connection->getConfig('prefix');
        $key='lead_coop_pay:'.substr(hash('sha256',$scope.':'.$site.':'.$id),0,40);
        $locked=$connection->query('SELECT GET_LOCK(:lock_key, 3) AS acquired',['lock_key'=>$key],true);
        if ((int)($locked[0]['acquired']??0)!==1) throw new CommonException('该笔支付正在处理，请稍后刷新核对，不要重复付款');
        try {
            if ($action==='pay') {
                // A locally cancelled WeChat sheet leaves an ING receipt. On an
                // explicit retry/switch, reconcile that receipt before allowing
                // the framework to create a new prepay for this same order.
                // Do not wrap this in an outer transaction: a discovered real
                // receipt must remain committed even when we stop a second pay.
                $recovered=(new PaymentOrderRecovery($context,$this->providerFactory))->resume((int)$id);
                $this->assertRecovered($recovered);
                $service->assertPayable((int)$id,$virtualSelected);
            } elseif (in_array($action,['close','info'],true)) $service->order((int)$id);
            else $service->assertPayable((int)$id);
            // A balance debit and its PaySuccess business hooks are one local
            // transaction. Keep this AFTER recovery has committed, so a failed
            // balance attempt cannot roll back a verified external receipt.
            $response=$action==='pay'&&$request->param('type')===PayDict::BALANCEPAY
                ?Db::transaction(function()use($service,$id,$next,$request){$service->assertPayable((int)$id);return $next($request);})
                :$next($request);
            return $action==='info'?$this->filterChannels($response,$site,$order,$request->getChannel()):$response;
        } finally {
            try {$connection->query('SELECT RELEASE_LOCK(:lock_key) AS released',['lock_key'=>$key],true);} catch (\Throwable $ignored) { /* MySQL releases it when the connection closes. */ }
        }
    }
    private function assertRecovered(array $order): void
    {
        if (($order['status']??'')==='paid') throw new CommonException('付款已成功，请关闭付款窗口查看结果，无需再次支付');
        $state=(string)($order['payment_recovery']['state']??'');
        if ($state==='paid_pending'||!empty($order['requires_manual_review'])) throw new CommonException('正在更新本次付款结果，请稍后查看订单，不要重复支付');
        if (($order['status']??'')==='closed') throw new CommonException('本次付款已关闭，请关闭付款窗口，重新确认费用后支付');
        if ($state==='uncertain') throw new CommonException('正在确认上次付款结果，请稍后再次点击付款，暂勿重复支付');
        if ($state!=='ready'||($order['status']??'')!=='unpaid'||($order['can_pay']??false)!==true) {
            $message=(string)($order['payment_recovery']['message']??'');
            throw new CommonException($message!==''?$message:'暂时无法继续付款，请稍后再次点击付款');
        }
    }
    private function filterChannels($response,int $site,?array $order,string $terminal)
    {
        if (!is_object($response)||!method_exists($response,'getData')||!method_exists($response,'data')) return $response;
        $body=$response->getData();if (!is_array($body)||!is_array($body['data']??null)) return $response;
        if (isset($body['data']['pay_type_list'])) $body['data']['pay_type_list']=MembershipVirtualPayAdapter::channels($site,$order,$body['data']['pay_type_list'],$terminal);
        elseif ($body['data']===[]||array_keys($body['data'])===range(0,count($body['data'])-1)) $body['data']=MembershipVirtualPayAdapter::channels($site,$order,$body['data'],$terminal);
        return $response->data($body);
    }
}
