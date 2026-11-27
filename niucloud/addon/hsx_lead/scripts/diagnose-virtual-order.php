<?php
declare(strict_types=1);
namespace addon\hsx_lead\scripts;

use addon\hsx_lead\app\service\virtualpay\{VirtualPayHttpClient,VirtualPaySettings};
use addon\hsx_lead\app\service\virtualpay\VirtualPayOrderService;
use addon\hsx_lead\app\service\cooperation\MembershipVirtualPayAdapter;
use app\dict\pay\PayDict;
use core\exception\CommonException;
use RuntimeException;
use Throwable;
use think\facade\Db;

/** No apply mode. Default: database-enforced read-only snapshot. --query:
 * one original WeChat query after rollback; access-token cache may refresh.
 * Never dispatches a payment, close, refund, receipt or entitlement operation.
 */
final class VirtualOrderDiagnosticStatus
{
    public static string $stage='arguments';
    public static bool $queryRequested=false;
    public static bool $tokenAttempted=false;
    public static bool $queryAttempted=false;
}

function virtualOrderOptions(array $args):array
{
    $out=['query'=>false];$seen=[];
    foreach($args as$arg){
        if($arg==='--query'||$arg==='--check'){
            $key=substr($arg,2);if(isset($seen[$key]))throw new RuntimeException('诊断参数不能重复');$seen[$key]=true;
            if($key==='query')$out['query']=true;continue;
        }
        if(!preg_match('/^--(site|order)=(.+)$/D',$arg,$m)||isset($seen[$m[1]]))throw new RuntimeException('仅支持 --site=站点ID --order=业务订单号，可选 --query；没有 --apply 或状态修复模式');
        $seen[$m[1]]=true;$out[$m[1]]=$m[2];
    }
    if(isset($seen['check'])&&$out['query'])throw new RuntimeException('--check 仅核对本地，不能与 --query 同时使用');
    if(!isset($out['site'])||!preg_match('/^[1-9][0-9]{0,9}$/D',$out['site'])||(int)$out['site']>4294967295)throw new RuntimeException('请明确填写有效的 --site=站点ID，不会默认选择站点');
    if(!isset($out['order'])||!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{7,63}$/D',$out['order']))throw new RuntimeException('请填写8至64位业务订单号，不接受手机号、URL或付款密钥');
    return$out;
}

function virtualOrderSafety():array
{
    return['diagnostic_version'=>'20261112.2','read_only'=>!VirtualOrderDiagnosticStatus::$queryRequested,
        'database_snapshot_read_only'=>true,'order_state_writes'=>false,'business_row_writes'=>false,
        'external_query_requested'=>VirtualOrderDiagnosticStatus::$queryRequested,
        'access_token_refresh_attempted'=>VirtualOrderDiagnosticStatus::$tokenAttempted,
        'token_cache_refresh_possible'=>VirtualOrderDiagnosticStatus::$tokenAttempted,
        'external_order_query_attempted'=>VirtualOrderDiagnosticStatus::$queryAttempted];
}

/** Location and validated numeric codes only. Unknown messages/SQL/URLs are hidden. */
function virtualOrderSafeError(Throwable $error):array
{
    $details=['stage'=>VirtualOrderDiagnosticStatus::$stage,'error_type'=>get_class($error),'file'=>basename($error->getFile()),'line'=>$error->getLine()];
    for($cause=$error,$i=0;$cause!==null&&$i<5;$cause=$cause->getPrevious(),$i++){
        if(!$cause instanceof \PDOException)continue;$info=$cause->errorInfo??[];$state=$info[0]??$cause->getCode();
        if(is_string($state)&&preg_match('/^[0-9A-Z]{5}$/D',$state))$details['sqlstate']=$state;
        if(isset($info[1])&&(is_int($info[1])||ctype_digit((string)$info[1]))&&(float)$info[1]<=2147483647)$details['driver_code']=(int)$info[1];
        break;
    }
    $httpFile=dirname(__DIR__).'/app/service/virtualpay/VirtualPayHttpClient.php';
    if(get_class($error)===CommonException::class&&$error->getFile()===$httpFile
        &&preg_match('/^虚拟支付渠道拒绝请求，错误码：(-?[0-9]{1,10})$/Du',$error->getMessage(),$m)
        &&abs((int)$m[1])<=9999999999&&(int)$m[1]===$error->getCode())$details['wechat_errcode']=(int)$m[1];
    $own=get_class($error)===RuntimeException::class&&$error->getFile()===__FILE__;
    return virtualOrderSafety()+['error'=>$own?$error->getMessage():'诊断未完成，安全定位信息见 diagnostics；未修改订单状态','diagnostics'=>$details];
}

function virtualOrderEnvironment($env):string{return(int)$env===0?'正式环境':((int)$env===1?'沙箱环境':'未知环境');}
function virtualOrderMoney($value):?int
{
    if(!is_string($value)||!preg_match('/^(0|[1-9][0-9]{0,7})\.([0-9]{2})$/D',$value,$m))return null;
    return(int)$m[1]*100+(int)$m[2];
}
function virtualOrderMessage($message):array
{
    $known=['','查询暂未获得完整凭据，未重复收款或发放权益','服务商已确认关闭','收款与业务已核实',
        '已核实收款，原业务状态需平台核对','已核实收款与服务商后续状态不一致，请核对原单',
        '渠道外部退款已核实，正在同步本地权益','渠道退款已核实，正在等待本地权益恢复；不会再次退款',
        '渠道外部退款已核实，原业务仍有待核对事项','渠道外部退款与原会员权益已同步'];
    $safe=is_string($message)&&in_array($message,$known,true);
    return['last_message'=>$safe?$message:'非标准历史提示已隐藏，请根据安全定位信息核对','last_message_redacted'=>!$safe];
}

/** Hash source files, never credentials/configuration or dynamic event payloads.
 * The upgrade bootstrap is deliberately minimal: absence of a CLI event instance
 * is not evidence that the HTTP worker has no listeners.
 */
function virtualOrderRuntime():array
{
    $backend=dirname(__DIR__,3);$files=[];$source=[];
    foreach([
        'addon/hsx_lead/app/service/virtualpay/VirtualPayOrderService.php',
        'addon/hsx_lead/app/service/virtualpay/VirtualPayHttpClient.php',
        'addon/hsx_lead/app/service/cooperation/MembershipVirtualPayAdapter.php',
        'addon/hsx_lead/app/service/cooperation/MembershipCostService.php',
        'addon/hsx_lead/app/service/cooperation/PaymentService.php',
        'addon/hsx_lead/app/listener/pay/VirtualPayMessage.php',
        'addon/hsx_lead/app/event.php',
        'app/service/core/pay/CorePayService.php',
        'app/service/core/weapp/CoreWeappServeService.php',
        'app/service/core/weapp/CoreWeappAuthenticatedMessage.php',
        'app/service/core/weapp/CoreWeappMessageEncryptor.php'
    ]as$relative){
        $file=$backend.'/'.$relative;$present=is_file($file)&&is_readable($file);
        $hash=$present?@hash_file('sha256',$file):false;
        $files[]=['file'=>$relative,'present'=>$present,'sha256'=>is_string($hash)?$hash:null];
        if($present)$source[$relative]=(string)@file_get_contents($file);
    }
    $serve=$source['app/service/core/weapp/CoreWeappServeService.php']??'';$declared=$source['addon/hsx_lead/app/event.php']??'';
    $registration=['scope'=>'current_cli_instance_only','inspected'=>false,'reason_code'=>'CLI_EVENT_INSTANCE_NOT_INITIALIZED'];
    try{
        if(function_exists('app')&&app()->exists('event')){
            $event=app()->make('event');$property=new \ReflectionProperty($event,'listener');$property->setAccessible(true);$listeners=$property->getValue($event);
            $registration=['scope'=>'current_cli_instance_only','inspected'=>true,'events'=>[]];
            foreach(['WeappMessage'=>'addon\\hsx_lead\\app\\listener\\pay\\VirtualPayMessage','PaySuccess'=>'addon\\hsx_lead\\app\\listener\\pay\\CooperationPaySuccess']as$name=>$expected){
                $items=is_array($listeners[$name]??null)?$listeners[$name]:[];$found=false;
                foreach($items as$item){$owner=is_array($item)?($item[0]??null):$item;if(is_object($owner))$owner=get_class($owner);if(is_string($owner)&&ltrim($owner,'\\')===$expected)$found=true;}
                $registration['events'][$name]=['listener_count'=>count($items),'expected_listener_registered'=>$found];
            }
        }
    }catch(Throwable$error){$registration=['scope'=>'current_cli_instance_only','inspected'=>false,'reason_code'=>'CLI_EVENT_INSPECTION_UNAVAILABLE'];}
    return['scope'=>'本次CLI磁盘文件与已存在事件实例；不证明HTTP或常驻进程已加载同一版本','files'=>$files,
        'capabilities'=>['weapp_message_event_present'=>str_contains($serve,"event('WeappMessage'")||str_contains($serve,'event("WeappMessage"'),
            'authenticated_message_parameter_present'=>str_contains($serve,'authenticated_message'),
            'authenticated_message_class_present'=>isset($source['app/service/core/weapp/CoreWeappAuthenticatedMessage.php']),
            'authenticated_encryptor_class_present'=>isset($source['app/service/core/weapp/CoreWeappMessageEncryptor.php']),
            'plugin_virtual_message_listener_declared'=>str_contains($declared,'VirtualPayMessage'),
            'plugin_payment_success_listener_declared'=>str_contains($declared,'CooperationPaySuccess')],
        'event_registration'=>$registration];
}

/** A controlled reason code, never a dependency's original error text. */
function virtualOrderValidationFailure(Throwable$error,string$default):array
{
    $plugin=dirname(__DIR__);$expected=[
        $plugin.'/app/service/virtualpay/VirtualPayOrderService.php'=>[
            '服务商查单与原单未唯一对应'=>'QUERY_ORIGINAL_ID_MISMATCH',
            '服务商查单环境不一致'=>'QUERY_ENVIRONMENT_MISMATCH',
            '服务商查单透传资料不一致'=>'QUERY_BIZ_META_MISMATCH',
            '服务商查单缺少一致的收款金额或交易凭据，未开通权益'=>'PAID_RECEIPT_PROOF_INCOMPLETE',
            '虚拟支付金额或状态须为有效整数'=>'QUERY_INTEGER_PROOF_INVALID'
        ]
    ];
    $code=$default;
    if(get_class($error)===CommonException::class){
        $code=$expected[$error->getFile()][$error->getMessage()]??$code;
        foreach([
            'MembershipCostService.php'=>'BUSINESS_COST_SNAPSHOT_INVALID',
            'MembershipVirtualPayAdapter.php'=>'BUSINESS_SOURCE_SCOPE_OR_AMOUNT_INVALID',
            'Store.php'=>'BUSINESS_ORIGINAL_UNAVAILABLE',
            'Rules.php'=>'BUSINESS_MONEY_INVALID'
        ]as$file=>$reason)if($error->getFile()===$plugin.'/app/service/cooperation/'.$file)$code=$reason;
    }
    return['passed'=>false,'reason_code'=>$code,'diagnostics'=>virtualOrderSafeError($error)['diagnostics']];
}

/** Calls only the two actual pure static validators, not the stateful query path. */
function virtualOrderProviderPreflight(array$source,array$remote):array
{
    $out=['biz_meta_present'=>array_key_exists('biz_meta',$remote),'biz_meta_is_string'=>is_string($remote['biz_meta']??null),
        'biz_meta_matches_attach'=>array_key_exists('biz_meta',$remote)&&is_string($remote['biz_meta'])&&is_string($source['attach']??null)&&hash_equals($source['attach'],$remote['biz_meta'])];
    foreach(['assertQuery'=>'query_identity','assertPaidQuery'=>'paid_receipt']as$method=>$key){
        VirtualOrderDiagnosticStatus::$stage='preflight_'.$key;
        try{$reflection=new \ReflectionMethod(VirtualPayOrderService::class,$method);$reflection->setAccessible(true);$reflection->invoke(null,$source,$remote);$out[$key]=['passed'=>true,'reason_code'=>'PASS'];}
        catch(Throwable$error){$out[$key]=virtualOrderValidationFailure($error,'PROVIDER_VALIDATION_UNAVAILABLE');}
    }
    return$out;
}

/** A second database-enforced read-only snapshot protects the business adapter.
 * Re-read original source/pay after the network round trip. An optional PHP-only
 * checker is for synthetic tests; no CLI flag or request can replace validation.
 */
function virtualOrderLocalPreflight(int$site,array$expectedSource,array$remote,?callable$businessChecker=null):array
{
    VirtualOrderDiagnosticStatus::$stage='preflight_read_only_transaction';$pdo=Db::connect()->getPdo();
    if($pdo instanceof \PDO&&$pdo->inTransaction())throw new RuntimeException('付款核验预检必须使用独立只读事务');
    Db::execute('SET TRANSACTION READ ONLY');Db::startTrans();
    try{
        VirtualOrderDiagnosticStatus::$stage='preflight_source_reload';
        $sources=Db::name('hsx_lead_virtual_order')->where('site_id',$site)->where('id',(int)$expectedSource['id'])->limit(2)->select()->toArray();
        if(count($sources)!==1)return['source_unchanged'=>false,'reason_code'=>'ORIGINAL_SOURCE_UNAVAILABLE'];$source=$sources[0];
        $same=true;foreach(['id','site_id','pay_id','order_id','member_id','env','amount_cents']as$key)if((int)($source[$key]??-1)!==(int)($expectedSource[$key]??-1))$same=false;
        foreach(['app_id','offer_id','out_trade_no','product_id','attach','snapshot_hash']as$key)if(($source[$key]??null)!==($expectedSource[$key]??null))$same=false;
        if(!$same)return['source_unchanged'=>false,'reason_code'=>'ORIGINAL_FROZEN_SOURCE_CHANGED'];
        $out=['source_unchanged'=>true,'provider'=>virtualOrderProviderPreflight($source,$remote)];
        VirtualOrderDiagnosticStatus::$stage='preflight_business_snapshot';
        try{
            $pays=Db::name('pay')->where('site_id',$site)->where('id',(int)$source['pay_id'])->limit(2)->select()->toArray();
            if(count($pays)!==1){$out['business_snapshot']=['passed'=>false,'reason_code'=>'ORIGINAL_NATIVE_PAYMENT_UNAVAILABLE'];return$out;}$pay=$pays[0];
            if($businessChecker)$resolved=$businessChecker($site,$pay);
            else{$adapter=new MembershipVirtualPayAdapter();$business=$adapter->resolve($site,$pay);$resolved=['business'=>$business,'fingerprint'=>$adapter->fingerprint($business)];}
            $business=$resolved['business']??null;$fingerprint=$resolved['fingerprint']??null;
            $link=is_array($business)&&(int)($business['site_id']??0)===$site&&(int)($business['id']??0)===(int)$source['order_id']
                &&(int)($business['member_id']??0)===(int)$source['member_id']&&(int)($pay['main_id']??0)===(int)$source['member_id']
                &&(int)($pay['site_id']??0)===$site&&(int)($pay['id']??0)===(int)$source['pay_id']&&(int)($pay['trade_id']??0)===(int)$source['order_id']
                &&($pay['trade_type']??null)==='hsx_lead_cooperation'&&($pay['main_type']??null)===PayDict::MEMBER
                &&($pay['type']??null)===VirtualPaySettings::TYPE&&($pay['channel']??null)==='weapp'
                &&($pay['out_trade_no']??null)===$source['out_trade_no']&&virtualOrderMoney($pay['money']??null)===(int)$source['amount_cents']
                &&(($business['out_trade_no']??'')===''||($business['out_trade_no']??null)===$source['out_trade_no'])
                &&virtualOrderMoney($business['amount']??null)===(int)$source['amount_cents'];
            $hash=is_string($fingerprint)&&preg_match('/^[a-f0-9]{64}$/D',$fingerprint)&&is_string($source['snapshot_hash']??null)&&hash_equals($source['snapshot_hash'],$fingerprint);
            $out['business_snapshot']=['passed'=>$link&&$hash,'reason_code'=>!$link?'BUSINESS_ORIGINAL_LINK_MISMATCH':(!$hash?'FROZEN_PRICING_FINGERPRINT_MISMATCH':'PASS'),
                'original_link_matches'=>$link,'pricing_fingerprint_matches'=>(bool)$hash];
        }catch(Throwable$error){$out['business_snapshot']=virtualOrderValidationFailure($error,'BUSINESS_SNAPSHOT_VALIDATION_UNAVAILABLE');}
        return$out;
    }finally{Db::rollback();}
}

/** PHP-only doubles are for isolated tests; CLI accepts no provider override. */
function virtualOrderDiagnostic(array $options,?callable $settingsReader=null,?callable $tokenReader=null,?object $client=null,?callable$businessChecker=null):array
{
    VirtualOrderDiagnosticStatus::$queryRequested=!empty($options['query']);
    VirtualOrderDiagnosticStatus::$tokenAttempted=false;VirtualOrderDiagnosticStatus::$queryAttempted=false;
    $site=(int)$options['site'];$report=virtualOrderSafety()+['site_id'=>$site,'requested_order_no'=>$options['order'],'runtime'=>virtualOrderRuntime()];
    VirtualOrderDiagnosticStatus::$stage='read_only_transaction';
    $pdo=Db::connect()->getPdo();
    if($pdo instanceof \PDO&&$pdo->inTransaction())throw new RuntimeException('诊断必须使用独立只读事务，拒绝嵌入已有事务');
    // A cold ThinkORM connection returns false before its first SQL command.
    Db::execute('SET TRANSACTION READ ONLY');Db::startTrans();
    try{
        VirtualOrderDiagnosticStatus::$stage='business_order_lookup';
        $orders=Db::name('hsx_lead_coop_order')->where('site_id',$site)->where('order_no',$options['order'])->field('id,site_id,order_no,purpose,member_id,amount,status,out_trade_no,pay_time,create_time,update_time')->limit(2)->select()->toArray();
        if(count($orders)!==1)throw new RuntimeException('本站未找到唯一的业务订单；不会跨站或按相似订单号查找');
        $business=$orders[0];
        $report['business_order']=array_intersect_key($business,array_flip(['id','order_no','purpose','amount','status','out_trade_no','pay_time','create_time','update_time']));
        VirtualOrderDiagnosticStatus::$stage='virtual_source_lookup';
        // The business out_trade_no may still be empty before a verified receipt.
        $sources=Db::name('hsx_lead_virtual_order')->where('site_id',$site)->where('order_id',(int)$business['id'])->limit(2)->select()->toArray();
        $report['virtual_source_count']=count($sources);
        if(count($sources)>1)return$report+['next_action'=>'AMBIGUOUS_VIRTUAL_SOURCE','message'=>'该业务订单存在多笔虚拟支付来源，本诊断不猜选原单，不会向微信发起查询'];
        if(!$sources)return$report+['next_action'=>'NO_VIRTUAL_SOURCE','message'=>'未找到本业务的虚拟支付原始记录；本地业务订单存在不代表已向微信发起付款'];
        $source=$sources[0];
        $report['virtual_order']=array_intersect_key($source,array_flip(['id','pay_id','order_id','env','out_trade_no','product_id','amount_cents','status','wx_order_id','order_type','last_provider_status','paid_at','next_check_at','create_time','update_time']));
        $report['virtual_order']+=virtualOrderMessage($source['last_message']??'');
        $report['virtual_order']['environment']=virtualOrderEnvironment($source['env']??-1);
        if(empty($source['wx_order_id']))$report['virtual_order']['order_type_note']='尚无已核实的渠道流水，记录中的默认 order_type 不能用来判断安卓或 iOS';
        VirtualOrderDiagnosticStatus::$stage='native_payment_lookup';
        $payments=Db::name('pay')->where('site_id',$site)->where('id',(int)$source['pay_id'])->limit(2)->select()->toArray();
        $pay=count($payments)===1?$payments[0]:[];
        $report['native_payment']=array_intersect_key($pay,array_flip(['id','out_trade_no','trade_type','trade_id','type','channel','money','status','pay_time','create_time','update_time']));
        $matches=$pay&&(int)$pay['trade_id']===(int)$business['id']&&($pay['trade_type']??'')==='hsx_lead_cooperation'
            &&($pay['main_type']??'')===PayDict::MEMBER
            &&($pay['type']??'')===VirtualPaySettings::TYPE&&($pay['channel']??'')==='weapp'
            &&($pay['out_trade_no']??'')===($source['out_trade_no']??'')&&(int)($pay['main_id']??0)===(int)($source['member_id']??0)
            &&(($business['out_trade_no']??'')===''||$business['out_trade_no']===($source['out_trade_no']??''))
            &&(int)($business['member_id']??0)===(int)($source['member_id']??0)
            &&virtualOrderMoney($pay['money']??null)===(int)($source['amount_cents']??-1)
            &&virtualOrderMoney($business['amount']??null)===(int)($source['amount_cents']??-1);
        $report['source_payment_matches']=(bool)$matches;
        VirtualOrderDiagnosticStatus::$stage='current_settings_lookup';
        $settings=$settingsReader?$settingsReader($site):VirtualPaySettings::get($site,false);
        if(!is_array($settings))throw new RuntimeException('当前支付配置返回格式异常，未继续查询');
        $scope=(string)($settings['app_id']??'')===(string)($source['app_id']??'')
            &&(string)($settings['offer_id']??'')===(string)($source['offer_id']??'')
            &&in_array($settings['env']??null,[0,1],true)&&(int)$settings['env']===(int)($source['env']??-1);
        $report['current_settings']=['enabled'=>!empty($settings['enabled']),'payment_mode'=>$settings['payment_mode']??'',
            'env'=>$settings['env']??null,'environment'=>virtualOrderEnvironment($settings['env']??-1),
            'original_account_and_environment_match'=>$scope,'has_app_key'=>is_string($settings['app_key']??null)&&$settings['app_key']!==''];
        $key=$settings['app_key']??null;
        $ready=preg_match('/^wx[A-Za-z0-9]{16}$/D',(string)($settings['app_id']??''))===1
            &&preg_match('/^[0-9]{1,32}$/D',(string)($settings['offer_id']??''))===1
            &&is_string($key)&&strlen($key)>=16&&strlen($key)<=256;
        if(!$matches||!$scope||!$ready)return$report+['next_action'=>'VERIFY_ORIGINAL_SOURCE_OR_SETTINGS','message'=>'原付款来源、原账户、环境或当前查询凭据未一致，不向微信发起查询，不修改原单'];
        if(empty($options['query']))return$report+['next_action'=>'LOCAL_SNAPSHOT_ONLY','message'=>'本次仅完成本地只读快照；显式增加 --query 才查询原微信单，可能刷新 access_token 缓存，但不改订单、权益或资金'];
    }finally{Db::rollback();}

    // No order service is used here: those query methods may confirm receipts.
    VirtualOrderDiagnosticStatus::$stage='access_token';VirtualOrderDiagnosticStatus::$tokenAttempted=true;
    try{
        $settings['access_token']=$tokenReader?$tokenReader($site):VirtualPaySettings::accessToken($site);
        VirtualOrderDiagnosticStatus::$stage='wechat_original_order_query';VirtualOrderDiagnosticStatus::$queryAttempted=true;
        $remote=($client??new VirtualPayHttpClient())->query($settings,['openid'=>$source['openid']??'','out_trade_no'=>$source['out_trade_no']]);
        if(!is_array($remote))throw new RuntimeException('微信原单查询未返回有效结构；未修改任何订单状态');
        $safe=[];
        foreach(['status','order_type','env_type','order_fee','paid_fee','left_fee','goods_price','paid_time']as$field)
            if(isset($remote[$field])&&is_int($remote[$field])&&$remote[$field]>=0)$safe[$field]=$remote[$field];
        foreach(['order_id','wx_order_id']as$field)
            if(isset($remote[$field])&&is_string($remote[$field])&&preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$remote[$field]))$safe[$field]=$remote[$field];
        $same=isset($safe['order_id'])&&$safe['order_id']===$source['out_trade_no'];
        if(isset($safe['env_type']))$same=$same&&$safe['env_type']===((int)$source['env']===0?1:2);
        $report['query']=['result'=>'returned','original_order_and_environment_match'=>$same,'fields'=>$safe,'fulfillment_performed'=>false];
        try{$report['local_preflight']=virtualOrderLocalPreflight($site,$source,$remote,$businessChecker);}
        catch(Throwable$error){$report['local_preflight']=virtualOrderValidationFailure($error,'LOCAL_PREFLIGHT_UNAVAILABLE');}
        $report['next_action']=$same?'REVIEW_RETURNED_PROVIDER_FACTS':'VERIFY_QUERY_IDENTITY';
        $report['message']='这里只展示原渠道查单事实，不确认收款、不发放权益、不关单、不退款；前端调起成功不等于真实扣款';
    }catch(Throwable$error){
        $failure=virtualOrderSafeError($error);$report['query']=['result'=>'unconfirmed','error'=>$failure['error'],'diagnostics'=>$failure['diagnostics'],'fulfillment_performed'=>false];
        $report['next_action']='REVIEW_SAFE_QUERY_DIAGNOSTICS';$report['message']='原单查询未取得可核实结果；请根据环境和安全错误码核对，不要重复付款或手工改成已支付';
    }
    VirtualOrderDiagnosticStatus::$stage='complete';
    return array_replace($report,virtualOrderSafety());
}

if(!defined('HSX_LEAD_VIRTUAL_ORDER_DIAG_LIBRARY_ONLY')){
    if(PHP_SAPI!=='cli'||PHP_VERSION_ID<80000)exit("仅支持 PHP 8.0+ 命令行执行\n");
    try{
        $options=virtualOrderOptions(array_slice($argv,1));VirtualOrderDiagnosticStatus::$queryRequested=$options['query'];
        VirtualOrderDiagnosticStatus::$stage='bootstrap';define('HSX_LEAD_UPGRADE_LIBRARY_ONLY',true);
        require_once __DIR__.'/upgrade.php';bootstrap(dirname(__DIR__,3));
        echo json_encode(virtualOrderDiagnostic($options),JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
    }catch(Throwable$error){fwrite(STDERR,json_encode(virtualOrderSafeError($error),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).PHP_EOL);exit(1);}
}
