<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\{Context,CatalogConfigStorage};
use core\exception\CommonException;
use think\facade\Db;

/** Declared delivery skills are not inventory. Uses the framework configuration store. */
final class CapabilityService
{
    private const PREFIX='HSX_LEAD_CAPABILITY_';
    public function __construct(private Context $context) {}

    public function models(int $merchantId): array
    {
        foreach($this->businesses($merchantId) as $entry) if ($entry['business_type']==='purchase') return $entry['items'];
        return [];
    }

    /** Live presentation/matching must not reinterpret a saved brand as a model/operator. */
    public function businesses(int $merchantId,?array $config=null): array
    {
        $definitions=array_column(($config??(new ConfigService($this->context))->get())['businesses'],null,'key');
        $result=[];
        foreach($this->savedBusinesses($merchantId) as $entry) {
            $business=$definitions[$entry['business_type']??'']??null;
            if (!$business) continue;
            $items=self::itemsForDimension($entry,(string)$business['dimension']);
            if ($items) $result[]=array_replace($entry,['items'=>$items,'option_ids'=>array_column($items,'id')]);
        }
        return $result;
    }

    /** A frozen lead adds its own dimension check after the live-business projection. */
    public static function itemsForDimension(array $entry,string $dimension): array
    {
        return array_values(array_filter($entry['items']??[],static fn($item):bool=>is_array($item)&&($item['dimension']??null)===$dimension));
    }

    /** Keep persisted declarations intact for disabled-business retries; never migrate on a read. */
    private function savedBusinesses(int $merchantId): array
    {
        $value=CatalogConfigStorage::read($this->context->site,self::PREFIX.$merchantId);
        return self::savedFromValue($value);
    }

    /** Bounded list summaries retain dimension checks, without copying all item payloads. */
    public function summaries(array $merchantIds,array $config): array
    {
        $keys=[];foreach($merchantIds as $id) $keys[]=self::PREFIX.(int)$id;
        $values=CatalogConfigStorage::readMany($this->context->site,$keys);
        $definitions=array_column($config['businesses'],null,'key');$result=[];
        foreach($merchantIds as $id) {
            $entries=[];$count=0;$models=0;
            foreach(self::savedFromValue($values[self::PREFIX.(int)$id]??[]) as $entry) {
                $business=$definitions[$entry['business_type']??'']??null;
                if(!$business) continue;
                $itemCount=count(self::itemsForDimension($entry,(string)$business['dimension']));
                if(!$itemCount) continue;
                $entries[]=['business_type'=>$business['key'],'business_name'=>$business['name'],
                    'dimension'=>$business['dimension'],'option_count'=>$itemCount];
                $count+=$itemCount;
                if($business['key']==='purchase') $models=$itemCount;
            }
            $result[(int)$id]=['business_capabilities'=>$entries,'business_capability_count'=>$count,'manual_model_count'=>$models];
        }
        return $result;
    }

    private static function savedFromValue(array $value): array
    {
        if (array_key_exists('business_capabilities',$value)) return is_array($value['business_capabilities'])?$value['business_capabilities']:[];
        // Existing declarations are readable as second-hand capability, never mass-migrated.
        $items=array_values(array_filter($value['models']??[],static fn($row):bool=>is_array($row)&&is_string($row['id']??null)&&is_string($row['title']??null)&&is_string($row['brand']??null)));
        $items=array_map(static fn(array $item):array=>array_replace($item,['dimension'=>'model']),$items);
        return $items?[['business_type'=>'purchase','option_ids'=>array_column($items,'id'),'items'=>$items]]:[];
    }

    /** Called under the merchant/member profile transaction; client labels are never trusted. */
    public function save(int $merchantId,array $input,bool $admin=false): void
    {
        if (!array_key_exists('business_capabilities',$input)) {
            if (array_key_exists('manual_models',$input)) throw new CommonException('请通过业务能力选项设置可承接范围，旧型号字段仅供读取');
            return;
        }
        Db::transaction(function()use($merchantId,$input,$admin):void {
            // Also protect direct capability saves, not only profile saves.
            // Keep the same lock order as profile/config/catalog mutations.
            if (!Db::name('site')->where('site_id',$this->context->site)->lock(true)->field('site_id')->find()) throw new CommonException('站点不存在，未保存交付能力');
            $this->saveLocked($merchantId,$input,$admin);
        });
        CatalogConfigStorage::evict($this->context->site,self::PREFIX.$merchantId);
    }

    private function saveLocked(int $merchantId,array $input,bool $admin): void
    {
        if ($admin) {$this->context->manager();$merchant=\addon\hsx_lead\app\support\Data::row('merchant',$this->context->site,$merchantId);}
        else {$merchant=$this->context->merchant();if ((int)$merchant['id']!==$merchantId) throw new CommonException('只能设置当前门店的业务能力');}
        if ((int)$merchant['status']!==1||(int)Store::partner($this->context->site,$merchantId)['blocked_at']>0) throw new CommonException('已停止合作的门店不能设置业务能力');
        $selected=$input['business_capabilities'];
        if (!is_array($selected)||!\addon\hsx_lead\app\support\PhpRuntime::isList($selected)||count($selected)>30) throw new CommonException('业务能力格式错误，最多30项业务');
        $service=new BusinessService($this->context);$config=(new ConfigService($this->context))->get();
        $capabilities=[];$seen=[];$prior=array_column($this->savedBusinesses($merchantId),null,'business_type');
        foreach($selected as $entry) {
            if (!is_array($entry)) throw new CommonException('业务能力格式错误');
            $key=BusinessService::key($entry['business_type']??null,'业务标识');if (isset($seen[$key])) throw new CommonException('同一业务不能重复登记');$seen[$key]=true;
            $business=$service->definition($key,false,$config);$ids=$entry['option_ids']??null;
            if (!$business['enabled']) {
                if (!is_array($ids)||!\addon\hsx_lead\app\support\PhpRuntime::isList($ids)||count($ids)>5000) throw new CommonException('业务能力选项格式错误');
                foreach($ids as $optionId) if (!is_string($optionId)||strlen($optionId)>100) throw new CommonException('业务能力选项ID格式错误');
                $oldIds=$prior[$key]['option_ids']??[];$nextIds=$ids;sort($oldIds);sort($nextIds);
                if ($ids&&$nextIds!==$oldIds) throw new CommonException('已关闭业务不能新增或修改能力选项');
                $items=$ids?($prior[$key]['items']??[]):[];
            } else $items=$service->resolve($business,$ids,true);
            if ($items) $capabilities[]=['business_type'=>$key,'option_ids'=>array_column($items,'id'),'items'=>$items];
        }
        $key=self::PREFIX.$merchantId;
        $old=CatalogConfigStorage::read($this->context->site,$key);
        $stored=array_replace($old,['business_capabilities'=>$capabilities,'updated_at'=>time()]);
        CatalogConfigStorage::write($this->context->site,$key,$stored);
        if ((CatalogConfigStorage::read($this->context->site,$key)['business_capabilities']??null)!==$capabilities) throw new CommonException('交付能力保存回读不完整，当前事务已停止，请重新核对');
    }
}
