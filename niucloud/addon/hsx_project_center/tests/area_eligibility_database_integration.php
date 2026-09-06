<?php
declare(strict_types=1);

if (getenv('HSX_PROJECT_CENTER_AREA_INTEGRATION') !== '1') {
    fwrite(STDERR, "Set HSX_PROJECT_CENTER_AREA_INTEGRATION=1 to run this rollback-only database integration test.\n");
    exit(2);
}

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_project_center\app\model\ProjectCenterApplication;
use addon\hsx_project_center\app\model\ProjectCenterProject;
use addon\hsx_project_center\app\service\core\ProjectCenterAreaEligibilityService;
use addon\hsx_project_center\app\support\ProjectCenterSchema;
use think\facade\Db;

$app = new think\App();
$app->initialize();
ProjectCenterSchema::migrate();

$assert = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
$expectException = static function (callable $callback, string $contains, string $message) use ($assert): void {
    try {
        $callback();
    } catch (Throwable $e) {
        $assert(str_contains($e->getMessage(), $contains), $message . '，实际异常：' . $e->getMessage());
        return;
    }
    throw new RuntimeException($message . '，但没有抛出异常');
};

$prefix = (string)config('database.connections.mysql.prefix');
$areaTable = $prefix . 'sys_area';
$chains = Db::table($areaTable)->alias('d')
    ->join($areaTable . ' c', 'c.id = d.pid')
    ->join($areaTable . ' p', 'p.id = c.pid')
    ->where('d.level', '=', 3)->where('c.level', '=', 2)->where('p.level', '=', 1)
    ->field('p.id province_id,p.name province_name,c.id city_id,c.name city_name,d.id district_id,d.name district_name')
    ->order('p.id asc,c.id asc,d.id asc')->select()->toArray();
if (count($chains) < 2) throw new RuntimeException('系统地区数据不完整，至少需要两条省/市/区链路');

$inside = $chains[0];
$outsideProvince = null;
$outsideCity = null;
foreach ($chains as $chain) {
    if ($outsideProvince === null && (int)$chain['province_id'] !== (int)$inside['province_id']) $outsideProvince = $chain;
    if ($outsideCity === null && (int)$chain['city_id'] !== (int)$inside['city_id']) $outsideCity = $chain;
    if ($outsideProvince !== null && $outsideCity !== null) break;
}
if ($outsideProvince === null || $outsideCity === null) {
    throw new RuntimeException('系统地区数据不足以验证跨省和跨市拦截');
}

$selection = static fn(array $chain): array => [
    'province_id' => (int)$chain['province_id'],
    'city_id' => (int)$chain['city_id'],
    'district_id' => (int)$chain['district_id'],
];
$projectConfig = static fn(array $ids, bool $enabled = true): array => [
    'config_json' => [
        'area_eligibility' => [
            'enabled' => $enabled ? 1 : 0,
            'allowed_area_ids' => $ids,
            'eligible_text' => '集成测试地区可以参加',
            'ineligible_text' => '集成测试地区不可参加',
        ],
    ],
];

$siteId = 2_100_000_000 + random_int(1, 10_000);
$suffix = date('YmdHis') . bin2hex(random_bytes(4));
$cases = [];
$pass = static function (string $id, string $name) use (&$cases): void {
    $cases[] = ['id' => $id, 'name' => $name, 'status' => 'PASS'];
};
$service = new ProjectCenterAreaEligibilityService();
$projectId = 0;
$applicationId = 0;
$failure = null;

Db::startTrans();
try {
    $projectId = (int)Db::name('project_center_project')->insertGetId([
        'site_id' => $siteId,
        'project_no' => 'PCARE-' . $suffix,
        'title' => '项目地区资格回滚集成测试',
        'status' => 1,
        'reviewer_uids' => '[]',
        'reviewer_role_ids' => '[]',
        'config_json' => json_encode($projectConfig([(int)$inside['province_id']])['config_json'], JSON_UNESCAPED_UNICODE),
        'create_at' => time(),
        'update_at' => time(),
    ]);
    $project = ProjectCenterProject::where('id', '=', $projectId)->findOrEmpty();
    $assert(!$project->isEmpty(), '测试项目创建失败');

    $provinceResult = $service->query($project, $selection($inside));
    $assert(!empty($provinceResult['eligible']), '勾选省后，该省下级区县应可参与');
    $assert((int)($provinceResult['matched_scope']['id'] ?? 0) === (int)$inside['province_id'], '省级命中范围错误');
    $outsideResult = $service->query($projectConfig([(int)$inside['province_id']]), $selection($outsideProvince));
    $assert(empty($outsideResult['eligible']), '未勾选省份必须拦截');
    $pass('PC-AREA-PROVINCE-001', '省级范围覆盖与跨省拦截');

    $cityResult = $service->query($projectConfig([(int)$inside['city_id']]), $selection($inside));
    $assert(!empty($cityResult['eligible']), '勾选市后，该市下级区县应可参与');
    $otherCityResult = $service->query($projectConfig([(int)$inside['city_id']]), $selection($outsideCity));
    $assert(empty($otherCityResult['eligible']), '未勾选城市必须拦截');
    $pass('PC-AREA-CITY-001', '市级范围覆盖与跨市拦截');

    $districtResult = $service->query($projectConfig([(int)$inside['district_id']]), $selection($inside));
    $assert(!empty($districtResult['eligible']), '精确区县白名单应可参与');
    $assert((string)$districtResult['full_name'] !== '', '查询结果必须包含可读地区名称');
    $pass('PC-AREA-DISTRICT-001', '区县精确命中和可读结果');

    $minimal = $service->validateProjectConfig([
        'enabled' => 1,
        'allowed_area_ids' => [(int)$inside['province_id'], (int)$inside['city_id'], (int)$inside['district_id']],
    ]);
    $assert($minimal['allowed_area_ids'] === [(int)$inside['province_id']], '上级已选时必须去掉重复的下级节点');
    $expectException(
        static fn() => $service->validateProjectConfig(['enabled' => 1, 'allowed_area_ids' => [2_147_483_000]]),
        '已失效', '不存在的地区 ID 必须被拦截'
    );
    $pass('PC-AREA-CONFIG-001', '地区规则最小化和失效 ID 拦截');

    $wrongChain = $selection($inside);
    $wrongChain['city_id'] = (int)$outsideCity['city_id'];
    $expectException(
        static fn() => $service->query($projectConfig([(int)$inside['province_id']]), $wrongChain),
        '不匹配', '伪造的省市父子关系必须被拦截'
    );
    $pass('PC-AREA-SECURITY-001', '省市区层级关系服务端校验');

    $summary = $service->publicSummary($projectConfig([(int)$inside['province_id']]));
    $assert(!array_key_exists('allowed_area_ids', $summary), '公开项目信息不得下发完整地区白名单');
    $assert((int)$summary['scope_count'] === 1, '公开摘要范围数量错误');
    $unrestricted = $service->query($projectConfig([], false), []);
    $assert(!empty($unrestricted['eligible']) && ($unrestricted['status'] ?? '') === 'unrestricted', '关闭地区限制后应直接允许参与');
    $pass('PC-AREA-PRIVACY-001', '白名单隐私和关闭开关兼容');

    $applicationId = (int)Db::name('project_center_application')->insertGetId([
        'site_id' => $siteId,
        'application_no' => 'PCAREAPP-' . $suffix,
        'project_id' => $projectId,
        'member_id' => 1,
        'idempotency_key' => 'area-test:' . $suffix,
        'status' => 'submitted',
        'eligibility_snapshot' => json_encode($provinceResult, JSON_UNESCAPED_UNICODE),
        'create_at' => time(),
        'update_at' => time(),
    ]);
    $application = ProjectCenterApplication::where('id', '=', $applicationId)->findOrEmpty();
    $assert(!$application->isEmpty(), '地区快照测试工单创建失败');
    $assert(is_array($application->eligibility_snapshot), '工单地区快照必须按 JSON 数组读取');
    $assert((int)($application->eligibility_snapshot['eligible'] ?? 0) === 1, '工单地区快照丢失查询结果');
    $pass('PC-AREA-SNAPSHOT-001', '工单地区资格快照持久化');
} catch (Throwable $e) {
    $failure = $e;
} finally {
    Db::rollback();
}

$cleanupVerified = true;
if ($projectId > 0) $cleanupVerified = $cleanupVerified && (int)Db::name('project_center_project')->where('id', $projectId)->count() === 0;
if ($applicationId > 0) $cleanupVerified = $cleanupVerified && (int)Db::name('project_center_application')->where('id', $applicationId)->count() === 0;

$summary = [
    'status' => $failure === null && $cleanupVerified ? 'PASS' : 'FAIL',
    'case_count' => count($cases),
    'cases' => $cases,
    'cleanup_verified' => $cleanupVerified,
    'sample_area' => [
        'province' => (string)$inside['province_name'],
        'city' => (string)$inside['city_name'],
        'district' => (string)$inside['district_name'],
    ],
];
echo json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
if ($failure !== null) throw $failure;
if (!$cleanupVerified) throw new RuntimeException('地区资格集成测试数据回滚失败');
