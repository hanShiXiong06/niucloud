<?php
declare(strict_types=1);

// One authorized acceptance device only. No settlement, no production, no mock or auto-resend.
use addon\hsx_boyuan\app\gateway\{HttpTransport,OpenApi};
use addon\hsx_boyuan\app\service\{ConfigService,PurchaseInventoryService,ReferenceService,WholesaleSyncService};
use addon\hsx_boyuan\app\support\{Contract,Problem,Secret};
use addon\hsx_erp\app\service\admin\{ErpIntegrationService,ErpSaleService};
use think\facade\Db;

const SO_IMEI='990000611183015';
const SO_REQ='BY_UAT_100003_SO_990000611183015';
const SO_MANIFEST='/private/tmp/boyuan-100003-uat-cycle-manifest.json';
const SO_RECEIPT='/private/tmp/boyuan-100003-uat-990000611183015-wholesale.json';
function soMust(bool $ok,string $message):void { if (!$ok) throw new RuntimeException($message); }
function soPrint(array $o):void { echo json_encode($o,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL; }

// Restrict business data saved in the evidence file to this one new device/order. Never save the signed form or the entire response.
function soEvidence(array $raw,array $scope):array {
    $headFields=['bill_id','billid','bill_no','billno','bill_type','billtype','id','head_id','headid','big_gsdm','business_date','cust_id','warehouse_id','cash_term','manual_number','manualnumber'];
    $lineFields=['id','line_id','detail_id','pdetail_id','head_id','bill_id','imei','imei_no','product_id','qty','unit_price','amounts','seq_no'];
    $fieldNames=[];$records=[];
    $walk=static function(array $node,int $depth,string $path,bool $inResult=false) use (&$walk,&$fieldNames,&$records,$scope,$headFields,$lineFields):void {
        if($depth>7 || count($records)>12)return;
        $safe=[];
        foreach($node as $key=>$v)if(is_string($key)&&preg_match('/^[a-z][a-z0-9_]{0,50}$/iD',$key))$safe[]=strtolower($key);
        if($safe && count($fieldNames)<45)$fieldNames[$path]=array_values(array_unique($safe));
        $normalized=array_change_key_case($node,CASE_LOWER);
        $serial=(string)($normalized['imei']??$normalized['imei_no']??'');
        $sameHead=isset($normalized['manual_number']) && (string)$normalized['manual_number']===$scope['manual_number'];
        // The create endpoint returned a single head, not a list of unrelated customer rows. Select scalars only.
        $singleHead=$inResult && ($path==='api_response.result.head'||$path==='api_response.head') && $serial==='';
        $fields=$serial===SO_IMEI?$lineFields:($sameHead||$singleHead?$headFields:[]);
        if($fields) {
            $row=[];
            foreach($fields as $k)if(isset($normalized[$k]) && is_scalar($normalized[$k]) && !is_bool($normalized[$k])
                && preg_match('/^[A-Za-z0-9.+:\/-]{1,96}$/D',(string)$normalized[$k]))$row[$k]=$normalized[$k];
            if($row)$records[]=['path'=>$path,'data'=>$row];
        }
        foreach($node as $k=>$v)if(is_array($v) && (is_int($k)? $k<5 : preg_match('/^[a-z][a-z0-9_]{0,50}$/iD',(string)$k)))
            $walk($v,$depth+1,trim($path.'.'.(is_int($k)?'[]':strtolower($k)),'.'),$inResult||$k==='api_response');
    };
    $walk($raw,0,'');
    return ['site'=>100003,'account'=>'80004','environment'=>'UAT','imei'=>SO_IMEI,'manual_number'=>$scope['manual_number'],
        'service'=>'boyuan.biz.bill.sale.generate','response_time'=>date(DATE_ATOM),
        'return_code'=>preg_match('/^[A-Za-z0-9_]{1,48}$/D',(string)($raw['return_code']??''))?$raw['return_code']:null,
        'field_names'=>$fieldNames,'records'=>$records];
}

if(PHP_SAPI!=='cli'||count($argv)!==2||!in_array($argv[1],['probe','plan','sale','verify','report','reconcile'],true)) exit(2);
$mode=$argv[1];
try {
    define('LOCAL_CYCLE_INTERNAL_PREFLIGHT',true);require __DIR__.'/site-100003-preflight.php';
    soMust($site===100003 && $out['site_name']==='测试 2' && (int)$out['first_site_admin_uid']>0 && !$out['enabled_printers'] && !$out['other_ledger_connector_config_count'],'必须是专用本机 100003 且无其他账套/打印副作用');
    $uid=(int)$out['first_site_admin_uid']; $app->initialize();
    soMust(Db::query('SELECT DATABASE() AS n')[0]['n']===$d['database'],'非预检本机库');
    $app->instance('request',new app\Request());request()->siteId($site);request()->uid($uid);request()->username('博远UAT验收');request()->appType('adminapi');$app->env->set('queue.state',false);
    $conf=new ConfigService();$c=$conf->get($site,false);$p=$c['profiles']['test'];$key=$p['connection_key'];
    soMust($conf->installed($site) && $c['mode']==='test' && $p['account_id']==='80004' && $p['protocol']==='openapi_v153'
        && $p['endpoint']==='https://open-api-uat.139erp.com/api' && $p['gateway_path']==='/gateway.do' && !$c['finance_enabled'] && !$c['uat_payment_enabled'],'当前账套不是指定 UAT，或财务连接未关闭');
    if($mode!=='report')soMust($p['contract_confirmed'] && (int)$p['probe_at']>0,'未通过 UAT 账套身份核验');
    if(in_array($mode,['sale','verify'],true)) soMust($c['purchase_mode']==='manual' && $c['enabled'] && !$c['paused'] && $c['purchase_enabled'],'本次只允许人工操作，连接未启用');
    $qas=static fn(string $t)=>Db::name($t)->where('site_id',$site);
    $qt=static fn(string $kind)=>$qas('hsx_boyuan_task')->where('mode','test')->where('connection_key',$key)->where('kind',$kind);
    $archive=static function(string $resource,string $id)use($qas,$site,$key):array{
        $run=ReferenceService::latestRun($site,$key,$resource);soMust($run!=='' && $id!=='','未取得完整 '.$resource.' 档案');
        $ref=$qas(\addon\hsx_boyuan\app\support\CatalogStore::table($resource))->where('connection_key',$key)->where('seen_run',$run)->where('external_id',$id)->where('enabled',1)->find();
        soMust((bool)$ref,'未在当前同步档案找到指定 '.$resource);return Contract::decode($ref['extra_json']);
    };
    $countPost=0;
    $exchange=static function(string $path,array $params)use($p,$mode,&$countPost):array{
        $service=$params['service']??''; soMust($path==='/gateway.do' && ($params['big_gsdm']??null)==='80004','禁止其他网关/账套');
        if($service==='boyuan.biz.sales.query' && in_array($mode,['probe','sale','verify','reconcile'],true))return (new HttpTransport($p,''))->postForm($path,$params);
        soMust($mode==='sale' && $service==='boyuan.biz.bill.sale.generate' && ++$countPost===1,'出库外发范围拒绝，未执行');
        $biz=json_decode((string)$params['biz'],true,512,JSON_THROW_ON_ERROR);$head=$biz['head'];$rows=$biz['product_details']['add_details'];
        soMust(count($rows)===1 && $rows[0]['imei']===SO_IMEI && $rows[0]['qty']===1 && $rows[0]['amounts']==='2.00'
            && $head['cash_term']===0 && $head['is_auto_generate_cust']===0 && !isset($biz['sale_cash_details']),'只允许该设备一笔 2 元挂账，不接受现结');
        $old=umask(0077);try{$file=@fopen(SO_RECEIPT,'xb');}finally{umask($old);}
        soMust($file!==false,'该设备已有发出标记，不能重新创建销售单，请核对原单');
        try {
            $initial=json_encode(['state'=>'attempt_reserved','manual_number'=>$head['manual_number'],'imei'=>SO_IMEI],JSON_THROW_ON_ERROR)."\n";
            soMust(fwrite($file,$initial)===strlen($initial) && fflush($file),'回执保护不可用，本次不发出请求');
            $raw=(new HttpTransport($p,''))->postForm($path,$params);
            $filtered=json_encode(soEvidence($raw,$head),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)."\n";
            soMust(fwrite($file,$filtered)===strlen($filtered)&&fflush($file),'原始回执未完整保存，不得重发');return $raw;
        }finally{fclose($file);}
    };
    $api=new OpenApi($p,Secret::open($p['app_secret_cipher'],(string)env('app.auth_key',''),$site,$key.':app_secret'),$exchange,'test');
    $probe=static function()use($api):array{
        $date=(new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');$r=$api->wholesaleDetails($date,1,10);
        $total=$r['total_results']??null;$rows=$r['result_list']??null;
        $shapes=[];foreach($rows??[] as $row){if(!is_array($row))continue;$i=$row['imeis']??null;$s=['imeis_type'=>gettype($i),'is_list'=>is_array($i)&&array_values($i)===$i,'one_serial'=>is_string($i)&&preg_match('/^[0-9A-Za-z]{6,30}$/D',$i)===1,
            'is_datetime'=>is_string($row['business_date']??null)&&preg_match('/^\d{4}-\d\d-\d\d [0-9:]{8}$/D',$row['business_date'])===1,'business_date_is_iso_day'=>is_string($row['business_date']??null)&&preg_match('/^\d{4}-\d\d-\d\d$/D',$row['business_date'])===1,
            'id_type'=>gettype($row['id']??null),'effective_type'=>gettype($row['is_effective']??null),'qty_type'=>gettype($row['qty']??null),'is_wholesale'=>($row['bill_type']??null)==='WSA'];
            $shapes[json_encode($s)]=$s;}
        soPrint(['sales_read_only'=>true,'date'=>$date,'top_keys'=>array_keys($r),'list_exists'=>is_array($rows),'count_type'=>gettype($total),'safe_shapes'=>array_values($shapes),'first_row_fields'=>is_array($rows[0]??null)?array_keys($rows[0]):[]]);
        soMust((is_int($total)||is_string($total)&&ctype_digit($total))&&(int)$total>=0&&is_array($rows)&&count($rows)===min(10,(int)$total),'销售明细无法按已知分页契约读取，请先修复，不新建销售');
        return $r;
    };
    if($mode==='probe'){$probe();exit(0);}
    soMust(is_file(SO_MANIFEST),'缺少本次授权档案清单');$m=json_decode(file_get_contents(SO_MANIFEST),true,512,JSON_THROW_ON_ERROR);
    soMust(($m['imei']??'')===SO_IMEI && ($partyId=(int)($m['local_customer_id']??0))>0 && !empty($m['customer_id']) && !empty($m['warehouse_id']) && !empty($m['company_id']),'授权档案不完整');
    $asset=$qas('erp_asset')->where('imei',SO_IMEI)->find();soMust((bool)$asset && $asset['ownership_type']==='owned','新设备尚未真实入库');
    $party=$qas('erp_party')->where('id',$partyId)->find();soMust((bool)$party,'未找到指定本站客户');
    $purchase=$qas('erp_purchase_order')->where('id',$asset['purchase_order_id'])->find();soMust((bool)$purchase,'没有该串号的本地采购单');
    $po=$qt('purchase')->where('business_no',$purchase['purchase_no'])->where('status','succeeded')->find();
    soMust($po && $po['external_no']!=='','博远采购单没有经查询核实的真实单号');
    $poRequest=Contract::decode($po['request_json']);$poLines=$poRequest['lines']??[];
    soMust(count($poLines)===1&&$poLines[0]['imei']===SO_IMEI&&$poLines[0]['device_ref']==='asset:'.$asset['id'],'原采购设备或串号不符');
    $inv=null;foreach($qt(PurchaseInventoryService::KIND)->where('status','succeeded')->where('fact_key','like','inventory:'.$po['id'].':%')->order('id','desc')->select()->toArray()as$t){
        $vr=Contract::decode($t['result_json']);$matched=$vr['matched_lines'][0]??[];
        if(($vr['verified']??false)===true && ($vr['current_stock_verified']??false)===true && (int)($vr['evidence']['source_task_id']??0)===(int)$po['id']&&count($vr['matched_lines'])===1
            && $matched['imei']===SO_IMEI && (string)$matched['warehouse_id']===(string)$m['warehouse_id'] && $matched['imei_in_stock_status']===1){$inv=$vr;break;}
    }
    soMust($inv!==null,'尚无与原采购和当前仓库一致的逐台在库证据');
    $sale=$qas('erp_sale_order')->where('request_id',SO_REQ)->find();
    if(!$sale && in_array($mode,['plan','sale'],true)){
        soMust((int)$asset['sale_order_id']===0 && $asset['status']==='in_stock','串号不在未售库存');
        soMust(in_array($archive('parties',(string)$m['customer_id'])['is_cust']??null,[1,'1',true],true),'远端不是已启用的客户');
        $archive('staff',(string)($m['creator_id']??''));
        $warehouse=$archive('warehouses',(string)$m['warehouse_id']);$archive('products',(string)$poLines[0]['product_id']);
        $mapped=$qas('hsx_boyuan_mapping')->where('connection_key',$key)->where('kind','warehouse')->where('local_ref','warehouse:'.$asset['warehouse_id'])->value('external_id');
        soMust((string)$mapped===(string)$m['warehouse_id']&&(string)$warehouse['company_id']===(string)$m['company_id'],'当前仓/公司与采购、映射不符');
        $s=ErpSaleService::forSite($site,$uid,'博远UAT验收');$selectable=$s->stockPage(['imei'=>SO_IMEI,'warehouse_id'=>$asset['warehouse_id'],'location_id'=>$asset['location_id']]);
        soMust((int)$selectable['total']===1 && $selectable['data'][0]['imei']===SO_IMEI,'串号不可售，未动本地库存');
        if($mode==='plan'){soPrint(['local_plan'=>true,'imei'=>SO_IMEI,'purchase_no'=>$po['external_no'],'asset_id'=>$asset['id'],'qty'=>1,'amount'=>'2.00','remote_calls'=>0]);exit(0);}
        soMust(!file_exists(SO_RECEIPT),'原始写入标记已存在，禁止新建');
        $probe();
        soMust($inv['checked_at']>=time()-PurchaseInventoryService::MAX_AGE,'博远在库证据超过五分钟，请只读刷新库存后再继续');
        $id=$s->create(['request_id'=>SO_REQ,'sale_channel_key'=>'erp_peer','party_id'=>$partyId,'party_name'=>$party['party_name'],'settle_method'=>'挂账','settle_mode'=>'credit','remark'=>'博远UAT联调，不收款',
            'items'=>[['asset_id'=>(int)$asset['id'],'sale_price'=>'2.00','remark'=>'博远UAT测试设备']]]);
        $sale=$qas('erp_sale_order')->where('id',$id)->where('request_id',SO_REQ)->find();
    }
    soMust((bool)$sale && (int)$sale['party_id']===$partyId && $sale['settle_method']==='挂账' && $sale['total_amount']==='2.00' && $sale['received_amount']==='0.00','未形成预期本地挂账销售，禁止外发');
    $ar=$qas('erp_receivable')->where('source_type','sale')->where('source_id',$sale['id'])->find();
    $asset=$qas('erp_asset')->where('id',$asset['id'])->find();
    soMust($ar && $ar['amount']==='2.00' && $ar['settled_amount']==='0.00' && !in_array($ar['status'],['settled','void'],true) && $asset['status']==='sold' && (int)$asset['sale_order_id']===(int)$sale['id'],'本地资产、未收应收与原单不一致');
    $target=static fn()=>$qt(WholesaleSyncService::KIND)->where('fact_key','sale:'.$sale['id'].':wholesale-credit')->find();$task=$target();
    if(!$task && $mode==='sale'){
        $events=$qas('erp_outbox_event')->where('event_name','erp.integration.document.confirmed.v1')->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.payload.source_document_id')) = ?",['sale:'.$sale['id']])->select()->toArray();
        soMust(count($events)===1,'未找到该销售单唯一业务事件');
        $reply=ErpIntegrationService::forSite($site,$uid,'博远UAT验收')->dispatchDomainEvent((int)$events[0]['id']);soMust(!empty($reply['ok']),'指定出库事件未能投递');$task=$target();
    }
    soMust((bool)$task,'未找到本机该 UAT 销售任务');$ws=new WholesaleSyncService();
    if($mode==='reconcile'){
        $frozen=Contract::decode($task['request_json']);$h=$frozen['biz']['head'];$seen=[];$count=null;$exactSerial=0;$exactManual=0;$sameTuple=0;
        for($page=1;$page<=1000;$page++){
            $resp=$api->wholesaleDetails($h['business_date'],$page,100);$n=$resp['total_results']??null;$rows=$resp['result_list']??null;
            soMust((is_int($n)||is_string($n)&&ctype_digit($n))&&(int)$n>=0&&(int)$n<=100000&&($count===null||$count===(int)$n) && is_array($rows)&&count($rows)===min(100,(int)$n-count($seen)),'当日销售分页不完整，不能据此判断');
            $count=(int)$n;
            foreach($rows as $row){
                $id=$row['id']??null;soMust(is_string($id)&&$id!==''&&!isset($seen[$id]),'当日销售明细分页重叠');$seen[$id]=true;
                $strings=[];$scan=static function($v)use(&$strings,&$scan){if(is_array($v)){foreach($v as $child)$scan($child);}elseif(is_string($v))$strings[]=$v;};
                $scan($row['imeis']??[]);if(($row['imei']??null)===SO_IMEI||in_array(SO_IMEI,$strings,true))$exactSerial++;
                if(($row['manual_number']??null)===$h['manual_number'])$exactManual++;
                if(($row['bill_type']??null)==='WSA'&&($row['business_date']??null)===$h['business_date']&&($row['cust_id']??null)===$h['cust_id']
                    &&($row['company_id']??null)===$m['company_id']&&($row['warehouse_id']??null)===$h['warehouse_id']&&($row['product_id']??null)===$frozen['lines'][0]['product_id']
                    && in_array($row['qty']??null,[1,'1',1.0],true)&&is_numeric($row['amounts']??null)&&(float)$row['amounts']===2.0&&is_numeric($row['unit_price']??null)&&(float)$row['unit_price']===2.0)$sameTuple++;
            }
            if(count($seen)===$count)break;
        }
        soMust($count===count($seen),'未覆盖完整业务日期');
        soPrint(['read_only'=>true,'site'=>$site,'uat'=>'80004','business_date'=>$h['business_date'],'local_sale'=>$sale['sale_no'],'local_asset_id'=>$asset['id'],'imei'=>SO_IMEI,'total_rows_on_date'=>$count,
            'manual_number_matches'=>$exactManual,'explicit_serial_matches'=>$exactSerial,'matching_full_customer_company_warehouse_product_price_qty'=>$sameTuple,'read_at'=>date(DATE_ATOM),'authorizes_create_retry'=>false]);exit(0);
    }
    $input=['mode'=>'test','connection_key'=>$key,'revision'=>(int)$task['revision']];
    if($mode==='sale'){
        soMust(!file_exists(SO_RECEIPT) && $task['side_effect']==='none' && in_array($task['status'],['awaiting_confirmation','blocked'],true),'出库已发或状态不明，只能 verify/report');
        soMust($inv['checked_at']>=time()-PurchaseInventoryService::MAX_AGE,'当前库存证据已过期，不发送销售单');
        $ws->submit($site,(int)$task['id'],$uid,$input+['customer_id'=>(string)$m['customer_id'],'creator_id'=>(string)$m['creator_id']]);$ws->process($site,(int)$task['id'],$api);$task=$target();
    }
    if($mode==='verify' && $task['status']!=='succeeded'){
        $ws->verify($site,(int)$task['id'],$uid,$input);$ws->process($site,(int)$task['id'],$api);$task=$target();
    }
    $result=Contract::decode($task['result_json']);
    soPrint(['site'=>100003,'uat_account'=>'80004','imei'=>SO_IMEI,'asset_id'=>(int)$asset['id'],'local_purchase'=>$purchase['purchase_no'],'uat_purchase'=>$po['external_no'],
        'original_inventory_checked_at'=>$inv['checked_at'],'local_sale'=>$sale['sale_no'],'asset_status'=>$asset['status'],'ar_id'=>$ar['id'],'ar'=>$ar['amount'],'received'=>$ar['settled_amount'],
        'outbound_task_id'=>(int)$task['id'],'task_status'=>$task['status'],'reason'=>$task['reason'],'uat_sale'=>$task['external_no'],'receipt'=>$result['receipt']??null,'verification'=>$result['verification']??null,'candidate'=>$result['lookup']??null,'real_create_calls_this_run'=>$countPost]);
    if($mode==='sale')soMust($task['status']==='created_review' || $task['status']==='succeeded','不确认远端创建，请核对原单，严禁重发');if($mode==='verify')soMust($task['status']==='succeeded','远端销售未完成独立逐行回读，不重发');
}catch(Throwable $e){
    $reason=$e instanceof PDOException?'数据库错误 '.$e->getCode():($e instanceof Problem||$e instanceof RuntimeException?$e->getMessage():get_class($e).' @ '.basename($e->getFile()).':'.$e->getLine());
    fwrite(STDERR,'未完成：'.$reason.PHP_EOL);exit(1);
}
