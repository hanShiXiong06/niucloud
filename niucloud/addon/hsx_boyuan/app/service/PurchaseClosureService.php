<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\{Contract,Problem};
use core\exception\CommonException;
use think\facade\{Db,Log};

/** Read-only batch summary. No gateway, payment command, state transition or business write. */
final class PurchaseClosureService
{
    public function view(int $site,array $task): array
    {
        $view=$this->empty();
        if($site<=0 || (int)($task['site_id']??0)!==$site || ($task['kind']??'')!=='purchase') return $this->unavailable($view,'来源任务不属于本站采购，未读取闭环数据');
        try {
            $snapshot=Contract::decode((string)($task['snapshot_json']??'{}'));
            if(!preg_match('/^purchase:([1-9][0-9]*)$/D',(string)($snapshot['source_document_id']??''),$match) || !is_array($snapshot['lines']??null)) throw new Problem('CLOSURE_SOURCE_INVALID','采购批次来源身份无效');
            $view['source']=['purchase_id'=>(int)$match[1],'source_document_id'=>$snapshot['source_document_id'],'batch_ref'=>(string)($snapshot['batch_ref']??''),
                'device_count'=>count($snapshot['lines']),'source_line_ids'=>array_values(array_column($snapshot['lines'],'source_line_id'))];
            $result=Contract::decode((string)($task['result_json']??'{}'));
            if(($result['verified']??false)===true && ($result['verification_level']??'')==='purchase_imei_records_matched' && !empty($task['external_no'])) {
                $view['stages'][1]=array_replace($view['stages'][1],['status'=>'verified','reason'=>'本任务已保存采购单及入库串号记录匹配证据；不表示当前库存余额或财务闭环已核验','count'=>count($snapshot['lines'])]);
            } elseif(!empty($task['external_no']) || ($result['created']??false)===true) {
                $view['stages'][1]['reason']='已取得博远采购单号，但尚无完整的采购及入库串号匹配证据';
            }
            $canFinance=true;
            try { (new PermissionService())->assertApi('get','erp/finance/payable/lists'); }
            catch(CommonException $error) { $canFinance=false; }
            if(!$canFinance) foreach([3,4] as $index) $view['stages'][$index]=array_replace($view['stages'][$index],['status'=>'permission_denied','reason'=>'无ERP应付查看权限，未读取或展示本批次应付及付款金额']);
            return Db::transaction(function() use($site,$task,$snapshot,$view,$canFinance): array {
                // Reuse the same current-source guard as submit, without acquiring write locks.
                (new PurchaseSyncService())->assertClosureSourceCurrent($site,$snapshot);
                $view['available']=true;
                $view['stages'][0]=array_replace($view['stages'][0],['status'=>'verified','reason'=>'本批次采购已在本站确认入库，当前来源核心事实与冻结快照一致','count'=>count($snapshot['lines'])]);
                if(!$canFinance) {
                    $view['warnings'][]='无应付查看权限；财务金额未取得，不代表金额为零';
                    return $view;
                }
                return $this->financial($site,$snapshot,$view);
            });
        } catch(Problem $error) {
            return $this->unavailable($view,$error->getMessage());
        } catch(\Throwable $error) {
            // Do not expose SQL, account configuration or exception details in the read model.
            try { Log::warning('hsx_boyuan purchase closure query unavailable',['site_id'=>$site,'task_id'=>(int)($task['id']??0),'error_type'=>get_class($error)]); }
            catch(\Throwable $ignored) {} // A technical log failure must not break this read-only response.
            return $this->unavailable($view,'本批次闭环查询暂不可用，金额未取得；请刷新或联系有权限的负责人核查');
        }
    }

    private function financial(int $site,array $snapshot,array $view): array
    {
        $source=[]; $inbound=0;
        foreach($snapshot['lines'] as $line) {
            $asset=(int)substr($line['device_ref'],6); $source[$asset]=$line; $inbound=$this->add($inbound,$this->cents($line['amount']));
        }
        $rows=Db::name('erp_payable')->where('site_id',$site)->where('source_type','purchase_asset')->whereIn('source_id',array_keys($source))
            ->field('id,site_id,party_id,source_type,source_id,asset_id,amount,settled_amount,status')->select()->toArray();
        $seen=[]; $active=[]; $voided=[];
        foreach($rows as $row) {
            $asset=(int)$row['source_id']; if(!isset($source[$asset])) throw new Problem('CLOSURE_PAYABLE_SCOPE','应付与本批次设备身份不一致，未汇总金额');
            if(($row['status']??'')==='void') { $voided[$asset]=true; continue; }
            if(isset($seen[$asset])) throw new Problem('CLOSURE_PAYABLE_AMBIGUOUS','同一设备存在多笔有效采购应付，金额待核对');
            if('party:'.(int)$row['party_id']!==$snapshot['party']['ref'] || (int)($row['asset_id']??0)>0 && (int)$row['asset_id']!==$asset) throw new Problem('CLOSURE_PAYABLE_SCOPE','应付供应商或设备归属与本批次不一致，未汇总金额');
            if(!in_array($row['status']??'',['pending','partial','settled'],true)) throw new Problem('CLOSURE_PAYABLE_STATE','本批次应付状态无法核验，金额未取得');
            $amount=$this->cents($row['amount']??null); $settled=$this->cents($row['settled_amount']??null);
            if($amount!==$this->cents($source[$asset]['amount']) || $settled>$amount) throw new Problem('CLOSURE_PAYABLE_CHANGED','本批次应付金额与采购来源不一致，或已结算金额超出应付，需核对原单');
            $seen[$asset]=true; $row['_amount']=$amount; $row['_settled']=$settled; $active[(int)$row['id']]=$row;
        }
        foreach($source as $asset=>$line) if(!isset($seen[$asset]) && !isset($voided[$asset])) throw new Problem('CLOSURE_PAYABLE_MISSING','本批次部分设备缺少采购应付，无法给出完整金额；未将缺失记录当作零');
        $amount=0; $expectedPaid=0;
        foreach($active as $row) { $amount=$this->add($amount,$row['_amount']); $expectedPaid=$this->add($expectedPaid,$row['_settled']); }
        $links=$active?Db::name('erp_settlement_link')->where('site_id',$site)->where('target_type','payable')->whereIn('target_id',array_keys($active))
            ->field('id,site_id,settlement_id,target_id,applied_amount')->select()->toArray():[];
        $settlementIds=array_values(array_unique(array_map('intval',array_column($links,'settlement_id')))); $settlements=[];
        if($settlementIds) foreach(Db::name('erp_settlement')->where('site_id',$site)->whereIn('id',$settlementIds)->field('id,site_id,status,settlement_type,cash_direction,confirmed_at')->select()->toArray() as $row) $settlements[(int)$row['id']]=$row;
        $paid=0; $paymentIds=[]; $perPayable=array_fill_keys(array_keys($active),0); $seenLinks=[];
        foreach($links as $link) {
            $id=(int)$link['id']; $target=(int)$link['target_id']; $settlement=$settlements[(int)$link['settlement_id']]??null;
            if($id<=0 || isset($seenLinks[$id]) || !isset($active[$target]) || !$settlement) throw new Problem('CLOSURE_PAYMENT_SCOPE','付款分配缺少本站唯一结算依据，金额待核对');
            $seenLinks[$id]=true;
            if(($settlement['status']??'')!=='confirmed' || ($settlement['settlement_type']??'')!=='payment' || ($settlement['cash_direction']??'')!=='out') continue;
            if((int)($settlement['confirmed_at']??0)<=0) throw new Problem('CLOSURE_PAYMENT_UNCONFIRMED','付款结算缺少财务确认时间，金额待核对');
            $applied=$this->cents($link['applied_amount']??null);
            $paid=$this->add($paid,$applied); $perPayable[$target]=$this->add($perPayable[$target],$applied);
            if($applied>0) $paymentIds[(int)$settlement['id']]=true;
        }
        foreach($active as $id=>$row) if($perPayable[$id]!==$row['_settled']) throw new Problem('CLOSURE_PAYMENT_MISMATCH','有效应付已结算额与本站已确认付款分配不一致，可能含折账或待核对记录；未将其他结算当作付款');
        if($paid!==$expectedPaid || $paid>$amount) throw new Problem('CLOSURE_PAYMENT_MISMATCH','本批次付款分配合计不一致，金额待核对');
        $unpaid=$amount-$paid; $money=['amount'=>Contract::amount($amount),'paid_amount'=>Contract::amount($paid),'unpaid_amount'=>Contract::amount($unpaid)];
        $view['totals']=['currency'=>'CNY']+$money; $view['stages'][0]['amount']=Contract::amount($inbound);
        $view['stages'][3]=array_replace($view['stages'][3],$money,['status'=>count($active)===count($source)?'verified':(count($active)?'partial':'pending'),
            'reason'=>count($active)?'仅汇总本批次设备的有效本站采购应付，作废应付已排除':'本批次应付均已作废，不纳入有效金额；不代表发生付款','count'=>count($active)]);
        $paymentStatus=$unpaid===0 && count($active)>0?'verified':($paid>0?'partial':'pending');
        $reason=$paymentStatus==='verified'?'本批次有效应付已由本站财务确认付款分配结清；不是银行流水或博远核销核验':($paid>0?'本批次已有部分本站财务确认付款，剩余应付尚未付清':'尚无本批次有效的本站财务确认付款；不代表已向博远登记付款');
        if($amount===0 && count($active)>0) $reason='本批次有效应付为零，无需付款；不表示博远财务已核验';
        $view['stages'][4]=array_replace($view['stages'][4],$money,['status'=>$paymentStatus,'reason'=>$reason,'count'=>count($paymentIds)]);
        $excluded=count(array_diff_key($voided,$seen)); if($excluded>0) $view['warnings'][]='已排除本批次'.$excluded.'台设备的作废应付，未计入有效应付或付款汇总';
        return $view;
    }

    private function cents($value): int
    {
        if(is_int($value) && $value>=0) $value=(string)$value.'.00';
        return Contract::cents($value);
    }
    private function add(int $left,int $right): int
    {
        if($right<0 || $left>PHP_INT_MAX-$right) throw new Problem('CLOSURE_AMOUNT_RANGE','本批次金额超出精确汇总范围，未给出金额');
        return $left+$right;
    }
    private function empty(): array
    {
        $stages=[];
        foreach(['local_inbound'=>'本站入库','remote_purchase'=>'博远采购记录','remote_stock'=>'当前库存余额','local_payable'=>'本站应付','local_payment'=>'本站财务确认付款','remote_settlement'=>'博远付款及指定核销'] as $key=>$label) {
            $stages[]=['key'=>$key,'label'=>$label,'status'=>'unverified','reason'=>'尚未取得本批次完整核验依据','amount'=>null,'paid_amount'=>null,'unpaid_amount'=>null,'count'=>null];
        }
        $stages[2]['reason']='当前库存余额查询契约尚未提供；采购及入库串号记录匹配不等于当前库存余额已核验';
        $stages[5]['status']='blocked'; $stages[5]['reason']='付款登记、指定应付核销、幂等及结果查询契约不完整；未向博远外发付款或核销请求';
        return ['schema'=>'boyuan.purchase.closure.v1','scope'=>'purchase_batch','complete'=>false,'available'=>false,
            'source'=>['purchase_id'=>null,'source_document_id'=>'','batch_ref'=>'','device_count'=>null,'source_line_ids'=>[]],
            'totals'=>['currency'=>'CNY','amount'=>null,'paid_amount'=>null,'unpaid_amount'=>null],'stages'=>$stages,'warnings'=>[]];
    }
    private function unavailable(array $view,string $reason): array
    {
        $view['available']=false; $view['totals']=['currency'=>'CNY','amount'=>null,'paid_amount'=>null,'unpaid_amount'=>null];
        foreach([0,3,4] as $index) {
            if($view['stages'][$index]['status']==='permission_denied') continue;
            $view['stages'][$index]=array_replace($view['stages'][$index],['status'=>'unavailable','reason'=>$reason,'amount'=>null,'paid_amount'=>null,'unpaid_amount'=>null,'count'=>null]);
        }
        $view['warnings'][]=$reason; return $view;
    }
}
