<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$service = $read('app/service/api/AiProjectAssistantService.php');
$routes = $read('app/api/route/route.php');
$api = $read('uni-app/api/assistant.ts');
$popup = $read('uni-app/components/AiProjectAssistantPopup.vue');

foreach (['hsx_project_center.customer_assistant', 'AiBusinessContextService', 'AiRiskService', "self::INTEGRATION . ':' . \$projectId", 'human_handoff'] as $needle) {
    $assert(str_contains($service, $needle), '项目 AI 服务缺少契约：' . $needle);
}
$assert(str_contains($routes, "middleware(ApiCheckToken::class, false)"), '项目 AI 公开咨询必须识别站点并支持可选会员身份');
$assert(str_contains($api, 'streamProjectAiAssistant'), 'uni-app 必须提供项目 SSE 客户端');
foreach (['streamProjectAiAssistant', 'projectAiTextToSpeech', '联系群内工作人员', '当前项目独立知识库'] as $needle) {
    $assert(str_contains($popup, $needle), '项目 AI 弹窗缺少能力：' . $needle);
}

echo "hsx_ai project assistant contract smoke passed\n";
