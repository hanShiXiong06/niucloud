<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$assertContains = static function (string $needle, string $haystack, string $message): void {
    if (!str_contains($haystack, $needle)) throw new RuntimeException($message);
};

$install = $read('sql/install.sql');
$schema = $read('app/support/ProjectCenterSchema.php');
$areaService = $read('app/service/core/ProjectCenterAreaEligibilityService.php');
$applicationService = $read('app/service/core/ProjectCenterApplicationService.php');
$portalService = $read('app/service/api/ProjectCenterPortalService.php');
$apiRoute = $read('app/api/route/route.php');
$projectAdmin = $read('admin/views/project/index.vue');
$areaAdmin = $read('admin/components/ProjectAreaEligibilityConfig.vue');
$diyDictionary = $read('app/dict/diy/components.php');
$diyEditor = $read('admin/views/diy/components/edit-project-center-area-eligibility.vue');
$diyRuntime = $read('uni-app/components/diy/project-center-area-eligibility/index.vue');
$detail = $read('uni-app/pages/project/detail.vue');
$databaseIntegration = $read('tests/area_eligibility_database_integration.php');

$assertContains('`eligibility_snapshot` longtext', $install, '全新安装缺少地区资格快照字段');
$assertContains("'eligibility_snapshot' =>", $schema, '一键升级迁移缺少地区资格快照字段');
$assertContains('use app\\model\\sys\\SysArea;', $areaService, '地区规则必须复用系统 sys_area');
$assertContains('validateProjectConfig', $areaService, '项目保存时必须校验地区白名单');
$assertContains('existingMinimalIds', $areaService, '省市区重复选择必须归并为最小规则');
$assertContains('assertEligible($project, $eligibilityRegion)', $applicationService, '工单提交核心层必须再次校验地区');
$assertContains("'eligibility_snapshot' => \$eligibilitySnapshot", $applicationService, '工单必须保存查询快照');
$assertContains('publicSummary($row)', $portalService, '公开项目信息必须只下发地区规则摘要');
$assertContains('projects/:id/area-eligibility', $apiRoute, '客户端缺少付款前地区查询接口');
$assertContains('ApiCheckToken::class, false', $apiRoute, '地区资格查询必须支持客户登录前使用');
$assertContains('getAreatree(3)', $areaAdmin, '管理端必须调用系统省市区树');
$assertContains('getCheckedNodes(false, false)', $areaAdmin, '管理端必须按树形级联结果读取选中地区');
$assertContains('checkedSet.has(Number(node.pid || 0))', $areaAdmin, '级联勾选后必须只保存未被父级覆盖的最小地区范围');
if (str_contains($areaAdmin, 'check-strictly')) throw new RuntimeException('地区树不能使用父子不关联的 check-strictly 模式');
$assertContains('ProjectAreaEligibilityConfig', $projectAdmin, '项目配置缺少地区资格管理入口');
$assertContains('ProjectCenterAreaEligibility', $diyDictionary, '低代码字典缺少地区查询器');
$assertContains('地区参与查询器', $diyEditor, '低代码编辑器缺少地区查询器配置');
$assertContains('getProjectCenterAreaEligibility', $diyRuntime, '低代码运行组件未调用真实地区资格接口');
$areaPosition = strpos($detail, 'areaGateRequired && !areaVerified');
$paymentPosition = strpos($detail, 'class="payment-panel"');
if ($areaPosition === false || $paymentPosition === false || $areaPosition >= $paymentPosition) {
    throw new RuntimeException('地区资格查询必须在付款码之前');
}
$assertContains('eligibility_region: eligibilitySelection.value', $detail, '客户提交必须传递服务端可重新校验的地区数据');
$assertContains('HSX_PROJECT_CENTER_AREA_INTEGRATION', $databaseIntegration, '必须提供显式开启的地区数据库回滚测试');
$assertContains("'cleanup_verified'", $databaseIntegration, '地区数据库测试必须验证测试数据已回滚');

echo "project center area eligibility contract smoke passed\n";
