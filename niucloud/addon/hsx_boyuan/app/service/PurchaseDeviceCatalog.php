<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;

use addon\hsx_boyuan\app\support\CatalogStore;
use core\exception\AdminException;
use think\facade\Db;

/** Read the site's received master data. No browse, search or select operation creates an ERP record. */
final class PurchaseDeviceCatalog
{
    public static function scope(int $site,string $connection): string
    {
        return hash('sha256','hsx_boyuan:'.$site.':'.$connection);
    }

    private static function categories(int $site,string $key): array
    {
        $rows=CategoryCatalog::current($site,$key)->field('external_id,parent_id,parent_name,name,enabled')->order('code asc,id asc')->select()->toArray();
        $index=array_column($rows,null,'external_id'); $paths=[]; $visiting=[];
        $walk=static function(string $id) use (&$walk,&$paths,&$visiting,$index): ?string {
            if (array_key_exists($id,$paths)) return $paths[$id];
            $r=$index[$id]??null;
            if (!$r || isset($visiting[$id]) || count($visiting)>255 || !(int)$r['enabled']) return null;
            $name=trim((string)$r['name']); $parent=(string)($r['parent_id']??'');
            if ($name==='' || strpbrk($name,'/\\')!==false || preg_match('/[／＞>,，|｜]/u',$name)) return null;
            $root=$parent==='' || (!isset($index[$parent]) && in_array($parent,['0','00000000-0000-0000-0000-000000000000'],true));
            $visiting[$id]=true;
            $path=$root ? (trim((string)$r['parent_name'])===''?$name:null) : (($p=$walk($parent))!==null?$p.'/'.$name:null);
            unset($visiting[$id]);
            return $paths[$id]=$path!==null && mb_strlen($path)<=255?$path:null;
        };
        $result=[];
        foreach ($rows as $r) {
            $id=(string)$r['external_id']; $path=$walk($id);
            if ($path===null) continue;
            $parent=(string)$r['parent_id'];
            $result[$id]=['id'=>$id,'parent_id'=>isset($index[$parent])?$parent:'','label'=>trim($r['name']),'category_path'=>$path];
        }
        return $result;
    }

    private static function products(int $site,string $key)
    {
        $run=Db::name('hsx_boyuan_reference_sync')->where(['site_id'=>$site,'connection_key'=>$key,'resource'=>'products'])->order('id desc')->field('status,run_key')->find();
        if (($run['status']??'')!=='completed' || empty($run['run_key'])) throw new AdminException('商品档案还没有完整取回。请在博远对接的「基础资料」同步商品后重试。');
        return CatalogStore::query('products')->where(['site_id'=>$site,'connection_key'=>$key,'seen_run'=>$run['run_key'],'enabled'=>1]);
    }

    private static function scoped(string $expected,int $site,string $key): void
    {
        if (!hash_equals(self::scope($site,$key),$expected)) throw new AdminException('商品目录来源已切换，请重新打开设备并选择分类和型号');
    }

    /** Saving a cart of devices must not reread the entire category tree once per line. */
    private static function categoryById(int $site,string $key,string $id): ?array
    {
        if ($id==='') return null;
        $path=CategoryCatalog::currentPath($site,$key,$id);
        if ($path==='') return null;
        $parts=explode('/',$path);
        return ['id'=>$id,'label'=>end($parts),'category_path'=>$path];
    }

    public static function browse(int $site,string $key,array $q): array
    {
        $kind=(string)($q['node_type']??'root');
        if ($kind!=='root') self::scoped((string)($q['expected_scope']??''),$site,$key);
        $cats=self::categories($site,$key);
        if ($kind==='root') return ['scope'=>self::scope($site,$key),'list'=>array_values($cats),'maintenance'=>[
            'hint'=>'新增、修改或删除分类和型号，请先在博远 ERP 操作并保存，再回本站同步对应资料。没有同步权限时，请联系站点管理员。',
            'links'=>[
                ['label'=>'去同步分类','name'=>'hsx_boyuan_catalog_page','query'=>['resource'=>'categories']],
                ['label'=>'去同步商品','name'=>'hsx_boyuan_catalog_page','query'=>['resource'=>'products']],
            ],
        ]];
        $cat=(string)($q['category_id']??'');
        if ($cat!=='' && !isset($cats[$cat])) throw new AdminException('该分类已停用或不在当前目录，请重新选择');
        $base=self::products($site,$key);
        if ($kind==='selection') {
            $picked=self::pick($site,$key,$base,$cats,$cat,(string)($q['product_id']??''));
            return ['choice'=>$picked['choice']];
        }
        if ($kind!=='products') throw new AdminException('请选择分类或搜索型号');
        $children=[]; foreach ($cats as $c) $children[$c['parent_id']][]=$c['id'];
        $ids=$cat===''?array_column($cats,'id'):[$cat];
        if ($cat!=='') for ($i=0;$i<count($ids);++$i) foreach ($children[$ids[$i]]??[] as $child) $ids[]=$child;
        if (!$ids) return ['list'=>[],'more'=>false];
        $query=(clone $base)->whereIn('category_id',$ids);
        $word=trim((string)($q['keyword']??''));
        if ($word!=='') $query->whereLike('name|model_name|code|brand_name','%'.addcslashes(mb_substr($word,0,100),'%_\\').'%');
        $limit=30; $page=max(1,min(100000,(int)($q['page']??1)));
        $rows=$query->field('external_id,code,name,category_id,brand_name,memory,color')->order('name asc,id asc')->limit(($page-1)*$limit,$limit+1)->select()->toArray();
        $more=count($rows)>$limit; if ($more) array_pop($rows);
        $list=[]; foreach ($rows as $r) if (isset($cats[$r['category_id']])) $list[]=self::option($r,$cats[$r['category_id']]);
        return ['list'=>$list,'more'=>$more];
    }

    private static function option(array $p,array $c): array
    {
        return ['id'=>(string)$p['external_id'],'code'=>(string)$p['code'],'label'=>(string)$p['name'],'category_id'=>$c['id'],
            'category_path'=>$c['category_path'],'detail'=>implode(' · ',array_filter([$p['brand_name'],$p['memory'],$p['color']]))];
    }

    private static function pick(int $site,string $key,$base,array $cats,string $category,string $product): array
    {
        $r=$product===''?null:(clone $base)->where('external_id',$product)->find();
        if (!$r || $r['external_id']!==$product || (string)$r['category_id']!==$category || !isset($cats[$category]))
            throw new AdminException('商品已停用、分类已变更或不属于当前账套，请重新选择');
        $c=$cats[$category];
        $plan=(new OpeningMasterService())->purchaseProductPlan($site,$key,$r,$base,$c['category_path']);
        if (!in_array($plan['action'],['existing','create'],true)) throw new AdminException($plan['reason'].'。请核对博远商品档案后重新同步。');
        $local=$plan['action']==='existing'?(int)substr($plan['ref'],8):0;
        $option=self::option($r,$c);
        $ref=['scope'=>self::scope($site,$key),'category_id'=>$category,'product_id'=>$product,
            'revision'=>hash('sha256',json_encode([$option,$r['brand_id']],JSON_UNESCAPED_UNICODE))];
        return ['choice'=>$option+['node_type'=>'product','product_name'=>$option['label'],'site_product_id'=>$local,'catalog_reference'=>$ref],
            'resolution'=>['site_product_id'=>$local,'category_name'=>$c['label'],'category_path'=>$c['category_path'],
                'source_key'=>OpeningMasterService::productSourceKey($site,$key),'row'=>$plan['payload']??null]];
    }

    /** Recheck the advertised identity in the purchase transaction; legacy clients may submit only a mapped local ID. */
    public static function resolve(int $site,string $key,array $input): array
    {
        $ref=$input['reference']??[]; $local=(int)($input['site_product_id']??0);
        if (!$ref && $local>0) {
            $old=Db::name('erp_site_catalog_product')->alias('sp')->join('erp_catalog_product_master mp','mp.master_product_id=sp.master_product_id')
                ->where(['sp.site_id'=>$site,'sp.site_product_id'=>$local,'sp.is_enabled'=>1,'mp.created_site_id'=>$site,'mp.source_key'=>OpeningMasterService::productSourceKey($site,$key)])
                ->field('mp.category_source_id,mp.source_product_id')->find();
            if (!$old) throw new AdminException('本站采购使用博远商品目录，请重新选择博远型号');
            $id=(string)$old['category_source_id']; $c=self::categoryById($site,$key,$id);
            return self::pick($site,$key,self::products($site,$key),$c?[$id=>$c]:[],$id,(string)$old['source_product_id'])['resolution'];
        }
        if ($ref) {
            self::scoped((string)($ref['scope']??''),$site,$key);
            $c=self::categoryById($site,$key,(string)($ref['category_id']??''));
        } else {
            $cats=self::categories($site,$key);
            $matches=array_values(array_filter($cats,static fn($r)=>$r['category_path']===($input['path']??'')));
            $c=count($matches)===1?$matches[0]:null;
        }
        if (!$c) throw new AdminException('所选分类不在当前博远目录，请重新选择');
        if (($ref['product_id']??'')==='') {
            if ($local>0) throw new AdminException('分类与所选型号不一致，请重新选择');
            return ['category_name'=>$c['label'],'category_path'=>$c['category_path'],'site_product_id'=>0];
        }
        $picked=self::pick($site,$key,self::products($site,$key),[$c['id']=>$c],$c['id'],(string)$ref['product_id']);
        if (!hash_equals($picked['choice']['catalog_reference']['revision'],(string)($ref['revision']??'')) ||
            ($local>0 && $local!==$picked['resolution']['site_product_id'])) throw new AdminException('所选商品资料已变化，请重新选择后入库');
        return $picked['resolution'];
    }
}
