<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;

use addon\hsx_lead\app\service\{Context,NoticeConfigService};
use addon\hsx_lead\app\support\Data;
use app\service\core\sys\CoreConfigService;
use app\dict\site\SiteDict;
use app\service\core\site\CoreSiteService;
use core\exception\CommonException;
use think\facade\{Db,Cache};

/** Rules only live in config. Attempts, customer choices and source facts never do. */
final class AccountNoticePolicyService
{
    public const KEY='HSX_LEAD_ACCOUNT_NOTICE_POLICY';
    public static function installed(int $site): bool
    {
        if($site<=0||!Db::name('addon')->where('key','hsx_lead')->where('status',1)->count())return false;
        $row=Db::name('site')->where('site_id',$site)->field('app,addons,status,expire_time')->find();
        if(!$row||(int)$row['status']!==SiteDict::ON||((int)$row['expire_time']>0&&(int)$row['expire_time']<=time()))return false;
        return in_array('hsx_lead',array_merge(Data::decode($row['app']??''),Data::decode($row['addons']??''),(new CoreSiteService())->getAddonKeysBySiteId($site)),true);
    }
    public static function raw(int $site): array
    {
        $row=Db::name('sys_config')->where('site_id',$site)->where('config_key',self::KEY)->find();
        $saved=Data::decode($row['value']??'');$events=[];
        foreach(NoticeEventCatalog::EVENTS as$key=>$definition)$events[$key]=array_replace([
            'event_key'=>$key,'enabled'=>false,'channels'=>array_fill_keys(NoticeEventCatalog::CHANNELS,false),
            'weapp'=>[],'wechat'=>[]],is_array($saved['events'][$key]??null)?$saved['events'][$key]:[]);
        return array_replace(['enabled'=>false,'enabled_at'=>0,'source_after_id'=>0,'resume_after_id'=>0,'ttl_seconds'=>86400,'max_attempts'=>3,'response_reminder_minutes'=>[30,10,5,1],'quiet_hours'=>NoticeDeliveryWindow::defaults()],$saved,['events'=>$events]);
    }
    public function get(int $site): array
    {
        $policy=self::raw($site);$events=[];
        foreach($policy['events']as$key=>$row){$config=Db::name('sys_notice')->where('site_id',$site)->where('key',NoticeEventCatalog::key($key))->find()?:[];$ready=[];
            foreach(NoticeEventCatalog::CHANNELS as$channel){$field=$channel==='sms'?'sms_id':$channel.'_template_id';
                $mapping=$channel==='sms'?NoticeEventCatalog::smsDefinitions():self::mapping($site,$channel);
                $ready[$channel]=['status'=>empty($mapping[NoticeEventCatalog::key($key)])||empty($config[$field])?'pending_configuration':(empty($config['is_'.$channel])?'disabled':'ready'),
                    'framework_template_id'=>(string)($config[$field]??''),'enabled'=>!empty($config['is_'.$channel])];}
            $events[]=array_replace($row,NoticeEventCatalog::EVENTS[$key],['group'=>str_starts_with($key,'account_')?'account':'cooperation','event_key'=>$key,'notice_key'=>NoticeEventCatalog::key($key),'readiness'=>$ready,'variables'=>NoticeEventCatalog::variables($key),'field_types'=>NoticeEventCatalog::fieldTypes($key)]);}
        return array_replace($policy,['events'=>$events,'installed'=>self::installed($site),'variables'=>NoticeEventCatalog::VARIABLES+NoticeEventCatalog::COOP_VARIABLES,
            'channel_routes'=>['weapp'=>'/site/channel/weapp/message','wechat'=>'/site/channel/wechat/message','sms'=>'/site/setting/notice/template'],
            'message'=>'默认不发送；只处理启用后的新业务。接单超时不自动撤回或限制接单，发布方可继续等待或重新选择。用户仍需订阅授权。']);
    }
    public function save(Context $context,array $input): array
    {
        $context->permission('hsx_lead_notice_center_save');$site=$context->site;
        if(!self::installed($site))throw new CommonException('本站未安装启用商户协作');
        Db::transaction(function()use($context,$site,$input){
            Db::name('site')->where('site_id',$site)->lock(true)->find();$current=self::raw($site);$saved=$current;
            foreach(['enabled']as$field)if(array_key_exists($field,$input)){if(!is_bool($input[$field]))throw new CommonException('通知开关格式不正确');$saved[$field]=$input[$field];}
            if(array_key_exists('quiet_hours',$input))$saved['quiet_hours']=NoticeDeliveryWindow::normalize($input['quiet_hours']);
            foreach(['ttl_seconds'=>[300,86400],'max_attempts'=>[1,5]]as$field=>$range)if(array_key_exists($field,$input)){
                if(!is_int($input[$field])||$input[$field]<$range[0]||$input[$field]>$range[1])throw new CommonException('通知时效或次数不在允许范围');$saved[$field]=$input[$field];}
            if(array_key_exists('response_reminder_minutes',$input)){
                $nodes=$input['response_reminder_minutes'];if(!is_array($nodes)||count($nodes)>8)throw new CommonException('提醒节点最多8项');
                foreach($nodes as$node)if(!is_int($node)||$node<1||$node>1440)throw new CommonException('提醒节点需为1—1440整数分钟');
                $nodes=array_values(array_unique($nodes));rsort($nodes,SORT_NUMERIC);$saved['response_reminder_minutes']=$nodes;
            }
            $events=$input['events']??[];
            if(!is_array($events)||count($events)>count(NoticeEventCatalog::EVENTS))throw new CommonException('通知事件格式不正确');$seen=[];
            foreach($events as$row){$key=$row['event_key']??'';if(!isset(NoticeEventCatalog::EVENTS[$key])||isset($seen[$key]))throw new CommonException('通知事件不存在或重复');$seen[$key]=true;$event=$saved['events'][$key];
                if(array_key_exists('enabled',$row)){if(!is_bool($row['enabled']))throw new CommonException('事件开关格式不正确');$event['enabled']=$row['enabled'];}
                foreach($row['channels']??[]as$channel=>$value){if(!in_array($channel,NoticeEventCatalog::CHANNELS,true)||!is_bool($value))throw new CommonException('通知渠道开关格式不正确');$event['channels'][$channel]=$value;}
                foreach(['weapp','wechat']as$channel)if(array_key_exists($channel,$row)){
                    $mapping=self::validateTemplate($channel,$row[$channel],$key);
                    if($mapping!==$event[$channel]){self::syncSite($site);Db::name('sys_notice')->where('site_id',$site)->where('key',NoticeEventCatalog::key($key))->update(['is_'.$channel=>0,$channel.'_template_id'=>'']);}
                    $event[$channel]=$mapping;}
                $saved['events'][$key]=$event;
            }
            // First activation creates a permanent lower bound. Later edits never replay historical money.
            if($saved['enabled']&&empty($current['enabled'])){
                $saved['resume_after_id']=(int)Db::name('member_account_log')->max('id');
                if((int)$saved['enabled_at']===0){$saved['enabled_at']=time();$saved['source_after_id']=$saved['resume_after_id'];}
            }
            $saved['updated_at']=time();$json=Data::json($saved);$query=Db::name('sys_config')->where('site_id',$site)->where('config_key',self::KEY);
            if($query->count())$query->update(['value'=>$json]);else Db::name('sys_config')->insert(['site_id'=>$site,'config_key'=>self::KEY,'value'=>$json]);
            self::syncSite($site);$context->audit('notice_policy',0,'updated',['enabled'=>$saved['enabled'],'events'=>array_keys($seen)]);
        });
        Cache::tag(CoreConfigService::$cache_tag_name.$site)->clear();return$this->get($site);
    }
    public static function syncSite(int $site): void
    {
        Db::transaction(function()use($site){Db::name('site')->where('site_id',$site)->lock(true)->find();
            foreach(NoticeEventCatalog::EVENTS as$event=>$definition)if(!Db::name('sys_notice')->where('site_id',$site)->where('key',NoticeEventCatalog::key($event))->count())
                Db::name('sys_notice')->insert(['site_id'=>$site,'key'=>NoticeEventCatalog::key($event),'is_weapp'=>0,'is_wechat'=>0,'is_sms'=>0,'weapp_template_id'=>'','wechat_template_id'=>'','sms_id'=>'','sms_content'=>'','wechat_first'=>'','wechat_remark'=>'']);});
    }
    public static function mapping(int $site,string $channel,bool $pending=false): array
    {
        if($site<=0)return[];$policy=self::raw($site);$result=[];
        foreach($policy['events']as$event=>$row){$config=$row[$channel]??[];$key=NoticeEventCatalog::key($event);
            if($config&&self::validStoredTemplate($channel,$config,$event)){
                $content=array_map(static fn($f)=>[$f['label'],'{'.$f['variable'].'}',$f['keyword']],$config['fields']);
                $result[$key]=$channel==='weapp'?['tid'=>$config['tid'],'kid_list'=>$config['kid_list'],'scene_desc'=>$config['scene_desc'],'content'=>$content]
                    :['temp_key'=>$config['temp_key'],'keyword_name_list'=>array_column($config['fields'],'label'),'content'=>$content];
            }elseif($pending&&$channel==='weapp'&&defined(\app\service\admin\weapp\WeappTemplateService::class.'::SUPPORTS_PENDING_DEFINITIONS'))
                $result[$key]=['tid'=>'','kid_list'=>[],'scene_desc'=>'','content'=>[],'configuration_required'=>true,'configuration_message'=>'通知尚未开通，请在商户协作「通知中心」核验本站真实模板后获取。'];
        }return$result;
    }
    private static function validStoredTemplate(string $channel,array $row,string $event): bool
    {
        try{self::validateTemplate($channel,$row+['confirmed'=>true],$event);return true;}catch(\Throwable $error){return false;}
    }
    private static function validateTemplate(string $channel,$row,string $event): array
    {
        if(!is_array($row))throw new CommonException('模板格式不正确');if($row===[])return[];
        $idField=$channel==='weapp'?'tid':'temp_key';$id=(string)($row[$idField]??'');if($id==='')return[];
        if(($row['confirmed']??false)!==true||!preg_match($channel==='weapp'?'/^[1-9][0-9]{0,19}$/D':'/^[A-Za-z0-9_-]{1,64}$/D',$id))throw new CommonException('请核验本站官方模板与字段并确认；不可复用他站模板ID');
        $fields=$row['fields']??[];if(!is_array($fields)||!$fields||count($fields)>10)throw new CommonException('模板字段应为1—10项');$clean=[];$kids=[];$vars=[];
        foreach($fields as$field){$variable=$field['variable']??'';$keyword=$field['keyword']??'';$label=trim((string)($field['label']??''));
            if(!isset(NoticeEventCatalog::variables($event)[$variable])||$label===''||mb_strlen($label)>30||!is_string($keyword)||!preg_match('/^(thing|character_string|time|date|phrase|number'.($channel==='wechat'?'|keyword':'').')([1-9][0-9]{0,2})$/D',$keyword,$parts)||isset($kids[(int)$parts[2]])||isset($vars[$variable]))throw new CommonException('模板字段类型暂不支持，或安全通知变量不匹配、重复；请核验真实官方模板');
            // Decimal money is formatted as text, never forced into an unverified money/amount or integer keyword.
            $allowed=NoticeEventCatalog::fieldTypes($event,$channel==='wechat')[$parts[1]]??[];
            if(!in_array($variable,$allowed,true))throw new CommonException('通知字段类型与当前事件不匹配');$kids[(int)$parts[2]]=true;$vars[$variable]=true;$clean[]=['keyword'=>$keyword,'variable'=>$variable,'label'=>$label];}
        if($channel==='wechat')return['temp_key'=>$id,'fields'=>$clean];
        $given=$row['kid_list']??[];if(!is_array($given)||array_map('intval',$given)!==array_keys($kids))throw new CommonException('模板关键词编号必须与字段一一对应');
        $scene=trim((string)($row['scene_desc']??''));if($scene===''||mb_strlen($scene)>100)throw new CommonException('请填写已核验的订阅场景');
        return['tid'=>$id,'kid_list'=>array_keys($kids),'scene_desc'=>$scene,'fields'=>$clean];
    }
}
