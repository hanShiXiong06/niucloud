<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;

use addon\hsx_boyuan\app\support\{Contract,Problem};

/** Accounting voucher only. Its receipt is neither a bank transfer nor proof of allocation. */
final class PaymentGateway
{
    public const SCHEMA='boyuan.purchase.payment.v1';
    public function __construct(private OpenApi $api) {}

    public function create(array $request): array
    {
        $production=$this->api->productionVoucher($request);
        if(!$production && (!$this->api->uatPaymentEnabled() || ($request['mode']??'test')!=='test')) throw new Problem('PAYMENT_UAT_ONLY','付款凭证未与已开通的账套、环境及原凭证匹配，没有发送');
        [$head,$detail,$amount]=self::validate($request);
        try {
            $envelope=self::keys($production?$this->api->confirmedVoucherCreate($request):$this->api->paymentCreate($request['biz']));
            if(array_key_exists('head',$envelope) || array_key_exists('payment_details',$envelope)) self::fail('PAYMENT_RESPONSE_AMBIGUOUS','付款回执层级异常，须核对原操作，不得重复创建');
            $result=$envelope['result']??null; $actual=is_array($result)?($result['head']??null):null;
            if(!is_array($result) || !is_array($actual)) self::fail('PAYMENT_RESPONSE_INCOMPLETE','付款SUCCESS未返回有效result.head；结果未知，不能重复登记');
            foreach(['big_gsdm'=>$this->api->accountId(),'bill_type'=>'PAYMENT','company_id'=>$request['company_id'],
                'supplier_cust_id'=>$request['supplier_id'],'business_date'=>$head['business_date']] as $field=>$expected) {
                if(($actual[$field]??null)!==$expected) self::fail('PAYMENT_HEAD_MISMATCH','付款回执账套、类型、公司、供应商或日期不一致（field='.$field.'）');
            }
            if(!in_array($actual['scope_flag']??null,[3,'3'],true)) self::fail('PAYMENT_SCOPE_MISMATCH','付款回执未明确限定所选公司范围，须人工核对原单');
            if(array_key_exists('amounts',$actual) && self::money($actual['amounts'])!==$amount) self::fail('PAYMENT_AMOUNT_MISMATCH','付款回执单头金额与本站已确认付款不一致，不能按已办处理');
            foreach(['creater_id','creater_name'] as $field) if(array_key_exists($field,$actual) && $actual[$field]!==$head[$field]) self::fail('PAYMENT_CREATER_MISMATCH','付款回执制单人与冻结选择不一致');
            $externalId=self::id($actual['id']??null,36,'result.head.id'); $externalNo=self::id($actual['bill_id']??null,32,'result.head.bill_id');
            $rows=$result['payment_details']['add_details']??null;
            if(!is_array($rows) || !self::isList($rows) || count($rows)!==1 || !is_array($rows[0])) self::fail('PAYMENT_RESPONSE_INCOMPLETE','付款回执账户行不完整或多出账户，不能确认原操作');
            $row=$rows[0];
            if(($row['account_id']??null)!==$request['account_id'] || self::money($row['amounts']??null)!==$amount
                || self::money($row['handling_fee']??0)!==0) self::fail('PAYMENT_AMOUNT_MISMATCH','付款账户、金额或手续费与本站已确认付款事实不一致');
            foreach(['subject_id','payment_type_id'] as $field) if(isset($detail[$field]) && $detail[$field]!=='' && ($row[$field]??null)!==$detail[$field]) self::fail('PAYMENT_ACCOUNTING_MISMATCH','付款科目或款项类型与冻结选择不一致');
            self::id($row['id']??null,36,'result.payment_details.add_details.0.id');
            if(!in_array($row['seq_no']??null,[1,'1'],true)) self::fail('PAYMENT_LINE_MISMATCH','付款回执单账户明细序号无效');
            $safeHead=array_intersect_key($actual,array_flip(['id','big_gsdm','bill_id','bill_type','company_id','supplier_cust_id','business_date','scope_flag','creater_id']));
            $safeRow=array_intersect_key($row,array_flip(['id','account_id','amounts','handling_fee','subject_id','payment_type_id','seq_no']));
            return ['created'=>true,'payment_registered'=>true,'verified'=>false,'external_no'=>$externalNo,'external_id'=>$externalId,'provider_result'=>\addon\hsx_boyuan\app\support\ReceiptFacts::head($actual),
                'amount'=>Contract::amount($amount),'verification_level'=>'payment_create_receipt_matched',
                'financial_posting_verified'=>false,'allocation_applied_verified'=>false,'bank_transfer_verified'=>false,
                'response'=>['head'=>$safeHead,'payment_details'=>['add_details'=>[$safeRow]]],
                'evidence'=>['response_contract'=>'boyuan.payment.v154.documented','environment'=>$production?'production':'uat','scope_flag'=>3,
                    'company_id'=>$request['company_id'],'supplier_id'=>$request['supplier_id'],'account_id'=>$request['account_id'],
                    'amount'=>Contract::amount($amount),'checked_at'=>time(),'payment_service'=>'boyuan.biz.bill.payment.generate',
                    'limitations'=>['仅表示付款凭证身份和金额匹配，不表示审核过账或原采购已冲减',
                        '未调用统一支付、银行转账、撤销或退款接口；不作为实际资金支付凭证',
                        '原采购未结余额和核销效果须另行只读核对，不能把未结金额0直接解释为已付',
                        '付款接口未提供第三方幂等号；未知结果不能自动重发']],
            ];
        } catch(Problem $error) { throw new Problem($error->reason,$error->getMessage(),'unknown'); }
        catch(\Throwable $error) { throw new Problem('PAYMENT_CREATE_UNKNOWN','付款登记未取得确定回执，请核对原操作；不得重复创建或发起真实支付','unknown'); }
    }

    public static function validate(array $request): array
    {
        if(($request['schema']??null)!==self::SCHEMA) throw new Problem('PAYMENT_SCHEMA_INVALID','付款登记协议无效');
        foreach(['company_id','supplier_id','account_id'] as $field) self::id($request[$field]??null,36,'request.'.$field);
        $amount=Contract::cents($request['amount']??null);
        if($amount<=0) throw new Problem('PAYMENT_AMOUNT_INVALID','仅登记本站已确认的正数付款金额，零金额不建付款单');
        $biz=$request['biz']??null;
        if(!is_array($biz) || array_diff(array_keys($biz),['head','payment_details']) || !is_array($biz['head']??null)
            || !is_array($biz['payment_details']??null) || array_keys($biz['payment_details'])!==['add_details']) throw new Problem('PAYMENT_SCOPE_DENIED','付款JSON仅允许官方单头和付款账户明细');
        $head=$biz['head'];
        $headAllowed=['company_id','company_name','business_date','supplier_cust_id','supplier_cust_name','is_auto_generate_supplier','is_auto_generate_subject','scope_flag',
            'broker_user_id','broker_user_name','payment_item_id','payment_item_name','payment_type_id','payment_type_name','creater_id','creater_name','remark'];
        if(array_diff(array_keys($head),$headAllowed) || !in_array($head['scope_flag']??null,[3,'3'],true)
            || !in_array($head['is_auto_generate_supplier']??null,[0,'0'],true) || !in_array($head['is_auto_generate_subject']??null,[0,'0'],true)) throw new Problem('PAYMENT_SCOPE_DENIED','付款必须限所选公司，明确禁止自动建供应商或科目，不支持银行指令及其他节点');
        if(($head['company_id']??null)!==$request['company_id'] || ($head['supplier_cust_id']??null)!==$request['supplier_id']) throw new Problem('PAYMENT_SOURCE_MISMATCH','付款公司或供应商与冻结业务事实不一致');
        self::id($head['creater_id']??null,36,'request.biz.head.creater_id');
        if(!is_string($head['creater_name']??null) || trim($head['creater_name'])==='' || strlen($head['creater_name'])>64 || preg_match('/[\x00-\x1f\x7f]/',$head['creater_name'])) throw new Problem('PAYMENT_CREATER_INVALID','请选择真实启用的博远制单人，不能使用本地员工ID代替');
        $date=$head['business_date']??null; $parsed=is_string($date)?\DateTimeImmutable::createFromFormat('!Y-m-d',$date):false;
        if(!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || !$parsed || $parsed->format('Y-m-d')!==$date) throw new Problem('PAYMENT_DATE_INVALID','付款日期必须为有效yyyy-MM-dd');
        $rows=$biz['payment_details']['add_details']??null;
        if(!is_array($rows) || !self::isList($rows) || count($rows)!==1 || !is_array($rows[0])) throw new Problem('PAYMENT_ACCOUNT_INVALID','首版UAT每笔已确认付款只登记一个明确账户');
        $detail=$rows[0]; $allowed=['account_id','account_name','handling_fee','amounts','subject_id','subject_name','payment_type_id','payment_type_name','seq_no','remark'];
        if(array_diff(array_keys($detail),$allowed) || ($detail['account_id']??null)!==$request['account_id'] || Contract::cents($detail['amounts']??null)!==$amount
            || self::money($detail['handling_fee']??0)!==0 || isset($detail['seq_no']) && !in_array($detail['seq_no'],[1,'1'],true)) throw new Problem('PAYMENT_ACCOUNT_INVALID','账户、金额或零手续费与本站确认事实不一致，不能登记');
        return [$head,$detail,$amount];
    }
    private static function money($value): int
    {
        if(is_float($value)) {
            if(!is_finite($value)) throw new Problem('PAYMENT_AMOUNT_INVALID','付款金额格式无效');
            $value=json_encode($value,JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
        } elseif(is_int($value)) $value=(string)$value;
        if(!is_string($value) || !preg_match('/^(0|[1-9]\d{0,9})(?:\.(\d{1,2})0*)?$/D',$value,$match)) throw new Problem('PAYMENT_AMOUNT_INVALID','付款金额必须为精确非负两位十进制数，不能舍入推测');
        return (int)$match[1]*100+(int)str_pad($match[2]??'',2,'0');
    }
    private static function id($value,int $max,string $path): string
    {
        if(!is_string($value) || $value==='' || strlen($value)>$max || preg_match('/[\x00-\x20,]/',$value)) throw new Problem('PAYMENT_IDENTITY_INVALID','付款身份字段无效（path='.$path.'; type='.get_debug_type($value).'; empty='.($value===null || $value===''?'true':'false').'; length='.(is_string($value)?strlen($value):'n/a').'）');
        return $value;
    }
    private static function keys(array $value): array
    {
        $clean=[];
        foreach($value as $key=>$item) {
            $key=is_string($key)?trim($key):$key;
            if(array_key_exists($key,$clean)) self::fail('PAYMENT_RESPONSE_AMBIGUOUS','付款回执出现重名字段，不猜测原操作身份');
            $clean[$key]=is_array($item)?self::keys($item):$item;
        }
        return $clean;
    }
    private static function isList(array $value): bool { return !$value || array_keys($value)===range(0,count($value)-1); }
    private static function fail(string $reason,string $message): void { throw new Problem($reason,$message,'unknown'); }
}
