<?php
declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$root = dirname($projectRoot, 3);
$read = static function (string $path): string {
    $content = file_get_contents($path);
    if ($content === false) throw new RuntimeException('无法读取：' . $path);
    return $content;
};
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

$service = $read($projectRoot . '/app/service/core/ProjectCenterContactGuideService.php');
$portal = $read($projectRoot . '/app/service/api/ProjectCenterPortalService.php');
$admin = $read($projectRoot . '/admin/views/project/index.vue');
$mobile = $read($projectRoot . '/uni-app/pages/project/detail.vue');
$wecom = $read($root . '/niucloud/addon/hsx_wecom/app/service/core/WecomClient.php');

$assert(str_contains($service, "'fallback_qrcode'"), '联系流程必须配置备用二维码');
$assert(str_contains($service, "'source' => 'uploaded_qrcode'"), '企微失败时必须回退上传二维码');
$assert(str_contains($service, "['source'] = 'wecom'"), '企微可用时必须标识企业微信来源');
$assert(str_contains($portal, "['contact_guide'] = \$contactGuide"), '项目公开接口未下发联系引导');
$assert(str_contains($admin, '备用企微二维码'), '管理端缺少备用二维码配置');
$assert(str_contains($mobile, "['查询','加企微','付款','核对','写资料']"), '移动端五步流程不完整');
$assert(str_contains($mobile, 'confirmContact'), '移动端缺少客户添加确认动作');
$assert(str_contains($wecom, '/externalcontact/add_contact_way'), '企微插件缺少联系我接口');

echo "hsx_project_center contact guide contract smoke passed\n";
