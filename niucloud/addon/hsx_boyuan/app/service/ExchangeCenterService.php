<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\{ExchangeJournal,Problem};
use addon\hsx_erp\app\support\ErpDevicePayableDisplay;
use think\facade\Db;

/** The documents are accounting facts; the journal is transport evidence. Neither path dispatches. */
final class ExchangeCenterService
{
    public static function available(int $site,array $c): bool
    {
        $mode=$c['mode'];$p=$c['profiles'][$mode];
        if(!in_array($mode,['test','production'],true) || empty($p['probe_at']) || empty($p['connection_key'])) return false;
        if(!empty($c['enabled']) || !empty($p['exchange_enabled_once'])) return true;
        // The current connection's history stays readable even when dispatch is subsequently disabled.
        return Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'connection_key'=>$p['connection_key'],'mode'=>$mode])->limit(1)->value('id')!==null;
    }
    public static function read(int $site,array $input): array
    {
        $cfg=(new ConfigService())->get($site);$mode=$cfg['mode'];$key=$cfg['profiles'][$mode]['connection_key'];
        if(!self::available($site,$cfg)) throw new Problem('JOURNAL_NOT_CONNECTED','本站尚未启用博远对接');
        $q=mb_substr(trim((string)$input['keyword']),0,128);
        if($input['view']==='detail') return ExchangeJournal::detail(ExchangeJournal::root(),$site,$mode,$key,(string)$input['day'],(string)$input['call_id'],(string)$input['side']);
        if($input['view']==='exchanges'){
            if($input['day']==='history') return ExchangeJournal::forTask(ExchangeJournal::root(),$site,$mode,$key,$q,(string)$input['cursor']);
            if($input['day']==='recent') return ExchangeJournal::recent(ExchangeJournal::root(),$site,$mode,$key,$q,(string)$input['cursor']);
            return ExchangeJournal::read(ExchangeJournal::root(),$site,$mode,$key,(string)$input['day'],$q,(string)$input['cursor']);
        }
        if(in_array($input['view'],['purchases','payments','purchase_detail','payment_detail'],true))
            return self::ledger($site,$cfg,$input['view'],$q,(int)($input['id']??0),max(1,(int)$input['page']));
        if($input['view']!=='documents') throw new Problem('JOURNAL_VIEW','请重新选择单据或接口记录');
        return self::devices($site,$cfg,$q,max(1,(int)$input['page']));
    }
    private static function assetQuery(int $site)
    {
        $t=static fn($name)=>Db::name($name)->getTable();
        $q=Db::name('erp_asset')->alias('a')->join($t('erp_purchase_order').' o','a.site_id=o.site_id AND o.id=a.purchase_order_id')
            ->where('a.site_id',$site)->field('a.id,a.imei,a.sn,a.model,a.purchase_item_id,a.purchase_order_id,o.purchase_no');
        ErpDevicePayableDisplay::attach($q,$t('erp_payable'),$t('erp_purchase_return_item'));
        return $q;
    }
    /** Paginate ORIGINAL headers, never settlement links; allocations may span devices or purchases. */
    private static function ledger(int $site,array $cfg,string $view,string $kw,int $id,int $page): array
    {
        $purchase=in_array($view,['purchases','purchase_detail'],true);
        $detail=str_ends_with($view,'_detail');
        $t=static fn($name)=>Db::name($name)->getTable();
        $p=$t('erp_payable');$l=$t('erp_settlement_link');$a=$t('erp_asset');$o=$t('erp_purchase_order');
        $number=$purchase?'purchase_no':'settlement_no';$at=$purchase?'purchase_at':'confirmed_at';
        $amount=$purchase?'total_cost':'amount';
        $h=Db::name($purchase?'erp_purchase_order':'erp_settlement')->alias('h')->where('h.site_id',$site);
        $rel=" FROM $l l INNER JOIN $p p ON p.site_id=l.site_id AND p.id=l.target_id
            WHERE l.site_id=h.site_id AND l.settlement_id=h.id AND l.target_type='payable' AND p.source_type='purchase_asset'";
        $h->field("h.id,h.$number AS number,h.$at AS at,h.$amount AS amount,h.status");
        $children='';
        if($purchase){
            $children=" FROM $a a WHERE a.site_id=h.site_id AND a.purchase_order_id=h.id";
            $h->whereRaw("EXISTS (SELECT 1 $children)")->fieldRaw("(SELECT COUNT(*) $children) AS device_count");
        }else{
            $h->where(['h.status'=>'confirmed','h.cash_direction'=>'out','h.settlement_type'=>'payment'])->where('h.confirmed_at','>',0)
                ->fieldRaw("(SELECT COUNT(DISTINCT CASE WHEN p.party_id=h.party_id AND l.applied_amount>0 THEN p.source_id END) $rel) AS device_count");
        }
        if($detail){
            $header=$id>0?$h->where('h.id',$id)->find():null;
            if(!$header)throw new Problem('JOURNAL_ORIGINAL_MISSING','本站未找到可查看的原始业务单据');
            $assets=self::assetQuery($site);
            if($purchase)$assets->where('a.purchase_order_id',$id);
            else $assets->whereRaw("EXISTS (SELECT 1 FROM $l l INNER JOIN ".$t('erp_settlement')." s ON s.site_id=l.site_id AND s.id=l.settlement_id
                WHERE l.site_id=a.site_id AND l.settlement_id=? AND l.target_type='payable' AND l.target_id=dp.id AND l.applied_amount>0 AND dp.party_id=s.party_id)",[$id]);
            $data=$assets->order('a.id desc')->paginate(['list_rows'=>10,'page'=>$page])->toArray();
            $rows=self::project($site,$cfg,$data['data'],$purchase?0:$id);
            return self::header($header,$purchase)+['rows'=>$rows,'total'=>$data['total']];
        }
        if($kw!==''){
            $like='%'.addcslashes($kw,'%_\\').'%';
            // Restrict external-number results to this kind and this exact trusted original.
            $external=self::externalAssets($site,$cfg,$kw,$purchase?'purchase':'payments');
            $h->where(function($group)use($site,$purchase,$number,$like,$external,$children,$rel,$a,$o){
                $group->whereLike("h.$number",$like);
                if($purchase){
                    $group->whereRaw("EXISTS (SELECT 1 $children AND (a.imei LIKE ? OR a.sn LIKE ?))",[$like,$like],'OR');
                    if($external)$group->whereIn('h.id',function($sub)use($a,$site,$external){$sub->table($a)->where('site_id',$site)->whereIn('id',$external)->field('purchase_order_id');},'OR');
                }else{
                    $group->whereRaw("EXISTS (SELECT 1 $rel AND p.party_id=h.party_id AND l.applied_amount>0 AND EXISTS
                        (SELECT 1 FROM $a a INNER JOIN $o o ON o.site_id=a.site_id AND o.id=a.purchase_order_id WHERE
                        a.site_id=p.site_id AND a.id=p.source_id AND (a.imei LIKE ? OR a.sn LIKE ? OR o.purchase_no LIKE ?)))",[$like,$like,$like],'OR');
                    if($external)$group->whereIn('h.id',$external,'OR');
                }
            });
        }
        $data=$h->order("h.$at desc,h.id desc")->paginate(['list_rows'=>10,'page'=>$page])->toArray();
        return ['rows'=>array_map(static fn($r)=>self::header($r,$purchase),$data['data']),'total'=>$data['total']];
    }
    private static function header(array $row,bool $purchase): array
    {
        $row['status_label']=$purchase?(['draft'=>'采购草稿','completed'=>'已入库','returned'=>'已退货','void'=>'已撤销'][$row['status']]??'本站状态待核对'):'本站已登记';
        return $row;
    }
    /** An ERP receipt number identifies a batch/voucher, not all devices of the local purchase.
     * Snapshot line IDs only nominate candidates; confirm them through the same per-device read model. */
    private static function externalAssets(int $site,array $cfg,string $number,string $scope=''): array
    {
        $mode=$cfg['mode'];
        $tasks=Db::name('hsx_boyuan_task')->where(['site_id'=>$site,'mode'=>$mode,'connection_key'=>$cfg['profiles'][$mode]['connection_key'],'external_no'=>$number])
            ->whereIn('kind',['purchase',PaymentSyncService::KIND])->column('snapshot_json');
        $ids=[];
        foreach($tasks as $json){
            $source=json_decode((string)$json,true);
            foreach($source['lines']??[] as $line){
                if(is_array($line) && preg_match('/^asset:([1-9][0-9]*)$/D',(string)($line['device_ref']??''),$m)) $ids[(int)$m[1]]=true;
            }
        }
        $found=[];
        foreach(array_chunk(array_keys($ids),100) as $chunk){
            $assets=self::assetQuery($site)->whereIn('a.id',$chunk)->select()->toArray();
            foreach(self::project($site,$cfg,$assets) as $row){
                foreach($scope===''?array_merge($row['purchase'],$row['payments']):$row[$scope] as $receipt) if((string)$receipt['external_no']===$number){
                    $found[]=$scope==='payments'?(int)$receipt['settlement_id']:$row['id'];
                }
            }
        }
        return array_values(array_unique($found));
    }
    private static function devices(int $site,array $cfg,string $kw,int $page): array
    {
        $t=static fn($name)=>Db::name($name)->getTable();
        $q=self::assetQuery($site);
        $like='%'.addcslashes($kw,'%_\\').'%';
        if($kw!==''){
            $external=self::externalAssets($site,$cfg,$kw);
            $q->where(function($group)use($external,$like,$site,$t,$kw){
                $group->whereLike('o.purchase_no|a.imei|a.sn',$like);
                $group->whereIn('dp.id',function($sub)use($t,$site,$like,$kw){
                    $sub->table($t('erp_settlement_link'))->alias('l')->join($t('erp_settlement').' s','s.site_id=l.site_id AND s.id=l.settlement_id')
                        ->join($t('erp_payable').' p','p.site_id=l.site_id AND p.id=l.target_id AND p.party_id=s.party_id')
                        ->where(['l.site_id'=>$site,'l.target_type'=>'payable','s.status'=>'confirmed','s.cash_direction'=>'out','s.settlement_type'=>'payment'])
                        ->where('s.confirmed_at','>',0)->where('l.applied_amount','>',0)->field('l.target_id');
                    if(str_starts_with(strtoupper($kw),'ST')) $sub->whereLike('s.settlement_no',$like);
                    else $sub->where('s.settlement_no',$kw);
                },'OR');
                if($external) $group->whereIn('a.id',$external,'OR');
            });
        }
        $data=$q->order('a.id desc')->paginate(['list_rows'=>10,'page'=>$page])->toArray();
        return ['rows'=>self::project($site,$cfg,$data['data']),'total'=>$data['total']];
    }
    private static function project(int $site,array $cfg,array $assets,int $onlySettlement=0): array
    {
        $t=static fn($name)=>Db::name($name)->getTable();
        $rows=[];$facts=[];$pids=[];
        foreach($assets as $a){
            $hint=ErpDevicePayableDisplay::take($a);$pid=(int)($hint['payable_id']??0);$id=(int)$a['id'];
            // Unknown originals still get their device-specific purchase task, never an invented payment.
            $fk=$pid?:-$id;$facts[$fk]=['asset_id'=>$id,'document_id'=>(int)$a['purchase_order_id'],'number'=>$a['purchase_no'],'line_id'=>(int)$a['purchase_item_id'],'settlements'=>[]];
            if($pid) $pids[]=$pid;
            $rows[$fk]=['id'=>$id,'model'=>$a['model'],'serial'=>$a['imei']?:$a['sn'],'number'=>$a['purchase_no'],'payable'=>$hint,'settlements'=>[]];
        }
        if($pids){
            $links=Db::name('erp_settlement_link')->alias('l')->join($t('erp_payable').' p','p.site_id=l.site_id AND p.id=l.target_id')
                ->join($t('erp_settlement').' s','s.site_id=l.site_id AND s.id=l.settlement_id AND s.party_id=p.party_id')
                ->where(['l.site_id'=>$site,'l.target_type'=>'payable','s.status'=>'confirmed','s.cash_direction'=>'out','s.settlement_type'=>'payment'])
                ->where('s.confirmed_at','>',0)->where('l.applied_amount','>',0)->whereIn('l.target_id',$pids)
                ->field('l.target_id,s.id,s.settlement_no,s.confirmed_at,s.amount voucher_amount,l.applied_amount')->order('s.confirmed_at desc');
            if($onlySettlement)$links->where('s.id',$onlySettlement);
            foreach($links->select() as $row){
                $fk=(int)$row['target_id'];$sid=(int)$row['id'];$facts[$fk]['settlements'][]=$sid;
                $facts[$fk]['applications'][$sid]=['amount'=>(string)$row['applied_amount'],'number'=>$row['settlement_no'],'voucher_amount'=>(string)$row['voucher_amount']];
                $rows[$fk]['settlements'][]=['id'=>$sid,'number'=>$row['settlement_no'],'at'=>(int)$row['confirmed_at'],'amount'=>$row['applied_amount']];
            }
        }
        $results=(new FinanceResultService())->links($site,'payable',$facts,$cfg);
        foreach($rows as $fk=>&$r){
            $r['purchase']=[];$r['payments']=[];
            foreach($results[$fk]??[] as $doc){
                if($doc['provider_label']==='博远采购单') $r['purchase'][]=$doc;
                elseif($fk>0) $r['payments'][]=$doc;
            }
        }unset($r);
        return array_values($rows);
    }
}
