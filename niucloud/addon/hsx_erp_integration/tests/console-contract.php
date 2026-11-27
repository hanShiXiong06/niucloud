<?php
declare(strict_types=1);
// Local PHP only. Never connects to a real ERP, queue or business database.
use addon\hsx_erp_integration\app\support\{ConsoleMenu, ConnectionFields, TaskView};
use addon\hsx_erp_integration\app\service\AdapterRegistry;

$addon = dirname(__DIR__); $root = dirname($addon, 2);
require $root . '/vendor/autoload.php';
require_once $root . '/vendor/topthink/framework/src/helper.php';
$app = new think\App($root);
$app->env->set('app.auth_key', bin2hex(random_bytes(32))); // ephemeral
function check($ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
$n = 0;
foreach ([false, true] as $site) {
    $menu = require $addon . '/app/dict/menu/' . ($site ? 'site' : 'admin') . '.php';
    check(count($menu[0]['children']) === 5, 'five pages per scope');
    foreach ($menu[0]['children'] as $page) {
        check(is_file($addon . '/admin/views/' . $page['view_path'] . '.vue'), $page['view_path']);
        foreach ($page['children'] as $button) $access[$button['methods'] . ':' . $button['api_url']] = true;
    }
}
foreach (ConsoleMenu::PAGES as [, , $actions]) foreach ($actions as [, $method, $path, $action]) {
    [$ctrl, $methodName] = explode('@', $action);
    check(method_exists('addon\\hsx_erp_integration\\app\\adminapi\\controller\\' . $ctrl, $methodName), $action);
    check(isset($access[$method . ':integration/' . str_replace(':id', '<id>', $path)]), $path); ++$n;
}
require $addon . '/app/adminapi/route/route.php';
$registered=0;
foreach ($app->route->getRuleName()->getRuleList() as $rule) {
    if (!str_starts_with($rule['rule'], 'integration/')) continue;
    check(isset($access[$rule['method'] . ':' . $rule['rule']]), 'unprotected route: ' . $rule['rule']);
    check(count($rule['option']['middleware'] ?? []) === 3, 'missing authentication or operation guard');
    ++$registered;
}
check($registered === $n, 'missing registered API');
$fields = [['name'=>'api_url','type'=>'text','required'=>1,'label'=>'URL'],['name'=>'api_token','type'=>'password','required'=>1,'label'=>'密钥']];
$plain = ConnectionFields::merge($fields, ['api_url'=>'https://example.invalid','api_token'=>'test-only','ignored'=>'discard'], []);
$sealed = ConnectionFields::seal($plain,$fields,42,'fixture');
check(strpos(json_encode($sealed),'test-only')===false,'do not store cleartext');
check(ConnectionFields::open($sealed,42,'fixture')===$plain, 'roundtrip');
try { ConnectionFields::open($sealed,43,'fixture'); throw new RuntimeException('wrong tenant accepted'); } catch (InvalidArgumentException $expected) {}
$view = ConnectionFields::present($fields,$sealed);
check($view['saved_secrets']['api_token'] && !isset($view['config']['api_token']), 'secret visibility');
check(ConnectionFields::merge($fields,['api_token'=>''],$plain)===$plain, 'blank must preserve secret');
try { ConnectionFields::merge($fields,['api_url'=>'https://changed.invalid'],$plain); throw new RuntimeException('reused credential on another endpoint'); } catch (InvalidArgumentException $expected) {}
check(TaskView::canRetry(['status'=>'failed','error_code'=>'MAPPING_MISSING']), 'preflight retry');
foreach ([['status'=>'failed','error_code'=>'TIMEOUT'],['status'=>'failed','error_code'=>'MAPPING_MISSING','external_doc_no'=>'DEMO'],['status'=>'succeeded','error_code'=>'MAPPING_MISSING']] as $task)
    check(!TaskView::canRetry($task), 'must not repeat ambiguous or accepted tasks');
check(TaskView::snapshot(['nested'=>['app_secret'=>'x']])['nested']['app_secret']==='******', 'snapshot redaction');
check(AdapterRegistry::fromAddons([])->getAdapterList()===[], 'no fabricated adapters');
if (is_dir(dirname($addon) . '/hsx_boyuan/app/adapter')) {
    $adapters = AdapterRegistry::fromAddons(['hsx_boyuan'])->getAdapterList();
    check(count($adapters)===1 && $adapters[0]['code']==='boyuan' && !empty($adapters[0]['fields']), 'fresh process discovery');
    check(AdapterRegistry::fromAddons([])->getAdapterList()===[], 'do not leak adapters across sites');
}
echo "PASS: 5 PC pages, $n route/action/permission contracts, adapter discovery, credential handling, retry guards.\n";
