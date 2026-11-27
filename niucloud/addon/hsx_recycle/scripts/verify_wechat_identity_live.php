<?php
declare(strict_types=1);

// In-memory SQLite and Guzzle mock responses only. Never load .env or contact WeChat.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require __DIR__ . '/diagnose_wechat_identity.php';
require __DIR__ . '/diagnose_wechat_identity_live.php';
require dirname(__DIR__, 3) . '/vendor/autoload.php';

$checks = 0;
function liveCheck(bool $condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
    $GLOBALS['checks']++;
    echo 'PASS ' . $label . PHP_EOL;
}
function liveReject(callable $operation, string $label): void
{
    try { $operation(); }
    catch (HsxIdentityDiagnosticError $expected) { liveCheck(true, $label); return; }
    throw new RuntimeException($label);
}
function liveFixture(array $configs = []): PDO
{
    $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE t_sys_config (site_id INTEGER, config_key TEXT, value TEXT)');
    $pdo->exec('CREATE TABLE t_member (member_id INTEGER, site_id INTEGER, wx_unionid TEXT COLLATE NOCASE, wx_openid TEXT COLLATE NOCASE, weapp_openid TEXT, status INTEGER, is_del INTEGER, delete_time INTEGER)');
    $pdo->exec('CREATE TABLE t_wechat_fans (fans_id INTEGER, site_id INTEGER, unionid TEXT COLLATE NOCASE, openid TEXT COLLATE NOCASE, app_id INTEGER, is_subscribe INTEGER, update_time INTEGER)');
    foreach ($configs as $config) $pdo->prepare('INSERT INTO t_sys_config VALUES (1,?,?)')->execute(['WECHAT', json_encode($config)]);
    $insert = $pdo->prepare('INSERT INTO t_member VALUES (?,?,?,?,?,?,?,?)');
    $insert->execute([10, 1, 'old-member-union', 'official-test-openid', 'mini-first-openid', 1, 0, 0]);
    $insert->execute([20, 1, 'other-union', '', 'mini-second-openid', 1, 0, 0]);
    $insert->execute([30, 1, 'live-current-union', '', 'mini-third-openid', 1, 0, 0]);
    $insert->execute([40, 2, 'live-current-union', 'official-test-openid', 'mini-other-site', 1, 0, 0]);
    $pdo->prepare('INSERT INTO t_wechat_fans VALUES (?,?,?,?,?,?,?)')->execute([50, 1, 'live-current-union', 'official-test-openid', 0, 1, 100]);
    $pdo->exec('PRAGMA query_only=ON');
    return $pdo;
}

$config = ['app_id' => 'wx1234567890test', 'app_secret' => 'SENSITIVE_APP_SECRET'];
$pdo = liveFixture([$config]);
$credentials = hsxLiveCredentials($pdo, 't_', 1);
liveCheck($credentials === $config, '只读取指定站点的唯一公众号配置');
liveReject(fn() => hsxLiveCredentials($pdo, 't_', 2), '不借用其他站点凭据');
liveReject(fn() => hsxLiveCredentials(liveFixture([$config, $config]), 't_', 1), '重复公众号配置时停止');
liveReject(fn() => hsxLiveCredentials(liveFixture([array_merge($config, ['is_authorization' => 1])]), 't_', 1), '代授权配置不擅自刷新授权');
liveReject(fn() => hsxLiveCredentials(liveFixture([['app_id' => 'wx123']]), 't_', 1), '缺少密钥在请求前停止');

$options = hsxLiveOptions(['--site-id=1', '--wx-openid=official-test-openid', '--compare-members=10,20', '--verify-wechat']);
liveCheck($options['compare-members'] === [10, 20], '对照会员参数正确解析');
foreach ([['--site-id=1', '--wx-openid=x'], ['--site-id=1', '--unionid=x', '--verify-wechat'], ['--site-id=1', '--wx-openid=x', '--verify-wechat', '--update=1'], ['--site-id=1', '--wx-openid=x', '--verify-wechat', '--compare-members=0'], ['--site-id=1', '--wx-openid=x', '--verify-wechat', '--compare-members=1', '--compare-members=2']] as $args) {
    liveReject(fn() => hsxLiveOptions($args), '无明确联网选择或参数错误时停止');
}

$history = [];
$mock = new \GuzzleHttp\Handler\MockHandler([
    new \GuzzleHttp\Psr7\Response(200, [], json_encode(['access_token' => 'SENSITIVE_ACCESS_TOKEN', 'expires_in' => 7200])),
    new \GuzzleHttp\Psr7\Response(200, [], json_encode(['openid' => 'official-test-openid', 'unionid' => 'live-current-union', 'subscribe' => 1, 'nickname' => 'SENSITIVE_NAME', 'remark' => 'SENSITIVE_PHONE'])),
]);
$stack = \GuzzleHttp\HandlerStack::create($mock);
$stack->push(\GuzzleHttp\Middleware::history($history));
$client = new \GuzzleHttp\Client(['handler' => $stack]);
$stage = '';
$live = hsxLiveLookup($credentials, 'official-test-openid', fn($method, $path, $params) => hsxLiveWechatRequest($method, $path, $params, $client), $stage);
liveCheck(count($history) === 2, '只调用凭据接口和单个粉丝接口各一次');
$tokenRequest = $history[0]['request'];
$tokenBody = json_decode((string)$tokenRequest->getBody(), true);
liveCheck($tokenRequest->getMethod() === 'POST' && $tokenRequest->getUri()->getPath() === '/cgi-bin/stable_token' && $tokenBody['force_refresh'] === false, '不使用旧Token接口或强制刷新');
liveCheck($tokenRequest->getUri()->getHost() === 'api.weixin.qq.com' && $tokenRequest->getUri()->getScheme() === 'https' && $history[0]['options']['verify'] === true && $history[0]['options']['allow_redirects'] === false, '凭据仅通过校验证书的微信HTTPS地址发送且不跟随跳转');
parse_str($history[1]['request']->getUri()->getQuery(), $userQuery);
liveCheck($userQuery['openid'] === 'official-test-openid' && $history[1]['request']->getUri()->getPath() === '/cgi-bin/user/info', '用户查询使用当前请求OpenID');
liveCheck(array_keys($live) === ['openid', 'unionid', 'subscribe'], '丢弃微信响应里的姓名备注等其他个人信息');

$before = $pdo->query('SELECT * FROM t_member')->fetchAll(PDO::FETCH_ASSOC);
$snapshot = hsxLiveSnapshot($pdo, 't_', 1, $live['openid'], [10, 20, 99], $live['unionid']);
liveCheck(array_map('intval', array_column($snapshot['members'], 'member_id')) === [10, 20, 30], '同时查找指定会员和当前UnionID匹配会员且不跨租户');
$report = hsxLiveReport(1, $config['app_id'], $live, $snapshot, [10, 20, 99]);
liveCheck($report['会员对照'][0]['UnionID对照'] === '不一致，不得直接覆盖' && $report['粉丝对照'][0]['UnionID对照'] === '一致', '识别粉丝信息正确而会员UnionID不一致');
liveCheck($report['会员对照'][2]['UnionID对照'] === '一致' && $report['会员对照'][2]['公众号OpenID对照'] === '本地未保存', '显示其他候选会员但不回填');
liveCheck($report['本站未找到的对照会员ID'] === [99], '明确缺失对照会员');
liveCheck($pdo->query('SELECT * FROM t_member')->fetchAll(PDO::FETCH_ASSOC) === $before, '查询前后会员数据不变');
$json = json_encode($report, JSON_UNESCAPED_UNICODE);
foreach (['official-test-openid', 'live-current-union', 'old-member-union', 'mini-first-openid', 'SENSITIVE_APP_SECRET', 'SENSITIVE_ACCESS_TOKEN', 'SENSITIVE_NAME', 'SENSITIVE_PHONE'] as $private) liveCheck(!str_contains($json, $private), '报告中身份脱敏且不包含凭据和个人资料');
liveCheck(hsxLiveSnapshot($pdo, 't_', 1, 'OFFICIAL-TEST-OPENID', [])['members'] === [], '大小写不一致的OpenID不能匹配');

$noUnionReport = hsxLiveReport(1, $config['app_id'], ['openid' => $live['openid'], 'unionid' => '', 'subscribe' => 0], $snapshot, [10]);
liveCheck($noUnionReport['会员对照'][0]['UnionID对照'] === '微信未返回，无法判断' && $noUnionReport['微信当前返回']['当前已关注'] === false, '微信缺少UnionID时不使用旧值代替或推断冲突');
$changedLive = array_replace($live, ['unionid' => 'old-member-union']);
liveCheck(hsxLiveReport(1, $config['app_id'], $changedLive, $snapshot, [])['粉丝对照'][0]['UnionID对照'] === '不一致，不得直接覆盖', '识别粉丝表UnionID与微信当前值不一致');

foreach ([['openid' => 'wrong-openid', 'subscribe' => 1], ['openid' => $live['openid']], ['openid' => $live['openid'], 'subscribe' => 1, 'unionid' => []], ['errcode' => 40003, 'errmsg' => 'SENSITIVE_ERROR']] as $response) {
    $calls = 0;
    $fake = static function () use (&$calls, $response): array { return ++$calls === 1 ? ['access_token' => 'TOKEN'] : $response; };
    liveReject(fn() => hsxLiveLookup($config, $live['openid'], $fake, $stage), '返回身份或状态无效时停止，不猜测');
}
$calls = 0;
$fakeFailure = static function () use (&$calls): array { $calls++; return ['errcode' => 40164, 'errmsg' => 'SENSITIVE_ERROR_IP']; };
liveReject(fn() => hsxLiveLookup($config, $live['openid'], $fakeFailure, $stage), '白名单错误时输出安全提示');
liveCheck($calls === 1, 'Token请求失败后不继续查询或自动重试');
liveReject(fn() => hsxLiveWechatRequest('POST', '/cgi-bin/message/template/send', [], $client), '拒绝消息发送路径');
$redirectClient = new \GuzzleHttp\Client(['handler' => new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(302, ['Location' => 'https://example.invalid'])])]);
liveReject(fn() => hsxLiveWechatRequest('POST', '/cgi-bin/stable_token', [], $redirectClient), 'HTTP跳转不能带着密钥离开微信域名');
$badJsonClient = new \GuzzleHttp\Client(['handler' => new \GuzzleHttp\Handler\MockHandler([new \GuzzleHttp\Psr7\Response(200, [], 'SENSITIVE_NON_JSON')])]);
liveReject(fn() => hsxLiveWechatRequest('GET', '/cgi-bin/user/info', [], $badJsonClient), '非JSON返回不作为有效身份');
$exceptionClient = new \GuzzleHttp\Client(['handler' => new \GuzzleHttp\Handler\MockHandler([new RuntimeException('SENSITIVE_TOKEN_URL')])]);
try { hsxLiveWechatRequest('GET', '/cgi-bin/user/info', [], $exceptionClient); }
catch (HsxIdentityDiagnosticError $error) { liveCheck(!str_contains($error->getMessage(), 'SENSITIVE'), 'HTTP异常不泄露Token网址'); }

ob_start(); $status = hsxLiveMain(['--help']); $help = ob_get_clean();
liveCheck($status === 0 && str_contains($help, '--verify-wechat'), '帮助入口不会连接数据库或微信');
ob_start(); $status = hsxLiveMain(['--site-id=1', '--wx-openid=x']); $denied = ob_get_clean();
liveCheck($status === 1 && str_contains($denied, '--verify-wechat'), '未显式开启实时核验时在读取环境前停止');
echo "Completed {$checks} isolated checks. No real credentials, business database or network requests.\n";
