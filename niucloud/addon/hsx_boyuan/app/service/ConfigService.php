<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\service;
use addon\hsx_boyuan\app\support\{Contract,Problem,Secret};
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
        $config = array_replace(['mode'=>'mock','enabled'=>false,'paused'=>true,'finance_enabled'=>false,'revision'=>0,'started_at'=>0,'assignee_uid'=>0,'profiles'=>[]],$rows[0]['value'] ?? []);
        foreach (self::MODES as $mode) {
            $saved=$config['profiles'][$mode]??[];
            $config['profiles'][$mode] = array_replace(['connection_key'=>'','endpoint'=>'','account_id'=>'','token_cipher'=>'','probe_at'=>0,'acceptance_ref'=>'','contract_confirmed'=>false,'scenario'=>'success',
                'protocol'=>$mode==='mock' || !empty($saved['token_cipher'])?'bridge_v1':'openapi_v153','app_id'=>'','app_secret_cipher'=>'','gateway_path'=>'/gateway.do','member_since'=>'2000-01-01','paths'=>[
                'documents'=>'/documents','operations'=>'/operations','references'=>'/reference-data','parties'=>'/parties/resolve',
                'financial'=>'/financial-items/ensure','balances'=>'/financial-items/search','settlements'=>'/settlements',
            ]],$config['profiles'][$mode] ?? []);
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
            $new['mode']=$mode; $new['enabled']=(bool)($input['enabled']??$old['enabled']); $new['paused']=(bool)($input['paused']??$old['paused']);
            $new['finance_enabled']=(bool)($input['finance_enabled']??$old['finance_enabled']);
            $new['assignee_uid']=max(0,(int)($input['assignee_uid']??$old['assignee_uid']));
            if ($new['assignee_uid']>0 && (!Db::name('sys_user_role')->where('site_id',$site)->where('uid',$new['assignee_uid'])->where('status',1)->count()
                || !Db::name('sys_user')->where('uid',$new['assignee_uid'])->where('status',1)->count())) throw new Problem('ASSIGNEE_INVALID','请选择本站有效的异常责任人');
            if ($new['enabled'] && !$new['paused'] && $mode!=='mock') {
                if($profile['protocol']==='openapi_v153') throw new Problem('OPENAPI_READ_ONLY','博远开放平台当前只接入基础资料，请保持业务投递暂停；基础资料同步可独立运行');
                if ($profile['endpoint']==='' || $profile['account_id']==='' || $profile['token_cipher']==='' || !$profile['probe_at'] || !$profile['contract_confirmed']) throw new Problem('PREFLIGHT_REQUIRED','请先保存授权、确认契约并完成只读账套核验');
                if (($input['confirm_account']??'')!==$profile['account_id']) throw new Problem('ACCOUNT_CONFIRM_REQUIRED','启用真实写入前请填写目标账套编号确认');
                if ($mode==='production' && trim($profile['acceptance_ref'])==='') throw new Problem('ACCEPTANCE_REQUIRED','正式启用前请登记真实测试验收单号');
            }
            $new['profiles'][$mode]=$profile; $new['revision']++;
            if ($new['enabled'] && (!$old['enabled'] || $old['mode']!==$mode || $changed)) $new['started_at']=time();
            $this->persist($site,$new);
            TaskService::audit($site,0,$profile['connection_key'],'config','saved','连接配置已保存，模式：'.$mode,$uid);
        });
        return $this->get($site);
    }
    public function persist(int $site,array $value): void
    {
        $row=SysConfig::where('site_id',$site)->where('config_key',self::KEY)->findOrEmpty();
        if ($row->isEmpty()) SysConfig::create(['site_id'=>$site,'config_key'=>self::KEY,'value'=>$value]); else $row->save(['value'=>$value]);
    }
    public function transport(int $site,array $profile,string $mode): Transport
    {
        if ($mode==='mock') return new MockTransport($site,$profile['connection_key'],$profile['scenario']);
        if(($profile['protocol']??'')==='openapi_v153') {
            if($profile['endpoint']==='' || $profile['app_id']==='' || $profile['account_id']==='' || $profile['app_secret_cipher']==='') throw new Problem('AUTH_INVALID','请先保存服务地址、AppID、AppSecret和账套代码');
            return new OpenApi($profile,Secret::open($profile['app_secret_cipher'],(string)env('app.auth_key',''),$site,$profile['connection_key'].':app_secret'));
        }
        return new HttpTransport($profile,Secret::open($profile['token_cipher'],(string)env('app.auth_key',''),$site,$profile['connection_key']));
    }
    public function probe(int $site,int $uid,?Transport $transport=null): array
    {
        $config=$this->get($site,false); $mode=$config['mode']; $p=$config['profiles'][$mode];
        $gateway=$transport??$this->transport($site,$p,$mode);
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
