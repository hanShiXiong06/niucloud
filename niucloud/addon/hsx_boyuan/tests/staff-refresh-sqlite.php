<?php
declare(strict_types=1);
/** Fresh SQLite :memory:, deterministic upstream; never loads site configuration or contacts a vendor. */
namespace addon\hsx_boyuan\app\service {
    final class ConfigService {
        public static $settings=[];
        public function installed(int $site): bool {return true;}
        public function get(int $site,bool $safe): array {return self::$settings;}
        public function transport(...$unused) {throw new \RuntimeException('no live transport allowed');}
    }
    final class TaskService {public static $log=[];public static function audit(...$args):void{self::$log[]=$args;}}
}
namespace {
    use addon\hsx_boyuan\app\service\{ConfigService,ReferenceService,TaskService};
    use addon\hsx_boyuan\app\gateway\OpenApi;
    use addon\hsx_boyuan\app\support\{Problem,Contract,Secret,ArchiveView,CatalogStore};
    use think\facade\Db;
    $root=dirname(__DIR__,3); require $root.'/vendor/autoload.php';require_once $root.'/vendor/topthink/framework/src/helper.php';
    $app=new \think\App($root);$app->config->set(['default'=>'test','connections'=>['test'=>['type'=>'sqlite','database'=>':memory:','prefix'=>'','fields_cache'=>false]]],'database');
    $app->config->set(['default'=>'file','stores'=>['file'=>['type'=>'File','path'=>sys_get_temp_dir().'/fixture-staff-cache/']]],'cache');
    $app->config->set(['default'=>'file','channels'=>['file'=>['type'=>'File','close'=>true,'path'=>sys_get_temp_dir().'/fixture-staff-log/']]],'log');
    $app->env->set('app.auth_key','TEST-AUTH-KEY');(new \think\service\ModelService($app))->boot();
    Db::execute('CREATE TABLE site(site_id INTEGER PRIMARY KEY)');
    Db::execute('CREATE TABLE hsx_boyuan_reference_sync(id INTEGER PRIMARY KEY,site_id INTEGER,connection_key TEXT,mode TEXT,resource TEXT,status TEXT,run_key TEXT)');
    Db::execute('CREATE TABLE hsx_boyuan_md_staff(id INTEGER PRIMARY KEY,site_id INTEGER,connection_key TEXT,external_id TEXT,name TEXT,code TEXT,enabled INTEGER,phone_masked TEXT,phone_hash TEXT,update_at INTEGER,seen_run TEXT,extra_json TEXT)');
    Db::name('site')->insert(['site_id'=>13]);$key='fixture';$company='C1';
    $p=['protocol'=>'openapi_v153','gateway_path'=>'/gateway.do','probe_at'=>1,'connection_key'=>$key,'endpoint'=>'https://open-api-uat.139erp.com/api','account_id'=>'fixture-account','app_id'=>'fixture-app'];
    ConfigService::$settings=['revision'=>4,'mode'=>'test','profiles'=>['test'=>$p]];
    $job=['mode'=>'test','resource'=>'staff','site_id'=>13,'connection_key'=>$key,'status'=>'completed','run_key'=>'batch1'];
    Db::name('hsx_boyuan_reference_sync')->insert($job);
    $old=['site_id'=>13,'connection_key'=>$key,'external_id'=>'E1','name'=>'before','code'=>'before','enabled'=>1,'update_at'=>1,'seen_run'=>'batch1',
        'extra_json'=>Contract::json(['company_id'=>$company,'source_page'=>3,'is_buyer'=>0,'is_sales'=>1])];
    foreach([[] , ['external_id'=>'E2'],['connection_key'=>'other'],['site_id'=>14]] as $patch) CatalogStore::query('staff')->insert(array_replace($old,$patch));
    $q=fn() => CatalogStore::query('staff')->where(['site_id'=>13,'connection_key'=>$key,'external_id'=>'E1']);
    $full=['id'=>'E1','company_id'=>$company,'login_name'=>'employee','real_name'=>'new name','is_buyer'=>1,'is_effective'=>1,'is_sales'=>1,
        'mobile_phone'=>'13900000001','password'=>'fixture-private-value'];
    $reads=0;$checked=0;$check=static function(bool $ok,string $desc)use(&$checked){if(!$ok)throw new \RuntimeException($desc);++$checked;};
    $try=static function(array $rows,$total,?callable $between=null,string $expectedKey='fixture',string $mode='test',string $id='E1')use($p,&$reads,$check) {
        $api=new OpenApi($p,'TEST-ONLY',function($path,$f)use($rows,$total,$between,&$reads,$check,$id){
            $check($path==='/gateway.do' && $f['service']==='boyuan.users.get' && $f['id']===$id && $f['page_no']===1 && $f['page_size']===1,'only exact signed employee lookup');
            ++$reads;if($between)$between();return ['return_code'=>'SUCCESS','api_response'=>['result_list'=>$rows,'total_results'=>$total]];
        },'test');
        return (new ReferenceService())->refreshStaff(13,1,$id,$expectedKey,$mode,$api);
    };
    $before=CatalogStore::query('staff')->where('id','>',1)->order('id')->select()->toArray();
    $reply=$try([$full],1);$row=$q()->find();$extra=Contract::decode($row['extra_json']);
    $snap=Contract::decode(Secret::open($extra[ArchiveView::SNAPSHOT],'TEST-AUTH-KEY',13,ArchiveView::context($key,'staff','E1')));
    $check($row['seen_run']==='batch1' && $extra['source_page']===3 && $row['name']===$snap['real_name'] && $extra['is_buyer']===$snap['is_buyer'] && $extra['is_sales']===1,'archive and selector consistent, run unchanged');
    $check($reply['is_buyer']===1 && $reply['external_id']==='E1' && $row['phone_masked']==='139****0001' && $row['phone_hash']!=='' && !str_contains(Contract::json([$row,$reply,$snap,TaskService::$log]),'fixture-private-value'),'no credential leaks');
    $check(CatalogStore::query('staff')->where('id','>',1)->order('id')->select()->toArray()===$before && Db::name('hsx_boyuan_reference_sync')->count()===1,'other employees, connections, sites and runs unchanged');
    $fails=static function($cb)use($q,$check){$prior=$q()->find();try{$cb();}catch(Problem $e){$check($q()->find()===$prior,'invalid response not applied');return;}throw new \RuntimeException('invalid refresh accepted');};
    foreach([['id'=>'other'],['company_id'=>'other'],['big_gsdm'=>'other'],['is_buyer'=>null],['is_effective'=>null],['is_sales'=>null]] as $change)
        $fails(fn()=>$try([array_replace($full,$change)],1));
    $fails(fn()=>$try([$full,$full],2));$fails(fn()=>$try([],0));
    $start=$reads;$fails(fn()=>$try([$full],1,null,'other'));$fails(fn()=>$try([$full],1,null,$key,'production'));$fails(fn()=>$try([$full],1,null,$key,'test','missing'));
    $check($reads===$start,'missing records, wrong key or mode fail before remote read');
    $fails(fn()=>$try([$full],1,static function(){++ConfigService::$settings['revision'];}));--ConfigService::$settings['revision'];
    $fails(fn()=>$try([$full],1,fn()=>Db::name('hsx_boyuan_reference_sync')->insert(array_replace($job,['run_key'=>'batch2','status'=>'queued']))));
    $check(count(TaskService::$log)===1,'only the successful change was audited');
    echo "$checked assertions passed, $reads fake single-staff reads; SQLite in memory only.\n";
}
