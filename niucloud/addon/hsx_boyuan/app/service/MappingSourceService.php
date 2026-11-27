<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\Problem;
use think\facade\Db;

/** Read local ERP identities only; never creates staff, parties, inventory or accounts. */
final class MappingSourceService
{
    public static function assertMatches(int $site,string $kind,string $ref): void
    {
        if($kind==='product' && preg_match('/^model:[a-f0-9]{64}$/D',$ref)) {
            foreach(Db::name('erp_asset')->where('site_id',$site)->field('model,spec,color')->group('model,spec,color')->cursor() as $row) {
                $values=['model'=>(string)$row['model'],'spec'=>(string)$row['spec'],'color'=>(string)$row['color']];
                if('model:'.hash('sha256',json_encode($values,JSON_UNESCAPED_UNICODE))===$ref) return;
            }
        }
        $tables=['warehouse'=>['erp_warehouse','warehouse:'],'location'=>['erp_warehouse_location','location:'],
            'customer'=>['erp_party','party:'],'supplier'=>['erp_party','party:'],'account'=>['erp_capital_account','account:']];
        if(isset($tables[$kind])) {
            [$table,$prefix]=$tables[$kind]; $id=substr($ref,strlen($prefix));
            if(str_starts_with($ref,$prefix) && preg_match('/^[1-9][0-9]*$/D',$id)) {
                $q=Db::name($table)->where('site_id',$site)->where('id',$id)->where('status',1);
                if(in_array($kind,['customer','supplier'],true)) self::partyRole($q,$kind);
                if($q->count()) return;
            }
        }
        if($kind==='staff' && preg_match('/^staff:([1-9][0-9]*)$/D',$ref,$match)
            && Db::name('sys_user_role')->where('site_id',$site)->where('uid',$match[1])->where('status',1)->count()
            && Db::name('sys_user')->where('uid',$match[1])->where('status',1)->where('delete_time',0)->count()) return;
        if($kind==='method') foreach((new self())->search($site,['kind'=>'method'])['items'] as $item) if($item['ref']===$ref) return;
        throw new Problem('LOCAL_SOURCE_MISSING','本站资料不存在、已停用或不属于当前站点，请从列表选择；不会自动创建资料');
    }
    public function search(int $site,array $input): array
    {
        $kind=(string)($input['kind']??''); $page=max(1,(int)($input['page']??1)); $limit=25;
        $keyword=trim((string)($input['keyword']??'')); $pattern='%'.addcslashes(mb_substr($keyword,0,100),'%_\\').'%';
        if($kind==='product') {
            $q=Db::name('erp_asset')->where('site_id',$site)->field('model,spec,color')->group('model,spec,color')->order('model,spec,color');
            if($keyword!=='') $q->where(function($w) use($pattern): void {$w->whereLike('model',$pattern)->whereOr('spec','like',$pattern)->whereOr('color','like',$pattern);});
            $rows=$q->limit(($page-1)*$limit,$limit+1)->select()->toArray(); $items=[];
            foreach($rows as $row) { $values=['model'=>(string)$row['model'],'spec'=>(string)$row['spec'],'color'=>(string)$row['color']];
                $items[]=['ref'=>'model:'.hash('sha256',json_encode($values,JSON_UNESCAPED_UNICODE)),'name'=>trim(implode(' ',$values))]; }
        } else {
            $definitions=['warehouse'=>['erp_warehouse','id','warehouse_name','warehouse:'],'location'=>['erp_warehouse_location','id','location_name','location:'],
                'customer'=>['erp_party','id','party_name','party:'],'supplier'=>['erp_party','id','party_name','party:'],'account'=>['erp_capital_account','id','account_name','account:']];
            if($kind==='staff') {
                $ids=Db::name('sys_user_role')->where('site_id',$site)->where('status',1)->column('uid');
                if(!$ids) return ['items'=>[],'has_more'=>false];
                $q=Db::name('sys_user')->whereIn('uid',$ids)->where('status',1)->where('delete_time',0); $id='uid'; $name='real_name'; $prefix='staff:';
            } elseif($kind==='method') {
                $values=Db::name('erp_capital_account')->where('site_id',$site)->distinct(true)->column('account_type');
                $values=array_unique(array_merge($values,Db::name('erp_sale_order')->where('site_id',$site)->distinct(true)->column('settle_method')));
                $items=[]; foreach($values as $value) if($value!=='' && ($keyword==='' || str_contains($value,$keyword))) $items[]=['ref'=>'method:'.$value,'name'=>['bank'=>'银行','cash'=>'现金','wechat'=>'微信','alipay'=>'支付宝'][$value]??$value];
                return ['items'=>$items,'has_more'=>false];
            } elseif(isset($definitions[$kind])) {
                [$table,$id,$name,$prefix]=$definitions[$kind]; $q=Db::name($table)->where('site_id',$site)->where('status',1);
                if(in_array($kind,['customer','supplier'],true)) self::partyRole($q,$kind);
            } else throw new Problem('MAPPING_INVALID','本地资料类型不支持');
            if($keyword!=='') $q->whereLike($name,$pattern);
            $phoneField=$kind==='staff'?'mobile':(in_array($kind,['customer','supplier'],true)?'contact_mobile':'');
            $rows=$q->field($id.','.$name.($phoneField!==''?','.$phoneField:''))->order($id)->limit(($page-1)*$limit,$limit+1)->select()->toArray(); $items=[];
            foreach($rows as $row) { $phone=(string)($row[$phoneField]??''); $items[]=['ref'=>$prefix.$row[$id],'name'=>(string)$row[$name],'phone_masked'=>$phone===''?'':mb_substr($phone,0,3).'****'.mb_substr($phone,-4)]; }
        }
        return ['items'=>array_slice($items,0,$limit),'has_more'=>count($items)>$limit];
    }
    private static function partyRole($query,string $kind): void
    {
        $flag=$kind==='supplier'?'purchase_supplier':'sale_customer';
        $query->where(function($q) use($kind,$flag): void { $q->whereIn('party_type',[$kind,'both'])->whereOrRaw("FIND_IN_SET('".$flag."', role_flags) > 0"); });
    }
}
