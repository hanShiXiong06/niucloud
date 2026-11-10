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
        $path=is_array($config)&&is_string($config['image']??null)?$config['image']:'';
        if ($path==='') return '';
        $attachment=$this->attachment($path);
        return $attachment?self::displayReference($attachment):'';
    }

    /** Keep the framework attachment's cloud URL separate from its object key.
     * No URL is constructed from client input or fetched from a remote host. */
    public static function displayReference(array $attachment): string
    {
        $path=$attachment['path']??'';
        $url=$attachment['url']??'';
        $isLocal=($attachment['storage_type']??'local')==='local';
        if (!is_string($path)||$path===''||!is_string($url)) return '';
        if ($url==='') {
            if (!$isLocal) return '';
            $url=$path;
        }
        if (strlen($url)>1000||preg_match('/[\x00-\x20\x7f]/',$url)||str_contains($url,'\\')) return '';
        $parts=parse_url($url);
        if ($parts===false||isset($parts['user'])||isset($parts['pass'])) return '';
        if (isset($parts['scheme'])) {
            return in_array(strtolower($parts['scheme']),['http','https'],true)&&!empty($parts['host'])?$url:'';
        }
        // Local uploads remain relative references for the framework img().
        if (!$isLocal||$url!==$path||str_starts_with($url,'/')||str_contains($url,'..')
            ||isset($parts['host'])||isset($parts['query'])||isset($parts['fragment'])) return '';
        return $url;
    }

    private function attachment(string $reference): ?array
    {
        $rows=Db::name('sys_attachment')->where('site_id',$this->context->site)->where('att_type','image')
            ->where(function($query)use($reference):void{$query->where('path',$reference)->whereOr('url',$reference);})->limit(2)->select()->toArray();
        return count($rows)===1?$rows[0]:null;
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
            $attachment=$this->attachment($path);
            if (!$attachment) throw new CommonException('门头图片不存在或不属于本站，请重新上传');
            $owned=Data::query('audit',$this->context->site)->where('entity_type',self::UPLOAD_ENTITY)
                ->where('entity_id',$attachment['att_id'])->where('action','uploaded')->where('operator_member_id',$this->context->memberId)->count();
            if (!$owned) throw new CommonException('请重新上传本人门头，不能使用其他账号或聊天附件');
            if (self::displayReference($attachment)==='') throw new CommonException('门头图片访问地址无效，请联系管理员核对存储域名');
            // Presentation URLs may change with the storage/CDN configuration;
            // persist only the exact registered storage reference, never a URL
            // supplied by the client or a URL with extra query parameters.
            $path=$attachment['path'];
        }
        (new CoreConfigService())->setConfig($this->context->site,self::PREFIX.$merchantId,['image'=>$path]);
    }
}
