<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\support\Data;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Cache;

/** Site-owned verified public-template mapping. No built-in or invented WeChat template ID. */
final class NoticeConfigService
{
    public const CONFIG_KEY='HSX_LEAD_NOTICE_WEAPP';
    public const PHASES=['assigned'=>'hsx_lead_task_assigned','changed'=>'hsx_lead_task_changed'];
    public const VARIABLES=[
        'task_no'=>'工单编号','status_text'=>'工单处理状态','notice_remark'=>'操作提示',
        'occurred_at'=>'通知时间','demand_summary'=>'需求提示（不含客户资料）','task_id'=>'工单数字ID',
    ];

    public function get(int $site): array
    {
        self::assertSite($site); self::syncSite($site);
        $config=(new CoreConfigService())->getConfigValue($site,self::CONFIG_KEY);
        $stored=$config['templates']??[]; $rows=[];
        foreach(self::PHASES as $phase=>$key) {
            $row=$stored[$phase]??['phase'=>$phase,'tid'=>'','kid_list'=>[],'scene_desc'=>'','fields'=>[]];
            $notice=Db::name('sys_notice')->where('site_id',$site)->where('key',$key)->find()?:[];
            $configured=!empty($row['tid']) && !empty($row['fields']);
            $templateId=(string)($notice['weapp_template_id']??'');
            $enabled=(bool)($notice['is_weapp']??false);
            $status=!$configured?'unconfigured':($templateId===''?'awaiting_template':($enabled?'ready':'disabled'));
            $rows[]=array_replace($row,[
                'phase'=>$phase,'notice_key'=>$key,'name'=>$phase==='assigned'?'商户新工单提醒':'商户工单处理结果提醒',
                'configured'=>$configured,'framework_template_id'=>$templateId,'enabled'=>$enabled,'status'=>$status,
                'message'=>match($status){
                    'unconfigured'=>'尚未配置已核验公共模板；工单仍在商户工作台待办中，不会向微信发送',
                    'awaiting_template'=>'请前往小程序订阅消息重新获取本站模板，再开启；会员仍需主动订阅授权',
                    'disabled'=>'本站模板已获取但通知开关关闭，不会发送',
                    default=>'框架模板与开关已就绪；会员需已登录小程序并订阅，仍不保证终端送达',
                },
            ]);
        }
        return ['templates'=>$rows,'variables'=>array_map(static fn($key,$label)=>['key'=>$key,'label'=>$label],array_keys(self::VARIABLES),self::VARIABLES),
            'notice'=>'这里只登记管理员在本站小程序官方后台核验的公共模板与字段，不自动申请或发送；渠道开关仍使用牛云订阅消息设置。'];
    }

    public function save(int $site,array $input,int $uid=0): array
    {
        self::assertSite($site);
        $rows=$input['templates']??null;
        if (!is_array($rows) || !array_is_list($rows) || !$rows || count($rows)>2) throw new CommonException('通知配置应包含1—2个场景');
        $updates=[]; $seen=[];
        foreach($rows as $row) {
            if (!is_array($row)) throw new CommonException('通知场景格式不正确');
            $phase=Data::text($row,'phase',20,true);
            if (!isset(self::PHASES[$phase]) || isset($seen[$phase])) throw new CommonException('通知场景不存在或重复');
            $seen[$phase]=true; $tid=Data::text($row,'tid',20);
            if ($tid==='') {
                if (!empty($row['kid_list']) || !empty($row['fields'])) throw new CommonException('清空模板时请同时清空关键词编号与字段');
                $item=['phase'=>$phase,'tid'=>'','kid_list'=>[],'scene_desc'=>'','fields'=>[]];
            } else {
                if (!ctype_digit($tid) || ($row['confirmed']??false)!==true) throw new CommonException('请先在本站小程序官方后台核验公共模板编号、关键词与场景，并勾选确认');
                $kids=$row['kid_list']??null; $fields=$row['fields']??null;
                if (!is_array($kids) || !array_is_list($kids) || !$kids || count($kids)>10
                    || !is_array($fields) || !array_is_list($fields) || count($fields)!==count($kids)) throw new CommonException('模板关键词与字段必须一一对应，最多10项');
                $cleanKids=[];
                foreach($kids as $kid) {
                    if ((!is_int($kid) && !is_string($kid)) || !preg_match('/^[1-9]\d{0,2}$/D',(string)$kid)) throw new CommonException('关键词编号应为1—999整数');
                    $cleanKids[]=(int)$kid;
                }
                if (count(array_unique($cleanKids))!==count($cleanKids)) throw new CommonException('关键词编号不能重复');
                $cleanFields=[]; $fieldIds=[]; $variablesUsed=[];
                foreach($fields as $field) {
                    if (!is_array($field)) throw new CommonException('模板字段格式不正确');
                    $variable=Data::text($field,'variable',40,true); $keyword=Data::text($field,'keyword',40,true);
                    if (!isset(self::VARIABLES[$variable]) || !preg_match('/^(thing|character_string|time|date|phrase|number)([1-9]\d{0,2})$/D',$keyword,$parts)
                        || !in_array((int)$parts[2],$cleanKids,true) || isset($fieldIds[$keyword])) throw new CommonException('仅允许安全通知变量，字段名编号必须与已核验关键词对应且不能重复');
                    $fieldIds[$keyword]=true;
                    if (isset($variablesUsed[$variable])) throw new CommonException('同一通知变量请只映射一次，避免不同字段类型造成歧义');
                    $variablesUsed[$variable]=true;
                    if (in_array($parts[1],['time','date'],true) && $variable!=='occurred_at') throw new CommonException('时间字段请映射通知时间');
                    if ($parts[1]==='number' && $variable!=='task_id') throw new CommonException('数字字段请映射工单数字ID');
                    $cleanFields[]=['label'=>Data::text($field,'label',30,true),'variable'=>$variable,'keyword'=>$keyword];
                }
                if (count(array_unique(array_map(static fn($f)=>(int)preg_replace('/\D/','',$f['keyword']),$cleanFields)))!==count($cleanKids)) {
                    throw new CommonException('每个关键词编号必须恰好映射一个字段');
                }
                $item=['phase'=>$phase,'tid'=>$tid,'kid_list'=>$cleanKids,'scene_desc'=>Data::text($row,'scene_desc',100,true),'fields'=>$cleanFields];
            }
            $updates[$phase]=$item;
        }
        Db::transaction(function()use($site,$updates,$uid){
            if (!Db::name('site')->where('site_id',$site)->lock(true)->field('site_id')->find()) throw new CommonException('当前站点不存在');
            // Read the database after taking the site lock; a pre-lock config/cache
            // snapshot could overwrite a different phase saved by another operator.
            $raw=Db::name('sys_config')->where('site_id',$site)->where('config_key',self::CONFIG_KEY)->lock(true)->value('value');
            $latest=Data::decode(is_string($raw)?$raw:null);
            $templates=$latest['templates']??[]; $changed=[];
            foreach($updates as $phase=>$item) {
                if (($templates[$phase]??null)!==$item) $changed[]=$phase;
                $templates[$phase]=$item;
            }
            self::syncSite($site);
            Cache::tag(CoreConfigService::$cache_tag_name.$site)->clear();
            (new CoreConfigService())->setConfig($site,self::CONFIG_KEY,['templates'=>$templates,'updated_at'=>time()]);
            foreach($changed as $phase) Db::name('sys_notice')->where('site_id',$site)->where('key',self::PHASES[$phase])
                ->update(['is_weapp'=>0,'weapp_template_id'=>'']);
            Data::audit($site,$uid,0,'notice_config',0,'updated',['phases'=>$changed]);
        });
        Cache::tag(CoreConfigService::$cache_tag_name.$site)->clear();
        return $this->get($site);
    }

    public static function syncSite(int $site): void
    {
        self::assertSite($site);
        // Framework sys_notice has no (site_id,key) unique index. Serialize on the
        // existing site row instead of swallowing arbitrary insert failures.
        Db::transaction(function()use($site){
            if (!Db::name('site')->where('site_id',$site)->lock(true)->field('site_id')->find()) throw new CommonException('当前站点不存在');
            foreach(self::PHASES as $key) if (!Db::name('sys_notice')->where('site_id',$site)->where('key',$key)->count()) {
                Db::name('sys_notice')->insert(['site_id'=>$site,'key'=>$key,'is_weapp'=>0,'is_wechat'=>0,'is_sms'=>0,
                    'weapp_template_id'=>'','wechat_template_id'=>'','sms_content'=>'','wechat_first'=>'','wechat_remark'=>'','sms_id'=>'']);
            }
        });
    }

    public static function syncAssignedSites(): void
    {
        foreach(Db::name('site')->whereLike('app|addons','%"hsx_lead"%')->column('site_id') as $site) self::syncSite((int)$site);
    }

    /** Explicit site is mandatory in workers. Dict loading alone may use the current admin request site. */
    public static function weappForSite(int $site): array
    {
        if ($site<=0) return [];
        $config=(new CoreConfigService())->getConfigValue($site,self::CONFIG_KEY); $result=[];
        foreach(($config['templates']??[]) as $phase=>$row) {
            if (!isset(self::PHASES[$phase]) || empty($row['tid']) || empty($row['fields'])) continue;
            $result[self::PHASES[$phase]]=['tid'=>$row['tid'],'kid_list'=>$row['kid_list'],'scene_desc'=>$row['scene_desc'],
                'content'=>array_map(static fn($f)=>[$f['label'],'{'.$f['variable'].'}',$f['keyword']],$row['fields']),
                'tips'=>'公共模板与字段由本站管理员核验配置。请重新获取本站模板并开启；仍需接收会员主动订阅，不保证终端送达。'];
        }
        return $result;
    }

    private static function assertSite(int $site): void
    {
        if ($site<=0) throw new CommonException('无效站点');
    }
}

