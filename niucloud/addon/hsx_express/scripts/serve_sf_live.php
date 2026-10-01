<?php
declare(strict_types=1);

/**
 * 本机顺丰沙箱联调入口，仅供 PHP 内置服务器使用。
 * 启动：php -S 127.0.0.1:18976 addon/hsx_express/scripts/serve_sf_live.php
 * 不接入框架路由，不保存凭据；配置只通过子进程 STDIN 传递。
 */
ini_set('display_errors', '0');
ini_set('log_errors', '0');

if (PHP_SAPI === 'cli') {
    fwrite(STDOUT, "请使用 PHP 内置服务器启动，仅监听 127.0.0.1，例如：\nphp -S 127.0.0.1:18976 " . __FILE__ . "\n");
    exit(0);
}
if (PHP_SAPI !== 'cli-server'
    || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1'
    || !preg_match('/\A127\.0\.0\.1(?::([1-9][0-9]{0,4}))?\z/D', (string) ($_SERVER['HTTP_HOST'] ?? ''))) {
    http_response_code(403);
    exit('Local sandbox tool only.');
}

$nonce = base64_encode(random_bytes(24));
header('Content-Security-Policy: default-src \'none\'; script-src \'nonce-' . $nonce . '\'; style-src \'nonce-' . $nonce . '\'; connect-src \'self\'; form-action \'self\'; frame-ancestors \'none\'; base-uri \'none\'');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

session_name('HSX_SF_LOCAL_TEST');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => false, 'httponly' => true, 'samesite' => 'Strict']);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
if (!session_start()) {
    http_response_code(500);
    exit('无法创建本机安全会话，请检查 PHP 临时目录权限。');
}
if (!isset($_SESSION['sf_live_csrf'])) {
    $_SESSION['sf_live_csrf'] = bin2hex(random_bytes(32));
}
$csrf = (string) $_SESSION['sf_live_csrf'];
session_write_close(); // 会话仅保存随机 CSRF 标记，不保存配置和凭据。

function sfLiveReply(int $status, array $data): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

/** 子进程只展示安全摘要，不回传原始 HTTP、凭据、文件路径或异常栈。 */
function sfLiveText(string $text, array $secrets): string
{
    foreach ($secrets as $secret) {
        if ($secret !== '') {
            $text = str_replace([$secret, rawurlencode($secret), urlencode($secret)], '[已隐藏]', $text);
        }
    }
    $text = preg_replace('/https?:\/\/[^\s"<>]+/iu', '[链接已隐藏]', $text) ?? '';
    $text = preg_replace('/(?:[A-Za-z]:\\\\|\/(?:Users|home|root|www|var|private|tmp)\/)[^\s"<>]+/u', '[路径已隐藏]', $text) ?? '';
    $text = preg_replace('/\b(?:check_word|checkWord|msgDigest|secret|token|password|authorization)\s*[=:]\s*[^\s,;]+/iu', '[敏感内容已隐藏]', $text) ?? '';
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text) ?? '';
    if (preg_match('/(?:Stack trace:|Fatal error:|Uncaught\s|#\d+\s+.*\(|\bon line \d+)/i', $text)) {
        return '脚本执行异常，已隐藏内部错误详情。请根据前面已完成的阶段核对原单，不要直接重复取号。';
    }
    return function_exists('mb_substr') ? mb_substr($text, 0, 2000) : substr($text, 0, 2000);
}

function sfLiveProject($value, array $secrets, int $depth = 0)
{
    if ($depth > 8) {
        return '[内容已折叠]';
    }
    if (is_string($value)) {
        return sfLiveText($value, $secrets);
    }
    if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
        return $value;
    }
    if (!is_array($value)) {
        return null;
    }
    $allowed = ['ok', 'success', 'status', 'stage', 'label', 'name', 'message', 'summary', 'action', 'environment',
        'stages', 'checks', 'results', 'result', 'warnings', 'errors', 'cleanup', 'code', 'count', 'passed', 'failed',
        'site_id', 'order_no', 'waybill_no', 'task_no', 'request_id', 'provider', 'scene', 'duration_ms', 'elapsed_ms',
        'ready', 'external_verified', 'cancelled', 'pdf_bytes', 'pdf_verified', 'bytes', 'sha256', 'skipped',
        'safe_summary', 'gateway_calls', 'pdf_downloads', 'original_orders', 'waybill', 'pickup', 'order_id', 'state',
        'persistent_writes', 'business_shipment_changed', 'diagnostics', 'pdf', 'success_type', 'success_value', 'keys',
        'errorCode', 'errorMsg', 'errorMessage', 'msg', 'obj_keys', 'pdf_diagnostics', 'top_level_fields'];
    $safe = [];
    foreach (array_slice($value, 0, 100, true) as $key => $item) {
        if (is_int($key) || in_array((string) $key, $allowed, true)) {
            $safe[$key] = sfLiveProject($item, $secrets, $depth + 1);
        }
    }
    return $safe;
}

function sfLiveOutput(string $stdout, array $secrets): array
{
    $decoded = json_decode(trim($stdout), true);
    if (is_array($decoded)) {
        $safe = sfLiveProject($decoded, $secrets);
        return $safe ?: ['message' => '脚本已结束，没有可展示的安全摘要。请勿据此认定联调成功。'];
    }
    $lines = [];
    foreach (preg_split('/\r?\n/', $stdout) ?: [] as $line) {
        $line = trim($line);
        if (preg_match('/^(?:\[?(?:PASS|FAIL|OK|WARN|INFO|SKIP)\]?\b|阶段|检查|提示|成功|失败|警告|完成|未执行|已取消|待核实)/u', $line)) {
            $lines[] = sfLiveText($line, $secrets);
        }
        if (count($lines) >= 60) {
            break;
        }
    }
    return ['message' => $lines ? implode("\n", $lines) : '脚本已结束，但输出不是约定的安全摘要。未显示原始输出，请勿据此认定成功。'];
}

$method = (string) ($_SERVER['REQUEST_METHOD'] ?? '');
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if ($method === 'POST' && $path === '/run') {
    $expectedOrigin = 'http://' . $_SERVER['HTTP_HOST'];
    if (!hash_equals($expectedOrigin, (string) ($_SERVER['HTTP_ORIGIN'] ?? ''))
        || !hash_equals($csrf, (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))
        || !in_array((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? 'same-origin'), ['same-origin', 'none'], true)) {
        sfLiveReply(403, ['ok' => false, 'message' => '本机会话校验失败，请从 127.0.0.1 页面重新打开。']);
    }
    if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0
        || (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) {
        sfLiveReply(400, ['ok' => false, 'message' => '请求格式或大小不符合本机联调要求。']);
    }
    $body = file_get_contents('php://input', false, null, 0, 8193);
    if (!is_string($body) || strlen($body) > 8192) {
        sfLiveReply(413, ['ok' => false, 'message' => '请求内容过大。']);
    }
    $request = json_decode($body, true);
    unset($body);
    if (!is_array($request) || !is_array($request['config'] ?? null)) {
        sfLiveReply(400, ['ok' => false, 'message' => '配置格式无效。']);
    }
    $action = $request['action'] ?? 'probe';
    if (!in_array($action, ['probe', 'run', 'pdf_probe'], true)) {
        sfLiveReply(400, ['ok' => false, 'message' => '只允许检查环境、执行沙箱联调或核对原单 PDF。']);
    }
    $scene = $request['scene'] ?? 'waybill';
    if (!in_array($scene, ['waybill', 'pickup'], true)) {
        sfLiveReply(400, ['ok' => false, 'message' => '每次只能选择电子面单或上门取件中的一种场景。']);
    }
    if ($action !== 'probe' && ($request['confirm_sandbox'] ?? false) !== true) {
        sfLiveReply(400, ['ok' => false, 'message' => '请先确认本次仅使用顺丰沙箱账户。']);
    }
    $originalWaybillNo = '';
    if ($action === 'pdf_probe') {
        $scene = 'waybill';
        if (!is_string($request['original_waybill_no'] ?? null)
            || !preg_match('/\ASF[0-9]{10,20}\z/D', trim($request['original_waybill_no']))) {
            sfLiveReply(400, ['ok' => false, 'message' => '请填写已取得的原沙箱运单号，格式为 SF 加 10 至 20 位数字。不会重新取号。']);
        }
        $originalWaybillNo = trim($request['original_waybill_no']);
    }
    $input = $request['config'];
    $config = ['environment' => 'sandbox', 'enabled' => 1, 'use_ack' => 1, 'product_code' => '2', 'pay_method' => 1];
    foreach (['client_code' => 64, 'check_word' => 256, 'template_code' => 100, 'monthly_card' => 10] as $field => $limit) {
        if (isset($input[$field]) && !is_string($input[$field])) {
            sfLiveReply(400, ['ok' => false, 'message' => '配置字段格式无效。']);
        }
        $config[$field] = trim((string) ($input[$field] ?? ''));
        if (strlen($config[$field]) > $limit || preg_match('/[\x00-\x1F\x7F]/', $config[$field])) {
            sfLiveReply(400, ['ok' => false, 'message' => '配置字段过长或包含不允许的控制字符。']);
        }
    }
    unset($request, $input);
    if ($config['client_code'] !== '' && !preg_match('/\A[A-Za-z0-9_-]+\z/D', $config['client_code'])) {
        sfLiveReply(400, ['ok' => false, 'message' => 'Client Code 只能包含字母、数字、下划线和连字符。']);
    }
    if ($config['monthly_card'] !== '' && !preg_match('/\A[0-9]{10}\z/D', $config['monthly_card'])) {
        sfLiveReply(400, ['ok' => false, 'message' => '沙箱月结卡号需为 10 位数字；寄付现结可留空。']);
    }
    if ($action !== 'probe' && ($config['client_code'] === '' || $config['check_word'] === '' || ($scene === 'waybill' && $config['template_code'] === ''))) {
        sfLiveReply(400, ['ok' => false, 'message' => $scene === 'waybill'
            ? '电子面单联调前，请填写 Client Code、Check Word 和 PDF 模板。'
            : '上门取件联调前，请填写 Client Code 和 Check Word，不需要 PDF 模板。']);
    }
    if (!function_exists('proc_open') || !is_file(__DIR__ . '/verify_sf_sandbox_live.php')) {
        sfLiveReply(503, ['ok' => false, 'message' => '本机联调脚本尚未就绪，或 PHP 未开启 proc_open。未发起顺丰请求。']);
    }
    $pipes = [];
    $process = @proc_open([PHP_BINARY, __DIR__ . '/verify_sf_sandbox_live.php'],
        [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, __DIR__, null, ['bypass_shell' => true]);
    if (!is_resource($process)) {
        sfLiveReply(503, ['ok' => false, 'message' => '无法启动本机联调进程，未发起顺丰请求。']);
    }
    $secrets = [$config['client_code'], $config['check_word'], $config['monthly_card']];
    $encoded = json_encode(['action' => $action, 'scene' => $scene, 'config' => $config, 'original_waybill_no' => $originalWaybillNo], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    unset($config);
    $written = is_string($encoded) ? fwrite($pipes[0], $encoded) : false;
    $inputOk = is_string($encoded) && $written === strlen($encoded);
    unset($encoded);
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = '';
    $started = microtime(true);
    $exitCode = -1;
    $interrupted = !$inputOk;
    @set_time_limit(210);
    while (!$interrupted) {
        $stdout .= (string) stream_get_contents($pipes[1], max(1, 262145 - strlen($stdout)));
        stream_get_contents($pipes[2], 65536); // 不展示或落盘内部错误输出。
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exitCode = (int) $status['exitcode'];
            $stdout .= (string) stream_get_contents($pipes[1], max(1, 262145 - strlen($stdout)));
            break;
        }
        if (microtime(true) - $started > 180 || strlen($stdout) > 262144) {
            $interrupted = true;
            break;
        }
        usleep(50000);
    }
    if ($interrupted) {
        proc_terminate($process);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closedCode = proc_close($process);
    if ($exitCode < 0) {
        $exitCode = $closedCode;
    }
    $result = sfLiveOutput(substr($stdout, 0, 262144), $secrets);
    unset($stdout, $secrets);
    if ($interrupted) {
        sfLiveReply(504, ['ok' => false, 'message' => '联调进程超时或未能完整执行，结果待核实。若已取号，请核对并取消原沙箱单，不要直接重复执行。', 'result' => $result]);
    }
    sfLiveReply(200, ['ok' => $exitCode === 0, 'action' => $action, 'scene' => $scene, 'result' => $result,
        'message' => $exitCode === 0 ? '联调脚本执行结束，请逐项查看阶段结果。' : '联调未全部通过，请查看已完成阶段；不代表未产生沙箱单。']);
}

if ($method !== 'GET' || $path !== '/' || !empty($_SERVER['QUERY_STRING'])) {
    http_response_code(404);
    exit('Not found.');
}
header('Content-Type: text/html; charset=utf-8');
$nonceAttr = htmlspecialchars($nonce, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>顺丰直连 · 本机沙箱联调</title>
    <style nonce="<?= $nonceAttr ?>">
        *{box-sizing:border-box}body{margin:0;background:#f4f6f9;color:#172033;font:14px/1.6 system-ui,-apple-system,"PingFang SC",sans-serif}
        main{max-width:880px;margin:36px auto;padding:0 20px}h1{font-size:24px;line-height:1.3;margin:0 0 10px}h2{font-size:17px;margin:0 0 14px}
        .muted{color:#677387}.card{background:#fff;border:1px solid #e2e7ed;border-radius:12px;padding:24px;margin-top:20px}
        .notice{background:#fff8e7;border:1px solid #f0dc9f;border-radius:8px;padding:13px 16px;margin:18px 0;color:#785313}
        .products{display:grid;grid-template-columns:1fr 1fr;gap:12px}.product{padding:14px;background:#f5f7fa;border-radius:8px}.product strong{display:block}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-top:22px}label.field{display:block;font-weight:600}.scene-field{margin-top:22px}input[type=text],input[type=password],select{width:100%;height:42px;border:1px solid #cdd5df;border-radius:6px;padding:8px 11px;margin-top:6px;background:#fff;color:#172033;font:inherit}
        input:focus,select:focus{outline:2px solid #b1c9ff;outline-offset:1px;border-color:#3675e7}.help{margin-top:5px;color:#758094;font-size:12px;font-weight:400}
        .confirm{display:flex;align-items:flex-start;gap:8px;margin:24px 0 18px}.confirm input{margin-top:5px}.actions{display:flex;gap:10px;flex-wrap:wrap}
        button{border:1px solid #cdd5df;border-radius:6px;padding:10px 17px;background:#fff;color:#263248;font:inherit;font-weight:600;cursor:pointer}button.primary{background:#2864dc;border-color:#2864dc;color:#fff}button:disabled{opacity:.55;cursor:not-allowed}
        pre{margin:12px 0 0;background:#f5f7fa;border-radius:8px;padding:16px;white-space:pre-wrap;overflow-wrap:anywhere;max-height:560px;overflow:auto;font:13px/1.6 ui-monospace,monospace}.status{color:#42516a;font-weight:600}.status.error{color:#bb3434}[hidden]{display:none!important}
        @media(max-width:620px){main{margin:20px auto;padding:0 12px}.card{padding:18px}.grid,.products{grid-template-columns:1fr}.actions button{flex:1}}
    </style>
</head>
<body>
<main>
    <h1>顺丰直连 · 本机沙箱联调</h1>
    <div class="muted">仅在当前电脑使用，不保存密钥，不使用生产账户，不修改线上订单。</div>
    <div class="notice">两种产品分别验证：取到运单号不等于预约成功，生成 PDF 不等于打印机已经出纸。沙箱测试也不代表生产权限已开通。</div>
    <section class="card">
        <div class="products">
            <div class="product"><strong>电子面单</strong>门店取号、查询原单、生成 PDF、取消测试单。</div>
            <div class="product"><strong>上门取件</strong>验证预约请求与响应，不向生产环境真实叫件。</div>
        </div>
        <form id="form" autocomplete="off">
            <label class="field scene-field">本次联调场景<select name="scene" id="scene"><option value="waybill" selected>电子面单</option><option value="pickup">上门取件</option></select><div class="help">每次只验证一种产品，默认电子面单。上门取件不要求 PDF 模板。</div></label>
            <div class="grid">
                <label class="field">沙箱 Client Code<input name="client_code" type="text" maxlength="64" spellcheck="false" autocomplete="off"><div class="help">使用顺丰沙箱页的接入编码。</div></label>
                <label class="field">沙箱 Check Word<input name="check_word" type="password" maxlength="256" autocomplete="off"><div class="help">只进入当前子进程内存；提交后清空此输入框。</div></label>
                <label class="field">PDF 模板编码（仅电子面单必填）<input name="template_code" type="text" maxlength="100" spellcheck="false" autocomplete="off"><div class="help">填写沙箱账户可用的模板编码，不是模板名称；上门取件可留空。</div></label>
                <label class="field">沙箱月结卡号（可选）<input name="monthly_card" type="text" inputmode="numeric" maxlength="10" autocomplete="off"><div class="help">有则填写 10 位沙箱卡号；无卡留空，按寄付现结验证。</div></label>
                <label class="field">原沙箱运单号（仅 PDF 诊断使用）<input name="original_waybill_no" type="text" maxlength="22" spellcheck="false" autocomplete="off" placeholder="SF 加 10 至 20 位数字"><div class="help">填写已取得的原单。PDF 诊断只请求这张原单面单，不创建新订单，不取新号。</div></label>
            </div>
            <label class="confirm"><input id="confirm" type="checkbox"><span>我确认使用的是沙箱凭据。“开始沙箱联调”会创建和取消沙箱测试单；“仅核对原单 PDF”只请求原单面单，不会取新号。两者均不向生产环境叫件。</span></label>
            <div class="actions">
                <button type="submit" id="probe" value="probe">先检查本机环境（不外呼）</button>
                <button type="button" class="primary" id="run" disabled>开始沙箱联调</button>
                <button type="button" id="pdf-probe" disabled>仅核对原单 PDF（不取号）</button>
                <button type="button" id="clear">清空输入</button>
            </div>
        </form>
    </section>
    <section class="card" id="results" hidden aria-live="polite">
        <h2>联调结果</h2>
        <div class="status" id="status"></div>
        <pre id="output" hidden></pre>
    </section>
    <p class="muted">若网络中断或出现“待核实”，先核对原沙箱单。关闭此本机服务后，入口即不可用；请勿把此工具部署到公网。</p>
</main>
<script nonce="<?= $nonceAttr ?>">
    'use strict';
    const csrf = <?= json_encode($csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const form = document.getElementById('form');
    const run = document.getElementById('run');
    const pdfProbe = document.getElementById('pdf-probe');
    const confirm = document.getElementById('confirm');
    const status = document.getElementById('status');
    const output = document.getElementById('output');
    let busy = false;
    const syncButtons = () => {
        for (const item of form.querySelectorAll('input,select,button')) item.disabled = busy;
        run.disabled = busy || !confirm.checked;
        pdfProbe.disabled = busy || !confirm.checked;
    };
    confirm.addEventListener('change', syncButtons);
    document.getElementById('clear').addEventListener('click', () => { form.reset(); syncButtons(); });
    form.addEventListener('submit', event => { event.preventDefault(); void execute('probe'); });
    run.addEventListener('click', () => { void execute('run'); });
    pdfProbe.addEventListener('click', () => { void execute('pdf_probe'); });
    async function execute(action) {
        if (busy || (action !== 'probe' && !confirm.checked)) return;
        const config = Object.fromEntries(new FormData(form).entries());
        const scene = action === 'pdf_probe' ? 'waybill' : config.scene;
        const original_waybill_no = config.original_waybill_no;
        delete config.scene;
        delete config.original_waybill_no;
        busy = true; syncButtons();
        document.getElementById('results').hidden = false;
        status.className = 'status';
        status.textContent = action === 'probe' ? '正在检查本机环境，不调用顺丰…'
            : action === 'pdf_probe' ? '正在核对原沙箱单 PDF，不新建订单、不取号…'
            : `正在请求顺丰沙箱并验证${scene === 'pickup' ? '上门取件' : '电子面单'}，请勿重复提交…`;
        output.hidden = true; output.textContent = '';
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 195000);
        try {
            const pending = fetch('/run', {method:'POST', mode:'same-origin', credentials:'same-origin', cache:'no-store',
                headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
                body:JSON.stringify({action,scene,config,original_waybill_no,confirm_sandbox:confirm.checked}), signal:controller.signal});
            form.elements.check_word.value = '';
            config.check_word = '';
            const response = await pending;
            const result = await response.json();
            status.textContent = result.message || '执行结束，请查看阶段结果。';
            status.className = result.ok ? 'status' : 'status error';
            if (result.result) { output.textContent = JSON.stringify(result.result, null, 2); output.hidden = false; }
        } catch (_) {
            status.className = 'status error';
            status.textContent = '连接中断或未收到完整结果，不能据此判定未取号。请先核对原沙箱单，再决定下一步。';
        } finally {
            clearTimeout(timeout); busy = false; syncButtons();
        }
    }
</script>
</body>
</html>
