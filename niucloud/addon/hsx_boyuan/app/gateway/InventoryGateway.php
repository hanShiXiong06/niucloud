<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;

use addon\hsx_boyuan\app\support\{Contract,Problem};

/** Per-device, current-date stock evidence. No writes and no financial claims. */
final class InventoryGateway
{
    public const SCHEMA='boyuan.purchase.inventory.v1';
    public const MAX_EVIDENCE_AGE=300;
    public const STEP_LIMIT=3;
    private const PAGE_SIZE=100;
    public function __construct(private OpenApi $api) {}

    public function verify(array $request): array
    {
        $steps=[]; $offset=0;
        do { $step=$this->verifyStep($request,$offset); $steps[]=$step; $offset=$step['next_offset']; } while($offset!==null);
        return $this->complete($request,$steps);
    }

    /** A bounded read step never claims that the entire batch has been verified. */
    public function verifyStep(array $request,int $offset,int $limit=self::STEP_LIMIT): array
    {
        $this->validate($request); $total=count($request['lines']);
        if($offset<0 || $offset>=$total || $limit<1 || $limit>self::STEP_LIMIT) throw new Problem('INVENTORY_STEP_INVALID','库存核验游标无效，每轮最多核验3台');
        $checkedAt=time(); $matched=[];
        foreach(array_slice($request['lines'],$offset,$limit) as $line) {
            $this->currentDate($request['query_date']);
            $rows=$this->pages(['query_date'=>$request['query_date'],'gsdm'=>$request['company_code'],'imei'=>$line['imei'],
                'product_ids'=>$line['product_id'],'warehouse_ids'=>$line['warehouse_id']]);
            if(!$rows) throw new Problem('INVENTORY_DEVICE_NOT_FOUND','未查到本批设备的完整当前库存记录；未将缺失记录当作库存零或核验成功');
            if(count($rows)!==1) throw new Problem('INVENTORY_DEVICE_AMBIGUOUS','完整串号返回多条库存记录，不能唯一核验本批设备');
            $row=$rows[0];
            foreach(['gsdm'=>$request['company_code'],'company_id'=>$request['company_id'],'imei1'=>$line['imei'],'imei'=>$line['imei'],
                'warehouse_id'=>$line['warehouse_id'],'product_id'=>$line['product_id'],'supplier_id'=>$line['supplier_id']] as $field=>$expected) {
                if(($row[$field]??null)!==$expected) throw new Problem('INVENTORY_IDENTITY_MISMATCH','库存记录的公司、仓库、商品、供应商或原始串号与冻结批次不一致（field='.$field.'）');
            }
            foreach(['imei2','imei3'] as $field) if(isset($row[$field]) && $row[$field]!=='') throw new Problem('INVENTORY_SERIAL_MODE_UNSUPPORTED','当前只支持单串号设备，不能忽略库存记录中的其他串号');
            if(!in_array($row['qty']??null,[1,'1',1.0],true)) throw new Problem('INVENTORY_QUANTITY_MISMATCH','本批设备当前库存数量不是明确的1');
            if(!in_array($row['imei_in_stock_status']??null,[1,'1'],true)) throw new Problem('INVENTORY_NOT_IN_STOCK','本批设备未明确在库，可能在途、借出或状态未知；不能确认当前在库');
            $matched[]=self::matchedLine($request,$line);
        }
        $this->currentDate($request['query_date']);
        $next=$offset+count($matched);
        return ['verified'=>false,'current_stock_verified'=>false,'step_verified'=>true,'verification_level'=>'batch_current_stock_step_matched',
            'scope_hash'=>self::scopeHash($request),'offset'=>$offset,'total_count'=>$total,'next_offset'=>$next<$total?$next:null,
            'query_date'=>$request['query_date'],'checked_at'=>$checkedAt,'matched_count'=>count($matched),'matched_lines'=>$matched];
    }

    /** Pure local validation of trusted worker-produced step receipts; never a network call. */
    public function complete(array $request,array $steps): array
    {
        $this->validate($request); $total=count($request['lines']); $offset=0; $matched=[]; $earliest=null; $latest=null;
        if(!$steps || !self::isList($steps) || count($steps)>$total) throw new Problem('INVENTORY_STEPS_INCOMPLETE','库存核验尚未取得完整分步证据');
        $scope=self::scopeHash($request); $now=time();
        foreach($steps as $step) {
            if(!is_array($step) || ($step['step_verified']??null)!==true || ($step['verified']??null)!==false || ($step['current_stock_verified']??null)!==false
                || ($step['verification_level']??'')!=='batch_current_stock_step_matched' || ($step['scope_hash']??'')!==$scope
                || ($step['offset']??null)!==$offset || ($step['total_count']??null)!==$total || ($step['query_date']??null)!==$request['query_date']) throw new Problem('INVENTORY_STEP_SCOPE_MISMATCH','库存分步证据的冻结批次、日期或连续游标不一致，不能确认整批');
            $time=$step['checked_at']??null;
            if(!is_int($time) || $time<=0 || $time>$now+5 || $now-$time>self::MAX_EVIDENCE_AGE
                || (new \DateTimeImmutable('@'.$time))->setTimezone(new \DateTimeZone('Asia/Shanghai'))->format('Y-m-d')!==$request['query_date']) throw new Problem('INVENTORY_EVIDENCE_STALE','库存核验分步证据已过期、跨日或时间无效，请重新排队，不沿用旧在库结论');
            $rows=$step['matched_lines']??null;
            if(!is_array($rows) || !self::isList($rows) || !$rows || count($rows)>self::STEP_LIMIT || ($step['matched_count']??null)!==count($rows)) throw new Problem('INVENTORY_STEPS_INCOMPLETE','库存分步证据缺少完整的逐台结果');
            foreach($rows as $row) {
                if(!isset($request['lines'][$offset]) || !is_array($row) || $row!==self::matchedLine($request,$request['lines'][$offset])) throw new Problem('INVENTORY_STEP_IDENTITY_MISMATCH','库存分步证据与冻结设备、串号、数量或在库状态不一致');
                $matched[]=$row; $offset++;
            }
            $next=$offset<$total?$offset:null;
            if(!array_key_exists('next_offset',$step) || $step['next_offset']!==$next) throw new Problem('INVENTORY_STEPS_INCOMPLETE','库存分步下一游标不一致，不能把部分设备当成整批成功');
            $earliest=$earliest===null?$time:min($earliest,$time); $latest=$latest===null?$time:max($latest,$time);
        }
        if($offset!==$total) throw new Problem('INVENTORY_STEPS_INCOMPLETE','库存核验仍缺少部分设备，不能确认整批');
        return ['verified'=>true,'current_stock_verified'=>true,'verification_level'=>'batch_current_stock_records_matched',
            'query_date'=>$request['query_date'],'checked_at'=>$earliest,'in_stock_count'=>count($matched),'matched_lines'=>$matched,
            'evidence'=>['response_contract'=>'boyuan.inventory.v153.documented','scope'=>'purchase_batch',
                'source_task_id'=>$request['source_task_id'],'source_snapshot_hash'=>$request['source_snapshot_hash'],
                'company_id'=>$request['company_id'],'company_code'=>$request['company_code'],'device_count'=>count($matched),
                'scope_hash'=>$scope,'checked_from'=>$earliest,'checked_until'=>$latest,'evidence_ttl'=>self::MAX_EVIDENCE_AGE,
                'aggregate_verified'=>false,'whole_warehouse_reconciled'=>false,'future_saleability_verified'=>false,'financial_posting_verified'=>false,
                'limitations'=>['仅证明本批次逐台设备在指定查询日期返回在库状态1；查询完成后可能变化',
                    '未做全仓汇总对账，不保证未来可售、未占用或后续出库能力',
                    '未核验成本重算、应付、付款、银行资金或指定核销；不修改任何本地或博远账务']],
        ];
    }

    private function validate(array $request): void
    {
        $allowed=['schema','source_task_id','source_snapshot_hash','connection_key','company_id','company_code','query_date','lines'];
        if(array_diff(array_keys($request),$allowed) || ($request['schema']??null)!==self::SCHEMA || !is_int($request['source_task_id']??null) || $request['source_task_id']<=0
            || !is_string($request['source_snapshot_hash']??null) || !preg_match('/^[a-f0-9]{64}$/D',$request['source_snapshot_hash'])) throw new Problem('INVENTORY_REQUEST_INVALID','库存核验必须来自有效的冻结采购批次');
        foreach(['connection_key'=>128,'company_id'=>36,'company_code'=>36] as $field=>$max) self::id($request[$field]??null,$max,'request.'.$field);
        $this->currentDate($request['query_date']??null);
        $lines=$request['lines']??null;
        if(!is_array($lines) || !self::isList($lines) || !$lines || count($lines)>100) throw new Problem('INVENTORY_LINES_INVALID','库存核验必须包含本批次1至100台完整设备');
        $sources=[]; $devices=[]; $serials=[];
        foreach($lines as $index=>$line) {
            if(!is_array($line) || array_diff(array_keys($line),['source_line_id','device_ref','imei','product_id','warehouse_id','supplier_id','quantity'])) throw new Problem('INVENTORY_LINES_INVALID','库存核验设备字段无效');
            foreach(['source_line_id'=>128,'device_ref'=>128,'imei'=>64,'product_id'=>36,'warehouse_id'=>36,'supplier_id'=>36] as $field=>$max) self::id($line[$field]??null,$max,'request.lines.'.$index.'.'.$field);
            if(($line['quantity']??null)!=='1' || isset($sources[$line['source_line_id']]) || isset($devices[$line['device_ref']]) || isset($serials[$line['imei']])) throw new Problem('INVENTORY_LINES_INVALID','库存核验必须一机一行数量为1，来源、设备和串号不得重复');
            $sources[$line['source_line_id']]=$devices[$line['device_ref']]=$serials[$line['imei']]=true;
        }
    }
    private function currentDate($date): void
    {
        $today=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
        if(!is_string($date) || $date!==$today) throw new Problem('INVENTORY_DATE_STALE','当前在库核验只允许上海时区今天的query_date；日期已变化请重新排队');
    }
    private function pages(array $params): array
    {
        $this->currentDate($params['query_date']);
        $data=self::keys($this->api->inventoryQuery('imei',$params+['page_no'=>1,'page_size'=>self::PAGE_SIZE]));
        $rows=$data['result_list']??null; $count=self::integer($data['total_results']??null);
        if(!is_array($rows) || !self::isList($rows) || $count===null) throw new Problem('INVENTORY_PAGE_INVALID','库存核验分页或总数异常，未确认本批库存');
        // An exact full serial must have one record. A larger declared total already
        // proves ambiguity; fail immediately instead of extending the worker lease.
        if($count>1) throw new Problem('INVENTORY_DEVICE_AMBIGUOUS','完整串号声明存在多条库存记录，已拒绝；不读取部分分页作为成功证据');
        if(count($rows)!==$count) throw new Problem('INVENTORY_PAGE_INCOMPLETE','库存核验分页不完整或总数矛盾，不能确认在库');
        foreach($rows as $index=>$row) {
            if(!is_array($row)) throw new Problem('INVENTORY_PAGE_INVALID','库存核验返回无效记录');
            self::id($row['imei1']??null,64,'imei.page1.result_list.'.$index.'.imei1');
        }
        return $rows;
    }
    private static function matchedLine(array $request,array $line): array
    {
        $matched=[];
        foreach(['source_line_id','device_ref','imei','product_id','warehouse_id','supplier_id','quantity'] as $key) $matched[$key]=$line[$key];
        return $matched+['qty'=>'1','imei_in_stock_status'=>1,'company_id'=>$request['company_id'],'company_code'=>$request['company_code']];
    }
    private static function scopeHash(array $request): string
    {
        $canonical=[];
        foreach(['schema','source_task_id','source_snapshot_hash','connection_key','company_id','company_code','query_date'] as $key) $canonical[$key]=$request[$key];
        $canonical['lines']=array_map(static function(array $line): array {
            $clean=[]; foreach(['source_line_id','device_ref','imei','product_id','warehouse_id','supplier_id','quantity'] as $key) $clean[$key]=$line[$key]; return $clean;
        },$request['lines']);
        return hash('sha256',Contract::json($canonical));
    }
    private static function keys(array $value): array
    {
        $clean=[];
        foreach($value as $key=>$item) {
            $key=is_string($key)?trim($key):$key;
            if(array_key_exists($key,$clean)) throw new Problem('INVENTORY_RESPONSE_AMBIGUOUS','库存响应出现重名字段，不能猜测');
            $clean[$key]=is_array($item)?self::keys($item):$item;
        }
        return $clean;
    }
    private static function id($value,int $max,string $path): string
    {
        if(!is_string($value) || $value==='' || strlen($value)>$max || preg_match('/[\x00-\x20,]/',$value)) {
            throw new Problem('INVENTORY_IDENTITY_INVALID','库存编号格式异常（path='.$path.'; type='.get_debug_type($value).'; empty='.($value===null || $value===''?'true':'false').'; length='.(is_string($value)?strlen($value):'n/a').'）');
        }
        return $value;
    }
    private static function integer($value): ?int
    {
        if(is_int($value)) return $value>=0?$value:null;
        return is_string($value) && preg_match('/^(0|[1-9]\d{0,8})$/D',$value)?(int)$value:null;
    }
    private static function isList(array $value): bool { return !$value || array_keys($value)===range(0,count($value)-1); }
}
