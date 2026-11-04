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
        $value=(new CoreConfigService())->getConfigValue($this->context->site,self::PREFIX.$merchantId);
        if (!is_array($value)||!is_array($value['models']??null)) return [];
        return array_values(array_filter($value['models'],static fn($row):bool=>is_array($row)&&is_string($row['id']??null)&&is_string($row['title']??null)&&is_string($row['brand']??null)));
    }

    /** Called under the merchant/member profile transaction; client labels are never trusted. */
    public function save(int $merchantId,array $input): void
    {
        if (!array_key_exists('manual_models',$input)) return;
        $own=$this->context->merchant();
        if ((int)$own['id']!==$merchantId||(int)Store::partner($this->context->site,$merchantId)['blocked_at']>0) throw new CommonException('只能设置当前启用门店的可交付型号');
        $selected=$input['manual_models'];
        if (!is_array($selected)||!\addon\hsx_lead\app\support\PhpRuntime::isList($selected)||count($selected)>5000) throw new CommonException('可交付型号请选择列表中的选项，单次不得超过完整清单范围');
        $catalog=array_column((new CatalogService($this->context))->directory()['items'],null,'id');$models=[];
        foreach($selected as $choice) {
            $id=is_array($choice)?($choice['id']??null):$choice;
            if (!is_string($id)||!isset($catalog[$id])) throw new CommonException('可交付型号已变化，请重新搜索选择');
            $item=$catalog[$id];$models[$id]=['id'=>$id,'title'=>$item['title'],'brand'=>$item['brand'],'category_name'=>(string)($item['category_name']??'')];
        }
        (new CoreConfigService())->setConfig($this->context->site,self::PREFIX.$merchantId,['models'=>array_values($models),'updated_at'=>time()]);
    }
}
