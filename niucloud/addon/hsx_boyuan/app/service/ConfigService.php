<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\{Contract,InterfaceTrace,Problem,Secret};
use addon\hsx_boyuan\app\gateway\{Transport,HttpTransport,MockTransport,OpenApi};
use app\model\sys\SysConfig;
use app\service\core\site\CoreSiteService;
use think\facade\Db;
final class ConfigService
{
    public const KEY = 'HSX_BOYUAN_CONNECTION';
    public const MODES = ['mock','test','production'];
    public function installed(int $site): bool
    {
        $keys = (new CoreSiteService())->getAddonKeysBySiteId($site);
        return in_array('hsx_erp',$keys,true) && in_array('hsx_boyuan',$keys,true);
    }
    public function get(int $site, bool $public = true): array
    {
        $rows = SysConfig::where('site_id',$site)->where('config_key',self::KEY)->limit(2)->select()->toArray();
        if (count($rows)>1) throw new Problem('CONFIG_DUPLICATE','博远连接配置重复，请管理员核对');
        $config = array_replace(['mode'=>'mock','enabled'=>false,'paused'=>true,'finance_enabled'=>false,'purchase_enabled'=>false,'purchase_mode'=>'manual','purchase_started_at'=>0,'uat_payment_enabled'=>false,'revision'=>0,'started_at'=>0,'assignee_uid'=>0,'profiles'=>[]],$rows[0]['value'] ?? []);
        foreach (self::MODES as $mode) {
            $saved=$config['profiles'][$mode]??[];
            $config['profiles'][$mode] = array_replace(['connection_key'=>'','endpoint'=>'','account_id'=>'','token_cipher'=>'','probe_at'=>0,'acceptance_ref'=>'','contract_confirmed'=>false,'scenario'=>'success',
                'protocol'=>$mode==='mock' || !empty($saved['token_cipher'])?'bridge_v1':'openapi_v153','app_id'=>'','app_secret_cipher'=>'','gateway_path'=>'/gateway.do','member_since'=>'2000-01-01','party_phone_field'=>'contact_phone','paths'=>[
                'documents'=>'/documents','operations'=>'/operations','references'=>'/reference-data','parties'=>'/parties/resolve',
                'financial'=>'/financial-items/ensure','balances'=>'/financial-items/search','settlements'=>'/settlements',
            ]],$config['profiles'][$mode] ?? []);
            // This saved choice meant "use the agreed phone". Only translate the preference,
            // not existing archive rows or their indexed phone hashes.
            if ($config['profiles'][$mode]['party_phone_field']==='spell_code') $config['profiles'][$mode]['party_phone_field']='help_code';
            if ($public) {
                $config['profiles'][$mode]['token_configured'] = $config['profiles'][$mode]['token_cipher'] !== '';
                $config['profiles'][$mode]['app_secret_configured']=$config['profiles'][$mode]['app_secret_cipher']!=='';
                unset($config['profiles'][$mode]['token_cipher'],$config['profiles'][$mode]['app_secret_cipher']);
            }
        }
        if ($public) {
            $heartbeat=SysConfig::where('site_id',$site)->where('config_key',self::KEY.'_HEARTBEAT')->findOrEmpty();
            $config['worker_last_at']=$heartbeat->isEmpty()?0:(int)($heartbeat->value['at']??0);
        }
        return $config;
    }
    public function save(int $site, int $uid, array $input): array
    {
        if (!$this->installed($site)) throw new Problem('ADDON_MISSING','请先安装ERP及博远插件');
        Db::transaction(function () use ($site,$uid,$input): void {
            if (!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在');
            $old=$this->get($site,false);
            if ((int)($input['revision']??-1)!==(int)$old['revision']) throw new Problem('CONFIG_STALE','连接配置已被修改，请刷新');
            $mode=(string)($input['mode']??$old['mode']);
            if (!in_array($mode,self::MODES,true)) throw new Problem('MODE_INVALID','请选择模拟、测试或正式模式');
            $new=$old; $profile=$old['profiles'][$mode]; $edit=(array)($input['profile']??[]);
            foreach (['endpoint','account_id','acceptance_ref','scenario','protocol','app_id','gateway_path','member_since'] as $field) if (array_key_exists($field,$edit)) $profile[$field]=trim((string)$edit[$field]);
            $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$profile['member_since'],new \DateTimeZone('Asia/Shanghai'));
            if(!$date || $date->format('Y-m-d')!==$profile['member_since'] || $profile['member_since']<'1900-01-01' || $date>new \DateTimeImmutable('today',new \DateTimeZone('Asia/Shanghai'))) throw new Problem('DATE_INVALID','会员同步起始日期须为1900年至今天的有效日期');
            if(!in_array($profile['protocol'],['bridge_v1','openapi_v153'],true)) throw new Problem('PROTOCOL_INVALID','不支持的接口协议');
            if($profile['app_id']!=='' && !preg_match('/^[0-9]{1,32}$/D',$profile['app_id'])) throw new Problem('APP_ID_INVALID','AppID需为1至32位数字');
            if(!preg_match('#^/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.do$#D',$profile['gateway_path'])) throw new Problem('GATEWAY_INVALID','网关路径格式应为 /gateway.do');
            if (strlen($profile['account_id'])>128 || preg_match('/[\x00-\x20]/',$profile['account_id'])) throw new Problem('ACCOUNT_INVALID','账套编号无效');
            if ($profile['endpoint']!=='') $profile['endpoint']=HttpTransport::endpoint($profile['endpoint']);
            if (!in_array($profile['scenario'],['success','rejected','timeout_after_commit','partial','malformed'],true)) throw new Problem('SCENARIO_INVALID','不支持的模拟场景');
            $changed=$profile['endpoint']!==$old['profiles'][$mode]['endpoint'] || $profile['account_id']!==$old['profiles'][$mode]['account_id']
                || $profile['protocol']!==$old['profiles'][$mode]['protocol'] || $profile['app_id']!==$old['profiles'][$mode]['app_id'] || $profile['gateway_path']!==$old['profiles'][$mode]['gateway_path'];
            $phoneField=$edit['party_phone_field']??($changed?'contact_phone':$profile['party_phone_field']);
            if(!in_array($phoneField,['help_code','contact_phone'],true)) throw new Problem('PARTY_PHONE_FIELD','请刷新后重新选择电话来源：助记码或联系电话');
            $profile['party_phone_field']=$phoneField;
            if ($changed && $old['enabled'] && !$old['paused'] && $mode===$old['mode']) throw new Problem('PAUSE_REQUIRED','修改目标账套前请先暂停投递');
            if ($profile['connection_key']==='' || $changed) { $profile['connection_key']=bin2hex(random_bytes(16)); $profile['probe_at']=0; $profile['acceptance_ref']=''; $profile['token_cipher']=''; $profile['app_secret_cipher']=''; }
            if (isset($edit['paths'])) foreach ($edit['paths'] as $key=>$path) {
                if (!isset($profile['paths'][$key]) || !is_string($path) || !preg_match('#^/[a-zA-Z0-9_/-]+$#D',$path) || str_contains($path,'..') || str_contains($path,'//')) throw new Problem('PATH_INVALID','接口路径必须是普通相对路径');
                if ($path!==$profile['paths'][$key]) $profile['probe_at']=0;
                $profile['paths'][$key]=$path;
            }
            if (!empty($edit['token'])) {
                if (strlen($edit['token'])>4000 || preg_match('/[\r\n]/',$edit['token'])) throw new Problem('AUTH_INVALID','授权格式无效');
                $profile['token_cipher']=Secret::seal($edit['token'],(string)env('app.auth_key',''),$site,$profile['connection_key']); $profile['probe_at']=0;
            }
            if(!empty($edit['app_secret'])) {
                if(!is_string($edit['app_secret']) || !preg_match('/^[A-Za-z0-9]{32}$/D',$edit['app_secret'])) throw new Problem('APP_SECRET_INVALID','AppSecret需为博远提供的32位密钥');
                $profile['app_secret_cipher']=Secret::seal($edit['app_secret'],(string)env('app.auth_key',''),$site,$profile['connection_key'].':app_secret'); $profile['probe_at']=0;
            }
            if (array_key_exists('contract_confirmed',$edit)) $profile['contract_confirmed']=(bool)$edit['contract_confirmed'];
            if($mode==='production' && $profile['protocol']==='openapi_v153') {
                // The number must come from a verified UAT receipt of this site, not from a typed field.
                $prior=($changed || !empty($edit['app_secret']))?'':(string)($old['profiles'][$mode]['acceptance_ref']??'');
                $submitted=(string)($edit['acceptance_ref']??'');
                $profile['acceptance_ref']=$prior;
                $enabling=($input['enabled']??$old['enabled']) && !($input['paused']??$old['paused']) && ($input['purchase_enabled']??$old['purchase_enabled']);
                if($prior==='' && ($enabling || $submitted!=='')) {
                    $probe=$old; $probe['mode']=$mode; $probe['profiles'][$mode]=$profile;
                    $candidate=(new PurchaseReleaseReadiness())->inspect($site,$probe)['candidate'];
                    if($candidate && !$changed && empty($edit['app_secret'])) $profile['acceptance_ref']=$candidate['test_bill'];
                }
                if($submitted!=='' && $submitted!==$profile['acceptance_ref']) throw new Problem('ACCEPTANCE_REQUIRED','不能手填验收编号；只能使用本站已核验的测试采购回执');
            }
            $new['mode']=$mode; $new['enabled']=(bool)($input['enabled']??$old['enabled']); $new['paused']=(bool)($input['paused']??$old['paused']);
            $new['finance_enabled']=(bool)($input['finance_enabled']??$old['finance_enabled']);
            $new['purchase_enabled']=(bool)($input['purchase_enabled']??$old['purchase_enabled']);
            $new['purchase_mode']=(string)($input['purchase_mode']??$old['purchase_mode']);
            if (!in_array($new['purchase_mode'],['manual','automatic'],true)) throw new Problem('PURCHASE_MODE_INVALID','请选择人工确认或资料齐全自动同步');
            if ($mode!=='mock' && $profile['protocol']==='openapi_v153' && $new['finance_enabled']) throw new Problem('FINANCE_UNAVAILABLE','本版仅开放采购入库，不开放财务同步');
            $uat=$mode==='test' && $profile['protocol']==='openapi_v153' && $profile['endpoint']===PaymentSyncService::UAT_ENDPOINT && $profile['gateway_path']==='/gateway.do';
            $new['uat_payment_enabled']=(bool)($input['uat_payment_enabled']??$old['uat_payment_enabled']);
            if(!$uat && !empty($input['uat_payment_enabled'])) throw new Problem('PAYMENT_UAT_ONLY','付款登记实验开关仅允许精确UAT测试连接，不能用于正式环境');
            if(!$uat || $changed || $old['mode']!==$mode || !empty($edit['app_secret']) || !empty($edit['token'])) $new['uat_payment_enabled']=false;
            if($new['uat_payment_enabled']) {
                if(!$profile['probe_at'] || !$profile['contract_confirmed']) throw new Problem('PAYMENT_UAT_PREFLIGHT_REQUIRED','请先核验UAT账套并确认接口契约，再开启付款登记实验');
                if(!$old['uat_payment_enabled'] && ($input['confirm_account']??'')!==$profile['account_id']) throw new Problem('PAYMENT_UAT_ACCOUNT_CONFIRM_REQUIRED','开启UAT付款登记实验前，请填写目标账套编号确认；不开放真实转账或指定核销');
            }
            $new['assignee_uid']=max(0,(int)($input['assignee_uid']??$old['assignee_uid']));
            if ($new['assignee_uid']>0 && (!Db::name('sys_user_role')->where('site_id',$site)->where('uid',$new['assignee_uid'])->where('status',1)->count()
                || !Db::name('sys_user')->where('uid',$new['assignee_uid'])->where('status',1)->count())) throw new Problem('ASSIGNEE_INVALID','请选择本站有效的异常责任人');
            if ($new['enabled'] && !$new['paused'] && $mode!=='mock') {
                if($profile['protocol']==='openapi_v153') {
                    if(!$new['purchase_enabled']) throw new Problem('PURCHASE_DISABLED','请先开启采购入库；其他业务及财务仍不投递');
                    if ($profile['endpoint']==='' || $profile['account_id']==='' || $profile['app_id']==='' || $profile['app_secret_cipher']==='' || !$profile['probe_at'] || !$profile['contract_confirmed']) throw new Problem('PREFLIGHT_REQUIRED','请先保存开放平台授权、确认采购接口已开通并核验账套');
                } elseif ($profile['endpoint']==='' || $profile['account_id']==='' || $profile['token_cipher']==='' || !$profile['probe_at'] || !$profile['contract_confirmed']) throw new Problem('PREFLIGHT_REQUIRED','请先保存授权、确认契约并完成只读账套核验');
                if (($input['confirm_account']??'')!==$profile['account_id']) throw new Problem('ACCOUNT_CONFIRM_REQUIRED','启用真实写入前请填写目标账套编号确认');
                if ($mode==='production' && trim($profile['acceptance_ref'])==='') throw new Problem('ACCEPTANCE_REQUIRED','正式采购还没有上线依据。请到电脑端「博远 → 连接设置 → 正式采购同步」查看已核验的测试采购；不用预填本次正式单号。');
            }
            if($mode!==$old['mode'] || $changed || !empty($edit['app_secret']) || !empty($edit['token'])) unset($new['interface_trace']);
            if($changed) unset($profile['exchange_enabled_once']);
            if($changed || !empty($edit['app_secret']) || !$profile['contract_confirmed'] || $old['mode']!==$mode || $new['paused'] || !$new['enabled'] || !$new['purchase_enabled'] || $new['purchase_mode']!=='manual') {
                unset($profile['voucher_release'],$new['profiles']['production']['voucher_release']);
            }
            if(($new['enabled'] && !$new['paused']) || (!$changed && $old['mode']===$mode && !empty($old['enabled']) && !empty($old['profiles'][$mode]['probe_at'])))
                $profile['exchange_enabled_once']=true;
            $new['profiles'][$mode]=$profile; $new['revision']++;
            if ($new['enabled'] && (!$old['enabled'] || $old['mode']!==$mode || $changed)) $new['started_at']=time();
            if ($new['purchase_enabled'] && (!$old['purchase_enabled'] || !$old['enabled'] || $old['mode']!==$mode || $changed
                || $new['purchase_mode']!==$old['purchase_mode'])) $new['purchase_started_at']=time();
            $this->persist($site,$new);
            TaskService::audit($site,0,$profile['connection_key'],'config','saved','连接配置已保存，模式：'.$mode,$uid);
        });
        return $this->get($site);
    }
    /** The compact ERP admin control never exposes or accepts credentials or connection editing. */
    public function manualDeliveryState(int $site): array
    {
        $c=$this->get($site,false); $p=$c['profiles'][$c['mode']];
        $uat=$c['mode']==='test' && $p['protocol']==='openapi_v153' && $p['endpoint']===PaymentSyncService::UAT_ENDPOINT
            && $p['gateway_path']==='/gateway.do' && $p['connection_key']!=='';
        $reason=!$uat?'当前不是官方博远 UAT 开放平台连接。':(
            !$p['probe_at'] || !$p['contract_confirmed'] || !$p['app_secret_cipher'] || $p['app_id']==='' || $p['account_id']===''?'请先核验账套并确认接口已开通。':(
            $c['finance_enabled'] || $c['uat_payment_enabled']?'本站已启用财务投递，请在完整连接设置中管理；此处不会更改财务设置。':''));
        return ['eligible'=>$reason==='','reason'=>$reason,'revision'=>(int)$c['revision'],'connection_key'=>$uat?$p['connection_key']:'',
            'account_id'=>$uat?$p['account_id']:'','enabled'=>(bool)$c['enabled'],'paused'=>(bool)$c['paused'],
            'purchase_enabled'=>(bool)$c['purchase_enabled'],'purchase_mode'=>$c['purchase_mode']];
    }
    public function setManualDelivery(int $site,int $uid,array $input): array
    {
        if (array_diff(array_keys($input),['revision','connection_key','intent','confirm_account'])) throw new Problem('MANUAL_DELIVERY_FIELDS','此处只允许管理手动投递');
        $state=$this->manualDeliveryState($site);
        if(!$state['eligible']) throw new Problem('MANUAL_DELIVERY_UNAVAILABLE',$state['reason']);
        if(!is_numeric($input['revision']??null) || (int)$input['revision']!==$state['revision'] || ($input['connection_key']??null)!==$state['connection_key'])
            throw new Problem('CONFIG_STALE','连接配置已改变，请刷新后重新确认');
        $intent=$input['intent']??'';
        if(!in_array($intent,['enable','pause'],true)) throw new Problem('MANUAL_DELIVERY_INTENT','请选择开启或暂停手动投递');
        $enable=$intent==='enable';
        if($enable && (!is_string($input['confirm_account']??null) || $input['confirm_account']!==$state['account_id']))
            throw new Problem('ACCOUNT_CONFIRM_REQUIRED','请手动输入当前博远账套编号确认');
        $changes=['revision'=>$state['revision'],'enabled'=>$enable,'paused'=>!$enable,'purchase_enabled'=>$enable];
        if($enable) { $changes['purchase_mode']='manual'; $changes['confirm_account']=$input['confirm_account']; }
        // save locks the site and rechecks revision in the same transaction as the update.
        $this->save($site,$uid,$changes);
        return $this->manualDeliveryState($site);
    }

    public function persist(int $site,array $value): void
    {
        $row=SysConfig::where('site_id',$site)->where('config_key',self::KEY)->findOrEmpty();
        if ($row->isEmpty()) SysConfig::create(['site_id'=>$site,'config_key'=>self::KEY,'value'=>$value]); else $row->save(['value'=>$value]);
    }
    public static function traceState(array $config): array
    {
        $w=$config['interface_trace']??[]; $mode=$config['mode']; $key=$config['profiles'][$mode]['connection_key']??'';
        $matched=$mode!=='mock' && $key!=='' && ($w['mode']??'')===$mode && ($w['connection_key']??null)===$key
            && preg_match('/^BW[a-f0-9]{16}$/D',(string)($w['window_id']??''));
        $now=time(); $active=$matched && (int)($w['expires_at']??0)>$now;
        return ['window_id'=>$active?$w['window_id']:'','expires_at'=>$active?(int)$w['expires_at']:0,
            'last_window_id'=>$matched?$w['window_id']:'','server_time'=>$now];
    }
    public function setTrace(int $site,int $uid,array $in): array
    {
        if(!$this->installed($site)) throw new Problem('ADDON_MISSING','请先启用 ERP 和博远插件');
        return Db::transaction(function() use($site,$uid,$in) {
            if(!Db::name('site')->where('site_id',$site)->lock(true)->find()) throw new Problem('SITE_INVALID','站点不存在');
            $c=$this->get($site,false); $mode=$c['mode']; $key=$c['profiles'][$mode]['connection_key'];
            if($mode==='mock' || $key==='' || $mode!==($in['mode']??'') || $key!==($in['connection_key']??'') || (int)($in['revision']??-1)!==(int)$c['revision'])
                throw new Problem('CONFIG_STALE','请重新载入并选择已保存的博远连接');
            $prior=self::traceState($c); $intent=$in['intent']??'';
            if(!in_array($intent,['start','stop'],true) || $prior['window_id']!==($in['window_id']??''))
                throw new Problem('TRACE_STALE','调试状态已改变，请重新载入');
            if($intent==='start' && $prior['window_id']!=='') return $prior;
            if($intent==='stop' && isset($c['interface_trace'])) $c['interface_trace']['expires_at']=0;
            if($intent==='start') $c['interface_trace']=['mode'=>$mode,'connection_key'=>$key,'expires_at'=>time()+900,'window_id'=>'BW'.bin2hex(random_bytes(8))];
            // Same site lock as save/probe. Do not advance the business revision or change dispatch flags.
            $this->persist($site,$c);
            TaskService::audit($site,0,$key,'interface_trace',$intent,'接口日志'.($intent==='start'?'开启 15 分钟':'已关闭'),$uid);
            return self::traceState($c);
        });
    }
    public function trace(int $site,array $profile,string $mode,array $context=[]): InterfaceTrace
    {
        $key=$profile['connection_key']??'';
        $journal=($site>0 && $key!=='' && in_array($mode,['test','production'],true))
            ? static function(array $row)use($site,$mode,$key){
                try{\addon\hsx_boyuan\app\support\ExchangeJournal::record($site,$mode,$key,$row);}
                finally{\addon\hsx_boyuan\app\support\BusinessOperation::wire($site,$key,$row);}
            } : null;
        return new InterfaceTrace($site,$mode,$context,function() use($site,$mode,$key) {
            if($mode==='mock' || $key==='') return '';
            $c=$this->get($site,false);
            if($mode!==$c['mode'] || $key!==$c['profiles'][$mode]['connection_key']) return '';
            return self::traceState($c)['window_id'];
        },$journal,$journal ? static fn(array $meta,string $side,array $payload)=>\addon\hsx_boyuan\app\support\ExchangeJournal::recordDetail($site,$mode,$key,$meta,$side,$payload) : null);
    }
    public function traceTaskResult(int $site,string $connection,array $context): void
    {
        if($connection==='') return;
        $c=$this->get($site,false);
        if($connection!==$c['profiles'][$c['mode']]['connection_key'] || !($window=self::traceState($c)['window_id'])) return;
        (new InterfaceTrace($site,$c['mode'],$context,static fn()=>$window))->taskState($context);
    }
    /** Only wrap purely local preparation, never an operation which might already have called the vendor. */
    public static function localPreparation(int $site,array $context,callable $prepare): array
    {
        $outcome='local_prepared'; $code='';
        try {
            $result=$prepare();
            if(!empty($result['task_id'])) $context['task_id']=$result['task_id'];
            if(isset($result['view']['task']['business_no'])) $context['business_no']=$result['view']['task']['business_no'];
            return $result;
        } catch(\Throwable $e) { $outcome='blocked_before_send'; $code=$e instanceof Problem?$e->reason:'LOCAL_ERROR'; throw $e; }
        finally {
            try { $svc=new self(); $cfg=$svc->get($site,false); $svc->trace($site,$cfg['profiles'][$cfg['mode']],$cfg['mode'],$context)->local($outcome,$code); }
            catch(\Throwable $ignored) {}
        }
    }
    public function transport(int $site,array $profile,string $mode,array $context=[]): Transport
    {
        if ($mode==='mock') return new MockTransport($site,$profile['connection_key'],$profile['scenario']);
        $trace=$this->trace($site,$profile,$mode,$context);
        if(($profile['protocol']??'')==='openapi_v153') {
            if($profile['endpoint']==='' || $profile['app_id']==='' || $profile['account_id']==='' || $profile['app_secret_cipher']==='') throw new Problem('AUTH_INVALID','请先保存服务地址、AppID、AppSecret和账套代码');
            return new OpenApi($profile,Secret::open($profile['app_secret_cipher'],(string)env('app.auth_key',''),$site,$profile['connection_key'].':app_secret'),null,$mode,$trace);
        }
        return new HttpTransport($profile,Secret::open($profile['token_cipher'],(string)env('app.auth_key',''),$site,$profile['connection_key']),$trace);
    }
    public function probe(int $site,int $uid,?Transport $transport=null,?array $expected=null): array
    {
        $config=$this->get($site,false); $mode=$config['mode']; $p=$config['profiles'][$mode];
        if ($expected!==null) DesktopConnectionService::assertProbeTarget($config,$expected);
        $gateway=$transport??$this->transport($site,$p,$mode,['kind'=>'probe','operator_uid'=>$uid]);
        $reply=$gateway instanceof OpenApi?$gateway->verify():$gateway->send('GET',$p['paths']['references'],['resource'=>'warehouses','limit'=>1]);
        if (($reply['code']??'')!=='OK' || ($mode!=='mock' && ($reply['data']['account_id']??'')!==$p['account_id'])) throw new Problem('ACCOUNT_UNVERIFIED','未获得匹配的授权账套身份，不能开放真实写入');
        Db::transaction(function () use ($site,$uid,$mode,$config,$p): void {
            Db::name('site')->where('site_id',$site)->lock(true)->find(); $fresh=$this->get($site,false);
            if ($fresh['revision']!==$config['revision']) throw new Problem('CONFIG_STALE','核验期间配置已改变，请重新检查');
            $fresh['profiles'][$mode]['probe_at']=time(); $this->persist($site,$fresh);
            TaskService::audit($site,0,$p['connection_key'],'probe','verified',$mode==='mock'?'模拟连接检查通过，未访问博远':'只读账套身份核验通过，未写单',$uid);
        });
        return ['verified'=>true,'mode'=>$mode,'account_id'=>$p['account_id'],'write_performed'=>false];
    }
}
