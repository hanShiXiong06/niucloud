<?php
declare(strict_types=1);

$definitions = [
    'LeadTitle' => ['分享标题', '', '邀请你加入商户协作', 654, 125],
    'LeadSubtitle' => ['分享副标题', '', '连接本地门店，让每条需求找到交付方', 654, 65],
    'LeadLabel' => ['邀请类型', '', '商户协作邀请', 630, 34],
    'LeadIdentity' => ['邀请人或门店（系统生成）', 'hsx_lead_identity', '本站会员', 630, 95],
    'LeadCode' => ['门店主体码（系统生成）', 'hsx_lead_code', '门店码由当前负责人身份生成', 560, 35],
    'LeadNote' => ['邀请提示', '', '登录后确认，不会自动加入门店', 650, 29],
];
$list = [];
$sort = 13100;
foreach ($definitions as $name => $entry) {
    [$title,$relate,$value,$width,$height] = $entry;
    $list[$name] = ['title' => $title, 'type' => 'text', 'icon' => 'iconfont iconbiaoti', 'path' => 'text', 'uses' => $relate === '' ? 0 : 1, 'sort' => $sort++,
        'relate' => $relate, 'value' => $value, 'template' => ['width' => $width, 'height' => $height, 'fontSize' => 26, 'fontColor' => '#29456C']];
}
$list['LeadShareQrcode'] = ['title' => '分享二维码', 'type' => 'qrcode', 'icon' => 'iconfont iconerweima', 'path' => 'qrcode', 'uses' => 1, 'sort' => $sort,
    'relate' => 'hsx_lead_qrcode', 'value' => '', 'template' => ['width' => 270, 'height' => 270, 'minWidth' => 150, 'minHeight' => 150]];
return ['hsx_lead_share' => ['title' => '商户协作分享', 'support' => ['hsx_lead_promotion', 'hsx_lead_entity'], 'list' => $list]];
