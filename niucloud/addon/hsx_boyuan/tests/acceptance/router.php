<?php
declare(strict_types=1);
$path=getenv('HSX_BOYUAN_ACCEPTANCE_STATE');
if (!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true) || !$path || !is_file($path)) { http_response_code(403); exit; }
$state=json_decode(file_get_contents($path),true);
if (!hash_equals($state['key'],$_SERVER['HTTP_X_ACCEPTANCE_KEY']??'')) { http_response_code(403); exit; }
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
try {
    require dirname(__DIR__).'/bootstrap.php'; require dirname(__DIR__).'/Fixtures.php';
    [$app,$pdo,$schema,$site]=boyuanBoot($state['schema']);
    $post=json_decode(file_get_contents('php://input'),true)?:[]; request()->withServer($_SERVER)->withGet($_GET)->withPost($post);
    $route=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH); $method=$_SERVER['REQUEST_METHOD'];
    $uat=getenv('HSX_BOYUAN_ACCEPTANCE_UAT_READONLY')==='1';
    if($uat && $method==='POST' && $route==='/api/boyuan/config') {
        if(($post['mode']??'')!=='test' || !empty($post['enabled']) || empty($post['paused']) || !empty($post['finance_enabled'])
            || ($post['profile']['protocol']??'')!=='openapi_v153' || ($post['profile']['endpoint']??'')!=='https://open-api-uat.139erp.com/api'
            || ($post['profile']['gateway_path']??'')!=='/gateway.do') throw new \RuntimeException('UAT acceptance is restricted to reference reads');
    }
    if ($route==='/api/demo/purchase' && $method==='POST') {
        if($uat) throw new \RuntimeException('Business fixtures are disabled in UAT reference acceptance');
        if (!in_array($post['scenario']??'',['success','rejected','timeout_after_commit','partial','malformed'],true)) throw new RuntimeException('invalid scenario');
        $id=\addon\hsx_boyuan\tests\Fixtures::purchase($site,$post['scenario'],(bool)($post['mapped']??true)); echo json_encode(['code'=>1,'data'=>['id'=>$id]]); exit;
    }
    $controller=new \addon\hsx_boyuan\app\adminapi\controller\Workbench($app);
    $routes=['GET /api/boyuan/config'=>'config','POST /api/boyuan/config'=>'saveConfig','POST /api/boyuan/probe'=>'probe','GET /api/boyuan/tasks'=>'tasks',
        'GET /api/boyuan/mappings'=>'mappings','POST /api/boyuan/mappings'=>'saveMapping','GET /api/boyuan/logs'=>'logs','GET /api/boyuan/staff'=>'staff','GET /api/boyuan/references'=>'references',
        'GET /api/boyuan/reference-sync'=>'referenceJobs','POST /api/boyuan/reference-sync'=>'syncReferences','GET /api/boyuan/mapping-sources'=>'mappingSources','POST /api/boyuan/reference-search'=>'references',
        'POST /api/boyuan/task-batch'=>'batch','POST /api/boyuan/mapping-candidates'=>'mappingCandidates','POST /api/boyuan/task-batch-status'=>'batchStatus'];
    $args=[]; $key=$method.' '.$route;
    if(preg_match('#^/api/boyuan/reference-sync/(\d+)/retry$#D',$route,$match) && $method==='POST') { $handler='retryReference'; $args=[(int)$match[1]]; }
    elseif (preg_match('#^/api/boyuan/tasks/(\d+)(/action)?$#D',$route,$match)) { $handler=$method==='GET' && empty($match[2])?'detail':($method==='POST' && !empty($match[2])?'action':''); $args=[(int)$match[1]]; }
    else $handler=$routes[$key]??'';
    if ($handler==='') { http_response_code(404); echo '{"code":0,"msg":"未找到接口"}'; exit; }
    $response=$controller->$handler(...$args); echo $response->getContent();
} catch(Throwable $e) { http_response_code(400); echo json_encode(['code'=>0,'msg'=>$e instanceof \core\exception\CommonException || $e instanceof \addon\hsx_boyuan\app\support\Problem ? $e->getMessage() : '本地验收接口未完成：'.get_class($e), 'diagnostic'=>\think\facade\Log::getLog()],JSON_UNESCAPED_UNICODE); }
