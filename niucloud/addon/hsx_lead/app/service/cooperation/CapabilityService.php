<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\{Context,CatalogService};
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

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

    public function businesses(int $merchantId): array
    {
        $value=(new CoreConfigService())->getConfigValue($this->context->site,self::PREFIX.$merchantId);
        if (!is_array($value)) return [];
        if (array_key_exists('business_capabilities',$value)) return is_array($value['business_capabilities'])?$value['business_capabilities']:[];
        // Existing declarations are readable as second-hand capability, never mass-migrated.
        $items=array_values(array_filter($value['models']??[],static fn($row):bool=>is_array($row)&&is_string($row['id']??null)&&is_string($row['title']??null)&&is_string($row['brand']??null)));
        return $items?[['business_type'=>'purchase','option_ids'=>array_column($items,'id'),'items'=>$items]]:[];
    }

    /** Called under the merchant/member profile transaction; client labels are never trusted. */
    public function save(int $merchantId,array $input,bool $admin=false): void
    {
        if (!array_key_exists('business_capabilities',$input)) {
            if (array_key_exists('manual_models',$input)) throw new CommonException('请通过业务能力选项设置可承接范围，旧型号字段仅供读取');
            return;
        }
        if ($admin) {$this->context->manager();$merchant=\addon\hsx_lead\app\support\Data::row('merchant',$this->context->site,$merchantId);}
        else {$merchant=$this->context->merchant();if ((int)$merchant['id']!==$merchantId) throw new CommonException('只能设置当前门店的业务能力');}
        if ((int)$merchant['status']!==1||(int)Store::partner($this->context->site,$merchantId)['blocked_at']>0) throw new CommonException('已停止合作的门店不能设置业务能力');
        $selected=$input['business_capabilities'];
        if (!is_array($selected)||!\addon\hsx_lead\app\support\PhpRuntime::isList($selected)||count($selected)>30) throw new CommonException('业务能力格式错误，最多30项业务');
        $service=new BusinessService($this->context);$capabilities=[];$seen=[];$prior=array_column($this->businesses($merchantId),null,'business_type');
        foreach($selected as $entry) {
            if (!is_array($entry)) throw new CommonException('业务能力格式错误');
            $key=BusinessService::key($entry['business_type']??null,'业务标识');if (isset($seen[$key])) throw new CommonException('同一业务不能重复登记');$seen[$key]=true;
            $business=$service->definition($key,false);$ids=$entry['option_ids']??null;
            if (!$business['enabled']) {
                if (!is_array($ids)||!\addon\hsx_lead\app\support\PhpRuntime::isList($ids)||count($ids)>5000) throw new CommonException('业务能力选项格式错误');
                foreach($ids as $optionId) if (!is_string($optionId)||strlen($optionId)>100) throw new CommonException('业务能力选项ID格式错误');
                $oldIds=$prior[$key]['option_ids']??[];$nextIds=$ids;sort($oldIds);sort($nextIds);
                if ($ids&&$nextIds!==$oldIds) throw new CommonException('已关闭业务不能新增或修改能力选项');
                $items=$ids?($prior[$key]['items']??[]):[];
            } else $items=$service->resolve($business,$ids,true);
            if ($items) $capabilities[]=['business_type'=>$key,'option_ids'=>array_column($items,'id'),'items'=>$items];
        }
        $config=new CoreConfigService();$old=$config->getConfigValue($this->context->site,self::PREFIX.$merchantId);
        $config->setConfig($this->context->site,self::PREFIX.$merchantId,array_replace(is_array($old)?$old:[],['business_capabilities'=>$capabilities,'updated_at'=>time()]));
    }
}
