<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\support\Data;
use core\exception\CommonException;
use think\facade\Db;

/**
 * The framework member_level table owns definitions; the merchant payment
 * ledger owns actual grants. Reading this catalog never changes member_level
 * on a personal account and never awards framework growth gifts.
 *
 * levels(site) => code ML{level_id}, level_id, name, rank=growth,
 * display_rank, account_limit, unlimited, capabilities, benefits and cycles.
 * The enabled benefits JSON lives at level_benefits.hsx_lead_subject. Invalid
 * enabled definitions fail closed, including definitions saved outside our UI.
 */
final class MembershipCatalogService
{
    public const BENEFIT_KEY='hsx_lead_subject';
    public const CAPABILITIES=['can_publish','can_accept','can_view_inventory','all_models'];
    public const BENEFIT_ICONS=['account','home','order','grid','star','calendar','checkmark-circle'];
    /** Presentation only: never a permission, payment price or frozen grant. */
    public const VISUAL_PRESETS=[
        'neutral'=>['#F4F5F7','#E9ECF0','#334155','#64748B'],
        'bronze'=>['#F3E8D9','#DDC2A0','#64452F','#A47743'],
        'silver'=>['#F4F6FA','#D9E0EA','#435269','#7C8EA5'],
        'gold'=>['#FFF1C8','#E5C581','#6E4C18','#AF7B25'],
        'sapphire'=>['#EAF1FE','#AFC8F0','#264D7F','#3769AD'],
        'amethyst'=>['#F0EAF9','#CDBBE5','#65477F','#8665A9'],
        'obsidian'=>['#354153','#192230','#F4E6BC','#C9A967'],
    ];

    public static function levels(int $site,bool $lock=false): array
    {
        if ($site<=0) throw new CommonException('会员目录所属站点无效');
        $query=Db::name('member_level')->where('site_id',$site)->where('status',1)->order('growth asc,level_id asc');
        if ($lock) $query->lock(true);
        $out=[];$ranks=[];$entry=0;
        foreach($query->field('level_id,site_id,level_name,growth,status,level_benefits')->select()->toArray() as $row) {
            $tier=self::normalize($row,count($out)+1);if (!$tier) continue;
            if (isset($ranks[$tier['rank']])) throw new CommonException('协作会员成长值存在重复，请在会员等级中设置不同的成长值');
            if ($tier['is_entry']&&++$entry>1) throw new CommonException('同一站点只能启用一个入门会员等级，请在会员等级中核对');
            $ranks[$tier['rank']]=true;$tier['display_rank']=count($out)+1;
            foreach($tier['cycles'] as &$cycle)$cycle['product_key']=MembershipProductIdentity::forCycle($site,(int)$tier['level_id'],(string)$cycle['key']);unset($cycle);
            $out[]=$tier;
        }
        return $out;
    }

    public static function normalize(array $level,int $displayRank=1): array
    {
        $raw=$level['level_benefits']??[];
        // Existing framework levels may have no benefits configured at all.
        // An empty value is absence, never a default subject membership.
        if (is_string($raw)&&trim($raw)==='') return [];
        if (is_string($raw)) {try {$raw=json_decode($raw,true,32,JSON_THROW_ON_ERROR);}catch(\Throwable $error){throw new CommonException('会员等级权益数据格式异常，请在会员等级中重新保存');}}
        if (!is_array($raw)) throw new CommonException('会员等级权益数据格式异常');
        $config=$raw[self::BENEFIT_KEY]??[];
        if (!is_array($config)) throw new CommonException('商户协作权益数据格式异常');
        $enabled=self::flag($config['is_use']??0,'商户协作权益开关');
        if (!$enabled||(int)($level['status']??1)!==1) return [];
        $id=self::integer($level['level_id']??null,1,4294967295,'会员等级编号');
        $rank=self::integer($level['growth']??null,1,2147483647,'会员等级成长值');
        $name=Data::text($level,'level_name',50,true);
        $entry=self::flag($config['is_entry']??false,'入门等级开关');
        $experienceGate=array_key_exists('experience_gate_enabled',$config)?$config['experience_gate_enabled']:true;
        if(!is_bool($experienceGate))throw new CommonException('经验门槛开关请明确选择开启或关闭');
        $deposit=MembershipCostService::money($config['deposit_amount']??'0.00','等级押金');
        $buyExperience=$experienceGate?MembershipCostService::flag($config['experience_purchase_enabled']??false,'购买经验'):false;
        $unit=$experienceGate?MembershipCostService::money($config['experience_unit_price']??'0.00','经验单价'):'0.00';
        if($buyExperience&&Rules::cents($unit)<=0)throw new CommonException('启用购买经验时，请填写大于0的每经验价格');
        $visual=self::visual($config['visual']??[],$displayRank);
        $limit=self::integer($config['account_limit']??null,1,99999,'账号总名额（含负责人）');
        $unlimited=self::flag($config['unlimited']??null,'不限账号名额开关');$caps=[];
        foreach(self::CAPABILITIES as $key) $caps[$key]=self::flag($config[$key]??null,'协作权益 '.$key);
        $cycles=$config['cycles']??null;
        if (!is_array($cycles)||count($cycles)>50||!\addon\hsx_lead\app\support\PhpRuntime::isList($cycles)) throw new CommonException('会员购买周期请设置为最多50项的列表');
        $seen=[];$periods=[];
        foreach($cycles as $cycle) {
            if (!is_array($cycle)) throw new CommonException('会员购买周期格式异常');
            $key=$cycle['key']??null;
            if (!is_string($key)||!preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,23}$/D',$key)||isset($seen[$key])) throw new CommonException('会员周期标识须唯一，使用24位以内的字母、数字、横线或下划线');
            $seen[$key]=true;$on=self::flag($cycle['enabled']??null,'购买周期开关');
            $months=self::integer($cycle['months']??1,1,120,'购买周期月份');
            $title=Data::text($cycle,'name',30,true);$price='0.00';
            if ($on) {$cents=Rules::cents($cycle['price']??'');if ($cents<1||$cents>9999999999) throw new CommonException('已启用周期的价格须大于0且不超出支付账户精度');$price=Rules::amount($cents);}
            $periods[]=['key'=>$key,'name'=>$title,'months'=>$months,'enabled'=>$on,'price'=>$price];
        }
        return ['code'=>'ML'.$id,'level_id'=>$id,'name'=>$name,'rank'=>$rank,'display_rank'=>0,'enabled'=>true,
            'account_limit'=>$limit,'unlimited'=>$unlimited,'capabilities'=>$caps,'benefits'=>self::benefits($caps),'cycles'=>$periods,
            'deposit_amount'=>$deposit,'experience_purchase_enabled'=>$buyExperience,'experience_unit_price'=>$unit,
            'is_entry'=>$entry,'experience_gate_enabled'=>$experienceGate,'experience_required'=>!$experienceGate||$entry?'0.00':Rules::amount($rank*100),'experience_eligible'=>null,'experience_remaining'=>null,'level_color'=>$visual['accent_color'],'level_visual'=>$visual];
    }

    /** Stable server-owned benefit cards: no user-supplied image URL or HTML. */
    public static function benefits(array $caps): array
    {
        $titles=['can_publish'=>['发布客户需求','选择当地门店承接客户需求','order'],
            'can_accept'=>['承接客户线索','确认接单并反馈真实成交结果','account'],
            'can_view_inventory'=>['查看门店货盘','查看可交付门店的新鲜在售库存参考','grid'],
            'all_models'=>['全型号交付能力','按业务规则匹配型号，不代表实际现货库存','star']];
        $out=[];foreach($titles as $key=>[$title,$desc,$icon]) $out[]=['key'=>$key,'title'=>$title,'desc'=>$desc,
            'icon'=>$icon,'enabled'=>($caps[$key]??false)===true];
        return $out;
    }
    public static function emptyCapabilities(): array {return array_fill_keys(self::CAPABILITIES,false);}
    public static function color(int $rank): string {return self::visual([],$rank)['accent_color'];}
    /** Resolve safe current appearance; raw JSON and CSS never reach consumers. */
    public static function visual($input,int $displayRank): array
    {
        if(!is_array($input))throw new CommonException('等级外观配置请使用对象');
        $preset=$input['preset']??'auto';
        if(!is_string($preset)||!in_array($preset,['auto','bronze','silver','gold','sapphire','amethyst','obsidian','custom'],true))throw new CommonException('请选择有效的等级外观预设');
        $resolved=$preset==='auto'?($displayRank<=0?'neutral':(['bronze','silver','gold','sapphire','amethyst','obsidian'][min(5,$displayRank-1)])):$preset;
        $base=self::VISUAL_PRESETS[$resolved==='custom'?'bronze':$resolved];$out=['preset'=>$resolved];
        foreach(['start_color','end_color','text_color','accent_color'] as $i=>$key){
            $color=$input[$key]??'';
            if(!is_string($color)||($color!==''&&!preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/iD',$color)))throw new CommonException('等级外观颜色请使用#RGB或#RRGGBB格式');
            $out[$key]=$preset==='custom'&&$color!==''?strtoupper($color):$base[$i];
        }
        $angle=$input['angle']??135;
        if((!is_int($angle)&&!is_string($angle))||!preg_match('/^(?:0|[1-9][0-9]{0,2})$/D',(string)$angle)||(int)$angle>360)throw new CommonException('等级渐变角度请填写0到360之间的整数');
        $out['angle']=(int)$angle;$out['card_image']=self::visualImage($input['card_image']??'');
        $out['avatar_effect']=self::flag($input['avatar_effect']??true,'等级头像效果开关');return$out;
    }
    private static function visualImage($value): string
    {
        if(!is_string($value)||mb_strlen($value)>1000)throw new CommonException('等级卡片图片地址过长或格式不正确');
        if($value==='')return'';
        $decoded=rawurldecode($value);
        if(preg_match('/[\s\x00-\x1f\x7f<>\x22\x27`{}();\\\\]/u',$decoded))throw new CommonException('等级卡片图片地址包含不安全字符');
        $parts=parse_url($value);$path=is_array($parts)?(string)($parts['path']??''):'';
        if(preg_match('~(?:^|/)\.\.(?:/|$)~',rawurldecode($path)))throw new CommonException('等级卡片图片地址不能跨越目录');
        if(preg_match('~^https?://~i',$value)){
            if(!filter_var($value,FILTER_VALIDATE_URL)||!is_array($parts)||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))throw new CommonException('请使用有效的HTTP或HTTPS图片地址');
            return$value;
        }
        // Exactly the packaged membership-cover gallery, not arbitrary addon
        // files, encoded aliases or administrator-supplied resource names.
        if(preg_match('~^addon/hsx_lead/member-covers/(?:bronze|silver|gold|sapphire|amethyst|obsidian)\.(?:png|gif)$~D',$value))return$value;
        if(!preg_match('~^/?(?:upload|uploads|attachment)/[^?#]+$~uD',$value)||!is_array($parts)||isset($parts['scheme'])||isset($parts['host']))throw new CommonException('请使用上传返回的相对图片路径或HTTP(S)图片地址');
        return$value;
    }
    public static function validCode($code): bool {return is_string($code)&&preg_match('/^ML[1-9][0-9]{0,9}$/D',$code)===1&&(int)substr($code,2)<=4294967295;}
    private static function integer($value,int $min,int $max,string $label): int
    {
        if ((!is_int($value)&&!is_string($value))||!preg_match('/^(?:0|[1-9][0-9]*)$/D',(string)$value)||strlen((string)$value)>10||(int)$value<$min||(int)$value>$max) throw new CommonException($label.'无效');return (int)$value;
    }
    private static function flag($value,string $label): bool
    {
        if (!in_array($value,[true,false,1,0,'1','0'],true)) throw new CommonException($label.'请明确选择开启或关闭');return in_array($value,[true,1,'1'],true);
    }
}
