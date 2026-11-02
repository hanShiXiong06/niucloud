<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\middleware;
use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\service\cooperation\PaymentService;
use app\Request;
use app\dict\pay\PayDict;
use core\exception\CommonException;
use think\facade\Db;
use Closure;

/** Plugin-only extension of the existing cashier; no route replacement or payment bypass. */
final class CooperationPaymentGuard
{
    public function handle(Request $request,Closure $next)
    {
        $controller=strtolower(str_replace(['\\','/'],'.',$request->controller()));
        $action=strtolower($request->action());
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
        if ($action==='getpaytype') return $this->filterChannels($next($request));
        if ((!is_int($id)&&!is_string($id))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$id)) throw new CommonException('支付业务编号无效，请返回详情');
        $service=new PaymentService(new Context($site,0,(int)$request->memberId()));
        $connection=Db::connect();$scope=(string)$connection->getConfig('database').':'.(string)$connection->getConfig('prefix');
        $key='lead_coop_pay:'.substr(hash('sha256',$scope.':'.$site.':'.$id),0,40);
        $locked=$connection->query('SELECT GET_LOCK(:lock_key, 3) AS acquired',['lock_key'=>$key],true);
        if ((int)($locked[0]['acquired']??0)!==1) throw new CommonException('该笔支付正在处理，请稍后刷新核对，不要重复付款');
        try {
            if (in_array($action,['close','info'],true)) $service->order((int)$id);
            else $service->assertPayable((int)$id);
            if ($action==='pay'&&!in_array($request->param('type'),[PayDict::WECHATPAY,PayDict::ALIPAY,PayDict::BALANCEPAY],true)) throw new CommonException('协作付费仅支持本站已开通的微信、支付宝或会员余额支付，不支持凭证冒充到账或代付');
            $response=$next($request);
            return $action==='info'?$this->filterChannels($response):$response;
        } finally {
            try {$connection->query('SELECT RELEASE_LOCK(:lock_key) AS released',['lock_key'=>$key],true);} catch (\Throwable $ignored) { /* MySQL releases it when the connection closes. */ }
        }
    }
    private function filterChannels($response)
    {
        if (!is_object($response)||!method_exists($response,'getData')||!method_exists($response,'data')) return $response;
        $body=$response->getData();if (!is_array($body)||!is_array($body['data']??null)) return $response;
        $allowed=static fn($row):bool=>is_array($row)&&in_array($row['key']??'',[PayDict::WECHATPAY,PayDict::ALIPAY,PayDict::BALANCEPAY],true);
        if (isset($body['data']['pay_type_list'])) $body['data']['pay_type_list']=array_values(array_filter($body['data']['pay_type_list'],$allowed));
        elseif ($body['data']===[]||array_keys($body['data'])===range(0,count($body['data'])-1)) $body['data']=array_values(array_filter($body['data'],$allowed));
        return $response->data($body);
    }
}
