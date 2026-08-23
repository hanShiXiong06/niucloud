<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$assertContains = static function (string $needle, string $content, string $message) use ($assert): void {
    $assert(str_contains($content, $needle), $message);
};

$event = $read('app/event.php');
$listener = $read('app/listener/ai/AiProjectBusinessContextRequested.php');
$portal = $read('app/service/api/ProjectCenterPortalService.php');
$admin = $read('admin/views/project/index.vue');
$detail = $read('uni-app/pages/project/detail.vue');
$popup = (string)file_get_contents(dirname(__DIR__, 2) . '/hsx_ai/uni-app/components/AiProjectAssistantPopup.vue');

$assertContains('HsxAiIntegrationRegistryRequested', $event, '项目中心必须通过契约注册 AI 业务接入');
$assertContains('HsxAiBusinessContextRequested', $event, '项目中心必须通过契约提供项目知识');
foreach (['site_id', 'project_id', 'ai_enabled', 'ai_knowledge', 'human_handoff'] as $needle) {
    $assertContains($needle, $listener, '项目知识消费者缺少隔离或转人工契约：' . $needle);
}
$assertContains("array_intersect_key", $portal, '公开项目接口必须过滤 AI 私有知识和审核配置');
$assertContains("assistantType'] = 'project_center'", $portal, '低代码 AI 入口必须切换为项目弹窗模式');
foreach (['项目专属知识库', '核心关注问题', '语音朗读'] as $needle) {
    $assertContains($needle, $admin, '项目后台缺少 AI 配置：' . $needle);
}
$assertContains('AiAssistantEntry', $detail, '未装修 AI 入口时项目页必须提供默认入口');
$assertContains('persistent-questions', $popup, 'AI 快捷问题必须在多轮对话中始终保留');
$assertContains('v-if="capability.suggestions?.length"', $popup, 'AI 快捷问题不能仅在初始空会话展示');
$assertContains('if (sending.value) return', $popup, 'AI 回答生成期间必须阻止重复点击快捷问题');

echo "project center ai assistant contract smoke passed\n";
