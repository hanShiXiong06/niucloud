<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

final class ProviderRegistry
{
    public function __construct(private ?TransportContract $transport = null) {}

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
            'verified_identity'=>true,'identity_tenant_optional'=>true,
            'auth_mode'=>'password','auth_type'=>'password','renderer'=>'fields','read_only'=>true,
            'fields'=>[$field('username','登录手机号','text',true,false,512,true),$field('password','登录密码','password',true,true,4096)],
            'scope_fields'=>[$field('store_id','来源店铺ID（可选，登录后自动核对）','text',false,false,100)],
            'renewal'=>['automatic'=>true,'mode'=>'password_on_expiry','max_attempts'=>1,'description'=>'账号密码保存在本站密文中；每轮核对登录门店，失效后最多重登一次'],
            'auth_modes'=>[
                ['key'=>'password','label'=>'账号密码登录',
                    'fields'=>[$field('username','登录手机号','text',true,false,512,true),$field('password','登录密码','password',true,true,4096)],
                    'scope_fields'=>[$field('store_id','来源店铺ID（可选，登录后自动核对）','text',false,false,100)],
                    'renewal'=>['automatic'=>true,'mode'=>'password_on_expiry','max_attempts'=>1,'description'=>'登录时自动识别门店；只读库存，不操作会员卡、客户或交易']],
                ['key'=>'token','label'=>'已有Token（临时测试）',
                    'fields'=>[$field('access_token','授权Token','password',true,true,8192,true)],
                    'scope_fields'=>[$field('store_id','来源店铺ID','text',true,false,100)],
                    'renewal'=>['automatic'=>false,'mode'=>'manual_token','max_attempts'=>0,'description'=>'仅用于已有授权测试，到期后需要重新提供，不自动尝试密码登录']],
            ],
            'notes'=>['默认使用账号密码登录并自动核对来源门店，Token仅保留为临时测试入口','登录协议参考会员卡既有接口，但不依赖会员卡插件，不读取客户、会员卡、成本或内部备注','库存预览后仍需明确启用，再完成完整分页同步'],
        ]),new ProviderDefinition([
            'key'=>'jixiaoer','name'=>'机小二','aliases'=>['guanql'],'adapter'=>JixiaoerProvider::class,'grade_field'=>'fineness',
            'verified_identity'=>true,'auth_mode'=>'password','auth_type'=>'password','renderer'=>'fields','read_only'=>true,
            'fields'=>[$field('username','登录手机号','text',true,false,512,true),$field('password','登录密码','password',true,true,4096)],
            'scope_fields'=>[$field('store_id','认证门店ID（可选，自动识别）','text',false,false,100)],
            'renewal'=>['automatic'=>true,'mode'=>'password_on_sync','max_attempts'=>1,'description'=>'每轮先登录并核对门店；会话失效最多重新登录一次，不循环重试'],
            'notes'=>['先核对商品总开关与每台上架状态，再与当前在库串号逐一对应','全部仓库、完整时间范围；在售快照仍不代表锁货，派单前需门店确认','不修改源库存，不保存成本、供货商和内部备注'],
        ]),new ProviderDefinition([
            'key'=>'aiguanji','name'=>'爱管机','aliases'=>[],'adapter'=>AiguanjiProvider::class,'grade_field'=>'purity_name',
            'verified_identity'=>true,'verify_before_sync'=>true,'auth_mode'=>'interactive','auth_type'=>'password_captcha','renderer'=>'fields','read_only'=>true,
            'fields'=>[
                $field('username','授权账号手机号','text',true,false,512,true),
                $field('access_token','已登录会话的授权Token','password',true,true,16384),
            ],
            'scope_fields'=>[$field('store_id','来源公司ID（可选，自动核验）','text',false,false,100)],
            'interactive_auth'=>['type'=>'image_captcha','automatic_recognition'=>true,'credential_fields'=>['username'],
                'fields'=>[$field('password','登录密码','password',true,true,4096)],'code_length'=>4,'default'=>true],
            'renewal'=>['automatic'=>false,'mode'=>'interactive_login','max_attempts'=>0,'description'=>'正常手机号、密码和图片验证码登录；到期后重新登录，不自动绕过验证码'],
            'notes'=>['通过本站向导正常登录并核对当前公司，不绑定测试账号或某个浏览器','全部可见仓库；未上架资料可以解析核验，但不计入可派单库存','登录密码和验证码仅本次授权使用；会话失效时保留旧完整快照并提示重新登录'],
        ])];
    }
}
