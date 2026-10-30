<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

final class ProviderRegistry
{
    public function __construct(private readonly ?TransportContract $transport = null) {}

    public function get(string $key): ProviderContract
    {
        return $this->definition($key)->adapter($this->transport);
    }

    public function definition(string $key): ProviderDefinition
    {
        foreach(self::definitions() as $definition) if ($definition->matches($key)) return $definition;
        throw new ProviderException('unsupported_provider','暂不支持该 ERP 库存来源');
    }

    public static function supported(): array
    {
        return array_map(static fn(ProviderDefinition $definition):array=>$definition->metadata(),self::definitions());
    }

    private static function definitions(): array
    {
        $field=static fn(string $key,string $label,string $type,bool $required,bool $secret,int $length,bool $identity=false):array=>[
            'key'=>$key,'label'=>$label,'type'=>$type,'required'=>$required,'secret'=>$secret,'max_length'=>$length,'identity'=>$identity];
        return [new ProviderDefinition([
            'key'=>'wanji','name'=>'万机管家','aliases'=>['wanjiguanjia'],'adapter'=>WanjiProvider::class,'grade_field'=>'fineness','store_name_field'=>'shopName',
            'auth_mode'=>'password','auth_type'=>'password','renderer'=>'fields','read_only'=>true,
            'fields'=>[$field('username','登录账号','text',true,false,512,true),$field('password','登录密码','password',true,true,4096)],
            'scope_fields'=>[$field('tenant_id','来源租户ID（可选）','text',false,false,100),$field('store_id','来源门店ID（可选，单店自动识别）','text',false,false,100)],
            'renewal'=>['automatic'=>true,'mode'=>'password_on_expiry','max_attempts'=>1,'description'=>'通过已验证账号续期；401最多重新授权一次，不循环重试'],
            'notes'=>['只读取已核验的库存接口，不回写ERP','只显示首屏观察到的门店；多店需明确选择','来源地区码不等于牛云地区ID，库存存在不代表可售或锁货'],
        ]),new ProviderDefinition([
            'key'=>'soujike','name'=>'搜机客','aliases'=>[],'adapter'=>SoujikeProvider::class,'grade_field'=>'renovateName',
            'auth_mode'=>'token','auth_type'=>'token','renderer'=>'fields','read_only'=>true,
            'fields'=>[$field('access_token','授权Token','password',true,true,8192,true)],
            'scope_fields'=>[$field('store_id','来源店铺ID','text',true,false,100)],
            'renewal'=>['automatic'=>false,'mode'=>'manual_token','max_attempts'=>0,'description'=>'仅支持用户提供的库存只读Token；到期后重新探测授权'],
            'notes'=>['尚未接入账号密码登录，不会模拟或猜测其登录流程','只读取当前Token在指定门店可见的商品列表','不采集订单、客户、成本或内部备注'],
        ])];
    }
}
