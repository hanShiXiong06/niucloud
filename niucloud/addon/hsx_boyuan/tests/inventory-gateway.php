<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit("CLI only\n");
// Deliberately no framework, database or real HttpTransport bootstrap.
require __DIR__.'/../app/support/Problem.php';
require __DIR__.'/../app/support/Contract.php';
require __DIR__.'/../app/gateway/Transport.php';
require __DIR__.'/../app/gateway/OpenApi.php';
require __DIR__.'/../app/gateway/InventoryGateway.php';

use addon\hsx_boyuan\app\gateway\{OpenApi,InventoryGateway};
use addon\hsx_boyuan\app\support\{Contract,Problem};

$checks=0; $calls=0;
function igRule(bool $ok,string $label): void {global $checks; if(!$ok) throw new RuntimeException($label); $checks++;}
function igDenied(callable $fn,string $label,?string $reason=null): void
{
    try {$fn();} catch(Problem $e) {igRule(($reason===null || $e->reason===$reason) && $e->effect==='none',$label.' ['.$e->reason.']'); return;}
    throw new RuntimeException($label.' accepted');
}
function igRequest(): array
{
    $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
    return ['schema'=>InventoryGateway::SCHEMA,'source_task_id'=>7,'source_snapshot_hash'=>str_repeat('a',64),'connection_key'=>'FROZEN_CONNECTION',
        'company_id'=>'COMPANY1','company_code'=>'00000800001','query_date'=>$today,'lines'=>[
            ['source_line_id'=>'purchase_line:1','device_ref'=>'asset:1','imei'=>'000000000000001','product_id'=>'P1','warehouse_id'=>'W1','supplier_id'=>'S1','quantity'=>'1'],
            ['source_line_id'=>'purchase_line:2','device_ref'=>'asset:2','imei'=>'SN中文0002','product_id'=>'P2','warehouse_id'=>'W1','supplier_id'=>'S1','quantity'=>'1'],
        ]];
}
function igRows(array $request): array
{
    $rows=[];
    foreach($request['lines'] as $line) $rows[$line['imei']]=[['gsdm'=>$request['company_code'],'company_id'=>$request['company_id'],
        'imei'=>$line['imei'],'imei1'=>$line['imei'],'imei2'=>null,'imei3'=>'','warehouse_id'=>$line['warehouse_id'],
        'supplier_id'=>$line['supplier_id'],'product_id'=>$line['product_id'],'qty'=>1,'imei_in_stock_status'=>1,
        'cost_unit'=>123.45,'pur_unit_price'=>120,'private_contact_phone'=>'13800000000','bank_name'=>'PRIVATE_BANK']];
    return $rows;
}
function igApi(array $rows,array &$wire,?Closure $override=null): OpenApi
{
    return new OpenApi(['app_id'=>'123','account_id'=>'ACCOUNT1','gateway_path'=>'/gateway.do'],'FAKE_SECRET_INVENTORY',
        function(string $path,array $params) use($rows,&$wire,$override): array {
            global $calls; $calls++; $wire[]=['path'=>$path,'params'=>$params];
            if($override) {$result=$override($params); if($result!==null) return $result;}
            if(!in_array($params['service'],['boyuan.biz.inventories.query','boyuan.biz.inventory.imeidetails.query'],true)) throw new RuntimeException('Unexpected non-inventory service');
            $data=$rows[$params['imei']??'aggregate']??[];
            return ['return_code'=>'SUCCESS','api_response'=>['total_results '=>count($data),
                'result_list'=>array_slice($data,((int)$params['page_no']-1)*(int)$params['page_size'],(int)$params['page_size'])]];
        });
}

try {
    $request=igRequest(); $rows=igRows($request); $wire=[]; $api=igApi($rows,$wire); $gateway=new InventoryGateway($api); $result=$gateway->verify($request);
    igRule($result['verified'] && $result['current_stock_verified'] && $result['in_stock_count']===2 && count($result['matched_lines'])===2,'only a complete batch returns verified stock evidence');
    igRule($result['verification_level']==='batch_current_stock_records_matched' && $result['query_date']===$request['query_date'] && is_int($result['checked_at']),'evidence has explicit scope, local query date and check timestamp');
    igRule($result['matched_lines'][0]['imei']==='000000000000001' && $result['matched_lines'][1]['imei']==='SN中文0002','raw serials and leading zeros are preserved exactly');
    igRule(!$result['evidence']['aggregate_verified'] && !$result['evidence']['whole_warehouse_reconciled'] && !$result['evidence']['future_saleability_verified'] && !$result['evidence']['financial_posting_verified'],'batch stock does not claim warehouse reconciliation, future availability or financial completion');
    igRule(!str_contains(Contract::json($result),'13800000000') && !str_contains(Contract::json($result),'PRIVATE_BANK') && !str_contains(Contract::json($result),'cost_unit'),'public stock evidence excludes arbitrary sensitive and cost fields');
    igRule(count($wire)===2 && $wire[0]['params']['imei']===$request['lines'][0]['imei'] && $wire[1]['params']['imei']===$request['lines'][1]['imei'],'queries use the full serial of each frozen device, never a broad approximate search');
    $first=$wire[0]['params'];
    igRule($first['query_date']===$request['query_date'] && $first['gsdm']===$request['company_code'] && $first['product_ids']==='P1' && $first['warehouse_ids']==='W1' && !isset($first['query_date_start']),'documented query_date and exact company/product/warehouse filters are used');
    igRule($first['sign']===OpenApi::sign($first,'FAKE_SECRET_INVENTORY') && $first['big_gsdm']==='ACCOUNT1','dedicated inventory reads reuse common signing and cannot replace account identity');
    igDenied(fn()=>$api->call('boyuan.biz.inventory.imeidetails.query'),'generic entry remains reference-only','READ_ONLY_ONLY');
    igDenied(fn()=>$api->inventoryQuery('payment',['query_date'=>$request['query_date']]),'inventory kind cannot query payment bills','INVENTORY_QUERY_INVALID');
    igDenied(fn()=>$api->inventoryQuery('boyuan.trade.unified.pay',[]),'inventory kind cannot call arbitrary service','INVENTORY_QUERY_INVALID');
    foreach(['service','app_id','big_gsdm','sign','timestamp','bill_id','bill_type','query_date_start'] as $field) {
        $before=count($wire); igDenied(fn()=>$api->inventoryQuery('imei',['query_date'=>$request['query_date'],$field=>'OVERRIDE']),'inventory rejects forbidden parameter '.$field,'PARAM_INVALID');
        igRule(count($wire)===$before,'forbidden parameter '.$field.' never reaches fake exchange');
    }
    igDenied(fn()=>$api->inventoryQuery('imei',[]),'inventory requires query_date','INVENTORY_DATE_INVALID');
    igDenied(fn()=>$api->inventoryQuery('imei',['query_date'=>'2026-02-30']),'inventory rejects impossible date','INVENTORY_DATE_INVALID');
    $api->inventoryQuery('aggregate',['query_date'=>$request['query_date'],'gsdm'=>$request['company_code'],'product_ids'=>'P1','warehouse_ids'=>'W1','has_imei'=>1,'show_zero'=>false,'group_type'=>'gsid,full_name','page_no'=>1,'page_size'=>100]);
    $last=$wire[count($wire)-1]['params'];
    igRule($last['service']==='boyuan.biz.inventories.query' && $last['group_type']==='gsid,full_name' && $last['show_zero']===false,'aggregate endpoint is a second explicit read whitelist and keeps zero-valued filters');

    foreach(['yesterday','future','schema','hash','company','supplier','quantity','duplicate_source','duplicate_device','duplicate_serial','extra','empty'] as $case) {
        $bad=$request;
        if($case==='yesterday') $bad['query_date']='2000-01-01';
        if($case==='future') $bad['query_date']='2999-01-01';
        if($case==='schema') $bad['schema']='unfrozen';
        if($case==='hash') $bad['source_snapshot_hash']='not-a-hash';
        if($case==='company') $bad['company_id']=123;
        if($case==='supplier') unset($bad['lines'][0]['supplier_id']);
        if($case==='quantity') $bad['lines'][0]['quantity']='2';
        if($case==='duplicate_source') $bad['lines'][1]['source_line_id']=$bad['lines'][0]['source_line_id'];
        if($case==='duplicate_device') $bad['lines'][1]['device_ref']=$bad['lines'][0]['device_ref'];
        if($case==='duplicate_serial') $bad['lines'][1]['imei']=$bad['lines'][0]['imei'];
        if($case==='extra') $bad['service']='boyuan.trade.unified.pay';
        if($case==='empty') $bad['lines']=[];
        $before=count($wire); igDenied(fn()=>$gateway->verify($bad),'invalid frozen inventory request '.$case);
        igRule(count($wire)===$before,'invalid request '.$case.' never reaches exchange');
    }
    $imei=$request['lines'][0]['imei'];
    foreach(['missing','extra','duplicate','company','gsdm','warehouse','product','supplier','imei1','imei','quantity','status','transit','boolstatus','secondserial','alias','ambiguouskey'] as $case) {
        $bad=$rows;
        if($case==='missing') $bad[$imei]=[];
        if($case==='extra') {$row=$bad[$imei][0]; $row['imei']=$row['imei1']='UNKNOWN_DEVICE'; $bad[$imei][]=$row;}
        if($case==='duplicate') $bad[$imei][]=$bad[$imei][0];
        foreach(['company'=>'company_id','gsdm'=>'gsdm','warehouse'=>'warehouse_id','product'=>'product_id','supplier'=>'supplier_id','imei1'=>'imei1','imei'=>'imei'] as $name=>$field) if($case===$name) $bad[$imei][0][$field]='WRONG';
        if($case==='quantity') $bad[$imei][0]['qty']=0;
        if($case==='status') unset($bad[$imei][0]['imei_in_stock_status']);
        if($case==='transit') $bad[$imei][0]['imei_in_stock_status']=2;
        if($case==='boolstatus') $bad[$imei][0]['imei_in_stock_status']=true;
        if($case==='secondserial') $bad[$imei][0]['imei2']='SECOND';
        if($case==='alias') {$bad[$imei][0]['company_code']=$bad[$imei][0]['gsdm']; unset($bad[$imei][0]['gsdm']);}
        if($case==='ambiguouskey') $bad[$imei][0]['company_id ']='OTHER';
        $w=[]; $g=new InventoryGateway(igApi($bad,$w)); igDenied(fn()=>$g->verify($request),'stock response rejects '.$case);
        igRule(!in_array('boyuan.biz.bill.purchase.generate',array_column(array_column($w,'params'),'service'),true),'stock failure '.$case.' cannot create or pay');
    }
    // A full-serial total above one already proves ambiguity; never extend a worker lease.
    $many=$rows; $many[$imei]=[];
    for($i=0;$i<101;$i++) {$row=$rows[$imei][0]; $row['imei']=$row['imei1']='UNEXPECTED_'.$i; $many[$imei][]=$row;}
    $w=[]; $g=new InventoryGateway(igApi($many,$w)); igDenied(fn()=>$g->verify($request),'complete broad result cannot be truncated to one device','INVENTORY_DEVICE_AMBIGUOUS');
    igRule(count($w)===1 && $w[0]['params']['page_no']===1,'ambiguous declared pagination fails in one call instead of accepting a partial page');
    foreach(['empty','overfull','missingtotal','zeroconflict','stringtotal','negative'] as $case) {
        $w=[]; $g=new InventoryGateway(igApi($rows,$w,static function(array $params) use($rows,$imei,$case): array {
            if($case==='missingtotal') return ['return_code'=>'SUCCESS','api_response'=>['result_list'=>[]]];
            $data=['total_results'=>1,'result_list'=>$rows[$imei]];
            if($case==='empty') $data['result_list']=[];
            if($case==='overfull') $data['result_list'][]=$rows[$imei][0];
            if($case==='zeroconflict') $data['total_results']=0;
            if($case==='stringtotal') $data['total_results']='01';
            if($case==='negative') $data['total_results']=-1;
            return ['return_code'=>'SUCCESS','api_response'=>$data];
        }));
        igDenied(fn()=>$g->verify($request),'inventory pagination rejects '.$case);
    }
    $large=$request; $large['lines']=[];
    for($i=1;$i<=7;$i++) {$line=$request['lines'][0]; $line['source_line_id']='purchase_line:'.$i; $line['device_ref']='asset:'.$i; $line['imei']='00000000000000'.$i; $large['lines'][]=$line;}
    $w=[]; $g=new InventoryGateway(igApi(igRows($large),$w)); $steps=[]; $offset=0;
    do {
        $before=count($w); $step=$g->verifyStep($large,$offset); $steps[]=$step; $offset=$step['next_offset'];
        igRule(!$step['verified'] && !$step['current_stock_verified'] && $step['step_verified'] && count($w)-$before<=3,'bounded step never labels a subset or final cursor as complete batch verification');
    } while($offset!==null);
    igRule(count($steps)===3 && count($w)===7 && $steps[0]['matched_count']===3 && $steps[2]['matched_count']===1,'seven devices take three bounded steps with at most one HTTP call per device');
    $steps[0]['checked_at']=time()-30; $steps[1]['checked_at']=time()-10; $before=count($w);
    $complete=$g->complete($large,$steps);
    igRule($complete['verified'] && $complete['in_stock_count']===7 && $complete['checked_at']===$steps[0]['checked_at'] && count($w)===$before,'only pure full-evidence completion returns batch success and uses the oldest check timestamp');
    foreach(['missing','duplicate','skip','scope','identity','quantity','state','flag','expired','future','cursor','subset'] as $case) {
        $bad=$steps; $manifest=$large;
        if($case==='missing') array_pop($bad);
        if($case==='duplicate') $bad[1]=$bad[0];
        if($case==='skip') array_shift($bad);
        if($case==='scope') $bad[1]['scope_hash']=str_repeat('f',64);
        if($case==='identity') $bad[0]['matched_lines'][0]['imei']='ANOTHER_SERIAL';
        if($case==='quantity') $bad[0]['matched_lines'][0]['qty']='2';
        if($case==='state') $bad[0]['matched_lines'][0]['imei_in_stock_status']=2;
        if($case==='flag') $bad[0]['verified']=true;
        if($case==='expired') $bad[0]['checked_at']=time()-InventoryGateway::MAX_EVIDENCE_AGE-1;
        if($case==='future') $bad[0]['checked_at']=time()+60;
        if($case==='cursor') $bad[2]['next_offset']=7;
        if($case==='subset') array_pop($manifest['lines']);
        $before=count($w); igDenied(fn()=>$g->complete($manifest,$bad),'step completion rejects '.$case);
        igRule(count($w)===$before,'invalid accumulated evidence '.$case.' cannot trigger an HTTP call');
    }
    igDenied(fn()=>$g->verifyStep($large,0,4),'step cannot exceed the three-device lease budget','INVENTORY_STEP_INVALID');
    igDenied(fn()=>$g->verifyStep($large,7),'step cannot start after the full manifest','INVENTORY_STEP_INVALID');
    igRule(!class_exists('think\\facade\\Db',false) && !class_exists('addon\\hsx_boyuan\\app\\gateway\\HttpTransport',false),'test loads no database facade or real network transport');
    echo Contract::json(['passed'=>true,'checks'=>$checks,'fake_exchanges'=>$calls,'external_calls'=>0,'database_access'=>false])."\n";
} catch(Throwable $e) {
    fwrite(STDERR,'FAIL '.get_class($e).': '.($e instanceof Problem?$e->reason:$e->getMessage()).' at '.basename($e->getFile()).':'.$e->getLine()."\n"); exit(1);
}
