<?php
declare(strict_types=1);
// One explicitly scoped local site acceptance. Refuses occupied sites. No external transport or bank accounts.
if(PHP_SAPI!=='cli'||ini_get('allow_url_fopen')||count($argv)!==2||!in_array($argv[1],['--isolated-rehearsal','--write-local-100003','--read-local-100003'],true))exit(2);
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client']as$fn)if(function_exists($fn))exit(2);
use addon\hsx_boyuan\app\service\{ConfigService,TaskService};
use addon\hsx_boyuan\app\adminapi\controller\Workbench;
use addon\hsx_erp\app\adminapi\controller\ErpCounterparty;
use addon\hsx_erp\app\service\admin\{ErpCapitalAccountService,ErpFinanceService,ErpGoodsCatalogService,ErpIntegrationService,ErpPurchaseService,ErpSaleService,ErpWarehouseService};
use think\facade\Db;
const MARK='本机验收-100003';
const SERIAL='990000010000033';
const PO_REQ='BY_LOCAL_100003_20261118_PO';
const SO_REQ='BY_LOCAL_100003_20261118_SO';
function must(bool $ok,string $s):void{if(!$ok)throw new RuntimeException($s);}
function callLocal(think\App $app,string $cls,string $method,array $input):array{
    $old=$app->request;
    try{
        $app->instance('request',(new app\Request())->withServer(['REQUEST_METHOD'=>'POST'])->withPost($input));
        $response=(new $cls($app))->$method()->getData();
        must((int)$response['code']===1,'后台操作失败：'.$method);
        return (array)($response['data']??[]);
    }finally{$app->instance('request',$old);}
}
function readSiteCycle(int $site):array{
    $r=static fn($table)=>Db::name($table)->where('site_id',$site);
    $purchase=$r('erp_purchase_order')->where('request_id',PO_REQ)->find(); $sale=$r('erp_sale_order')->where('request_id',SO_REQ)->find();
    must($purchase!==null && $sale!==null,'尚无本次两张单据');
    $asset=$r('erp_asset')->where('imei',SERIAL)->find();
    must($asset && (int)$asset['purchase_order_id']===(int)$purchase['id'] && (int)$asset['sale_order_id']===(int)$sale['id'] && $asset['status']==='sold','原资产与采购单、销售单不一致');
    $ap=$r('erp_payable')->where('source_type','purchase_asset')->where('source_id',$asset['id'])->find();
    $ar=$r('erp_receivable')->where('source_type','sale')->where('source_id',$sale['id'])->find();
    must($ap && $ar && $ap['status']==='settled' && $ap['settled_amount']==='2000.00' && $ar['status']==='settled' && $ar['settled_amount']==='2500.00','收付款未结清');
    $account=$r('erp_capital_account')->where('account_name',MARK.'-专用记账户')->find();
    must($account && $account['balance']==='3500.00','验收户余额不符');
    $tasks=$r('hsx_boyuan_task')->whereIn('business_no',[$purchase['purchase_no'],$sale['sale_no']])->whereIn('kind',['purchase','sale'])->field('id,kind,mode,status,business_no,external_no')->order('id')->select()->toArray();
    must(count($tasks)===2 && array_column($tasks,'mode')===['mock','mock'] && array_column($tasks,'status')===['simulated','simulated'],'模拟账套终态不符');
    $cfg=(new ConfigService())->get($site,false);
    must($cfg['mode']==='mock' && !$cfg['enabled'] && $cfg['paused'] && !$cfg['finance_enabled'] && !$cfg['uat_payment_enabled'],'模拟连接未关闭或财务开关不符');
    foreach(['test','production']as$k)foreach(['endpoint','app_id','token_cipher','app_secret_cipher','account_id','probe_at']as$f)must(empty($cfg['profiles'][$k][$f]),'非模拟环境意外被配置');
    return ['verified'=>true,'site_id'=>$site,'sample_identification'=>MARK,'imei'=>SERIAL,'warehouse'=>$asset['warehouse_name'],'location'=>$asset['location_name'],
        'purchase'=>['id'=>(int)$purchase['id'],'no'=>$purchase['purchase_no'],'supplier'=>$purchase['party_name'],'payable_id'=>(int)$ap['id'],'amount'=>$ap['amount'],'settled'=>$ap['settled_amount']],
        'sale'=>['id'=>(int)$sale['id'],'no'=>$sale['sale_no'],'customer'=>$sale['party_name'],'receivable_id'=>(int)$ar['id'],'amount'=>$ar['amount'],'settled'=>$ar['settled_amount']],
        'stock_status'=>$asset['status'],'stock_movements'=>$r('erp_asset_ledger')->where('asset_id',$asset['id'])->field('action,before_status,after_status,source_no')->order('id')->select()->toArray(),
        'money_account'=>$account['account_name'],'ending_balance'=>$account['balance'],'local_settlements'=>$r('erp_settlement')->where('capital_account_id',$account['id'])->field('id,settlement_no,amount')->order('id')->select()->toArray(),
        'mock_tasks'=>$tasks,'mode'=>'mock','connector_enabled'=>false,'connector_paused'=>true,'bank_or_real_api_requests'=>0];
}
$rehearsal=$argv[1]==='--isolated-rehearsal';$maintenancePdo=null;$tempSchema='';$transaction=false;
try{
    if($rehearsal){
        require __DIR__.'/bootstrap.php';[$app,$maintenancePdo,$tempSchema,$site]=boyuanBoot();(new addon\hsx_boyuan\Addon())->install();$uid=1;
    }else{
        define('LOCAL_CYCLE_INTERNAL_PREFLIGHT',true);require __DIR__.'/site-100003-preflight.php';
        must($site===100003 && $out['site_name']==='测试 2' && $out['active_staff_count']>=1 && $out['first_site_admin_uid']>0,'站点或操作员不符');$uid=(int)$out['first_site_admin_uid'];
        if($argv[1]==='--write-local-100003'){
            foreach($out['counts']as$t=>$count)must($count===0,'已存在资料或结构未就绪，停止：'.$t);
            must(!$out['other_ledger_connector_config_count']&&!$out['boyuan_connection_saved']&&!$out['enabled_printers'],'外部连接、打印或原配置非空，停止');
        }
        // Bind the CLI run to the same LOCAL site database, never initialize or install any other plugin.
        $app->initialize();must(Db::query('SELECT DATABASE() AS n')[0]['n']===$d['database'],'业务连接与本机预检库不一致');
        $app->instance('request',new app\Request());
        request()->siteId($site);request()->uid($uid);request()->username(MARK);request()->appType('adminapi');
    }
    $app->env->set('queue.state',false);
    $cfg=new ConfigService();must($cfg->installed($site),'请先开通本站 ERP 和博远');
    if($argv[1]==='--read-local-100003'){
        Db::execute('SET TRANSACTION READ ONLY');Db::startTrans();$transaction=true;
        $result=readSiteCycle($site);Db::rollback();$transaction=false;
        $result['evidence']='独立 PHP 进程从本机 100003 已提交记录只读复查，业务单据保留；博远是内置模拟，不是真实回执。';
        $result['verified_at']=(new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')))->format(DATE_ATOM);
        $result['local_routes']=['purchase'=>'/site/hsx_erp/purchase','sale'=>'/site/hsx_erp/sale'];
        $path=dirname(__DIR__,4).'/deliverables/boyuan-local-cycle-20261118/site-100003.json';
        must(file_put_contents($path,json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL,LOCK_EX)!==false,'读回成功，但保存验收凭证失败');
    }else{
        $row=static fn(string $t)=>Db::name($t)->where('site_id',$site);
        Db::startTrans();$transaction=true;
        $otherCount=static function()use($site):array{
            $count=[];foreach(['erp_site_catalog_product','erp_warehouse','erp_warehouse_location','erp_capital_account','erp_party','erp_purchase_order','erp_sale_order','erp_asset','erp_payable','erp_receivable','erp_settlement','hsx_boyuan_mock_record','hsx_boyuan_task','hsx_boyuan_mapping','hsx_boyuan_binding']as$t)$count[$t]=(int)Db::name($t)->where('site_id','<>',$site)->count();
            $count['erp_catalog_product_master']=(int)Db::name('erp_catalog_product_master')->where('created_site_id','<>',$site)->count();return $count;
        };$startOther=$otherCount();
        must((bool)Db::name('site')->where('site_id',$site)->lock(true)->find(),'本站缺失');
        foreach(['erp_purchase_order','erp_sale_order','erp_asset','erp_warehouse','erp_warehouse_location','erp_site_catalog_product','erp_party','erp_payable','erp_receivable','erp_capital_account','hsx_boyuan_task','hsx_boyuan_mapping',...array_map([\addon\hsx_boyuan\app\support\CatalogStore::class,'table'],\addon\hsx_boyuan\app\support\CatalogStore::kinds()),'erp_printer']as$t)must(!$row($t)->count(),'站点不再为空：'.$t);
        must(!$row('sys_config')->whereIn('config_key',[ConfigService::KEY,'HSX_KINGDEE_CONNECTION','HSX_KINGDEE_JDY_CONNECTION'])->count(),'不覆盖已存账套配置');
        $c=$cfg->save($site,$uid,['revision'=>0,'mode'=>'mock','enabled'=>true,'paused'=>false,'finance_enabled'=>false,'purchase_enabled'=>true,'purchase_mode'=>'manual','uat_payment_enabled'=>false]);
        $key=$c['profiles']['mock']['connection_key'];
        $warehouse=ErpWarehouseService::forSite($site,$uid);
        $wid=$warehouse->saveWarehouse(['warehouse_name'=>MARK.'-自营仓','warehouse_code'=>'DEMO100003','manager_uid'=>$uid,'warehouse_type'=>'owned','allow_direct_sale'=>1,'allow_transfer'=>0,'remark'=>MARK]);
        $lid=$warehouse->saveLocation($wid,['location_name'=>MARK.'-A1','location_code'=>'A1','remark'=>MARK]);
        $pid=(new ErpGoodsCatalogService())->add(['category_path'=>'本机验收/演示','brand_name'=>MARK,'series_name'=>'测试系列','product_name'=>MARK.'-演示手机','is_enabled'=>1]);
        $supplier=callLocal($app,ErpCounterparty::class,'quickParty',['name'=>MARK.'-供应商','role_type'=>'supplier']);
        $customer=callLocal($app,ErpCounterparty::class,'quickParty',['name'=>MARK.'-同行客户','role_type'=>'customer']);
        $acc=(new ErpCapitalAccountService())->save(['account_name'=>MARK.'-专用记账户','account_type'=>'cash','balance'=>3000,'remark'=>MARK.'，仅合成账面余额']);
        $purchase=ErpPurchaseService::forSite($site,$uid,MARK);
        $in=$purchase->create(['request_id'=>PO_REQ,'party_id'=>$supplier['id'],'party_name'=>$supplier['party_name'],'remark'=>MARK.'采购，不是真实业务','settle_method'=>'挂账','items'=>[['imei'=>SERIAL,
            'catalog_product_id'=>$pid,'model'=>MARK.'-演示手机','spec'=>'256GB','color'=>'黑色','purchase_cost'=>2000,'retail_price'=>2500,'warehouse_id'=>$wid,'location_id'=>$lid,'image_urls'=>'["/addon/hsx_erp/resource/icon.png"]']]]);
        $integrations=ErpIntegrationService::forSite($site,$uid,MARK);
        $mapping=static function(string $kind,string $local,string $name) use($app,$key,$row) {
            if($row('hsx_boyuan_mapping')->where('connection_key',$key)->where('kind',$kind)->where('local_ref',$local)->count())return;
            $ref='DEMO100003_'.strtoupper($kind).'_'.substr(hash('sha256',$local),0,10);
            callLocal($app,Workbench::class,'saveMapping',['kind'=>$kind,'local_ref'=>$local,'local_name'=>MARK.' '.$name,'external_id'=>$ref,'external_code'=>$ref,'external_name'=>MARK.'模拟 '.$name,
                'mode'=>'mock','connection_key'=>$key,'extra'=>$kind==='product'?['unit_code'=>'TAI']:[]]);
        };
        $runDoc=static function(string $kind,int $id)use($site,$uid,$integrations,$row,$mapping,$key):void{
            $fact=null;
            foreach($row('erp_outbox_event')->where('event_name','erp.integration.document.confirmed.v1')->order('id')->select()->toArray() as$e){
                $event=json_decode($e['payload_json'],true,512,JSON_THROW_ON_ERROR);$s=$event['payload'];
                if($s['kind']!==$kind||$s['source_document_id']!==$kind.':'.$id)continue;$fact=$s;
                foreach(TaskService::missingMappings($site,$key,$fact)as$m)$mapping($m['kind'],$m['ref'],$m['name']?:$m['kind']);
                $reply=$integrations->dispatchDomainEvent((int)$e['id']);
                must(!empty($reply['ok']),'本站 ERP 单据事件投递不成功');
                break;
            }
            must($fact!==null,'缺少原采购/销售事件');
            $service=new TaskService();$deadline=microtime(true)+10;
            do{
                $task=$row('hsx_boyuan_task')->where('connection_key',$key)->where('kind',$kind)->where('business_no',$fact['source_document_no'])->find();
                must($task && $task['mode']==='mock','未捕获可见的模拟单据任务（检查本地事件注册）');
                must(!$row('hsx_boyuan_task')->where('mode','<>','mock')->count(),'非模拟任务出现，拒绝执行');
                if($task['status']==='simulated')return;
                if($task['status']==='blocked')$service->action($site,(int)$task['id'],$uid,'retry');
                $service->tick($site,20);
                usleep(180000);
            }while(microtime(true)<$deadline);
            throw new RuntimeException('模拟任务未完成：'.$kind.' '.$task['status'].' '.($task['reason']??''));
        };
        $runDoc('purchase',$in);
        $sale=ErpSaleService::forSite($site,$uid,MARK);
        $stock=$sale->stockPage(['imei'=>SERIAL,'catalog_product_id'=>$pid,'warehouse_id'=>$wid,'location_id'=>$lid]);
        must((int)$stock['total']===1 && $stock['data'][0]['imei']===SERIAL,'采购后不可按指定仓库串号选中');
        $assetId=(int)$stock['data'][0]['id'];
        $outId=$sale->create(['request_id'=>SO_REQ,'sale_channel_key'=>'erp_peer','party_id'=>$customer['id'],'party_name'=>$customer['party_name'],'settle_method'=>'挂账','remark'=>MARK.'销售，不是真实业务',
            'items'=>[['asset_id'=>$assetId,'sale_price'=>2500,'remark'=>MARK]]]); $runDoc('sale',$outId);
        $ap=$row('erp_payable')->where('source_type','purchase_asset')->where('source_id',$assetId)->find();
        $ar=$row('erp_receivable')->where('source_type','sale')->where('source_id',$outId)->find();
        must($ap && $ar && $ap['settled_amount']==='0.00' && $ar['settled_amount']==='0.00' && $ap['amount']==='2000.00' && $ar['amount']==='2500.00','原单未形成预期挂账');
        $fin=ErpFinanceService::forSite($site,$uid,MARK);
        $fin->confirmPayment((int)$ap['id'],2000,['request_id'=>'BY_LOCAL_100003_PAYMENT','capital_account_id'=>$acc,'remark'=>MARK.'付款登记，不转账']);
        $fin->confirmReceipt((int)$ar['id'],2500,['request_id'=>'BY_LOCAL_100003_RECEIPT','capital_account_id'=>$acc,'remark'=>MARK.'收款登记，不收真实款',
            'items'=>[['sale_item_id'=>(int)$row('erp_asset')->where('id',$assetId)->value('sale_item_id'),'sale_price'=>2500,'amount'=>2500]]]);
        $cfg->save($site,$uid,['revision'=>$cfg->get($site,false)['revision'],'mode'=>'mock','enabled'=>false,'paused'=>true,'finance_enabled'=>false,'purchase_enabled'=>false,'uat_payment_enabled'=>false]);
        $result=readSiteCycle($site);must($otherCount()===$startOther,'其他站点的业务行数发生变更；整笔回滚');
        Db::commit();$transaction=false;
    }
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;
}catch(Throwable $e){
    if($transaction)Db::rollback();
    fwrite(STDERR,'未完成: '.get_class($e).' '.($e instanceof PDOException?'PDO '.$e->getCode():$e->getMessage()).' @ '.basename($e->getFile()).':'.$e->getLine().PHP_EOL);
    $failed=true;
}finally{if($maintenancePdo && preg_match('/^hsx_boyuan_test_[a-f0-9]{16}$/D',$tempSchema))$maintenancePdo->exec('DROP DATABASE `'.$tempSchema.'`');}
exit(empty($failed)?0:1);
