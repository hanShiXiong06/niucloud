<?php
declare(strict_types=1);

/** Called only by the disposable, offline ERP lifecycle with --guard-suite. */
use addon\hsx_boyuan\app\gateway\OpenApi;
use addon\hsx_boyuan\app\service\{ConfigService,TaskService,WholesaleSyncService};
use addon\hsx_boyuan\app\support\{Contract,Problem};
use addon\hsx_erp\app\service\admin\{ErpIntegrationDocumentService,ErpIntegrationService,ErpSaleReturnService};
use think\facade\Db;

function cycleWholesaleGuards(int $site,int $taskId,string $connection,OpenApi $base,ConfigService $cfg,string $account,callable $version,callable $task): array
{
    $db=Db::query('SELECT DATABASE() AS name')[0]['name'];
    if (!preg_match('/^hsx_boyuan_test_[0-9a-f]{16}$/D',$db) || !in_array('--guard-suite',$GLOBALS['argv'],true) || function_exists('curl_exec') || ini_get('allow_url_fopen')) throw new LogicException('Guard suite requires offline disposable schema');
    $service=new WholesaleSyncService(); $worker=new TaskService(); $baseRow=$task($taskId);
    $sale=Contract::decode($baseRow['snapshot_json']); $saleId=(int)substr($sale['source_document_id'],5);
    $assetId=(int)substr($sale['lines'][0]['device_ref'],6);
    $assertions=0; $cases=[]; $calls=[]; $wireMode='ok'; $created=null; $callback=null;
    $check=static function(bool $ok,string $label) use (&$assertions): void { cycleAssert($ok,$label); ++$assertions; };
    $denied=static function(callable $fn,string $reason) use ($check): void {
        try { $fn(); } catch (Problem $e) { $check($e->reason===$reason,'应为 '.$reason.'，实为 '.$e->reason); return; }
        throw new RuntimeException('应拒绝 '.$reason);
    };
    $profile=$cfg->get($site,false)['profiles']['test']; $testSecret=str_repeat('N',32);
    $check($base->accountId()===$account && $base->purchaseDialect()===OpenApi::PURCHASE_DIALECT_UAT
        && $connection===$profile['connection_key'] && $baseRow['status']==='awaiting_confirmation','从正确隔离账套的一张未外发销售单开始');
    $fake=new OpenApi($profile,$testSecret,static function(string $path,array $params) use ($account,$testSecret,$check,&$wireMode,&$calls,&$created,&$callback): array {
        $check($path==='/gateway.do' && $params['big_gsdm']===$account && $params['sign']===OpenApi::sign($params,$testSecret),'仅使用隔离签名');
        $op=$params['service']; $calls[]=$op;
        if ($op==='boyuan.biz.bill.sale.generate') {
            $check(!isset($created),'一次实验只可创建一个外部单据');
            $r=Contract::decode($params['biz']); $line=$r['product_details']['add_details'][0];
            $created=['head'=>$r['head']+['big_gsdm'=>$account,'bill_id'=>'WSA_NEGATIVE','bill_type'=>'WSA','id'=>'NEGATIVE_HEAD'],
                'product_details'=>['add_details'=>[$line+['id'=>'NEGATIVE_LINE']]]];
            if ($callback) $callback(); // The user's correction can land while the write is in flight.
            if ($wireMode==='after_accept_timeout') throw new RuntimeException('Fixture accepted credit sale; no response arrived');
            return ['return_code'=>'SUCCESS','api_response'=>['result'=>$created]];
        }
        if ($op!=='boyuan.biz.sales.query') throw new LogicException('Unsupported fixture operation');
        $rows=[];
        if ($created!==null) {
            $h=$created['head']; $row=$created['product_details']['add_details'][0];
            unset($row['imei']); // Unlike the create response, genuine SaleDetail omits the serial.
            $rows[]=$row+['head_id'=>$h['id'],'is_effective'=>1,'company_id'=>'COMP01']+$h;
        }
        return ['return_code'=>'SUCCESS','api_response'=>['total_results'=>count($rows),'result_list'=>$rows]];
    },'test');
    $scope=static function(string $name,callable $fn) use ($check,$task,$taskId,$cfg,$site,$baseRow,&$wireMode,&$calls,&$created,&$callback,&$cases): void {
        $calls=[]; $wireMode='ok'; $created=null; $callback=null;
        Db::startTrans();
        try { $fn(); $cases[]=$name; } finally { Db::rollback(); }
        $check($task($taskId)===$baseRow && $cfg->get($site,false)['mode']==='test','回滚恢复原始未外发销售任务');
    };
    $submit=static function() use ($site,$taskId,$version,$task,$service) { return $service->submit($site,$taskId,1,$version($task($taskId))); };
    $run=static function() use ($site,$taskId,$worker,$fake) { return $worker->process($site,$taskId,$fake); };
    $query=static function() use ($site,$taskId,$version,$task,$service,$run) {
        $r=$service->verify($site,$taskId,1,$version($task($taskId)));
        cycleAssert($r['status']==='confirming' && $run(),'核对使用真正的任务分派器');
    };
    $void=static function() use ($site,$sale,$saleId,$check): void {
        ErpIntegrationDocumentService::invalidated($site,'sale',$saleId,$sale['source_document_no'],'GUARD: 作废','void-test');
        $q=Db::name('erp_outbox_event')->where('site_id',$site)->where('event_name','erp.integration.document.invalidated.v1');
        $eventId=(int)$q->order('id desc')->value('id');
        $check($eventId>0,'实际生成原销售撤销事件');
        $sent=ErpIntegrationService::forSite($site,1,'隔离验收')->dispatchDomainEvent($eventId);
        $check(!empty($sent['ok']),'撤销事件从 ERP outbox 派发');
    };
    $returned=static function() use ($site,$saleId,$assetId,$check): void {
        $returnIds=(new ErpSaleReturnService())->createAndConfirm(['sale_order_id'=>$saleId,'request_id'=>'BOYUAN_GUARD_RETURN_01',
            'reason'=>'隔离收回设备','remark'=>'隔离回归','items'=>[['asset_id'=>$assetId,'reason'=>'隔离收回设备']]]);
        $check(count($returnIds)===1,'ERP 确认实际销售退货单');
        cycleDispatch($site);
    };
    $scope('待发送时原销售撤销',static function() use ($site,$taskId,$baseRow,$version,$service,$denied,$submit,$run,$task,$void,$check,&$calls) {
        $initial=$version($baseRow);
        $submit(); $denied(fn()=>$service->submit($site,$taskId,1,$initial),'TASK_STALE');
        $denied($submit,'SALE_ALREADY_SENT');
        $void(); $r=$task($taskId);
        $check($r['status']==='blocked' && $r['resolution']==='source_changed' && $r['side_effect']==='none','撤销后停止待外发任务');
        $denied($submit,'SALE_SOURCE_CHANGED'); $check(!$run() && $calls===[],'已撤销单不触发网络调用');
    });
    $scope('服务商受理后回包超时',static function() use ($taskId,$submit,$run,$query,$task,$check,$denied,&$wireMode,&$calls) {
        $wireMode='after_accept_timeout'; $submit(); $check($run(),'处理一次发送');
        $r=$task($taskId); $check($r['status']==='attention' && $r['side_effect']==='unknown','超时必须保持待核实');
        $denied($submit,'SALE_ALREADY_SENT'); $check(!$run(),'普通工作任务不重发');
        $query(); $query(); $r=$task($taskId); $result=Contract::decode($r['result_json']);
        $check($r['status']==='needs_review' && $r['side_effect']==='unknown' && ($result['lookup']['can_resend']??null)===false
            && empty($result['lookup']['verified']) && ($result['lookup']['candidates'][0]['external_no']??'')==='WSA_NEGATIVE','查到候选单不代表证实原始串号与出库');
        $check($calls===['boyuan.biz.bill.sale.generate','boyuan.biz.sales.query','boyuan.biz.sales.query'],'超时后反复核对只走查询');
    });
    $scope('出库请求中途确认退货',static function() use ($taskId,$submit,$run,$query,$task,$returned,$check,$denied,&$calls,&$callback) {
        $callback=$returned; $submit(); $check($run(),'退货并发时仍保存原始外部回执');
        $r=$task($taskId); $result=Contract::decode($r['result_json']);
        $check($r['status']==='needs_review' && $r['resolution']==='source_changed' && ($result['receipt']['external_no']??'')==='WSA_NEGATIVE','在途回执不覆盖已退货标记');
        $denied($submit,'SALE_SOURCE_CHANGED'); $query(); $r=$task($taskId);
        $check($r['status']==='needs_review' && $r['resolution']==='source_changed','即使博远原始销售仍有效，本地退货仍待人工核对');
        $check($calls===['boyuan.biz.bill.sale.generate','boyuan.biz.sales.query'],'未发送反向单据或第二次销售');
    });
    $scope('已核对成功后实际退货',static function() use ($taskId,$submit,$run,$query,$task,$returned,$check,$denied,&$calls) {
        $submit(); $check($run(),'正式处理器取得模拟回单'); $query();
        $original=$task($taskId); $check($original['status']==='succeeded','退款前状态已核对');
        $returned(); $r=$task($taskId);
        $check($r['status']==='needs_review' && $r['resolution']==='source_changed' && $r['external_no']===$original['external_no']
            && $r['result_json']===$original['result_json'],'退货后保留原运行业务证据并显示待人工核对');
        $denied($submit,'SALE_SOURCE_CHANGED');
        $check($calls===['boyuan.biz.bill.sale.generate','boyuan.biz.sales.query'],'退货后不自动创建冲销单');
    });
    $scope('本地模拟生产配置不得执行测试任务',static function() use ($site,$cfg,$submit,$run,$check,$denied,&$calls) {
        $submit();
        // Isolated configuration ONLY: empty and disabled, no real production URL or credentials.
        $cfg->save($site,1,['revision'=>$cfg->get($site,false)['revision'],'mode'=>'production','enabled'=>false,'paused'=>true,'purchase_enabled'=>false]);
        $check(empty($cfg->get($site,false)['profiles']['production']['endpoint']),'隔离生产模式无外部地址');
        $denied($submit,'SALE_UAT_ONLY'); $denied($run,'SALE_UAT_ONLY');
        $check($calls===[],'生产模式未产生任何写入或查询');
    });
    return ['assertions'=>$assertions,'cases'=>$cases,'real_external_requests'=>0,'isolated_database'=>$db,'fixture_state_restored'=>true];
}
