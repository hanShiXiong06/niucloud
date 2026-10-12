<?php
declare(strict_types=1);

// Explicit, single-query diagnostic for the official SDK demo, never an arbitrary endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
ini_set('display_errors', '0');
require dirname(__DIR__, 3) . '/vendor/autoload.php';

use addon\hsx_kingdee\app\gateway\ApiFailure;
use addon\hsx_kingdee\app\gateway\EndpointPolicy;
use addon\hsx_kingdee\app\gateway\OfficialSdk;
use addon\hsx_kingdee\app\gateway\ReadOnlyCurlTransport;
use addon\hsx_kingdee\app\gateway\WebApiClient;

$options = getopt('', ['config:', 'verify-online', 'help']);
if (isset($options['help']) || !isset($options['verify-online'])) {
    echo "Official Kingdee demo: one exact material query, limit 1, no writes.\n";
    echo "Usage: php probe_official_demo.php --verify-online [--config=/absolute/path/to/demo/conf.ini]\n";
    echo "Only https://apiexp.open.kingdee.com/k3cloud/ is accepted. No browser session or business DB is used.\n";
    exit(isset($options['help']) ? 0 : 2);
}
$report = [
    'probe_id' => bin2hex(random_bytes(12)), 'started_at' => gmdate(DATE_ATOM),
    'target' => 'https://apiexp.open.kingdee.com/k3cloud/', 'sdk_version' => OfficialSdk::VERSION,
    'status' => 'started', 'code' => '', 'message' => '', 'request_attempted' => false,
    'write_enabled' => false, 'business_database_used' => false, 'remote_data_in_report' => false,
];
$log = null;
$logPath = '';
$exitCode = 1;
try {
    $directory = dirname(__DIR__, 3) . '/runtime/hsx_kingdee/probes';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new ApiFailure('audit_unavailable', '无法创建本地诊断日志目录，未发起查询');
    }
    $logPath = $directory . '/' . $report['probe_id'] . '.jsonl';
    $log = @fopen($logPath, 'x');
    if (!$log || !chmod($logPath, 0600)) throw new ApiFailure('audit_unavailable', '无法创建受保护的诊断日志，未发起查询');
    $append = static function (array $event) use ($log): void {
        $line = json_encode($event, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        if (fwrite($log, $line) !== strlen($line) || !fflush($log)) throw new ApiFailure('audit_unavailable', '诊断日志写入失败');
    };
    $append($report);
    $file = $options['config'] ?? dirname(__DIR__, 3) . '/vendor/SDK_PHP7.0_V8.2.0/php_sdk_demo/Config/conf.ini';
    if (!is_string($file) || !($file = realpath($file)) || !is_file($file) || filesize($file) > 16384) {
        throw new ApiFailure('demo_config_missing', '未找到 SDK 的本地示例配置文件，未发起查询');
    }
    $ini = @parse_ini_file($file, false, INI_SCANNER_RAW);
    if (!is_array($ini)) throw new ApiFailure('demo_config_invalid', 'SDK 示例配置无法解析，未发起查询');
    foreach (['X-KDApi-AcctID', 'X-KDApi-UserName', 'X-KDApi-AppID', 'X-KDApi-AppSec', 'X-KDApi-ServerUrl'] as $key) {
        if (!isset($ini[$key]) || !is_string($ini[$key]) || trim($ini[$key]) === '') {
            throw new ApiFailure('demo_config_incomplete', 'SDK 示例授权字段不完整，未发起查询');
        }
    }
    if (rtrim($ini['X-KDApi-ServerUrl'], '/') . '/' !== $report['target']) {
        throw new ApiFailure('demo_target_mismatch', '该入口仅允许 SDK 官方演示地址，不允许客户账套或其他地址');
    }
    $endpoint = new EndpointPolicy($report['target'], ['apiexp.open.kingdee.com']);
    $config = ['account_id' => $ini['X-KDApi-AcctID'], 'username' => $ini['X-KDApi-UserName'],
        'app_id' => $ini['X-KDApi-AppID'], 'organization' => (string)($ini['X-KDApi-OrgNum'] ?? '0')];
    $client = new WebApiClient($endpoint, $config, $ini['X-KDApi-AppSec'], new ReadOnlyCurlTransport($endpoint));
    unset($ini);
    $report['request_attempted'] = true;
    $result = $client->queryExact('BD_MATERIAL', ['FNumber'], ['FNumber' => '__HSX_CONNECTION_PROBE__'], 1);
    $report['status'] = 'passed';
    $report['code'] = 'query_verified';
    $report['message'] = '官方演示环境签名查询通过。未写单，未同步客户或库存，不代表客户账套及写入流程已验收。';
    $report['matched_rows'] = count($result);
    unset($result, $client, $config);
    $exitCode = 0;
} catch (ApiFailure $error) {
    $report['status'] = 'failed'; $report['code'] = $error->reason; $report['message'] = $error->getMessage();
} catch (Throwable $error) {
    $report['status'] = 'failed'; $report['code'] = 'probe_failed';
    $report['message'] = '演示环境检查未完成；原始异常、配置和响应未输出，请检查运行环境。';
} finally {
    $report['finished_at'] = gmdate(DATE_ATOM);
    if (is_resource($log)) {
        try { $append($report); }
        catch (Throwable $error) { $report['audit_failed'] = true; $exitCode = 1; }
        fclose($log);
    }
}
echo json_encode($report + ['local_audit_file' => $logPath], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n";
exit($exitCode);
