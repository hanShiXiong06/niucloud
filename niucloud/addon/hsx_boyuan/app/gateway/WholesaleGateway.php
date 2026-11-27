<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;

use addon\hsx_boyuan\app\support\{Contract,Problem};

/** Official v1.5.4 §§4.2.13, 4.2.61.1 and 5.8. Only single-warehouse, on-credit wholesale. */
final class WholesaleGateway
{
    public const SCHEMA='boyuan.wholesale.v154';
    private const PAGE_SIZE=100;
    public function __construct(private OpenApi $api) {}

    public static function freeze(string $manualNumber,string $businessDate,string $customerId,string $warehouseId,array $lines,string $creatorId,string $creatorName): array
    {
        return self::build($manualNumber,$businessDate,$customerId,$warehouseId,$lines,['creater_id'=>$creatorId,'creater_name'=>$creatorName]);
    }

    private static function build(string $manualNumber,string $businessDate,string $customerId,string $warehouseId,array $lines,?array $creator): array
    {
        $manual=self::id($manualNumber,64); $customer=self::id($customerId,36); $warehouse=self::id($warehouseId,36);
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$businessDate);
        if (!$date || $date->format('Y-m-d')!==$businessDate) throw new Problem('SALE_DATE_INVALID','请选择有效的销售出库日期');
        if (!$lines || count($lines)>100 || array_keys($lines)!==range(0,count($lines)-1)) throw new Problem('SALE_LINES_INVALID','单张出库单须有 1 至 100 台设备');
        $seen=[]; $manifest=[]; $details=[];
        foreach ($lines as $i=>$line) {
            if (!is_array($line)) throw new Problem('SALE_LINES_INVALID','销售设备行不完整');
            $row=[];
            foreach (['source_line_id'=>128,'device_ref'=>128,'imei'=>64,'product_id'=>36] as $field=>$max) $row[$field]=self::id($line[$field]??null,$max);
            foreach (['source_line_id','device_ref','imei'] as $field) {
                if (isset($seen[$field][$row[$field]])) throw new Problem('SALE_LINES_DUPLICATE','销售设备、串号和来源明细不能重复');
                $seen[$field][$row[$field]]=true;
            }
            if ((isset($line['warehouse_id']) && $line['warehouse_id']!==$warehouse) ||
                (isset($line['qty']) && !in_array($line['qty'],[1,'1'],true)) ||
                (isset($line['quantity']) && !in_array($line['quantity'],[1,'1'],true))) throw new Problem('SALE_LINES_INVALID','只支持同仓库、一机一行的销售出库');
            Contract::cents($line['amount']??null);
            $row['amount']=$line['amount'];
            $manifest[]=$row;
            $details[]=['product_id'=>$row['product_id'],'imei'=>$row['imei'],'qty'=>1,'unit_price'=>$row['amount'],'amounts'=>$row['amount'],'seq_no'=>$i+1];
        }
        $head=['manual_number'=>$manual,'business_date'=>$businessDate,'cust_id'=>$customer,'warehouse_id'=>$warehouse,'cash_term'=>0,'is_auto_generate_cust'=>0];
        if ($creator!==null) {
            try {$id=self::id($creator['creater_id']??null,36);}
            catch(Problem $e){throw new Problem('SALE_CREATOR_INVALID','请选择当前账套已启用的博远制单人，再提交出库');}
            $name=$creator['creater_name']??null;
            if(!is_string($name)||trim($name)===''||strlen($name)>64||preg_match('/[\x00-\x1f\x7f]/',$name))throw new Problem('SALE_CREATOR_INVALID','请在当前账套选择有效的博远制单人，再提交出库');
            $head+=['creater_id'=>$id,'creater_name'=>$name];
        }
        return ['schema'=>self::SCHEMA,'biz'=>['head'=>$head,'product_details'=>['add_details'=>$details]],'lines'=>$manifest];
    }

    /** Rebuild only the allow-listed signed fields. Never silently strip a changed payment mode. */
    public static function validated(array $request,bool $readOriginal=false): array
    {
        if (($request['schema']??null)!==self::SCHEMA || !is_array($request['lines']??null) || !is_array($request['biz']['head']??null)) throw new Problem('SALE_REQUEST_INVALID','挂账批发请求不完整');
        $head=$request['biz']['head']; $creator=null;
        if(array_key_exists('creater_id',$head)||array_key_exists('creater_name',$head))$creator=['creater_id'=>$head['creater_id']??null,'creater_name'=>$head['creater_name']??null];
        if(!$readOriginal && $creator===null)throw new Problem('SALE_CREATOR_REQUIRED','销售出库缺少博远制单人，未提交博远');
        $rebuilt=self::build(self::id($head['manual_number']??null,64),(string)($head['business_date']??''),self::id($head['cust_id']??null,36),self::id($head['warehouse_id']??null,36),$request['lines'],$creator);
        if ($rebuilt['biz']!==$request['biz'] || $rebuilt['lines']!==$request['lines']) throw new Problem('SALE_SCOPE_DENIED','冻结的商品、串号、金额或挂账条件已改变；未提交博远');
        return $rebuilt;
    }

    /** The caller durably marks the attempt before invoking this method, then stores this receipt before verification. */
    public function create(array $request): array
    {
        $request=self::validated($request); $head=$request['biz']['head'];
        try {
            $reply=$this->api->wholesaleCreditCreate($request);
            $result=$reply['result']??null; $actual=$result['head']??null;
            if (!is_array($actual) || isset($reply['head'])) self::uncertain('缺少原始批发单头');
            foreach (['big_gsdm'=>$this->api->accountId(),'bill_type'=>'WSA','manual_number'=>$head['manual_number'],'business_date'=>$head['business_date'],'cust_id'=>$head['cust_id'],'warehouse_id'=>$head['warehouse_id']] as $key=>$value) {
                if (($actual[$key]??null)!==$value) self::uncertain('创建回执与本次挂账销售的账套、单号、客户或仓库不一致');
            }
            if (isset($actual['creater_id']) && $actual['creater_id']!==$head['creater_id']) self::uncertain('创建回执制单人与冻结选择不一致');
            if (!in_array($actual['cash_term']??null,[0,'0'],true) || !empty($result['sale_cash_details']['add_details'])) self::uncertain('创建回执不是纯挂账销售');
            $no=self::id($actual['bill_id']??null,32); $id=self::id($actual['id']??null,36);
            $rows=$result['product_details']['add_details']??null;
            if (!is_array($rows) || count($rows)!==count($request['lines'])) self::uncertain('创建回执的设备数量不符');
            $expected=array_column($request['lines'],null,'imei'); $mapped=[]; $ids=[];
            foreach ($rows as $row) {
                if (!is_array($row)) self::uncertain('创建回执明细无效');
                $imei=self::id($row['imei']??null,64); $lineId=self::id($row['id']??null,36);
                if (!isset($expected[$imei]) || isset($mapped[$imei]) || isset($ids[$lineId])) self::uncertain('创建回执串号或明细编号重复或不匹配');
                self::product($row,$expected[$imei]);
                $mapped[$imei]=['source_line_id'=>$expected[$imei]['source_line_id'],'device_ref'=>$expected[$imei]['device_ref'],'imei'=>$imei,'external_line_id'=>$lineId];
                $ids[$lineId]=true;
            }
            return ['created'=>true,'external_no'=>$no,'external_id'=>$id,'lines'=>array_values($mapped),'provider_result'=>\addon\hsx_boyuan\app\support\ReceiptFacts::head($actual)];
        } catch(Problem $e) { throw new Problem($e->reason,$e->getMessage(),$e->reason==='SALE_UAT_ONLY'?'none':'unknown'); }
        catch(\Throwable $e) { self::uncertain('创建请求未取得完整回执'); }
    }

    /** After a write timeout we can locate a candidate by manual_number, not reconstruct its missing serial receipt. */
    public function locate(array $request): array
    {
        $company=(string)($request['company_id']??''); // frozen outside the signed head by the local purchase trace
        $request=self::validated($request,true);
        $head=$request['biz']['head']; $missing=0; $rows=$this->details($head,null,$missing);
        $candidates=[]; $conflicts=[]; $hadConflict=false;
        foreach ($rows as $row) {
            // This is only a clue for support. An exact manual number is mandatory; a matching
            // serial/date/customer/amount on another sale must never become a candidate.
            $no=self::id($row['bill_id']??null,32);
            $id=isset($row['head_id']) && $row['head_id']!==''?self::id($row['head_id'],36):null;
            if (($row['manual_number']??null)!==$head['manual_number'] || ($row['bill_type']??null)!=='WSA' ||
                ($row['business_date']??null)!==$head['business_date'] || ($row['cust_id']??null)!==$head['cust_id'] ||
                ($row['warehouse_id']??null)!==$head['warehouse_id'] || ($company!=='' && ($row['company_id']??null)!==$company) ||
                (isset($row['big_gsdm']) && $row['big_gsdm']!==$this->api->accountId()) ||
                !in_array($row['cash_term']??null,[0,'0'],true) || !in_array($row['is_effective']??null,[1,'1'],true) ||
                (isset($candidates[$no]['external_id']) && $id!==null && $candidates[$no]['external_id']!==$id)) {
                $conflicts[$no]=true; $hadConflict=true; continue;
            }
            $matched=false;
            foreach ($request['lines'] as $line) {
                if ((isset($row['imei']) && $row['imei']!=='' && $row['imei']!==$line['imei']) ||
                    (array_key_exists('imeis',$row) && $row['imeis']!==[] && $row['imeis']!==[$line['imei']]) ||
                    (isset($row['imei_qty']) && !in_array($row['imei_qty'],[1,'1'],true))) continue;
                try { self::product($row,$line); $matched=true; break; } catch(Problem $ignored) {}
            }
            if (!$matched) { $conflicts[$no]=true; $hadConflict=true; continue; }
            // UAT can return bill_id without head_id. Never manufacture a UUID from the row ID.
            $candidates[$no]=['external_no'=>$no]+($id!==null?['external_id'=>$id]:($candidates[$no]??[]));
        }
        $candidates=array_values(array_diff_key($candidates,$conflicts));
        return ['verified'=>false,'verification_level'=>'candidate_only','outcome'=>$candidates?'matched_candidates':($missing || $hadConflict?'unidentifiable':'no_match'),
            'candidate_count'=>count($candidates),'missing_manual_number_rows'=>$missing,'queried_at'=>time(),'candidates'=>$candidates,'can_resend'=>false];
    }

    /** Join original per-device creation receipts to independently read sales rows, never by newest record or product alone. */
    public function verify(array $request,array $receipt): array
    {
        $request=self::validated($request,true); $head=$request['biz']['head']; $lines=$request['lines'];
        $no=self::id($receipt['external_no']??null,32); $id=self::id($receipt['external_id']??null,36);
        if (($receipt['created']??null)!==true || !is_array($receipt['lines']??null) || count($receipt['lines'])!==count($lines)) self::uncertain('缺少原始单据的逐台串号回执，只能核对候选单号');
        $expected=array_column($lines,null,'imei'); $byId=[]; $serials=[];
        foreach ($receipt['lines'] as $row) {
            $imei=self::id($row['imei']??null,64); $lineId=self::id($row['external_line_id']??null,36); $line=$expected[$imei]??null;
            if (!$line || isset($byId[$lineId]) || isset($serials[$imei]) || ($row['source_line_id']??null)!==$line['source_line_id'] || ($row['device_ref']??null)!==$line['device_ref']) self::uncertain('持久化的原始设备回执不匹配');
            $byId[$lineId]=$line; $serials[$imei]=true;
        }
        $rows=$this->details($head,$no); $company=null; $seen=[]; $allSerialsEchoed=true;
        if (count($rows)!==count($lines)) self::uncertain('未找到完整且唯一的批发明细');
        foreach ($rows as $row) {
            foreach (['bill_id'=>$no,'bill_type'=>'WSA','business_date'=>$head['business_date'],'warehouse_id'=>$head['warehouse_id'],'cust_id'=>$head['cust_id']] as $key=>$value) if (($row[$key]??null)!==$value) self::uncertain('查询的批发单号、日期、仓库或客户与原单不一致');
            $hasHead=array_key_exists('head_id',$row);$hasManual=array_key_exists('manual_number',$row);$documented=$hasHead&&$hasManual;
            if (($hasHead && $row['head_id']!==$id) || ($hasManual && $row['manual_number']!==$head['manual_number'])) self::uncertain('查询的单头或手工编号与原单不一致');
            // Observed UAT WSA on 2026-11-18: independently queried row contains our exact manual number,
            // original bill and row UUID, but no head_id, and reports imei_qty=1 without echoing imeis.
            // This joins the explicitly serial-bound create receipt, not a serial found in the read response.
            $receiptJoin=!$hasHead && $hasManual && ($row['imeis']??null)===[] && in_array($row['imei_qty']??null,[1,'1'],true);
            if ($hasHead!==$hasManual && !$receiptJoin) self::uncertain('查询的批发明细缺少可核对的原单身份或串号数量');
            if (!in_array($row['cash_term']??null,[0,'0'],true) || !in_array($row['is_effective']??null,[1,'1'],true)) self::uncertain('批发明细无效或不是挂账业务');
            $companyId=self::id($row['company_id']??null,36);
            if ($company!==null && $company!==$companyId) self::uncertain('批发单号跨多个门店');
            $company=$companyId; $lineId=self::id($row['id']??null,36);
            if (isset($seen[$lineId]) || !isset($byId[$lineId])) self::uncertain('查询的批发行号无法关联到原始设备');
            if ($receiptJoin && (!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD',$lineId) || !preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD',$id))) self::uncertain('缺少可关联的博远原单及逐台明细 UUID');
            self::product($row,$byId[$lineId]);
            if (isset($row['imei']) && $row['imei']!==$byId[$lineId]['imei']) self::uncertain('批发明细与原始串号回执矛盾');
            // Without either header identifier, demand a full independent serial echo in addition to
            // the original bill and line ID. Never turn a contradictory or multi-serial response into a match.
            if (!$receiptJoin && (!$documented || array_key_exists('imeis',$row))) {
                if (($row['imeis']??null)!==[$byId[$lineId]['imei']]) self::uncertain('批发明细的逐台串号清单缺失、多台或与来源设备不符');
            }
            if (array_key_exists('imei_qty',$row) && !in_array($row['imei_qty'],[1,'1'],true)) self::uncertain('批发明细串号数量与本次逐台出库不符');
            if (($row['imei']??null)!==$byId[$lineId]['imei'] && ($row['imeis']??null)!==[$byId[$lineId]['imei']]) $allSerialsEchoed=false;
            $seen[$lineId]=true;
        }
        return ['verified'=>true,'external_no'=>$no,'external_id'=>$id,'lines'=>$receipt['lines'],'verification_level'=>'wholesale_records_matched',
            'serial_evidence'=>$allSerialsEchoed?'echoed_by_sales_query':'linked_via_original_receipt','query_serials_echoed'=>$allSerialsEchoed,
            'stock_balance_verified'=>false,'financial_posting_verified'=>false,'company_id'=>$company];
    }

    /** Read all bounded pages on that business date: WSA query has no documented bill_id request filter. */
    private function details(array $head,?string $knownNo=null,?int &$missing=null): array
    {
        $selected=[]; $seen=[]; $total=null;
        for ($page=1;$page<=1000;$page++) {
            $result=$this->api->wholesaleDetails($head['business_date'],$page,self::PAGE_SIZE);
            $count=$result['total_results']??null;
            if (!is_int($count) && (!is_string($count) || !ctype_digit($count))) self::uncertain('查询未返回总数');
            $count=(int)$count; $rows=$result['result_list']??null;
            if ($count<0 || $count>100000 || ($total!==null && $total!==$count) || !is_array($rows) || count($rows)!==min(self::PAGE_SIZE,$count-count($seen))) self::uncertain('查询分页不完整或数据已变动');
            $total=$count;
            foreach ($rows as $row) {
                if (!is_array($row)) self::uncertain('查询返回无效商品行');
                $key=self::id($row['id']??null,36);
                if (isset($seen[$key])) self::uncertain('查询分页包含重复商品行');
                $seen[$key]=true;
                // Only count otherwise in-scope rows; never disclose third-party bill numbers or customer data.
                if ($missing!==null && ($row['bill_type']??null)==='WSA' && ($row['business_date']??null)===$head['business_date']
                    && ($row['cust_id']??null)===$head['cust_id'] && ($row['warehouse_id']??null)===$head['warehouse_id']
                    && (!isset($row['manual_number']) || $row['manual_number']==='')) $missing++;
                if (($row['manual_number']??null)===$head['manual_number'] || ($knownNo!==null && ($row['bill_id']??null)===$knownNo)) $selected[]=$row;
            }
            if (count($seen)===$total) return $selected;
        }
        self::uncertain('查询结果超过本次核对范围');
    }
    private static function product(array $row,array $expected): void
    {
        if (($row['product_id']??null)!==$expected['product_id'] || !in_array($row['qty']??null,[1,'1',1.0],true)) self::uncertain('商品或数量与原始设备不一致');
        foreach (['amounts','unit_price'] as $key) {
            $value=$row[$key]??null;
            if (is_float($value) && is_finite($value)) $value=json_encode($value,JSON_PRESERVE_ZERO_FRACTION);
            if (is_int($value)) $value=(string)$value;
            if (!is_string($value) || !preg_match('/^(0|[1-9]\d{0,9})(?:\.(\d{1,2})0*)?$/D',$value,$m) || ($m[1].'.'.str_pad($m[2]??'',2,'0'))!==$expected['amount']) self::uncertain('逐台销售金额与原始设备不一致');
        }
    }
    private static function id($value,int $length): string
    {
        if (!is_string($value) || $value==='' || strlen($value)>$length || preg_match('/[\x00-\x20,|]/',$value)) throw new Problem('SALE_ID_INVALID','缺少有效的销售单据、档案或设备编号');
        return $value;
    }
    private static function uncertain(string $reason): void
    {
        throw new Problem('SALE_RESULT_UNVERIFIED',$reason.'；请核对博远原单，不要重复出库','unknown');
    }
}
