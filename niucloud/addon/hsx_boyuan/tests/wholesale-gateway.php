<?php
declare(strict_types=1);
// In-memory v1.5.4 contract test. No database, credentials, DNS or real HTTP implementation is loaded.
if (PHP_SAPI!=='cli') exit("CLI only\n");
foreach (['support/Problem','support/Contract','support/ReceiptFacts','gateway/Transport','gateway/OpenApi','gateway/WholesaleGateway'] as $class) require __DIR__.'/../app/'.$class.'.php';
use addon\hsx_boyuan\app\gateway\{OpenApi,WholesaleGateway};
use addon\hsx_boyuan\app\support\Problem;

$checks=0; $calls=[]; $scenario='ok';
function wsOk(bool $condition,string $msg): void { global $checks; if (!$condition) throw new RuntimeException($msg); ++$checks; }
function wsFail(callable $fn,string $message,string $effect=''): void {
    try { $fn(); } catch (Problem $e) { wsOk($effect==='' || $effect===$e->effect,$message.': '.$e->effect); return; }
    throw new RuntimeException($message.' incorrectly accepted');
}
$profile=['account_id'=>'account-test','app_id'=>'app-test','endpoint'=>'https://open-api-uat.139erp.com/api','gateway_path'=>'/gateway.do'];
$key='test-only-secret-no-network';
$manifest=[['source_line_id'=>'s:1','device_ref'=>'d:1','product_id'=>'p1','imei'=>'000001','amount'=>'10.90'],['source_line_id'=>'s:2','device_ref'=>'d:2','product_id'=>'p1','imei'=>'000002','amount'=>'11.11']];
$request=WholesaleGateway::freeze('TEST-WSA-1','2026-11-17','C1','W1',$manifest,'OPER1','UAT测试制单人');
$expectedHead=['id'=>'H1','bill_id'=>'WSA123','big_gsdm'=>'account-test','bill_type'=>'WSA','cash_term'=>0,'business_date'=>'2026-11-17','manual_number'=>'TEST-WSA-1','warehouse_id'=>'W1','cust_id'=>'C1'];
$expectedRows=[
    ['id'=>'R1','product_id'=>'p1','imei'=>'000001','qty'=>1,'unit_price'=>10.9,'amounts'=>'10.900','seq_no'=>1],
    ['id'=>'R2','product_id'=>'p1','imei'=>'000002','qty'=>1,'unit_price'=>11.11,'amounts'=>'11.11','seq_no'=>2]
];
$exchange=static function(string $path,array $f) use (&$calls,&$scenario,$key,$expectedHead,$expectedRows): array {
    $calls[]=$f['service'];
    wsOk($f['sign']===OpenApi::sign($f,$key) && $f['big_gsdm']==='account-test' && $path==='/gateway.do','Signed scoped request');
    if ($f['service']==='boyuan.biz.bill.sale.generate') {
        if ($scenario==='timeout') throw new RuntimeException('injected timeout');
        $biz=json_decode($f['biz'],true,512,JSON_THROW_ON_ERROR);
        wsOk($biz['head']['cash_term']===0 && $biz['head']['is_auto_generate_cust']===0 && !isset($biz['sale_cash_details']) && $biz['product_details']['add_details'][0]['imei']==='000001','pure credit; retained serial');
        $h=$expectedHead; $rows=$expectedRows;
        if ($scenario==='wrong_account') $h['big_gsdm']='foreign';
        if ($scenario==='wrong_creator') $h['creater_id']='OTHER';
        if ($scenario==='wrong_serial') $rows[0]['imei']='999';
        if ($scenario==='repeat_line') $rows[0]['id']=$rows[1]['id'];
        if ($scenario==='cash') $h['cash_term']=1;
        if ($scenario==='decimal') $rows[0]['amounts']='10.901';
        $data=['result'=>['head'=>$h,'product_details'=>['add_details'=>array_reverse($rows)]]];
    } elseif ($f['service']==='boyuan.biz.sales.query') {
        wsOk(!array_intersect(['biz','bill_id','warehouse_ids','manual_number'],array_keys($f)) && $f['business_date_start']===$f['business_date_end'],'documented date pagination only');
        $rows=[];
        foreach ($expectedRows as $r) {
            unset($r['imei'],$r['seq_no']); // Official SaleDetail omits IMEI. No invention.
            $rows[]=$r+['head_id'=>'H1','company_id'=>'company1','is_effective'=>1]+$expectedHead;
        }
        if ($scenario==='unknown') $rows=[];
        if ($scenario==='wrong_product') $rows[0]['product_id']='p2';
        if ($scenario==='wrong_head') $rows[0]['head_id']='other';
        if ($scenario==='wrong_fee') $rows[0]['amounts']=10.91;
        if ($scenario==='deleted') $rows[0]['is_effective']=0;
        if ($scenario==='wrong_manual') $rows[0]['manual_number']='other';
        if ($scenario==='no_echo') foreach ($rows as &$r) { unset($r['manual_number']); $r['imeis']=[]; } unset($r);
        if ($scenario==='no_echo_other') foreach ($rows as &$r) { unset($r['manual_number']); $r['cust_id']='OTHER'; } unset($r);
        if ($scenario==='similar_serial') foreach ($rows as $k=>&$r) { $r['manual_number']='another-order'; $r['imei']=$expectedRows[$k]['imei']; } unset($r);
        if ($scenario==='locate_conflict') $rows[0]['cust_id']='OTHER';
        if ($scenario==='locate_read_error') throw new RuntimeException('injected read timeout');
        if ($scenario==='conflicting_imei') $rows[0]['imei']='other';
        if ($scenario==='duplicate') $rows[1]['id']='R1';
        if (str_starts_with($scenario,'live_')) {
            foreach($rows as $i=>&$r){unset($r['head_id'],$r['manual_number']);$r['imeis']=[$expectedRows[$i]['imei']];}unset($r);
            if($scenario==='live_other')$rows[0]['imeis']=['100000'];
            if($scenario==='live_duplicate')$rows[0]['imeis']=['000001','000001'];
            if($scenario==='live_empty')$rows[0]['imeis']=[];
            if($scenario==='live_partial_legacy')$rows[0]['head_id']='H1';
            if($scenario==='live_foreign_line')$rows[0]['id']='R3';
            if($scenario==='live_wrong_bill')$rows[0]['bill_id']='WSA124';
        }
        if (str_starts_with($scenario,'bridge_')) {
            foreach($rows as $k=>&$row){unset($row['head_id']);$row['id']='00000000-0000-7000-8000-00000000000'.($k+1);$row['imeis']=[];$row['imei_qty']=1;}unset($row);
            if($scenario==='bridge_wrong_manual')$rows[0]['manual_number']='wrong';
            if($scenario==='bridge_wrong_line')$rows[0]['id']='00000000-0000-7000-8000-000000000003';
            if($scenario==='bridge_wrong_bill')$rows[0]['bill_id']='other';
            if($scenario==='bridge_foreign_head')$rows[0]['head_id']='different';
            if($scenario==='bridge_contradict_scalar')$rows[0]['imei']='111111';
            if($scenario==='bridge_contradict_array')$rows[0]['imeis']=['111111'];
            if($scenario==='bridge_missing_count')unset($rows[0]['imei_qty']);
            if($scenario==='bridge_wrong_count')$rows[0]['imei_qty']=2;
            if($scenario==='bridge_missing_array')unset($rows[0]['imeis']);
            if($scenario==='bridge_missing_manual')unset($rows[0]['manual_number']);
        }
        if ($scenario==='second_bill') $rows[]=['id'=>'other','head_id'=>'H2','bill_id'=>'WSA124']+$rows[0];
        if ($scenario==='paged' || $scenario==='partial_page') {
            $filler=[]; for ($i=0;$i<101;$i++) $filler[]=['id'=>'outside'.$i,'bill_id'=>'else','manual_number'=>'else'];
            $rows=array_merge($filler,array_reverse($rows));
        }
        $page=array_slice($rows,($f['page_no']-1)*$f['page_size'],$f['page_size']);
        if ($scenario==='partial_page') array_pop($page);
        $data=['total_results'=>count($rows),'result_list'=>$page];
    } else throw new LogicException('Unexpected service '.$f['service']);
    return ['return_code'=>'SUCCESS','api_response'=>$data];
};
$gw=new WholesaleGateway(new OpenApi($profile,$key,$exchange,'test'));
$receipt=$gw->create($request);
$result=$gw->verify($request,$receipt);
wsOk($receipt['created'] && $result['verified'] && $result['verification_level']==='wholesale_records_matched' && !$result['stock_balance_verified'] && !$result['financial_posting_verified'],'Evidence scope, no inventory/AR promises');
foreach (['wrong_account','wrong_creator','wrong_serial','repeat_line','cash','decimal'] as $scenario) wsFail(fn()=>$gw->create($request),'Refuse mismatched create '.$scenario,'unknown');
foreach (['wrong_product','wrong_head','wrong_fee','deleted','wrong_manual','conflicting_imei','duplicate','second_bill','unknown','partial_page'] as $scenario) wsFail(fn()=>$gw->verify($request,$receipt),'Refuse wrong independent detail '.$scenario,'unknown');
$scenario='paged'; $r=$gw->verify($request,$receipt); wsOk($r['verified'],'Match second-page results with reversed order');
$scenario='live_exact';wsOk($gw->verify($request,$receipt)['verified'],'Original bill, detail ID, customer, warehouse, amount, and exact live serial all matched');
foreach(['live_other','live_duplicate','live_empty','live_partial_legacy','live_foreign_line','live_wrong_bill'] as $scenario)wsFail(fn()=>$gw->verify($request,$receipt),'Do not infer missing identity '.$scenario,'unknown');
$uat=$receipt;$uat['external_id']='00000000-0000-7000-8000-000000000010';foreach($uat['lines'] as &$l)$l['external_line_id']='00000000-0000-7000-8000-00000000000'.($l['source_line_id']==='s:1'?'1':'2');unset($l);
$scenario='bridge_ok';$checked=$gw->verify($request,$uat);wsOk($checked['verified']&&$checked['serial_evidence']==='linked_via_original_receipt'&&!$checked['query_serials_echoed'],'Exact known bill, manual, row UUID and one serial linked to original; not a second serial echo');
foreach(['bridge_wrong_manual','bridge_wrong_line','bridge_wrong_bill','bridge_foreign_head','bridge_contradict_scalar','bridge_contradict_array','bridge_missing_count','bridge_wrong_count','bridge_missing_array','bridge_missing_manual']as$scenario)wsFail(fn()=>$gw->verify($request,$uat),'Refuse incomplete/contradictory real UAT join '.$scenario,'unknown');
$scenario='ok';$legacy=$request;unset($legacy['biz']['head']['creater_id'],$legacy['biz']['head']['creater_name']);
$before=count($calls);wsFail(fn()=>$gw->create($legacy),'No creator: never reach write','none');wsOk(count($calls)===$before,'Missing creator rejected locally');
wsOk($gw->verify($legacy,$receipt)['verified'],'Old receipt stays independently readable despite no creator in its original request');
foreach([['','valid'],['X',str_repeat('名',22)],['X',"line\nfeed"],['X',' ']] as [$badId,$badName])wsFail(fn()=>WholesaleGateway::freeze('N','2026-11-17','C1','W1',$manifest,$badId,$badName),'Invalid creator on new freeze','none');
$bad=$receipt; $bad['lines'][0]['external_line_id']='identity0'; wsFail(fn()=>$gw->verify($request,$bad),'Cannot join stored receipt with true line ID','unknown');
$bad=$request; $bad['biz']['head']['cash_term']=1; $before=count($calls); wsFail(fn()=>$gw->create($bad),'No immediate payment','none'); wsOk(count($calls)===$before,'Reject before transport');
$bad=$request; $bad['biz']['sale_cash_details']=['add_details'=>[['amounts'=>'1']]]; wsFail(fn()=>$gw->create($bad),'No cash payload','none');
$bad=$request; $bad['biz']['product_details']['add_details'][0]['qty']=2; wsFail(fn()=>$gw->create($bad),'No mixed frozen payload','none');
$scenario='timeout'; wsFail(fn()=>$gw->create($request),'Unknown after network cutoff','unknown');
$before=count($calls); $scenario='ok'; $recovery=$gw->locate($request);
wsOk(!$recovery['verified'] && $recovery['can_resend']===false && $recovery['candidates']===[['external_no'=>'WSA123','external_id'=>'H1']] && array_slice($calls,$before)===['boyuan.biz.sales.query'],'Recovery reads candidate only; no simulated serial or second write');
$scenario='unknown'; $recovery=$gw->locate($request); wsOk(!$recovery['verified'] && !$recovery['candidates'] && !$recovery['can_resend'],'Empty query not permission to resend');
$scenario='second_bill'; $recovery=$gw->locate($request); wsOk(count($recovery['candidates'])===2 && !$recovery['verified'],'Ambiguous manual number not a successful write');
$scenario='bridge_ok';$recovery=$gw->locate($request+['company_id'=>'company1']);
wsOk($recovery['outcome']==='matched_candidates' && $recovery['candidate_count']===1 && $recovery['candidates']===[['external_no'=>'WSA123']]
    && !$recovery['verified'] && !$recovery['can_resend'],'exact manual number on UAT yields only the actual candidate bill, without synthesizing a header ID/serial');
foreach(['similar_serial'=>'no_match','unknown'=>'no_match','no_echo'=>'unidentifiable','no_echo_other'=>'no_match','locate_conflict'=>'unidentifiable',
         'bridge_foreign_head'=>'matched_candidates','bridge_contradict_scalar'=>'unidentifiable','wrong_product'=>'unidentifiable','deleted'=>'unidentifiable'] as $scenario=>$outcome) {
    $recovery=$gw->locate($request);wsOk($recovery['outcome']===$outcome && !$recovery['verified'] && !$recovery['can_resend'],'bounded read-only candidate result: '.$scenario);
    if ($outcome!=='matched_candidates') wsOk($recovery['candidate_count']===0 && $recovery['candidates']===[],'no guessed bill: '.$scenario);
    if ($scenario==='no_echo') wsOk($recovery['missing_manual_number_rows']===2,'lack of join identity is not proof of absence');
}
$scenario='bridge_ok';$recovery=$gw->locate($request+['company_id'=>'another-company']);wsOk($recovery['candidate_count']===0,'candidate scope excludes a foreign company');
$scenario='locate_read_error';$before=count($calls);try {$gw->locate($request);throw new LogicException('unexpected locate success');} catch(Problem $e){wsOk(array_slice($calls,$before)===['boyuan.biz.sales.query'],'read failure not a successful empty result; only read invoked');}
$scenario='ok'; $before=count($calls);
foreach ([['prod',$profile],['test',['endpoint'=>'https://production.invalid']+$profile],['test',['gateway_path'=>'/incorrect']+$profile]] as [$mode,$p]) {
    $other=new WholesaleGateway(new OpenApi($p,$key,$exchange,$mode));
    wsFail(fn()=>$other->create($request),'Deny non-UAT create','none'); wsFail(fn()=>$other->locate($request),'Deny non-UAT read','none');
}
wsOk(count($calls)===$before,'Non-UAT profiles did not invoke exchange');
fwrite(STDOUT,"PASS {$checks} wholesale credit contract checks; injected transport only, no remote writes.\n");
