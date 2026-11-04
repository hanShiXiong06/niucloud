<?php
declare(strict_types=1);

// Random, process-owned localhost schema and framework sender double only.
if (PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true)) exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $function) if(function_exists($function)) exit("Disable network functions before isolated testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN)) exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';

use addon\hsx_lead\app\listener\notice\CooperationNoticeData;
use addon\hsx_lead\app\service\{FrameworkNoticeGateway,NoticeConfigService};
use addon\hsx_lead\app\service\cooperation\{CooperationNoticeService,Store};
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
    coopNoticeCheck(($events['listen']['NoticeData']??[])===[CooperationNoticeData::class],'current plugin registers the cooperation-only framework NoticeData listener');
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
    $states=coopNoticeStates($site,$disabled);
    coopNoticeCheck(count($states)===6&&array_unique(array_column($states,'status'))===['disabled'],'real assigned event creates six independent disabled-channel audit facts');
    event('HsxLeadCollaborationChanged',['site_id'=>$site,'lead_id'=>$disabled,'action'=>'assigned']);
    coopNoticeCheck(count(coopNoticeStates($site,$disabled))===6,'real business event is idempotent before any sender is configured');
    Store::query('collaboration',$site)->where('id',$disabled)->update(['status'=>'following']);
    event('HsxLeadCollaborationChanged',['site_id'=>$site,'lead_id'=>$disabled,'action'=>'accepted']);
    $changedStates=coopNoticeStates($site,$disabled);
    coopNoticeCheck(count($changedStates)===9&&array_column(array_slice($changedStates,-3),'phase')===['changed','changed','changed'],'real changed event follows the current cooperation listener, not retired task events');
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
    coopNoticeCheck(count($sent)-$before===3&&count(coopNoticeStates($site,$id))===9,'changed business state receives a separate audit and publisher notification');
    foreach(array_slice($sent,$before) as $row) coopNoticeCheck($row['event']['to']['member_id']===101&&$row['event']['key']===NoticeConfigService::PHASES['changed'],'follow-up does not notify an unrelated recipient');

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
        coopNoticeCheck(count(array_filter(coopNoticeStates($site,$raceId),static fn(array $r):bool=>$r['phase']==='assigned'&&$r['status']==='uncertain'))===1,'in-flight attempt retains explicit uncertain result instead of claiming delivery: '.$label);
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
    coopNoticeCheck(count(array_filter(coopNoticeStates($site,$staleId),static fn(array $r):bool=>$r['phase']==='assigned'&&$r['status']==='uncertain'))===3,'stale assignment is never marked submitted');
    $identityId=$newLead($site,$publisher,$receiver);$before=count($sent);$identityChanged=false;
    $identityRace=new CooperationNoticeService(static function(array $payload)use(&$identityChanged,$gateway):bool {
        if(!$identityChanged&&$payload['key']===NoticeConfigService::PHASES['assigned']) {$identityChanged=true;Db::name('member')->where('member_id',102)->update(['weapp_openid'=>'']);}
        return $gateway->dispatch($payload);
    });
    $identityRace->changed(['site_id'=>$site,'lead_id'=>$identityId,'action'=>'assigned']);
    coopNoticeCheck($identityChanged&&count($sent)-$before===5,'removing the miniapp identity after claim blocks that channel, not the remaining legal channels');
    coopNoticeCheck(count(array_filter(coopNoticeStates($site,$identityId),static fn(array $r):bool=>$r['phase']==='assigned'&&$r['channel']==='weapp'&&$r['status']==='uncertain'))===1,'lost in-flight channel identity is never marked submitted');
    Db::name('member')->where('member_id',102)->update(['weapp_openid'=>'fixture-weapp-102']);
    $failedId=$newLead($site,$publisher,0,'following');$failure=new CooperationNoticeService(static fn(array $payload)=>throw new RuntimeException('Synthetic sender failure'));
    $failureEvent=['site_id'=>$site,'lead_id'=>$failedId,'action'=>'paid'];$failure->changed($failureEvent);$failure->changed($failureEvent);
    coopNoticeCheck(count(coopNoticeStates($site,$failedId))===3&&array_values(array_unique(array_column(coopNoticeStates($site,$failedId),'status')))===['uncertain'],'sender exception keeps one safe uncertain audit per channel, not an automatic retry');
    coopNoticeCheck(Store::row('collaboration',$site,$failedId)['status']==='following','notification failure never mutates business state');
    $falseId=$newLead($site,$publisher,0,'following');$unconfirmed=new CooperationNoticeService(static fn(array $payload):bool=>false);
    $unconfirmed->changed(['site_id'=>$site,'lead_id'=>$falseId,'action'=>'accepted']);
    coopNoticeCheck(array_values(array_unique(array_column(coopNoticeStates($site,$falseId),'status')))===['uncertain'],'a false sender result is uncertain, not successful delivery');
    leadAssertIsolated($schema);
    echo "PASS cooperation notice: $checks checks; random local schema, framework sender double, no external requests or real notifications\n";
} catch(Throwable $error) {
    $failed=true;fwrite(STDERR,'FAIL cooperation notice: '.$error->getMessage()."\n".$error->getFile().':'.$error->getLine()."\n");
} finally {
    if($pdo instanceof PDO&&$schema!=='') leadCleanup($pdo,$schema);
}
exit($failed?1:0);
