<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\CatalogStore;

use addon\hsx_boyuan\app\support\{Contract,Problem};
use addon\hsx_erp\app\model\ErpParty;
use addon\hsx_erp\app\service\admin\{ErpGoodsCatalogService,ErpLedgerService,ErpWarehouseService};
use think\facade\Db;

/** Builds master records, not opening balances. Calling application owns permission, empty-ledger gate and transaction. */
final class OpeningMasterService
{
    public const RESOURCES=['products','warehouses','parties'];
    public const PAGE_SIZE=50;

    public static function productSourceKey(int $site,string $connection): string
    {
        return 'by_'.substr(hash('sha256',$site.':'.$connection),0,36);
    }

    /** Suggest the exact product selected at purchase entry. Never create a reusable mapping from a name. */
    public static function purchaseProductDefaults(int $site,string $connection,array $lines): array
    {
        $ids=array_values(array_unique(array_filter(array_column($lines,'catalog_product_id'))));
        if (!$ids) return [];
        $run=Db::name('hsx_boyuan_reference_sync')->where(['site_id'=>$site,'connection_key'=>$connection,'resource'=>'products'])
            ->order('id desc')->field('status,run_key')->find();
        if (($run['status']??'')!=='completed' || ($run['run_key']??'')==='') return [];
        $products=Db::name('erp_site_catalog_product')->alias('sp')
            ->join('erp_catalog_product_master mp','mp.master_product_id=sp.master_product_id')
            ->where('sp.site_id',$site)->where('sp.is_enabled',1)->whereIn('sp.site_product_id',$ids)
            ->where('mp.created_site_id',$site)->where('mp.source_key',self::productSourceKey($site,$connection))
            ->field('sp.site_product_id,sp.product_name,mp.source_product_id,mp.product_name AS master_name')->select()->toArray();
        if (!$products) return [];
        $bySiteId=array_column($products,null,'site_product_id');
        $archives=CatalogStore::query('products')->where(['site_id'=>$site,'connection_key'=>$connection,'enabled'=>1,'seen_run'=>$run['run_key']])
            ->whereIn('external_id',array_column($products,'source_product_id'))->field('external_id,name,extra_json')->select()->toArray();
        $byExternalId=array_column($archives,null,'external_id'); $defaults=[];
        $lineIds=[];
        foreach ($lines as $line) {
            if (preg_match('/^purchase_line:([1-9][0-9]*)$/D',(string)($line['source_line_id']??''),$m)) $lineIds[]=(int)$m[1];
        }
        $originals=$lineIds?array_column(Db::name('erp_purchase_item')->where('site_id',$site)->whereIn('id',$lineIds)
            ->field('id,asset_id,imei,catalog_product_id,model,spec,color,spec_json')->select()->toArray(),null,'id'):[];
        foreach ($lines as $line) {
            $product=$bySiteId[$line['catalog_product_id']??0]??null;
            $archive=$product?($byExternalId[$product['source_product_id']]??null):null;
            if (!$archive || $product['product_name']!==$product['master_name'] || $product['product_name']!==$archive['name']) continue;
            $attrs=array_column($line['attributes']??[],'value_text','key');
            $variant=['model'=>$product['product_name'],'spec'=>(string)($attrs['spec']??''),'color'=>(string)($attrs['color']??'')];
            $extra=Contract::decode($archive['extra_json']);
            $color=self::skuColor($variant['color']); $remoteColor=self::skuColor((string)($extra['color']??''));
            if ($color!=='' && $remoteColor!=='' && $color!==$remoteColor) continue;
            $capacity=self::capacity($variant['spec']); $remoteCapacity=self::capacity((string)($extra['memory']??''));
            $plain=self::variantRef($variant)===$line['product_ref'] && ($variant['spec']==='' || ($remoteCapacity!=='' && $capacity===$remoteCapacity));
            $original=$originals[(int)substr((string)$line['source_line_id'],14)]??null;
            if (!$plain && !self::matchesStructuredVariant($product,$line,$variant,$original,$remoteCapacity)) continue;
            $defaults[$line['source_line_id']]=$archive['external_id'];
        }
        return $defaults;
    }

    private static function variantRef(array $variant): string
    {
        return 'model:'.hash('sha256',json_encode($variant,JSON_UNESCAPED_UNICODE));
    }

    private static function capacity(string $value): string
    {
        $value=trim($value);
        return preg_match('/^([1-9][0-9]*)\s*(G|T)(?:B)?$/iD',$value,$m)?$m[1].strtoupper($m[2]):$value;
    }

    private static function skuColor(string $value): string
    {
        $value=trim($value);
        return preg_match('/^([黑白红蓝绿紫金银灰粉黄橙])色$/uD',$value,$m)?$m[1]:$value;
    }

    /** Only discard known ERP display fields, reconstructed from the original line (never split a model name). */
    private static function matchesStructuredVariant(array $product,array $line,array $variant,?array $original,string $remoteCapacity): bool
    {
        if (!$original || (int)$original['catalog_product_id']!==(int)$product['site_product_id']
            || $line['source_line_id']!=='purchase_line:'.$original['id'] || ($line['device_ref']??'')!=='asset:'.$original['asset_id']
            || (int)$original['asset_id']<=0 || ($line['imei']??'')==='' || $line['imei']!==$original['imei']
            || $original['spec']!==$variant['spec'] || $original['color']!==$variant['color']) return false;
        $variant['model']=(string)$original['model'];
        if (self::variantRef($variant)!==$line['product_ref']) return false;
        $detail=json_decode((string)$original['spec_json'],true);
        if (!is_array($detail) || !is_array($detail['specs']??null) || !is_array($detail['title_rule']??null)
            || ($detail['color']??null)!==$variant['color']) return false;
        $rules=$detail['title_rule']; $specs=[]; $title=[$product['product_name']];
        foreach ($detail['specs'] as $picked) {
            if (!is_array($picked) || !is_string($picked['value']??null) || trim($picked['value'])==='') return false;
            $value=trim($picked['value']);
            // Other SKU dimensions are not proven by matching a capacity or the base catalog ID.
            if ($remoteCapacity==='' || self::capacity($value)!==$remoteCapacity) return false;
            $specs[]=$value;
            if (!empty($rules['spec_in_title']) && !empty($picked['title_part'])) $title[]=$value;
        }
        $grade=$detail['grade']['value']??'';
        if (!is_string($grade)) return false;
        $grade=trim($grade);
        if ($grade!=='') { $specs[]=$grade; if (!empty($rules['grade_in_title'])) $title[]=$grade; }
        $render=static fn(array $parts,string $separator):string=>implode($separator,array_unique($parts));
        if ($variant['model']!==$product['product_name'] && $variant['model']!==$render($title,(string)(($rules['separator']??'')?:' '))) return false;
        if ($variant['spec']===$render($specs,' ')) return true; // mobile purchase form
        if ($variant['color']!=='') $specs[]=$variant['color'];
        if (($detail['battery']??'')!=='' && $detail['battery']!==null) {
            if (!preg_match('/^(?:100|[1-9]?[0-9])$/D',(string)$detail['battery'])) return false;
            $specs[]='电池'.$detail['battery'].'%';
        }
        if ((int)($detail['warranty']??0)>0) $specs[]='保修至'.date('Y-m-d',(int)$detail['warranty']);
        return $variant['spec']===$render($specs,' '); // PC purchase form
    }

    public function page(int $site,int $uid,string $connection,string $resource,string $runKey,int $cursor,bool $write): array
    {
        if($site<=0 || $uid<=0 || !in_array($resource,self::RESOURCES,true) || $cursor<0 || $connection==='' || $runKey==='') throw new Problem('OPENING_SCOPE','建档参数不完整，请重新预览');
        if($write && !(Db::connect()->getPdo() && Db::connect()->getPdo()->inTransaction())) throw new Problem('OPENING_TRANSACTION','期初建档须从预览确认入口提交');
        $base=CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$connection)->where('seen_run',$runKey);
        $items=(clone $base)->where('id','>',$cursor)->order('id')->limit(self::PAGE_SIZE+1)->select()->toArray();
        $more=count($items)>self::PAGE_SIZE; if($more) array_pop($items);
        $rows=[]; $sourceKey=self::productSourceKey($site,$connection);
        $siteManager=$resource!=='warehouses' || (
            Db::name('sys_user_role')->where(['site_id'=>$site,'uid'=>$uid,'status'=>1,'delete_time'=>0])->count()
            && Db::name('sys_user')->where(['uid'=>$uid,'status'=>1,'delete_time'=>0])->count()
        );
        foreach($items as $item) {
            $extra=Contract::decode($item['extra_json']);
            $decision=$this->plan($site,$connection,$resource,$item,$extra,$sourceKey,$base);
            if(!$siteManager && $decision['action']==='create') $decision=$this->decision('conflict','须由当前站点的在职员工确认仓库负责人，不能以跨站管理员代领');
            $row=['id'=>(int)$item['id'],'name'=>(string)$item['name'],'external_id'=>(string)$item['external_id'],'action'=>$decision['action'],'reason'=>$decision['reason'],
                // Includes proposed category/roles/policies. A refreshed field may not change the displayed name.
                'basis'=>hash('sha256',Contract::json([$item['external_id'],$item['code'],$decision]))];
            if($decision['action']==='create' && $write) $this->create($site,$uid,$connection,$resource,$item,$sourceKey,$decision);
            $rows[]=$row;
        }
        return ['rows'=>$rows,'next_cursor'=>$more?(int)end($items)['id']:0];
    }

    private function plan(int $site,string $connection,string $resource,array $item,array $extra,string $sourceKey,$scope,?string $verifiedPath=null): array
    {
        $name=trim((string)$item['name']); $external=(string)$item['external_id'];
        if(!(int)$item['enabled'] || !empty($extra['source_name_missing'])) return $this->decision('skip','停用、状态未提供或缺少名称，不建档');
        if($name==='' || $external==='' || preg_match('/[\x00-\x1F\x7F]/',$name) || mb_strlen($name)>($resource==='products'?150:100)) return $this->decision('conflict','名称为空、含控制字符或超过 ERP 长度，请核对博远原档');
        if($resource==='products') return $this->productPlan($site,$connection,$sourceKey,$item,$extra,$scope,$verifiedPath);
        $kinds=['warehouse']; $roles=[];
        if($resource==='parties') {
            $kinds=[];
            if(in_array($extra['is_supplier']??null,[true,1,'1'],true)) { $kinds[]='supplier'; $roles[]='purchase_supplier'; }
            if(in_array($extra['is_cust']??null,[true,1,'1'],true)) { $kinds[]='customer'; $roles[]='sale_customer'; }
            if(!$kinds) return $this->decision('skip','不是明确的客户或供应商，不建往来单位');
        }
        $warehouse=$resource==='warehouses'; $table=$warehouse?'erp_warehouse':'erp_party'; $prefix=$warehouse?'warehouse:':'party:';
        $mapping=Db::name('hsx_boyuan_mapping')->where('site_id',$site)->where('connection_key',$connection)
            ->whereIn('kind',$warehouse?['warehouse']:['customer','supplier'])->where('external_id',$external)->select()->toArray();
        if($mapping) {
            $refs=array_unique(array_column($mapping,'local_ref'));
            if(count($refs)!==1 || !preg_match('/^'.$prefix.'([1-9][0-9]*)$/D',(string)reset($refs),$m)) return $this->decision('conflict','同一博远档案已有多条或异常的本地对应，请人工核对');
            $local=Db::name($table)->where('site_id',$site)->where('id',(int)$m[1])->find();
            if(!$local || (int)$local['status']!==1) return $this->decision('conflict','已关联的本站资料不存在或已停用；不会另建或重新启用');
            if(trim((string)$local[$warehouse?'warehouse_name':'party_name'])!==$name) return $this->decision('conflict','本站名称与博远已不同；原档和对应关系保留，请人工核对');
            foreach($roles as $i=>$role) if(!in_array($role,explode(',',(string)$local['role_flags']),true) && !in_array($local['party_type'],[$kinds[$i],'both'],true)) return $this->decision('conflict','本站与博远的往来角色不同，请人工核对');
            if(array_diff($kinds,array_column($mapping,'kind'))) return $this->decision('conflict','双重身份的对应尚不齐全，请核对已有关系');
            return $this->decision('existing','已关联本站启用资料；保留本站设置',['ref'=>(string)reset($refs)]);
        }
        if((clone $scope)->where('enabled',1)->where('name',$name)->where('id','<>',(int)$item['id'])->count()) return $this->decision('conflict','本轮有同名的不同博远档案，请先区分');
        $local=Db::name($table)->where('site_id',$site);
        if((clone $local)->where($warehouse?'warehouse_name':'party_name',$name)->count()) return $this->decision('conflict','本站已有同名档案但未建立对应；不会按名称合并');
        if($warehouse && (mb_strlen((string)$item['code'])>60 || (clone $local)->where('warehouse_code',$item['code'])->count() ||
            (clone $scope)->where('enabled',1)->where('code',$item['code'])->where('id','<>',(int)$item['id'])->count()))
            return $this->decision('conflict','仓库编码过长、在博远重复或已被本站其他仓库占用');
        return $this->decision('create',$warehouse?'新建仓库和一个本站默认库位；仓库待核实，禁止直售和调拨':'建立'.implode('、',array_map(static fn($v)=>$v==='customer'?'客户':'供应商',$kinds)).'资料',
            ['name'=>$name,'kinds'=>$kinds,'roles'=>$roles]);
    }

    /** The purchase picker and opening import must use the same identity and conflict rules. */
    public function purchaseProductPlan(int $site,string $connection,array $item,$scope,string $currentPath): array
    {
        return $this->plan($site,$connection,'products',$item,Contract::decode($item['extra_json']),self::productSourceKey($site,$connection),$scope,$currentPath);
    }

    private function productPlan(int $site,string $connection,string $sourceKey,array $item,array $extra,$scope,?string $verifiedPath=null): array
    {
        $name=trim((string)$item['name']); $external=(string)$item['external_id'];
        $categoryId=trim((string)($extra['category_id']??'')); $brandId=trim((string)($extra['brand_id']??''));
        $category=trim((string)($extra['category_name']??'')); $brand=trim((string)($extra['brand_name']??''));
        // Resolve only exact ids in complete source catalogues; never guess a brand/category from its label.
        if($categoryId!=='') {
            $category=$verifiedPath??CategoryCatalog::path($site,$connection,$categoryId);
            if($category==='') return $this->decision('conflict','此商品的博远分类或上级关系未核实，请在博远核对后回到商品目录点击「同步最新分类」');
        }
        if($brand==='' && $brandId!=='') $brand=$this->sourceLabel($site,$connection,'brands',$brandId);
        if($category==='' || ($brandId!=='' && $brand==='')) return $this->decision('conflict','缺少可确认的商品品类或品牌，请补齐博远档案后再同步');
        $segments=array_map(static fn($v)=>trim((string)preg_replace('/\s+/u',' ',$v)),preg_split('~[/\\\\／＞>,，|｜]+~u',$category));
        if(in_array('',array_map('trim',$segments),true)) return $this->decision('conflict','博远商品的分类路径不完整，请核对后同步');
        $category=implode('/',$segments);
        if(strlen($external)>80 || strlen($categoryId)>80 || strlen($brandId)>80 || mb_strlen($category)>255 || mb_strlen($brand)>100) return $this->decision('conflict','商品名称、分类或来源编号超过 ERP 长度，不截断建档');
        $master=Db::name('erp_catalog_product_master')->where('source_key',$sourceKey)->where('source_product_id',$external)->find();
        if($master) {
            if((int)$master['created_site_id']!==$site || $master['source_product_id']!==$external) return $this->decision('conflict','商品来源身份冲突，不合并');
            $local=Db::name('erp_site_catalog_product')->where('site_id',$site)->where('master_product_id',$master['master_product_id'])->find();
            if(!$local || (int)$local['is_enabled']!==1) return $this->decision('conflict','来源商品已有历史关联，但本站商品被移除或停用');
            // Renaming/moving a bound category is not a different product. New operations resolve the current path by ID.
            if(trim((string)$local['product_name'])!==$name || ($categoryId==='' && $local['category_path']!==$category) || ($brand!=='' && $local['brand_name']!==$brand)
                || ($categoryId!=='' && $master['category_source_id']!==$categoryId) || ($brandId!=='' && $master['brand_source_id']!==$brandId)) {
                return $this->decision('conflict','已建档商品的型号、分类或品牌与博远不同；保留本站数据，请核对');
            }
            return $this->decision('existing','本站商品已建档，来源和型号已核对',['ref'=>'catalog:'.$local['site_product_id']]);
        }
        if(Db::name('erp_site_catalog_product')->where('site_id',$site)->where('product_name',$name)->count() ||
            (clone $scope)->where('enabled',1)->where('name',$name)->where('id','<>',(int)$item['id'])->count()) return $this->decision('conflict','存在同名但不同来源的商品，不覆盖或合并');
        return $this->decision('create','建立本站商品目录，不增加实物库存',['payload'=>['category_path'=>$category,'category_source_id'=>$categoryId,'brand_name'=>$brand,'brand_source_id'=>$brandId,'product_name'=>$name,'source_product_id'=>$external]]);
    }
    private function sourceLabel(int $site,string $connection,string $resource,string $id): string
    {
        $job=Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('connection_key',$connection)->where('resource',$resource)
            ->order('id desc')->field('status,run_key')->find();
        if(($job['status']??'')!=='completed' || ($job['run_key']??'')==='') return '';
        return trim((string)CatalogStore::query($resource)->where('site_id',$site)->where('connection_key',$connection)
            ->where('external_id',$id)->where('enabled',1)->where('seen_run',$job['run_key'])->value('name'));
    }
    private function decision(string $action,string $reason,array $details=[]): array {return ['action'=>$action,'reason'=>$reason]+$details;}

    private function create(int $site,int $uid,string $connection,string $resource,array $item,string $sourceKey,array $decision): void
    {
        if($resource==='products') {
            $stats=(new ErpGoodsCatalogService())->importManagedRows([$decision['payload']],$sourceKey,$site,'hsx_boyuan',$connection);
            if($stats['site_created']!==1 || $stats['master_created']!==1) throw new Problem('OPENING_CHANGED','商品目录刚被其他人修改，请重新预览');
            return;
        }
        if($resource==='warehouses') {
            $id=ErpWarehouseService::forSite($site,$uid)->createSyncedWarehouseWithDefaultLocation('hsx_boyuan',$connection,(string)$item['external_id'],['warehouse_name'=>$decision['name'],'warehouse_code'=>$item['code'],
                'manager_uid'=>$uid,'warehouse_type'=>'exception','allow_direct_sale'=>0,'allow_transfer'=>0,'is_default'=>0]);
            $local='warehouse:'.$id;
        } else {
            // The generic ERP party resolver merges by name/phone. Opening must never merge a distinct external id.
            $now=time();
            $row=ErpParty::create(['site_id'=>$site,'party_no'=>ErpLedgerService::makeNo('PT'),'party_name'=>$decision['name'],
                'party_type'=>$decision['kinds'][0],'role_flags'=>implode(',',$decision['roles']),'status'=>1,'create_at'=>$now,'update_at'=>$now]);
            $local='party:'.$row->id;
        }
        foreach($decision['kinds'] as $kind) Db::name('hsx_boyuan_mapping')->insert(['site_id'=>$site,'connection_key'=>$connection,
            'kind'=>$kind,'local_ref'=>$local,'local_name'=>$decision['name'],'external_id'=>$item['external_id'],'external_code'=>$item['code'],
            'external_name'=>$item['name'],'extra_json'=>'{}','revision'=>1,'operator_uid'=>$uid,'update_at'=>time()]);
    }
}
