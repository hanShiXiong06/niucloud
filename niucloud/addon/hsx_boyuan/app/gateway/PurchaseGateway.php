<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;

use addon\hsx_boyuan\app\support\{Contract,Problem,ProductionPurchasePilot as Pilot};

/** v1.5.4 purchase creation and read-only verification; no financial operations. */
final class PurchaseGateway
{
    private const PAGE_SIZE=100;
    private const MAX_QUERY_ROWS=100000;
    public function __construct(private OpenApi $api) {}

    /** Read-only duplicate check for a single explicitly adopted historical fact. Never uses a partial or ambiguous list as clearance. */
    public function checkExistingSerial(array $request): void
    {
        [$head,$expected]=$this->request($request);
        if(count($expected)!==1 || empty($request['original_event_id'])) throw new Problem('ORIGINAL_PURCHASE_AMBIGUOUS','仅支持核对一台原采购设备');
        $imei=(string)array_key_first($expected); $date=$head['business_date'];
        try {
            if((($imei===Pilot::THIRD_IMEI && isset($request['pilot_bridge_id']) && $this->api->productionPilotReadback()) || ($request['pilot_operation_id']??null)===Pilot::THIRD_OP) &&
                !Pilot::pricerIntent($request,$imei)) self::fail('PILOT_SCOPE','回收单、串号与本次原采购授权不符，未进行查询或发单');
            $day=$this->pages('imei',['business_date_start'=>$date,'business_date_end'=>$date,'bill_type'=>'PUR'],'detail_id',1000);
            $scoped=Pilot::pricerIntent($request,$imei) && $this->api->productionPilotReadback()
                && ($request['stage']??null)==='frozen' && is_int($request['pilot_bridge_id']??null) && $request['pilot_bridge_id']>0
                && is_int($request['pilot_authorized_uid']??null) && $request['pilot_authorized_uid']>0 && ($request['pilot_pricer_ref']??null)==='staff:1'
                && ($request['selection']['buyer_id']??null)===Pilot::BUYER && ($head['remark']??null)===Pilot::REMARK
                && ($head['cash_term']??null)===0 && $expected[$imei]['amount']==='1.00';
            if($scoped) Pilot::requireNewSelection($head,$request['biz']['product_details']['add_details'][0],$request['selection']);
            $join=false;
            foreach($day as $row) {
                if($scoped) {
                    self::pilotSerialValues($row,$imei);
                    if(array_key_exists('bill_id',$row)) self::identity($row['bill_id'],32,'daily_imei.bill_id');
                    if(!array_key_exists('business_date',$row) || !array_key_exists('bill_id',$row)) $join=true;
                    if(!array_key_exists('business_date',$row)) continue; // only accepted after independent document join below
                }
                if(($row['bill_type']??null)!=='PUR' || ($row['business_date']??null)!==$date || empty($row['imei1']) && empty($row['imei']))
                    throw new Problem('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','博远返回的采购明细无法识别日期、单据或串号');
                foreach(['imei','imei1','imei2','imei3','imei4'] as $field) if(isset($row[$field]) && (string)$row[$field]===$imei)
                    throw new Problem('ORIGINAL_PURCHASE_REMOTE_EXISTS','博远采购记录已有此串号，请核对原采购单');
            }
            if($join || ($scoped && !$day)) $this->pilotCheckSerialManifest($day,$date,$imei);
            $today=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
            $stock=self::keys($this->api->inventoryQuery('imei',['query_date'=>$today,'imei'=>$imei,'page_no'=>1,'page_size'=>1]));
            $total=self::integer($stock['total_results']??null); $rows=$stock['result_list']??null;
            if($total===null || !is_array($rows) || !self::isList($rows) || count($rows)!==min($total,1))
                throw new Problem('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','博远未返回可核对的实时库存结果');
            if($total!==0) throw new Problem('ORIGINAL_PURCHASE_REMOTE_EXISTS','博远已有此串号的库存记录，请核对原采购单');
        } catch(Problem $e) { throw new Problem($e->reason,'只读排重未通过，本次没有创建博远采购单：'.$e->getMessage()); }
    }

    /** The daily serial and bill-specific serial feeds can omit date and bill number in 89096.
     * The dated purchase line provides both. Do not infer its identity from the request filter, price or sequence. */
    private function pilotCheckSerialManifest(array $day,string $date,string $target): void
    {
        $details=array_column($this->pages('details',['business_date_start'=>$date,'business_date_end'=>$date,'trans_type'=>'购'],'id',1000),null,'id');
        $wanted=[]; $daySerial=[];
        foreach($day as $r) {
            $key=$r['detail_id']; $line=$details[$key]??[];
            $no=self::identity($line['bill_id']??null,32,'purchases.bill_id');
            self::pilotSerialJoin($r,$line,$date,$no,$this->api->accountId());
            $wanted[$no][$key]=true; $daySerial[$key]=self::pilotSerialValues($r,$target);
        }
        // A row omitted by BOTH serial endpoints is not negative evidence. Even an apparent
        // non-serial item is ambiguous here (the query does not prove its serial mode).
        if(array_diff_key($details,$daySerial)) self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','当日采购明细有行未出现在串号清单，无法完整排重；请人工核对');
        if(count($wanted)>16) self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','当日采购单较多，超出单笔试单核对范围；请人工核对');
        foreach($wanted as $no=>$keys) {
            $no=(string)$no;
            $rows=$this->pages('imei',['bill_type'=>'PUR','bill_id'=>$no],'detail_id',1000);
            foreach($rows as $r) {
                $key=$r['detail_id'];
                if(!isset($keys[$key])) self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','单据定向查询返回不属于当日该单的串号明细');
                self::pilotSerialJoin($r,$details[$key],$date,$no,$this->api->accountId());
                if(self::pilotSerialValues($r,$target)!==$daySerial[$key])
                    self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','按日期与按采购单号读取的同一明细串号不一致');
                unset($keys[$key]);
            }
            if($keys) self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','采购定向查询未找全当日该单的串号明细');
        }
    }

    private static function pilotSerialJoin(array $r,array $line,string $date,string $no,string $account): void
    {
        if(($line['business_date']??null)!==$date || ($line['bill_type']??null)!=='PUR' || ($line['trans_type']??null)!=='购' ||
            ($line['bill_id']??null)!==$no || ($r['bill_type']??null)!=='PUR' || ($r['detail_id']??null)!==($line['id']??null))
            self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','串号没有精确关联到指定日期、单号的购入明细');
        foreach(['company_id','warehouse_id','product_id'] as $k)
            if(($r[$k]??null)!==self::identity($line[$k]??null,36,'purchases.'.$k)) self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','串号明细的公司、仓库或商品与采购行不一致');
        foreach(['business_date'=>$date,'bill_id'=>$no,'big_gsdm'=>$account,'trans_type'=>'购'] as $k=>$value)
            if(array_key_exists($k,$r) && $r[$k]!==$value) self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','串号明细实际返回的单据信息与采购行矛盾');
        if(array_key_exists('big_gsdm',$line) && $line['big_gsdm']!==$account) self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','独立明细不属于当前账套');
        foreach(['supplier_id','head_id'] as $k)
            if(isset($r[$k],$line[$k]) && $r[$k]!==$line[$k]) self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','采购与串号明细返回的原单身份冲突');
        foreach([$r,$line] as $record)
            if(array_key_exists('is_effective',$record) && !in_array($record['is_effective'],[1,'1'],true)) self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','串号或采购明细有效状态异常');
    }

    private static function pilotSerialValues(array $row,string $target): array
    {
        if(in_array($target,array_intersect_key($row,array_flip(['imei','imei1','imei2','imei3','imei4','Imei4'])),true))
            self::fail('ORIGINAL_PURCHASE_REMOTE_EXISTS','博远采购记录已有此串号，请核对原采购单');
        $serial=[];
        foreach(['imei','imei1','imei2','imei3','imei4','Imei4'] as $k) {
            $v=$row[$k]??''; if($v==='') continue;
            if(!is_string($v) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}$/D',$v))
                self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','采购串号缺失、被遮盖或无法完整识别，不能据此排除重复');
            $serial[$k]=$v;
        }
        if(!isset($serial['imei1']) && !isset($serial['imei']) || isset($serial['imei'],$serial['imei1']) && $serial['imei']!==$serial['imei1'])
            self::fail('ORIGINAL_PURCHASE_REMOTE_UNKNOWN','采购原始串号缺失或互相矛盾，无法排重');
        return $serial;
    }

    public function create(array $request): array
    {
        [$head,$expected]=$this->request($request);
        try {
            $envelope=self::keys(isset($request['pilot_bridge_id'])?$this->api->purchasePilotCreate($request):$this->api->purchaseCreate($request['biz']));
            if(array_key_exists('head',$envelope) || array_key_exists('product_details',$envelope)) self::fail('PURCHASE_CREATE_AMBIGUOUS','采购创建回执层级异常，请核对原单，不能重复创建');
            $reply=$envelope['result']??null;
            if(!is_array($reply) || !$reply) self::fail('PURCHASE_CREATE_INCOMPLETE','创建回执缺少有效采购result，请核对原单，不能重复创建');
            $actual=$reply['head']??null;
            if(!is_array($actual)) self::fail('PURCHASE_CREATE_INCOMPLETE','创建回执缺少采购单头，请核对原单，不能重复创建');
            foreach(['big_gsdm'=>$this->api->accountId(),'bill_type'=>'PUR','third_no'=>$request['third_no'],
                'warehouse_id'=>$head['warehouse_id'],'supplier_id'=>$head['supplier_id'],'business_date'=>$head['business_date']] as $key=>$value) {
                if(($actual[$key]??null)!==$value) self::fail('PURCHASE_CREATE_MISMATCH','创建回执账套、单据或采购身份不一致，请核对原单');
            }
            $id=self::identity($actual['id']??null,36,'create.result.head.id'); $no=self::identity($actual['bill_id']??null,32,'create.result.head.bill_id');
            $rows=$reply['product_details']['add_details']??null;
            if(!is_array($rows) || !self::isList($rows) || count($rows)!==count($expected)) self::fail('PURCHASE_CREATE_INCOMPLETE','创建回执设备行不完整，请核对原单，不能重复创建');
            $seen=[]; $ids=[]; $seqs=[]; $safe=[];
            foreach($rows as $index=>$row) {
                if(!is_array($row)) self::fail('PURCHASE_CREATE_INCOMPLETE','创建回执设备行格式异常');
                $imei=self::identity($row['imei']??null,64,'create.result.product_details.add_details.'.$index.'.imei'); $line=$expected[$imei]??null;
                if(!$line || isset($seen[$imei])) self::fail('PURCHASE_CREATE_MISMATCH','创建回执出现未知或重复设备');
                self::product($row,$line,'imei');
                $lineId=self::identity($row['id']??null,36,'create.result.product_details.add_details.'.$index.'.id');
                if(isset($ids[$lineId])) self::fail('PURCHASE_CREATE_MISMATCH','多个设备回传相同采购明细编号');
                $seq=self::sequence($row['seq_no']??null);
                if(isset($seqs[$seq])) self::fail('PURCHASE_CREATE_MISMATCH','创建回执采购明细序号重复');
                $seen[$imei]=$ids[$lineId]=$seqs[$seq]=true;
                $safe[]=array_intersect_key($row,array_flip(['id','imei','product_id','qty','unit_price','amounts','seq_no']));
            }
            return ['created'=>true,'external_no'=>$no,'external_id'=>$id,'provider_result'=>\addon\hsx_boyuan\app\support\ReceiptFacts::head($actual),'response'=>[
                'head'=>array_intersect_key($actual,array_flip(['id','big_gsdm','bill_id','bill_type','third_no','business_date','warehouse_id','supplier_id'])),
                'product_details'=>['add_details'=>$safe],
            ]];
        } catch(Problem $e) {
            if(Pilot::pricerOperation($request['pilot_operation_id']??null) &&
                \addon\hsx_boyuan\app\support\ProductionPurchasePilot::buyerPreflightFailure($e)) throw $e;
            // Once create was attempted, even a malformed SUCCESS is not permission to resend.
            throw new Problem($e->reason,$e->getMessage(),'unknown');
        } catch(\Throwable $e) {
            throw new Problem('PURCHASE_CREATE_UNKNOWN','采购创建未取得可核验结果，请人工核对原单，不得重复创建','unknown');
        }
    }

    public function verify(array $request,string $externalNo,?array $savedPilotLine=null): array
    {
        [$head,$expected]=$this->request($request);
        self::identity($externalNo,32,'verify.external_no');
        try {
            $dialect=$this->api->purchaseDialect(); $uat=$dialect===OpenApi::PURCHASE_DIALECT_UAT;
            $priorId=isset($request['external_id'])?self::identity($request['external_id'],36,'request.external_id'):null;
            $pilot=$savedPilotLine!==null;
            if($pilot) {
                if((string)array_key_first($expected)===Pilot::THIRD_IMEI && !Pilot::pricerIntent($request,Pilot::THIRD_IMEI))
                    self::fail('PURCHASE_CREATE_LINK_UNVERIFIABLE','当前回收原单与授权不符，不查询其他单据');
                if(!$this->api->productionPilotReadback() || empty($request['pilot_bridge_id']) || !$priorId ||
                    ($savedPilotLine['head_id']??null)!==$priorId || ($savedPilotLine['bill_id']??null)!==$externalNo ||
                    ($savedPilotLine['company_id']??null)!==($request['selection']['company_id']??null) ||
                    !is_string($savedPilotLine['line_id']??null) || count($expected)!==1 ||
                    ($savedPilotLine['imei']??null)!==(string)array_key_first($expected) ||
                    ($savedPilotLine['operation_id']??null)!==($request['pilot_operation_id']??\addon\hsx_boyuan\app\support\ProductionPurchasePilot::OP) ||
                    \addon\hsx_boyuan\app\support\ProductionPurchasePilot::operation((string)array_key_first($expected))!==$savedPilotLine['operation_id']) self::fail('PURCHASE_CREATE_LINK_UNVERIFIABLE','原采购创建回执与本次核验目标不一致');
                $dialect=OpenApi::PURCHASE_DIALECT_PILOT_LINK;
            }
            $details=$this->pages('details',['business_date_start'=>$head['business_date'],'business_date_end'=>$head['business_date'],'trans_type'=>'购'],'id');
            $selected=[]; $externalId=null; $companyId=null; $sequences=[]; $purchaseById=[]; $headIdVerified=true;
            foreach($details as $row) {
                if(($row['bill_id']??null)!==$externalNo) continue;
                self::purchaseIdentity($row,$head,$externalNo);
                if($pilot && ($request['pilot_operation_id']??null)===Pilot::THIRD_OP &&
                    (trim((string)($row['sales_id']??''))==='' || trim((string)($row['sales_name']??''))===''))
                    self::fail('PURCHASE_BUYER_NOT_RETURNED','博远采购明细未完整返回业务员资料，保留采购单号，业务员待核对，不重建');
                if($pilot && Pilot::pricerOperation($request['pilot_operation_id']??null) &&
                    (($row['sales_id']??null)!==($head['sales_id']??null) || ($row['sales_name']??null)!==($head['sales_name']??null) || ($row['sales_id']??null)!==\addon\hsx_boyuan\app\support\ProductionPurchasePilot::BUYER))
                    self::fail('PURCHASE_BUYER_MISMATCH','博远原采购明细未返回与实际定价人一致的采购业务员，保留单号待核对，不重建');
                if($pilot && (($row['id']??null)!==$savedPilotLine['line_id'] || ($row['company_id']??null)!==$savedPilotLine['company_id'] ||
                    array_key_exists('big_gsdm',$row) && $row['big_gsdm']!==$this->api->accountId()))
                    self::fail('PURCHASE_LINE_MISMATCH','采购查询未匹配创建时的唯一行ID或公司');
                if(($uat || $pilot) && !array_key_exists('head_id',$row)) {
                    if($priorId===null) self::fail('PURCHASE_HEAD_ID_UNVERIFIABLE','UAT采购查询未提供head_id，且本任务没有已保存的创建单头ID；只能人工核对，不合成编号或重新创建');
                    $rowHead=$priorId; $headIdVerified=false;
                } else $rowHead=self::identity($row['head_id']??null,36,'details.head_id');
                $rowCompany=self::identity($row['company_id']??null,36,'details.company_id');
                if(($externalId!==null && $externalId!==$rowHead) || ($companyId!==null && $companyId!==$rowCompany)) self::fail('PURCHASE_HEAD_CONFLICT','同一采购单号返回多个单头或门店，不能自动确认');
                $externalId=$rowHead; $companyId=$rowCompany;
                $seq=self::sequence($row['seq_no']??null);
                if($pilot && $seq!==$savedPilotLine['seq_no']) self::fail('PURCHASE_LINE_MISMATCH','原采购创建与独立明细查询的行序号不一致');
                if(isset($sequences[$seq])) self::fail('PURCHASE_LINE_DUPLICATE','采购明细显示序号重复，不能逐台核验');
                $sequences[$seq]=$row;
                $purchaseById[$row['id']]=$row; $selected[]=$row;
            }
            if(!$selected) self::fail('PURCHASE_NOT_FOUND','日期范围未查到已知采购单号；不能据此重新创建');
            if(count($selected)!==count($expected)) self::fail('PURCHASE_DETAILS_INCOMPLETE','采购明细数量不完整或多出设备，不能确认整单');
            if($priorId!==null && $priorId!==$externalId) self::fail('PURCHASE_HEAD_CONFLICT','已保存采购单头与查询结果不一致');

            $serials=$this->pages('imei',['bill_id'=>$externalNo,'bill_type'=>'PUR'],'detail_id');
            if(count($serials)!==count($expected)) self::fail('PURCHASE_IMEI_INCOMPLETE','入库串号明细数量不完整或多出设备，不能确认整单');
            $seen=[]; $seenPurchaseIds=[]; $seenSequences=[]; $gsdm=null; $mapped=[];
            foreach($serials as $row) {
                if($uat || $pilot) $row=self::foreignKeySerial($row);
                $identity=$row;
                if($pilot && Pilot::pricerOperation($request['pilot_operation_id']??null) && (!array_key_exists('bill_id',$row) || !array_key_exists('business_date',$row))) {
                    $key=self::identity($row['detail_id']??null,36,'imei.detail_id'); $linked=$purchaseById[$key]??[];
                    if($key!==$savedPilotLine['line_id']) self::fail('PURCHASE_LINE_MISMATCH','原创建回执行与定向查询串号明细的外键不一致');
                    self::pilotSerialJoin($row,$linked,$head['business_date'],$externalNo,$this->api->accountId());
                    // Borrow only these known-omitted fields, and only from the independent matched purchase line.
                    $identity+=['bill_id'=>$linked['bill_id'],'business_date'=>$linked['business_date']];
                }
                self::purchaseIdentity($identity,$head,$externalNo);
                if(($row['company_id']??null)!==$companyId) self::fail('PURCHASE_COMPANY_MISMATCH','入库串号与采购单所属门店不一致');
                $rowGsdm=self::identity($row['gsdm']??null,36,'imei.gsdm');
                if($gsdm!==null && $gsdm!==$rowGsdm) self::fail('PURCHASE_COMPANY_MISMATCH','入库串号返回多个所属门店代码');
                $gsdm=$rowGsdm;
                $imei=self::identity($row['imei1']??null,64,'imei.imei1'); $line=$expected[$imei]??null;
                if(!$line || isset($seen[$imei])) self::fail('PURCHASE_IMEI_MISMATCH','入库串号缺失、未知或重复，不能确认整单');
                self::product($row,$line,'imei1');
                if(($row['imei']??null)!==$imei) self::fail('PURCHASE_IMEI_MISMATCH','入库串号拼接值与原始单串号不一致');
                if($uat || $pilot) {
                    // Match the serial result to the independent purchase line (and for the pilot also the CREATE line).
                    if($pilot && (array_key_exists('head_id',$row) && $row['head_id']!==$priorId ||
                        array_key_exists('big_gsdm',$row) && $row['big_gsdm']!==$this->api->accountId()))
                        self::fail('PURCHASE_HEAD_CONFLICT','入库串号的单头或账套与创建凭证冲突');
                    $purchaseId=self::identity($row['detail_id']??null,36,'imei.detail_id'); $purchase=$purchaseById[$purchaseId]??null;
                    if(!$purchase || isset($seenPurchaseIds[$purchaseId])) self::fail('PURCHASE_LINE_MISMATCH','入库串号未通过采购明细行外键唯一联结');
                    if(array_key_exists('seq_no',$row) && self::sequence($row['seq_no'])!==self::sequence($purchase['seq_no'])) self::fail('PURCHASE_LINE_MISMATCH','入库串号行序号与采购行外键互相矛盾');
                } else {
                    $seq=self::sequence($row['seq_no']??null); $purchase=$sequences[$seq]??null;
                    if(!$purchase || isset($seenSequences[$seq])) self::fail('PURCHASE_LINE_MISMATCH','入库串号无法唯一对应采购明细序号');
                    $seenSequences[$seq]=true; $purchaseId=$purchase['id'];
                }
                self::product($purchase,$line);
                $seen[$imei]=$seenPurchaseIds[$purchaseId]=true;
                $mapped[$imei]=['source_line_id'=>$line['source_line_id'],'device_ref'=>$line['device_ref'],
                    'external_line_id'=>self::identity($purchaseId,36,'details.id'),'external_document_id'=>$externalId,'imei'=>$imei];
            }
            $lines=[]; foreach($expected as $imei=>$line) $lines[]=$mapped[$imei];
            $buyer=empty($head['sales_id'])?[]:['pricing_buyer'=>['source'=>'purchase_details']+
                \addon\hsx_boyuan\app\support\ReceiptFacts::observedBuyer($head,$selected)];
            return ['verified'=>true,'external_no'=>$externalNo,'external_id'=>$externalId,'lines'=>$lines,
                'verification_level'=>'purchase_imei_records_matched','stock_balance_verified'=>false,'financial_posting_verified'=>false,
                'evidence'=>$buyer+['bill_type'=>'PUR','business_date'=>$head['business_date'],'request_third_no'=>$request['third_no'],
                    'response_contract'=>$dialect,'external_line_id_source'=>'purchase_detail.id',
                    'document_head_id_verified'=>$headIdVerified,'created_head_id_matched'=>$priorId!==null && $headIdVerified,
                    'company_id'=>$companyId,'gsdm'=>$gsdm,'supplier_id'=>$head['supplier_id'],'warehouse_id'=>$head['warehouse_id'],
                    'purchase_detail_count'=>count($selected),'imei_detail_count'=>count($serials),'record_is_effective'=>true,
                    'inventory_evidence'=>'purchase_imei_records','limitations'=>[
                        'is_effective仅说明记录有效未删除，不表示已审核',
                        '入库串号明细完整匹配，不等于当前库存余额核验',
                        '未调用或核验任何应付、付款及财务过账接口',
                        '查询接口不返回第三方单号；原始第三方单号仅来自冻结请求',
                        $headIdVerified?'采购单头ID已由查询核对':'查询未独立返回单头ID；单头ID来自已保存的创建回执',
                        $pilot?'正式原单核对：创建行ID=独立采购明细ID=串号detail_id；串号查询逐位匹配，未按行序或金额猜测':($uat?'UAT专用字段契约：detail_id为采购行外键，amount/company_code为明确适配字段；不用于生产':'文档字段契约：采购与串号明细使用唯一seq_no交叉核验'),
                    ]],
            ];
        } catch(Problem $e) { throw new Problem($e->reason,$e->getMessage(),'unknown'); }
        catch(\Throwable $e) { throw new Problem('PURCHASE_VERIFY_UNKNOWN','采购与入库串号核验结果不确定，请核对已知原单','unknown'); }
    }

    /** Validate the frozen official JSON against its independent per-device manifest. */
    private function request(array $request): array
    {
        if(($request['schema']??null)!=='boyuan.purchase.v154') throw new Problem('PURCHASE_SCHEMA_INVALID','采购协议版本无效');
        $third=self::identity($request['third_no']??null,32,'request.third_no');
        $biz=$request['biz']??null;
        if(!is_array($biz) || !is_array($biz['head']??null) || !is_array($biz['product_details']??null)) throw new Problem('PURCHASE_REQUEST_INVALID','采购JSON缺少有效单头或商品节点');
        $head=$biz['head']; $rows=$biz['product_details']['add_details']??null; $lines=$request['lines']??null;
        if(!is_array($rows) || !self::isList($rows) || !is_array($lines) || !self::isList($lines)
            || !$lines || count($lines)>100 || count($rows)!==count($lines)) throw new Problem('PURCHASE_REQUEST_INVALID','采购必须提供1至100条完整的一机一行明细');
        if(array_diff(array_keys($biz),['head','product_details']) || array_keys($biz['product_details'])!==['add_details']) throw new Problem('PURCHASE_SCOPE_DENIED','采购仅支持创建商品入库，不支持收银或其他业务节点');
        $headKeys=['warehouse_id','warehouse_name','third_no','business_date','order_id','logistics_no','cash_term','is_auto_generate_supplier','supplier_id','supplier_name','pur_way','pur_way_name','pay_way','pay_way_name','sales_id','sales_name','creater_id','creater_name','is_need_invioces','remark'];
        if(array_diff(array_keys($head),$headKeys)) throw new Problem('PURCHASE_SCOPE_DENIED','采购单头包含未支持的字段');
        // Values are selected from Boyuan staff by the caller; never substitute a local uid.
        if(isset($head['creater_id']) && $head['creater_id']!=='') self::identity($head['creater_id'],36,'request.biz.head.creater_id');
        if(isset($head['creater_name']) && (!is_string($head['creater_name']) || strlen($head['creater_name'])>64)) throw new Problem('PURCHASE_CREATER_INVALID','博远制单人名称格式无效');
        if(!in_array($head['cash_term']??0,[0,'0'],true) || !in_array($head['is_auto_generate_supplier']??0,[0,'0'],true)) throw new Problem('PURCHASE_SCOPE_DENIED','当前不支持现结、资金写入或自动创建供应商');
        if(($head['third_no']??null)!==$third) throw new Problem('PURCHASE_THIRD_NO_MISMATCH','采购第三方单号必须与冻结请求一致');
        self::identity($head['warehouse_id']??null,36,'request.biz.head.warehouse_id'); self::identity($head['supplier_id']??null,36,'request.biz.head.supplier_id');
        $date=$head['business_date']??null;
        if(!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || !($parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date)) || $parsed->format('Y-m-d')!==$date) throw new Problem('PURCHASE_DATE_INVALID','采购日期必须为有效的yyyy-MM-dd');
        $expected=[]; $sourceIds=[]; $devices=[];
        foreach($lines as $index=>$line) {
            if(!is_array($line)) throw new Problem('PURCHASE_LINES_INVALID','采购设备明细格式无效');
            foreach(['source_line_id','device_ref','product_id','warehouse_id','supplier_id'] as $key) self::identity($line[$key]??null,in_array($key,['source_line_id','device_ref'],true)?128:36,'request.lines.'.$index.'.'.$key);
            $imei=self::identity($line['imei']??null,64,'request.lines.'.$index.'.imei');
            if(isset($expected[$imei]) || isset($sourceIds[$line['source_line_id']]) || isset($devices[$line['device_ref']]) || !in_array($line['quantity']??null,['1',1],true)) throw new Problem('PURCHASE_LINES_INVALID','设备、串号和来源明细不能重复，数量必须为1');
            Contract::cents($line['amount']??null);
            if($line['warehouse_id']!==$head['warehouse_id'] || $line['supplier_id']!==$head['supplier_id']) throw new Problem('PURCHASE_LINES_INVALID','同张采购单只能包含同一供应商和仓库');
            $expected[$imei]=$line; $sourceIds[$line['source_line_id']]=$devices[$line['device_ref']]=true;
        }
        $seen=[];
        foreach($rows as $index=>$row) {
            if(!is_array($row) || array_diff(array_keys($row),['imei','imei2','imei3','Imei4','product_id','product_full_name','qty','unit_price','amounts','remark'])) throw new Problem('PURCHASE_PRODUCT_INVALID','采购JSON商品行包含不支持的字段');
            $imei=self::identity($row['imei']??null,64,'request.biz.product_details.add_details.'.$index.'.imei'); $line=$expected[$imei]??null;
            if(!$line || isset($seen[$imei])) throw new Problem('PURCHASE_PRODUCT_INVALID','采购JSON商品行与设备清单不一致');
            try { self::product($row,$line,'imei'); }
            catch(Problem $e) { throw new Problem($e->reason,$e->getMessage()); }
            $seen[$imei]=true;
        }
        return [$head,$expected];
    }

    private function pages(string $kind,array $params,string $idKey,int $maxRows=self::MAX_QUERY_ROWS): array
    {
        $all=[]; $seen=[]; $total=null;
        for($page=1;$page<=intdiv($maxRows,self::PAGE_SIZE);$page++) {
            $data=self::keys($this->api->purchaseQuery($kind,$params+['page_no'=>$page,'page_size'=>self::PAGE_SIZE]));
            $rows=$data['result_list']??null; $count=self::integer($data['total_results']??null);
            if(!is_array($rows) || !self::isList($rows) || $count===null || $count>$maxRows || ($total!==null && $total!==$count)) self::fail('PURCHASE_PAGE_INVALID','采购核验分页列表或总数异常，不能确认整单');
            $total=$count; $remaining=$total-count($all);
            if($remaining<0 || count($rows)!==min(self::PAGE_SIZE,$remaining)) self::fail('PURCHASE_PAGE_INCOMPLETE','采购核验分页不完整，不能用部分结果确认整单');
            foreach($rows as $index=>$row) {
                if(!is_array($row)) self::fail('PURCHASE_PAGE_INVALID','采购核验分页行格式异常');
                $id=self::identity($row[$idKey]??null,36,$kind.'.page'.$page.'.result_list.'.$index.'.'.$idKey);
                if(isset($seen[$id])) self::fail('PURCHASE_PAGE_DUPLICATE','采购核验分页返回重复明细');
                $seen[$id]=true; $all[]=$row;
            }
            if(count($all)===$total) return $all;
        }
        self::fail('PURCHASE_PAGE_LIMIT','采购核验数据超过安全分页上限，请人工核对');
    }

    private static function purchaseIdentity(array $row,array $head,string $no): void
    {
        foreach(['bill_id'=>$no,'bill_type'=>'PUR','business_date'=>$head['business_date'],'supplier_id'=>$head['supplier_id'],'warehouse_id'=>$head['warehouse_id'],'trans_type'=>'购'] as $key=>$value) {
            if(($row[$key]??null)!==$value) self::fail('PURCHASE_IDENTITY_MISMATCH','采购或入库串号的单据、供应商、仓库身份不一致');
        }
        if(!in_array($row['is_effective']??null,[1,'1'],true)) self::fail('PURCHASE_RECORD_INVALID','采购或入库串号记录无效、已删除或状态未知');
    }
    /** Verified foreign-key read contract only; conflicting dual representations are never ignored. */
    private static function foreignKeySerial(array $row): array
    {
        if(array_key_exists('company_code',$row)) {
            $code=self::identity($row['company_code'],36,'imei.company_code');
            if(array_key_exists('gsdm',$row) && $row['gsdm']!==$code) self::fail('PURCHASE_COMPANY_MISMATCH','入库串号company_code与gsdm互相矛盾');
            $row['gsdm']=$code;
        }
        if(array_key_exists('amount',$row)) {
            $amount=self::money($row['amount']);
            if(array_key_exists('amounts',$row) && self::money($row['amounts'])!==$amount) self::fail('PURCHASE_AMOUNT_MISMATCH','入库串号amount与amounts互相矛盾');
            $row['amounts']=$row['amount'];
        }
        return $row;
    }
    private static function product(array $row,array $line,?string $serialKey=null): void
    {
        if(($row['product_id']??null)!==$line['product_id'] || !in_array($row['qty']??null,[1,'1',1.0],true)
            || self::money($row['unit_price']??null)!==Contract::cents($line['amount']) || self::money($row['amounts']??null)!==Contract::cents($line['amount'])) self::fail('PURCHASE_PRODUCT_MISMATCH','采购商品、数量、单价或逐台金额不一致');
        if($serialKey!==null && ($row[$serialKey]??null)!==$line['imei']) self::fail('PURCHASE_IMEI_MISMATCH','采购原始串号不一致');
        foreach(['imei2','imei3','Imei4','imei4'] as $key) if(isset($row[$key]) && $row[$key]!=='') self::fail('PURCHASE_SERIAL_MODE_UNSUPPORTED','当前核验仅支持一机一个原始串号，不能忽略其他串号');
    }
    /** Compare vendor decimal numbers without rounding a mismatched fractional cent. */
    private static function money($value): int
    {
        if(is_float($value)) {
            if(!is_finite($value)) self::fail('PURCHASE_AMOUNT_INVALID','博远金额格式异常');
            $value=json_encode($value,JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
        } elseif(is_int($value)) $value=(string)$value;
        if(!is_string($value) || !preg_match('/^(0|[1-9]\d{0,9})(?:\.(\d{1,2})0*)?$/D',$value,$m)) self::fail('PURCHASE_AMOUNT_INVALID','博远金额不是精确的非负两位金额');
        return (int)$m[1]*100+(int)str_pad($m[2]??'',2,'0');
    }
    private static function identity($value,int $max,string $path='request'): string
    {
        if(!is_string($value) || $value==='' || strlen($value)>$max || preg_match('/[\x00-\x20,]/',$value)) {
            $empty=$value===null || $value===''?'true':'false'; $length=is_string($value)?(string)strlen($value):'n/a';
            throw new Problem('PURCHASE_IDENTITY_INVALID','采购缺少有效的稳定原始编号（path='.$path.'; type='.get_debug_type($value).'; empty='.$empty.'; length='.$length.'）');
        }
        return $value;
    }
    private static function integer($value): ?int
    {
        if(is_int($value)) return $value>=0?$value:null;
        if(!is_string($value) || !preg_match('/^(0|[1-9]\d{0,8})$/D',$value)) return null;
        return (int)$value;
    }
    private static function sequence($value): int
    {
        $seq=self::integer($value);
        if($seq===null || $seq<1) self::fail('PURCHASE_SEQUENCE_INVALID','采购明细缺少有效显示序号');
        return $seq;
    }
    private static function isList(array $value): bool { return !$value || array_keys($value)===range(0,count($value)-1); }
    /** Vendor examples have trailing spaces in keys, but duplicate aliases are ambiguous. */
    private static function keys(array $value): array
    {
        $clean=[];
        foreach($value as $key=>$item) {
            $key=is_string($key)?trim($key):$key;
            if(array_key_exists($key,$clean)) self::fail('PURCHASE_RESPONSE_AMBIGUOUS','博远响应包含重名字段');
            $clean[$key]=is_array($item)?self::keys($item):$item;
        }
        return $clean;
    }
    private static function fail(string $reason,string $message): void { throw new Problem($reason,$message,'unknown'); }
}
