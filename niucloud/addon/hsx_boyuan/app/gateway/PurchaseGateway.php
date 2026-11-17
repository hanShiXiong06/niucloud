<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;

use addon\hsx_boyuan\app\support\{Contract,Problem};

/** v1.5.4 purchase creation and read-only verification; no financial operations. */
final class PurchaseGateway
{
    private const PAGE_SIZE=100;
    private const MAX_QUERY_ROWS=100000;
    public function __construct(private OpenApi $api) {}

    public function create(array $request): array
    {
        [$head,$expected]=$this->request($request);
        try {
            $envelope=self::keys($this->api->purchaseCreate($request['biz']));
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
            return ['created'=>true,'external_no'=>$no,'external_id'=>$id,'response'=>[
                'head'=>array_intersect_key($actual,array_flip(['id','big_gsdm','bill_id','bill_type','third_no','business_date','warehouse_id','supplier_id'])),
                'product_details'=>['add_details'=>$safe],
            ]];
        } catch(Problem $e) {
            // Once create was attempted, even a malformed SUCCESS is not permission to resend.
            throw new Problem($e->reason,$e->getMessage(),'unknown');
        } catch(\Throwable $e) {
            throw new Problem('PURCHASE_CREATE_UNKNOWN','采购创建未取得可核验结果，请人工核对原单，不得重复创建','unknown');
        }
    }

    public function verify(array $request,string $externalNo): array
    {
        [$head,$expected]=$this->request($request);
        self::identity($externalNo,32,'verify.external_no');
        try {
            $dialect=$this->api->purchaseDialect(); $uat=$dialect===OpenApi::PURCHASE_DIALECT_UAT;
            $priorId=isset($request['external_id'])?self::identity($request['external_id'],36,'request.external_id'):null;
            $details=$this->pages('details',['business_date_start'=>$head['business_date'],'business_date_end'=>$head['business_date'],'trans_type'=>'购'],'id');
            $selected=[]; $externalId=null; $companyId=null; $sequences=[]; $purchaseById=[]; $headIdVerified=true;
            foreach($details as $row) {
                if(($row['bill_id']??null)!==$externalNo) continue;
                self::purchaseIdentity($row,$head,$externalNo);
                if($uat && !array_key_exists('head_id',$row)) {
                    if($priorId===null) self::fail('PURCHASE_HEAD_ID_UNVERIFIABLE','UAT采购查询未提供head_id，且本任务没有已保存的创建单头ID；只能人工核对，不合成编号或重新创建');
                    $rowHead=$priorId; $headIdVerified=false;
                } else $rowHead=self::identity($row['head_id']??null,36,'details.head_id');
                $rowCompany=self::identity($row['company_id']??null,36,'details.company_id');
                if(($externalId!==null && $externalId!==$rowHead) || ($companyId!==null && $companyId!==$rowCompany)) self::fail('PURCHASE_HEAD_CONFLICT','同一采购单号返回多个单头或门店，不能自动确认');
                $externalId=$rowHead; $companyId=$rowCompany;
                $seq=self::sequence($row['seq_no']??null);
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
                if($uat) $row=self::uatImei($row);
                self::purchaseIdentity($row,$head,$externalNo);
                if(($row['company_id']??null)!==$companyId) self::fail('PURCHASE_COMPANY_MISMATCH','入库串号与采购单所属门店不一致');
                $rowGsdm=self::identity($row['gsdm']??null,36,'imei.gsdm');
                if($gsdm!==null && $gsdm!==$rowGsdm) self::fail('PURCHASE_COMPANY_MISMATCH','入库串号返回多个所属门店代码');
                $gsdm=$rowGsdm;
                $imei=self::identity($row['imei1']??null,64,'imei.imei1'); $line=$expected[$imei]??null;
                if(!$line || isset($seen[$imei])) self::fail('PURCHASE_IMEI_MISMATCH','入库串号缺失、未知或重复，不能确认整单');
                self::product($row,$line,'imei1');
                if(($row['imei']??null)!==$imei) self::fail('PURCHASE_IMEI_MISMATCH','入库串号拼接值与原始单串号不一致');
                if($uat) {
                    // Observed UAT foreign key: serial.detail_id is purchase-detail.id, not serial.id.
                    $purchaseId=self::identity($row['detail_id']??null,36,'imei.detail_id'); $purchase=$purchaseById[$purchaseId]??null;
                    if(!$purchase || isset($seenPurchaseIds[$purchaseId])) self::fail('PURCHASE_LINE_MISMATCH','UAT入库串号未通过真实采购行外键唯一联结');
                    if(array_key_exists('seq_no',$row) && self::sequence($row['seq_no'])!==self::sequence($purchase['seq_no'])) self::fail('PURCHASE_LINE_MISMATCH','UAT入库串号序号与真实外键对应采购行互相矛盾');
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
            return ['verified'=>true,'external_no'=>$externalNo,'external_id'=>$externalId,'lines'=>$lines,
                'verification_level'=>'purchase_imei_records_matched','stock_balance_verified'=>false,'financial_posting_verified'=>false,
                'evidence'=>['bill_type'=>'PUR','business_date'=>$head['business_date'],'request_third_no'=>$request['third_no'],
                    'response_contract'=>$dialect,'external_line_id_source'=>'purchase_detail.id',
                    'document_head_id_verified'=>$headIdVerified,'created_head_id_matched'=>$priorId!==null && $headIdVerified,
                    'company_id'=>$companyId,'gsdm'=>$gsdm,'supplier_id'=>$head['supplier_id'],'warehouse_id'=>$head['warehouse_id'],
                    'purchase_detail_count'=>count($selected),'imei_detail_count'=>count($serials),'record_is_effective'=>true,
                    'inventory_evidence'=>'purchase_imei_records','limitations'=>[
                        'is_effective仅说明记录有效未删除，不表示已审核',
                        '入库串号明细完整匹配，不等于当前库存余额核验',
                        '未调用或核验任何应付、付款及财务过账接口',
                        '查询接口不返回第三方单号；原始第三方单号仅来自冻结请求',
                        $headIdVerified?'采购单头ID已由查询核对':'UAT查询未提供单头ID；保留已保存的创建ID，但未由查询核对',
                        $uat?'UAT专用字段契约：detail_id为采购行外键，amount/company_code为明确适配字段；不用于生产':'文档字段契约：采购与串号明细使用唯一seq_no交叉核验',
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

    private function pages(string $kind,array $params,string $idKey): array
    {
        $all=[]; $seen=[]; $total=null;
        for($page=1;$page<=intdiv(self::MAX_QUERY_ROWS,self::PAGE_SIZE);$page++) {
            $data=self::keys($this->api->purchaseQuery($kind,$params+['page_no'=>$page,'page_size'=>self::PAGE_SIZE]));
            $rows=$data['result_list']??null; $count=self::integer($data['total_results']??null);
            if(!is_array($rows) || !self::isList($rows) || $count===null || $count>self::MAX_QUERY_ROWS || ($total!==null && $total!==$count)) self::fail('PURCHASE_PAGE_INVALID','采购核验分页列表或总数异常');
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
    /** Exact observed test-only aliases; conflicting dual representations are never ignored. */
    private static function uatImei(array $row): array
    {
        if(array_key_exists('company_code',$row)) {
            $code=self::identity($row['company_code'],36,'imei.company_code');
            if(array_key_exists('gsdm',$row) && $row['gsdm']!==$code) self::fail('PURCHASE_COMPANY_MISMATCH','UAT入库串号company_code与gsdm互相矛盾');
            $row['gsdm']=$code;
        }
        if(array_key_exists('amount',$row)) {
            $amount=self::money($row['amount']);
            if(array_key_exists('amounts',$row) && self::money($row['amounts'])!==$amount) self::fail('PURCHASE_AMOUNT_MISMATCH','UAT入库串号amount与amounts互相矛盾');
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
