<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\CatalogStore;
use think\facade\Db;

/** A current catalogue is a view of the archive, never a destructive reconciliation. */
final class CategoryCatalog
{
    public static function current(int $site,string $connection)
    {
        $scope=['site_id'=>$site,'connection_key'=>$connection];
        $query=CatalogStore::query('categories')->where($scope);
        $jobs=$scope+['resource'=>'categories'];
        $baseline=(int)Db::name('hsx_boyuan_reference_sync')->where($jobs)->where('status','completed')->max('id');
        // A complete read supersedes older rows. Partial reads may add/update rows but cannot prove a deletion.
        if ($baseline>0) $query->whereIn('seen_run',static function ($sub) use ($jobs,$baseline) {
            $sub->name('hsx_boyuan_reference_sync')->where($jobs)->where('id','>=',$baseline)->field('run_key');
        });
        return $query;
    }

    /** Resolve by exact parent IDs in the latest received batch; never infer a parent from its name. */
    public static function path(int $site,string $connection,string $id): string
    {
        $last=Db::name('hsx_boyuan_reference_sync')->where(['site_id'=>$site,'connection_key'=>$connection,'resource'=>'categories'])
            ->order('id desc')->field('status,run_key')->find();
        if (!$last || !in_array($last['status'],['completed','needs_review'],true)) return '';
        $base=CatalogStore::query('categories')->where(['site_id'=>$site,'connection_key'=>$connection,'seen_run'=>$last['run_key']]);
        return self::walk($base,$id);
    }

    public static function currentPath(int $site,string $connection,string $id): string
    {
        return self::walk(self::current($site,$connection),$id);
    }

    private static function walk($base,string $id): string
    {
        $names=[]; $seen=[]; $parentName='';
        while ($id!=='') {
            if (isset($seen[$id]) || count($seen)>255) return '';
            $row=(clone $base)->where('external_id',$id)->field('external_id,name,parent_id,parent_name,enabled')->find();
            if (!$row && $names && in_array($id,['0','00000000-0000-0000-0000-000000000000'],true) && $parentName==='') break;
            if (!$row || $row['external_id']!==$id || !(int)$row['enabled']) return '';
            $name=trim((string)$row['name']);
            if ($name==='' || strpbrk($name,'/\\')!==false || preg_match('/[／＞>,，|｜]/u',$name)) return '';
            $seen[$id]=true; $names[]=$name;
            $id=(string)($row['parent_id']??''); $parentName=trim((string)($row['parent_name']??''));
            if ($id==='' && $parentName!=='') return '';
        }
        $path=implode('/',array_reverse($names));
        return mb_strlen($path)<=255?$path:'';
    }

    /** ERP stores a category path on quantity products. A non-unique name under a parent cannot identify a source ID. */
    public static function selection(int $site,string $key,array $event): array
    {
        $base=self::current($site,$key);
        $markers=['0','00000000-0000-0000-0000-000000000000'];
        $sentinels=array_diff($markers,(clone $base)->whereIn('external_id',$markers)->column('external_id'));
        $children=static function (?string $id) use ($base,$sentinels) {
            $q=(clone $base)->where('enabled',1);
            if ($id!==null) return $q->where('parent_id',$id);
            return $q->where(function($w) use ($sentinels){
                $w->whereNull('parent_id')->whereOr('parent_id','=','');
                if($sentinels) $w->whereOr('parent_id','in',array_values($sentinels));
            })->where(function($w){$w->whereNull('parent_name')->whereOr('parent_name','=','');});
        };
        $resolve=static function(string $path) use ($children) {
            $parent=null;
            if ($path==='' || mb_strlen($path)>255) return null;
            foreach (explode('/',$path) as $label) {
                if ($label==='') return null;
                $hits=$children($parent)->where('name',$label)->field('external_id,name')->limit(2)->select()->toArray();
                if (count($hits)!==1 || $hits[0]['name']!==$label) return null;
                $parent=(string)$hits[0]['external_id'];
            }
            return $parent;
        };
        $path=trim((string)($event['path']??'')); $parent=$path===''?null:$resolve($path);
        if (($event['action']??'')==='leaf') {
            $parts=explode('/',$path);
            return ['leaf'=>$parent!==null && !$children($parent)->count()?['category_path'=>$path,'category_name'=>end($parts)]:null];
        }
        if ($path!=='' && $parent===null) return ['list'=>[]];
        $keyword=trim((string)($event['keyword']??'')); $limit=max(1,min(500,(int)($event['limit']??200)));
        $q=$keyword===''?$children($parent):(clone $base)->where('enabled',1)->whereLike('name','%'.addcslashes(mb_substr($keyword,0,100),'%_\\').'%');
        $rows=$q->field('external_id,name')->order('code asc,id asc')->limit($limit)->select()->toArray();
        $hasChildren=$rows?(clone $base)->where('enabled',1)->whereIn('parent_id',array_column($rows,'external_id'))->group('parent_id')->column('parent_id'):[];
        $branch=array_fill_keys($hasChildren,true); $list=[];
        foreach ($rows as $r) {
            $id=(string)$r['external_id']; $label=(string)$r['name'];
            $full=$keyword!==''?self::walk($base,$id):($path===''?$label:$path.'/'.$label);
            if ($full==='' || mb_strlen($full)>255) continue;
            $leaf=!isset($branch[$id]);
            $list[]=['node_key'=>'source:'.$id,'node_type'=>'category','label'=>$label,'category_path'=>$full,'path_text'=>$full,
                'catalog_level'=>substr_count($full,'/')+1,'is_leaf'=>(int)$leaf,'has_children'=>(int)!$leaf];
        }
        return ['list'=>$list];
    }
}
