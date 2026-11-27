<?php
declare(strict_types=1);
use addon\hsx_boyuan\app\service\{PurchaseSettlementPort,PurchaseSyncService,PaymentSyncService};
use think\facade\Db;
if (PHP_SAPI!=='cli' || !extension_loaded('pdo_sqlite')) exit(2);
$root=dirname(__DIR__,3); require $root.'/vendor/autoload.php'; require_once $root.'/vendor/topthink/framework/src/helper.php';
final class FixtureSettlementPort extends PurchaseSettlementPort {
    public static ?Closure $fixtureReader=null;
    protected static function reader(): ?Closure { return self::$fixtureReader; }
}
$app=new think\App($root);
$app->config->set(['default'=>'isolated','connections'=>['isolated'=>['type'=>'sqlite','database'=>':memory:','prefix'=>'','fields_cache'=>false]]],'database');
$app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/erp-settlement-port-cache/']]],'cache');
$app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','close'=>true,'path'=>sys_get_temp_dir().'/erp-settlement-port-log/']]],'log');
foreach (['hsx_boyuan_task'=>'id INTEGER PRIMARY KEY, site_id INTEGER, mode TEXT, connection_key TEXT, kind TEXT, fact_key TEXT, status TEXT, resolution TEXT, side_effect TEXT, business_no TEXT, external_no TEXT, snapshot_json TEXT, request_json TEXT, result_json TEXT',
    'erp_payable'=>'site_id INTEGER,id INTEGER,source_type TEXT,source_id INTEGER,amount TEXT,settled_amount TEXT,status TEXT',
    'erp_settlement'=>'site_id INTEGER,id INTEGER,settlement_no TEXT,amount TEXT,status TEXT,cash_direction TEXT,settlement_type TEXT,confirmed_at INTEGER',
    'erp_settlement_link'=>'site_id INTEGER,target_type TEXT,target_id INTEGER,settlement_id INTEGER,applied_amount TEXT'] as $name=>$cols) Db::execute("CREATE TABLE $name($cols)");
$fixture=json_decode(file_get_contents(__DIR__.'/fixtures/purchase-allocation-evidence.json'),true,64,JSON_THROW_ON_ERROR);
$x=$fixture['expected']; $proof=$fixture['evidence'];
// The verified parser also supports merged vouchers when complete bindings are supplied. The current
// vendor task only supports a single purchase batch: use the non-merged variant for this path.
$x['payments'][0]['voucher_amount']='70.00';
foreach($proof['allocations'] as &$a) if ($a['external_payment_id']==='BY-V1') $a['voucher_amount']='70.00';
unset($a);
$scope=array_intersect_key($x,array_flip(['site_id','mode','connection_key'])); $site=$scope['site_id'];
$p=['kind'=>'purchase','source_document_id'=>$x['source_document_id'],'source_document_no'=>$x['source_document_no'],'batch_ref'=>'B',
    'lines'=>[],'financial_items'=>[]]; $verifiedLines=[]; $index=[];
foreach ($x['lines'] as $l) {
    $index[$l['payable_id']]=$l;
    $p['lines'][]=array_intersect_key($l,array_flip(['source_line_id','device_ref','amount']));
    $p['financial_items'][]=['financial_item_type'=>'payable','source_financial_item_id'=>'payable:'.$l['payable_id'],'source_document_id'=>$p['source_document_id'],
        'source_line_id'=>$l['source_line_id'],'device_ref'=>$l['device_ref'],'amount'=>$l['amount']];
    $verifiedLines[]=['source_line_id'=>$l['source_line_id'],'device_ref'=>$l['device_ref'],'external_line_id'=>$l['external_line_id'],'external_document_id'=>$x['external_purchase_id']];
    Db::name('erp_payable')->insert(['site_id'=>$site,'id'=>$l['payable_id'],'source_type'=>'purchase_asset','source_id'=>(int)substr($l['device_ref'],6),'amount'=>$l['amount'],'settled_amount'=>$l['amount'],'status'=>'settled']);
}
$pq=['schema'=>PurchaseSyncService::SCHEMA,'stage'=>'frozen','selection'=>['company_id'=>$x['company_id']],'biz'=>['head'=>['cash_term'=>0]]];
$pr=['verified'=>true,'external_id'=>$x['external_purchase_id'],'external_no'=>$x['external_purchase_no'],'verification_level'=>'purchase_imei_records_matched',
    'lines'=>$verifiedLines,'evidence'=>['record_is_effective'=>true,'company_id'=>$x['company_id']]];
$encode=static fn($v)=>json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
Db::name('hsx_boyuan_task')->insert($scope+['id'=>1,'kind'=>'purchase','fact_key'=>$p['source_document_id'].':B','status'=>'succeeded','side_effect'=>'applied',
    'business_no'=>$p['source_document_no'],'external_no'=>$pr['external_no'],'snapshot_json'=>$encode($p),'request_json'=>$encode($pq),'result_json'=>$encode($pr)]);
foreach ($x['payments'] as $i=>$payment) {
    $sid=$payment['settlement_id']; $amount=$payment['voucher_amount'];
    $s=array_intersect_key($x,array_flip(['source_document_id','source_document_no','external_purchase_id','external_purchase_no','company_id','currency']))+
        ['kind'=>PaymentSyncService::KIND,'scope'=>'single_purchase_batch','source_task_id'=>1,'source_snapshot_hash'=>hash('sha256',$encode($p)),
        'source_request_hash'=>hash('sha256',$encode($pq)),'settlement_id'=>$sid,'settlement_no'=>$payment['settlement_no'],'amount'=>$amount,'lines'=>[]];
    foreach ($payment['applications'] as $a) {
        $l=$index[$a['payable_id']]; $s['lines'][]=$a+['source_line_id'=>$l['source_line_id'],'device_ref'=>$l['device_ref'],'external_line_id'=>$l['external_line_id'],
            'external_document_id'=>$x['external_purchase_id'],'payable_amount'=>$l['amount']];
        Db::name('erp_settlement_link')->insert(['site_id'=>$site,'settlement_id'=>$sid,'target_type'=>'payable','target_id'=>$a['payable_id'],'applied_amount'=>$a['amount']]);
    }
    $q=['schema'=>PaymentSyncService::SCHEMA,'stage'=>'frozen','source_task_id'=>1,'source_snapshot_hash'=>$s['source_snapshot_hash'],'source_settlement_id'=>$sid,
        'connection_key'=>$x['connection_key'],'company_id'=>$x['company_id'],'amount'=>$amount];
    $r=['created'=>true,'payment_registered'=>true,'verification_level'=>'payment_create_receipt_matched','amount'=>$amount,'external_no'=>$payment['external_payment_no'],
        'external_id'=>$payment['external_payment_id'],'response'=>['head'=>['id'=>$payment['external_payment_id'],'bill_id'=>$payment['external_payment_no'],'bill_type'=>'PAYMENT',
        'big_gsdm'=>$x['account_id'],'company_id'=>$x['company_id']]],'allocation'=>['verified'=>true,'status'=>'settled']];
    Db::name('erp_settlement')->insert(['site_id'=>$site,'id'=>$sid,'settlement_no'=>$payment['settlement_no'],'amount'=>$amount,'status'=>'confirmed','cash_direction'=>'out','settlement_type'=>'payment','confirmed_at'=>123]);
    Db::name('hsx_boyuan_task')->insert($scope+['id'=>2+$i,'kind'=>PaymentSyncService::KIND,'fact_key'=>'purchase-payment:settlement:'.$sid,'status'=>PaymentSyncService::REGISTERED,
        'business_no'=>$payment['settlement_no'],'external_no'=>$r['external_no'],'snapshot_json'=>$encode($s),'request_json'=>$encode($q),'result_json'=>$encode($r)]);
}
$checks=0; $check=static function($ok,$text) use (&$checks) { if (!$ok) throw new RuntimeException($text); ++$checks; };
$count=0; $input=[]; $answer=$proof;
FixtureSettlementPort::$fixtureReader=static function(array $expected) use (&$count,&$input,&$answer) { ++$count; $input=$expected; return $answer; };
$pin=$scope+['id'=>2,'allocation'=>$proof]; $default=PurchaseSettlementPort::forVoucher($pin);
$check(!$default['verified'] && $default['reason']==='contract_unavailable' && $count===0,'production portal never loads fixture or payload');
$all=FixtureSettlementPort::forVoucher($pin);
$check($all['status']==='settled' && $all['applied_amount']==='100.00' && $count===1,'same portal with injected normalized reader reconciles full scope');
$check($input['account_id']===$x['account_id'] && $input['external_purchase_id']===$x['external_purchase_id']
    && array_column($input['payments'],'external_payment_id')===['BY-V1','BY-V2'] && array_column($input['lines'],'payable_id')===[201,202],'reader receives trusted ledger, parent, all devices and both vouchers');
array_pop($answer['allocations']); $answer['applied_amount']='70.00'; $answer['remaining_amount']='30.00';
$partial=FixtureSettlementPort::forVoucher($scope+['id'=>3]); $check($partial['status']==='partial' && $partial['remaining_amount']==='30.00','either voucher leads to same full purchase, not its own voucher total');
$answer['account_id']='incorrect'; $check(FixtureSettlementPort::forVoucher($pin)['reason']==='evidence_mismatch','wrong authenticated book refused');
$answer=$proof; $answer['allocations'][0]['external_payment_no']='other'; $check(FixtureSettlementPort::forVoucher($pin)['reason']==='evidence_mismatch','wrong voucher refused');
$answer=['payment_registered'=>true,'allocation'=>['verified'=>true,'status'=>'settled']]; $check(FixtureSettlementPort::forVoucher($pin)['reason']==='evidence_unavailable','payment result never supplies allocation');
$answer=$proof; $before=$count;
foreach (["UPDATE hsx_boyuan_task SET mode='production' WHERE id=3","UPDATE hsx_boyuan_task SET site_id=12 WHERE id=1",
    "UPDATE hsx_boyuan_task SET connection_key='another' WHERE id=3","UPDATE hsx_boyuan_task SET status='attention' WHERE id=3",
    "UPDATE erp_settlement_link SET applied_amount='9.00' WHERE target_id=202 AND settlement_id=301",
    "UPDATE erp_settlement SET amount='80.00' WHERE id=301","UPDATE erp_payable SET source_id=99 WHERE id=202",
    "UPDATE hsx_boyuan_task SET snapshot_json='{}' WHERE id=1", "UPDATE hsx_boyuan_task SET result_json='{}' WHERE id=1",
    "UPDATE hsx_boyuan_task SET result_json='{}' WHERE id=3"] as $sql) {
    Db::startTrans();
    try { Db::execute($sql); $bad=FixtureSettlementPort::forVoucher($pin); $check($bad['reason']==='source_unverified' && $bad['applied_amount']===null && $count===$before,$sql); }
    finally { Db::rollback(); }
}
foreach ([
    [1,'result_json',static function(&$d){ array_pop($d['lines']); }],
    [3,'request_json',static function(&$d){ $d['target_account']='unrelated-book'; }],
    [3,'result_json',static function(&$d){ $d['response']['head']['big_gsdm']='different-book'; }],
    [3,'result_json',static function(&$d){ unset($d['response']['head']['big_gsdm']); $d['evidence']['account_id']='a-cash-account'; }],
    [3,'snapshot_json',static function(&$d){ $d['external_purchase_id']='another-batch'; }],
    [2,'snapshot_json',static function(&$d){ $d['settlement_id']=302; }],
] as [$tid,$field,$mutate]) {
    Db::startTrans();
    try {
        $table=Db::name('hsx_boyuan_task')->where('id',$tid);
        $data=json_decode((clone $table)->value($field),true,64,JSON_THROW_ON_ERROR); $mutate($data); $table->update([$field=>$encode($data)]);
        $bad=FixtureSettlementPort::forVoucher($pin);
        $check($bad['reason']==='source_unverified' && $bad['remaining_amount']===null && $count===$before,'incomplete/mismatched frozen batch, voucher or ledger should not contact any reader');
    } finally { Db::rollback(); }
}
echo "PASS: $checks same-port trusted-voucher checks; SQLite in memory, no network or live data.\n";
