<?php
declare(strict_types=1);
// Pure UAT eligibility checks: no database, transport, credential or external request.
use addon\hsx_boyuan\app\service\PaymentSyncService as Pay;
require dirname(__DIR__,3).'/vendor/autoload.php';
require_once __DIR__.'/../app/support/Contract.php';
require_once __DIR__.'/../app/service/ManualDocumentCommandService.php';
require_once __DIR__.'/../app/service/PaymentSyncService.php';
$config=['mode'=>'test','purchase_mode'=>'manual','revision'=>1,'enabled'=>true,'purchase_enabled'=>true,'uat_payment_enabled'=>true,'paused'=>false,
    'profiles'=>['test'=>['protocol'=>'openapi_v153','endpoint'=>Pay::UAT_ENDPOINT,'gateway_path'=>'/gateway.do','account_id'=>'fixture','connection_key'=>'k','contract_confirmed'=>true,'probe_at'=>10]]];
$source=['source_task_id'=>21,'settlement_id'=>42,'settlement_no'=>'FK42','confirmed_at'=>1];
$frozen=['connection_key'=>'k','schema'=>Pay::SCHEMA,'stage'=>'frozen','source_task_id'=>21,'source_settlement_id'=>42];
$row=['kind'=>Pay::KIND,'status'=>'pending','mode'=>'test','connection_key'=>'k','attempts'=>0,'side_effect'=>'none','next_at'=>0,
    'fact_key'=>'purchase-payment:settlement:42','business_no'=>'FK42','snapshot_json'=>json_encode($source),'request_json'=>json_encode($frozen)];
if(!Pay::currentEligible($config,$row)) throw new RuntimeException('Frozen original should be eligible');
foreach ([['mode'=>'production'],['connection_key'=>'sibling'],['status'=>'processing'],['side_effect'=>'unknown'],['attempts'=>1],['next_at'=>PHP_INT_MAX],['lease_token'=>'claimed'],['external_no'=>'already'],
    ['request_json'=>json_encode(array_replace($frozen,['stage'=>'draft']))],['request_json'=>json_encode(array_replace($frozen,['source_settlement_id'=>43]))]] as $delta)
    if(Pay::currentEligible($config,array_replace($row,$delta))) throw new RuntimeException('Unsafe pending task accepted: '.implode(',',array_keys($delta)));
foreach ([['paused'=>true],['mode'=>'production'],['purchase_mode'=>'auto'],['uat_payment_enabled'=>false],['profiles'=>['test'=>array_replace($config['profiles']['test'],['endpoint'=>'https://example.invalid'])]]] as $delta)
    if(Pay::currentEligible(array_replace($config,$delta),$row)) throw new RuntimeException('Unsafe connection accepted');
echo "PASS: exact pending UAT voucher, no retry/production, no I/O.\n";
