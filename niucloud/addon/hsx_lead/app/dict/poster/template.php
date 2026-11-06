<?php
declare(strict_types=1);

// Ordinary framework template dictionaries: no seeded poster row or additional schema required.
$text=static function(string $id,string $title,string $value,int $x,int $y,int $width,int $height,int $size,string $color,bool $weight=false,string $relate=''):array {
    return ['id'=>$id,'componentName'=>$relate===''?'Text':'Lead'.str_replace(' ','',ucwords(str_replace('_',' ',$id))),
        'componentTitle'=>$title,'type'=>'text','path'=>'text','uses'=>$relate===''?0:1,'relate'=>$relate,'value'=>$value,
        'width'=>$width,'height'=>$height,'minWidth'=>80,'minHeight'=>24,'x'=>$x,'y'=>$y,'angle'=>0,'zIndex'=>4,
        'fontFamily'=>'','fontSize'=>$size,'fontColor'=>$color,'weight'=>$weight,'space'=>0,'lineHeight'=>1,'textAlign'=>'left'];
};
$templates=[];
foreach(['promotion'=>'商户协作推广海报','entity'=>'门店成员邀请海报']as$scene=>$name) {
    $templates[]=['name'=>$name,'type'=>'hsx_lead_'.$scene,'data'=>[
        'global'=>['bgType'=>'url','bgColor'=>'#EDF5FF','bgUrl'=>'addon/hsx_lead/poster/share-background.png','width'=>750,'height'=>1100],
        'value'=>[
            $text('eyebrow','海报眉题','LOCAL BUSINESS · 商户协作',48,45,660,35,19,'#CDE0FF'),
            $text('title','分享标题',$scene==='promotion'?'邀请你加入商户协作':'一起加入我的门店',48,114,654,125,46,'#FFFFFF',true,'hsx_lead_title'),
            $text('subtitle','分享副标题','连接本地门店，让需求找到交付方',48,263,654,65,24,'#DDEAFF',false,'hsx_lead_subtitle'),
            $text('label','邀请类型',$scene==='promotion'?'商户协作邀请':'门店成员邀请',60,389,630,34,22,'#3974C9',false,'hsx_lead_label'),
            $text('identity','邀请人或门店','我的门店',60,450,630,95,34,'#263E5D',true,'hsx_lead_identity'),
            ['id'=>'lead_qrcode','componentName'=>'LeadShareQrcode','componentTitle'=>'分享二维码','type'=>'qrcode','path'=>'qrcode','uses'=>1,
                'relate'=>'hsx_lead_qrcode','value'=>'','width'=>270,'height'=>270,'minWidth'=>150,'minHeight'=>150,'x'=>240,'y'=>586,'angle'=>0,'zIndex'=>5],
            $text('scan','扫码操作','微信扫一扫，查看邀请',178,888,475,40,27,'#29456C',true),
            $text('code','主体码或标语',$scene==='promotion'?'线索协作 · 连接同城商户':'门店码  ABCDEFGH23',128,947,560,35,22,'#7486A0',false,'hsx_lead_code'),
            $text('note','邀请提示',$scene==='promotion'?'扫码查看，登录后确认邀请关系':'扫码申请加入，由门店负责人确认',70,1060,650,29,18,'#7C8DA6',false,'hsx_lead_note'),
        ],
    ]];
}
return $templates;
