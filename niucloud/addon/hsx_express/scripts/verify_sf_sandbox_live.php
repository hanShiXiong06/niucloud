<?php
declare(strict_types=1);

/**
 * 顺丰真实沙箱联调：仅 CLI + STDIN JSON；禁止生产、禁止持久表写入。
 * action=probe 仅检查本机 MySQL 临时表；action=run 才调用固定沙箱网关。
 * scene=waybill|pickup|all。pdf_probe 只对 original_waybill_no 获取原单 PDF，禁止创建。
 * 配置从 config 传入，不保存站点配置，不输出凭据。
 * 不启动 App::initialize；不触发商城支付、发货、库存及客户通知。
 */
ini_set('display_errors', '0');
ini_set('log_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

$report = [
    'ok' => false, 'action' => 'probe', 'scene' => 'all', 'environment' => 'sandbox',
    'stages' => [], 'checks' => 0, 'gateway_calls' => 0, 'pdf_downloads' => 0,
    'original_orders' => [], 'pdf_diagnostics' => [], 'persistent_writes' => false, 'business_shipment_changed' => false,
    'safe_summary' => '', 'message' => '',
];
$pdo = null;
$temporaryTable = '';
$temporaryCreated = false;
$config = [];
$failed = false;
$repository = null;
$task = null;
$root = dirname(__DIR__, 3);

$stage = static function (string $name, bool $success, string $message, array $extra = []) use (&$report): void {
    $report['stages'][] = ['stage' => $name, 'status' => $success ? 'passed' : 'failed', 'success' => $success, 'message' => $message] + $extra;
    ++$report['checks'];
};
$check = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};
// Never serialize an exception, HTTP request, configuration or provider response to stdout.
$safe = static function (string $message) use (&$config): string {
    foreach (['client_code', 'check_word', 'template_code', 'monthly_card'] as $field) {
        $value = (string)($config[$field] ?? '');
        if ($value !== '') $message = str_replace($value, '[已隐藏]', $message);
    }
    $message = preg_replace('/https?:\/\/[^\s；，。]+/u', '[链接省略]', strip_tags($message));
    $message = preg_replace('/(?:token|secret|check[_ -]?word|authorization|密钥|凭据)\s*["\x27:=： ]+[^\s,，;；}]+/iu', '[凭据省略]', (string)$message);
    $message = preg_replace('/[A-Za-z0-9_+\/=\-]{24,}/', '[长标识省略]', (string)$message);
    return mb_substr((string)$message, 0, 500);
};
$syncWaybillEvidence = static function (array $row) use (&$report): void {
    $target = $report['original_orders']['waybill'] ?? [];
    if (!empty($row['task_no'])) $target['order_id'] = (string)$row['task_no'];
    if (!empty($row['waybill_no'])) $target['waybill_no'] = (string)$row['waybill_no'];
    if (isset($row['state'])) $target['state'] = (string)$row['state'];
    $report['original_orders']['waybill'] = $target;
};

try {
    $check(PHP_SAPI === 'cli', '本工具仅支持本机 CLI，不提供公网调用入口');
    $check(count($argv ?? []) === 1, '参数只允许通过标准输入传递，不接受命令行凭据');
    $raw = stream_get_contents(STDIN, 32769);
    $check(is_string($raw) && strlen($raw) <= 32768, '测试输入过长');
    try { $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { throw new RuntimeException('请通过标准输入提供有效 JSON'); }
    unset($raw);
    $check(is_array($input), '测试输入必须是 JSON 对象');
    $action = (string)($input['action'] ?? 'probe');
    $scene = (string)($input['scene'] ?? 'all');
    $check(in_array($action, ['probe', 'run', 'pdf_probe'], true), '仅支持 probe、run 或 pdf_probe');
    $check(in_array($scene, ['waybill', 'pickup', 'all'], true), '请选择 waybill、pickup 或 all');
    if ($action === 'pdf_probe') $scene = 'waybill';
    $report['action'] = $action;
    $report['scene'] = $scene;

    require $root . '/vendor/autoload.php';
    require_once $root . '/vendor/topthink/framework/src/helper.php';
    $app = new \think\App($root . '/');
    $app->env->load($root . '/.env');
    $database = require $root . '/config/database.php';
    $source = $database['connections']['mysql'];
    $check(in_array((string)$source['hostname'], ['localhost', '127.0.0.1', '::1'], true), '仅允许本机 MySQL，未连接远程数据库');
    $check((bool)preg_match('/^[a-zA-Z0-9_]*$/D', (string)$source['prefix']), '数据库前缀格式异常');
    $check((bool)preg_match('/^[a-zA-Z0-9_\-]+$/D', (string)$source['database']), '数据库名称格式异常');
    $source = array_replace($source, ['deploy' => 0, 'rw_separate' => false, 'break_reconnect' => false,
        'fields_cache' => false, 'trigger_sql' => false, 'dsn' => '',
        'params' => [PDO::ATTR_PERSISTENT => false]]);
    $manager = new \think\DbManager();
    $manager->setConfig(['default' => 'mysql', 'connections' => ['mysql' => $source]]);
    $app->instance('think\\DbManager', $manager);
    $app->env->set('app.auth_key', 'sf-sandbox-ephemeral-' . bin2hex(random_bytes(32)));
    try {
        $connection = \think\facade\Db::connect();
        $connection->query('SELECT 1', [], true);
        $pdo = $connection->getPdo();
    } catch (Throwable $e) { throw new RuntimeException('无法连接本机测试数据库；未调用顺丰'); }
    $check($pdo instanceof PDO, '未取得本机临时表连接');
    $temporaryTable = $source['prefix'] . 'hsx_express_task';
    $sql = trim((string)file_get_contents(dirname(__DIR__) . '/sql/install.sql'));
    $check(substr_count($sql, ';') === 1 && str_starts_with($sql, 'CREATE TABLE IF NOT EXISTS `{{prefix}}hsx_express_task`'), '临时表结构不符合预期，已中止');
    $sql = str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', str_replace('{{prefix}}', $source['prefix'], $sql));
    try { $pdo->exec($sql); }
    catch (Throwable $e) { throw new RuntimeException('本机数据库不能创建隔离临时表；未调用顺丰'); }
    $temporaryCreated = true;
    $definition = $pdo->query('SHOW CREATE TABLE `' . $temporaryTable . '`')->fetch(PDO::FETCH_NUM);
    $check(str_starts_with((string)($definition[1] ?? ''), 'CREATE TEMPORARY TABLE'), '未确认临时表隔离，已中止');
    $stage('database_isolation', true, '本机 MySQL 会话临时表已建立；不写持久任务表或客户配置');

    $site = 987650002;
    $assertConnection = static function () use ($connection, $pdo, $check, $temporaryTable): void {
        $check($connection->getPdo() === $pdo, '测试数据库连接已改变，禁止继续');
        $definition = $pdo->query('SHOW CREATE TABLE `' . $temporaryTable . '`')->fetch(PDO::FETCH_NUM);
        $check(str_starts_with((string)($definition[1] ?? ''), 'CREATE TEMPORARY TABLE'), '临时表隔离已失效，禁止继续');
    };
    $lockResult = \addon\hsx_express\app\support\OperationLock::run($site, 'sandbox-probe', static function () use ($assertConnection) {
        $assertConnection();
        return true;
    });
    $check($lockResult === true, '本机命名锁不可用');
    $stage('operation_lock', true, '真实业务命名锁与临时表保持同一连接');

    if ($action === 'probe') {
        $report['ok'] = true;
        $report['safe_summary'] = '隔离能力检查通过；没有调用顺丰，也没有读取或保存物流凭据';
    } else {
        $supplied = $input['config'] ?? [];
        $check(is_array($supplied), '缺少测试配置');
        $check(($supplied['environment'] ?? '') === 'sandbox', '必须显式选择 sandbox；本工具拒绝生产环境');
        foreach ($supplied as $value) $check(is_scalar($value) || $value === null, '配置字段格式不正确');
        $config = array_replace(\addon\hsx_express\app\service\core\SfConfigService::defaults(),
            array_intersect_key($supplied, \addon\hsx_express\app\service\core\SfConfigService::defaults()));
        unset($input['config'], $supplied);
        $check(!empty($config['enabled']), '请确认启用本次沙箱测试配置');
        $readiness = \addon\hsx_express\app\service\core\SfConfigService::readiness($config, $scene === 'pickup' ? 'pickup' : 'waybill');
        $check(!empty($readiness['ready']), '沙箱配置未齐全：' . implode('；', $readiness['missing']));
        $stage('sandbox_guard', true, '配置仅在内存使用；接口固定为顺丰沙箱，禁止生产网关与重定向');

        $client = new \addon\hsx_express\app\service\core\SfClient(static function ($url, $form) use (&$report, $check, $assertConnection) {
            $assertConnection();
            $check($url === \addon\hsx_express\app\service\core\SfClient::SANDBOX_URL, '已阻止非沙箱网关');
            $check($report['gateway_calls'] < 10, '已达到本轮沙箱请求上限，禁止继续');
            ++$report['gateway_calls'];
            $response = (new \GuzzleHttp\Client())->post($url, ['form_params' => $form,
                'connect_timeout' => 5, 'timeout' => 25, 'verify' => true,
                'http_errors' => false, 'allow_redirects' => false]);
            $check($response->getStatusCode() === 200, '沙箱网关暂不可用，结果待核实');
            $body = $response->getBody()->read(2097153);
            $check(strlen($body) <= 2097152, '沙箱响应过长，结果待核实');
            return json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        });
        $request = static function (string $service, array $data, array $cfg) use ($client, $check, &$report, $action, $safe): array {
            $check(($cfg['environment'] ?? '') === 'sandbox', '已阻止非沙箱配置');
            if ($action === 'pdf_probe') $check($service === \addon\hsx_express\app\service\core\SfClient::PRINT_PDF, 'PDF 诊断只允许获取原单文件，禁止创建、取消或其他接口');
            if ($service === \addon\hsx_express\app\service\core\SfClient::CREATE_ORDER) {
                $kind = (int)($data['isDocall'] ?? -1) === 1 ? 'pickup' : 'waybill';
                $check(!isset($report['original_orders'][$kind]), '同场景已发送过创建请求，禁止新建重试');
                $report['original_orders'][$kind] = ['order_id' => (string)$data['orderId'], 'state' => 'submitted'];
            }
            $response = $client->request($service, $data, $cfg);
            if ($service === \addon\hsx_express\app\service\core\SfClient::PRINT_PDF) {
                $value = $response['success'] ?? null;
                $diagnostic = [
                    'success_type' => array_key_exists('success', $response) ? gettype($value) : 'missing',
                    'success_value' => is_bool($value) || is_numeric($value) || $value === null ? $value : (is_string($value) ? $safe(mb_substr($value, 0, 30)) : '[非标量]'),
                    'top_level_fields' => array_map(static fn($key) => $safe(mb_substr((string)$key, 0, 80)), array_slice(array_keys($response), 0, 30)),
                ];
                foreach (['errorCode', 'code', 'errorMsg', 'errorMessage', 'msg', 'message'] as $field) {
                    if (array_key_exists($field, $response)) $diagnostic[$field] = is_scalar($response[$field]) ? $safe(mb_substr((string)$response[$field], 0, 300)) : '[非标量]';
                }
                $report['pdf_diagnostics'][] = $diagnostic;
            }
            return $response;
        };

        if ($action === 'pdf_probe') {
            $number = trim((string)($input['original_waybill_no'] ?? ''));
            $check((bool)preg_match('/^SF[0-9]{10,20}$/D', $number), '请填写已取得的顺丰原运单号，仅接受 SF 加数字');
            $report['original_orders']['waybill'] = ['waybill_no' => $number, 'state' => 'pdf_probe_only'];
            $response = $request(\addon\hsx_express\app\service\core\SfClient::PRINT_PDF,
                \addon\hsx_express\app\service\core\SfProtocol::pdfRequest($config, ['waybill_no' => $number]), $config);
            try {
                $artifact = \addon\hsx_express\app\service\core\SfProtocol::normalizePdf($response, $number);
                $check(!empty($artifact['url']) && !empty($artifact['token']), '原单 PDF 响应不完整');
                $stage('pdf_probe', true, '已取得并校验原单 PDF 响应；未创建运单、未取消、未下载或打印', ['expires_at' => (int)$artifact['expires_at']]);
                $report['ok'] = true;
                $report['safe_summary'] = '原单 PDF 响应验证通过；未新建运单，未改变业务状态';
                unset($artifact);
            } catch (Throwable $e) {
                $stage('pdf_probe', false, $e instanceof \addon\hsx_express\app\service\core\ProviderException ? $safe($e->getMessage()) : '原单 PDF 响应未通过校验，请查看脱敏诊断');
                $report['safe_summary'] = '原单 PDF 诊断未通过；未新建运单，请查看脱敏响应字段';
            }
        } else {

        // 官方下订单接口 2.6 公开示例，仅用于顺丰沙箱；不读取客户、商城订单或通讯录。
        // https://open.sf-express.com/Api/ApiDetails?level3=393&interName=下订单接口-EXP_RECE_CREATE_ORDER
        $payload = [
            'business_type' => 'phone_shop', 'business_id' => '990001:sf-sandbox',
            'business_no' => 'SF-SANDBOX-ONLY', 'order_id' => 990001, 'order_goods_ids' => [990001],
            'sender' => ['name' => '顺小丰', 'mobile' => '13480155048', 'province' => '广东省',
                'city' => '深圳市', 'district' => '南山区', 'address' => '广东省深圳市南山区软件产业基地11栋'],
            'receiver' => ['name' => '顺小丰', 'mobile' => '13925211148', 'province' => '广东省',
                'city' => '广州市', 'district' => '白云区', 'address' => '广东省广州市白云区湖北大厦'],
            'cargo' => '沙箱测试手机', 'weight' => 0.5, 'count' => 1,
        ];
        $stop = false;
        if (in_array($scene, ['waybill', 'all'], true)) {
            $repository = new \addon\hsx_express\app\service\core\TaskRepository();
            $service = new \addon\hsx_express\app\service\core\SfWaybillService($repository, $request,
                static fn($siteId) => $config, null, static fn($data) => [true]);
            $task = $service->execute($site, 'create', $payload);
            $syncWaybillEvidence($task);
            $known = !empty($task['waybill_no']) && in_array($task['state'], ['ready', 'print_pending', 'print_failed'], true);
            $stage('waybill_create', $known, $safe((string)$task['message']), ['state' => $task['state']]);
            $failed = !$known || $failed;
            $before = $report['gateway_calls'];
            $query = $service->execute($site, 'query', ['task_id' => $task['task_id']]);
            $check($before === $report['gateway_calls'], '本地查询不应触发顺丰请求');
            $stage('waybill_local_query', true, '本地查询原任务，没有重复取号');
            if ($task['state'] !== 'failed') {
                $task = $service->execute($site, 'refresh', ['task_id' => $task['task_id']]);
                $syncWaybillEvidence($task);
                $known = !empty($task['waybill_no']) && in_array($task['state'], ['ready', 'print_pending', 'print_failed'], true);
                $row = $repository->find($site, (int)$task['task_id']);
                $logs = json_decode((string)($row['logs_json'] ?? '[]'), true) ?: [];
                $lastLog = $logs ? $logs[count($logs) - 1] : [];
                $refreshed = $known && ($lastLog['operation'] ?? '') === 'sf_order_queried';
                $failed = !$refreshed || $failed;
                $stage('waybill_refresh', $refreshed, $safe((string)$task['message']), ['state' => $task['state']]);
                if (!$known) $stop = true;
            }
            $check(($task['environment'] ?? '') === 'sandbox' && empty($task['can_confirm_delivery']), '沙箱面单不应允许商城确认发货');
            $stage('waybill_delivery_guard', true, '沙箱面单不能确认商城正式发货，未改订单或库存');
            if (!empty($task['can_download'])) {
                try {
                    ++$report['pdf_downloads'];
                    $file = $service->downloadPdf($site, (int)$task['task_id']);
                    $check(str_starts_with($file['body'] ?? '', '%PDF-'), '未取得有效 PDF');
                    $stage('waybill_pdf_download', true, '已下载并验证原单 PDF；不保存下载凭据，不代表打印机已出纸', ['bytes' => strlen($file['body'])]);
                    unset($file);
                } catch (Throwable $e) {
                    $failed = true;
                    $stage('waybill_pdf_download', false, '原单 PDF 下载未完成；原运单保留，继续取消同一测试原单');
                }
            } else {
                $failed = true;
                $stage('waybill_pdf_download', false, '未取得可下载 PDF；未新建运单或自动重试');
            }
            if (!empty($task['can_cancel'])) {
                $syncWaybillEvidence($task);
                $task = $service->execute($site, 'cancel', ['task_id' => $task['task_id'], 'reason' => '项目沙箱联调结束，仅取消本次测试原单']);
                $syncWaybillEvidence($task);
                $cancelled = $task['state'] === 'cancelled';
                $failed = !$cancelled || $failed;
                $stop = !$cancelled || $stop;
                $stage('waybill_cancel', $cancelled, $safe((string)$task['message']), ['state' => $task['state']]);
            }
            $syncWaybillEvidence($task);
        }

        if (!$stop && in_array($scene, ['pickup', 'all'], true)) {
            $orderId = 'SFPTEST' . date('YmdHis') . bin2hex(random_bytes(5));
            $start = new DateTimeImmutable('tomorrow 10:00:00', new DateTimeZone('Asia/Shanghai'));
            $pickup = $payload + ['pickup_start_at' => $start->format('Y-m-d H:i:s'), 'pickup_end_at' => $start->modify('+2 hours')->format('Y-m-d H:i:s')];
            $message = \addon\hsx_express\app\service\core\SfProtocol::createOrder($config, $pickup, $orderId, 'pickup');
            $check(($message['isDocall'] ?? 0) === 1, '上门取件参数未正确设置');
            $result = $request(\addon\hsx_express\app\service\core\SfClient::CREATE_ORDER, $message, $config);
            $order = \addon\hsx_express\app\service\core\SfProtocol::normalizeOrder($result);
            $accepted = !empty($order['success']) && hash_equals($orderId, $order['order_no']) && in_array($order['filter_result'], ['1', '2'], true);
            $failed = !$accepted || $failed;
            $report['original_orders']['pickup']['state'] = $accepted ? 'accepted' : 'unknown';
            if ($order['waybill_no'] !== '') $report['original_orders']['pickup']['waybill_no'] = $order['waybill_no'];
            $stage('pickup_create', $accepted, $safe($order['message']) . '；只验证沙箱受理，不表示真实派员或揽收');
            $knownRejection = !empty($order['definitive_rejected']);
            if (!$knownRejection) {
                try {
                    $searched = $request(\addon\hsx_express\app\service\core\SfClient::SEARCH_ORDER,
                        \addon\hsx_express\app\service\core\SfProtocol::searchOrder($orderId), $config);
                    $lookup = \addon\hsx_express\app\service\core\SfProtocol::normalizeOrder($searched);
                    $matched = !empty($lookup['success']) && hash_equals($orderId, $lookup['order_no']);
                    $failed = !$matched || $failed;
                    $stage('pickup_search', $matched, $safe($lookup['message']) . '；未重新下单');
                    if ($matched && $lookup['waybill_no'] !== '') $order['waybill_no'] = $lookup['waybill_no'];
                } catch (Throwable $e) {
                    $failed = true;
                    $stage('pickup_search', false, '沙箱原预约查询待核实；未新建重试，继续尝试取消原编号');
                }
                try {
                    $cancel = $request(\addon\hsx_express\app\service\core\SfClient::CANCEL_ORDER,
                        \addon\hsx_express\app\service\core\SfProtocol::cancelOrder($orderId, $order['waybill_no']), $config);
                    $cancelled = \addon\hsx_express\app\service\core\SfProtocol::normalizeCancel($cancel, $orderId);
                    $failed = empty($cancelled['confirmed']) || $failed;
                    $report['original_orders']['pickup']['state'] = !empty($cancelled['confirmed']) ? 'cancelled' : 'cancel_unknown';
                    $stage('pickup_cancel', !empty($cancelled['confirmed']), $safe($cancelled['message']));
                } catch (Throwable $e) {
                    $failed = true;
                    $report['original_orders']['pickup']['state'] = 'cancel_unknown';
                    $stage('pickup_cancel', false, '沙箱原预约取消待核实，请按报告中的原编号查询，禁止新建重试');
                }
            } else {
                $report['original_orders']['pickup']['state'] = 'rejected';
                $stage('pickup_cancel', true, '顺丰明确未受理，无已确认预约可取消；未重新下单');
            }
            $stage('pickup_business_guard', true, '未调用或绕过回收沙箱安全锁，未写回收预约成功、通知或业务状态');
        } elseif ($stop && in_array($scene, ['pickup', 'all'], true)) {
            $failed = true;
            $stage('pickup_skipped', false, '面单原单或取消结果待核实，本轮停止后续新建测试');
        }
        $report['ok'] = !$failed;
        $report['safe_summary'] = $failed
            ? '本轮存在未通过阶段；已保留原沙箱单号用于核实，没有新建重试或生产操作'
            : '顺丰沙箱联调通过；不等于真实发货、派员、揽收或打印机出纸已验收';
        }
    }
} catch (Throwable $e) {
    $report['ok'] = false;
    $message = get_class($e) === RuntimeException::class || $e instanceof \addon\hsx_express\app\service\core\ProviderException
        ? $safe($e->getMessage()) : '测试中止，请查看已完成阶段；未自动重试，请保留原沙箱单号核实';
    $stage('stopped', false, $message);
    $report['safe_summary'] = '联调未完成；没有生产业务操作，没有自动新建重试';
} finally {
    if ($temporaryCreated && $pdo instanceof PDO) {
        if ($repository && is_array($task) && !empty($task['task_id'])) {
            try {
                $row = $repository->find($site, (int)$task['task_id']);
                if ($row) $syncWaybillEvidence($row);
            } catch (Throwable $e) {
                $report['ok'] = false;
                $stage('evidence_snapshot', false, '未能读取最后任务状态；请保留报告中的原沙箱编号核实');
            }
        }
        try {
            $pdo->exec('DROP TEMPORARY TABLE `' . $temporaryTable . '`');
            $stage('temporary_cleanup', true, '测试临时表已清理，原持久表及客户配置未改变');
        } catch (Throwable $e) {
            $report['ok'] = false;
            $stage('temporary_cleanup', false, '临时表随连接关闭释放；未对持久表执行清理');
            $report['safe_summary'] = '联调未完整通过：临时表清理确认失败；请查看阶段结果，原持久表未执行清理';
        }
    }
    unset($config, $input, $source, $database);
}
if (!$report['ok'] && !array_filter($report['stages'], static fn($item) => empty($item['success']))) {
    $report['safe_summary'] = '联调尚未确认完整通过，请查看阶段结果及原沙箱编号';
}
if (!$report['ok'] && str_contains($report['safe_summary'], '验证通过')) $report['safe_summary'] = '联调未完整通过，请查看失败阶段及原沙箱编号';
if (!$report['ok'] && str_contains($report['safe_summary'], '联调通过')) $report['safe_summary'] = '联调未完整通过，请查看失败阶段及原沙箱编号';
$report['message'] = $report['safe_summary'];
echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
exit($report['ok'] ? 0 : 1);
