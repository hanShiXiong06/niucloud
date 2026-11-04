<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--isolated-local-mysql',$argv,true))exit("Requires --isolated-local-mysql\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect'] as $f)if(function_exists($f))exit("Disable external HTTP/network functions before isolated testing\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
require __DIR__.'/bootstrap.php';
use addon\hsx_lead\app\listener\BottomNavigationListener;
use app\service\core\diy\CoreDiyConfigService;
use think\facade\{Db,Event};

$checks=0;$pdo=null;$schema='';$failed=false;
function tabbarCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
try{
    [$app,$pdo,$schema,$site]=leadBoot();leadAssertIsolated($schema);
    $plugin=dirname(__DIR__);$root=dirname($plugin,3);
    // CoreSiteService authorizes real package/site.addons, not the fixture's site.app.
    Db::name('site')->where('site_id',$site)->update(['addons'=>'["hsx_lead"]']);
    $listener=new BottomNavigationListener();
    tabbarCheck($listener->handle(['key'=>'phone_shop'])===null,'another application never receives lead defaults');
    $default=$listener->handle(['key'=>'hsx_lead']);
    tabbarCheck($default['key']==='hsx_lead'&&count($default['value']['list'])===4,'authorized merchant application exposes four default destinations');
    tabbarCheck(($default['info']['type']??'')==='app','framework receives actual add-on metadata');
    $urls=array_column(array_column($default['value']['list'],'link'),'url');
    tabbarCheck(count(array_unique($urls))===4,'each tab is a distinct real page rather than a client-only state');
    tabbarCheck(array_column($default['value']['list'],'text')===['交付','找门店','收益','我的'],'default business labels are explicit');
    $package=require $plugin.'/package/uni-app-pages.php';
    foreach($default['value']['list'] as $item){
        $url=$item['link']['url'];$page=substr($url,strlen('/addon/hsx_lead/'));
        tabbarCheck(is_file($plugin.'/uni-app/'.$page.'.vue'),'registered destination source exists: '.$page);
        tabbarCheck(str_contains($package['pages'],'"path": "'.$page.'"'),'plugin page dictionary includes: '.$page);
        foreach(['iconPath','iconSelectPath'] as $key){
            $source=$plugin.'/resource/'.substr($item[$key],strlen('addon/hsx_lead/'));
            tabbarCheck(is_file($source),'navigation image is bundled with this plugin');
            $size=getimagesize($source);tabbarCheck($size!==false&&$size[0]===81&&$size[1]===81,'navigation PNG uses the intended compact size');
            tabbarCheck(filesize($source)<=4096,'navigation icon is small and local, not an external dependency');
        }
    }
    $events=require $plugin.'/app/event.php';
    tabbarCheck(in_array(BottomNavigationListener::class,$events['listen']['BottomNavigation']??[],true),'plugin registers the framework BottomNavigation event');
    Event::listen('BottomNavigation',app\listener\system\BottomNavigationListener::class);
    Event::listen('BottomNavigation',BottomNavigationListener::class);
    $core=new CoreDiyConfigService();
    $config=$core->getBottomConfig($site,'hsx_lead');
    tabbarCheck($config['key']==='hsx_lead'&&$config['value']===$default['value'],'actual framework service loads the plugin default');
    tabbarCheck(Db::name('sys_config')->where('config_key','DIY_BOTTOM_hsx_lead')->count()===0,'defaults need no extra table or forced per-site seed');
    $custom=$config['value'];$custom['list'][0]['text']='自定义交付';$custom['textHoverColor']='#2655aa';$custom['list']=array_reverse($custom['list']);
    $core->setBottomConfig($site,$custom,'hsx_lead');
    tabbarCheck($core->getBottomConfig($site,'hsx_lead')['value']===$custom,'framework persists edited captions, colors and ordering');
    tabbarCheck($listener->handle()['value']===$default['value'],'loading defaults cannot overwrite the saved site configuration');
    tabbarCheck(Db::name('sys_config')->where('site_id',$site)->where('config_key','DIY_BOTTOM_hsx_lead')->count()===1,'configuration stays in the existing framework config table');
    request()->siteId($site+1);
    tabbarCheck($listener->handle(['key'=>'hsx_lead'])===null,'unauthorized site has no merchant navigation');
    tabbarCheck($core->getBottomList(['key'=>'hsx_lead'])===[],'framework event listing respects site authorization');
    tabbarCheck(Db::name('sys_config')->where('site_id',$site+1)->where('config_key','DIY_BOTTOM_hsx_lead')->count()===0,'editing one site leaves other sites unchanged');
    echo "PASS cooperation tabbar: {$checks} checks; real framework event/configuration with isolated local schema, no external requests\n";
}catch(Throwable $error){$failed=true;fwrite(STDERR,'FAIL cooperation tabbar: '.$error->getMessage()."\n".$error->getFile().':'.$error->getLine()."\n");}
finally{if($pdo instanceof PDO&&$schema!=='')leadCleanup($pdo,$schema);}
exit($failed?1:0);
