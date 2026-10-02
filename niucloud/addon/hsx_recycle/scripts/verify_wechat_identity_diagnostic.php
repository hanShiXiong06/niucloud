<?php
declare(strict_types=1);

// Synthetic SQLite only. Never load .env, initialize the app or call WeChat.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/diagnose_wechat_identity.php';

$checks = 0;
function identityCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    $GLOBALS['checks']++;
    echo 'PASS ' . $message . PHP_EOL;
}
function identityCodes(array $report): array { return array_column($report['检查结果'], 'code'); }

function identityFixture(array $members, array $fans, bool $missingUnion = false): array
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE t_member (member_id INTEGER PRIMARY KEY, site_id INTEGER, wx_unionid TEXT COLLATE NOCASE, wx_openid TEXT COLLATE NOCASE, weapp_openid TEXT COLLATE NOCASE, status INTEGER DEFAULT 1, is_del INTEGER DEFAULT 0, delete_time INTEGER DEFAULT 0)');
    $pdo->exec('CREATE TABLE t_wechat_fans (fans_id INTEGER PRIMARY KEY, site_id INTEGER, unionid TEXT COLLATE NOCASE, openid TEXT COLLATE NOCASE, app_id TEXT, is_subscribe INTEGER DEFAULT 1, update_time INTEGER DEFAULT 100)');
    $pdo->exec('CREATE TABLE t_sys_config (site_id INTEGER, config_key TEXT, value TEXT)');
    $pdo->exec('CREATE TABLE t_site (site_id INTEGER, site_name TEXT)');
    $pdo->exec("INSERT INTO t_site VALUES (1,'Test store')");
    foreach (['WECHAT' => ['app_id' => 'wx-official-test', 'app_secret' => 'SENSITIVE_SECRET', 'token' => 'SENSITIVE_TOKEN'], 'weapp' => ['app_id' => 'wx-mini-test', 'is_authorization' => 1], 'recycle_order_submit_config' => ['follow_official_account' => ['enabled' => false]]] as $key => $data) {
        $pdo->prepare('INSERT INTO t_sys_config VALUES (1,?,?)')->execute([$key, json_encode($data)]);
    }
    foreach (['member' => $members, 'wechat_fans' => $fans] as $table => $rows) {
        foreach ($rows as $row) {
            $sql = 'INSERT INTO t_' . $table . ' (`' . implode('`,`', array_keys($row)) . '`) VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')';
            $pdo->prepare($sql)->execute(array_values($row));
        }
    }
    if ($missingUnion) $pdo->exec('ALTER TABLE t_member DROP COLUMN wx_unionid');
    $pdo->exec('PRAGMA query_only = ON');
    return [new HsxWechatIdentityDiagnostic($pdo, 't_'), $pdo];
}

$union = 'union-1234567890123456789';
$openid = 'official-1234567890123456';
$member = ['member_id' => 10, 'site_id' => 1, 'wx_unionid' => $union, 'weapp_openid' => 'mini-123456789012345678', 'wx_openid' => ''];
$fan = ['fans_id' => 20, 'site_id' => 1, 'unionid' => $union, 'openid' => $openid, 'app_id' => 'wx-official-test'];
[$diagnostic, $pdo] = identityFixture([$member], [$fan]);
$before = $pdo->query('SELECT * FROM t_member')->fetchAll(PDO::FETCH_ASSOC);
$report = $diagnostic->report(1, 'unionid', $union);
identityCheck(in_array('LOCAL_LINK_CANDIDATE', identityCodes($report), true), '识别缺少公众号OpenID的待关联候选');
identityCheck($report['配置检查']['关注提示开关'] === false && count($report['关联会员_脱敏']) === 1, '关注提示关闭不影响诊断');
identityCheck($pdo->query('SELECT * FROM t_member')->fetchAll(PDO::FETCH_ASSOC) === $before, '只读模式不修改会员');
$json = json_encode($report, JSON_UNESCAPED_UNICODE);
foreach ([$union, $openid, $member['weapp_openid'], 'SENSITIVE_SECRET', 'SENSITIVE_TOKEN'] as $private) identityCheck(!str_contains($json, $private), '报告不泄露原始身份或凭证');
identityCheck($report['关联会员_脱敏'][0]['wx_unionid'] === $report['关联粉丝_脱敏'][0]['unionid'], '相同身份使用一致脱敏指纹');
identityCheck(count($diagnostic->sites()) === 1, '可列出站点供用户选择');

$linked = array_replace($member, ['wx_openid' => $openid]);
[$diagnostic] = identityFixture([$linked], [$fan]);
identityCheck(in_array('LOCAL_LINK_MATCHES', identityCodes($diagnostic->report(1, 'member-id', '10')), true), '支持按会员ID确认已有本地关联');
identityCheck(count($diagnostic->report(1, 'weapp-openid', $member['weapp_openid'])['关联粉丝_脱敏']) === 1, '从小程序OpenID找到同一关联范围');

[$diagnostic] = identityFixture([$member, array_replace($member, ['member_id' => 11, 'wx_unionid' => 'other-union', 'wx_openid' => $openid])], [$fan]);
$codes = identityCodes($diagnostic->report(1, 'unionid', $union));
identityCheck(in_array('MULTIPLE_MEMBERS', $codes, true) && in_array('UNIONID_CONFLICT', $codes, true), '候选OpenID被另一会员占用时提示冲突');
identityCheck(!in_array('LOCAL_LINK_CANDIDATE', $codes, true), '冲突身份不标为单一关联候选');

[$diagnostic] = identityFixture([array_replace($member, ['wx_openid' => 'wrong-openid'])], [$fan]);
identityCheck(in_array('OPENID_CONFLICT', identityCodes($diagnostic->report(1, 'unionid', $union)), true), '识别已手工写入的冲突OpenID');

[$diagnostic] = identityFixture([$member], [$fan, array_replace($fan, ['fans_id' => 21, 'openid' => 'second-openid'])]);
identityCheck(in_array('MULTIPLE_FANS', identityCodes($diagnostic->report(1, 'unionid', $union)), true), '多粉丝记录不任意挑选第一条');

[$diagnostic] = identityFixture([$member], [array_replace($fan, ['app_id' => 'wx-other-account', 'is_subscribe' => 0])]);
$codes = identityCodes($diagnostic->report(1, 'unionid', $union));
identityCheck(in_array('FAN_APPID_MISMATCH', $codes, true) && in_array('LOCALLY_UNSUBSCRIBED', $codes, true), '区分公众号归属与本地关注状态');

[$diagnostic] = identityFixture([$member], [array_replace($fan, ['openid' => '', 'app_id' => '0'])]);
$codes = identityCodes($diagnostic->report(1, 'unionid', $union));
identityCheck(in_array('FAN_OPENID_MISSING', $codes, true) && in_array('FAN_APPID_UNKNOWN', $codes, true), '识别粉丝入库缺少关键字段');

[$diagnostic] = identityFixture([$member, array_replace($member, ['member_id' => 12, 'site_id' => 2])], [array_replace($fan, ['site_id' => 2])]);
$report = $diagnostic->report(1, 'unionid', $union);
identityCheck(count($report['关联会员_脱敏']) === 1 && $report['关联粉丝_脱敏'] === [], '相同UnionID不跨站点串联');
identityCheck(in_array('FAN_NOT_FOUND_LOCALLY', identityCodes($report), true), '本地没有粉丝不宣称用户未关注');

[$diagnostic] = identityFixture([$member], [array_replace($fan, ['unionid' => strtoupper($union)])]);
identityCheck($diagnostic->report(1, 'unionid', $union)['关联粉丝_脱敏'] === [], '忽略数据库默认排序规则并精确区分身份大小写');

[$diagnostic] = identityFixture([$member], [$fan], true);
identityCheck(in_array('SCHEMA_INCOMPLETE', identityCodes($diagnostic->report(1, 'member-id', '10')), true), '缺失字段时提示表结构问题而非直接报SQL错误');

[$diagnostic] = identityFixture([$linked], [$fan]);
identityCheck(in_array('INPUT_MAY_BE_OPENID', identityCodes($diagnostic->report(1, 'unionid', $openid)), true), '发现把OpenID当UnionID输入的情况');

$many = [];
for ($id = 1; $id <= 51; $id++) $many[] = array_replace($member, ['member_id' => $id]);
[$diagnostic] = identityFixture($many, [$fan]);
identityCheck(in_array('RESULT_TRUNCATED', identityCodes($diagnostic->report(1, 'unionid', $union)), true), '超过明细上限明确提示截断');

foreach ([['--update_wx_openid=1'], ['--site-id=1', '--unionid=x', '--member-id=10'], ['--site-id=0', '--unionid=x'], ['--site-id=1', '--unionid=x', '--site-id=2']] as $args) {
    try { hsxIdentityOptions($args); throw new RuntimeException('Unexpected accepted options'); }
    catch (HsxIdentityDiagnosticError $expected) { identityCheck(true, '拒绝写入开关或歧义参数'); }
}
ob_start(); $status = hsxIdentityMain(['--update_wx_openid=1']); $error = ob_get_clean();
identityCheck($status === 1 && str_contains($error, '没有更新'), '无效参数在连接数据库之前停止');
identityCheck(HsxWechatIdentityDiagnostic::mask('') === '(空)', '未获取到标识明确显示为空');

$failure = hsxIdentityErrorReport(new Error('Call to undefined function env()'), '读取数据库配置');
identityCheck(str_contains($failure['说明'], 'env辅助函数未加载') && $failure['失败阶段'] === '读取数据库配置', '缺失env函数时明确提示失败阶段与原因');
identityCheck($failure['报告版本'] === '1.1' && $failure['错误位置']['行号'] > 0 && !str_contains($failure['错误位置']['文件'], '/'), '失败报告带版本及代码位置但不泄露服务器目录');
foreach ([new PDOException('SELECT SENSITIVE_SECRET FROM member WHERE wx_unionid=SENSITIVE_UNION'), new Error('SENSITIVE_TOKEN')] as $exception) {
    identityCheck(!str_contains(json_encode(hsxIdentityErrorReport($exception, '连接数据库')), 'SENSITIVE_'), '未知异常不输出原始SQL或敏感内容');
}

// Exercise the real framework/config bootstrap using in-memory environment values, never .env or MySQL.
$projectRoot = dirname(__DIR__, 3);
require_once $projectRoot . '/vendor/autoload.php';
$app = new \think\App($projectRoot);
$app->env->set(['DATABASE' => ['driver' => 'mysql', 'type' => 'mysql', 'hostname' => 'fixture.invalid', 'database' => 'identity_fixture', 'username' => 'fixture_user', 'password' => 'FIXTURE_SECRET', 'hostport' => '3306', 'charset' => 'utf8mb4', 'prefix' => 'fixture_'], 'APP_DEBUG' => false]);
identityCheck(!function_exists('env'), '复现Composer自动加载不包含env辅助函数的启动条件');
$database = hsxIdentityReadDatabaseConfig($app);
identityCheck(function_exists('env') && $database['connections']['mysql']['database'] === 'identity_fixture', '只加载框架辅助函数即可读取实际数据库配置文件');
identityCheck($database['connections']['mysql']['password'] === 'FIXTURE_SECRET' && $database['connections']['mysql']['prefix'] === 'fixture_', '数据库配置使用隔离环境值而非真实业务凭证');
identityCheck(!$app->initialized(), '加载配置未初始化应用或触发业务服务');
identityCheck(hsxIdentityReadDatabaseConfig($app) === $database, '重复加载配置不会产生函数重复定义');
echo "Completed {$checks} isolated checks. No business database, network requests or writes.\n";
