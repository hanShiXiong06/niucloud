<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\Contract;
use think\facade\Db;

/** Read-only join from a sale to its own locally confirmed incoming vouchers. Never recover or send on GET. */
final class OriginalSaleCashReceipts
{
    public static function rows(int $site,int $sale,string $number,array $c,bool $canPrepare=false,int $ar=0):array
    {
        if($site<1 || $sale<1 || $number==='' || $c['mode']!=='test')return [];
        $table=static fn($n)=>Db::name($n)->getTable();
        $q=Db::name('erp_receivable')->alias('a')->join([$table('erp_sale_order')=>'h'],'h.id=a.source_id AND h.site_id=a.site_id AND h.party_id=a.party_id AND h.sale_no=a.source_no')
            ->join([$table('erp_settlement_link')=>'l'],"l.target_type='receivable' AND l.target_id=a.id AND l.site_id=a.site_id")
            ->join([$table('erp_settlement')=>'s'],'s.site_id=l.site_id AND s.id=l.settlement_id AND s.party_id=a.party_id')
            ->where(['a.site_id'=>$site,'a.source_type'=>'sale','a.source_id'=>$sale,'h.sale_no'=>$number,'s.status'=>'confirmed','s.cash_direction'=>'in','s.settlement_type'=>'receipt']);
        if($ar>0)$q->where('a.id',$ar);
        $items=$q->field('a.id ar,s.id sid,s.settlement_no,s.amount,l.applied_amount,s.confirmed_at,h.status sale_status')->order('s.id')->select()->toArray();
        if(!$items)return [];
        $ids=array_values(array_unique(array_column($items,'sid')));
        $linkCount=Db::name('erp_settlement_link')->where('site_id',$site)->whereIn('settlement_id',$ids)->group('settlement_id')->column('COUNT(*) as n','settlement_id');
        $tasks=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>'test','connection_key'=>$c['profiles']['test']['connection_key'],'kind'=>SaleReceiptSyncService::KIND])
            ->whereIn('fact_key',array_map([SaleReceiptSyncService::class,'fact'],$ids))->select()->toArray();
        $tasks=array_column($tasks,null,'fact_key');$out=[];
        foreach($items as $i){
            $sid=(int)$i['sid'];$t=$tasks[SaleReceiptSyncService::fact($sid)]??null;$reg='';$taskId=0;$query=[];
            $label='未同步';$status='not_dispatched';$tone='info';$reason='本站已收，请勿重复收款。';
            $canLink=(int)($linkCount[$sid]??0)===1 && $i['amount']===$i['applied_amount'] && $i['sale_status']==='completed';
            if($t){
                $s=Contract::decode($t['snapshot_json']);
                if(($s['sale_id']??null)!==$sale || ($s['sale_no']??'')!==$number || ($s['settlement_id']??null)!==$sid
                    || ($s['receivable_id']??null)!==(int)$i['ar'] || ($s['settlement_no']??'')!==$i['settlement_no'] || $t['business_no']!==$i['settlement_no'] || $s['amount']!==$i['amount']){
                    $label='待核对';$status='needs_review';$tone='warning';$reason='原凭证与博远任务不一致，禁止重新登记';
                } else {
                    $taskId=(int)$t['id'];$reg=SaleReceiptSyncService::number($t);
                    if($reg!==''){$label='已登记';$status='registered';$tone='success';$reason='博远已登记；原销售核销待核对，无需再次收款。';}
                    elseif(SaleReceiptSyncService::draft($t)){$label='待确认同步';$status='awaiting_confirmation';$reason='只登记这张已有收款凭证，不会再次收款。';}
                    else {$label='待核对';$status='needs_review';$tone='warning';$reason='博远收款登记结果未确定，只能核对原单，不可重发。';}
                }
            }
            if($taskId || (!$t && $canLink && $canPrepare)) {
                $query=['detail_extension'=>'hsx_boyuan','kind'=>'receipt','sale_id'=>$sale,'settlement_id'=>$sid,'receipt_task_id'=>$taskId,
                    'action_label'=>$taskId>0?($status==='awaiting_confirmation'&&$canPrepare?'确认同步已有凭证':'查看收款回执'):'同步已有收款凭证'];
                if(!$taskId)$query['original_source_id']=$sid;
            }
            $out[$sid]=['key'=>'boyuan_sale_receipt_'.$sid,'provider_label'=>'博远收款单','status'=>$status,'status_label'=>$label,'tone'=>$tone,'external_no'=>$reg,
                'task_id'=>$taskId,'settlement_id'=>$sid,'query'=>$query,'pc_route'=>'','mobile_path'=>'','next_step'=>$i['settlement_no'].' · ¥'.$i['amount'].'。'.$reason];
        }
        return array_values($out);
    }

    public static function label(array $rows):string
    {
        if(!$rows)return '未同步';
        $states=array_column($rows,'status');$n=count(array_filter($states,static fn($s)=>$s==='registered'));
        if($n===count($states))return '已登记';
        if(in_array('needs_review',$states,true))return '待核对';
        return $n?'部分已登记':'未同步';
    }
}
