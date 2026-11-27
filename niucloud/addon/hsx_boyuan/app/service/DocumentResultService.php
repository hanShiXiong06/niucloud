<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\gateway\WholesaleGateway;
use addon\hsx_boyuan\app\support\Contract;
use addon\hsx_boyuan\app\support\ProductionPurchasePilot;
use addon\hsx_erp_integration\app\support\BridgeTarget;
use think\facade\Db;

/** Original-document GET projection only: current tenant + connection + number + source id. */
final class DocumentResultService
{
    public function rows(int $site,string $kind,int $id,string $number,array $c,bool $finance=false,bool $saleSerialEvidence=false): array
    {
        if($id<=0 || $number==='' || !in_array($kind,['purchase','sale'],true)) return [];
        $p=$c['profiles'][$c['mode']]; $src=$kind.':'.$id; $purchase=$kind==='purchase';
        $q=Db::name('hsx_boyuan_task')->where('site_id',$site)->where('mode',$c['mode'])->where('connection_key',$p['connection_key'])
            ->where('kind',$purchase?'purchase':WholesaleSyncService::KIND)->where('business_no',$number);
        if($purchase) $q->where('fact_key','like',$src.':%'); else $q->where('fact_key',$src.':wholesale-credit');
        $out=[];
        foreach($q->field('id,business_no,fact_key,operation_id,status,side_effect,resolution,external_no,snapshot_json,request_json,result_json,update_at')->order('id')->select()->toArray() as $task) {
            $s=Contract::decode($task['snapshot_json']);
            if((Contract::decode($task['request_json'])['schema']??'')!==($purchase?PurchaseSyncService::SCHEMA:WholesaleGateway::SCHEMA)
                || ($s['source_document_id']??'')!==$src || ($s['source_document_no']??'')!==$number || ($s['kind']??'')!==$kind ||
                ($purchase && $task['fact_key']!==$src.':'.($s['batch_ref']??''))) continue;
            $st=(string)$task['status'];$no=(string)$task['external_no'];$rid=(int)$task['id'];$stored=Contract::decode($task['result_json']);$word=$purchase?'采购':'出库';
            $proof=$purchase?$stored:($stored['verification']??[]);
            $verified=($proof['verified']??false)===true && ($proof['external_no']??null)===$no &&
                ($proof['verification_level']??'')===($purchase?'purchase_imei_records_matched':'wholesale_records_matched');
            // A manually entered recovery number is a search candidate, not a vendor receipt.
            $receipt=$purchase?$stored:($stored['receipt']??[]);
            if(!$verified && !(($receipt['created']??false)===true && ($receipt['external_no']??null)===$no)) $no='';
            if($st==='succeeded' && (!$verified || $no==='')) $st='needs_review';
            if($st==='created_review' && $no==='') $st='needs_review';
            [$label,$tone,$next]=match($st){
                'succeeded'=>['已核实'.$word,'success',$purchase?'原采购及串号已匹配，不代表已经付款。':'原出库单及设备明细已匹配，不代表已经收款。'],
                'created_review'=>['已取得单号，待核对','warning','查看原单核对进度，不要重复提交。'],
                'awaiting_confirmation'=>['待人工确认','info','核对档案和设备后再决定是否提交。'],
                'pending','confirming','processing'=>['处理中','info','请稍后刷新原单结果，不要重复提交。'],
                'blocked'=>['未发送，需处理','warning','查看任务提示，核对资料。'],
                'attention','needs_review'=>['结果待核实','warning','查看原任务，按原单核对；不要重新建单。'],
                default=>['待核对','info','查看任务详情，不能根据本地订单推定博远状态。'],
            };
            if($purchase && ($unsent=ProductionPurchasePilot::unsentNotice($task,$stored))!==''){ $st='blocked';$label='采购单未发送';$tone='warning';$next=$unsent;}
            if(($task['resolution']??'')==='source_changed'||str_starts_with((string)($task['resolution']??''),'[source_invalidated]')){ $st='needs_review';$label='原单已变化，需核对';$tone='warning';$next='请核对原单及后续退货，不要重新建单。';}
            if($st==='awaiting_confirmation' && (empty($c['enabled'])||empty($c['purchase_enabled'])||!empty($c['paused']))) {
                $pilot=$purchase && !empty(Contract::decode($task['request_json'])['pilot_bridge_id']) && ProductionPurchasePilot::profile($site,$c,$p);
                $next=$pilot?'仅这张采购可人工确认。请核对设备与博远档案；不登记付款。':'本站已暂停投递，现可查看任务，不会发送。';
            }
            $out[]=['key'=>'boyuan_'.$kind.'_'.$rid,'provider_label'=>'博远 ERP','status'=>$st,'status_label'=>$label,'tone'=>$tone,'external_no'=>$no,
                'task_id'=>$rid,'next_step'=>$next,'pc_route'=>$purchase?'/hsx_boyuan/purchase':'/hsx_boyuan/wholesale','mobile_path'=>'/addon/hsx_boyuan/pages/workbench/index',
                'query'=>['task_id'=>(string)$rid]+($purchase?['detail_extension'=>'hsx_boyuan','kind'=>'purchase','purchase_task_id'=>$rid]:['sale_no'=>$number])];
            if($saleSerialEvidence && !$purchase && $st==='succeeded' && $verified)
                $out[array_key_last($out)]['verified_device_receipts']=OriginalSaleReceipts::confirmedLines($no,$s,$receipt,$proof);
            if ($finance) {
                $index=array_key_last($out); $out[$index]['device_lines']=[];$out[$index]['event_at']=(int)$task['update_at'];
                foreach((array)($s['lines']??[]) as $line) {
                    if(is_string($line['source_line_id']??null) && is_string($line['device_ref']??null)) $out[$index]['device_lines'][$line['source_line_id']]=$line['device_ref'];
                }
                $out[$index]['snapshot_hash']=hash('sha256',$task['snapshot_json']);
                if(in_array($st,['attention','blocked','needs_review','failed'],true)) $out[$index]['issue_code']=$stored['last_issue']['code']??'';
                $head=$receipt['provider_result']??[];
                if(is_array($head) && $no!=='' && ($head['bill_id']??null)===$no && is_string($receipt['external_id']??null)
                    && ($head['id']??null)===$receipt['external_id']) $out[$index]['provider_result']=\addon\hsx_boyuan\app\support\ReceiptFacts::head($head);
            }
        }
        if(!$out) {
            $message='本站没有这笔单据在当前博远账套的同步记录。';
            if($purchase && !empty($p['probe_at']) && (empty($c['enabled']) || empty($c['purchase_enabled']) || !empty($c['paused'])))
                $message='这张采购尚未从本站发送到博远。'.($c['mode']==='production' && trim((string)($p['acceptance_ref']??''))===''?'请管理员到「博远 → 连接设置 → 正式采购同步」核对测试采购并开启同步。正式单号由博远收单后返回。':'采购同步未开启或已暂停。');
            $out[]=['key'=>'boyuan_'.$kind.'_none','provider_label'=>$purchase?'博远采购单':'博远 ERP','status'=>'not_recorded','status_label'=>'暂无同步记录','tone'=>'info','external_no'=>'','task_id'=>0,
                'next_step'=>$message,'pc_route'=>'','mobile_path'=>'','query'=>[]];
            if ($purchase && !$finance && ProductionPurchasePilot::profile($site,$c,$p) && class_exists(BridgeTarget::class))
                $out[0]['fallback_target']=BridgeTarget::key(['site_id'=>$site,'adapter_code'=>'boyuan','config'=>['mode'=>$c['mode'],'account_code'=>$p['account_id'],'api_url'=>$p['endpoint'],'gateway_path'=>$p['gateway_path']]]);
        }
        return $out;
    }
}
