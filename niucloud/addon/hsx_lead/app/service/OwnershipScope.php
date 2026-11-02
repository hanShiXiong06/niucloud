<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/** Explicit site/uid identity; never uses request identity or a stale permission cache. */
final class OwnershipScope
{
    public const ALL_DATA='hsx_lead_manage_all_data';
    public const TRANSFER='hsx_lead_manage_lead_owner';
    private bool $schemaChecked=false;

    public function __construct(private Context $context) {}

    public function operator(?int $uid=null): array
    {
        $uid=$uid??$this->context->uid;
        if ($uid<=0) throw new CommonException('此操作仅限有权限的站点工作人员');
        $user=Db::name('sys_user')->where('uid',$uid)->where('status',1)->where('is_del',0)->where('delete_time',0)
            ->field('uid,username,real_name')->find();
        if (!$user) throw new CommonException('工作人员已停用或失效，请重新登录');
        $roles=Db::name('sys_user_role')->where('uid',$uid)->where('status',1)->where('delete_time',0)
            ->whereIn('site_id',[0,$this->context->site])->field('site_id,is_admin,role_ids')->select()->toArray();
        $siteRole=null; $super=false;
        foreach ($roles as $role) {
            if ((int)$role['site_id']===0 && (int)$role['is_admin']===1) $super=true;
            if ((int)$role['site_id']===$this->context->site) $siteRole=$role;
        }
        $admin=$super || ($siteRole && (int)$siteRole['is_admin']===1);
        if (!$siteRole && !$super) throw new CommonException('当前工作人员不属于本站，或本站权限已停用');
        $keys=[];
        if (!$admin) {
            $ids=Data::decode($siteRole['role_ids']??'');
            $ids=array_values(array_filter($ids,static fn($id):bool=>(is_int($id)||is_string($id)) && preg_match('/^[1-9][0-9]*$/D',(string)$id)===1));
            if ($ids) foreach (Db::name('sys_role')->where('site_id',$this->context->site)->where('status',1)->whereIn('role_id',$ids)->column('rules') as $rules) {
                foreach (Data::decode($rules) as $key) if (is_string($key) && str_starts_with($key,'hsx_lead')) $keys[]=$key;
            }
            $keys=$keys?Db::name('sys_menu')->where('app_type','site')->where('status',1)->where('delete_time',0)
                ->whereIn('menu_key',array_values(array_unique($keys)))->column('menu_key'):[];
            if (!$keys) throw new CommonException('尚未获授客资协作操作权限，请联系管理员');
        }
        return ['uid'=>$uid,'name'=>$this->name($user),'admin'=>$admin,'super'=>$super,'keys'=>$keys,'site_role'=>$siteRole!==null];
    }

    public function scope(): array
    {
        $this->ensureSchema();
        $actor=$this->operator();
        $all=$actor['admin'] || in_array(self::ALL_DATA,$actor['keys'],true);
        return ['all_data'=>$all,'can_transfer'=>$all && ($actor['admin'] || in_array(self::TRANSFER,$actor['keys'],true)),
            'uid'=>$this->context->uid,'label'=>$all?'全站客资与关联业务':'仅本人负责的客资与关联业务'];
    }

    public function permission(string $key): void
    {
        if (!$this->allows($key)) throw new CommonException('当前角色没有该操作权限，请联系管理员');
    }

    public function allows(string $key): bool
    {
        $actor=$this->operator();
        return $actor['admin'] || in_array($key,$actor['keys'],true);
    }

    public function requireTransfer(): void
    {
        if (!$this->scope()['can_transfer']) throw new CommonException('仅有全量查看及归属转交权限的管理员可转交客资');
    }

    /** Query constraints precede pagination, counts, aggregate and keyword matching. */
    public function leads($query,array $params=[],string $prefix='')
    {
        $scope=$this->scope();
        $owner=null;
        if (array_key_exists('owner_uid',$params) && $params['owner_uid']!=='' && $params['owner_uid']!==null) {
            $owner=self::ownerId($params['owner_uid'],true);
            if (!$scope['all_data'] && $owner!==$this->context->uid) throw new CommonException('不能查询其他工作人员的客资');
        }
        if (!$scope['all_data']) $query->where($prefix.'owner_uid',$this->context->uid);
        elseif ($owner!==null) $query->where($prefix.'owner_uid',$owner);
        return $query;
    }

    public function tasks($query,array $params=[],string $prefix='')
    {
        $scope=$this->scope();
        $filtered=array_key_exists('owner_uid',$params) && $params['owner_uid']!=='' && $params['owner_uid']!==null;
        if ($scope['all_data'] && !$filtered) return $query;
        return $query->whereIn($prefix.'lead_id',function($sub)use($params):void {
            $sub->name('hsx_lead_lead')->field('id')->where('site_id',$this->context->site);
            $this->leads($sub,$params);
        });
    }

    public function commissions($query,array $params=[],string $prefix='')
    {
        $scope=$this->scope();
        $filtered=array_key_exists('owner_uid',$params) && $params['owner_uid']!=='' && $params['owner_uid']!==null;
        if ($scope['all_data'] && !$filtered) return $query;
        return $query->whereIn($prefix.'task_id',function($sub)use($params):void {
            $sub->name('hsx_lead_task')->field('id')->where('site_id',$this->context->site);
            $this->tasks($sub,$params);
        });
    }

    public function lead(int $id,bool $lock=false): array
    {
        $query=$this->leads(Data::query('lead',$this->context->site))->where('id',$id);
        if ($lock) $query->lock(true);
        $row=$query->find();
        if (!$row) throw new CommonException('客资不存在，或不在当前可查看的范围内');
        return $row;
    }

    public function present(array $lead): array
    {
        // Merchant/member APIs need no internal employee names or identifiers.
        if ($this->context->uid<=0) return [];
        $owner=(int)($lead['owner_uid']??0);
        $name=$owner>0?Db::name('sys_user')->where('uid',$owner)->where('status',1)->where('is_del',0)->where('delete_time',0)->field('uid,real_name,username')->find():null;
        return ['owner_uid'=>$owner,'owner_name'=>$owner===0?'未分配（历史资料）':($name?$this->name($name):'原工作人员（已失效）'),
            'can_transfer'=>$this->context->uid>0 && $this->scope()['can_transfer']];
    }

    public function owners(array $params=[]): array
    {
        $this->requireTransfer();
        $keyword=Data::text($params,'q',80);
        $query=Db::name('sys_user')->alias('u')->join('sys_user_role ur','ur.uid=u.uid')
            ->where('ur.site_id',$this->context->site)->where('ur.status',1)->where('ur.delete_time',0)
            ->where('u.status',1)->where('u.is_del',0)->where('u.delete_time',0);
        if ($keyword!=='') $query->whereLike('u.username|u.real_name','%'.addcslashes($keyword,'\\%_').'%');
        // Eligibility depends on current enabled role/menu grants, not just membership.
        $users=$query->distinct(true)->field('u.uid,u.username,u.real_name')->order('u.uid')->select()->toArray();
        $eligible=[];
        foreach ($users as $user) {
            try {$actor=$this->operator((int)$user['uid']);} catch (CommonException $error) {continue;}
            if (!$this->eligible($actor)) continue;
            $eligible[]=['uid'=>(int)$user['uid'],'name'=>$actor['name']];
        }
        $page=max(1,(int)($params['page']??1)); $size=max(1,min(100,(int)($params['limit']??20)));
        return ['data'=>array_slice($eligible,($page-1)*$size,$size),'total'=>count($eligible),'per_page'=>$size,'current_page'=>$page];
    }

    public function target(int $uid): array
    {
        $actor=$this->operator($uid);
        if (!$this->eligible($actor)) throw new CommonException('归属人员必须是本站启用且有客资列表及详情权限的工作人员');
        return $actor;
    }

    private function eligible(array $actor): bool
    {
        return $actor['site_role'] && ($actor['admin']
            || (in_array('hsx_lead_manage_leads',$actor['keys'],true) && in_array('hsx_lead_manage_lead_info',$actor['keys'],true)));
    }

    private function ensureSchema(): void
    {
        if ($this->schemaChecked) return;
        $prefix=(string)Db::connect()->getConfig('prefix');
        if (!preg_match('/^[a-zA-Z0-9_]*$/D',$prefix)) throw new CommonException('请先完成客资协作 1.1.1 数据库升级后重试');
        try {$columns=Db::query('SHOW COLUMNS FROM `'.$prefix.'hsx_lead_lead`');}
        catch (\Throwable $error) {throw new CommonException('请先完成客资协作 1.1.1 数据库升级后重试');}
        $fields=array_column($columns,null,'Field'); $owner=$fields['owner_uid']??null;
        if (!$owner || !preg_match('/^int(?:\([0-9]+\))? unsigned$/i',(string)$owner['Type']) || $owner['Null']!=='NO' || (string)$owner['Default']!=='0') {
            throw new CommonException('请先完成客资协作 1.1.1 数据库升级：缺少有效归属字段 owner_uid，未放开数据范围');
        }
        $this->schemaChecked=true;
    }

    public static function ownerId($value,bool $zero=false): int
    {
        if ((!is_int($value)&&!is_string($value)) || preg_match($zero?'/^(?:0|[1-9][0-9]{0,9})$/D':'/^[1-9][0-9]{0,9}$/D',(string)$value)!==1
            || (float)$value>4294967295) throw new CommonException('请选择有效的归属工作人员');
        return (int)$value;
    }

    private function name(array $user): string
    {
        return trim((string)($user['real_name']??'')) ?: ((string)($user['username']??'') ?: '工作人员');
    }
}
