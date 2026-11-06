<?php
$definition=require __DIR__.'/components.php';
$entry=$definition['HSX_LEAD_COMPONENT']['list']['LeadCooperationEntry'];
$component=array_replace($entry['template'],$entry['value'],[
    'path'=>$entry['path'],'id'=>'hsx-lead-cooperation-entry','componentName'=>'LeadCooperationEntry','componentTitle'=>$entry['title'],
    'uses'=>1,'ignore'=>[],'componentIsShow'=>true,'isHidden'=>false,'pageStyle'=>'',
]);
$business=$definition['HSX_LEAD_COMPONENT']['list']['LeadBusinessNavigation'];
$businessComponent=array_replace($business['template'],$business['value'],[
    'path'=>$business['path'],'id'=>'hsx-lead-business-navigation','componentName'=>'LeadBusinessNavigation','componentTitle'=>$business['title'],
    'uses'=>1,'ignore'=>[],'componentIsShow'=>true,'isHidden'=>false,'pageStyle'=>'',
]);
$hero=$definition['HSX_LEAD_COMPONENT']['list']['LeadCooperationHero'];
$heroComponent=array_replace($hero['template'],$hero['value'],['path'=>$hero['path'],'id'=>'hsx-lead-cooperation-hero','componentName'=>'LeadCooperationHero','componentTitle'=>$hero['title'],'caption'=>'做好最后一公里服务。','title'=>'把客户还给各地靠谱手机店。','titleSize'=>21,'uses'=>1,'ignore'=>[],'componentIsShow'=>true,'isHidden'=>false,'pageStyle'=>'']);
$role=$definition['HSX_LEAD_COMPONENT']['list']['LeadRoleEntry'];
$storesComponent=array_replace($role['template'],$role['value'],['path'=>$role['path'],'id'=>'hsx-lead-role-stores','componentName'=>'LeadRoleEntry','componentTitle'=>'找门店卡片','title'=>'找全国靠谱手机店','uses'=>0,'ignore'=>[],'componentIsShow'=>true,'isHidden'=>false,'pageStyle'=>'']);
$customersComponent=array_replace($storesComponent,['id'=>'hsx-lead-role-customers','componentTitle'=>'找客户卡片','role'=>'customers','title'=>'找客户','subtitle'=>'查看交给本店的客户线索','buttonText'=>'查看客户','buttonStyle'=>'link','componentStartBgColor'=>'#ffffff','componentEndBgColor'=>'','buttonColor'=>'#536e9d','buttonTextColor'=>'#536e9d','borderColor'=>'#e0e7f1']);
$businessComponent=array_replace($businessComponent,['margin'=>['top'=>0,'bottom'=>12,'both'=>12],'topRounded'=>10,'bottomRounded'=>10]);
$pages=['DIY_HSX_LEAD_INDEX'=>['hsx_lead_index'=>[
    'title'=>'蓝白原版 · 独立卡片','cover'=>'','preview'=>'','desc'=>'标题区、找门店、找客户和业务入口分别装修','mode'=>'diy',
    'data'=>['global'=>[
        'title'=>'商户协作','pageStartBgColor'=>'#eef5ff','pageEndBgColor'=>'','pageGradientAngle'=>'to bottom','bgUrl'=>'','bgHeightScale'=>100,'imgWidth'=>'','imgHeight'=>'',
        'topStatusBar'=>['control'=>true,'isShow'=>true,'bgColor'=>'#ffffff','rollBgColor'=>'#ffffff','style'=>'style-1','styleName'=>'风格1','textColor'=>'#202124','rollTextColor'=>'#202124','textAlign'=>'left','titleFontSize'=>20,'titleFontWeight'=>700,'titleLineHeight'=>28,'h5Visible'=>true,'h5Height'=>44,'inputPlaceholder'=>'','imgUrl'=>'','link'=>['name'=>'']],
        'bottomTabBarSwitch'=>true,'bottomTabBar'=>['control'=>true,'isShow'=>true,'designNav'=>['title'=>'商户协作','key'=>'hsx_lead']],
        'copyright'=>['control'=>true,'isShow'=>false,'textColor'=>'#909399'],
        'popWindow'=>['imgUrl'=>'','imgWidth'=>'','imgHeight'=>'','count'=>'once','show'=>0,'link'=>['name'=>'']],
        'template'=>['textColor'=>'#202124','pageStartBgColor'=>'','pageEndBgColor'=>'','pageGradientAngle'=>'to bottom','componentBgUrl'=>'','componentBgAlpha'=>0,'componentStartBgColor'=>'','componentEndBgColor'=>'','componentGradientAngle'=>'to bottom','topRounded'=>0,'bottomRounded'=>0,'elementBgColor'=>'','topElementRounded'=>0,'bottomElementRounded'=>0,'margin'=>['top'=>0,'bottom'=>0,'both'=>0]],
    ],'value'=>[$heroComponent,$storesComponent,$customersComponent,$businessComponent]],
]]];
$pages['DIY_HSX_LEAD_INDEX']['hsx_lead_combined']=array_replace($pages['DIY_HSX_LEAD_INDEX']['hsx_lead_index'],['title'=>'经典图文 · 组合入口','desc'=>'保留组合区块，可在区块内分别调整两张卡','data'=>['global'=>$pages['DIY_HSX_LEAD_INDEX']['hsx_lead_index']['data']['global'],'value'=>[$component,$businessComponent]]]);

$profileGlobal=$pages['DIY_HSX_LEAD_INDEX']['hsx_lead_index']['data']['global'];
$profileGlobal['title']='我的门店';
$profileGlobal['pageStartBgColor']='#ffffff';
$profileGlobal['topStatusBar']['style']='style-1';
$profileGlobal['topStatusBar']['styleName']='风格1';
$links=(require __DIR__.'/links.php')['HSX_LEAD_MERCHANT_LINK']['child_list'];
$linksByName=array_column($links,null,'name');
$navItems=[];
foreach ([
    ['HSX_LEAD_MERCHANT_PROFILE_EDIT','门店资料','profile'],
    ['HSX_LEAD_MERCHANT_INVENTORY','库存快照','stores'],
    ['HSX_LEAD_MERCHANT_CONNECTION','ERP 同步','home'],
    ['HSX_LEAD_MERCHANT_WALLET','协作收益','wallet'],
    ['HSX_LEAD_MERCHANT_PROMOTION','推广中心','deliveries'],
] as [$name,$title,$icon]) {
    $navItems[]=[
        'id'=>'hsx-lead-nav-'.strtolower($icon),'title'=>$title,
        'imageUrl'=>'addon/hsx_lead/diy/tabbar/'.$icon.'.png','imgWidth'=>80,'imgHeight'=>80,
        'link'=>array_replace($linksByName[$name],['parent'=>'HSX_LEAD_MERCHANT_LINK']),
        'label'=>['control'=>false,'text'=>'','textColor'=>'#ffffff','bgColorStart'=>'#333333','bgColorEnd'=>'#333333'],
    ];
}
// Reuse the framework GraphicNav renderer and editor for ordinary links. These
// items can be renamed, sorted, removed, or given custom images and destinations.
$profileNavigation=[
    'path'=>'edit-graphic-nav','id'=>'hsx-lead-profile-navigation','componentName'=>'GraphicNav','componentTitle'=>'图文导航','uses'=>0,
    'ignore'=>[],'componentIsShow'=>true,'isHidden'=>false,'pageStyle'=>'',
    'pageStartBgColor'=>'','pageEndBgColor'=>'','pageGradientAngle'=>'to bottom',
    'componentStartBgColor'=>'#ffffff','componentEndBgColor'=>'','componentGradientAngle'=>'to bottom',
    'componentBgUrl'=>'','componentBgAlpha'=>0,'topRounded'=>0,'bottomRounded'=>0,
    'elementBgColor'=>'','topElementRounded'=>0,'bottomElementRounded'=>0,'margin'=>['top'=>0,'bottom'=>8,'both'=>0],
    'layout'=>'horizontal','mode'=>'graphic','showStyle'=>'fixed','rowCount'=>3,'pageCount'=>2,
    'imageSize'=>24,'aroundRadius'=>0,'font'=>['size'=>13,'weight'=>'normal','color'=>'#333333'],
    'carousel'=>['type'=>'circle','color'=>'#ffffff'],
    'swiper'=>['indicatorColor'=>'rgba(0,0,0,0.3)','indicatorActiveColor'=>'#333333','indicatorStyle'=>'style-1','indicatorAlign'=>'center'],
    'list'=>$navItems,
];
$profileComponents=[];
foreach (['LeadProfileIdentity','LeadProfileTools','LeadProfileBenefits','LeadProfilePromotion'] as $name) {
    $section=$definition['HSX_LEAD_COMPONENT']['list'][$name];
    $profileComponents[]=array_replace($section['template'],$section['value'],[
        'path'=>$section['path'],'id'=>'hsx-lead-profile-'.strtolower(substr($name,11)),
        'componentName'=>$name,'componentTitle'=>$section['title'],'uses'=>1,'ignore'=>[],
        'componentIsShow'=>true,'isHidden'=>false,'pageStyle'=>'',
    ]);
    if ($name==='LeadProfileIdentity') $profileComponents[]=$profileNavigation;
}
$pages['DIY_HSX_LEAD_MEMBER_INDEX']=['hsx_lead_member_index'=>[
    'title'=>'商户个人中心','cover'=>'','preview'=>'','desc'=>'门店身份、图文导航、协作通知、合作权益与推广入口','mode'=>'diy',
    'data'=>['global'=>$profileGlobal,'value'=>$profileComponents],
]];
return $pages;
