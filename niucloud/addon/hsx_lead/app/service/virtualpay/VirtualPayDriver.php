<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\virtualpay;

use addon\hsx_lead\app\service\cooperation\Rules;
use core\exception\CommonException;

/** Registered only through the plugin's native pay-driver dictionary. */
final class VirtualPayDriver
{
    private int $site;
    private VirtualPayOrderService $service;
    public function __construct(string $name,array $config=[],?string $configFile=null)
    {
        $this->site=(int)($config['site_id']??0);$this->service=new VirtualPayOrderService();
    }
    public function pay(array $params): array
    {
        if(($params['channel']??'')!=='weapp'||(int)($params['site_id']??0)!==$this->site)throw new CommonException('微信虚拟支付仅支持当前站点的小程序');
        if(Rules::cents((string)($params['money']??''))<=0)throw new CommonException('虚拟支付金额无效');
        $code=request()->param('login_code','');if(!is_string($code))throw new CommonException('请重新发起微信登录后付款');
        return$this->service->prepare($this->site,(string)($params['out_trade_no']??''),$code);
    }
    public function getOrder(array $params): array{return$this->service->query($this->site,(string)($params['out_trade_no']??''));}
    public function close(string $number): bool{return$this->service->close($this->site,$number);}
    public function refund(array $params): array{return$this->service->refund($this->site,$params);}
    public function getRefund(string $number,?string $refundNo): array{return$this->service->refundState($this->site,$number,$refundNo);}
    public function __call(string $name,array $arguments){throw new CommonException('此虚拟支付操作尚未开放，未发起资金处理');}
}
