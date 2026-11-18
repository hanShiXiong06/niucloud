<?php
declare(strict_types=1);

/**
 * Pure in-memory regression harness. Does not load application bootstrap, .env,
 * PDO, vendor services, customer rows, or any network transport.
 * Run: php addon/hsx_boyuan/tests/purchase-sync.php (from niucloud/).
 */
namespace {
    function success($data) { return $data; }
    // Production composer provides php81-polyfill; keep this standalone fake harness PHP 8.0-safe.
    if(!function_exists('array_is_list')) {
        function array_is_list(array $values): bool { $index=0; foreach($values as $key=>$unused) if($key!==$index++) return false; return true; }
    }
}
namespace core\exception { class CommonException extends \RuntimeException {} }
namespace core\base { class BaseAdminController { public $request; } }
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
        private array $conditions=[]; private string $sort=''; private int $offset=0; private ?int $size=null;
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
        public function page(int $page,int $size): self { $this->offset=($page-1)*$size; $this->size=$size; return $this; }
        private function matches(array $row): bool
        {
            foreach($this->conditions as [$field,$operator,$value]) {
                $actual=$row[$field]??null;
                $ok=match($operator) {'='=>$actual==$value,'<>'=>$actual!=$value,'>'=>$actual>$value,'>='=>$actual>=$value,'<'=>$actual<$value,'<='=>$actual<=$value,'in'=>in_array($actual,$value,true),'like'=>str_starts_with((string)$actual,rtrim((string)$value,'%')),default=>throw new \RuntimeException('Unsupported fake operator')};
                if(!$ok) return false;
            }
            return true;
        }
        private function rows(): array
        {
            $rows=array_values(array_filter(Db::$tables[$this->table]??[],fn($row)=>$this->matches($row)));
            if($this->sort!=='') { [$key,$direction]=array_pad(explode(' ',$this->sort),2,'asc'); usort($rows,fn($a,$b)=>(($a[$key]??0)<=>($b[$key]??0))*($direction==='desc'?-1:1)); }
            return $this->size===null?$rows:array_slice($rows,$this->offset,$this->size);
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
    final class PaymentGateway
    {
        public static int $creates=0; public static string $scenario='success';
        public function __construct(OpenApi $api) {}
        public function create(array $request): array
        {
            self::$creates++;
            $task=\think\facade\Db::name('hsx_boyuan_task')->where('kind','purchase_payment')->order('id desc')->find();
            if($task['side_effect']!=='unknown' || $task['status']!=='processing' || $task['attempts']!==1) throw new \RuntimeException('Unknown effect must commit before gateway call');
            if(self::$scenario==='timeout') throw new \addon\hsx_boyuan\app\support\Problem('PAYMENT_CREATE_UNKNOWN','Synthetic unknown effect','unknown');
            return ['created'=>true,'payment_registered'=>true,'verified'=>false,'external_no'=>'PAY_TEST_'.self::$creates,'external_id'=>'PAY_HEAD_'.self::$creates,'amount'=>self::$scenario==='mismatch'?'9999.00':$request['amount'],
                'verification_level'=>'payment_create_receipt_matched','financial_posting_verified'=>false,'allocation_applied_verified'=>false,'bank_transfer_verified'=>false,
                'response'=>['head'=>['id'=>'PAY_HEAD_1','bank_account'=>'MUST_NOT_LEAK'],'payment_details'=>['add_details'=>[['account_id'=>$request['account_id'],'amounts'=>$request['amount'],'bank_account'=>'MUST_NOT_LEAK']]]]];
        }
    }
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
    final class InventoryGateway
    {
        public static int $steps=0; public static string $scenario='success';
        public function __construct(OpenApi $api) {}
        public function verifyStep(array $request,int $offset,int $limit=3): array
        {
            self::$steps++;
            if(self::$scenario==='timeout') throw new \addon\hsx_boyuan\app\support\Problem('OPENAPI_EXCHANGE_UNKNOWN','Synthetic read timeout','unknown');
            if(self::$scenario==='missing') throw new \addon\hsx_boyuan\app\support\Problem('INVENTORY_DEVICE_NOT_FOUND','Synthetic missing record');
            $lines=[]; foreach(array_slice($request['lines'],$offset,$limit) as $line) $lines[]=$line+['qty'=>'1','imei_in_stock_status'=>1,'company_id'=>$request['company_id'],'company_code'=>$request['company_code']];
            $end=$offset+count($lines);
            return ['verified'=>false,'current_stock_verified'=>false,'step_verified'=>true,'verification_level'=>'batch_current_stock_step_matched','scope_hash'=>hash('sha256',\addon\hsx_boyuan\app\support\Contract::json($request)),
                'offset'=>$offset,'total_count'=>count($request['lines']),'next_offset'=>$end<count($request['lines'])?$end:null,'query_date'=>$request['query_date'],'checked_at'=>time(),'matched_count'=>count($lines),'matched_lines'=>$lines];
        }
        public function complete(array $request,array $steps): array
        {
            $lines=[]; foreach($steps as $step) $lines=array_merge($lines,$step['matched_lines']);
            return ['verified'=>true,'current_stock_verified'=>true,'verification_level'=>'batch_current_stock_records_matched','query_date'=>$request['query_date'],'checked_at'=>min(array_column($steps,'checked_at')),'in_stock_count'=>count($lines),'matched_lines'=>$lines,
                'evidence'=>['response_contract'=>'boyuan.inventory.v153.documented','scope'=>'purchase_batch','source_task_id'=>$request['source_task_id'],'source_snapshot_hash'=>$request['source_snapshot_hash'],
                    'company_id'=>$request['company_id'],'company_code'=>$request['company_code'],'device_count'=>count($lines),'aggregate_verified'=>false,'whole_warehouse_reconciled'=>false,'future_saleability_verified'=>false,'financial_posting_verified'=>false]];
        }
    }
}
namespace {
    use addon\hsx_boyuan\app\gateway\{OpenApi,PurchaseGateway,InventoryGateway,PaymentGateway};
    use addon\hsx_boyuan\app\service\{ConfigService,PurchaseSyncService,PurchaseClosureService,PurchaseInventoryService,PaymentSyncService,PermissionService,TaskService};
    use addon\hsx_boyuan\app\support\{Contract,Problem};
    use think\facade\Db;
    require __DIR__.'/../app/support/Problem.php';
    require __DIR__.'/../app/support/Contract.php';
    require __DIR__.'/../app/service/PurchaseClosureService.php';
    require __DIR__.'/../app/service/PurchaseInventoryService.php';
    require __DIR__.'/../app/service/PaymentSyncService.php';
    require __DIR__.'/../app/service/BatchService.php';
    require __DIR__.'/../app/adminapi/controller/Workbench.php';
    require __DIR__.'/../app/service/PurchaseSyncService.php';
    $assertions=0;
    function check(bool $condition,string $label): void { global $assertions; $assertions++; if(!$condition) throw new RuntimeException('FAIL: '.$label); }
    function rejects(callable $callback,string $reason): void { try { $callback(); } catch(Problem $error) { check($error->reason===$reason,'rejects '.$reason.' (got '.$error->reason.')'); return; } throw new RuntimeException('FAIL: expected '.$reason); }
    function seed(): array
    {
        Db::$tables=[]; Db::$failTable=null; PermissionService::$canViewPayables=true; TaskService::$logs=[]; PurchaseGateway::$creates=PurchaseGateway::$queries=0; PurchaseGateway::$scenario='success'; PurchaseGateway::$onCreate=PurchaseGateway::$onVerify=null; InventoryGateway::$steps=0; InventoryGateway::$scenario='success'; ConfigService::$onGet=null; ConfigService::$lastTransportProfile=[];
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
        foreach([['companies','C1',['gsdm'=>'C1_CODE']],['companies','C2',['gsdm'=>'C2_CODE']],['parties','S1',['is_supplier'=>1]],['parties','CUSTOMER',['is_cust'=>1]],['products','P1',['imei_count'=>1,'imei_len'=>15]],['products','P2',['imei_count'=>2,'imei_len'=>15]],['warehouses','W1',['company_id'=>'C1']],['warehouses','W2',['company_id'=>'C1']],['warehouses','W3',['company_id'=>'C2']],['staff','B1',['is_buyer'=>1]],['staff','SELLER',['is_sales'=>1]]] as [$resource,$id,$extra]) {
            Db::name('hsx_boyuan_reference')->insert(['site_id'=>1,'connection_key'=>$key,'resource'=>$resource,'external_id'=>$id,'code'=>$id,'name'=>$id,'enabled'=>1,'seen_run'=>'latest-'.$resource,'extra_json'=>Contract::json($extra)]);
        }
        return [$event,$snapshot];
    }
    function selection(array $snapshot): array { return ['company_id'=>'C1','supplier_id'=>'S1','creator_id'=>'B1','buyer_id'=>'B1','lines'=>array_map(fn($line)=>['source_line_id'=>$line['source_line_id'],'product_id'=>'P1','warehouse_id'=>'W1'],$snapshot['lines'])]; }
    function input(array $view,?array $selection=null): array { $input=array_intersect_key($view['task'],array_flip(['revision','mode','connection_key'])); if($selection!==null) $input+=['selection'=>$selection,'remember'=>false]; return $input; }
    function ready(): void { Db::name('hsx_boyuan_task')->update(['next_at'=>0]); }
    function row(): array { return Db::name('hsx_boyuan_task')->where('id',1)->find(); }
    function newTask(PurchaseSyncService $service,array $event): array { $service->capture(1,$event); return $service->view(1,1); }
    function finishPurchase(PurchaseSyncService $service,array $event,array $snapshot): array
    {
        foreach($snapshot['lines'] as $line) { $asset=(int)substr($line['device_ref'],6); Db::name('erp_payable')->insert(['id'=>$asset,'site_id'=>1,'party_id'=>1,'source_type'=>'purchase_asset','source_id'=>$asset,'asset_id'=>$asset,'amount'=>$line['amount'],'settled_amount'=>'0.00','status'=>'pending']); }
        $view=newTask($service,$event); $service->submit(1,1,7,input($view,selection($snapshot))); ready(); $service->process(1,1,new OpenApi());
        $view=$service->view(1,1); $service->queueVerify(1,1,7,input($view)+['external_no'=>'123456']); ready(); $service->process(1,1,new OpenApi()); return $service->view(1,1);
    }

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

    [$event,$snapshot]=seed(); $awaiting=newTask($service,$event); $inventory=new PurchaseInventoryService();
    rejects(fn()=>$inventory->queue(1,1,7,input($awaiting)),'INVENTORY_DEPENDENCY_REQUIRED');
    [$event,$snapshot]=seed();
    foreach([3,4,5] as $asset) {
        $product=['model'=>'Model '.$asset,'spec'=>'256GB','color'=>'Black']; $imei=str_pad((string)$asset,15,'0',STR_PAD_LEFT);
        Db::name('erp_asset')->insert(['id'=>$asset,'site_id'=>1,'imei'=>$imei,'sn'=>'','warehouse_id'=>1]+$product);
        Db::name('erp_purchase_item')->insert(['id'=>$asset,'site_id'=>1,'purchase_order_id'=>1,'asset_id'=>$asset,'imei'=>$imei,'sn'=>'','quantity'=>'1.000','purchase_cost'=>'100.00','warehouse_id'=>1]+$product);
        $snapshot['lines'][]=['source_line_id'=>'purchase_line:'.$asset,'device_ref'=>'asset:'.$asset,'product_ref'=>'model:'.hash('sha256',json_encode($product,JSON_UNESCAPED_UNICODE)),'product_name'=>implode(' ',$product),'imei'=>$imei,'sn'=>'','warehouse_ref'=>'warehouse:1','quantity'=>'1.000','amount'=>'100.00'];
    }
    $snapshot['batch_ref']=hash('sha256',implode('|',array_column($snapshot['lines'],'source_line_id'))); $event['payload']=$snapshot;
    $completed=finishPurchase($service,$event,$snapshot); $parentBefore=row(); $erpBefore=array_filter(Db::$tables,static fn($key)=>str_starts_with($key,'erp_'),ARRAY_FILTER_USE_KEY);
    $queued=$inventory->queue(1,1,7,input($completed)); $childId=$queued['inventory']['task_id']; $same=$inventory->queue(1,1,7,input($completed));
    check($childId===2 && $same['inventory']['task_id']===$childId && Db::name('hsx_boyuan_task')->count()===2,'duplicate inventory queue requests reuse one parent/date/source-scoped child task');
    check(Db::name('hsx_boyuan_task')->where('id',$childId)->value('query_only')===1 && row()===$parentBefore,'inventory child is read-only and leaves original purchase task entirely unchanged');
    ready(); $inventory->process(1,$childId,new OpenApi()); $progress=$service->view(1,1);
    check(InventoryGateway::$steps===1 && $progress['inventory']['status']==='pending' && $progress['inventory']['completed_count']===3 && $progress['inventory']['in_stock_count']===null,'one worker round verifies at most three devices and never reports partial progress as stock success');
    check($progress['closure']['stages'][2]['status']==='pending','closure reports pending bounded inventory progress');
    ready(); $inventory->process(1,$childId,new OpenApi()); $stock=$service->view(1,1);
    check(InventoryGateway::$steps===2 && $stock['inventory']['status']==='succeeded' && $stock['inventory']['in_stock_count']===5 && $stock['inventory']['total_count']===5 && is_int($stock['inventory']['checked_at']),'all read-only steps are required before publishing complete batch stock evidence');
    check($stock['closure']['stages'][2]['status']==='verified' && $stock['closure']['stages'][2]['label']==='本批次当前在库' && !$stock['closure']['complete'] && $stock['closure']['stages'][5]['status']==='blocked','stock evidence only verifies the batch stage and never financial closure or whole-warehouse balance');
    check(array_filter(Db::$tables,static fn($key)=>str_starts_with($key,'erp_'),ARRAY_FILTER_USE_KEY)===$erpBefore && row()===$parentBefore && PurchaseGateway::$creates===1,'inventory reads do not alter local stock, payments, original task or create another purchase');
    $crossStock=$inventory->summary(2,row()); check($crossStock['status']==='unavailable' && $crossStock['in_stock_count']===null,'inventory summary rejects cross-tenant source');
    $child=Db::name('hsx_boyuan_task')->where('id',$childId)->find(); $old=Contract::decode($child['result_json']); $old['checked_at']=time()-301;
    Db::name('hsx_boyuan_task')->where('id',$childId)->update(['result_json'=>Contract::json($old)]); $expired=$service->view(1,1);
    check($expired['inventory']['status']==='stale' && $expired['inventory']['in_stock_count']===null && $expired['can_check_inventory']===true,'expired stock evidence is not reused as a current count and needs explicit recheck');
    $requeued=$inventory->queue(1,1,7,input($expired)); check($requeued['inventory']['task_id']===$childId && Db::name('hsx_boyuan_task')->count()===2,'explicit stale refresh reuses the read-only child without changing the purchase request');
    InventoryGateway::$scenario='missing'; ready(); $inventory->process(1,$childId,new OpenApi()); $failed=$service->view(1,1);
    check($failed['inventory']['status']==='failed' && $failed['inventory']['in_stock_count']===null && $failed['closure']['stages'][2]['status']!=='verified','missing inventory records are a failure, not zero inventory or false success');
    $inventory->queue(1,1,7,input($failed)); InventoryGateway::$scenario='timeout'; $beforeSteps=InventoryGateway::$steps;
    for($try=0;$try<3;$try++) { ready(); $inventory->process(1,$childId,new OpenApi()); }
    check(Db::name('hsx_boyuan_task')->where('id',$childId)->value('status')==='attention' && InventoryGateway::$steps-$beforeSteps===3,'unknown read-only query outcomes have at most three automatic attempts');
    ready(); check(!$inventory->process(1,$childId,new OpenApi()) && InventoryGateway::$steps-$beforeSteps===3,'exhausted read-only retry budget stops rather than looping');
    $latest=$service->view(1,1); $inventory->queue(1,1,7,input($latest)); InventoryGateway::$scenario='success'; Db::name('erp_purchase_item')->where('id',1)->update(['purchase_cost'=>'99.00']);
    $beforeSteps=InventoryGateway::$steps; ready(); $inventory->process(1,$childId,new OpenApi()); $changedInventory=$service->view(1,1);
    check($changedInventory['inventory']['status']==='unavailable' && $changedInventory['inventory']['in_stock_count']===null && InventoryGateway::$steps===$beforeSteps,'changed original purchase is rejected before inventory exchange and invalidates old stock evidence');
    // Real source amounts are immutable; this fake gateway never performs an exchange.
    function paymentSeed(PurchaseSyncService $service): array
    {
        [$event,$snapshot]=seed(); $parent=finishPurchase($service,$event,$snapshot); PaymentGateway::$creates=0; PaymentGateway::$scenario='success';
        ConfigService::$config['uat_payment_enabled']=true;
        ConfigService::$config['profiles']['test']+=['endpoint'=>PaymentSyncService::UAT_ENDPOINT,'gateway_path'=>'/gateway.do','contract_confirmed'=>true];
        Db::name('hsx_boyuan_reference')->where('resource','staff')->where('external_id','B1')->update(['extra_json'=>Contract::json(['gsdm'=>'C1_CODE','is_buyer'=>1])]);
        Db::name('hsx_boyuan_reference_sync')->insert(['site_id'=>1,'connection_key'=>str_repeat('a',32),'resource'=>'capital_accounts','status'=>'completed','run_key'=>'latest-capital_accounts']);
        foreach([['ACC1',['gsdm'=>'C1_CODE']],['ACC2',['company_id'=>'C2']],['CONFLICT',['company_id'=>'C1','gsdm'=>'C2_CODE']]] as [$id,$extra]) Db::name('hsx_boyuan_reference')->insert(['site_id'=>1,'connection_key'=>str_repeat('a',32),'resource'=>'capital_accounts','external_id'=>$id,'name'=>$id,'code'=>$id,'enabled'=>1,'seen_run'=>'latest-capital_accounts','extra_json'=>Contract::json($extra)]);
        Db::name('erp_payable')->where('id',1)->update(['settled_amount'=>'40.00','status'=>'partial']);
        Db::name('erp_settlement')->insert(['id'=>1,'site_id'=>1,'settlement_no'=>'SET_TEST_1','party_id'=>1,'status'=>'confirmed','settlement_type'=>'payment','cash_direction'=>'out','confirmed_at'=>time(),'operator_uid'=>1,'capital_account_id'=>1,'amount'=>'40.00']);
        Db::name('erp_settlement_link')->insert(['site_id'=>1,'settlement_id'=>1,'target_type'=>'payable','target_id'=>1,'applied_amount'=>'40.00']); return $parent;
    }
    function paymentInput(array $view,array $extra=[]): array { return array_replace(array_intersect_key($view['task'],array_flip(['revision','mode','connection_key'])),$extra); }
    $paymentService=new PaymentSyncService(); $completed=paymentSeed($service); $parentBefore=row();
    $candidates=$paymentService->candidates(1,1); check($candidates['total']===1 && $candidates['items'][0]['amount']==='40.00' && $candidates['items'][0]['eligible'],'only a confirmed real payment/out candidate exposes its read-only exact amount');
    $payment=$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])); $pid=$payment['task']['id'];
    check($payment['task']['status']==='awaiting_confirmation' && PaymentGateway::$creates===0 && $payment['source']['company_code']==='C1_CODE' && $payment['source']['parent_purchase_task_id']===1,'capture freezes existing local payment facts without gateway and publishes scoped parent identity');
    check(!$payment['can_submit'] && !$payment['can_submit_experiment'],'initial payment draft without an account never claims submit capability');
    check(!$payment['can_verify'] && !$payment['ap_evidence']['verified'] && $payment['ap_evidence']['status']==='rule_confirmed','official noncash AP formation rule is distinguished from unverified actual balance and no fake payment query');
    check($paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1]))['task']['id']===$pid,'repeat capture reuses the same confirmed settlement task');
    $sameSourceParent=row(); $sameSourceParent['id']=99; Db::name('hsx_boyuan_task')->insert($sameSourceParent);
    rejects(fn()=>$paymentService->capture(1,99,7,paymentInput($completed,['settlement_id'=>1])),'PAYMENT_FACT_CONFLICT');
    rejects(fn()=>$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1,'amount'=>'0.01'])),'PAYMENT_READ_ONLY_FACT');
    $choose=['creator_id'=>'B1','account_id'=>'ACC1'];
    rejects(fn()=>$paymentService->saveDraft(1,$pid,7,paymentInput($payment,['selection'=>['creator_id'=>'B1','account_id'=>'ACC2']])),'PAYMENT_ACCOUNT_SCOPE');
    rejects(fn()=>$paymentService->saveDraft(1,$pid,7,paymentInput($payment,['selection'=>['creator_id'=>'B1','account_id'=>'CONFLICT']])),'PAYMENT_ACCOUNT_SCOPE');
    rejects(fn()=>$paymentService->saveDraft(1,$pid,7,paymentInput($payment,['selection'=>['creator_id'=>'SELLER','account_id'=>'ACC1']])),'PAYMENT_CREATOR_SCOPE');
    $payment=$paymentService->saveDraft(1,$pid,7,paymentInput($payment,['selection'=>$choose]));
    check(!$payment['can_submit'] && $payment['can_submit_experiment'] && !$payment['can_verify'],'standard path blocks without authoritative AP balance proof while explicit UAT experiment remains separate');
    rejects(fn()=>$paymentService->submit(1,$pid,7,paymentInput($payment,['selection'=>$choose])),'PAYMENT_CONFIRM_REQUIRED');
    rejects(fn()=>$paymentService->submit(1,$pid,7,paymentInput($payment,['selection'=>$choose,'confirm_registration'=>true])),'PAYMENT_AP_EVIDENCE_REQUIRED');
    $erpBefore=array_filter(Db::$tables,static fn($key)=>str_starts_with($key,'erp_'),ARRAY_FILTER_USE_KEY);
    $payment=$paymentService->submit(1,$pid,7,paymentInput($payment,['selection'=>$choose,'confirm_registration'=>true,'confirm_unverified_ap'=>true]));
    $frozen=Db::name('hsx_boyuan_task')->where('id',$pid)->value('request_json');
    check($payment['task']['status']==='pending' && Contract::decode($frozen)['amount']==='40.00' && PaymentGateway::$creates===0,'submit freezes server amount and never invokes payment network synchronously');
    ConfigService::$config['paused']=true; ready(); check(!$paymentService->process(1,$pid,new OpenApi()) && PaymentGateway::$creates===0,'global pause blocks a frozen payment before gateway'); ConfigService::$config['paused']=false;
    ready(); check($paymentService->process(1,$pid,new OpenApi()),'single-attempt UAT payment worker executes'); $registered=$paymentService->view(1,$pid);
    check($registered['task']['status']==='registered_review' && $registered['result']['registered_amount']==='40.00' && !$registered['result']['allocation_applied_verified'] && !$registered['result']['bank_transfer_verified'],'receipt confirms registration only, never AP allocation or actual bank payment success');
    check($registered['operator_complete'] && !$registered['editable'] && !$registered['can_submit'] && $registered['issues']===[],'matched receipt closes operator work and presents no further action');
    check(!str_contains(Contract::json($registered),'MUST_NOT_LEAK'),'service receipt whitelist excludes bank data even if injected fake adapter adds it');
    check(Db::name('hsx_boyuan_binding')->where('kind','payment_registration')->count()===1 && Db::name('hsx_boyuan_binding')->where('kind','financial')->count()===0,'registration binding never forges payable or allocation proof');
    check(!$paymentService->process(1,$pid,new OpenApi()) && PaymentGateway::$creates===1,'registered task cannot repeat payment create');
    rejects(fn()=>$paymentService->submit(1,$pid,7,paymentInput($registered,['selection'=>$choose,'confirm_registration'=>true,'confirm_unverified_ap'=>true])),'PAYMENT_FROZEN');
    check(row()===$parentBefore && array_filter(Db::$tables,static fn($key)=>str_starts_with($key,'erp_'),ARRAY_FILTER_USE_KEY)===$erpBefore,'payment registration changes no original purchase task, ERP payable, settlement, inventory or capital table');
    $parentView=$service->view(1,1); check($parentView['payment']['status']==='registered_review' && $parentView['closure']['stages'][5]['status']==='partial' && !$parentView['closure']['complete'],'parent aggregates registration evidence but never labels full financial closure');
    check(!$parentView['closure']['operator_complete'] && $parentView['closure']['stages'][5]['amount']==='40.00' && $parentView['closure']['stages'][5]['count']===1,'one completed registration cannot close an incompletely paid batch');
    PermissionService::$canViewPayables=false; $hidden=$service->view(1,1); check($hidden['payment']['status']==='permission_denied' && $hidden['payment']['registered_amount']===null && !$hidden['payment']['can_capture'],'read-only task permission cannot disclose payment facts or give ERP finance capabilities');
    $controller=new \addon\hsx_boyuan\app\adminapi\controller\Workbench(); $controller->request=new class { public function siteId(): int { return 1; } public function uid(): int { return 7; } };
    try { $controller->detail($pid); throw new RuntimeException('FAIL: generic payment detail must enforce finance permission'); }
    catch(\core\exception\CommonException $error) { check($error->getMessage()==='No payable permission','generic task detail cannot bypass dedicated payment finance permission'); }
    $states=(new \addon\hsx_boyuan\app\service\BatchService())->states(1,['ids'=>[1,$pid],'mode'=>'test','connection_key'=>str_repeat('a',32)]);
    check(array_column($states['items'],'id')===[1],'generic batch status hides payment business facts from users without ERP payable permission');
    PermissionService::$canViewPayables=true;
    // Same two-device batch: first payment (40) is registered, remainder (60+100) paid locally only.
    Db::name('erp_payable')->update(['settled_amount'=>'100.00','status'=>'settled']);
    Db::name('erp_settlement')->insert(['id'=>2,'site_id'=>1,'settlement_no'=>'SET_TEST_2','party_id'=>1,'status'=>'confirmed','settlement_type'=>'payment','cash_direction'=>'out','confirmed_at'=>time(),'operator_uid'=>1,'capital_account_id'=>1,'amount'=>'160.00']);
    foreach([1=>'60.00',2=>'100.00'] as $target=>$amount) Db::name('erp_settlement_link')->insert(['site_id'=>1,'settlement_id'=>2,'target_type'=>'payable','target_id'=>$target,'applied_amount'=>$amount]);
    $parentView=$service->view(1,1);
    check($parentView['closure']['totals']['unpaid_amount']==='0.00' && !$parentView['closure']['operator_complete'] && $parentView['closure']['stages'][5]['status']==='partial','local payment in full does not cover the unregistered second settlement');
    $second=$paymentService->capture(1,1,7,paymentInput($parentView,['settlement_id'=>2])); $secondId=$second['task']['id'];
    $paymentService->submit(1,$secondId,7,paymentInput($second,['selection'=>$choose,'confirm_registration'=>true,'confirm_unverified_ap'=>true])); ready(); $paymentService->process(1,$secondId,new OpenApi());
    $final=$service->view(1,1)['closure']; $stage=$final['stages'][5];
    check($final['operator_complete'] && $stage['status']==='verified' && $stage['amount']==='200.00' && $stage['count']===2,'two matched independent receipts cover both exact confirmed local settlements and finish operator work');
    check(!$final['complete'] && !$paymentService->view(1,$secondId)['result']['allocation_applied_verified'] && PaymentGateway::$creates===2,'operator completion asserts neither remote allocation nor a third request');
    Db::name('erp_settlement')->where('id',2)->update(['capital_account_id'=>99]);
    $changedReceipt=$service->view(1,1)['closure'];
    check(!$changedReceipt['operator_complete'] && $changedReceipt['stages'][5]['amount']==='40.00','changed payment identity cannot consume a historical receipt from before the change');
    $completed=paymentSeed($service); $payment=$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])); $pid=$payment['task']['id'];
    $payment=$paymentService->submit(1,$pid,7,paymentInput($payment,['selection'=>$choose,'confirm_registration'=>true,'confirm_unverified_ap'=>true])); PaymentGateway::$scenario='timeout'; ready(); $paymentService->process(1,$pid,new OpenApi());
    $unknown=$paymentService->view(1,$pid); check($unknown['task']['status']==='attention' && $unknown['task']['side_effect']==='unknown' && !$unknown['editable'] && !$unknown['can_verify'] && !$unknown['operator_complete'],'unknown payment outcome is immutable manual attention with no invented verify endpoint');
    ready(); check(!$paymentService->process(1,$pid,new OpenApi()) && PaymentGateway::$creates===1,'unknown write never retries create');
    check($paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1]))['task']['id']===$pid,'capture cannot bypass unknown outcome by creating another task');
    $completed=paymentSeed($service); $payment=$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])); $pid=$payment['task']['id'];
    $paymentService->submit(1,$pid,7,paymentInput($payment,['selection'=>$choose,'confirm_registration'=>true,'confirm_unverified_ap'=>true])); PaymentGateway::$scenario='mismatch'; ready(); $paymentService->process(1,$pid,new OpenApi());
    $mismatch=$paymentService->view(1,$pid);
    check($mismatch['task']['status']==='attention' && !$mismatch['operator_complete'] && !$mismatch['can_submit'] && !$paymentService->process(1,$pid,new OpenApi()) && PaymentGateway::$creates===1,'a wrong-amount creation receipt is never done or resubmitted');
    foreach(['duplicate-id','duplicate-number'] as $collision) {
        $completed=paymentSeed($service); $payment=$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])); $pid=$payment['task']['id'];
        Db::name('hsx_boyuan_binding')->insert(['site_id'=>1,'connection_key'=>$payment['task']['connection_key'],'kind'=>'payment_registration','local_ref'=>'settlement:9','task_id'=>999,
            'external_id'=>$collision==='duplicate-id'?'PAY_HEAD_1':'ANOTHER_ID','external_no'=>$collision==='duplicate-number'?'PAY_TEST_1':'ANOTHER_NUMBER','detail_json'=>Contract::json(['evidence'=>['company_id'=>'C1']])]);
        $paymentService->submit(1,$pid,7,paymentInput($payment,['selection'=>$choose,'confirm_registration'=>true,'confirm_unverified_ap'=>true])); ready(); $paymentService->process(1,$pid,new OpenApi());
        $conflict=$paymentService->view(1,$pid);
        check($conflict['task']['status']==='attention' && !$conflict['operator_complete'] && !$conflict['can_submit'] && Db::name('hsx_boyuan_binding')->where('kind','payment_registration')->count()===1,
            'remote voucher already linked elsewhere ('.$collision.') cannot mark another settlement registered or replace the prior binding');
    }
    $completed=paymentSeed($service); Db::name('erp_settlement_link')->insert(['site_id'=>1,'settlement_id'=>1,'target_type'=>'payable','target_id'=>1,'applied_amount'=>'40.00']);
    rejects(fn()=>$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])),'PAYMENT_SOURCE_CHANGED');
    Db::name('erp_payable')->where('id',1)->update(['settled_amount'=>'80.00']); Db::name('erp_settlement')->where('id',1)->update(['amount'=>'80.00']);
    rejects(fn()=>$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])),'PAYMENT_LINK_DUPLICATE');
    $completed=paymentSeed($service); Db::name('erp_payable')->where('id',1)->update(['source_id'=>999]);
    rejects(fn()=>$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])),'PAYMENT_SINGLE_BATCH_REQUIRED');
    $completed=paymentSeed($service); Db::name('erp_payable')->where('id',1)->update(['status'=>'void']);
    rejects(fn()=>$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])),'PAYMENT_SINGLE_BATCH_REQUIRED');
    $completed=paymentSeed($service); $payment=$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])); $pid=$payment['task']['id'];
    $payment=$paymentService->submit(1,$pid,7,paymentInput($payment,['selection'=>$choose,'confirm_registration'=>true,'confirm_unverified_ap'=>true]));
    Db::name('erp_settlement')->where('id',1)->update(['status'=>'void']); ready(); $paymentService->process(1,$pid,new OpenApi());
    check(PaymentGateway::$creates===0 && $paymentService->view(1,$pid)['task']['status']==='attention','changed or voided confirmed payment is rejected before any gateway call');
    $completed=paymentSeed($service); ConfigService::$config['mode']='production'; ConfigService::$config['profiles']['production']=ConfigService::$config['profiles']['test'];
    rejects(fn()=>$paymentService->capture(1,1,7,paymentInput($completed,['settlement_id'=>1])),'PAYMENT_UAT_ONLY');
    check(!$service->view(1,1)['payment']['can_capture'] && PaymentGateway::$creates===0,'production payment write capability is always false and no gateway invoked');
    echo 'PASS '.$assertions." assertions; fake database only; 0 bootstrap, 0 PDO, 0 network.\n";
}
