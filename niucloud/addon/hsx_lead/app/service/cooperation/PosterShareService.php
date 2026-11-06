<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use app\service\core\sys\CoreSysConfigService;
use core\exception\CommonException;
use think\facade\{Cache,Db,Log};

/** Plugin-owned identity/routing; framework-owned templates, composition and QR channels. */
final class PosterShareService
{
    public const TYPES=['promotion'=>'hsx_lead_promotion','entity'=>'hsx_lead_entity'];
    public function __construct(private Context $context) {}

    public static function scene(string $value): string
    {
        if (!isset(self::TYPES[$value])) throw new CommonException('请选择推广邀请或门店成员邀请');
        return $value;
    }

    public static function fromRequest(int $site): self
    {
        if ($site<=0 || (int)request()->siteId()!==$site || request()->appType()!=='api' || (int)request()->memberId()<=0) {
            throw new CommonException('请先登录当前站点，再生成本人的分享海报');
        }
        return new self(new Context($site,0,(int)request()->memberId()));
    }

    /** The real template editor may preview only synthetic, non-binding H5 data. */
    public static function preview(int $site,string $scene,string $channel): array
    {
        self::scene($scene);
        if ((int)request()->siteId()!==$site || !in_array(request()->appType(),['site','admin'],true) || (int)request()->uid()<=0 || $channel!=='h5') {
            throw new CommonException('请在站点海报编辑器中使用网页渠道预览');
        }
        (new Context($site,(int)request()->uid()))->manager();
        return ['hsx_lead_title'=>$scene==='promotion'?'邀请你加入商户协作':'一起加入我的门店',
            'hsx_lead_subtitle'=>'连接本地门店，让每条需求找到交付方','hsx_lead_identity'=>$scene==='promotion'?'示例邀请人':'示例门店',
            'hsx_lead_label'=>$scene==='promotion'?'商户协作邀请':'门店成员邀请','hsx_lead_code'=>'预览样例 · 不是正式邀请',
            'hsx_lead_note'=>'预览二维码不绑定推广关系，也不能申请加入门店',
            'hsx_lead_qrcode'=>['url'=>'https://example.invalid','page'=>'','data'=>[]]];
    }

    /** Client identifiers, URLs, codes, titles and member IDs are deliberately not arguments. */
    public function data(string $scene,string $channel): array
    {
        self::scene($scene);$this->assertRequest();$this->channel($channel);
        $site=$this->context->site;$member=$this->context->memberId;
        if ($scene==='promotion') {
            $role=PromotionService::resolveRole($site,$member);
            if (!PromotionService::configuration($site)['enabled'] || !$role['promotion_allowed']) throw new CommonException('当前账号尚未开放推广邀请，不能生成推广海报');
            $name=Db::name('member')->where('site_id',$site)->where('member_id',$member)->value('nickname');
            return $this->payload('邀请你加入商户协作','连接本地门店，让每条需求找到交付方',
                $this->text((string)$name,18)?:'本站会员','商户协作邀请','扫码查看，登录后确认邀请关系',
                'addon/hsx_lead/pages/profile/index',[
                    ['key'=>'mid','value'=>(string)$member],['key'=>'pi','value'=>'1'],
                ],'');
        }
        $merchant=TeamService::assertOwner($this->context);
        $partner=Store::partner($site,(int)$merchant['id']);
        if ((int)$partner['blocked_at']>0) throw new CommonException('当前门店已暂停合作，不能邀请新成员');
        $team=(new TeamService($this->context))->summary();
        $code=(string)($team['subject_code']??'');
        if (!preg_match('/^[A-HJ-NP-Z2-9]{10}$/D',$code)) throw new CommonException('门店主体码尚未就绪，请先刷新门店与成员');
        return $this->payload('一起加入我的门店','共享门店业务，一起承接好客户需求',
            $this->text((string)$merchant['name'],26),'门店成员邀请','扫码申请加入，由门店负责人确认',
            'addon/hsx_lead/pages/entity/index',[
                // Only this key is needed: the entity page infers join mode. 23-byte scene, not 33.
                ['key'=>'subject_code','value'=>$code],
            ],$code);
    }

    public function generate(string $scene,string $channel): array
    {
        $scene=self::scene($scene);
        // Recheck authorization even when CorePosterService has an existing image on disk.
        $this->data($scene,$channel);
        // The core renderer silently replaces a missing background with bgColor,
        // which would make this default template's white header unreadable.
        if (!is_file(public_path('addon/hsx_lead/poster/share-background.png'))) {
            throw new CommonException('海报素材尚未同步，请管理员执行插件更新命令以同步资源后重试');
        }
        $key='hsx_lead.poster.'.$this->context->site.'.'.$this->context->memberId.'.'.intdiv(time(),60);
        if (!Cache::has($key)) Cache::set($key,0,70);
        if ((int)Cache::inc($key)>10) throw new CommonException('海报生成较频繁，请稍后再试');
        if (!extension_loaded('imagick')) throw new CommonException('服务器尚未启用海报图像扩展，请联系管理员');
        try {
            // Empty param prevents the global system listener from interpreting a foreign member_id.
            $url=(string)poster($this->context->site,0,self::TYPES[$scene],[],$channel,true);
            if ($url==='') throw new \RuntimeException('Empty poster result');
            return ['url'=>$url,'scene'=>$scene,'type'=>self::TYPES[$scene]];
        } catch (\Throwable $error) {
            // CorePosterService appends paths/line numbers to its exception. Do not expose those to customers.
            $code=preg_match('/errcode:([0-9-]+)/',$error->getMessage(),$match)?$match[1]:'';
            Log::warning('商户协作海报生成失败：scene='.$scene.'，site_id='.$this->context->site.'，channel='.$channel.'，error='.get_class($error).'，provider_code='.($code?:'none'));
            if ($code!=='') throw new CommonException('小程序码生成失败，请核对本站小程序配置（错误码'.$code.'）');
            throw new CommonException('海报生成失败，请稍后重试；仍失败请联系管理员检查小程序配置及图片处理服务');
        }
    }

    private function assertRequest(): void
    {
        if (request()->appType()!=='api' || (int)request()->siteId()!==$this->context->site || (int)request()->memberId()!==$this->context->memberId || $this->context->memberId<=0) {
            throw new CommonException('分享身份已变化，请重新登录后再生成海报');
        }
    }
    private function channel(string $channel): void
    {
        if (!in_array($channel,['weapp','h5','wechat','app'],true) || request()->getChannel()!==$channel) throw new CommonException('当前分享渠道无效，请从小程序或手机页面重新进入');
    }
    private function text(string $value,int $limit): string
    {
        $value=trim(preg_replace('/[\x00-\x1f\x7f]/u',' ',strip_tags($value))??'');
        return mb_strlen($value,'UTF-8')>$limit?mb_substr($value,0,$limit-1,'UTF-8').'…':$value;
    }
    private function payload(string $title,string $subtitle,string $identity,string $label,string $note,string $page,array $data,string $code): array
    {
        $base=rtrim((string)((new CoreSysConfigService())->getSceneDomain($this->context->site)['wap_url']??''),'/');
        $parts=parse_url($base);
        if (!$parts || !in_array($parts['scheme']??'',['http','https'],true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) throw new CommonException('本站手机端访问地址未配置，请联系管理员');
        $scene=implode('&',array_map(static fn(array $item):string=>$item['key'].'-'.$item['value'],$data));
        if (strlen($scene)>32) throw new CommonException('扫码参数超出小程序长度限制，请联系管理员');
        return ['hsx_lead_title'=>$title,'hsx_lead_subtitle'=>$subtitle,'hsx_lead_identity'=>$identity,
            'hsx_lead_label'=>$label,'hsx_lead_note'=>$note,'hsx_lead_code'=>$code===''?'线索协作 · 连接同城商户':'门店码  '.$code,
            'hsx_lead_qrcode'=>['url'=>$base,'page'=>$page,'data'=>$data]];
    }
}
