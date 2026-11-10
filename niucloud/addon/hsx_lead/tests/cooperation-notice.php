<?php
declare(strict_types=1);

// Random, process-owned localhost schema and framework sender double only.
if (PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true)) exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $function) if(function_exists($function)) exit("Disable network functions before isolated testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';

use addon\hsx_lead\app\listener\notice\CooperationNoticeData;
use addon\hsx_lead\app\service\{Context,FrameworkNoticeGateway,NoticeConfigService};
use addon\hsx_lead\app\service\cooperation\{CooperationService,CooperationNoticeService,CooperationNoticeReadService,CooperationNoticeOutbox,Store};
use addon\hsx_lead\app\support\Data;
use app\dict\sys\ConfigKeyDict;
use app\service\core\sys\CoreConfigService;
use think\facade\{Db,Event};

$checks=0;$pdo=null;$schema='';$failed=false;
function coopNoticeCheck(bool $condition,string $label): void
{
    if (!$condition) throw new RuntimeException('Assertion failed: '.$label);
    $GLOBALS['checks']++;
}
function coopNoticeReject(callable $call,string $label): void
{
    try {$call();} catch(Throwable $error) {coopNoticeCheck(true,$label);return;}
    throw new RuntimeException('Expected rejection: '.$label);
}
function coopNoticeStates(int $site,int $id): array
{
    return array_map(static fn(array $row):array=>Data::decode($row['detail_json']),Data::query('audit',$site)
        ->where('entity_type','collaboration_notice')->where('entity_id',$id)->order('id')->select()->toArray());
}

try {
    [$app,$pdo,$schema,$site]=leadBoot();leadAssertIsolated($schema);
    $events=require dirname(__DIR__).'/app/event.php';
    coopNoticeCheck(in_array(CooperationNoticeData::class,$events['listen']['NoticeData']??[],true),'current plugin preserves the cooperation framework NoticeData listener');
    coopNoticeCheck(!isset($events['listen']['HsxLeadTaskAssigned'])&&!isset($events['listen']['HsxLeadTaskChanged']),'retired platform-task events are absent');
    Event::remove('NoticeData');Event::remove('HsxLeadCollaborationChanged');
    Event::listen('NoticeData',CooperationNoticeData::class);
    Event::listen('HsxLeadCollaborationChanged',$events['listen']['HsxLeadCollaborationChanged'][0]);
    $listener=new CooperationNoticeData();
    coopNoticeCheck($listener->handle(['site_id'=>$site,'key'=>'unrelated_notice'])===null,'unrelated framework notice is not intercepted');
    coopNoticeCheck($listener->handle(['site_id'=>$site,'key'=>NoticeConfigService::PHASES['assigned'],'data'=>['task_id'=>1]])===null,'old task_id payload cannot load a retired workflow');
    coopNoticeCheck($listener->handle(['site_id'=>$site,'key'=>NoticeConfigService::PHASES['assigned'],'data'=>'bad'])===null,'malformed notice data is ignored safely');
    coopNoticeCheck($listener->handle(['site_id'=>$site,'key'=>NoticeConfigService::PHASES['assigned'],'data'=>['collaboration_id'=>1,'channel'=>'wecom']])===null,'unsupported channels never masquerade as member notifications');

    Db::name('site')->where('site_id',$site)->update(['site_domain'=>'https://notice-one.example.test']);
    Db::name('site')->where('site_id',$site+1)->update(['site_domain'=>'https://notice-two.example.test']);
    foreach([101,102,103] as $member) Db::name('member')->where('member_id',$member)->update(['weapp_openid'=>'fixture-weapp-'.$member,'wx_openid'=>'fixture-wechat-'.$member,'status'=>1,'is_del'=>0,'delete_time'=>0]);
    Db::name('member')->insert(['member_id'=>104,'site_id'=>$site,'nickname'=>'Other same-site member','mobile'=>'19900000104','weapp_openid'=>'fixture-weapp-104','wx_openid'=>'fixture-wechat-104']);
    $merchant=static function(int $owner,int $member,string $name):int {return(int)Data::query('merchant',$owner)->insertGetId(['site_id'=>$owner,'name'=>$name,'member_id'=>$member,'status'=>1,'create_time'=>time(),'update_time'=>time()]);};
    $publisher=$merchant($site,101,'Publisher');$receiver=$merchant($site,102,'Receiver');$outsider=$merchant($site,104,'Other merchant');$foreign=$merchant($site+1,103,'Other-site merchant');
    Store::partner($site,$publisher);$receiverPartner=Store::partner($site,$receiver);Store::partner($site,$outsider);Store::partner($site+1,$foreign);
    $counter=0;
    $newLead=static function(int $owner,int $pub,int $rec=0,string $status='assigned')use(&$counter):int {
        $counter++;
        return(int)Store::query('collaboration',$owner)->insertGetId(['site_id'=>$owner,'lead_no'=>'NOTICE_FIXTURE_'.$counter,'publisher_id'=>$pub,'receiver_id'=>$rec,
            'owner_uid'=>1,'request_id'=>'notice-fixture-'.$counter,'customer_name'=>'HiddenCustomerMarker','mobile'=>'18800009999','models_json'=>Data::json([['title'=>'HiddenModelMarker']]),
            'remark'=>'HiddenRemarkMarker','status'=>$status,'assignment_version'=>1,'create_time'=>time(),'update_time'=>time()]);
    };

    // Exercise the real registered business listener with disabled channels. It
    // persists precise reasons, but never enters a network-capable sender.
    $disabled=$newLead($site,$publisher,$receiver);
    event('HsxLeadCollaborationChanged',['site_id'=>$site,'lead_id'=>$disabled,'action'=>'assigned']);
    coopNoticeCheck(coopNoticeStates($site,$disabled)===[],'disabled queue records a durable business event without synchronous notification claims');
    foreach(Data::query('audit',$site)->where('entity_type',CooperationNoticeOutbox::TYPE)->where('entity_id',$disabled)->where('action','notice_pending')->column('id')as$pending)CooperationNoticeOutbox::process($site,(int)$pending);
    $states=coopNoticeStates($site,$disabled);
    coopNoticeCheck(count($states)===6&&array_unique(array_column($states,'status'))===['disabled'],'real assigned event creates six independent disabled-channel audit facts');
    event('HsxLeadCollaborationChanged',['site_id'=>$site,'lead_id'=>$disabled,'action'=>'assigned']);
    coopNoticeCheck(count(coopNoticeStates($site,$disabled))===6,'real business event is idempotent before any sender is configured');
    Store::query('collaboration',$site)->where('id',$disabled)->update(['status'=>'following']);
    event('HsxLeadCollaborationChanged',['site_id'=>$site,'lead_id'=>$disabled,'action'=>'accepted']);
    foreach(Data::query('audit',$site)->where('entity_type',CooperationNoticeOutbox::TYPE)->where('entity_id',$disabled)->where('action','notice_pending')->column('id')as$pending)CooperationNoticeOutbox::process($site,(int)$pending);
    $changedStates=coopNoticeStates($site,$disabled);
    coopNoticeCheck(count($changedStates)===12&&array_column(array_slice($changedStates,-6),'phase')===array_fill(0,6,'changed'),'real changed event reaches both parties through the current cooperation listener, not retired task events');
    Event::remove('HsxLeadCollaborationChanged');

    $configure=static function(int $owner,string $suffix):void {
        $templates=[];$wechat=[];
        foreach(NoticeConfigService::PHASES as $phase=>$key) {
            $templates[$phase]=['phase'=>$phase,'tid'=>'12345','kid_list'=>[1,2,3],'scene_desc'=>'Synthetic isolated fixture','fields'=>[
                ['label'=>'编号','variable'=>'task_no','keyword'=>'character_string1'],['label'=>'状态','variable'=>'status_text','keyword'=>'thing2'],['label'=>'需求提示','variable'=>'demand_summary','keyword'=>'thing3']]];
            $wechat[$phase]=['phase'=>$phase,'temp_key'=>'fixture_'.$suffix,'fields'=>[
                ['label'=>'编号','variable'=>'task_no','keyword'=>'keyword1'],['label'=>'状态','variable'=>'status_text','keyword'=>'keyword2']]];
        }
        $config=new CoreConfigService();$config->setConfig($owner,NoticeConfigService::CONFIG_KEY,['templates'=>$templates,'wechat_templates'=>$wechat]);
        $config->setConfig($owner,ConfigKeyDict::SMS,['default'=>'isolated_sender_double']);
        NoticeConfigService::syncSite($owner);
        foreach(NoticeConfigService::PHASES as $phase=>$key) Db::name('sys_notice')->where('site_id',$owner)->where('key',$key)->update([
            'is_weapp'=>1,'is_wechat'=>1,'is_sms'=>1,'weapp_template_id'=>'weapp_'.$suffix.'_'.$phase,'wechat_template_id'=>'wechat_'.$suffix.'_'.$phase,'sms_id'=>'sms_'.$suffix.'_'.$phase]);
    };
    $configure($site,'one');$configure($site+1,'two');
    $claims=[];$sent=[];$resolved=[];
    $gateway=new FrameworkNoticeGateway(static function(string $channel,array $event)use(&$sent):bool {$sent[]=['channel'=>$channel,'event'=>$event];return true;});
    $dispatch=static function(array $payload)use(&$claims,&$resolved,$gateway,$listener) {
        $claims[]=$payload;$resolved[]=$listener->handle($payload);
        return $gateway->dispatch($payload);
    };
    $notices=new CooperationNoticeService($dispatch);
    $id=$newLead($site,$publisher,$receiver);$event=['site_id'=>$site,'lead_id'=>$id,'action'=>'assigned'];$notices->changed($event);
    coopNoticeCheck(count($claims)===6&&count($sent)===6,'assigned event dispatches publisher-changed and receiver-assigned through all three framework channels');
    coopNoticeCheck(count(array_filter($sent,static fn(array $r):bool=>$r['event']['to']['member_id']===102&&$r['event']['key']===NoticeConfigService::PHASES['assigned']))===3,'new assignment targets only its current receiving merchant');
    coopNoticeCheck(count(array_filter($sent,static fn(array $r):bool=>$r['event']['to']['member_id']===101&&$r['event']['key']===NoticeConfigService::PHASES['changed']))===3,'publisher receives changed notices, not the receiving work reminder');
    foreach($resolved as $data) {
        coopNoticeCheck($data['vars']['__weapp_page']==='addon/hsx_lead/pages/workbench/index?collaboration_id='.$id,'miniapp link uses the current collaboration_id');
        coopNoticeCheck($data['vars']['__wechat_page']==='https://notice-one.example.test/wap/addon/hsx_lead/pages/workbench/index?collaboration_id='.$id,'public-account link uses this tenant HTTPS entry and collaboration_id');
    }
    foreach($sent as $row) {
        $channel=$row['channel'];$out=$row['event'];
        coopNoticeCheck($out['site_id']===$site&&$out['template']['site_id']===$site,'framework sender envelope remains tenant scoped');
        foreach(['weapp','wechat','sms'] as $candidate) coopNoticeCheck((int)$out['template']['is_'.$candidate]===($candidate===$channel?1:0),'only the selected channel is enabled in each sender envelope');
        if($channel==='sms') {
            coopNoticeCheck(isset($out['__hsx_lead_notice'])&&!isset($out['vars']['__weapp_page'],$out['vars']['url']),'SMS correlation stays in the log envelope, not approved provider variables');
            $expected=$out['key']===NoticeConfigService::PHASES['assigned']?['task_no']:['task_no','status_text'];
            coopNoticeCheck(array_keys($out['vars'])===$expected,'SMS provider receives only dictionary-declared template variables');
        }
    }
    $allSafe=Data::json(['claims'=>$claims,'sent'=>$sent,'audit'=>Data::query('audit',$site)->where('entity_type','collaboration_notice')->select()->toArray()]);
    foreach(['HiddenCustomerMarker','18800009999','HiddenModelMarker','HiddenRemarkMarker'] as $secret) coopNoticeCheck(!str_contains($allSafe,$secret),'customer detail is absent from notice payloads and audits: '.$secret);
    $notices->changed($event);
    coopNoticeCheck(count($claims)===6&&count(coopNoticeStates($site,$id))===6,'replayed assigned event neither resubmits nor duplicates audit rows');
    coopNoticeCheck(array_values(array_unique(array_column(coopNoticeStates($site,$id),'status')))===['submitted'],'gateway acceptance is recorded as submitted, never terminal delivery');
    coopNoticeReject(static fn()=>$listener->handle($claims[0]),'submitted audit cannot be replayed through NoticeData');

    Store::query('collaboration',$site)->where('id',$id)->update(['status'=>'following']);$before=count($sent);
    $notices->changed(['site_id'=>$site,'lead_id'=>$id,'action'=>'accepted']);
    coopNoticeCheck(count($sent)-$before===6&&count(coopNoticeStates($site,$id))===12,'changed business state receives separate audits and both participant notifications');
    foreach(array_slice($sent,$before) as $row) coopNoticeCheck(in_array($row['event']['to']['member_id'],[101,102],true)&&$row['event']['key']===NoticeConfigService::PHASES['changed'],'follow-up notifies both parties but never unrelated recipients');

    // The same source ids and request host must never select another tenant's
    // account, notice switches, templates, members or callback path.
    $foreignId=$newLead($site+1,$foreign,0,'following');$before=count($sent);request()->siteId($site);
    $notices->changed(['site_id'=>$site+1,'lead_id'=>$foreignId,'action'=>'accepted']);
    coopNoticeCheck(count($sent)-$before===3,'explicit second tenant can dispatch independently of request identity');
    foreach(array_slice($sent,$before) as $row) {
        coopNoticeCheck($row['event']['site_id']===$site+1&&$row['event']['to']['member_id']===103,'second tenant never receives first-tenant identities');
        $channel=$row['channel'];$field=$channel==='sms'?'sms_id':$channel.'_template_id';
        coopNoticeCheck(str_contains($row['event']['template'][$field],'_two_'),'second tenant uses its own configured template');
        if($channel!=='sms') coopNoticeCheck(str_starts_with($row['event']['vars']['url'],'https://notice-two.example.test/'),'second tenant notification uses its own domain');
    }
    coopNoticeCheck(CooperationNoticeService::statusForLead($site,$foreignId)===[],'notice status lookup cannot cross the tenant boundary');

    foreach(NoticeConfigService::PHASES as $key) Db::name('sys_notice')->where('site_id',$site)->where('key',$key)->update(['is_sms'=>0]);
    $channelId=$newLead($site,$publisher,$receiver);$before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$channelId,'action'=>'assigned']);
    coopNoticeCheck(count($sent)-$before===4,'disabled SMS does not stop weapp or public-account notifications');
    coopNoticeCheck(count(array_filter(coopNoticeStates($site,$channelId),static fn(array $r):bool=>$r['channel']==='sms'&&$r['status']==='disabled'))===2,'disabled channel is visible for each affected participant');
    foreach(NoticeConfigService::PHASES as $key) Db::name('sys_notice')->where('site_id',$site)->where('key',$key)->update(['is_sms'=>1]);

    $invalid=[
        'disabled merchant'=>[static fn()=>Data::query('merchant',$site)->where('id',$receiver)->update(['status'=>0]),static fn()=>Data::query('merchant',$site)->where('id',$receiver)->update(['status'=>1])],
        'blocked merchant'=>[static fn()=>Store::query('partner',$site)->where('id',$receiverPartner['id'])->update(['blocked_at'=>time()]),static fn()=>Store::query('partner',$site)->where('id',$receiverPartner['id'])->update(['blocked_at'=>0])],
        'disabled member'=>[static fn()=>Db::name('member')->where('member_id',102)->update(['status'=>0]),static fn()=>Db::name('member')->where('member_id',102)->update(['status'=>1])],
        'deleted member'=>[static fn()=>Db::name('member')->where('member_id',102)->update(['is_del'=>1]),static fn()=>Db::name('member')->where('member_id',102)->update(['is_del'=>0])],
        'deleted member timestamp'=>[static fn()=>Db::name('member')->where('member_id',102)->update(['delete_time'=>time()]),static fn()=>Db::name('member')->where('member_id',102)->update(['delete_time'=>0])],
        'foreign member binding'=>[static fn()=>Data::query('merchant',$site)->where('id',$receiver)->update(['member_id'=>103]),static fn()=>Data::query('merchant',$site)->where('id',$receiver)->update(['member_id'=>102])],
        'missing member binding'=>[static fn()=>Data::query('merchant',$site)->where('id',$receiver)->update(['member_id'=>0]),static fn()=>Data::query('merchant',$site)->where('id',$receiver)->update(['member_id'=>102])],
    ];
    foreach($invalid as $label=>[$mutate,$restore]) {
        $mutate();$invalidId=$newLead($site,$publisher,$receiver);$before=count($sent);
        $notices->changed(['site_id'=>$site,'lead_id'=>$invalidId,'action'=>'assigned']);
        coopNoticeCheck(count($sent)-$before===3,'invalid receiving identity is not submitted: '.$label);
        coopNoticeCheck(count(array_filter(coopNoticeStates($site,$invalidId),static fn(array $r):bool=>$r['phase']==='assigned'&&$r['status']==='pending_recipient'))===3,'all blocked recipient channels expose a reason: '.$label);
        $restore();
    }
    Db::name('member')->where('member_id',102)->update(['weapp_openid'=>'']);$missing=$newLead($site,$publisher,$receiver);$before=count($sent);
    $notices->changed(['site_id'=>$site,'lead_id'=>$missing,'action'=>'assigned']);
    coopNoticeCheck(count($sent)-$before===5,'a missing miniapp identity does not disable the same merchant public-account or SMS identity');
    Db::name('member')->where('member_id',102)->update(['weapp_openid'=>'fixture-weapp-102']);

    // Claim-to-send races: the sender double is never reached by a recipient
    // whose account, merchant, binding or original assignment changed in flight.
    foreach($invalid as $label=>[$mutate,$restore]) {
        $raceId=$newLead($site,$publisher,$receiver);$before=count($sent);$changed=false;
        $race=new CooperationNoticeService(static function(array $payload)use(&$changed,$mutate,$gateway,$site,$raceId,$listener):bool {
            if(!$changed&&$payload['key']===NoticeConfigService::PHASES['assigned']) {
                $changed=true;
                coopNoticeReject(static fn()=>$listener->handle(array_replace($payload,['site_id'=>$site+1])),'foreign site cannot consume the claimed audit');
                coopNoticeReject(static fn()=>$listener->handle(array_replace($payload,['channel'=>'wechat'])),'wrong channel cannot consume the claimed weapp audit');
                coopNoticeReject(static fn()=>$listener->handle(array_replace($payload,['data'=>array_replace($payload['data'],['collaboration_id'=>$raceId+9999])])),'wrong collaboration cannot consume the claimed audit');
                $mutate();
            }
            return $gateway->dispatch($payload);
        });
        $raceEvent=['site_id'=>$site,'lead_id'=>$raceId,'action'=>'assigned'];$race->changed($raceEvent);
        coopNoticeCheck($changed&&count($sent)-$before===3,'claim-time identity change never reaches a receiving sender: '.$label);
        coopNoticeCheck(count(array_filter(coopNoticeStates($site,$raceId),static fn(array $r):bool=>$r['phase']==='assigned'&&$r['status']==='not_submitted'))===1,'preflight identity change is explicitly not submitted: '.$label);
        $restore();$race->changed($raceEvent);
        coopNoticeCheck(count($sent)-$before===3,'restored identity cannot blindly resend an already claimed event: '.$label);
    }
    $staleId=$newLead($site,$publisher,$receiver);$before=count($sent);$staled=false;
    $stale=new CooperationNoticeService(static function(array $payload)use(&$staled,$gateway,$site,$staleId,$outsider):bool {
        if(!$staled&&$payload['key']===NoticeConfigService::PHASES['assigned']) {$staled=true;Store::query('collaboration',$site)->where('id',$staleId)->update(['receiver_id'=>$outsider,'assignment_version'=>2]);}
        return $gateway->dispatch($payload);
    });
    $stale->changed(['site_id'=>$site,'lead_id'=>$staleId,'action'=>'assigned']);
    coopNoticeCheck($staled&&count($sent)-$before===3,'reassignment invalidates all in-flight notices for the previous receiver');
    coopNoticeCheck(count(array_filter(coopNoticeStates($site,$staleId),static fn(array $r):bool=>$r['phase']==='assigned'&&$r['status']==='not_submitted'))===1,'stale assignment stops further claims before provider submission');
    $identityId=$newLead($site,$publisher,$receiver);$before=count($sent);$identityChanged=false;
    $identityRace=new CooperationNoticeService(static function(array $payload)use(&$identityChanged,$gateway):bool {
        if(!$identityChanged&&$payload['key']===NoticeConfigService::PHASES['assigned']) {$identityChanged=true;Db::name('member')->where('member_id',102)->update(['weapp_openid'=>'']);}
        return $gateway->dispatch($payload);
    });
    $identityRace->changed(['site_id'=>$site,'lead_id'=>$identityId,'action'=>'assigned']);
    coopNoticeCheck($identityChanged&&count($sent)-$before===5,'removing the miniapp identity after claim blocks that channel, not the remaining legal channels');
    coopNoticeCheck(count(array_filter(coopNoticeStates($site,$identityId),static fn(array $r):bool=>$r['phase']==='assigned'&&$r['channel']==='weapp'&&$r['status']==='not_submitted'))===1,'lost in-flight channel identity is known not submitted');
    Db::name('member')->where('member_id',102)->update(['weapp_openid'=>'fixture-weapp-102']);
    $failedId=$newLead($site,$publisher,0,'following');$failure=new CooperationNoticeService(static fn(array $payload)=>throw new RuntimeException('Synthetic sender failure'));
    $failureEvent=['site_id'=>$site,'lead_id'=>$failedId,'action'=>'paid'];$failure->changed($failureEvent);$failure->changed($failureEvent);
    coopNoticeCheck(count(coopNoticeStates($site,$failedId))===3&&array_values(array_unique(array_column(coopNoticeStates($site,$failedId),'status')))===['uncertain'],'sender exception keeps one safe uncertain audit per channel, not an automatic retry');
    coopNoticeCheck(Store::row('collaboration',$site,$failedId)['status']==='following','notification failure never mutates business state');
    $falseId=$newLead($site,$publisher,0,'following');$unconfirmed=new CooperationNoticeService(static fn(array $payload):bool=>false);
    $unconfirmed->changed(['site_id'=>$site,'lead_id'=>$falseId,'action'=>'accepted']);
    coopNoticeCheck(array_values(array_unique(array_column(coopNoticeStates($site,$falseId),'status')))===['uncertain'],'a false sender result is uncertain, not successful delivery');

    // A shop is one entity with several independently authenticated accounts.
    // Valid employees receive their own channel claim, never the owner's to/id.
    foreach([105,106,107,108,109,110,111]as$memberId)Db::name('member')->insert(['site_id'=>$site,'member_id'=>$memberId,'nickname'=>'Synthetic staff '.$memberId,'mobile'=>'19900000'.$memberId,'weapp_openid'=>'staff-weapp-'.$memberId,'wx_openid'=>'staff-wechat-'.$memberId,'status'=>$memberId===110?0:1,'is_del'=>$memberId===111?1:0]);
    $link=static function(int$owner,int$shop,int$memberId,string$status='active'):void{Db::name('hsx_lead_merchant_member')->insert(['site_id'=>$owner,'merchant_id'=>$shop,'member_id'=>$memberId,'status'=>$status,'role'=>'member']);};
    $link($site,$publisher,105);$link($site,$receiver,106);$link($site,$receiver,102); // Duplicate owner association.
    $link($site,$receiver,107,'suspended');$link($site,$receiver,108,'removed');$link($site,$receiver,109,'pending');
    $link($site,$receiver,110);$link($site,$receiver,111);$link($site,$receiver,103); // Foreign member must fail current-tenant validation.
    $link($site+1,$receiver,104); // Same merchant number in another site is not an employee here.
    $staffId=$newLead($site,$publisher,$receiver,'following');$before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$staffId,'action'=>'accepted']);
    $staffSent=array_slice($sent,$before);$recipients=array_column(array_column($staffSent,'event'),'to');$recipientIds=array_column($recipients,'member_id');sort($recipientIds);
    coopNoticeCheck($recipientIds===[101,101,101,102,102,102,105,105,105,106,106,106],'both owners and active valid employees receive exactly one notice per channel');
    coopNoticeCheck(count(array_filter(coopNoticeStates($site,$staffId),static fn(array$r):bool=>in_array((int)$r['member_id'],[107,108,109],true)))===0,'suspended removed and unapproved employees receive no claims');
    coopNoticeCheck(count(array_filter(coopNoticeStates($site,$staffId),static fn(array$r):bool=>in_array((int)$r['member_id'],[103,110,111],true)&&$r['status']==='pending_recipient'))===9,'foreign disabled and deleted accounts are audited but never dispatched');
    $before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$staffId,'action'=>'accepted']);coopNoticeCheck(count($sent)===$before,'member-aware audit dedupe prevents owner or employee replay');
    foreach(['suspended','removed','pending']as$leaveStatus){
        $staffRaceId=$newLead($site,$publisher,$receiver,'following');$before=count($sent);$staffChanged=false;
        $staffRace=new CooperationNoticeService(static function(array$payload)use(&$staffChanged,$gateway,$site,$receiver,$leaveStatus,$listener):bool{
            $audit=Data::row('audit',$site,(int)$payload['data']['notice_audit_id']);$detail=Data::decode($audit['detail_json']);
            if(!$staffChanged&&(int)$detail['member_id']===106){
                $staffChanged=true;
                coopNoticeReject(static fn()=>$listener->handle(array_replace($payload,['key'=>NoticeConfigService::PHASES['assigned']])),'an employee audit cannot be consumed with another notice scene');
                Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',$receiver)->where('member_id',106)->update(['status'=>$leaveStatus]);
            }
            return$gateway->dispatch($payload);
        });
        $staffRace->changed(['site_id'=>$site,'lead_id'=>$staffRaceId,'action'=>'accepted']);
        $out=array_slice($sent,$before);$outIds=array_column(array_column(array_column($out,'event'),'to'),'member_id');
        coopNoticeCheck($staffChanged&&count($out)===9&&!in_array(106,$outIds,true),'leaving or suspended employee cannot reach sender after claiming: '.$leaveStatus);
        coopNoticeCheck(count(array_filter(coopNoticeStates($site,$staffRaceId),static fn(array$r):bool=>(int)$r['member_id']===106&&$r['status']==='not_submitted'))===1,'employee preflight rejection is known not submitted without automatic replay: '.$leaveStatus);
        Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',$receiver)->where('member_id',106)->update(['status'=>'active']);
        $staffRace->changed(['site_id'=>$site,'lead_id'=>$staffRaceId,'action'=>'accepted']);coopNoticeCheck(count($sent)-$before===9,'restoring employee does not automatically resubmit a claimed notice');
    }
    $internalId=$newLead($site,$publisher,$publisher);$before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$internalId,'action'=>'assigned']);
    $internalSent=array_slice($sent,$before);coopNoticeCheck(count($internalSent)===6,'same-entity assignment sends one notice per owner/employee/channel, not publisher plus receiver duplicates');
    coopNoticeCheck(count(array_filter($internalSent,static fn(array$r):bool=>$r['event']['key']===NoticeConfigService::PHASES['assigned']))===6,'same-entity assigned event has one consistent actionable template');
    Store::query('collaboration',$site)->where('id',$internalId)->update(['status'=>'paid']);$before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$internalId,'action'=>'internal_completed']);
    $internalDone=array_slice($sent,$before);coopNoticeCheck(count($internalDone)===6,'same-store completion notifies each valid account once');
    foreach($internalDone as$done)if($done['channel']!=='sms')coopNoticeCheck($done['event']['vars']['status_text']==='已完成','internal free completion never claims paid commission');
    // New person-directed handoffs do not broadcast an actionable assignment to every staff member.
    $link($site,$publisher,109);
    $directedId=$newLead($site,$publisher,$publisher);Store::query('collaboration',$site)->where('id',$directedId)->update(['rule_json'=>Data::json(['internal_delivery'=>true,'internal_publisher_member_id'=>101,'internal_assignee_member_id'=>105])]);
    $before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$directedId,'action'=>'assigned']);$directed=array_slice($sent,$before);
    coopNoticeCheck(count($directed)===6,'new internal handoff only notifies publisher actor and selected recipient, not a third active colleague');
    foreach($directed as$notice){$target=(int)$notice['event']['to']['member_id'];coopNoticeCheck(in_array($target,[101,105],true)&&$notice['event']['key']===NoticeConfigService::PHASES[$target===105?'assigned':'changed'],'only selected member receives actionable assigned template');}
    foreach(['reject'=>'selecting','withdraw'=>'selecting','cancel'=>'cancelled'] as $returnAction=>$returnStatus) {
        $returnedId=$newLead($site,$publisher,$publisher);Store::query('collaboration',$site)->where('id',$returnedId)->update(['receiver_id'=>0,'status'=>$returnStatus,'assignment_version'=>2,'rule_json'=>Data::json(['internal_delivery'=>true,'internal_publisher_member_id'=>101,'internal_assignee_member_id'=>105])]);
        $before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$returnedId,'action'=>$returnAction]);$returned=array_slice($sent,$before);
        coopNoticeCheck(count($returned)===6,'returned internal assignment notifies two actors only: '.$returnAction);
        foreach($returned as$notice)coopNoticeCheck(in_array((int)$notice['event']['to']['member_id'],[101,105],true)&&$notice['event']['key']===NoticeConfigService::PHASES['changed'],'reject/withdraw/cancel does not become a broadcast or actionable new assignment');
    }
    $directedRace=$newLead($site,$publisher,$publisher);Store::query('collaboration',$site)->where('id',$directedRace)->update(['rule_json'=>Data::json(['internal_delivery'=>true,'internal_publisher_member_id'=>101,'internal_assignee_member_id'=>105])]);
    $before=count($sent);$removed=false;$directedNotices=new CooperationNoticeService(static function(array$payload)use(&$removed,$gateway,$site,$publisher):bool{
        $audit=Data::row('audit',$site,(int)$payload['data']['notice_audit_id']);$state=Data::decode($audit['detail_json']);
        if(!$removed&&(int)$state['member_id']===105){$removed=true;Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',$publisher)->where('member_id',105)->update(['status'=>'removed']);}
        return$gateway->dispatch($payload);
    });$directedNotices->changed(['site_id'=>$site,'lead_id'=>$directedRace,'action'=>'assigned']);
    coopNoticeCheck($removed&&count($sent)-$before===3,'selected member removed after claim receives no in-flight notification, publisher progress is retained');
    Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',$publisher)->where('member_id',105)->update(['status'=>'active']);
    Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',$publisher)->where('member_id',109)->update(['status'=>'removed']);

    // Cross-store returns use the persisted assignment audit, not the cleared
    // receiver_id or an unversioned receiver supplied by a queued payload.
    $returnedLead=static function(string $action,string $returnAction='reject')use($newLead,$site,$publisher,$receiver):int {
        $leadId=$newLead($site,$publisher,$receiver);
        Data::audit($site,0,101,'collaboration',$leadId,'assigned',['receiver_id'=>$receiver,'assignment_version'=>1]);
        $return=$action==='cancel'?$returnAction:$action;
        Data::audit($site,0,$return==='reject'?102:101,'collaboration',$leadId,$return,['merchant_id'=>$return==='reject'?$receiver:$publisher]);
        $values=['receiver_id'=>0,'status'=>'selecting','assignment_version'=>2];
        if($action==='cancel'){Data::audit($site,0,101,'collaboration',$leadId,'cancel',['merchant_id'=>$publisher]);$values=['receiver_id'=>0,'status'=>'cancelled','assignment_version'=>3];}
        Store::query('collaboration',$site)->where('id',$leadId)->update($values);
        return$leadId;
    };
    foreach(['accept'=>'following','deal'=>'unpaid','paid'=>'paid','no_deal'=>'no_deal','cancel'=>'cancelled']as$action=>$status){
        $bothId=$newLead($site,$publisher,$receiver,$status);$before=count($sent);
        $notices->changed(['site_id'=>$site,'lead_id'=>$bothId,'action'=>$action,'assignment_version'=>1,'lead_status'=>$status]);
        $both=array_slice($sent,$before);$ids=array_column(array_column(array_column($both,'event'),'to'),'member_id');sort($ids);
        coopNoticeCheck($ids===[101,101,101,102,102,102,105,105,105,106,106,106],'current cross-store progress reaches both stores: '.$action);
        foreach($both as$notice)coopNoticeCheck($notice['event']['key']===NoticeConfigService::PHASES['changed'],'progress does not masquerade as a new assignment: '.$action);
    }
    foreach(['reject','withdraw','cancel']as$action){
        $returnedId=$returnedLead($action);$before=count($sent);$queue=CooperationNoticeOutbox::record($site,$returnedId,$action);
        coopNoticeCheck(CooperationNoticeOutbox::process($site,$queue,$dispatch),'current return outbox is processed: '.$action);
        $out=array_slice($sent,$before);$ids=array_column(array_column(array_column($out,'event'),'to'),'member_id');sort($ids);
        coopNoticeCheck($ids===[101,101,101,102,102,102,105,105,105,106,106,106],'cleared cross-store receiver still receives all three status channels: '.$action);
        foreach($out as$notice){
            coopNoticeCheck($notice['event']['key']===NoticeConfigService::PHASES['changed'],'returned receivers never receive actionable assignments: '.$action);
            if($notice['channel']==='sms')continue;
            $isPrevious=in_array((int)$notice['event']['to']['member_id'],[102,106],true);
            coopNoticeCheck(str_contains($notice['event']['vars']['__weapp_page'],'collaboration_id=')!==$isPrevious
                &&str_contains($notice['event']['vars']['url'],'collaboration_id=')!==$isPrevious,'only current publisher retains a customer-record deep link: '.$action);
        }
        foreach([102,106]as$memberId)coopNoticeReject(static fn()=>(new CooperationService(new Context($site,0,$memberId)))->authorized($returnedId),'return notice does not restore previous receiver customer access: '.$action);
        $notices->changed(['site_id'=>$site,'lead_id'=>$returnedId,'action'=>$action,'assignment_version'=>$action==='cancel'?3:2,'lead_status'=>$action==='cancel'?'cancelled':'selecting']);
        coopNoticeCheck(count($sent)-$before===12,'replayed current return never resubmits a claimed recipient/channel: '.$action);
        $oldCount=count(coopNoticeStates($site,$returnedId));
        $notices->changed(['site_id'=>$site,'lead_id'=>$returnedId,'action'=>'assigned','assignment_version'=>1,'lead_status'=>'assigned','receiver_id'=>$outsider]);
        coopNoticeCheck(count(coopNoticeStates($site,$returnedId))===$oldCount,'old queued assignment and payload receiver cannot create new claims: '.$action);
    }
    $withdrawClosed=$returnedLead('cancel','withdraw');$before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$withdrawClosed,'action'=>'cancel']);
    coopNoticeCheck(count($sent)-$before===12,'closing after withdrawal also reaches its exact previous receiver');

    $wrongVersion=$returnedLead('reject');Store::query('collaboration',$site)->where('id',$wrongVersion)->update(['assignment_version'=>8]);
    $before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$wrongVersion,'action'=>'reject']);
    coopNoticeCheck(count($sent)-$before===6,'unmatched old assignment audit never invents a former receiving party');
    $newReceiver=$returnedLead('reject');
    Data::audit($site,0,101,'collaboration',$newReceiver,'assigned',['receiver_id'=>$outsider,'assignment_version'=>3]);
    Data::audit($site,0,104,'collaboration',$newReceiver,'reject',['merchant_id'=>$outsider]);
    Store::query('collaboration',$site)->where('id',$newReceiver)->update(['assignment_version'=>4]);
    $before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$newReceiver,'action'=>'reject']);$out=array_slice($sent,$before);
    $ids=array_column(array_column(array_column($out,'event'),'to'),'member_id');sort($ids);
    coopNoticeCheck($ids===[101,101,101,104,104,104,105,105,105],'a second return uses only the latest exact-version receiver, never the earlier shop');

    $returnedRace=$returnedLead('withdraw');$before=count($sent);$reassigned=false;
    $returnedNotices=new CooperationNoticeService(static function(array$payload)use(&$reassigned,$site,$returnedRace,$outsider,$gateway):bool{
        $state=Data::decode(Data::row('audit',$site,(int)$payload['data']['notice_audit_id'])['detail_json']);
        if(!$reassigned&&(int)$state['member_id']===102){$reassigned=true;Store::query('collaboration',$site)->where('id',$returnedRace)->update(['receiver_id'=>$outsider,'status'=>'assigned','assignment_version'=>3]);}
        return$gateway->dispatch($payload);
    });$returnedNotices->changed(['site_id'=>$site,'lead_id'=>$returnedRace,'action'=>'withdraw','assignment_version'=>2,'lead_status'=>'selecting']);
    coopNoticeCheck($reassigned&&count($sent)-$before===6,'reassignment during former receiver claim blocks all old receiver sends and further stale claims');

    $ambiguous=(int)Db::name('hsx_lead_merchant_member')->insertGetId(['site_id'=>$site,'merchant_id'=>$outsider,'member_id'=>106,'status'=>'active','role'=>'member']);
    $ambiguousId=$newLead($site,$publisher,$receiver);$before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$ambiguousId,'action'=>'assigned']);
    coopNoticeCheck(count($sent)-$before===9&&count(array_filter(coopNoticeStates($site,$ambiguousId),static fn(array$s):bool=>(int)$s['member_id']===106&&$s['status']==='pending_recipient'))===3,'ambiguous multi-merchant member cannot receive an actionable assignment');
    Db::name('hsx_lead_merchant_member')->where('id',$ambiguous)->delete();
    $ambiguousRace=$newLead($site,$publisher,$receiver);$before=count($sent);$ambiguousLink=0;
    $ambiguousNotices=new CooperationNoticeService(static function(array$payload)use(&$ambiguousLink,$site,$outsider,$gateway):bool{
        $state=Data::decode(Data::row('audit',$site,(int)$payload['data']['notice_audit_id'])['detail_json']);
        if(!$ambiguousLink&&(int)$state['member_id']===106)$ambiguousLink=(int)Db::name('hsx_lead_merchant_member')->insertGetId(['site_id'=>$site,'merchant_id'=>$outsider,'member_id'=>106,'status'=>'active','role'=>'member']);
        return$gateway->dispatch($payload);
    });$ambiguousNotices->changed(['site_id'=>$site,'lead_id'=>$ambiguousRace,'action'=>'assigned']);
    coopNoticeCheck($ambiguousLink>0&&count($sent)-$before===9,'multi-merchant binding created after claim is rechecked before every framework sender');
    Db::name('hsx_lead_merchant_member')->where('id',$ambiguousLink)->delete();
    $formerDisabled=$returnedLead('reject');Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',$receiver)->where('member_id',106)->update(['status'=>'suspended']);
    $before=count($sent);$notices->changed(['site_id'=>$site,'lead_id'=>$formerDisabled,'action'=>'reject']);
    coopNoticeCheck(count($sent)-$before===9,'previous receiver snapshot does not retain a suspended employee notification right');
    Db::name('hsx_lead_merchant_member')->where('site_id',$site)->where('merchant_id',$receiver)->where('member_id',106)->update(['status'=>'active']);

    $keyRaceId=$newLead($site,$publisher,0,'following');$before=count($sent);$keyChanged=false;
    $keyRace=new CooperationNoticeService(static function(array$payload)use(&$keyChanged,$gateway,$site):bool{
        if(!$keyChanged){$keyChanged=true;Db::name('sys_notice')->where('site_id',$site)->where('key',$payload['key'])->update(['is_weapp'=>0]);}
        return$gateway->dispatch($payload);
    });
    $keyRace->changed(['site_id'=>$site,'lead_id'=>$keyRaceId,'action'=>'accepted']);
    coopNoticeCheck($keyChanged&&count($sent)-$before===4,'disabling channel after claim blocks in-flight weapp without stopping other channels');
    $staffSafe=Data::json(['sent'=>array_slice($sent,-30),'audit'=>coopNoticeStates($site,$staffId)]);
    foreach(['HiddenCustomerMarker','18800009999','HiddenModelMarker','HiddenRemarkMarker']as$secret)coopNoticeCheck(!str_contains($staffSafe,$secret),'staff notices do not expose customer detail: '.$secret);

    // Recipient identities are an admin-only projection, not extra personal
    // data in the shared member workbench or queue rows.
    $labelLead=$newLead($site,$publisher,$receiver,'following');
    foreach([101=>$publisher,105=>$publisher,102=>$receiver,106=>$receiver]as$memberId=>$merchantId)foreach(['weapp','wechat','sms']as$labelChannel)Data::audit($site,0,0,'collaboration_notice',$labelLead,'notice_event',['channel'=>$labelChannel,'status'=>'submitted','message'=>'Synthetic admin projection fixture','merchant_id'=>$merchantId,'member_id'=>$memberId]);
    CooperationNoticeOutbox::record($site,$labelLead,'accepted');
    Db::name('member')->where('site_id',$site)->where('member_id',105)->update(['nickname'=>'','member_no'=>'STAFF_105']);
    Data::query('merchant',$site)->where('id',$receiver)->update(['name'=>'']);
    Data::audit($site,0,0,'collaboration_notice',$labelLead,'notice_event',['channel'=>'weapp','status'=>'pending_recipient','message'=>'Synthetic foreign binding','merchant_id'=>$foreign,'member_id'=>103]);
    $plain=CooperationNoticeService::statusForLead($site,$labelLead);$adminNotices=CooperationNoticeService::statusForLead($site,$labelLead,true);
    coopNoticeCheck(count($plain)===14&&count($adminNotices)===14,'recipient projection preserves the bounded original notice list and queue rows');
    foreach($plain as$row)coopNoticeCheck(!array_intersect(['recipient_label','recipient_member_id','recipient_merchant_id'],array_keys($row)),'default projection never exposes another recipient identity');
    foreach($adminNotices as$row){
        if($row['channel']==='queue'){coopNoticeCheck(!array_intersect(['recipient_label','recipient_member_id','recipient_merchant_id'],array_keys($row)),'queue item has no fabricated recipient');continue;}
        coopNoticeCheck(isset($row['recipient_label'],$row['recipient_member_id'],$row['recipient_merchant_id']),'admin channel row identifies the intended merchant and account');
        if($row['recipient_member_id']===101)coopNoticeCheck($row['recipient_label']==='Publisher · Fixture 101','admin projection uses same-site merchant name and account nickname');
        if($row['recipient_member_id']===105)coopNoticeCheck($row['recipient_label']==='Publisher · STAFF_105','blank account nickname falls back to member number');
        if($row['recipient_member_id']===102)coopNoticeCheck($row['recipient_label']==='门店#'.$receiver.' · Fixture 102','blank merchant name falls back to its numeric identity');
        if($row['recipient_member_id']===103)coopNoticeCheck($row['recipient_label']==='门店#'.$foreign.' · 账号#103','foreign identities never resolve names across the site boundary');
    }
    $encoded=Data::json($adminNotices);
    foreach(['mobile','openid','19900000101','fixture-weapp-101','Other-site merchant','Fixture 103']as$private)coopNoticeCheck(!str_contains($encoded,$private),'admin recipient labels expose no contacts or foreign names: '.$private);
    $adminDetail=(new CooperationService(new Context($site,1)))->detail($labelLead);
    coopNoticeCheck($adminDetail['notice']===[],'ordinary admin detail does not bypass dedicated notice-list permission');
    $ledger=(new CooperationNoticeReadService(new Context($site,1)))->lists($labelLead,['limit'=>100]);
    coopNoticeCheck($ledger['total']===14&&count(array_filter($ledger['data'],static fn(array$row):bool=>$row['channel']!=='queue'&&isset($row['recipient_label'])))===13,'dedicated authorized ledger contains paginated recipient identities');
    $memberDetail=(new CooperationService(new Context($site,0,101)))->detail($labelLead);
    coopNoticeCheck(count(array_filter($memberDetail['notice'],static fn(array$row):bool=>isset($row['recipient_label'])||isset($row['recipient_member_id'])||isset($row['recipient_merchant_id'])))===0,'actual member detail retains the identity-free notice projection');

    $db=Db::getConfig();$db['connections'][$db['default']]['trigger_sql']=true;Db::setConfig($db);Db::connect($db['default'],true);Db::query('SELECT 1');
    $queries=[];Db::listen(static function(string$sql)use(&$queries):void{$queries[]=$sql;});
    CooperationNoticeService::statusForLead($site,$labelLead,true);
    $memberReads=array_values(array_filter($queries,static fn(string$sql):bool=>preg_match('/^SELECT\b/i',$sql)&&str_contains($sql,'`lt_member`')));
    $merchantReads=array_values(array_filter($queries,static fn(string$sql):bool=>preg_match('/^SELECT\b/i',$sql)&&str_contains($sql,'`lt_hsx_lead_merchant`')));
    coopNoticeCheck(count($memberReads)===1&&count($merchantReads)===1,'all admin labels use exactly one bounded member read and one merchant read, not N+1 queries');
    coopNoticeCheck(!preg_match('/mobile|openid|SELECT\s+\*/i',implode(' ',array_merge($memberReads,$merchantReads))),'batched admin identity queries select only whitelisted name and number columns');
    $queries=[];CooperationNoticeService::statusForLead($site,$labelLead);
    coopNoticeCheck(count(array_filter($queries,static fn(string$sql):bool=>str_contains($sql,'`lt_member`')||str_contains($sql,'`lt_hsx_lead_merchant`')))===0,'ordinary projection performs no recipient identity lookup');
    leadAssertIsolated($schema);
    echo "PASS cooperation notice: $checks checks; random local schema, framework sender double, no external requests or real notifications\n";
} catch(Throwable $error) {
    $failed=true;fwrite(STDERR,'FAIL cooperation notice: '.$error->getMessage()."\n".$error->getFile().':'.$error->getLine()."\n");
} finally {
    if($pdo instanceof PDO&&$schema!=='') leadCleanup($pdo,$schema);
}
exit($failed?1:0);
