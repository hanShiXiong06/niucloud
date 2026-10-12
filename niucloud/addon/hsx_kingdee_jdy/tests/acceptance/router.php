<?php
declare(strict_types=1);

use HsxJdyAcceptance\Environment;
use addon\hsx_kingdee_jdy\app\adminapi\controller\Workbench;
use addon\hsx_kingdee_jdy\app\service\ConfigService;
use addon\hsx_kingdee_jdy\app\service\ConnectionService;
use addon\hsx_kingdee_jdy\app\gateway\Client;
use addon\hsx_kingdee_jdy\app\gateway\Transport;
use addon\hsx_kingdee_jdy\app\support\Problem;
use think\facade\Db;

require __DIR__ . '/Environment.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$reply = static function (array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
try {
    if (PHP_SAPI !== 'cli-server' || ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' || ($_SERVER['HTTP_HOST'] ?? '') !== '127.0.0.1:5202') {
        $reply(['code' => 0, 'msg' => 'Local acceptance only'], 403); return;
    }
    $state = json_decode(file_get_contents(Environment::path()), true, 512, JSON_THROW_ON_ERROR);
    if (!hash_equals($state['key'], (string)($_SERVER['HTTP_X_ACCEPTANCE_KEY'] ?? ''))) { $reply(['code' => 0, 'msg' => 'Forbidden'], 403); return; }
    $method = $_SERVER['REQUEST_METHOD'];
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (!in_array($method, ['GET', 'POST'], true) || (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 32768) { $reply(['code' => 0, 'msg' => 'Unsupported request'], 400); return; }
    $raw = file_get_contents('php://input');
    $input = $method === 'POST' ? json_decode($raw ?: '{}', true, 16, JSON_THROW_ON_ERROR) : [];
    if (!is_array($input)) throw new \RuntimeException('Expected object');
    $env = Environment::boot(false, $input);
    if ($path === '/api/acceptance/summary' && $method === 'GET') $data = $env->summary();
    elseif ($path === '/api/acceptance/cases' && $method === 'POST') $data = ['id' => $env->newCase()];
    elseif (preg_match('#^/api/acceptance/cases/([1-9][0-9]*)/step$#D', $path, $m) && $method === 'POST') $data = $env->advance((int)$m[1], (int)($input['step'] ?? -1));
    elseif ($path === '/api/acceptance/notices' && $method === 'GET') $data = Db::name('jdy_acceptance_notice')->order('id desc')->limit(100)->select()->toArray();
    elseif ($path === '/api/acceptance/fault' && $method === 'POST') {
        $fault = (string)($input['fault'] ?? 'none');
        if (!in_array($fault, ['none', 'auth', 'timeout'], true)) throw new Problem('FAULT_INVALID', '请选择有效模拟场景');
        $env->setMeta('fault', $fault);
        $row = \app\model\sys\SysConfig::where('site_id', Environment::SITE)->where('config_key', ConfigService::KEY)->find();
        $v = $row->value; $v['probe_next_at'] = 0; $v['probe_at'] = 0; $v['probe_revision'] = 0; $row->save(['value' => $v]);
        \think\facade\Cache::delete('hsx_kingdee_jdy_token_' . Environment::SITE . '_' . $v['revision']);
        $data = ['fault' => $fault];
    } elseif ($path === '/api/kingdee_jdy/connection/probe' && $method === 'POST') {
        $fake = new class($env->meta('fault')) implements Transport {
            private string $fault;
            public function __construct(string $fault) { $this->fault = $fault; }
            public function send(string $method, string $path, array $query, array $headers, array $body): array {
                if ($this->fault === 'timeout') throw new Problem('SIMULATED_TIMEOUT', '本地注入的网络超时，未发出外部请求', true);
                if ($this->fault === 'auth') return ['errcode' => 1, 'code' => -2];
                return str_contains($path, 'auth_token') ? ['errcode' => 0, 'data' => ['access_token' => 'local-only-token', 'expires' => (time() + 3600) * 1000]] : ['code' => 0, 'items' => [], 'records' => 0];
            }
        };
        (new ConnectionService())->probe(Environment::SITE, 1, (int)($input['revision'] ?? -1), new Client($fake));
        $data = ['status' => 'simulation', 'message' => '本地授权流程模拟通过；未连接精斗云，真实授权仍未验证', 'write_enabled' => false];
    } else {
        $routes = ['GET /api/kingdee_jdy/config' => 'config', 'POST /api/kingdee_jdy/config' => 'saveConfig',
            'GET /api/kingdee_jdy/staff' => 'staff', 'GET /api/kingdee_jdy/tasks' => 'tasks',
            'GET /api/kingdee_jdy/mappings' => 'mappings', 'POST /api/kingdee_jdy/mappings' => 'saveMapping', 'GET /api/kingdee_jdy/logs' => 'logs'];
        $action = $routes[$method . ' ' . $path] ?? '';
        $args = [];
        if (preg_match('#^/api/kingdee_jdy/tasks/([1-9][0-9]*)(?:/(retry|resolve|notice))?$#D', $path, $m)) {
            if ($method === 'GET' && empty($m[2])) $action = 'detail';
            elseif ($method === 'POST' && !empty($m[2])) $action = $m[2];
            $args = [(int)$m[1]];
        }
        if (!$action) { $reply(['code' => 0, 'msg' => 'Not found'], 404); return; }
        if ($action === 'saveConfig') {
            foreach (['client_id' => '999001', 'app_key' => 'LOCAL-FIXTURE', 'account_id' => '999000001', 'domain' => 'https://tf.jdy.com'] as $field => $fixed) {
                if (($input[$field] ?? '') !== $fixed) throw new Problem('FIXTURE_ONLY', '隔离验收环境只接受预置模拟授权，不要输入真实账号或密钥');
            }
            foreach (['client_secret' => 'local-fixture-client', 'app_secret' => 'local-fixture-app'] as $field => $fixed) {
                if (!empty($input[$field]) && $input[$field] !== $fixed) throw new Problem('FIXTURE_ONLY', '请留空保留模拟密钥，不要输入真实密钥');
            }
        }
        $result = (new Workbench($env->app))->{$action}(...$args)->getData();
        if (in_array($action, ['config', 'saveConfig'], true)) {
            $result['data']['probe_revision'] = 0; $result['data']['probe_at'] = 0;
        }
        $reply($result); return;
    }
    $reply(['code' => 1, 'msg' => 'SUCCESS', 'data' => $data]);
} catch (Throwable $error) {
    $safe = $error instanceof Problem || $error instanceof \core\exception\CommonException;
    $reply(['code' => 0, 'msg' => $safe ? $error->getMessage() : '隔离验收请求失败，请检查本地服务',
        'error_location' => basename($error->getFile()) . ':' . $error->getLine()], 400);
} finally {
    if (isset($env)) \think\facade\Log::save();
}
