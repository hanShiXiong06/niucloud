<?php
declare(strict_types=1);
namespace addon\hsx_recycle\app\service\core\audit;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use core\exception\CommonException;
use think\facade\Db;

/** Internal operation summaries only; no wire payloads, staff passwords, free-text errors or account data. */
final class OperationAuditService
{
    private const RESULTS=['success'=>'成功','failed'=>'失败','pending'=>'处理中','review'=>'待核对','recorded'=>'已记录','sent'=>'已发送','received'=>'已收到响应','skipped'=>'已跳过'];
    private const SOURCES=['erp'=>'ERP','boyuan'=>'博远'];
    private static function token($value,int $max=80): string
    {
        return (is_string($value)||is_int($value)) && strlen((string)$value)<=$max && preg_match('/^[a-zA-Z0-9._:\/-]+$/D',(string)$value)?(string)$value:'';
    }
    private static function ids($ids): array
    {
        if(!is_array($ids))return [];
        return array_values(array_unique(array_filter(array_map(static fn($v)=>(is_int($v)||is_string($v)&&ctype_digit($v))?(int)$v:0,$ids),static fn($v)=>$v>0)));
    }
    public function record(array $e): void
    {
        $site=(int)($e['site_id']??0);$source=$e['source']??'';$result=$e['result']??'';
        $id=$e['event_id']??'';$action=self::token($e['action']??'',80);
        if($site<=0||!is_string($source)||!isset(self::SOURCES[$source])||!is_string($result)||!isset(self::RESULTS[$result])||
            !is_string($id)||$id===''||strlen($id)>200||$action==='') return;
        // Sender supplies the fixed business label, never a remote message/remark.
        $label=mb_substr(trim((string)($e['label']??'')),0,60);
        if($label===''||preg_match('/[\x00-\x1f<>={}]/u',$label))$label=$action;
        $orders=self::ids($e['order_ids']??[]);$devices=self::ids($e['device_ids']??[]);$groups=[];
        foreach(array_chunk($orders,500) as $chunk)
            foreach(Db::name('recycle_order')->where('site_id',$site)->whereIn('id',$chunk)->column('id') as $oid)$groups[(int)$oid]=[];
        $orderTable=Db::name('recycle_order')->getTable();
        foreach(array_chunk($devices,500) as $chunk){
            // A historical device may have site_id=0. Only its positive-site original order proves tenancy.
            $rows=Db::name('recycle_device')->alias('d')->join($orderTable.' o','o.id=d.order_id')
                ->where('o.site_id',$site)->whereIn('d.site_id',[0,$site])->whereIn('d.id',$chunk)->field('d.id,d.order_id')->select();
            foreach($rows as $d) $groups[(int)$d['order_id']][]=(int)$d['id'];
        }
        if(!$groups){
            if($orders||$devices) return; // Explicit dangling/foreign references cannot become site-wide facts.
            $groups=[0=>[]];
        }
        $uid=max(0,(int)($e['operator_id']??0));$name=mb_substr(trim((string)($e['operator_name']??'')),0,50);
        $at=(int)($e['occurred_at']??0);
        $meta=['source'=>$source,'action'=>$action,'label'=>$label,'result'=>$result,'business_no'=>self::token($e['business_no']??''),
            'external_no'=>self::token($e['external_no']??''),'task_id'=>max(0,(int)($e['task_id']??0)),'call_id'=>self::token($e['call_id']??'')];
        // One site-wide event in the list, while every affected original order retains its own local slice.
        // Row kind is indexed/scalar, not a JSON_CONTAINS scan on the entire timeline.
        ksort($groups,SORT_NUMERIC);$primary=(int)array_key_first($groups);$related=[];
        if(count($groups)>1){
            foreach(array_chunk(array_keys($groups),500) as $chunk)
                foreach(Db::name('recycle_order')->where('site_id',$site)->whereIn('id',$chunk)->column('order_no','id') as $i=>$no)
                    $related[]=['id'=>(int)$i,'no'=>(string)$no];
        }
        $rows=[];
        foreach($groups as $order=>$dids){
            sort($dids,SORT_NUMERIC);
            $rows[]=['site_id'=>$site,'order_id'=>$order,'operator_id'=>$uid,'operator_name'=>$uid?($name?:'员工 #'.$uid):'系统',
                'action'=>'audit_'.$source.($order===$primary?'':'_link'),'old_status'=>0,'new_status'=>0,'remark'=>$label.' · '.self::RESULTS[$result],
                'create_at'=>$at>0?$at:time(),'audit_key'=>hash('sha256',$source.':'.$id),
                'audit_data'=>json_encode($meta+['device_ids'=>$dids]+($order===$primary && $related?['related_orders'=>$related]:[]),JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)];
        }
        Db::name('recycle_order_log')->duplicate(Db::raw('id=id'))->insertAll($rows);
    }

    /** Keyset across two native tables. Each stream reads at most limit+1 valid rows, no row-by-row lookups. */
    public function page(int $site,array $in): array
    {
        if($site<=0) throw new CommonException('请进入站点后查看');
        $source=(string)($in['source']??'');$oid=(int)($in['order_id']??0);
        if(!in_array($source,['','recycle','erp','boyuan'],true)||$oid<0) throw new CommonException('来源或回收订单不正确');
        $limit=min(50,max(1,(int)($in['limit']??20)));$cursor=(string)($in['cursor']??'');$position=[];
        if($cursor!==''){
            if(!preg_match('/^(\d{1,10}):([01]):(\d{1,19})$/D',$cursor,$m)) throw new CommonException('列表位置无效，请刷新');
            $position=[(int)$m[1],(int)$m[2],(int)$m[3]];
        }
        $order=Db::name('recycle_order')->getTable();$rows=[];
        foreach([1,0] as $rank){
            if(!$rank && isset(self::SOURCES[$source]))continue;
            if($rank){
                $q=Db::name('recycle_order_log')->alias('l')->leftJoin($order.' o','o.id=l.order_id');
                $q->where(function($s)use($site){
                    $s->where(function($x)use($site){$x->whereIn('l.site_id',[0,$site])->where('o.site_id',$site);})
                      ->whereOr(function($x)use($site){$x->where('l.site_id',$site)->where('l.order_id',0)->whereNotNull('l.audit_key');});
                });
                if(!$oid)$q->whereNotIn('l.action',['audit_erp_link','audit_boyuan_link']);
                if(isset(self::SOURCES[$source]))$q->whereIn('l.action',$oid?['audit_'.$source,'audit_'.$source.'_link']:['audit_'.$source])->whereNotNull('l.audit_key');
                if($source==='recycle')$q->whereNull('l.audit_key');
                $q->field('l.id,l.order_id,l.operator_id,l.operator_name,l.action,l.new_status,l.remark,l.create_at,l.audit_key,l.audit_data,o.order_no');
            } else {
                $q=Db::name('recycle_device_log')->alias('l')->join(Db::name('recycle_device')->getTable().' d','d.id=l.device_id')
                    ->join($order.' o','o.id=d.order_id')->where('o.site_id',$site)->whereIn('d.site_id',[0,$site])
                    ->whereIn('l.site_id',[0,$site])->whereRaw('(l.order_id=0 OR l.order_id=o.id)')
                    ->field('l.id,d.order_id,l.operator_id,l.operator_name,l.device_id,l.operation_type,l.action,l.new_status,l.remark,l.create_at,o.order_no');
            }
            if($oid)$q->where('o.id',$oid);
            if($position){
                [$ts,$tie,$last]=$position;
                $q->where(function($s)use($rank,$ts,$tie,$last){
                    $s->where('l.create_at','<',$ts);
                    if($rank<$tie)$s->whereOr('l.create_at','=',$ts);
                    if($rank===$tie)$s->whereOr(function($x)use($ts,$last){$x->where('l.create_at',$ts)->where('l.id','<',$last);});
                });
            }
            foreach($q->order('l.create_at desc,l.id desc')->limit($limit+1)->select() as $r){
                $meta=!empty($r['audit_key'])?(json_decode((string)$r['audit_data'],true)?:[]):[];
                $kind=$meta['source']??'recycle';
                $label=$meta['label']??($rank?($r['remark']?:($r['action']?:'订单操作')):RecycleOrderDict::getDeviceLogOperationName($r));
                $rows[]=['id'=>(int)$r['id'],'rank'=>$rank,'time'=>(int)$r['create_at'],'source'=>$kind,'source_label'=>self::SOURCES[$kind]??'回收',
                    'label'=>mb_substr((string)$label,0,100),'result'=>$meta['result']??'recorded','result_label'=>self::RESULTS[$meta['result']??'recorded']??'已记录',
                    'order_id'=>(int)$r['order_id'],'order_no'=>(string)$r['order_no'],'device_ids'=>$meta['device_ids']??($rank?[]:[(int)$r['device_id']]),
                    'related_orders'=>$oid?[]:($meta['related_orders']??[]),
                    'operator'=>$r['operator_name']?:((int)$r['operator_id']?'员工 #'.$r['operator_id']:'未记录'),
                    'remark'=>$meta?'':(string)$r['remark'],'business_no'=>$meta['business_no']??'','external_no'=>$meta['external_no']??'',
                    'site_id'=>$site,'action'=>$meta['action']??'','task_id'=>$meta['task_id']??0,'call_id'=>$meta['call_id']??''];
            }
        }
        usort($rows,static fn($a,$b)=>[$b['time'],$b['rank'],$b['id']]<=>[$a['time'],$a['rank'],$a['id']]);
        $more=count($rows)>$limit;$rows=array_slice($rows,0,$limit);$last=end($rows);
        return ['rows'=>$rows,'next'=>$more?$last['time'].':'.$last['rank'].':'.$last['id']:''];
    }
}
