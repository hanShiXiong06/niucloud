<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;

use addon\hsx_boyuan\app\support\{Contract,Problem,ReceiptFacts};

/** v1.5.4 §4.2.63 / §5.10: receipt voucher only. No bank transfer, retry, or inference of sale allocation. */
final class ReceiptGateway
{
    public const SCHEMA = 'boyuan.wholesale.receipt.v1';
    public const SERVICE = 'boyuan.biz.bill.receipt.generate';

    public function __construct(private OpenApi $api) {}

    public static function validate(array $request): array
    {
        if (($request['mode'] ?? null) !== 'test') throw new Problem('RECEIPT_UAT_ONLY', '收款凭证只允许提交到已核对的测试账套');
        if (($request['schema'] ?? null) !== self::SCHEMA) throw new Problem('RECEIPT_SCHEMA_INVALID', '销售收款登记协议无效');
        foreach (['company_id','customer_id','account_id'] as $field) self::id($request[$field] ?? null, 36);
        $amount = Contract::cents($request['amount'] ?? null);
        if ($amount <= 0) throw new Problem('RECEIPT_AMOUNT_INVALID', '仅可同步财务已登记的正数收款');
        $biz = $request['biz'] ?? null;
        if (!is_array($biz) || array_diff(array_keys($biz), ['head','receipt_details']) || !is_array($head = $biz['head'] ?? null)
            || !is_array($details = $biz['receipt_details'] ?? null) || array_keys($details) !== ['add_details']
            || !is_array($rows = $details['add_details']) || array_keys($rows) !== [0] || !is_array($line = $rows[0])) {
            throw new Problem('RECEIPT_SCOPE_DENIED', '销售收款仅接受单一账户的收款凭证，不支持付款、分摊和银行指令');
        }
        if (array_diff(array_keys($head), ['company_id','company_name','supplier_cust_id','supplier_cust_name','business_date',
                'scope_flag','is_auto_generate_cust','is_auto_generate_subject','creater_id','creater_name','remark'])
            || !in_array($head['scope_flag'] ?? null,[3,'3'],true)
            || !in_array($head['is_auto_generate_cust'] ?? null,[0,'0'],true)
            || !in_array($head['is_auto_generate_subject'] ?? null,[0,'0'],true)
            || ($head['company_id'] ?? null) !== $request['company_id'] || ($head['supplier_cust_id'] ?? null) !== $request['customer_id']) {
            throw new Problem('RECEIPT_SOURCE_MISMATCH', '收款公司、客户或范围无效；禁止自动建档');
        }
        self::id($head['creater_id'] ?? null, 36);
        self::stringField($head['creater_name'] ?? null, 64, true);
        $date = $head['business_date'] ?? null;
        if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
            || !($day = \DateTimeImmutable::createFromFormat('!Y-m-d',$date)) || $day->format('Y-m-d') !== $date) {
            throw new Problem('RECEIPT_DATE_INVALID', '请核对原收款凭证的日期');
        }
        if (array_diff(array_keys($line), ['account_id','account_name','amounts','handling_fee','seq_no','remark'])
            || ($line['account_id'] ?? null) !== $request['account_id'] || self::money($line['amounts'] ?? null) !== $amount
            || self::money($line['handling_fee'] ?? 0) !== 0 || !in_array($line['seq_no'] ?? 1,[1,'1'],true)) {
            throw new Problem('RECEIPT_ACCOUNT_INVALID', '收款账户、金额或手续费与原凭证不符');
        }
        foreach (['company_name'=>128,'supplier_cust_name'=>64,'remark'=>256] as $key=>$max) {
            if (array_key_exists($key,$head)) self::stringField($head[$key],$max);
        }
        foreach (['account_name'=>64,'remark'=>256] as $key=>$max) {
            if (array_key_exists($key,$line)) self::stringField($line[$key],$max);
        }
        return [$head,$line,$amount];
    }

    public function create(array $request): array
    {
        if (!$this->api->uatReceiptEnabled()) throw new Problem('RECEIPT_UAT_ONLY', '收款凭证不可投递到生产或其他未核对的连接');
        [$expected, , $amount] = self::validate($request);
        $ledger = $this->api->accountId();
        try {
            $response = self::keys($this->api->receiptCreate($request));
            $result = $response['result'] ?? null;
            $head = is_array($result) ? ($result['head'] ?? null) : null;
            $details = is_array($result) ? ($result['receipt_details'] ?? null) : null;
            $rows = is_array($details) ? ($details['add_details'] ?? null) : null;
            if (array_intersect(['head','receipt_details','payment_details'],array_keys($response)) || !is_array($head)
                || array_key_exists('payment_details',$result) || !is_array($rows) || array_keys($rows) !== [0] || !is_array($line=$rows[0])) {
                throw new Problem('RECEIPT_RESPONSE_INCOMPLETE', '收款返回缺少独立的单头或唯一账户明细，结果待核对');
            }
            foreach (['bill_type'=>'RECEIPT','big_gsdm'=>$ledger,'company_id'=>$request['company_id'],
                         'supplier_cust_id'=>$request['customer_id'],'business_date'=>$expected['business_date']] as $key=>$value) {
                if (($head[$key] ?? null) !== $value) throw new Problem('RECEIPT_HEAD_MISMATCH', '收款回执的账套、单据类型、客户、公司或日期不匹配');
            }
            foreach (['creater_id','creater_name'] as $key) {
                if (array_key_exists($key,$head) && $head[$key] !== $expected[$key]) throw new Problem('RECEIPT_CREATER_MISMATCH','收款制单人与登记财务不匹配');
            }
            if (!in_array($head['scope_flag'] ?? null,[3,'3'],true) || ($line['account_id'] ?? null) !== $request['account_id']
                || self::money($line['amounts'] ?? null) !== $amount || self::money($line['handling_fee'] ?? 0) !== 0
                || array_key_exists('amounts',$head) && self::money($head['amounts']) !== $amount || !in_array($line['seq_no'] ?? null,[1,'1'],true)) {
                throw new Problem('RECEIPT_ACCOUNT_MISMATCH','收款回执的范围、资金账户、金额或明细不一致');
            }
            $id = self::id($head['id'] ?? null,36);
            $no = self::id($head['bill_id'] ?? null,32);
            self::id($line['id'] ?? null,36);
            return [
                'created'=>true,'receipt_registered'=>true,'verified'=>false,'external_no'=>$no,'external_id'=>$id,
                'amount'=>Contract::amount($amount),'provider_result'=>ReceiptFacts::head($head),
                'verification_level'=>'receipt_create_receipt_matched','allocation_applied_verified'=>false,'financial_posting_verified'=>false,
                'response'=>['head'=>array_intersect_key($head,array_flip(['id','bill_id','bill_type','big_gsdm','company_id','supplier_cust_id','business_date','scope_flag','creater_id'])),
                    'receipt_details'=>['add_details'=>[array_intersect_key($line,array_flip(['id','seq_no','account_id','amounts','handling_fee']))]]],
                'evidence'=>['environment'=>'uat','response_contract'=>'boyuan.receipt.v154.documented','receipt_service'=>self::SERVICE,
                    'company_id'=>$request['company_id'],'customer_id'=>$request['customer_id'],'account_id'=>$request['account_id'],
                    'amount'=>Contract::amount($amount),'checked_at'=>time()],
            ];
        } catch (Problem $e) {
            // A lost response or a response for another transaction must never trigger a second voucher.
            throw new Problem($e->reason,$e->getMessage().'；请核对原收款凭证，不要重复登记','unknown');
        } catch (\Throwable $e) {
            throw new Problem('RECEIPT_CREATE_UNKNOWN','未取得确定的博远收款回执，请核对原收款凭证，不要重复登记','unknown');
        }
    }

    private static function id($value,int $max): string
    {
        if (!is_string($value) || $value==='' || strlen($value)>$max || preg_match('/[[:cntrl:][:space:],]/',$value)) {
            throw new Problem('RECEIPT_IDENTITY_INVALID','缺少有效的博远档案编号或收款回执编号');
        }
        return $value;
    }

    private static function stringField($value,int $max,bool $required=false): void
    {
        if (!is_string($value) || strlen($value)>$max || ($required && trim($value)==='') || preg_match('/[[:cntrl:]]/',$value)) {
            throw new Problem('RECEIPT_FIELD_INVALID','请核对收款凭证的制单人、档案名称和备注');
        }
    }

    private static function money($value): int
    {
        if (is_float($value) && is_finite($value)) $value=json_encode($value,JSON_PRESERVE_ZERO_FRACTION|JSON_THROW_ON_ERROR);
        if (is_int($value)) $value=(string)$value;
        if (!is_string($value) || !preg_match('/^(0|[1-9]\d{0,9})(?:\.(\d{1,2})0*)?$/D',$value,$m)) {
            throw new Problem('RECEIPT_AMOUNT_INVALID','收款金额须为可精确核对的非负两位金额');
        }
        return (int)$m[1]*100+(int)str_pad($m[2] ?? '',2,'0');
    }

    /** The vendor also emits padded key names; reject collisions instead of guessing. */
    private static function keys(array $data): array
    {
        $out=[];
        foreach ($data as $key=>$value) {
            $key=is_string($key)?trim($key):$key;
            if (array_key_exists($key,$out)) throw new Problem('RECEIPT_RESPONSE_AMBIGUOUS','收款回执出现重复字段');
            $out[$key]=is_array($value)?self::keys($value):$value;
        }
        return $out;
    }
}
