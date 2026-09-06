<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
};

$install = $read('sql/install.sql');
$schema = $read('app/support/ProjectCenterSchema.php');
$benefits = $read('app/dict/member/benefits.php');
$rules = $read('app/service/core/ProjectCenterDistributionRuleService.php');
$distribution = $read('app/service/core/ProjectCenterDistributionService.php');
$invite = $read('app/service/core/ProjectCenterInviteService.php');
$databaseIntegration = $read('tests/distribution_database_integration.php');
$review = $read('app/service/core/ProjectCenterReviewService.php');
$refund = $read('app/service/admin/ProjectCenterRefundAdminService.php');
$adminRoute = $read('app/adminapi/route/route.php');
$apiRoute = $read('app/api/route/route.php');
$projectAdmin = $read('admin/views/project/index.vue');
$applicationAdmin = $read('admin/views/application/index.vue');
$distributionAdmin = $read('admin/views/distribution/index.vue');
$distributionPortal = $read('uni-app/pages/distribution/index.vue');
$projectPortal = $read('uni-app/pages/project/detail.vue');
$uniPages = $read('package/uni-app-pages.php');
$event = $read('app/event.php');
$posterType = $read('app/listener/poster/ProjectCenterDistributionPosterType.php');
$posterData = $read('app/listener/poster/ProjectCenterDistributionPoster.php');
$posterTemplate = $read('app/dict/poster/template.php');
$posterService = $read('app/service/api/ProjectCenterDistributionPosterService.php');
$posterComponent = $read('uni-app/components/ProjectDistributionPoster.vue');
$portalApi = $read('uni-app/api/index.ts');
$memberBenefits = (string)file_get_contents(dirname($root, 3) . '/admin/src/app/views/member/components/member-benefits.vue');

foreach (['project_center_invite', 'project_center_relation_log', 'project_center_distribution_order', 'project_center_distribution_detail', 'project_center_distribution_debt'] as $table) {
    $assertContains($table, $install, '全新安装缺少分销表：' . $table);
    $assertContains($table, $schema, '升级迁移缺少分销表：' . $table);
}
$assertContains('hsx_project_distribution', $benefits, '分销资格必须接入会员等级权益');
$assertContains("ELIGIBILITY_ALL_MEMBER = 'all_member'", $rules, '项目必须支持不接入会员等级的所有会员推广模式');
$assertContains("'eligibility_mode' => \$eligibilityMode", $rules, '项目分销规则必须持久化推广资格模式');
$assertContains('json_decode($config, true)', $rules, '核心规则层必须兼容模型数组和数据库原始 JSON 字符串');
$assertContains('所有正常会员均可推广', $rules, '所有会员模式必须返回明确可读的规则说明');
$assertContains("'enabled_at'", $rules, '项目规则必须记录分销启用时间');
$assertContains("field('level_id,level_name,status,level_benefits')", $rules, '会员等级停用后必须立即失去推广资格');
$assertContains('不追溯发佣', $distribution, '历史审核工单必须明确不追溯发佣');
$assertContains('payment_confirmed_at <= 0', $distribution, '佣金核心层必须兜底拦截未核款工单');
$assertContains('reconcileApproved(int $limit = 100, int $siteId = 0)', $distribution, '补偿任务必须支持按站点隔离执行');
$assertContains('member.pid', $invite, '推荐关系必须复用框架会员关系');
$assertContains('wouldCreateCycle', $invite, '推荐关系必须阻止循环绑定');
$assertContains('memberCapability($siteId, $inviterMemberId, 1, $project)', $invite, '邀请资格必须按当前项目的准入模式判断');
$assertContains("'share_code'", $invite, '邀请接口必须返回适用于小程序码的短签名凭证');
$assertContains('hash_equals', $invite, '短签名邀请凭证必须在服务端防篡改校验');
$assertContains('拒绝日志必须在绑定事务结束后单独落库', $invite, '自绑和循环拒绝必须留下不被事务回滚的审计记录');
$assertContains('paymentChecked', $review, '审核核心层必须校验真实付款确认');
$assertContains('freezeByApplication', $refund, '退款申请必须冻结待结算佣金');
$assertContains('refundByApplication', $refund, '退款完成必须冲红已生成佣金');
$assertContains("->join('project_center_distribution_order d'", $distribution, '退款补偿查询必须使用当前 ThinkPHP 版本支持的连接方法');
if (str_contains($distribution, '->innerJoin(')) throw new RuntimeException('当前 ThinkPHP 版本不支持 innerJoin 方法');
$assertContains('distribution/orders', $adminRoute, '管理端必须提供分销台账接口');
$assertContains('distribution/invite', $apiRoute, '会员端必须提供安全邀请凭证接口');
$assertContains('QuestionFilled', $projectAdmin, '项目分销配置必须提供悬浮规则说明');
$assertContains('form.eligibility_mode', $projectAdmin, '项目分销配置必须允许管理员选择推广资格模式');
$assertContains('所有会员', $projectAdmin, '项目分销配置必须提供不接入会员等级的选项');
$assertContains('paymentChecked', $applicationAdmin, '审核界面必须有付款核对确认');
$assertContains('计算规则', $distributionAdmin, '佣金台账必须展示可解释计算公式');
$assertContains('开启佣金提醒', $distributionPortal, '会员端必须提供佣金消息订阅入口');
$assertContains("eligibility_mode === 'all_member'", $distributionPortal, '会员端必须清晰展示所有会员推广规则');
$assertContains('分享海报', $distributionPortal, '推广中心必须提供项目海报分享入口');
$assertContains('overview.capability?.eligible && inviteToken', $distributionPortal, '推广中心海报入口只能向具备分销资格的会员显示');
$assertContains('pages/distribution/index', $uniPages, '推广中心页面必须登记到 uni-app 页面清单，确保 H5 和小程序构建包含该页面');
$assertContains('v-if="isDistributor"', $projectPortal, '项目详情分享入口只能向已具备分销资格的会员显示');
$assertContains('project-distribution-poster', $projectPortal, '项目详情必须接入插件专用分享海报组件');
$assertContains('handleOnloadParams', $projectPortal, '项目详情必须兼容微信小程序码 scene 参数');
$assertContains('ProjectCenterDistributionPosterType', $event, '插件必须注册项目推广海报类型事件');
$assertContains('ProjectCenterDistributionPoster', $event, '插件必须注册项目推广海报数据事件');
$assertContains('public function handle($data = []): array', $posterType, '海报类型监听器必须兼容框架无参数触发时传入 null');
$assertContains('public function handle($data = []): array', $posterData, '海报数据监听器必须兼容空事件参数');
$assertContains('项目推广分享海报', $posterType, '必须提供可识别的项目推广海报类型');
$assertContains('memberCapability', $posterData, '服务端生成海报前必须再次校验会员推广资格');
$assertContains("['key' => 'i', 'value' => \$credential]", $posterData, '小程序码只能携带长度受控的短邀请凭证');
$assertContains("'relate' => 'url'", $posterTemplate, '默认海报模板必须包含可扫码进入项目的二维码');
$assertContains('distribution/poster', $apiRoute, '插件必须提供独立的项目海报接口');
$assertContains("upload/hsx_project_center/poster/", $posterService, '项目海报必须写入插件独立的云存储目录');
$assertContains('CoreFileService', $posterService, '项目海报必须复用后台当前启用的云存储驱动');
$assertContains('@unlink($localPath)', $posterService, '海报上传云端成功后必须删除本地临时成品');
$assertContains('CACHE_TTL', $posterService, '项目海报必须提供服务端结果缓存以减少重复生成');
$assertContains('正在生成推广海报', $posterComponent, '项目专用海报弹窗必须展示明确的生成 loading');
$assertContains('pendingKey', $posterComponent, '移动端必须合并同一海报的重复生成请求');
$assertContains('generateProjectCenterDistributionPoster', $portalApi, '移动端必须调用插件专用海报接口');
$assertContains('const loader = modules[componentPath]', $memberBenefits, '会员权益动态组件必须先检查加载器是否存在');
$assertContains('component_missing', $memberBenefits, '插件权益组件未同步时必须给出明确提示，不能拖垮会员等级页面');
$assertContains('HSX_PROJECT_CENTER_DISTRIBUTION_INTEGRATION', $databaseIntegration, '必须提供显式开启、事务回滚的数据闭环集成测试');
$assertContains("'cleanup_verified'", $databaseIntegration, '数据库集成测试必须验证测试数据已清理');

require_once $root . '/app/listener/poster/ProjectCenterDistributionPosterType.php';
require_once $root . '/app/listener/poster/ProjectCenterDistributionPoster.php';
$posterTypes = (new \addon\hsx_project_center\app\listener\poster\ProjectCenterDistributionPosterType())->handle(null);
if (($posterTypes[0]['type'] ?? '') !== 'hsx_project_center_distribution') {
    throw new RuntimeException('海报类型监听器接收 null 后必须正常返回项目推广类型');
}
$emptyPoster = (new \addon\hsx_project_center\app\listener\poster\ProjectCenterDistributionPoster())->handle(null);
if ($emptyPoster !== []) throw new RuntimeException('海报数据监听器接收 null 时必须安全返回空数据');

echo "project center distribution contract smoke passed\n";
