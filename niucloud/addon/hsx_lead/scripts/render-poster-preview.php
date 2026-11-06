<?php
declare(strict_types=1);
/** Actual framework renderer, synthetic payload, locally generated H5 QR; no database or WeChat call. */
if(PHP_SAPI!=='cli'||!in_array('--synthetic-local',$argv,true))exit("Requires --synthetic-local\n");
foreach(['curl_exec','curl_multi_exec','fsockopen','pfsockopen','stream_socket_client','socket_connect']as$f)if(function_exists($f))exit("Disable external network functions\n");
if(filter_var(ini_get('allow_url_fopen'),FILTER_VALIDATE_BOOLEAN))exit("Disable allow_url_fopen\n");
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
$root=dirname(__DIR__,3);require $root.'/vendor/autoload.php';$app=new think\App($root);
require $app->getThinkPath().'helper.php';require $root.'/app/common.php';
$out=dirname($root).'/output/hsx-lead-poster-20261106';
if(!is_dir($out)&&!mkdir($out,0700,true))throw new RuntimeException('Cannot create synthetic preview output');
$stage=$out.'/render-input';if(!is_dir($stage)&&!mkdir($stage,0700,true))throw new RuntimeException('Cannot create render input');
if(!file_exists($stage.'/static')&&!symlink($root.'/public/static',$stage.'/static'))throw new RuntimeException('Cannot expose existing local fonts read-only');
$app->event->listen('GetQrcodeOfChannel',app\listener\qrcode\WechatQrcodeListener::class);
$templates=require dirname(__DIR__).'/app/dict/poster/template.php';
$selected='';foreach($argv as$arg)if(str_starts_with($arg,'--scene='))$selected=substr($arg,8);
if($selected!==''&&!in_array($selected,['promotion','entity'],true))throw new RuntimeException('Unknown synthetic scene');
$results=[];
$payload=['hsx_lead_title'=>'一起加入我的门店','hsx_lead_subtitle'=>'共享门店业务，一起承接好客户需求','hsx_lead_identity'=>'合成样例 · 石家庄长安数码门店',
    'hsx_lead_label'=>'门店成员邀请','hsx_lead_code'=>'门店码  ABCDEFGH23','hsx_lead_note'=>'合成样例，不是实际门店邀请',
    'hsx_lead_qrcode'=>['url'=>'https://example.invalid','page'=>'','data'=>[]]];
$before=getcwd();chdir($stage);
try {
    foreach($templates as$template){
        if($selected!==''&&$template['type']!=='hsx_lead_'.$selected)continue;
        $data=$payload;
        if($template['type']==='hsx_lead_promotion')$data=array_replace($data,['hsx_lead_title'=>'邀请你加入商户协作','hsx_lead_subtitle'=>'连接本地门店，让每条需求找到交付方','hsx_lead_identity'=>'合成样例 · 韩师傅','hsx_lead_label'=>'商户协作邀请','hsx_lead_code'=>'线索协作 · 连接同城商户']);
        $template['data']['global']['bgUrl']=realpath(dirname(__DIR__).'/resource/poster/share-background.png');
        // Kkokk's CLI driver requires absolute file paths; only resolve assets,
        // keeping the checked-in layout, fonts, sizes, text and colors unchanged.
        foreach($template['data']['value']as&$component){
            if($component['type']==='text')$component['fontFamily']=realpath($root.'/public/static/font/PingFang-Medium.ttf');
            if($component['type']==='qrcode'){
                $component['type']='image';$component['value']=realpath(qrcode('https://example.invalid','',[],0,'','h5'));
                $component['relate']='';
            }
        }unset($component);unset($data['hsx_lead_qrcode']);
        $temporary=$template['type'].'-rendering-'.bin2hex(random_bytes(4)).'.png';
        (new app\service\core\poster\CorePosterService())->create(0,$template['data'],$data,$out,$temporary,'h5');
        $png=new Imagick($out.'/'.$temporary);$scale=$png->getImageWidth()/750;$black=0;$samples=0;
        for($y=(int)(594*$scale);$y<(int)(847*$scale);$y+=2)for($x=(int)(249*$scale);$x<(int)(501*$scale);$x+=2){
            $rgb=$png->getImagePixelColor($x,$y)->getColor();$samples++;if($rgb['r']<40&&$rgb['g']<40&&$rgb['b']<40)$black++;
        }
        if($samples===0||$black/$samples<0.15||$black/$samples>0.75)throw new RuntimeException('Synthetic QR is blank or damaged; cannot accept this render');
        $sha=hash_file('sha256',$out.'/'.$temporary);$versioned=$template['type'].'-'.substr($sha,0,12).'.png';
        if(!rename($out.'/'.$temporary,$out.'/'.$versioned))throw new RuntimeException('Atomic preview publication failed');
        copy($out.'/'.$versioned,$out.'/'.$template['type'].'.png');
        $results[]=['file'=>$versioned,'sha256'=>$sha,'qr_black_samples'=>$black,'qr_samples'=>$samples];
    }
} finally {chdir($before);}
echo json_encode(['passed'=>true,'output'=>$out,'files'=>$results,'scope'=>'actual framework template and renderer; QR pixel threshold verified; synthetic payload; local H5 QR; no database or external requests'],JSON_UNESCAPED_UNICODE).PHP_EOL;
