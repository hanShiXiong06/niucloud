<?php
declare(strict_types=1);

// CLI only. Explicitly opted-in WeChat lookup; no business data or token-cache writes.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

const HSX_WECHAT_LIVE_VERSION = '1.0';

function hsxLiveOptions(array $arguments): array
{
    $base = []; $members = []; $verify = false; $seen = [];
    foreach ($arguments as $argument) {
        if ($argument === '--verify-wechat') {
            if ($verify) throw new HsxIdentityDiagnosticError('不能重复填写 --verify-wechat。');
            $verify = true;
        } elseif (str_starts_with($argument, '--compare-members=')) {
            if (isset($seen['members'])) throw new HsxIdentityDiagnosticError('不能重复填写 --compare-members。');
            $seen['members'] = true;
            $members = explode(',', substr($argument, strlen('--compare-members=')));
            if (count($members) > 10) throw new HsxIdentityDiagnosticError('一次最多对照10个会员。');
            foreach ($members as $id) {
                if (!ctype_digit($id) || filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                    throw new HsxIdentityDiagnosticError('对照会员必须为正整数，用英文逗号分隔。');
                }
            }
            $members = array_values(array_unique(array_map('intval', $members)));
        } else {
            $base[] = $argument;
        }
    }
    $options = hsxIdentityOptions($base ?: ['--help']);
    if (isset($options['help'])) return $options;
    if (isset($options['list-sites']) || !isset($options['wx-openid'])) throw new HsxIdentityDiagnosticError('实时核验只支持 --site-id 和 --wx-openid，不接受UnionID或小程序OpenID作为查询入口。');
    if (!$verify) throw new HsxIdentityDiagnosticError('加上 --verify-wechat 才会访问微信：获取接口凭据并查询一名粉丝，不写数据库、不发消息。');
    $options['compare-members'] = $members;
    return $options;
}

function hsxLiveRows(PDO $pdo, string $sql, array $parameters): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    return $statement->fetchAll(PDO::FETCH_ASSOC);
}

function hsxLiveCredentials(PDO $pdo, string $prefix, int $siteId): array
{
    if (!preg_match('/^[a-zA-Z0-9_]*$/D', $prefix)) throw new HsxIdentityDiagnosticError('数据库表前缀不符合预期。');
    $rows = hsxLiveRows($pdo, "SELECT `value` FROM `{$prefix}sys_config` WHERE site_id = ? AND config_key = ? LIMIT 2", [$siteId, 'WECHAT']);
    if (count($rows) !== 1) throw new HsxIdentityDiagnosticError('公众号配置缺失或重复，已停止微信请求，不会任意选择一条配置。');
    $config = json_decode((string)$rows[0]['value'], true);
    if (!is_array($config)) throw new HsxIdentityDiagnosticError('公众号配置不是有效JSON。');
    if (!empty($config['is_authorization'])) throw new HsxIdentityDiagnosticError('本站使用第三方代授权，本脚本不操作授权刷新凭据，已停止。');
    if (!is_string($config['app_id'] ?? null) || !preg_match('/^wx[a-zA-Z0-9]+$/D', $config['app_id']) || !is_string($config['app_secret'] ?? null) || $config['app_secret'] === '') {
        throw new HsxIdentityDiagnosticError('公众号AppID或AppSecret配置不完整。');
    }
    return ['app_id' => $config['app_id'], 'app_secret' => $config['app_secret']];
}

function hsxLiveApiError(array $response): void
{
    if (!isset($response['errcode'])) return;
    if (!is_int($response['errcode']) && !(is_string($response['errcode']) && preg_match('/^-?\d+$/D', $response['errcode']))) {
        throw new HsxIdentityDiagnosticError('微信接口错误码格式异常，已停止。');
    }
    $code = (int)$response['errcode'];
    if ($code === 0) return;
    $hints = [
        -1 => '微信服务暂时繁忙，请稍后再查。',
        40001 => '接口凭据无效，请核对公众号配置。',
        40003 => 'OpenID无效或不属于当前公众号，请核对标识来源。',
        40013 => '公众号AppID无效。',
        40014 => '接口凭据无效，本脚本不会强制刷新。',
        40125 => '公众号AppSecret无效，请在后台核对，不要发送密钥。',
        40164 => '服务器出口IP不在公众号IP白名单，请在公众号后台核对。',
        42001 => '接口凭据已过期，本脚本不会强制刷新或自动重试。',
        45009 => '微信接口调用额度已用尽，请勿连续重试。',
        48001 => '当前公众号没有该接口权限。',
    ];
    throw new HsxIdentityDiagnosticError('微信错误码 ' . $code . '：' . ($hints[$code] ?? '请反馈此错误码，原始返回已隐藏。'), $code);
}

function hsxLiveWechatRequest(string $method, string $path, array $parameters, ?\GuzzleHttp\ClientInterface $client = null): array
{
    if (!in_array($method . ' ' . $path, ['POST /cgi-bin/stable_token', 'GET /cgi-bin/user/info'], true)) {
        throw new HsxIdentityDiagnosticError('诊断只允许获取稳定版接口凭据和查询单个粉丝。');
    }
    $options = ['allow_redirects' => false, 'verify' => true, 'http_errors' => false, 'connect_timeout' => 5, 'timeout' => 15, 'proxy' => '', 'debug' => false];
    $options[$method === 'POST' ? 'json' : 'query'] = $parameters;
    try {
        $response = ($client ?? new \GuzzleHttp\Client())->request($method, 'https://api.weixin.qq.com' . $path, $options);
        if ($response->getStatusCode() !== 200) throw new HsxIdentityDiagnosticError('微信接口HTTP状态码 ' . $response->getStatusCode() . '，已停止，不跟随跳转。');
        $body = (string)$response->getBody();
        if (strlen($body) > 1048576) throw new HsxIdentityDiagnosticError('微信接口返回体积异常，已停止。');
        $data = json_decode($body, true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) throw new HsxIdentityDiagnosticError('微信接口未返回有效JSON，原始内容已隐藏。');
        return $data;
    } catch (HsxIdentityDiagnosticError $error) {
        throw $error;
    } catch (Throwable $error) {
        // HTTP exception messages can contain the access_token URL. Never forward them.
        throw new HsxIdentityDiagnosticError('微信HTTPS请求失败，请检查服务器网络、证书和PHP网络扩展；原始异常已隐藏。');
    }
}

function hsxLiveLookup(array $config, string $openid, callable $request, string &$stage): array
{
    $stage = '获取微信稳定版接口凭据_非强制刷新';
    $token = $request('POST', '/cgi-bin/stable_token', ['grant_type' => 'client_credential', 'appid' => $config['app_id'], 'secret' => $config['app_secret'], 'force_refresh' => false]);
    hsxLiveApiError($token);
    if (!is_string($token['access_token'] ?? null) || $token['access_token'] === '') throw new HsxIdentityDiagnosticError('微信未返回有效接口凭据，已停止。');
    $stage = '向微信查询当前公众号粉丝身份';
    $user = $request('GET', '/cgi-bin/user/info', ['access_token' => $token['access_token'], 'openid' => $openid, 'lang' => 'zh_CN']);
    hsxLiveApiError($user);
    if (!is_string($user['openid'] ?? null) || $user['openid'] !== $openid) throw new HsxIdentityDiagnosticError('微信返回的OpenID与查询值不一致，不能继续比对。');
    if (!in_array($user['subscribe'] ?? null, [0, 1, '0', '1'], true)) throw new HsxIdentityDiagnosticError('微信未返回有效关注状态，不能把未知状态当成未关注。');
    $union = $user['unionid'] ?? '';
    if (!is_string($union) || ($union !== '' && !preg_match('/^[a-zA-Z0-9_-]{1,255}$/D', $union))) throw new HsxIdentityDiagnosticError('微信返回的UnionID格式异常，已停止。');
    // Ignore all other personal fields in the WeChat response.
    return ['openid' => $openid, 'unionid' => $union, 'subscribe' => (int)$user['subscribe']];
}

function hsxLiveSnapshot(PDO $pdo, string $prefix, int $siteId, string $openid, array $memberIds, string $unionid = ''): array
{
    if (!preg_match('/^[a-zA-Z0-9_]*$/D', $prefix)) throw new HsxIdentityDiagnosticError('数据库表前缀不符合预期。');
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $exact = static fn(string $field): string => $mysql ? "CAST(`{$field}` AS BINARY) = CAST(? AS BINARY)" : "`{$field}` COLLATE BINARY = ?";
    $memberWhere = [$exact('wx_openid')]; $memberParams = [$siteId, $openid];
    $fanWhere = [$exact('openid')]; $fanParams = [$siteId, $openid];
    if ($memberIds) {
        $memberWhere[] = 'member_id IN (' . implode(',', array_fill(0, count($memberIds), '?')) . ')';
        $memberParams = array_merge($memberParams, $memberIds);
    }
    if ($unionid !== '') {
        $memberWhere[] = $exact('wx_unionid'); $memberParams[] = $unionid;
        $fanWhere[] = $exact('unionid'); $fanParams[] = $unionid;
    }
    $members = hsxLiveRows($pdo, "SELECT member_id, wx_unionid, wx_openid, weapp_openid, status, is_del, delete_time FROM `{$prefix}member` WHERE site_id = ? AND (" . implode(' OR ', $memberWhere) . ') ORDER BY member_id LIMIT 51', $memberParams);
    $fans = hsxLiveRows($pdo, "SELECT fans_id, unionid, openid, app_id, is_subscribe, update_time FROM `{$prefix}wechat_fans` WHERE site_id = ? AND (" . implode(' OR ', $fanWhere) . ') ORDER BY fans_id LIMIT 51', $fanParams);
    if (count($members) > 50 || count($fans) > 50) throw new HsxIdentityDiagnosticError('同一身份命中超过50条记录，已停止，不能据此自动关联。');
    return ['members' => $members, 'fans' => $fans];
}

function hsxLiveReport(int $siteId, string $appId, array $live, array $snapshot, array $memberIds): array
{
    $compare = static function ($saved, string $current): string {
        if ($current === '') return '微信未返回，无法判断';
        if ((string)($saved ?? '') === '') return '本地未保存';
        return $saved === $current ? '一致' : '不一致，不得直接覆盖';
    };
    $members = [];
    foreach ($snapshot['members'] as $member) {
        $members[] = [
            '会员ID' => (int)$member['member_id'], '指定对照会员' => in_array((int)$member['member_id'], $memberIds, true),
            '本地UnionID' => HsxWechatIdentityDiagnostic::mask($member['wx_unionid']), 'UnionID对照' => $compare($member['wx_unionid'], $live['unionid']),
            '本地公众号OpenID' => HsxWechatIdentityDiagnostic::mask($member['wx_openid']), '公众号OpenID对照' => $compare($member['wx_openid'], $live['openid']),
            '本地小程序OpenID' => HsxWechatIdentityDiagnostic::mask($member['weapp_openid']),
            '会员有效' => (int)$member['status'] === 1 && empty($member['is_del']) && empty($member['delete_time']),
        ];
    }
    $fans = [];
    foreach ($snapshot['fans'] as $fan) {
        $fans[] = [
            '粉丝ID' => (int)$fan['fans_id'], '本地UnionID' => HsxWechatIdentityDiagnostic::mask($fan['unionid']), 'UnionID对照' => $compare($fan['unionid'], $live['unionid']),
            '本地OpenID' => HsxWechatIdentityDiagnostic::mask($fan['openid']), 'OpenID对照' => $compare($fan['openid'], $live['openid']),
            '本地AppID' => HsxWechatIdentityDiagnostic::mask($fan['app_id']), '本地关注状态' => (int)$fan['is_subscribe'], '本地更新时间' => $fan['update_time'],
        ];
    }
    return [
        '实时核验版本' => HSX_WECHAT_LIVE_VERSION, '完成' => true, '时间_UTC' => gmdate('c'), '站点ID' => $siteId,
        '核验公众号AppID' => HsxWechatIdentityDiagnostic::mask($appId),
        '微信当前返回' => ['OpenID' => HsxWechatIdentityDiagnostic::mask($live['openid']), 'UnionID' => HsxWechatIdentityDiagnostic::mask($live['unionid']), '当前已关注' => $live['subscribe'] === 1],
        '会员对照' => $members, '粉丝对照' => $fans,
        '本站未找到的对照会员ID' => array_values(array_diff($memberIds, array_column($members, '会员ID'))),
        '结论边界' => '仅核验当前公众号返回的身份；没有实时验证小程序登录身份或开放平台绑定。即使匹配，也不据此合并会员、覆盖UnionID或更改订单归属。',
        '数据保护' => '数据库只读；未同步粉丝、未写会员或配置、未发送消息。仅获取稳定版接口凭据（force_refresh=false）并查询一名粉丝，未调用旧版token接口，未写凭据缓存，报告不输出密钥、Token、姓名或手机号。',
    ];
}

function hsxLiveMain(array $arguments): int
{
    ini_set('display_errors', '0');
    $pdo = null; $stage = '加载原只读诊断脚本';
    try {
        $base = __DIR__ . '/diagnose_wechat_identity.php';
        if (!is_file($base)) throw new RuntimeException('Missing base diagnostic');
        require_once $base;
        if (!function_exists('hsxIdentityErrorReport') || !function_exists('hsxIdentityReadDatabaseConfig')) throw new HsxIdentityDiagnosticError('请将本文件与已修复的v1.1只读诊断脚本放在同一目录。');
        $stage = '检查命令参数';
        $options = hsxLiveOptions($arguments ?: ['--help']);
        if (isset($options['help'])) {
            echo "微信身份实时核验 v" . HSX_WECHAT_LIVE_VERSION . "（服务器终端，PHP 8+）\n";
            echo "与 diagnose_wechat_identity.php v1.1 放在同一目录。\n";
            echo "php addon/hsx_recycle/scripts/diagnose_wechat_identity_live.php --site-id=站点ID --wx-openid=公众号OpenID --compare-members=会员ID1,会员ID2 --verify-wechat\n";
            echo "--verify-wechat 明确允许获取稳定版接口凭据（非强制刷新）和查询一名粉丝。\n";
            echo "不合并账号、不回填、不同步粉丝、不发消息、不写Token缓存。输出已脱敏。\n";
            return 0;
        }
        [$pdo, $prefix] = hsxIdentityConnect($options, $stage);
        $stage = '核对站点公众号配置及本地表结构';
        $config = hsxLiveCredentials($pdo, $prefix, (int)$options['site-id']);
        hsxLiveSnapshot($pdo, $prefix, (int)$options['site-id'], $options['wx-openid'], $options['compare-members']);
        $live = hsxLiveLookup($config, $options['wx-openid'], 'hsxLiveWechatRequest', $stage);
        $stage = '对照微信当前身份与本地记录';
        $snapshot = hsxLiveSnapshot($pdo, $prefix, (int)$options['site-id'], $options['wx-openid'], $options['compare-members'], $live['unionid']);
        $report = hsxLiveReport((int)$options['site-id'], $config['app_id'], $live, $snapshot, $options['compare-members']);
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
        return 0;
    } catch (Throwable $error) {
        $report = function_exists('hsxIdentityErrorReport') ? hsxIdentityErrorReport($error, $stage) : ['完成' => false, '失败阶段' => $stage, '说明' => '原诊断脚本缺失或无法解析，请将两个完整PHP文件放在同一目录。', '错误类型' => get_class($error)];
        $report['实时核验版本'] = HSX_WECHAT_LIVE_VERSION;
        $report['数据保护'] = '未执行数据库写入或消息发送；未自动刷新重试，未输出凭据或原始微信响应。';
        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
        return 1;
    } finally {
        try { if ($pdo && $pdo->inTransaction()) $pdo->rollBack(); }
        catch (Throwable $error) { fwrite(STDERR, "只读连接清理未完成，连接关闭后将释放；未执行数据写入。\n"); }
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) exit(hsxLiveMain(array_slice($argv, 1)));
