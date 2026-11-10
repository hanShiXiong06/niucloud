<?php
declare(strict_types=1);

namespace addon\hsx_lead\scripts;

use app\dict\schedule\ScheduleDict;
use app\dict\addon\AddonDict;
use app\service\core\schedule\CoreScheduleInstallService;
use PDO;
use RuntimeException;
use think\facade\Db;
use Throwable;

/** Configure one plugin schedule. Never runs a job, touches an order or starts a worker. */
final class PaymentTimeoutScheduleCommand
{
    public const KEY='hsx_lead_unpaid_order_close';
    private const ADDON='hsx_lead';
    private const JOB='addon\\hsx_lead\\app\\job\\CloseUnpaidOrders';
    public function __construct(private string $backend,private string $plugin) {}

    public static function arguments(array $arguments): string
    {
        if(count($arguments)!==1||!in_array($arguments[0],['--check','--enable'],true)) {
            throw new RuntimeException('必须明确指定 --check（只读）或 --enable（仅启用未付订单关闭任务）');
        }
        return $arguments[0];
    }

    /** Enforced read-only transaction: no cache discovery or application/job initialization. */
    public function check(): array
    {
        Db::query('SELECT 1');$pdo=Db::connect()->getPdo();
        if(!$pdo instanceof PDO||$pdo->inTransaction()) throw new RuntimeException('预检需要独立数据库连接，不能混入已有事务');
        $pdo->exec('SET TRANSACTION READ ONLY');Db::startTrans();
        try {return $this->report($this->inspect(),'check');}
        finally {Db::rollback();}
    }

    public function enable(): array
    {
        $before=$this->inspect();$created=false;$changed=false;
        if($before['addon_status']!==AddonDict::ON) throw new RuntimeException('商户协作插件已停用，未启用未付订单关闭任务');
        Db::transaction(function()use(&$created,&$changed):void {
            // Same addon-row lock as the established plugin upgrader. Do not
            // scan-lock all sys_schedule rows or modify unrelated workers.
            $addon=Db::name('addon')->where('key',self::ADDON)->lock(true)->field('key,status')->find();
            if(!$addon) throw new RuntimeException('插件尚未安装，未注册计划任务');
            if((int)$addon['status']!==AddonDict::ON) throw new RuntimeException('商户协作插件已停用，未启用未付订单关闭任务');
            $state=$this->inspect();$row=$state['schedule'];
            if($row===null) {
                (new CoreScheduleInstallService())->install([$state['template']],self::ADDON);
                $created=true;$changed=true;$state=$this->inspect();$row=$state['schedule'];
            }
            if($row===null) throw new RuntimeException('目标任务注册后未能回读，当前事务已停止');
            $locked=Db::name('sys_schedule')->where('id',(int)$row['id'])->where('addon',self::ADDON)->where('key',self::KEY)->lock(true)->find();
            if(!$locked||(int)$locked['delete_time']>0) throw new RuntimeException('目标任务记录已变化，未继续启用');
            $time=self::time($locked['time']);$wanted=['type'=>'min','min'=>1];
            if((int)$locked['status']!==ScheduleDict::ON||$time!==$wanted) {
                Db::name('sys_schedule')->where('id',(int)$locked['id'])->where('addon',self::ADDON)->where('key',self::KEY)
                    ->update(['status'=>ScheduleDict::ON,'time'=>json_encode($wanted,JSON_THROW_ON_ERROR),'update_time'=>time()]);
                $changed=true;
            }
            $after=Db::name('sys_schedule')->where('id',(int)$locked['id'])->find();
            if(!$after||(int)$after['status']!==ScheduleDict::ON||self::time($after['time'])!==$wanted) throw new RuntimeException('目标任务设置未完整回读，事务已停止');
            foreach(['count','last_time','next_time','create_time'] as $field) {
                if((string)$after[$field]!== (string)$locked[$field]) throw new RuntimeException('任务计数或历史时间发生非预期变化，事务已停止');
            }
        });
        $after=$this->check();$after['mode']='enable';$after['registered_now']=$created;$after['changed']=$changed;
        $after['before']=$before['schedule']===null?null:self::scheduleView($before['schedule']);
        $after['result']='仅完成目标任务数据库注册/启用；未执行关单，尚未验证常驻调度已加载';
        return $after;
    }

    private function inspect(): array
    {
        foreach([$this->backend,$this->plugin] as $path) if(!is_dir($path)||realpath($path)!==$path) throw new RuntimeException('项目目录必须为真实绝对路径，不能使用符号链接根目录');
        $addon=Db::name('addon')->where('key',self::ADDON)->field('key,status')->find();
        if(!$addon) throw new RuntimeException('插件尚未安装，未注册计划任务');
        if(!is_file($this->plugin.'/app/dict/schedule/schedule.php')) throw new RuntimeException('插件任务模板文件缺失，未修改任务');
        $templates=require $this->plugin.'/app/dict/schedule/schedule.php';$matches=[];
        foreach($templates as $template) if(($template['key']??'')===self::KEY) $matches[]=$template;
        if(count($matches)!==1||($matches[0]['class']??'')!==self::JOB||($matches[0]['function']??'')!=='doJob'
            ||($matches[0]['time']??null)!==['type'=>'min','min'=>1]) throw new RuntimeException('未付订单关闭任务模板缺失或异常，未修改任务');
        $dependencies=[self::JOB=>'app/job/CloseUnpaidOrders.php',
            'addon\\hsx_lead\\app\\service\\cooperation\\PaymentOrderLifecycle'=>'app/service/cooperation/PaymentOrderLifecycle.php',
            'addon\\hsx_lead\\app\\service\\cooperation\\PaymentOrderRecovery'=>'app/service/cooperation/PaymentOrderRecovery.php',
            'addon\\hsx_lead\\app\\service\\cooperation\\PaymentGatewayReconciler'=>'app/service/cooperation/PaymentGatewayReconciler.php',
            'addon\\hsx_lead\\app\\service\\cooperation\\PaymentCloseScope'=>'app/service/cooperation/PaymentCloseScope.php'];
        foreach($dependencies as $class=>$file) if(!is_file($this->plugin.'/'.$file)||!class_exists($class)) throw new RuntimeException('支付安全依赖缺失或未能加载：'.$file.'；请先完整上传配套后端，未启用任务');
        if(!method_exists(self::JOB,'doJob')) throw new RuntimeException('关闭订单任务方法未能加载，未修改任务');
        $constant='addon\\hsx_lead\\app\\service\\cooperation\\PaymentOrderLifecycle::UNPAID_TIMEOUT_SECONDS';
        if(!defined($constant)||constant($constant)!==120) throw new RuntimeException('实际加载的支付超时实现不是120秒，请先上传正确的PaymentOrderLifecycle.php并核对项目路径，未启用任务');
        $rows=Db::name('sys_schedule')->where('key',self::KEY)->select()->toArray();
        foreach($rows as $row) if($row['addon']!==self::ADDON) throw new RuntimeException('任务标识与其他插件冲突，未修改任何计划任务');
        if(count($rows)>1) throw new RuntimeException('目标计划任务有重复记录，请先核对，未自动合并或删除');
        if($rows&&(int)$rows[0]['delete_time']>0) throw new RuntimeException('目标计划任务已删除，请先核对，未自动恢复');
        return ['template'=>$matches[0],'schedule'=>$rows[0]??null,'addon_status'=>(int)$addon['status']];
    }

    private static function time($raw): ?array
    {
        if(is_array($raw)) return $raw;
        if(!is_string($raw)||$raw==='') return null;
        try {$value=json_decode($raw,true,32,JSON_THROW_ON_ERROR);return is_array($value)?$value:null;}
        catch(Throwable $error) {return null;}
    }

    private static function scheduleView(array $row): array
    {
        $result=['id'=>(int)$row['id'],'addon'=>$row['addon'],'key'=>$row['key'],'status'=>(int)$row['status'],'time'=>self::time($row['time'])];
        foreach(['count','last_time','next_time','create_time','update_time'] as $key) $result[$key]=(int)$row[$key];
        return $result;
    }

    private function report(array $state,string $mode): array
    {
        $heartbeat=$this->backend.'/runtime/.schedule';$value=is_file($heartbeat)?trim((string)file_get_contents($heartbeat)):'';
        $at=ctype_digit($value)?(int)$value:null;$age=$at===null?null:time()-$at;$pidFiles=[];
        foreach(['workerman_queue.pid','workerman_schedule.pid'] as $name) {
            $path=$this->backend.'/runtime/'.$name;$raw=is_file($path)?trim((string)file_get_contents($path)):'';
            $pid=ctype_digit($raw)?(int)$raw:null;
            $pidFiles[]=['path'=>$path,'pid'=>$pid]+self::processView($pid);
        }
        $kinds=array_column($pidFiles,'command_kind');$combined=in_array('combined_workerman',$kinds,true);$separate=in_array('schedule_only',$kinds,true);
        $observed=$combined&&$separate?'multiple_scheduler_modes':($combined?'combined_workerman':($separate?'schedule_only':'unknown'));
        return ['mode'=>$mode,'backend_root'=>$this->backend,'project_root'=>dirname($this->backend),'addon_status'=>$state['addon_status'],
            'can_enable'=>$state['addon_status']===AddonDict::ON,'lifecycle_timeout_seconds'=>120,'payment_dependencies_loadable'=>true,
            'schedule'=>$state['schedule']===null?null:self::scheduleView($state['schedule']),
            'template'=>['key'=>self::KEY,'class'=>self::JOB,'function'=>'doJob','time'=>['type'=>'min','min'=>1]],
            'heartbeat'=>['path'=>$heartbeat,'at'=>$at,'age_seconds'=>$age,'recent'=>$age!==null&&$age>=0&&$age<90,
                'meaning'=>'仅反映调度心跳；不能证明本任务已加载或订单已关闭'],
            'pid_files'=>$pidFiles,'observed_worker_mode'=>$observed,'runtime_loaded'=>null,'job_executed'=>false,'schema_changed'=>false,'business_data_changed'=>false,
            'reload_required_after_enable'=>true,
            'reload_hint'=>'先用原守护进程确认实际项目和运行方式。合并进程：php think workerman status / reload；分离调度：php think cron:schedule status / reload。只重载既有方式，不要另启第二套；重启PHP-FPM不能证明CLI调度已重载。',
            'reload_commands'=>['cwd'=>$this->backend,'combined_workerman'=>'php think workerman reload','schedule_only'=>'php think cron:schedule reload',
                'warning'=>'使用网站相同PHP二进制和既有守护进程；模式unknown或多个调度器时先核对，不自动运行上述命令'],
            'verification_hint'=>'回读目标任务count/last_time及后续执行日志的examined/closed/blocked；blocked不是已取消。每分钟扫描，下单满2分钟起由后续轮次核对，不保证第120秒精确关闭。'];
    }

    /** Linux-only, read-only classification. Never returns raw arguments or environment values. */
    private static function processView(?int $pid): array
    {
        $result=['running_process_verified'=>false,'command_kind'=>'unknown'];
        if($pid===null||$pid<=0) return $result;
        $path='/proc/'.$pid.'/cmdline';
        if(!is_readable($path)) return $result;
        $raw=@file_get_contents($path);
        if(!is_string($raw)||$raw==='') return $result;
        $result['running_process_verified']=true;$arguments=explode("\0",rtrim($raw,"\0"));
        foreach($arguments as $index=>$argument) {
            if(basename($argument)!=='think') continue;
            $command=$arguments[$index+1]??'';
            if($command==='workerman') $result['command_kind']='combined_workerman';
            elseif($command==='cron:schedule') $result['command_kind']='schedule_only';
            elseif(in_array($command,['queue:listen','queue:work'],true)) $result['command_kind']='queue_only';
            break;
        }
        return $result;
    }
}

/** Minimal CLI initialization, without HTTP middleware, dictionary cache discovery or lifecycle hooks. */
function paymentTimeoutBootstrap(string $backend): void
{
    require_once $backend.'/vendor/autoload.php';$app=new \think\App($backend);$app->loadEnv();
    require_once $app->getThinkPath().'helper.php';require_once $backend.'/app/common.php';
    foreach(['app','database','cache','log'] as $name) {
        $path=$backend.'/config/'.$name.'.php';if(is_file($path)) $app->config->set(require $path,$name);
    }
    $database=$app->config->get('database');$connection=$database['default'];
    $database['connections'][$connection]['fields_cache']=false;$database['connections'][$connection]['trigger_sql']=false;
    $app->config->set($database,'database');$manager=new \think\DbManager();$manager->setConfig($database);$app->instance('think\DbManager',$manager);
    $app->instance('request',new \app\Request());date_default_timezone_set((string)$app->config->get('app.default_timezone','Asia/Shanghai'));
}

function paymentTimeoutMain(array $arguments): int
{
    if(PHP_SAPI!=='cli'||PHP_VERSION_ID<80000) {fwrite(STDERR,"仅支持网站对应的PHP 8.0及以上CLI。\n");return 1;}
    if(count($arguments)===1&&in_array($arguments[0],['--help','-h'],true)) {
        echo "仅配置商户协作未支付订单关闭任务；不执行关单、不修改其他任务。\nphp addon/hsx_lead/scripts/configure-payment-timeout.php --check\nphp addon/hsx_lead/scripts/configure-payment-timeout.php --enable\n";return 0;
    }
    try {
        $mode=PaymentTimeoutScheduleCommand::arguments($arguments);$plugin=dirname(__DIR__);$backend=dirname($plugin,2);
        paymentTimeoutBootstrap($backend);$command=new PaymentTimeoutScheduleCommand($backend,$plugin);
        $report=$mode==='--enable'?$command->enable():$command->check();
        echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n";return 0;
    } catch(Throwable $error) {
        $message=get_class($error)===RuntimeException::class?$error->getMessage():'框架或数据库检查失败；连接信息已隐藏，请核对网站目录、PHP环境和官方日志';
        fwrite(STDERR,'[未完成] '.$message."\n");return 1;
    }
}

if(!defined('HSX_LEAD_PAYMENT_TIMEOUT_LIBRARY_ONLY')) exit(paymentTimeoutMain(array_slice($_SERVER['argv']??[],1)));
