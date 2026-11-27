<?php
declare(strict_types=1);

/**
 * One-off test script:
 * Find a customer's openid by unionid and print concrete feedback.
 *
 * Usage:
 *   php scripts/find_openid_by_unionid.php --site_id=1 --unionid=YOUR_UNIONID
 *   php scripts/find_openid_by_unionid.php --site_id=1 --unionid=YOUR_UNIONID --json=1
 *   php scripts/find_openid_by_unionid.php --site_id=1 --unionid=YOUR_UNIONID --update_wx_openid=1
 *   php scripts/find_openid_by_unionid.php --site_id=1 --sync_official_fans=1 --update_member_wx_openid=1
 *   php scripts/find_openid_by_unionid.php --site_id=1 --diagnose_unionid=1
 *   php scripts/find_openid_by_unionid.php --site_id=1 --unionid=YOUR_UNIONID --db_name=DB --db_user=USER --db_pass=PASS
 *
 * Notes:
 * - Mini program subscribe messages use member.weapp_openid.
 * - Official account template messages use member.wx_openid or wechat_fans.openid.
 * - By default this script only reads data. Add --update_wx_openid=1 to update member.wx_openid.
 */

const EXIT_OK = 0;
const EXIT_USAGE = 1;
const EXIT_ERROR = 2;

const DEFAULT_DB_HOST = 'localhost';
const DEFAULT_DB_NAME = 'niushops';
const DEFAULT_DB_USER = 'niushops';
const DEFAULT_DB_PASS = 'Jb2P5x6B3JhZK37a';
const DEFAULT_DB_PORT = '3306';
const DEFAULT_DB_PREFIX = 'saas_';
const DEFAULT_DB_CHARSET = 'utf8mb4';

$basePath = dirname(__DIR__);
$options = getopt('', [
    'site_id:',
    'unionid:',
    'limit::',
    'json::',
    'help::',
    'db_host::',
    'db_name::',
    'db_user::',
    'db_pass::',
    'db_port::',
    'db_prefix::',
    'db_charset::',
    'update_wx_openid::',
    'overwrite_wx_openid::',
    'sync_official_fans::',
    'update_member_wx_openid::',
    'wechat_app_id::',
    'wechat_app_secret::',
    'access_token::',
    'max_openids::',
    'diagnose_unionid::',
]);

if (isset($options['help'])) {
    printUsage();
    exit(EXIT_OK);
}

$siteId = isset($options['site_id']) ? (int)$options['site_id'] : 0;
$unionid = trim((string)($options['unionid'] ?? ''));
$limit = isset($options['limit']) ? max(1, min(100, (int)$options['limit'])) : 20;
$asJson = (string)($options['json'] ?? '') === '1';
$updateWxOpenid = (string)($options['update_wx_openid'] ?? '') === '1';
$overwriteWxOpenid = (string)($options['overwrite_wx_openid'] ?? '') === '1';
$syncOfficialFans = (string)($options['sync_official_fans'] ?? '') === '1';
$updateMemberWxOpenid = (string)($options['update_member_wx_openid'] ?? '') === '1';
$maxOpenids = isset($options['max_openids']) ? max(0, (int)$options['max_openids']) : 0;
$diagnoseUnionid = (string)($options['diagnose_unionid'] ?? '') === '1';

if ($siteId <= 0 || (!$syncOfficialFans && !$diagnoseUnionid && $unionid === '')) {
    printUsage();
    exit(EXIT_USAGE);
}

try {
    $envPath = $basePath . '/.env';
    $env = loadEnv($envPath);
    $db = getDatabaseConfig($env, $envPath, $options);
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $db['hostname'],
            $db['hostport'],
            $db['database'],
            $db['charset']
        ),
        $db['username'],
        $db['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $memberTable = quoteTableName($db['prefix'] . 'member');
    $wechatFansTable = quoteTableName($db['prefix'] . 'wechat_fans');
    $sysConfigTable = quoteTableName($db['prefix'] . 'sys_config');

    if ($diagnoseUnionid) {
        $feedback = diagnoseUnionid($pdo, $memberTable, $wechatFansTable, $siteId);

        if ($asJson) {
            echo json_encode($feedback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
            exit(EXIT_OK);
        }

        printDiagnoseFeedback($feedback);
        exit(EXIT_OK);
    }

    if ($syncOfficialFans) {
        $syncResult = syncOfficialFans($pdo, $sysConfigTable, $wechatFansTable, $memberTable, $siteId, $options, $maxOpenids);
        $memberUpdateResult = [
            'enabled' => false,
            'message' => '未执行会员 wx_openid 批量回填。需要回填时请加 --update_member_wx_openid=1。',
        ];

        if ($updateMemberWxOpenid) {
            $memberUpdateResult = updateMembersWxOpenidFromFans($pdo, $memberTable, $wechatFansTable, $siteId, $overwriteWxOpenid);
        }

        $feedback = [
            'ok' => true,
            'site_id' => $siteId,
            'mode' => 'sync_official_fans',
            'sync_official_fans' => $syncResult,
            'update_member_wx_openid' => $memberUpdateResult,
        ];

        if ($asJson) {
            echo json_encode($feedback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
            exit(EXIT_OK);
        }

        printSyncFeedback($feedback);
        exit(EXIT_OK);
    }

    $members = fetchAll(
        $pdo,
        "SELECT member_id, site_id, member_no, nickname, mobile, wx_unionid, weapp_openid, wx_openid, status, delete_time
         FROM {$memberTable}
         WHERE site_id = :site_id
           AND wx_unionid = :unionid
           AND delete_time = 0
         ORDER BY member_id DESC
         LIMIT {$limit}",
        [
            ':site_id' => $siteId,
            ':unionid' => $unionid,
        ]
    );

    $fans = fetchAll(
        $pdo,
        "SELECT fans_id, site_id, openid, unionid, is_subscribe
         FROM {$wechatFansTable}
         WHERE site_id = :site_id
           AND unionid = :unionid
         ORDER BY is_subscribe DESC, fans_id DESC
         LIMIT {$limit}",
        [
            ':site_id' => $siteId,
            ':unionid' => $unionid,
        ]
    );

    $updateResult = null;
    if ($updateWxOpenid) {
        $updateResult = updateMemberWxOpenid($pdo, $memberTable, $siteId, $unionid, $members, $fans, $overwriteWxOpenid);
        $members = fetchAll(
            $pdo,
            "SELECT member_id, site_id, member_no, nickname, mobile, wx_unionid, weapp_openid, wx_openid, status, delete_time
             FROM {$memberTable}
             WHERE site_id = :site_id
               AND wx_unionid = :unionid
               AND delete_time = 0
             ORDER BY member_id DESC
             LIMIT {$limit}",
            [
                ':site_id' => $siteId,
                ':unionid' => $unionid,
            ]
        );
    }

    $feedback = buildFeedback($siteId, $unionid, $members, $fans, $updateResult);

    if ($asJson) {
        echo json_encode($feedback, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
        exit(EXIT_OK);
    }

    printFeedback($feedback);
    exit(EXIT_OK);
} catch (Throwable $e) {
    $error = [
        'ok' => false,
        'error' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ];

    if ($asJson) {
        echo json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL;
    } else {
        echo "执行失败\n";
        echo "错误信息: {$error['error']}\n";
        echo "位置: {$error['file']}:{$error['line']}\n";
    }
    exit(EXIT_ERROR);
}

function printUsage(): void
{
    echo "用法:\n";
    echo "  php scripts/find_openid_by_unionid.php --site_id=1 --unionid=YOUR_UNIONID\n";
    echo "  php scripts/find_openid_by_unionid.php --site_id=1 --unionid=YOUR_UNIONID --update_wx_openid=1\n";
    echo "  php scripts/find_openid_by_unionid.php --site_id=1 --sync_official_fans=1 --update_member_wx_openid=1\n";
    echo "  php scripts/find_openid_by_unionid.php --site_id=1 --diagnose_unionid=1\n";
    echo "  php scripts/find_openid_by_unionid.php --site_id=1 --unionid=YOUR_UNIONID --json=1\n\n";
    echo "脚本已内置默认数据库连接；如果需要覆盖，也可以直接传数据库参数:\n";
    echo "  php scripts/find_openid_by_unionid.php --site_id=1 --unionid=YOUR_UNIONID --db_host=127.0.0.1 --db_name=DB --db_user=USER --db_pass=PASS --db_prefix=saas_\n\n";
    echo "说明:\n";
    echo "  小程序推送要看 member.weapp_openid。\n";
    echo "  公众号消息要看 member.wx_openid 或 wechat_fans.openid。\n";
    echo "  默认只查询，不回填，不推送。\n";
    echo "  加 --update_wx_openid=1 后，会把 wechat_fans.openid 写入 member.wx_openid。\n";
    echo "  默认只补空 wx_openid；如需覆盖已有 wx_openid，再加 --overwrite_wx_openid=1。\n";
    echo "  加 --sync_official_fans=1 后，会静默同步公众号已关注粉丝到 wechat_fans。\n";
    echo "  同步粉丝默认从 sys_config 的 WECHAT 配置读取 app_id/app_secret；也可传 --wechat_app_id=xxx --wechat_app_secret=xxx。\n";
}

function loadEnv(string $path): array
{
    if (!is_file($path)) {
        throw new RuntimeException(".env 文件不存在: {$path}");
    }

    $result = [];
    $section = '';
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException(".env 文件读取失败: {$path}");
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (preg_match('/^\[(.+)]$/', $line, $matches)) {
            $section = strtoupper(trim($matches[1]));
            continue;
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $key = strtoupper($key);
        $value = trim($value, "\"'");
        $result[$key] = $value;
        if ($section !== '') {
            $result[$section . '.' . $key] = $value;
        }
    }

    return $result;
}

function getDatabaseConfig(array $env, string $envPath, array $options = []): array
{
    $config = [
        'hostname' => optionValue($options, 'db_host', firstEnvValue($env, ['DATABASE.HOSTNAME', 'DATABASE.HOST', 'DB_HOST', 'MYSQL_HOST', 'HOSTNAME'], DEFAULT_DB_HOST)),
        'database' => optionValue($options, 'db_name', firstEnvValue($env, ['DATABASE.DATABASE', 'DATABASE.NAME', 'DATABASE.DBNAME', 'DB_DATABASE', 'DATABASE_NAME', 'MYSQL_DATABASE', 'DATABASE'], DEFAULT_DB_NAME)),
        'username' => optionValue($options, 'db_user', firstEnvValue($env, ['DATABASE.USERNAME', 'DATABASE.USER', 'DB_USERNAME', 'DB_USER', 'MYSQL_USER', 'USERNAME'], DEFAULT_DB_USER)),
        'password' => optionValue($options, 'db_pass', firstEnvValue($env, ['DATABASE.PASSWORD', 'DATABASE.PWD', 'DB_PASSWORD', 'DB_PASS', 'MYSQL_PASSWORD', 'PASSWORD'], DEFAULT_DB_PASS)),
        'hostport' => optionValue($options, 'db_port', firstEnvValue($env, ['DATABASE.HOSTPORT', 'DATABASE.PORT', 'DB_PORT', 'MYSQL_PORT', 'HOSTPORT'], DEFAULT_DB_PORT)),
        'prefix' => optionValue($options, 'db_prefix', firstEnvValue($env, ['DATABASE.PREFIX', 'DB_PREFIX', 'PREFIX'], DEFAULT_DB_PREFIX)),
        'charset' => optionValue($options, 'db_charset', firstEnvValue($env, ['DATABASE.CHARSET', 'DB_CHARSET', 'CHARSET'], DEFAULT_DB_CHARSET)),
    ];

    if ($config['database'] === '') {
        $availableKeys = implode(', ', array_slice(array_keys($env), 0, 80));
        throw new RuntimeException(
            "数据库名为空，请检查 {$envPath} 的数据库配置。支持 DATABASE、[DATABASE] DATABASE、[DATABASE] NAME、DB_DATABASE。当前读取到的 key: {$availableKeys}"
        );
    }

    return $config;
}

function optionValue(array $options, string $key, string $default = ''): string
{
    if (array_key_exists($key, $options) && trim((string)$options[$key]) !== '') {
        return trim((string)$options[$key]);
    }

    return $default;
}

function firstEnvValue(array $env, array $keys, string $default = ''): string
{
    foreach ($keys as $key) {
        if (array_key_exists($key, $env) && trim((string)$env[$key]) !== '') {
            return trim((string)$env[$key]);
        }
    }

    return $default;
}

function quoteTableName(string $table): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new InvalidArgumentException("非法表名: {$table}");
    }
    return '`' . $table . '`';
}

function fetchAll(PDO $pdo, string $sql, array $params): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    return $statement->fetchAll();
}

function fetchOne(PDO $pdo, string $sql, array $params): array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $row = $statement->fetch();
    return $row ?: [];
}

function diagnoseUnionid(PDO $pdo, string $memberTable, string $wechatFansTable, int $siteId): array
{
    $memberWithUnionid = fetchOne(
        $pdo,
        "SELECT COUNT(*) AS total
         FROM {$memberTable}
         WHERE site_id = :site_id
           AND delete_time = 0
           AND wx_unionid <> ''",
        [':site_id' => $siteId]
    );

    $fansWithUnionid = fetchOne(
        $pdo,
        "SELECT COUNT(*) AS total
         FROM {$wechatFansTable}
         WHERE site_id = :site_id
           AND unionid <> ''",
        [':site_id' => $siteId]
    );

    $exactMatched = fetchOne(
        $pdo,
        "SELECT COUNT(*) AS total
         FROM {$memberTable} m
         INNER JOIN {$wechatFansTable} f
            ON f.site_id = m.site_id
           AND f.unionid = m.wx_unionid
         WHERE m.site_id = :site_id
           AND m.delete_time = 0
           AND m.wx_unionid <> ''
           AND f.unionid <> ''",
        [':site_id' => $siteId]
    );

    $trimMatched = fetchOne(
        $pdo,
        "SELECT COUNT(*) AS total
         FROM {$memberTable} m
         INNER JOIN {$wechatFansTable} f
            ON f.site_id = m.site_id
           AND TRIM(f.unionid) = TRIM(m.wx_unionid)
         WHERE m.site_id = :site_id
           AND m.delete_time = 0
           AND TRIM(m.wx_unionid) <> ''
           AND TRIM(f.unionid) <> ''",
        [':site_id' => $siteId]
    );

    $sampleMembers = fetchAll(
        $pdo,
        "SELECT member_id, nickname, mobile, weapp_openid, wx_unionid, wx_openid, CHAR_LENGTH(wx_unionid) AS unionid_len
         FROM {$memberTable}
         WHERE site_id = :site_id
           AND delete_time = 0
           AND wx_unionid <> ''
         ORDER BY member_id DESC
         LIMIT 10",
        [':site_id' => $siteId]
    );

    $sampleFans = fetchAll(
        $pdo,
        "SELECT fans_id, openid, unionid, is_subscribe, CHAR_LENGTH(unionid) AS unionid_len
         FROM {$wechatFansTable}
         WHERE site_id = :site_id
           AND unionid <> ''
         ORDER BY fans_id DESC
         LIMIT 10",
        [':site_id' => $siteId]
    );

    $matchedSamples = fetchAll(
        $pdo,
        "SELECT m.member_id, m.nickname, m.mobile, m.wx_unionid, f.fans_id, f.openid AS wx_openid_from_fans
         FROM {$memberTable} m
         INNER JOIN {$wechatFansTable} f
            ON f.site_id = m.site_id
           AND f.unionid = m.wx_unionid
         WHERE m.site_id = :site_id
           AND m.delete_time = 0
           AND m.wx_unionid <> ''
           AND f.unionid <> ''
         ORDER BY m.member_id DESC
         LIMIT 10",
        [':site_id' => $siteId]
    );

    return [
        'ok' => true,
        'site_id' => $siteId,
        'mode' => 'diagnose_unionid',
        'summary' => [
            'member_with_unionid' => (int)($memberWithUnionid['total'] ?? 0),
            'fans_with_unionid' => (int)($fansWithUnionid['total'] ?? 0),
            'exact_matched' => (int)($exactMatched['total'] ?? 0),
            'trim_matched' => (int)($trimMatched['total'] ?? 0),
        ],
        'sample_members' => $sampleMembers,
        'sample_fans' => $sampleFans,
        'matched_samples' => $matchedSamples,
    ];
}

function syncOfficialFans(PDO $pdo, string $sysConfigTable, string $wechatFansTable, string $memberTable, int $siteId, array $options, int $maxOpenids = 0): array
{
    $token = trim((string)($options['access_token'] ?? ''));
    $wechatConfig = [];

    if ($token === '') {
        $wechatConfig = getWechatConfig($pdo, $sysConfigTable, $siteId);
        $appId = optionValue($options, 'wechat_app_id', (string)($wechatConfig['app_id'] ?? ''));
        $appSecret = optionValue($options, 'wechat_app_secret', (string)($wechatConfig['app_secret'] ?? ''));

        if ($appId === '' || $appSecret === '') {
            throw new RuntimeException('公众号 app_id/app_secret 为空。请先配置站点微信公众号，或在命令中传 --wechat_app_id=xxx --wechat_app_secret=xxx。');
        }

        $tokenData = wechatGet('https://api.weixin.qq.com/cgi-bin/token', [
            'grant_type' => 'client_credential',
            'appid' => $appId,
            'secret' => $appSecret,
        ]);
        if (empty($tokenData['access_token'])) {
            throw new RuntimeException('获取公众号 access_token 失败: ' . json_encode($tokenData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $token = (string)$tokenData['access_token'];
    }

    $openids = [];
    $nextOpenid = '';
    $pageCount = 0;
    $totalFromWechat = null;

    do {
        $pageCount++;
        $listData = wechatGet('https://api.weixin.qq.com/cgi-bin/user/get', [
            'access_token' => $token,
            'next_openid' => $nextOpenid,
        ]);

        if (isset($listData['errcode']) && (int)$listData['errcode'] !== 0) {
            throw new RuntimeException('获取公众号粉丝列表失败: ' . json_encode($listData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $totalFromWechat = $listData['total'] ?? $totalFromWechat;
        $pageOpenids = $listData['data']['openid'] ?? [];
        if (is_array($pageOpenids) && $pageOpenids) {
            foreach ($pageOpenids as $openid) {
                $openid = trim((string)$openid);
                if ($openid === '') {
                    continue;
                }
                $openids[] = $openid;
                if ($maxOpenids > 0 && count($openids) >= $maxOpenids) {
                    break 2;
                }
            }
        }

        $nextOpenid = (string)($listData['next_openid'] ?? '');
        $count = (int)($listData['count'] ?? 0);
    } while ($nextOpenid !== '' && $count > 0);

    $openids = array_values(array_unique($openids));
    $chunks = array_chunk($openids, 100);
    $savedCount = 0;
    $updatedCount = 0;
    $insertedCount = 0;
    $withUnionidCount = 0;
    $withoutUnionidCount = 0;
    $subscribeCount = 0;
    $failedBatches = [];
    $matchedMemberCount = 0;

    foreach ($chunks as $index => $chunk) {
        $batchData = wechatPostJson('https://api.weixin.qq.com/cgi-bin/user/info/batchget?access_token=' . urlencode($token), [
            'user_list' => array_map(static fn(string $openid): array => [
                'openid' => $openid,
                'lang' => 'zh_CN',
            ], $chunk),
        ]);

        if (isset($batchData['errcode']) && (int)$batchData['errcode'] !== 0) {
            $failedBatches[] = [
                'batch' => $index + 1,
                'response' => $batchData,
            ];
            continue;
        }

        $userInfoList = $batchData['user_info_list'] ?? [];
        if (!is_array($userInfoList)) {
            continue;
        }

        foreach ($userInfoList as $userInfo) {
            if (!is_array($userInfo)) {
                continue;
            }
            $result = upsertWechatFan($pdo, $wechatFansTable, $siteId, $userInfo);
            $savedCount++;
            if ($result === 'inserted') {
                $insertedCount++;
            } else {
                $updatedCount++;
            }

            $unionid = trim((string)($userInfo['unionid'] ?? ''));
            if ($unionid !== '') {
                $withUnionidCount++;
            } else {
                $withoutUnionidCount++;
            }
            if ((int)($userInfo['subscribe'] ?? 0) === 1) {
                $subscribeCount++;
            }
        }
    }

    if ($withUnionidCount > 0) {
        $row = fetchOne(
            $pdo,
            "SELECT COUNT(*) AS total
             FROM {$wechatFansTable} f
             INNER JOIN {$memberTable} m
                ON m.site_id = f.site_id
               AND m.wx_unionid = f.unionid
               AND m.delete_time = 0
             WHERE f.site_id = :site_id
               AND f.unionid <> ''
               AND f.openid <> ''",
            [':site_id' => $siteId]
        );
        $matchedMemberCount = (int)($row['total'] ?? 0);
    }

    return [
        'total_from_wechat' => $totalFromWechat,
        'fetched_openid_count' => count($openids),
        'page_count' => $pageCount,
        'batch_count' => count($chunks),
        'saved_count' => $savedCount,
        'inserted_count' => $insertedCount,
        'updated_count' => $updatedCount,
        'subscribe_count' => $subscribeCount,
        'with_unionid_count' => $withUnionidCount,
        'without_unionid_count' => $withoutUnionidCount,
        'matched_member_count' => $matchedMemberCount,
        'failed_batch_count' => count($failedBatches),
        'failed_batches' => $failedBatches,
        'used_config' => [
            'from_access_token_option' => trim((string)($options['access_token'] ?? '')) !== '',
            'app_id' => maskString((string)($options['wechat_app_id'] ?? ($wechatConfig['app_id'] ?? ''))),
            'is_authorization' => (int)($wechatConfig['is_authorization'] ?? 0),
        ],
    ];
}

function getWechatConfig(PDO $pdo, string $sysConfigTable, int $siteId): array
{
    $row = fetchOne(
        $pdo,
        "SELECT value FROM {$sysConfigTable} WHERE site_id = :site_id AND config_key = 'WECHAT' LIMIT 1",
        [':site_id' => $siteId]
    );

    if (!$row) {
        return [];
    }

    $value = $row['value'] ?? '';
    if (is_array($value)) {
        return $value;
    }

    $decoded = json_decode((string)$value, true);
    return is_array($decoded) ? $decoded : [];
}

function upsertWechatFan(PDO $pdo, string $wechatFansTable, int $siteId, array $userInfo): string
{
    $openid = trim((string)($userInfo['openid'] ?? ''));
    if ($openid === '') {
        throw new RuntimeException('粉丝 openid 为空，无法写入 wechat_fans。');
    }

    $exists = fetchOne(
        $pdo,
        "SELECT fans_id FROM {$wechatFansTable} WHERE site_id = :site_id AND openid = :openid LIMIT 1",
        [
            ':site_id' => $siteId,
            ':openid' => $openid,
        ]
    );

    $data = [
        ':site_id' => $siteId,
        ':nickname' => trim((string)($userInfo['nickname'] ?? '')),
        ':avatar' => trim((string)($userInfo['headimgurl'] ?? '')),
        ':sex' => (int)($userInfo['sex'] ?? 0),
        ':language' => trim((string)($userInfo['language'] ?? '')),
        ':country' => trim((string)($userInfo['country'] ?? '')),
        ':province' => trim((string)($userInfo['province'] ?? '')),
        ':city' => trim((string)($userInfo['city'] ?? '')),
        ':openid' => $openid,
        ':unionid' => trim((string)($userInfo['unionid'] ?? '')),
        ':groupid' => (int)($userInfo['groupid'] ?? 0),
        ':is_subscribe' => (int)($userInfo['subscribe'] ?? 0),
        ':remark' => trim((string)($userInfo['remark'] ?? '')),
        ':subscribe_time' => (int)($userInfo['subscribe_time'] ?? 0),
        ':subscribe_scene' => trim((string)($userInfo['subscribe_scene'] ?? '')),
        ':unsubscribe_time' => 0,
        ':update_time' => time(),
    ];

    if ($exists) {
        $updateData = $data;
        unset($updateData[':openid']);
        $updateData[':fans_id'] = (int)$exists['fans_id'];
        $statement = $pdo->prepare(
            "UPDATE {$wechatFansTable}
             SET nickname = :nickname,
                 avatar = :avatar,
                 sex = :sex,
                 language = :language,
                 country = :country,
                 province = :province,
                 city = :city,
                 unionid = :unionid,
                 groupid = :groupid,
                 is_subscribe = :is_subscribe,
                 remark = :remark,
                 subscribe_time = :subscribe_time,
                 subscribe_scene = :subscribe_scene,
                 unsubscribe_time = :unsubscribe_time,
                 update_time = :update_time
             WHERE fans_id = :fans_id
               AND site_id = :site_id"
        );
        $statement->execute($updateData);
        return 'updated';
    }

    $statement = $pdo->prepare(
        "INSERT INTO {$wechatFansTable}
            (site_id, nickname, avatar, sex, language, country, province, city, openid, unionid, groupid, is_subscribe, remark, subscribe_time, subscribe_scene, unsubscribe_time, update_time)
         VALUES
            (:site_id, :nickname, :avatar, :sex, :language, :country, :province, :city, :openid, :unionid, :groupid, :is_subscribe, :remark, :subscribe_time, :subscribe_scene, :unsubscribe_time, :update_time)"
    );
    $statement->execute($data);
    return 'inserted';
}

function updateMembersWxOpenidFromFans(PDO $pdo, string $memberTable, string $wechatFansTable, int $siteId, bool $overwrite): array
{
    $matched = fetchOne(
        $pdo,
        "SELECT COUNT(*) AS total
         FROM {$memberTable} m
         INNER JOIN {$wechatFansTable} f
            ON f.site_id = m.site_id
           AND f.unionid = m.wx_unionid
         WHERE m.site_id = :site_id
           AND m.delete_time = 0
           AND m.wx_unionid <> ''
           AND f.openid <> ''
           AND f.is_subscribe = 1",
        [':site_id' => $siteId]
    );

    $sql = "UPDATE {$memberTable} m
            INNER JOIN {$wechatFansTable} f
               ON f.site_id = m.site_id
              AND f.unionid = m.wx_unionid
            SET m.wx_openid = f.openid
            WHERE m.site_id = :site_id
              AND m.delete_time = 0
              AND m.wx_unionid <> ''
              AND f.openid <> ''
              AND f.is_subscribe = 1";

    if (!$overwrite) {
        $sql .= " AND (m.wx_openid = '' OR m.wx_openid IS NULL)";
    }

    $statement = $pdo->prepare($sql);
    $statement->execute([':site_id' => $siteId]);
    $updated = $statement->rowCount();

    return [
        'enabled' => true,
        'matched_count' => (int)($matched['total'] ?? 0),
        'updated_count' => $updated,
        'overwrite' => $overwrite,
        'message' => $updated > 0 ? 'member.wx_openid 批量回填完成。' : '没有 member.wx_openid 被更新。',
    ];
}

function wechatGet(string $url, array $params): array
{
    $query = http_build_query($params);
    return httpJson($url . ($query ? '?' . $query : ''), 'GET');
}

function wechatPostJson(string $url, array $payload): array
{
    return httpJson($url, 'POST', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

function httpJson(string $url, string $method = 'GET', ?string $body = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('当前 PHP 未启用 curl 扩展，无法请求微信接口。');
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body ?? '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('请求微信接口失败: ' . $error);
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode((string)$response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("微信接口返回非 JSON，HTTP {$status}: {$response}");
    }

    return $decoded;
}

function maskString(string $value): string
{
    if ($value === '') {
        return '';
    }
    $length = strlen($value);
    if ($length <= 8) {
        return str_repeat('*', $length);
    }
    return substr($value, 0, 4) . str_repeat('*', max(0, $length - 8)) . substr($value, -4);
}

function updateMemberWxOpenid(PDO $pdo, string $memberTable, int $siteId, string $unionid, array $members, array $fans, bool $overwrite): array
{
    $fansOpenids = array_values(array_unique(array_filter(array_map(
        static fn(array $item): string => trim((string)($item['openid'] ?? '')),
        $fans
    ))));

    if (!$fansOpenids) {
        return [
            'enabled' => true,
            'status' => 'skipped',
            'source_openid' => '',
            'matched_members' => count($members),
            'updated_count' => 0,
            'skipped_count' => count($members),
            'overwrite' => $overwrite,
            'message' => '没有找到 wechat_fans.openid，无法更新 member.wx_openid。',
            'items' => [],
        ];
    }

    if (!$members) {
        return [
            'enabled' => true,
            'status' => 'skipped',
            'source_openid' => $fansOpenids[0],
            'matched_members' => 0,
            'updated_count' => 0,
            'skipped_count' => 0,
            'overwrite' => $overwrite,
            'message' => '没有找到匹配的 member 记录，无法更新 member.wx_openid。',
            'items' => [],
        ];
    }

    $sourceOpenid = $fansOpenids[0];
    $updatedCount = 0;
    $skippedCount = 0;
    $items = [];
    $pdo->beginTransaction();

    try {
        foreach ($members as $member) {
            $memberId = (int)($member['member_id'] ?? 0);
            $oldOpenid = trim((string)($member['wx_openid'] ?? ''));

            if ($memberId <= 0) {
                $skippedCount++;
                continue;
            }

            if ($oldOpenid === $sourceOpenid) {
                $skippedCount++;
                $items[] = [
                    'member_id' => $memberId,
                    'old_wx_openid' => $oldOpenid,
                    'new_wx_openid' => $sourceOpenid,
                    'updated' => false,
                    'reason' => 'wx_openid 已经一致',
                ];
                continue;
            }

            if ($oldOpenid !== '' && !$overwrite) {
                $skippedCount++;
                $items[] = [
                    'member_id' => $memberId,
                    'old_wx_openid' => $oldOpenid,
                    'new_wx_openid' => $sourceOpenid,
                    'updated' => false,
                    'reason' => 'wx_openid 已有值，未开启覆盖',
                ];
                continue;
            }

            $sql = "UPDATE {$memberTable}
                    SET wx_openid = :wx_openid
                    WHERE site_id = :site_id
                      AND member_id = :member_id
                      AND wx_unionid = :unionid
                      AND delete_time = 0";

            if (!$overwrite) {
                $sql .= " AND (wx_openid = '' OR wx_openid IS NULL)";
            }

            $statement = $pdo->prepare($sql);
            $statement->execute([
                ':wx_openid' => $sourceOpenid,
                ':site_id' => $siteId,
                ':member_id' => $memberId,
                ':unionid' => $unionid,
            ]);

            $rowCount = $statement->rowCount();
            if ($rowCount > 0) {
                $updatedCount += $rowCount;
                $items[] = [
                    'member_id' => $memberId,
                    'old_wx_openid' => $oldOpenid,
                    'new_wx_openid' => $sourceOpenid,
                    'updated' => true,
                    'reason' => '更新成功',
                ];
            } else {
                $skippedCount++;
                $items[] = [
                    'member_id' => $memberId,
                    'old_wx_openid' => $oldOpenid,
                    'new_wx_openid' => $sourceOpenid,
                    'updated' => false,
                    'reason' => 'UPDATE 未影响行，可能已被其他流程更新',
                ];
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return [
        'enabled' => true,
        'status' => $updatedCount > 0 ? 'updated' : 'skipped',
        'source_openid' => $sourceOpenid,
        'matched_members' => count($members),
        'updated_count' => $updatedCount,
        'skipped_count' => $skippedCount,
        'overwrite' => $overwrite,
        'message' => $updatedCount > 0 ? 'member.wx_openid 更新完成。' : '没有 member.wx_openid 被更新。',
        'items' => $items,
    ];
}

function buildFeedback(int $siteId, string $unionid, array $members, array $fans, ?array $updateResult = null): array
{
    $weappOpenids = array_values(array_unique(array_filter(array_map(
        static fn(array $item): string => trim((string)($item['weapp_openid'] ?? '')),
        $members
    ))));

    $memberWxOpenids = array_values(array_unique(array_filter(array_map(
        static fn(array $item): string => trim((string)($item['wx_openid'] ?? '')),
        $members
    ))));

    $fansOpenids = array_values(array_unique(array_filter(array_map(
        static fn(array $item): string => trim((string)($item['openid'] ?? '')),
        $fans
    ))));

    return [
        'ok' => true,
        'site_id' => $siteId,
        'unionid' => $unionid,
        'summary' => [
            'member_matched' => count($members),
            'wechat_fans_matched' => count($fans),
            'weapp_openid_found' => count($weappOpenids),
            'member_wx_openid_found' => count($memberWxOpenids),
            'fans_openid_found' => count($fansOpenids),
        ],
        'openid' => [
            'for_weapp_subscribe_message' => $weappOpenids,
            'for_official_account_from_member' => $memberWxOpenids,
            'for_official_account_from_fans' => $fansOpenids,
        ],
        'members' => $members,
        'wechat_fans' => $fans,
        'update_wx_openid' => $updateResult ?? [
            'enabled' => false,
            'message' => '未执行更新。需要更新 member.wx_openid 时请加 --update_wx_openid=1。',
        ],
        'next_step' => $weappOpenids
            ? '可以用 openid.for_weapp_subscribe_message 里的值测试小程序订阅消息。'
            : '没有找到 member.weapp_openid，当前 unionid 不能直接用于小程序订阅消息推送；需要用户用同一微信身份进过小程序并完成授权/登录。'
    ];
}

function printFeedback(array $feedback): void
{
    echo "处理结果\n";
    echo "site_id: {$feedback['site_id']}\n";
    echo "unionid: {$feedback['unionid']}\n";
    echo "member 匹配数: {$feedback['summary']['member_matched']}\n";
    echo "wechat_fans 匹配数: {$feedback['summary']['wechat_fans_matched']}\n";
    echo "小程序 openid 数: {$feedback['summary']['weapp_openid_found']}\n";
    echo "公众号 member.wx_openid 数: {$feedback['summary']['member_wx_openid_found']}\n";
    echo "公众号 wechat_fans.openid 数: {$feedback['summary']['fans_openid_found']}\n\n";

    echo "wx_openid 更新结果:\n";
    echo "  - {$feedback['update_wx_openid']['message']}\n";
    if (!empty($feedback['update_wx_openid']['enabled'])) {
        echo "  - 来源 openid: {$feedback['update_wx_openid']['source_openid']}\n";
        echo "  - 更新数: {$feedback['update_wx_openid']['updated_count']}\n";
        echo "  - 跳过数: {$feedback['update_wx_openid']['skipped_count']}\n";
        echo "  - 覆盖已有 wx_openid: " . (!empty($feedback['update_wx_openid']['overwrite']) ? '是' : '否') . "\n";
    }
    echo "\n";

    echo "小程序推送 openid(member.weapp_openid):\n";
    printStringList($feedback['openid']['for_weapp_subscribe_message']);

    echo "\n公众号 openid(member.wx_openid):\n";
    printStringList($feedback['openid']['for_official_account_from_member']);

    echo "\n公众号 openid(wechat_fans.openid):\n";
    printStringList($feedback['openid']['for_official_account_from_fans']);

    echo "\n会员明细:\n";
    if (!$feedback['members']) {
        echo "  - 无\n";
    } else {
        foreach ($feedback['members'] as $member) {
            echo sprintf(
                "  - member_id=%s, member_no=%s, nickname=%s, mobile=%s, weapp_openid=%s, wx_openid=%s, status=%s\n",
                $member['member_id'] ?? '',
                $member['member_no'] ?? '',
                $member['nickname'] ?? '',
                $member['mobile'] ?? '',
                $member['weapp_openid'] ?? '',
                $member['wx_openid'] ?? '',
                $member['status'] ?? ''
            );
        }
    }

    echo "\n粉丝明细:\n";
    if (!$feedback['wechat_fans']) {
        echo "  - 无\n";
    } else {
        foreach ($feedback['wechat_fans'] as $fan) {
            echo sprintf(
                "  - fans_id=%s, openid=%s, is_subscribe=%s\n",
                $fan['fans_id'] ?? '',
                $fan['openid'] ?? '',
                $fan['is_subscribe'] ?? ''
            );
        }
    }

    echo "\n下一步: {$feedback['next_step']}\n";
}

function printSyncFeedback(array $feedback): void
{
    $sync = $feedback['sync_official_fans'];
    $update = $feedback['update_member_wx_openid'];

    echo "公众号粉丝同步结果\n";
    echo "site_id: {$feedback['site_id']}\n";
    echo "微信粉丝总数(total): " . ($sync['total_from_wechat'] ?? '未知') . "\n";
    echo "本次拉取 openid 数: {$sync['fetched_openid_count']}\n";
    echo "拉取页数: {$sync['page_count']}\n";
    echo "批量详情批次数: {$sync['batch_count']}\n";
    echo "写入/更新粉丝数: {$sync['saved_count']}\n";
    echo "新增粉丝数: {$sync['inserted_count']}\n";
    echo "更新粉丝数: {$sync['updated_count']}\n";
    echo "已关注粉丝数: {$sync['subscribe_count']}\n";
    echo "拿到 unionid 数: {$sync['with_unionid_count']}\n";
    echo "没有 unionid 数: {$sync['without_unionid_count']}\n";
    echo "可匹配会员数: {$sync['matched_member_count']}\n";
    echo "失败批次数: {$sync['failed_batch_count']}\n";
    echo "使用公众号 app_id: {$sync['used_config']['app_id']}\n";
    echo "是否第三方授权配置: {$sync['used_config']['is_authorization']}\n\n";

    echo "member.wx_openid 批量回填结果\n";
    echo "  - {$update['message']}\n";
    if (!empty($update['enabled'])) {
        echo "  - 可匹配会员数: {$update['matched_count']}\n";
        echo "  - 更新会员数: {$update['updated_count']}\n";
        echo "  - 覆盖已有 wx_openid: " . (!empty($update['overwrite']) ? '是' : '否') . "\n";
    }

    if (!empty($sync['failed_batches'])) {
        echo "\n失败批次详情:\n";
        foreach ($sync['failed_batches'] as $failedBatch) {
            echo "  - batch={$failedBatch['batch']}, response=" . json_encode($failedBatch['response'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
        }
    }

    echo "\n说明: 只有公众号已关注粉丝，且微信接口返回 unionid，才能通过 unionid 回填 member.wx_openid。\n";
}

function printDiagnoseFeedback(array $feedback): void
{
    $summary = $feedback['summary'];
    echo "UnionID 诊断结果\n";
    echo "site_id: {$feedback['site_id']}\n";
    echo "会员表有 wx_unionid 数: {$summary['member_with_unionid']}\n";
    echo "粉丝表有 unionid 数: {$summary['fans_with_unionid']}\n";
    echo "精确匹配数: {$summary['exact_matched']}\n";
    echo "TRIM 后匹配数: {$summary['trim_matched']}\n\n";

    echo "会员 unionid 样本:\n";
    if (!$feedback['sample_members']) {
        echo "  - 无\n";
    } else {
        foreach ($feedback['sample_members'] as $member) {
            echo sprintf(
                "  - member_id=%s, nickname=%s, mobile=%s, unionid=%s, len=%s, weapp_openid=%s, wx_openid=%s\n",
                $member['member_id'] ?? '',
                $member['nickname'] ?? '',
                $member['mobile'] ?? '',
                $member['wx_unionid'] ?? '',
                $member['unionid_len'] ?? '',
                $member['weapp_openid'] ?? '',
                $member['wx_openid'] ?? ''
            );
        }
    }

    echo "\n公众号粉丝 unionid 样本:\n";
    if (!$feedback['sample_fans']) {
        echo "  - 无\n";
    } else {
        foreach ($feedback['sample_fans'] as $fan) {
            echo sprintf(
                "  - fans_id=%s, unionid=%s, len=%s, openid=%s, is_subscribe=%s\n",
                $fan['fans_id'] ?? '',
                $fan['unionid'] ?? '',
                $fan['unionid_len'] ?? '',
                $fan['openid'] ?? '',
                $fan['is_subscribe'] ?? ''
            );
        }
    }

    echo "\n匹配样本:\n";
    if (!$feedback['matched_samples']) {
        echo "  - 无\n";
    } else {
        foreach ($feedback['matched_samples'] as $item) {
            echo sprintf(
                "  - member_id=%s, nickname=%s, mobile=%s, unionid=%s, fans_id=%s, wx_openid=%s\n",
                $item['member_id'] ?? '',
                $item['nickname'] ?? '',
                $item['mobile'] ?? '',
                $item['wx_unionid'] ?? '',
                $item['fans_id'] ?? '',
                $item['wx_openid_from_fans'] ?? ''
            );
        }
    }

    echo "\n判断: 如果会员表和粉丝表都有 unionid，但精确匹配数仍为 0，说明两边 unionid 不是同一套开放平台关系下产生的，或会员表 unionid 来源不对。\n";
}

function printStringList(array $items): void
{
    if (!$items) {
        echo "  - 无\n";
        return;
    }

    foreach ($items as $item) {
        echo "  - {$item}\n";
    }
}
