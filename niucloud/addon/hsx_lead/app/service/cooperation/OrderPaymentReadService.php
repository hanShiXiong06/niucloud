<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\support\Data;
use app\dict\common\ChannelDict;
use app\dict\pay\{PayDict,RefundDict};
use core\exception\CommonException;
use think\facade\Db;

/** Read real cashier facts. A terminal (weapp/h5) is not a payment method. */
final class OrderPaymentReadService
{
    private static array $knownTables=[];
    public static function options(): array
    {
        $types=[];foreach(PayDict::getPayType() as $key=>$item)$types[]=['value'=>(string)$key,'label'=>(string)$item['name']];
        if(!in_array(PayDict::OFFLINEPAY,array_column($types,'value'),true))$types[]=['value'=>PayDict::OFFLINEPAY,'label'=>'线下确认收款'];
        $channels=[];foreach(ChannelDict::getType() as $key=>$label)$channels[]=['value'=>(string)$key,'label'=>(string)$label];
        $statuses=[];foreach(PaymentRefundService::LABELS as $key=>$label)$statuses[]=['value'=>$key,'label'=>$label];
        return ['payment_types'=>$types,'payment_channels'=>$channels,'refund_statuses'=>$statuses];
    }
    public static function payment(array $order,bool $lock=false): ?array
    {
        if(!self::hasTable('pay'))return null;
        $q=Db::name('pay')->where('site_id',(int)$order['site_id'])->where('trade_type',PaymentService::TRADE_TYPE)->where('trade_id',(string)$order['id']);
        if((string)($order['out_trade_no']??'')!=='')$q->where('out_trade_no',(string)$order['out_trade_no']);
        else $q->orderRaw('CASE WHEN status=2 THEN 0 WHEN status=1 THEN 1 WHEN status=0 THEN 2 ELSE 3 END')->order('id','desc');
        if($lock)$q->lock(true);
        $rows=$q->limit(2)->select()->toArray();if(!$rows)return null;
        if((string)($order['out_trade_no']??'')!==''&&count($rows)!==1)return null;
        if(count($rows)>1&&(int)$rows[0]['status']===(int)PayDict::STATUS_FINISH&&(int)$rows[1]['status']===(int)PayDict::STATUS_FINISH)return null;
        return$rows[0];
    }
    /** Typed source evidence is required for attested receipts, not for an
     * ordinary wallet payment. Reused before payment/refund side effects. */
    public static function assertManualSource(array $order,array $pay): void
    {
        $site=(int)$order['site_id'];$type=(string)($pay['type']??'');
        if($type===PayDict::OFFLINEPAY||($type===PayDict::BALANCEPAY
            &&(str_starts_with((string)($pay['voucher']??''),'manual-receipt:')
                ||ManualPaymentService::hasPaymentReceipt($site,(int)$order['id'],(string)$pay['out_trade_no']))))ManualPaymentService::assertPaymentReceipt($site,$order,$pay);
    }
    public static function hasTable(string $table): bool
    {
        if(!in_array($table,['pay','pay_refund','hsx_lead_virtual_order'],true))return false;
        $name=(string)Db::name($table)->getTable();
        $database=(string)Db::connect()->getConfig('database');$scope=$database.':'.$name;
        if(isset(self::$knownTables[$scope]))return true;
        $rows=Db::query('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?',[$name]);
        $exists=(int)($rows[0]['n']??0)===1;if($exists)self::$knownTables[$scope]=true;return$exists;
    }
    public static function present(array $order,array $presented): array
    {
        $pay=self::payment($order);$types=PayDict::getPayType();$type=(string)($pay['type']??'');$channel=(string)($pay['channel']??'');
        $presented['payment_type']=$type;$presented['payment_type_name']=$type===PayDict::OFFLINEPAY?'线下确认收款':($type!==''?(string)($types[$type]['name']??'其他支付方式'):'尚未选择');
        // Administration retains the actual channel; customer copy does not
        // turn a server-side channel choice into another payment decision.
        $presented['payment_display_name']=$type===\addon\hsx_lead\app\service\virtualpay\VirtualPaySettings::TYPE?'微信支付':$presented['payment_type_name'];
        $presented['payment_channel']=$channel;$presented['payment_channel_name']=$channel!==''?(ChannelDict::getType($channel)?:'其他支付终端'):'尚未确认';
        $source=null;
        if($pay&&$type===\addon\hsx_lead\app\service\virtualpay\VirtualPaySettings::TYPE&&self::hasTable('hsx_lead_virtual_order')){
            $source=Db::name('hsx_lead_virtual_order')->where('site_id',(int)$order['site_id'])->where('pay_id',(int)$pay['id'])->where('order_id',(int)$order['id'])->find();
        }
        return PaymentOrderActions::present($order,$pay,$source,array_replace($presented,PaymentRefundService::summary($order,$pay)));
    }
    public static function filter($query,array $params,int $site): void
    {
        $prefix=(string)Db::connect()->getConfig('prefix');
        foreach(['payment_type'=>'type','payment_channel'=>'channel'] as $key=>$field){
            if(!isset($params[$key])||$params[$key]==='')continue;
            $value=$params[$key];$allowed=array_column(self::options()[$key==='payment_type'?'payment_types':'payment_channels'],'value');
            if(!is_string($value)||!in_array($value,$allowed,true))throw new CommonException('支付方式或终端筛选无效');
            // Match the same canonical cashier row used for presentation, not an abandoned attempt.
            $query->whereRaw("(SELECT p.`$field` FROM `{$prefix}pay` p WHERE p.site_id=? AND p.trade_type=? AND BINARY p.trade_id=BINARY CAST(`{$prefix}hsx_lead_coop_order`.id AS CHAR) AND (`{$prefix}hsx_lead_coop_order`.out_trade_no='' OR BINARY p.out_trade_no=BINARY `{$prefix}hsx_lead_coop_order`.out_trade_no) ORDER BY CASE WHEN p.status=2 THEN 0 WHEN p.status=1 THEN 1 WHEN p.status=0 THEN 2 ELSE 3 END,p.id DESC LIMIT 1)=?",[$site,PaymentService::TRADE_TYPE,$value]);
        }
        if(isset($params['refund_status'])&&$params['refund_status']!==''){
            $value=$params['refund_status'];if(!is_string($value)||!isset(PaymentRefundService::LABELS[$value]))throw new CommonException('退款状态筛选无效');
            $stored="COALESCE(JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(rule_json) THEN rule_json ELSE '{}' END,'$.order_refund.status')),'none')";
            $returned="COALESCE((SELECT SUM(r.money) FROM `{$prefix}pay_refund` r WHERE r.site_id=`{$prefix}hsx_lead_coop_order`.site_id AND BINARY r.out_trade_no=BINARY `{$prefix}hsx_lead_coop_order`.out_trade_no AND r.trade_type='hsx_lead_cooperation' AND BINARY r.trade_id=BINARY CAST(`{$prefix}hsx_lead_coop_order`.id AS CHAR) AND r.status='success'),0)";
            $query->whereRaw("CASE WHEN $stored='none' AND $returned>0 THEN CASE WHEN $returned>=amount THEN 'refunded' ELSE 'partial_refunded' END ELSE $stored END=?",[$value]);
        }
    }
}
