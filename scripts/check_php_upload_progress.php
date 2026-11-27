<?php
/**
 * PHP 8.0+ CLI: verify session.upload_progress.enabled through the website.
 * Does not bootstrap NiuCloud, connect to a database, change ini or restart PHP.
 * Writes one random, token-protected probe to the supplied web root, then deletes it.
 * This is a mitigation configuration check, NOT a vulnerability/exploit test.
 * Reference: https://www.php.net/index.php (security advisory, 2026-10-21)
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function uploadCheckUsage(): void
{
    echo <<<'HELP'
用法：
  php check_php_upload_progress.php --root=/网站实际运行目录 --url=https://网站域名
  php check_php_upload_progress.php --root=/网站实际运行目录 --url=https://网站域名 --resolve-ip=127.0.0.1

--root       网站 PHP 实际运行目录，牛云通常是 niucloud/public。
--url        对应网站 HTTPS 地址；仅本机测试允许 http://127.0.0.1 或 http://localhost。
--resolve-ip 可选：直连指定源站 IP，仍使用原域名校验 HTTPS 证书。
--help       显示帮助。

只检查，不修复、不重启、不读取业务配置或数据库。
会临时创建一个 3 分钟内有效、带随机校验的 PHP 文件，并自动删除。
要求 CLI PHP 8.0+、curl 扩展，以及对该运行目录的写权限。
退出码：0=抽样请求均关闭；1=检测到开启；2=无法确认/参数错误/清理失败。
注意：0 不代表漏洞已修复，也不能证明所有进程或其他服务器的状态。

HELP;
}

function runUploadCheck(array $args): int
{
    $options = [];
    foreach (array_slice($args, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            uploadCheckUsage();
            return 0;
        }
        if (!preg_match('/^--(root|url|resolve-ip)=(.+)$/D', $arg, $match)) {
            throw new RuntimeException('参数格式错误，请使用 --root=... --url=...，或 --help 查看帮助。');
        }
        if (isset($options[$match[1]])) {
            throw new RuntimeException('参数重复：' . $match[1]);
        }
        $options[$match[1]] = $match[2];
    }
    $root = isset($options['root']) ? realpath($options['root']) : false;
    if ($root === false || !is_dir($root) || !is_writable($root)) {
        throw new RuntimeException('请用 --root 指定存在且可写的网站运行目录。不要修改目录为 777。');
    }
    $url = rtrim($options['url'] ?? '', '/');
    $parts = parse_url($url);
    if (!$parts || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment']) || preg_match('/[\x00-\x20\x7f]/', $url)
        || !in_array($parts['scheme'] ?? '', ['https', 'http'], true)) {
        throw new RuntimeException('请提供合法的网站地址，不能包含账号、密码、参数、锚点或空白。');
    }
    if ($parts['scheme'] === 'http' && !in_array(strtolower($parts['host']), ['127.0.0.1', 'localhost', '[::1]'], true)) {
        throw new RuntimeException('正式网站必须使用 HTTPS；不关闭证书校验。');
    }
    if (isset($options['resolve-ip']) && !filter_var($options['resolve-ip'], FILTER_VALIDATE_IP)) {
        throw new RuntimeException('--resolve-ip 必须是有效 IP 地址。');
    }
    if (!extension_loaded('curl')) {
        throw new RuntimeException('当前 CLI PHP 缺少 curl 扩展。请改用网站对应的 PHP，例如宝塔 /www/server/php/80/bin/php。');
    }

    $nonce = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(32));
    $name = 'php_upload_check_' . $nonce . '.php';
    $path = $root . DIRECTORY_SEPARATOR . $name;
    $source = <<<'PROBE'
<?php
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');
if (time() > __EXPIRES__ || !hash_equals('__TOKEN_HASH__', hash('sha256', (string) ($_SERVER['HTTP_X_PHP_PROBE_TOKEN'] ?? '')))) {
    http_response_code(404);
    exit;
}
header('Content-Type: application/json; charset=utf-8');
$value = ini_get('session.upload_progress.enabled');
$all = function_exists('ini_get_all') ? ini_get_all('session', true) : false;
$master = is_array($all) ? ($all['session.upload_progress.enabled']['global_value'] ?? null) : null;
echo json_encode([
    'probe' => '__NONCE__',
    'php_version' => PHP_VERSION,
    'sapi' => PHP_SAPI,
    'value' => $value,
    'master_value' => $master,
    'checked_at' => time(),
], JSON_UNESCAPED_UNICODE);
PROBE;
    $source = str_replace(
        ['__EXPIRES__', '__TOKEN_HASH__', '__NONCE__'],
        [(string) (time() + 180), hash('sha256', $token), $nonce],
        $source
    );

    // Exclusive creation: never overwrite a pre-existing file or symlink.
    $handle = @fopen($path, 'x');
    if ($handle === false) {
        throw new RuntimeException('不能创建临时检查文件；未修改其他文件。');
    }
    $owned = true;
    $cleanup = static function () use (&$owned, $path): bool {
        if (!$owned) {
            return true;
        }
        if (!file_exists($path) || @unlink($path)) {
            $owned = false;
            return true;
        }
        fwrite(STDERR, '[警告] 临时文件删除失败，请手动删除：' . $path . PHP_EOL);
        return false;
    };
    register_shutdown_function($cleanup);
    // SIGKILL/power loss cannot run cleanup; the probe still expires after 3 minutes.
    if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, static function () use ($cleanup): void { $cleanup(); exit(130); });
        pcntl_signal(SIGTERM, static function () use ($cleanup): void { $cleanup(); exit(143); });
    }

    $result = 2;
    try {
        $written = fwrite($handle, $source);
        fclose($handle);
        $handle = null;
        if ($written !== strlen($source) || !@chmod($path, 0644)) {
            throw new RuntimeException('临时文件写入或权限设置失败。');
        }
        echo "检查网站实际 PHP 配置（3 次普通 GET 请求，不执行漏洞攻击）\n";
        echo "前提：配置已写入 php.ini 或 FPM 池，并已重启所有相关工作进程。\n";
        $result = 0;
        for ($i = 1; $i <= 3; $i++) {
            $body = '';
            $ch = curl_init($url . '/' . $name . '?sample=' . $i);
            $settings = [
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_PROXY => '',
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => ['X-PHP-Probe-Token: ' . $token, 'Cache-Control: no-cache'],
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > 16384) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ];
            if (isset($options['resolve-ip'])) {
                $ip = $options['resolve-ip'];
                $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
                $settings[CURLOPT_RESOLVE] = [$parts['host'] . ':' . $port . ':' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip)];
            }
            curl_setopt_array($ch, $settings);
            $ok = curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $errorCode = curl_errno($ch);
            curl_close($ch);
            if ($ok === false || $http !== 200) {
                throw new RuntimeException("网站检查未完成（HTTP {$http}，cURL {$errorCode}）。请核对域名、运行目录、证书与访问规则；不要为检查关闭安全限制。可用 --resolve-ip 指定源站。");
            }
            $data = json_decode($body, true);
            if (!is_array($data) || ($data['probe'] ?? '') !== $nonce || !isset($data['php_version'], $data['sapi'], $data['checked_at'])
                || !is_int($data['checked_at']) || abs(time() - $data['checked_at']) > 180) {
                throw new RuntimeException('网站未返回本次有效检查结果，可能是运行目录不匹配、未解析 PHP、缓存或页面重写；未输出响应正文，避免泄露信息。');
            }
            $value = $data['value'] ?? null;
            $enabled = is_string($value) ? filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) : null;
            if ($enabled === null || !in_array($data['sapi'], ['fpm-fcgi', 'cgi-fcgi', 'apache2handler', 'litespeed', 'frankenphp', 'cli-server'], true)) {
                throw new RuntimeException('未读取到有效配置或无法识别 Web PHP 运行方式，不能判定通过。');
            }
            printf("[%s] 第 %d 次：PHP %s / %s / session.upload_progress.enabled=%s\n",
                $enabled ? '未通过' : '通过', $i, preg_replace('/[^0-9A-Za-z.+_-]/', '', $data['php_version']),
                $data['sapi'], $enabled ? '开启' : '关闭');
            if ($enabled) {
                $result = 1;
            }
            if ($i === 1 && $data['sapi'] === 'cli-server') {
                echo "[注意] 当前是 PHP 内置开发服务器，只验证脚本；不能替代生产 PHP-FPM 检查。\n";
            }
            if (!$enabled && is_string($data['master_value'] ?? null) && filter_var($data['master_value'], FILTER_VALIDATE_BOOLEAN)) {
                echo "[注意] 主配置开启、当前请求关闭。请确认来自 FPM 池配置，不是请求解析后才生效的 .user.ini 或 ini_set。\n";
            }
        }
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (!$cleanup()) {
            $result = 2;
        } else {
            echo "[清理] 临时检查文件已删除。\n";
        }
    }
    echo $result === 0 ? "[结果] 本次抽样响应均已关闭该功能。\n" : "[结果] 尚未通过，请核对配置、重启情况及各节点状态。\n";
    echo "这只验证一项临时缓解配置，不证明漏洞已修复、全部进程已重启或其他站点/节点已生效。仍需安全补丁及登录、上传等业务验证。\n";
    return $result;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try {
        exit(runUploadCheck($argv));
    } catch (Throwable $e) {
        fwrite(STDERR, '[无法确认] ' . $e->getMessage() . PHP_EOL);
        exit(2);
    }
}
