<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\Data;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Db;

/** Public storefront media, separate from private lead evidence and certification. */
final class StorefrontService
{
    private const PREFIX='HSX_LEAD_STOREFRONT_';
    public const UPLOAD_ENTITY='cooperation_storefront';

    public function __construct(private Context $context) {}

    /** A new, valid member can upload before creating their first merchant profile. */
    public function assertUploadAllowed(): void
    {
        if ($this->context->memberId<=0) throw new CommonException('请先使用本站会员身份登录');
        $member=Db::name('member')->where('site_id',$this->context->site)->where('member_id',$this->context->memberId)
            ->where('status',1)->where('is_del',0)->where('delete_time',0)->find();
        if (!$member) throw new CommonException('会员已停用或失效，无法上传门头');
        $merchant=Data::query('merchant',$this->context->site)->where('member_id',$this->context->memberId)->find();
        if (!$merchant) return;
        $partner=Store::query('partner',$this->context->site)->where('merchant_id',$merchant['id'])->find();
        if ((int)$merchant['status']!==1||(int)($partner['blocked_at']??0)>0) throw new CommonException('当前商户已停止合作，不能修改门头');
    }

    public function image(int $merchantId): string
    {
        if ($merchantId<=0) return '';
        $config=(new CoreConfigService())->getConfigValue($this->context->site,self::PREFIX.$merchantId);
        return is_array($config)&&is_string($config['image']??null)?$config['image']:'';
    }

    /** Called inside the profile transaction, after the merchant/member locks. */
    public function save(int $merchantId,array $input): void
    {
        if (!array_key_exists('storefront_image',$input)) return;
        $merchant=Data::row('merchant',$this->context->site,$merchantId);
        if ($this->context->memberId<=0||(int)$merchant['member_id']!==$this->context->memberId) throw new CommonException('只能修改当前会员绑定门店的门头');
        $this->assertUploadAllowed();
        $path=$input['storefront_image'];
        if (!is_string($path)||strlen($path)>1000||str_contains($path,'..')) throw new CommonException('请使用本人在本站上传的门头图片');
        if ($path!=='') {
            $attachment=Db::name('sys_attachment')->where('site_id',$this->context->site)->where('att_type','image')->where('path',$path)->find();
            if (!$attachment) throw new CommonException('门头图片不存在或不属于本站，请重新上传');
            $owned=Data::query('audit',$this->context->site)->where('entity_type',self::UPLOAD_ENTITY)
                ->where('entity_id',$attachment['att_id'])->where('action','uploaded')->where('operator_member_id',$this->context->memberId)->count();
            if (!$owned) throw new CommonException('请重新上传本人门头，不能使用其他账号或聊天附件');
        }
        (new CoreConfigService())->setConfig($this->context->site,self::PREFIX.$merchantId,['image'=>$path]);
    }
}
