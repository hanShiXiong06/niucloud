<?php
declare(strict_types=1);
// Actual ThinkPHP Log file channel, but private temp directory, fixed fixtures, no DB/network.
if(PHP_SAPI!=='cli') exit(2);
require dirname(__DIR__,3).'/vendor/autoload.php';
use addon\hsx_boyuan\app\support\{ExchangeJournal,InterfaceTrace,Problem};
$app=new think\App();$app->initialize();
$root=sys_get_temp_dir().'/boyuan-journal-'.bin2hex(random_bytes(8));
$key='private-test-connection';$day=date('Y-m-d');$n=0;
$check=static function($s,$label)use(&$n){if(!$s)throw new RuntimeException($label);++$n;};
$writer=static fn($row)=>ExchangeJournal::record(123,'test',$key,$row,$root);
try {
    $trace=new InterfaceTrace(123,'test',['kind'=>'purchase','task_id'=>17,'business_no'=>'PO-FIXTURE'],static fn()=>'', $writer);
    for($i=0;$i<31;$i++){
        $a=$trace->begin('POST','purchase_create',['service'=>'boyuan.biz.bill.purchase.generate','sign'=>'SECRET-ABCD','biz'=>['head'=>['remark'=>'PERSON','cash_term'=>0],'product_details'=>[['imei'=>'990000000012345','unit_price'=>'1.00']]]]);
        $trace->end($a,'response_received',200,0,['return_code'=>'SUCCESS','api_response'=>['result'=>['head'=>['bill_id'=>'PUR-'.$i,'help_code'=>'13800000123']]]],'json','');
    }
    $only=$trace->begin('POST','purchase_create',['biz'=>['bill_id'=>'pending-on-wire']]);
    $today=date('Y-m-d',intdiv($only[0]['sent_ms'],1000));
    $all=[];$cursor='';$pages=0;
    do{
        $res=ExchangeJournal::read($root,123,'test',$key,$today,'T123-17',$cursor);
        foreach($res['rows'] as $r) if(!isset($all[$r['call_id']]) || $r['stage']==='result') $all[$r['call_id']]=$r;
        $cursor=$res['next'];if(++$pages>12)throw new RuntimeException('cursor loop');
    }while($cursor!=='');
    $check(count($all)===32 && $pages>1,'paged without losing pairs');
    $check($all[$only[0]['call_id']]['stage']==='request','lack of response not success');
    $report=json_encode($all);$check(!preg_match('/PERSON|SECRET-ABCD|13800000123|990000000012345/',$report),'credentials and private facts omitted');
    $check(str_contains($report,'PUR-30') && str_contains($report,'request_payload') && str_contains($report,'finished_ms'),'bill id/request/response/time preserved without trace window');
    foreach([[124,'test',$key],[123,'production',$key],[123,'test','another']] as [$s,$m,$k])
        $check(!ExchangeJournal::read($root,$s,$m,$k,$today,'','')['rows'],'tenant/mode/connection isolation');
    $check(!ExchangeJournal::read($root,123,'test',$key,$today,'T123-1','')['rows'],'task lookup is exact');
    $bad=false;try{ExchangeJournal::read($root,123,'test',$key,'../../secrets','','');}catch(Problem $e){$bad=true;}$check($bad,'invalid day rejected');
    $bad=false;try{ExchangeJournal::read($root,123,'test',$key,$today,'changed',ExchangeJournal::read($root,123,'test',$key,$today,'','')['next']);}catch(Problem $e){$bad=true;}$check($bad,'cursor tied to query');
    // Results arriving after midnight must be discoverable with the original request date.
    $old=strtotime('yesterday 23:59:59');$other=$only[0];$other['sent_ms']=$old*1000;
    $writer($other+['stage'=>'result','delivery'=>'no_confirmed_response','finished_ms'=>($old+3)*1000]);
    $oldRows=ExchangeJournal::read($root,123,'test',$key,date('Y-m-d',$old),'','')['rows'];
    $check(count($oldRows)===1 && $oldRows[0]['delivery']==='no_confirmed_response','cross-midnight timeout');
    $recent=ExchangeJournal::recent($root,123,'test',$key,'','');
    $check($recent['rows'] && $recent['rows'][0]['journal_day']===$today,'recent shows records with no keyword or date');
    $onlyYesterday='another-test-connection';
    ExchangeJournal::record(123,'test',$onlyYesterday,$other+['stage'=>'request'],$root);
    $recentYesterday=ExchangeJournal::recent($root,123,'test',$onlyYesterday,'','');
    $check(count($recentYesterday['rows'])===1 && $recentYesterday['rows'][0]['journal_day']===date('Y-m-d',$old),'latest records visible when today is empty');
    $check(!ExchangeJournal::recent($root,124,'test',$onlyYesterday,'','')['rows'],'recent list site isolated');
    $cross=[];$cursor='';$pages=0;
    do {
        $result=ExchangeJournal::forTask($root,123,'test',$key,'T123-17',$cursor);
        foreach($result['rows'] as $r) $cross[$r['journal_day']][$r['call_id']]=true;
        if(++$pages>12) throw new RuntimeException('task history cursor loop');
    } while(($cursor=$result['next'])!=='');
    $check(count($cross[$today]??[])===32 && count($cross[date('Y-m-d',$old)]??[])===1,'task lookup spans real request days without assuming latest task update date');
    $bad=false;try{ExchangeJournal::forTask($root,123,'test',$key,'T124-17','');}catch(Problem $e){$bad=true;}$check($bad,'cannot request history for a foreign task trace');
    $bad=false;try{ExchangeJournal::forTask($root,123,'test',$key,'T123-17','invalid');}catch(Problem $e){$bad=true;}$check($bad,'forged history cursor rejected');
    for($i=1;$i<=8;$i++){
        $noise=$other;$noise['trace_id']='T123-99';$noise['sent_ms']=strtotime('+'.$i.' days 01:00:00')*1000;
        $writer($noise+['stage'=>'request']);
    }
    $first=ExchangeJournal::forTask($root,123,'test',$key,'T123-17','');
    $check(!$first['rows'] && $first['next']!=='','bounded search returns continuation rather than reading the whole noisy history');
    $next=ExchangeJournal::forTask($root,123,'test',$key,'T123-17',$first['next']);
    $check(!empty($next['rows']) && $next['rows'][0]['journal_day']===$today,'skips consecutive empty days until the first page with matching calls');
    $detail=static fn($meta,$side,$payload)=>ExchangeJournal::recordDetail(123,'test',$key,$meta,$side,$payload,$root);
    $withDetails=new InterfaceTrace(123,'test',['kind'=>'purchase','task_id'=>20],static fn()=>'', $writer,$detail);
    $many=[];for($i=0;$i<18;$i++)$many[]=['unit_price'=>($i+1).'.00','imei'=>'990000000012345','help_code'=>'13800000123'];
    $a=$withDetails->begin('POST','purchase_create',['sign'=>'NO-SECRET-IN-LOG','biz'=>['product_details'=>['add_details'=>$many]]]);
    $withDetails->end($a,'response_received',200,0,['return_code'=>'SUCCESS','api_response'=>['result_list'=>$many]],'json','');
    $idx=ExchangeJournal::read($root,123,'test',$key,$today,'T123-20','')['rows'][0];
    $check($idx['request_detail'] && $idx['response_detail'] && isset($idx['request_payload']['biz']['product_details']['add_details']['_omitted']),'previews disclose omission, full sides stored independently');
    $req=ExchangeJournal::detail($root,123,'test',$key,$today,$idx['call_id'],'request')['payload'];
    $resp=ExchangeJournal::detail($root,123,'test',$key,$today,$idx['call_id'],'response')['payload'];
    $check($req['biz']['product_details']['add_details'][17]['unit_price']==='18.00' && count($resp['api_response']['result_list'])===18,'last device available on both sides');
    $check(!preg_match('/13800000123|990000000012345|NO-SECRET-IN-LOG/',json_encode([$req,$resp])),'same redaction in full business details');
    $bad=false;try{ExchangeJournal::detail($root,124,'test',$key,$today,$idx['call_id'],'request');}catch(Problem $e){$bad=true;}$check($bad,'cannot look up another tenant detail by a valid call id');
    $bad=false;try{ExchangeJournal::detail($root,123,'test',$key,$today,'../elsewhere','request');}catch(Problem $e){$bad=true;}$check($bad,'cannot choose arbitrary report filenames');
    echo 'PASS '.$n." log checks, real file channel, isolated data only.\n";
} finally {
    if(is_dir($root)){
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($it as $file){if($file->isDir())rmdir($file->getPathname());else unlink($file->getPathname());}rmdir($root);
    }
}
