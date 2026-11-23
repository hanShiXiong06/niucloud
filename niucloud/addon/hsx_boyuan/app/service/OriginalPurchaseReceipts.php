<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use think\facade\Db;

/** Staff-only, local read model. Opening or refreshing a purchase never sends either voucher. */
final class OriginalPurchaseReceipts
{
    public function paymentRows(int $site,int $purchase,string $number,array $config): array
    {
        $profile=$config['profiles'][$config['mode']]??[];
        if ($site<1 || $purchase<1 || $number==='' || !in_array($config['mode'],['test','production'],true)
            || ($profile['protocol']??'')!=='openapi_v153' || empty($profile['probe_at']) || empty($profile['connection_key'])) return [];
        $table=static fn(string $name)=>Db::name($name)->getTable();
        $lines=Db::name('erp_purchase_order')->alias('h')
            ->join([$table('erp_purchase_item')=>'i'],'i.site_id=h.site_id AND i.purchase_order_id=h.id')
            ->join([$table('erp_asset')=>'a'],'a.site_id=i.site_id AND a.id=i.asset_id AND a.purchase_item_id=i.id AND a.purchase_order_id=h.id')
            ->join([$table('erp_payable')=>'p'],"p.site_id=h.site_id AND p.source_type='purchase_asset' AND p.source_id=a.id AND (p.asset_id=0 OR p.asset_id=a.id) AND p.party_id=h.party_id")
            ->join([$table('erp_settlement_link')=>'l'],"l.site_id=p.site_id AND l.target_type='payable' AND l.target_id=p.id")
            ->join([$table('erp_settlement')=>'s'],'s.site_id=l.site_id AND s.id=l.settlement_id AND s.party_id=p.party_id')
            ->where(['h.site_id'=>$site,'h.id'=>$purchase,'h.purchase_no'=>$number,'i.item_type'=>'device','s.status'=>'confirmed','s.cash_direction'=>'out','s.settlement_type'=>'payment'])
            ->where('p.status','<>','void')->where('s.confirmed_at','>',0)->where('l.applied_amount','>',0)
            ->field('p.id payable_id,i.id line_id,a.id asset_id,i.imei,i.sn,s.id settlement_id,s.settlement_no,s.amount voucher_amount,l.applied_amount')
            ->order('i.id,s.confirmed_at,s.id')->select()->toArray();
        if (!$lines) return [];
        $facts=[];
        foreach ($lines as $line) {
            $pid=(int)$line['payable_id']; $sid=(int)$line['settlement_id'];
            $facts[$pid]??=['document_id'=>$purchase,'number'=>$number,'asset_id'=>(int)$line['asset_id'],'line_id'=>(int)$line['line_id'],'settlements'=>[],'applications'=>[]];
            $facts[$pid]['settlements'][]=$sid;
            $facts[$pid]['applications'][$sid]=['amount'=>(string)$line['applied_amount'],'number'=>(string)$line['settlement_no'],'voucher_amount'=>(string)$line['voucher_amount']];
        }
        $byPayable=(new FinanceResultService())->links($site,'payable',$facts,$config,true);
        $out=[]; $bySettlement=[];
        foreach ($byPayable as $pid=>$rows) foreach ($rows as $row) {
            if (!empty($row['settlement_id'])) $bySettlement[$pid][(int)$row['settlement_id']]=$row;
        }
        foreach ($lines as $line) {
            $pid=(int)$line['payable_id']; $sid=(int)$line['settlement_id']; $row=$bySettlement[$pid][$sid]??null;
            if (!$row) continue;
            $key='boyuan_original_payment_'.$pid.'_'.$sid;
            $serial=(string)(($line['imei']??'')?:($line['sn']??''));
            $scope=count($facts)>1 && $serial!==''?' · 串号尾号 '.mb_substr($serial,-6):'';
            $row['key']=$key; $row['provider_label']='博远付款单'.$scope;
            $row['next_step']='本站凭证 '.$line['settlement_no'].'，本机已登记 ¥'.$line['applied_amount'].'。'.match($row['status']){
                'registered'=>'博远已返回付款单号，无需再次付款。',
                'not_dispatched'=>'尚无博远付款回执。核实原采购后，只同步这张已有凭证，无需再次打款。',
                default=>$row['next_step'],
            };
            // The result is also visible to staff without access to plugin management. No forced navigation.
            $row['pc_route']=''; $row['mobile_path']='';
            $out[$key]=$row;
        }
        if ($out) $out['allocation']=['key'=>'boyuan_original_allocation','provider_label'=>'博远原采购核销','status'=>'unverified','status_label'=>'待核对','tone'=>'warning',
            'external_no'=>'','task_id'=>0,'pc_route'=>'','mobile_path'=>'','query'=>[],
            'next_step'=>'尚未取得博远把这笔付款冲到本张采购的核销明细。本站已付或已取得博远付款单号，都不等于原采购已结清。'];
        return array_values($out);
    }
}
