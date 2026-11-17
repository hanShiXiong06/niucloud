<?php
declare(strict_types=1);

/**
 * Pure in-memory regression harness. Does not load application bootstrap, .env,
 * PDO, vendor services, customer rows, or any network transport.
 * Run: php addon/hsx_boyuan/tests/purchase-sync.php (from niucloud/).
 */
namespace {
    // Production composer provides php81-polyfill; keep this standalone fake harness PHP 8.0-safe.
    if(!function_exists('array_is_list')) {
        function array_is_list(array $values): bool { $index=0; foreach($values as $key=>$unused) if($key!==$index++) return false; return true; }
    }
}
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace think\facade {
    final class Db
    {
        public static array $tables=[];
        public static ?string $failTable=null;
        public static function name(string $table): Query { if(self::$failTable===$table) throw new \RuntimeException('Synthetic local read failure'); return new Query($table); }
        public static function transaction(callable $callback) { $backup=self::$tables; try { return $callback(); } catch(\Throwable $error) { self::$tables=$backup; throw $error; } }
    }
    final class Rows { public function __construct(private array $rows) {} public function toArray(): array { return $this->rows; } }
    final class Query
    {
        private array $conditions=[]; private string $sort='';
        public function __construct(private string $table) {}
        public function where($field,$operator=null,$value=null): self
        {
            if(is_array($field)) { foreach($field as $condition) $this->where(...$condition); return $this; }
            if(func_num_args()===2) { $value=$operator; $operator='='; }
            $this->conditions[]=[$field,$operator,$value]; return $this;
        }
        public function whereIn(string $field,array $values): self { $this->conditions[]=[$field,'in',$values]; return $this; }
        public function lock(bool $lock=true): self { return $this; }
        public function field(string $fields): self { return $this; }
        public function order(string $sort): self { $this->sort=$sort; return $this; }
        private function matches(array $row): bool
        {
            foreach($this->conditions as [$field,$operator,$value]) {
                $actual=$row[$field]??null;
                $ok=match($operator) {'='=>$actual==$value,'<>'=>$actual!=$value,'>'=>$actual>$value,'>='=>$actual>=$value,'<'=>$actual<$value,'<='=>$actual<=$value,'in'=>in_array($actual,$value,true),default=>throw new \RuntimeException('Unsupported fake operator')};
                if(!$ok) return false;
            }
            return true;
        }
        private function rows(): array
        {
            $rows=array_values(array_filter(Db::$tables[$this->table]??[],fn($row)=>$this->matches($row)));
            if($this->sort!=='') { [$key,$direction]=array_pad(explode(' ',$this->sort),2,'asc'); usort($rows,fn($a,$b)=>(($a[$key]??0)<=>($b[$key]??0))*($direction==='desc'?-1:1)); }
            return $rows;
        }
        public function find(): ?array { return $this->rows()[0]??null; }
        public function select(): Rows { return new Rows($this->rows()); }
        public function count(): int { return count($this->rows()); }
        public function column(string $field): array { return array_column($this->rows(),$field); }
        public function value(string $field) { return $this->find()[$field]??null; }
        public function insert(array $row): int { $this->insertGetId($row); return 1; }
        public function insertGetId(array $row): int
        {
            $id=(int)($row['id']??(count(Db::$tables[$this->table]??[])+1));
            if($this->table==='hsx_boyuan_task') $row+=['revision'=>1,'attempts'=>0,'side_effect'=>'none','status'=>'pending','reason'=>'','next_at'=>0,'lease_token'=>'','lease_until'=>0,'external_no'=>'','resolution'=>'','query_only'=>0];
            Db::$tables[$this->table][$id]=$row+['id'=>$id]; return $id;
        }
        public function update(array $values): int
        {
            $changed=0;
            foreach(Db::$tables[$this->table]??[] as $id=>$row) if($this->matches($row)) { Db::$tables[$this->table][$id]=array_replace($row,$values); $changed++; }
            return $changed;
        }
    }
}
namespace addon\hsx_boyuan\app\service {
    final class PermissionService
    {
        public static bool $canViewPayables=true;
        public function assertApi(string $method,string $api): void { if($api==='erp/finance/payable/lists' && !self::$canViewPayables) throw new \core\exception\CommonException('No payable permission'); }
    }
    final class ConfigService
    {
        public static array $config=[];
        public static ?\Closure $onGet=null; public static array $lastTransportProfile=[];
        public function installed(int $site): bool { return $site===1; }
        public function get(int $site,bool $public=true): array { if(self::$onGet) (self::$onGet)(); return self::$config; }
        public function transport(int $site,array $profile,string $mode): \addon\hsx_boyuan\app\gateway\Transport { self::$lastTransportProfile=$profile; return new \addon\hsx_boyuan\app\gateway\OpenApi(); }
    }
    final class ReferenceService
    {
        public static function latestRun(int $site,string $key,string $resource): string { return (string)\think\facade\Db::name('hsx_boyuan_reference_sync')->where('site_id',$site)->where('connection_key',$key)->where('resource',$resource)->where('status','completed')->order('id desc')->value('run_key'); }
    }
    final class TaskService
    {
        public static array $logs=[];
        public static function audit(int $site,int $task,string $connection,string $action,string $outcome,string $message,int $uid=0,array $detail=[]): void { self::$logs[]=compact('site','task','action','outcome','message','uid','detail'); }
    }
}
namespace addon\hsx_boyuan\app\gateway {
    interface Transport { public function send(string $method,string $path,array $payload=[]): array; }
    final class OpenApi implements Transport { public function send(string $method,string $path,array $payload=[]): array { throw new \RuntimeException('Network is forbidden by this harness'); } }
    final class PurchaseGateway
    {
        public static int $creates=0; public static int $queries=0; public static string $scenario='success'; public static ?\Closure $onCreate=null; public static ?\Closure $onVerify=null;
        public function __construct(OpenApi $api) {}
        public function create(array $request): array
        {
            self::$creates++;
            $task=\think\facade\Db::name('hsx_boyuan_task')->where('status','processing')->find();
            if(!$task || $task['side_effect']!=='unknown') throw new \RuntimeException('Side effect must be persisted before create');
            if(self::$onCreate) (self::$onCreate)();
            if(self::$scenario==='timeout') throw new \addon\hsx_boyuan\app\support\Problem('TIMEOUT','建单响应超时','unknown');
            return ['created'=>true,'external_no'=>'123456','external_id'=>'external-head','response'=>['secret'=>'do not persist raw reply']];
        }
        public function verify(array $request,string $externalNo): array
        {
            self::$queries++; $lines=[];
            if(self::$onVerify) (self::$onVerify)();
            foreach($request['lines'] as $index=>$line) $lines[]=['source_line_id'=>$line['source_line_id'],'device_ref'=>$line['device_ref'],'imei'=>$line['imei'],'external_document_id'=>'external-head','external_line_id'=>'external-line-'.$index];
            if(self::$scenario==='incomplete') array_pop($lines);
            return ['verified'=>true,'external_no'=>$externalNo,'external_id'=>'external-head','lines'=>$lines,'verification_level'=>'purchase_imei_records_matched','stock_balance_verified'=>false,'financial_posting_verified'=>false,
                'evidence'=>['company_id'=>'C1','purchase_detail_count'=>count($lines),'imei_detail_count'=>count($lines),'bill_type'=>'PUR','record_is_effective'=>true,'response_contract'=>'fake-explicit-contract','external_line_id_source'=>'purchase_detail.id','document_head_id_verified'=>false],'unexpected_sensitive_field'=>'not persisted'];
        }
    }
}
namespace {
    use addon\hsx_boyuan\app\gateway\{OpenApi,PurchaseGateway};
    use addon\hsx_boyuan\app\service\{ConfigService,PurchaseSyncService,PurchaseClosureService,PermissionService,TaskService};
    use addon\hsx_boyuan\app\support\{Contract,Problem};
    use think\facade\Db;
    require __DIR__.'/../app/support/Problem.php';
    require __DIR__.'/../app/support/Contract.php';
    require __DIR__.'/../app/service/PurchaseClosureService.php';
    require __DIR__.'/../app/service/PurchaseSyncService.php';
    $assertions=0;
    function check(bool $condition,string $label): void { global $assertions; $assertions++; if(!$condition) throw new RuntimeException('FAIL: '.$label); }
    function rejects(callable $callback,string $reason): void { try { $callback(); } catch(Problem $error) { check($error->reason===$reason,'rejects '.$reason.' (got '.$error->reason.')'); return; } throw new RuntimeException('FAIL: expected '.$reason); }
    function seed(): array
    {
        Db::$tables=[]; Db::$failTable=null; PermissionService::$canViewPayables=true; TaskService::$logs=[]; PurchaseGateway::$creates=PurchaseGateway::$queries=0; PurchaseGateway::$scenario='success'; PurchaseGateway::$onCreate=PurchaseGateway::$onVerify=null; ConfigService::$onGet=null; ConfigService::$lastTransportProfile=[];
        ConfigService::$config=['mode'=>'test','enabled'=>true,'purchase_enabled'=>true,'purchase_mode'=>'manual','finance_enabled'=>false,'paused'=>false,'started_at'=>time()-100,
            'profiles'=>['test'=>['protocol'=>'openapi_v153','connection_key'=>str_repeat('a',32),'probe_at'=>time()]]];
        Db::name('site')->insert(['site_id'=>1]); $now=time()-10;
        Db::name('erp_purchase_order')->insert(['id'=>1,'site_id'=>1,'status'=>'completed','purchase_no'=>'PUR-1','party_id'=>1,'purchase_at'=>$now,'create_at'=>$now,'purchaser_uid'=>1,'purchaser_name'=>'采购员']);
        $lines=[];
        foreach([1,2] as $id) {
            $product=['model'=>'Model '.$id,'spec'=>'256GB','color'=>'Black']; $imei='00000000000000'.$id;
            Db::name('erp_asset')->insert(['id'=>$id,'site_id'=>1,'imei'=>$imei,'sn'=>'','warehouse_id'=>1]+$product);
            Db::name('erp_purchase_item')->insert(['id'=>$id,'site_id'=>1,'purchase_order_id'=>1,'asset_id'=>$id,'imei'=>$imei,'sn'=>'','quantity'=>'1.000','purchase_cost'=>'100.00','warehouse_id'=>1]+$product);
            $lines[]=['source_line_id'=>'purchase_line:'.$id,'device_ref'=>'asset:'.$id,'product_ref'=>'model:'.hash('sha256',json_encode($product,JSON_UNESCAPED_UNICODE)),'product_name'=>implode(' ',$product),'imei'=>$imei,'sn'=>'','warehouse_ref'=>'warehouse:1','quantity'=>'1.000','amount'=>'100.00'];
        }
        $snapshot=['kind'=>'purchase','source_document_id'=>'purchase:1','source_document_no'=>'PUR-1','batch_ref'=>hash('sha256',implode('|',array_column($lines,'source_line_id'))),'occurred_at'=>$now,'party'=>['ref'=>'party:1','name'=>'本地供应商','mobile'=>'13800000000'],'operator'=>['operator_ref'=>'staff:1','name'=>'本地操作员'],'lines'=>$lines,'financial_items'=>[['amount'=>'200.00']]];
        $event=['event_version'=>1,'site_id'=>1,'event_id'=>'event-1','event_name'=>'erp.integration.document.confirmed.v1','occurred_at'=>$now,'payload'=>$snapshot];
        $key=str_repeat('a',32);
        foreach(['companies','parties','products','warehouses','staff'] as $resource) Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>1,'connection_key'=>$key,'resource'=>$resource,'status'=>'completed','run_key'=>'latest-'.$resource]);
        foreach([['companies','C1',[]],['companies','C2',[]],['parties','S1',['is_supplier'=>1]],['parties','CUSTOMER',['is_cust'=>1]],['products','P1',['imei_count'=>1,'imei_len'=>15]],['products','P2',['imei_count'=>2,'imei_len'=>15]],['warehouses','W1',['company_id'=>'C1']],['warehouses','W2',['company_id'=>'C1']],['warehouses','W3',['company_id'=>'C2']],['staff','B1',['is_buyer'=>1]],['staff','SELLER',['is_sales'=>1]]] as [$resource,$id,$extra]) {
            Db::name('hsx_boyuan_reference')->insert(['site_id'=>1,'connection_key'=>$key,'resource'=>$resource,'external_id'=>$id,'code'=>$id,'name'=>$id,'enabled'=>1,'seen_run'=>'latest-'.$resource,'extra_json'=>Contract::json($extra)]);
        }
        return [$event,$snapshot];
    }
    function selection(array $snapshot): array { return ['company_id'=>'C1','supplier_id'=>'S1','creator_id'=>'B1','buyer_id'=>'B1','lines'=>array_map(fn($line)=>['source_line_id'=>$line['source_line_id'],'product_id'=>'P1','warehouse_id'=>'W1'],$snapshot['lines'])]; }
    function input(array $view,?array $selection=null): array { $input=array_intersect_key($view['task'],array_flip(['revision','mode','connection_key'])); if($selection!==null) $input+=['selection'=>$selection,'remember'=>false]; return $input; }
    function ready(): void { Db::name('hsx_boyuan_task')->update(['next_at'=>0]); }
    function row(): array { return Db::name('hsx_boyuan_task')->where('id',1)->find(); }
    function newTask(PurchaseSyncService $service,array $event): array { $service->capture(1,$event); return $service->view(1,1); }

    $service=new PurchaseSyncService(); [$event,$snapshot]=seed(); $view=newTask($service,$event); $original=row()['snapshot_json'];
    check($view['task']['status']==='awaiting_confirmation' && $view['editable'] && !$view['can_submit'],'new purchase is a manual draft');
    check(Db::name('hsx_boyuan_task')->count()===1,'no party or financial child tasks');
    check(PurchaseGateway::$creates===0 && !$service->process(1,1,new OpenApi()),'draft worker never calls gateway');
    $service->capture(1,$event); check(Db::name('hsx_boyuan_task')->count()===1,'event replay reuses exact fact');
    check(row()['snapshot_json']===$original,'captured source snapshot is immutable');
    $partial=$view['selection']; $partial['supplier_id']='S1'; $view=$service->saveDraft(1,1,7,input($view,$partial));
    check($view['task']['revision']===2 && !$view['can_submit'] && PurchaseGateway::$creates===0,'incomplete draft saves without external effect');
    $chosen=selection($snapshot); $bad=$chosen; $bad['supplier_id']='CUSTOMER'; rejects(fn()=>$service->submit(1,1,7,input($view,$bad)),'SUPPLIER_ROLE_INVALID');
    $bad=$chosen; $bad['buyer_id']='SELLER'; rejects(fn()=>$service->submit(1,1,7,input($view,$bad)),'BUYER_ROLE_INVALID');
    $bad=$chosen; $bad['creator_id']=''; rejects(fn()=>$service->submit(1,1,7,input($view,$bad)),'PURCHASE_SELECTION_REQUIRED');
    $bad=$chosen; $bad['lines'][0]['warehouse_id']='W3'; rejects(fn()=>$service->submit(1,1,7,input($view,$bad)),'WAREHOUSE_COMPANY_MISMATCH');
    $bad=$chosen; $bad['lines'][1]['warehouse_id']='W2'; rejects(fn()=>$service->submit(1,1,7,input($view,$bad)),'PURCHASE_MULTI_WAREHOUSE');
    $bad=$chosen; $bad['lines'][0]['imei']='ALTERED'; rejects(fn()=>$service->submit(1,1,7,input($view,$bad)),'PURCHASE_READ_ONLY_FACT');
    $bad=$chosen; $bad['lines'][0]['product_id']='P2'; rejects(fn()=>$service->submit(1,1,7,input($view,$bad)),'PRODUCT_SERIAL_UNSUPPORTED');
    $badInput=input($view,$chosen); $badInput['revision']=1; rejects(fn()=>$service->submit(1,1,7,$badInput),'TASK_STALE');
    $badInput=input($view,$chosen); $badInput['connection_key']='other'; rejects(fn()=>$service->submit(1,1,7,$badInput),'CONNECTION_CHANGED');
    $badInput=input($view,$chosen); $badInput['amount']='0.01'; rejects(fn()=>$service->submit(1,1,7,$badInput),'PURCHASE_READ_ONLY_FACT');
    Db::name('hsx_boyuan_reference')->where('external_id','P1')->update(['seen_run'=>'old']); rejects(fn()=>$service->submit(1,1,7,input($view,$chosen)),'ARCHIVE_STALE');
    Db::name('hsx_boyuan_reference')->where('external_id','P1')->update(['seen_run'=>'latest-products']);
    $view=$service->submit(1,1,7,input($view,$chosen)); $frozen=row()['request_json']; $request=Contract::decode($frozen);
    check($view['task']['status']==='pending' && !$view['editable'] && $request['stage']==='frozen','submission freezes the exact request');
    check(strlen($request['third_no'])===32 && $request['biz']['head']['cash_term']===0 && !isset($request['biz']['cash_details']),'stable bounded order number and no payment posting');
    check($request['biz']['head']['creater_id']==='B1' && $request['biz']['head']['creater_name']==='B1','creator external identity and archived name are frozen in official head fields');
    check($request['lines'][0]['imei']==='000000000000001' && $request['lines'][0]['amount']==='100.00','zero-prefixed IMEI and exact decimal cost preserved');
    check(Db::name('hsx_boyuan_mapping')->count()===0,'default selection does not pollute global mappings');
    rejects(fn()=>$service->submit(1,1,7,input($view,$chosen)),'PURCHASE_FROZEN');
    ready(); check($service->process(1,1,new OpenApi()),'frozen task is processed'); $view=$service->view(1,1);
    check(PurchaseGateway::$creates===1 && row()['status']==='created_review' && row()['external_no']==='123456','create preserves bill number and is not success');
    check(!str_contains(row()['result_json'],'secret') && !str_contains(row()['result_json'],'response'),'raw gateway response not persisted');
    check(row()['request_json']===$frozen && row()['snapshot_json']===$original,'processing never changes frozen request or source');
    check(!$service->process(1,1,new OpenApi()) && PurchaseGateway::$creates===1,'created task cannot automatically create again');
    rejects(fn()=>$service->queueVerify(1,1,7,input($view)+['external_no'=>'different']),'EXTERNAL_NO_CONFLICT');
    $view=$service->queueVerify(1,1,7,input($view)+['external_no'=>'123456']); check(row()['status']==='confirming' && row()['query_only']===1,'verify queue is query-only');
    ready(); $service->process(1,1,new OpenApi()); $result=Contract::decode(row()['result_json']);
    check(row()['status']==='succeeded' && PurchaseGateway::$queries===1 && PurchaseGateway::$creates===1,'successful record verification never creates another document');
    check($result['stock_balance_verified']===false && $result['financial_posting_verified']===false,'record success does not claim real-time balance or finance verification');
    check($result['evidence']['document_head_id_verified']===false && $result['evidence']['response_contract']==='fake-explicit-contract' && $result['evidence']['external_line_id_source']==='purchase_detail.id','explicit query contract and negative head-ID verification evidence survive the persistence whitelist');
    check(Db::name('hsx_boyuan_binding')->where('kind','line')->count()===2,'verified source lines receive authoritative bindings');
    check(!str_contains(row()['result_json'],'unexpected_sensitive_field'),'result stores only allowed evidence');

    [$event,$snapshot]=seed(); $view=newTask($service,$event); Db::name('erp_purchase_item')->where('id',1)->update(['purchase_cost'=>'99.99']);
    rejects(fn()=>$service->submit(1,1,7,input($view,selection($snapshot))),'PURCHASE_SOURCE_CHANGED'); check(PurchaseGateway::$creates===0,'changed source never sends');
    Db::name('erp_purchase_item')->where('id',1)->update(['purchase_cost'=>'100.00']); $view=$service->submit(1,1,7,input($view,selection($snapshot)));
    Db::name('erp_purchase_order')->where('id',1)->update(['status'=>'void']); ready(); $service->process(1,1,new OpenApi());
    check(row()['status']==='attention' && row()['side_effect']==='none' && PurchaseGateway::$creates===0,'worker rechecks cancellation before any send');

    [$event,$snapshot]=seed(); $view=newTask($service,$event); $view=$service->submit(1,1,7,input($view,selection($snapshot))); $frozen=row()['request_json'];
    PurchaseGateway::$scenario='timeout'; ready(); $service->process(1,1,new OpenApi());
    check(row()['status']==='attention' && row()['side_effect']==='unknown' && PurchaseGateway::$creates===1,'timeout is durable unknown and requires attention');
    Db::name('hsx_boyuan_task')->where('id',1)->update(['status'=>'pending','next_at'=>0]); $service->process(1,1,new OpenApi());
    check(row()['status']==='attention' && PurchaseGateway::$creates===1 && row()['request_json']===$frozen,'even a pending reset cannot resend a possibly-sent frozen create');
    $view=$service->view(1,1); $service->queueVerify(1,1,7,input($view)+['external_no'=>'123456']); PurchaseGateway::$scenario='incomplete'; ready(); $service->process(1,1,new OpenApi());
    check(row()['status']==='needs_review' && PurchaseGateway::$creates===1 && Db::name('hsx_boyuan_binding')->count()===0,'incomplete verification is not success or partial binding');

    [$event,$snapshot]=seed(); $view=newTask($service,$event); $remember=input($view,selection($snapshot)); $remember['remember']=true; $service->saveDraft(1,1,7,$remember);
    check(Db::name('hsx_boyuan_mapping')->count()===5,'explicit remember stores supplier, buyer, two products and shared warehouse only');
    $view=$service->view(1,1); $chosen=selection($snapshot); $chosen['supplier_id']='S1'; Db::name('hsx_boyuan_mapping')->where('kind','supplier')->update(['external_id'=>'OTHER']);
    rejects(fn()=>$service->saveDraft(1,1,7,array_replace(input($view,$chosen),['remember'=>true])),'MAPPING_CONFLICT');

    [$event,$snapshot]=seed(); $view=newTask($service,$event); $service->submit(1,1,7,input($view,selection($snapshot)));
    PurchaseGateway::$onCreate=function() use($service): void { $service->capture(1,['site_id'=>1,'event_version'=>1,'event_id'=>'void-event','event_name'=>'erp.integration.document.invalidated.v1','payload'=>['original_document_ref'=>'purchase:1']]); };
    ready(); $service->process(1,1,new OpenApi());
    check(row()['status']==='attention' && row()['external_no']==='123456','in-flight invalidation retains lease and persists returned bill number for review');

    [$event,$snapshot]=seed(); $view=newTask($service,$event); $service->submit(1,1,7,input($view,selection($snapshot))); ready(); $service->process(1,1,new OpenApi());
    $view=$service->view(1,1); $service->queueVerify(1,1,7,input($view)+['external_no'=>'123456']);
    PurchaseGateway::$onVerify=static function(): void { Db::name('erp_purchase_order')->where('id',1)->update(['status'=>'void']); };
    ready(); $service->process(1,1,new OpenApi());
    check(row()['status']==='attention' && row()['side_effect']==='unknown' && Db::name('hsx_boyuan_binding')->count()===0,'source invalidation during read verification cannot bind or complete');
    check((Contract::decode(row()['result_json'])['verified']??false)===true,'remote record evidence is retained separately from local invalidation');

    [$event,$snapshot]=seed(); $view=newTask($service,$event); $service->submit(1,1,7,input($view,selection($snapshot))); ready();
    Db::name('hsx_boyuan_task')->where('id',1)->update(['status'=>'processing','lease_until'=>time()-1,'lease_token'=>'old-worker','attempts'=>1,'side_effect'=>'unknown']);
    $service->process(1,1,new OpenApi()); check(row()['status']==='attention' && PurchaseGateway::$creates===0,'stale create lease never grants permission to resend');

    [$event,$snapshot]=seed(); $view=newTask($service,$event); $service->submit(1,1,7,input($view,selection($snapshot))); ready(); $gets=0;
    ConfigService::$onGet=static function() use(&$gets): void { if(++$gets===2) ConfigService::$config['profiles']['test']['app_id']='new-claimed-profile'; };
    $service->process(1,1); check((ConfigService::$lastTransportProfile['app_id']??'')==='new-claimed-profile' && PurchaseGateway::$creates===1,'worker transport uses the profile read under the claim lock');
    [$event,$snapshot]=seed(); $view=newTask($service,$event); $service->submit(1,1,7,input($view,selection($snapshot))); ready(); $gets=0;
    ConfigService::$onGet=static function() use(&$gets): void { if(++$gets===3) ConfigService::$config['profiles']['test']['connection_key']='changed-after-claim'; };
    $service->process(1,1); check(row()['status']==='attention' && PurchaseGateway::$creates===0,'connection drift after claim is stopped before gateway create');

    [$event,$snapshot]=seed(); ConfigService::$config['purchase_mode']='automatic'; $service->capture(1,$event);
    check(row()['status']==='awaiting_confirmation' && PurchaseGateway::$creates===0,'automatic mode with incomplete mapping falls back to manual without calls');
    [$event,$snapshot]=seed(); $view=newTask($service,$event); $remember=input($view,selection($snapshot)); $remember['remember']=true; $service->saveDraft(1,1,7,$remember);
    Db::$tables['hsx_boyuan_task']=[]; ConfigService::$config['purchase_mode']='automatic'; $service->capture(1,$event);
    check(row()['status']==='pending' && PurchaseGateway::$creates===0,'complete automatic mapping reuses frozen-submit path, not inline sending');
    $automaticFrozen=row()['request_json']; check(Contract::decode($automaticFrozen)['auto_verify']===true,'new automatic task freezes its own read-verification intent');
    ConfigService::$config['purchase_mode']='manual'; ready(); $service->process(1,1,new OpenApi());
    check(row()['status']==='confirming' && row()['query_only']===1 && PurchaseGateway::$creates===1,'automatic create queues read-only verification even if current mode changes');
    ready(); $service->process(1,1,new OpenApi());
    check(row()['status']==='succeeded' && PurchaseGateway::$creates===1 && PurchaseGateway::$queries===1 && row()['request_json']===$automaticFrozen,'automatic task completes via one create plus read-only verification without changing frozen request');
    [$event,$snapshot]=seed(); $view=newTask($service,$event); $remember=input($view,selection($snapshot)); $remember['remember']=true; $service->saveDraft(1,1,7,$remember);
    Db::$tables['hsx_boyuan_task']=[]; ConfigService::$config['purchase_mode']='automatic'; $service->capture(1,$event); ready(); $service->process(1,1,new OpenApi());
    PurchaseGateway::$scenario='incomplete'; ready(); $service->process(1,1,new OpenApi());
    check(row()['status']==='needs_review' && PurchaseGateway::$creates===1 && Db::name('hsx_boyuan_binding')->count()===0,'automatic verification gaps return to manual review without another create or success binding');
    [$event,$snapshot]=seed(); $view=newTask($service,$event); $remember=input($view,selection($snapshot)); $remember['remember']=true; $service->saveDraft(1,1,7,$remember);
    ConfigService::$config['purchase_mode']='automatic'; $service->capture(1,$event);
    check(row()['status']==='awaiting_confirmation','replayed old draft never auto-submits after a mode change');
    [$event,$snapshot]=seed(); ConfigService::$config['purchase_started_at']=time(); $service->capture(1,$event);
    check(Db::name('hsx_boyuan_task')->count()===0,'purchase-specific enablement cutoff excludes earlier facts');

    [$event,$snapshot]=seed(); $view=newTask($service,$event); $chosen=selection($snapshot); $chosen['creator_id']='SELLER'; $chosen['buyer_id']='';
    $view=$service->submit(1,1,7,input($view,$chosen));
    check($view['task']['status']==='pending' && Contract::decode(row()['request_json'])['biz']['head']['creater_id']==='SELLER','creator is independent from optional procurement buyer and need not be is_buyer');

    [$event,$snapshot]=seed(); Db::name('erp_outbox_event')->insert(['site_id'=>1,'event_name'=>$event['event_name'],'occurred_at'=>$event['occurred_at'],'payload_json'=>Contract::json($event)]);
    $opened=$service->capturePurchaseId(1,7,1); check($opened['task_id']===1 && count($opened['task_ids'])===1,'explicit source button captures its confirmed new event');
    $service->capturePurchaseId(1,7,1); check(Db::name('hsx_boyuan_task')->count()===1 && PurchaseGateway::$creates===0,'source button reuses task and is not a create action');
    [$event,$snapshot]=seed(); $event['occurred_at']=time()-1000; Db::name('erp_outbox_event')->insert(['site_id'=>1,'event_name'=>$event['event_name'],'occurred_at'=>$event['occurred_at'],'payload_json'=>Contract::json($event)]);
    rejects(fn()=>$service->capturePurchaseId(1,7,1),'PURCHASE_FACT_MISSING'); check(Db::name('hsx_boyuan_task')->count()===0,'historical event is never imported');

    // Closure is a separately permissioned, read-only summary of this exact task batch.
    [$event,$snapshot]=seed(); $view=newTask($service,$event);
    foreach($snapshot['lines'] as $index=>$line) Db::name('erp_payable')->insert(['id'=>$index+1,'site_id'=>1,'party_id'=>1,'source_type'=>'purchase_asset','source_id'=>$index+1,'asset_id'=>$index+1,'amount'=>'100.00','settled_amount'=>'0.00','status'=>'pending']);
    $before=Db::$tables; $closure=(new PurchaseClosureService())->view(1,row());
    check($closure['available'] && $closure['complete']===false && $closure['scope']==='purchase_batch' && count($closure['stages'])===6,'closure has six explicit batch-scoped stages and never implies a complete cycle');
    check($closure['totals']===['currency'=>'CNY','amount'=>'200.00','paid_amount'=>'0.00','unpaid_amount'=>'200.00'],'unpaid batch retains precise decimal totals');
    check($closure['stages'][2]['status']==='unverified' && $closure['stages'][5]['status']==='blocked','missing stock and finance contracts are visibly unverified and blocked');
    check(Db::$tables===$before && PurchaseGateway::$creates===0 && PurchaseGateway::$queries===0,'closure neither changes business state nor invokes a gateway');
    $cross=(new PurchaseClosureService())->view(2,row()); check(!$cross['available'] && $cross['totals']['amount']===null && $cross['source']['source_line_ids']===[],'cross-site closure reveals no source identity or amount');
    PermissionService::$canViewPayables=false; $denied=(new PurchaseClosureService())->view(1,row());
    check($denied['stages'][3]['status']==='permission_denied' && $denied['stages'][4]['status']==='permission_denied' && $denied['totals']['amount']===null && $denied['stages'][0]['amount']===null,'task access does not grant payable or payment amount access');
    Db::name('erp_purchase_order')->where('id',1)->update(['status'=>'void']); $deniedChanged=(new PurchaseClosureService())->view(1,row());
    check(!$deniedChanged['available'] && $deniedChanged['stages'][3]['status']==='permission_denied' && $deniedChanged['totals']['amount']===null,'source invalidation cannot downgrade or bypass an established financial permission denial');
    Db::name('erp_purchase_order')->where('id',1)->update(['status'=>'completed']);
    PermissionService::$canViewPayables=true;
    Db::name('erp_payable')->where('id',1)->update(['settled_amount'=>'30.00','status'=>'partial']);
    Db::name('erp_settlement')->insert(['id'=>1,'site_id'=>1,'status'=>'confirmed','settlement_type'=>'payment','cash_direction'=>'out','confirmed_at'=>time(),'capital_account_name'=>'PRIVATE_ACCOUNT','voucher_urls'=>'PRIVATE_VOUCHER','party_name'=>'PRIVATE_CONTACT']);
    Db::name('erp_settlement_link')->insert(['site_id'=>1,'settlement_id'=>1,'target_type'=>'payable','target_id'=>1,'applied_amount'=>'30.00']);
    $closure=(new PurchaseClosureService())->view(1,row());
    check($closure['totals']['paid_amount']==='30.00' && $closure['totals']['unpaid_amount']==='170.00' && $closure['stages'][4]['status']==='partial','partial confirmed payment is not represented as full payment');
    check(!str_contains(Contract::json($closure),'PRIVATE_'),'closure never exposes account names, vouchers or contact fields');
    Db::name('erp_payable')->update(['settled_amount'=>'100.00','status'=>'settled']);
    Db::name('erp_settlement_link')->where('target_id',1)->update(['applied_amount'=>'100.00']);
    Db::name('erp_settlement_link')->insert(['site_id'=>1,'settlement_id'=>1,'target_type'=>'payable','target_id'=>2,'applied_amount'=>'100.00']);
    $closure=(new PurchaseClosureService())->view(1,row());
    check($closure['totals']['paid_amount']==='200.00' && $closure['totals']['unpaid_amount']==='0.00' && $closure['stages'][4]['status']==='verified' && !$closure['complete'],'full local payment remains distinct from unverified remote settlement');
    Db::name('erp_payable')->where('id',2)->update(['status'=>'void']); $closure=(new PurchaseClosureService())->view(1,row());
    check($closure['totals']['amount']==='100.00' && $closure['totals']['paid_amount']==='100.00' && $closure['stages'][3]['count']===1 && count($closure['warnings'])===1,'void payables and their prior payment links are excluded');
    Db::name('erp_payable')->where('id',2)->update(['status'=>'settled']);
    Db::$failTable='erp_settlement_link'; $failure=(new PurchaseClosureService())->view(1,row()); Db::$failTable=null;
    check(!$failure['available'] && $failure['totals']['paid_amount']===null && $failure['totals']['unpaid_amount']===null && $failure['stages'][4]['status']==='unavailable','query failure never fabricates zero paid or unpaid amounts');
    Db::name('erp_purchase_item')->where('id',1)->update(['purchase_cost'=>'99.00']); $changed=(new PurchaseClosureService())->view(1,row());
    check(!$changed['available'] && $changed['totals']['amount']===null && $changed['stages'][0]['status']==='unavailable','changed original purchase invalidates closure amounts rather than replacing the captured fact');
    Db::name('erp_purchase_item')->where('id',1)->update(['purchase_cost'=>'100.00']);
    Db::name('erp_settlement')->where('id',1)->update(['site_id'=>2]); $foreign=(new PurchaseClosureService())->view(1,row());
    check(!$foreign['available'] && $foreign['totals']['paid_amount']===null,'foreign-tenant settlement cannot substantiate payment for a local link');
    Db::name('erp_settlement')->where('id',1)->update(['site_id'=>1,'settlement_type'=>'offset','cash_direction'=>'none']); $offset=(new PurchaseClosureService())->view(1,row());
    check(!$offset['available'] && $offset['totals']['paid_amount']===null,'offset settlement is not silently classified as actual payment');

    [$event,$snapshot]=seed();
    foreach([1,2] as $lineId) {
        $part=$event; $part['event_id']='batch-'.$lineId; $part['payload']['lines']=[$snapshot['lines'][$lineId-1]];
        $part['payload']['batch_ref']=hash('sha256','purchase_line:'.$lineId); $service->capture(1,$part);
        Db::name('erp_payable')->insert(['id'=>$lineId,'site_id'=>1,'party_id'=>1,'source_type'=>'purchase_asset','source_id'=>$lineId,'asset_id'=>$lineId,'amount'=>'100.00','settled_amount'=>'100.00','status'=>'settled']);
        Db::name('erp_settlement_link')->insert(['site_id'=>1,'settlement_id'=>1,'target_type'=>'payable','target_id'=>$lineId,'applied_amount'=>'100.00']);
    }
    Db::name('erp_settlement')->insert(['id'=>1,'site_id'=>1,'status'=>'confirmed','settlement_type'=>'payment','cash_direction'=>'out','confirmed_at'=>time()]);
    $a=(new PurchaseClosureService())->view(1,Db::name('hsx_boyuan_task')->where('id',1)->find()); $b=(new PurchaseClosureService())->view(1,Db::name('hsx_boyuan_task')->where('id',2)->find());
    check($a['totals']['amount']==='100.00' && $a['totals']['paid_amount']==='100.00' && $b['totals']['paid_amount']==='100.00','one payment shared across two batches is allocated by each task source line, not whole purchase or payment total');
    check($a['source']['source_line_ids']===['purchase_line:1'] && $b['source']['source_line_ids']===['purchase_line:2'],'closure scope retains exact independent batch line identities');
    echo 'PASS '.$assertions." assertions; fake database only; 0 bootstrap, 0 PDO, 0 network.\n";
}
