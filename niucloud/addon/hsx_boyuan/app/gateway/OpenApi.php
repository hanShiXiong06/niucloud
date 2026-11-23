<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;
use addon\hsx_boyuan\app\support\{ArchiveView,Contract,InterfaceTrace,Problem};
use addon\hsx_boyuan\app\support\ProductionPurchasePilot as Pilot;

/** Signed vendor gateway. Generic calls remain restricted to reference reads. */
final class OpenApi implements Transport
{
    public const PURCHASE_DIALECT_DOCUMENTED='boyuan.purchase.v154.documented';
    public const PURCHASE_DIALECT_UAT='boyuan.purchase.v154.uat-query-v1';
    public const PURCHASE_DIALECT_PILOT_LINK='boyuan.purchase.v154.prod-create-line-fk-v1';
    private const RESERVED=['service','app_id','timestamp','version','_input_charset','sign_type','sign','big_gsdm'];
    public const RESOURCES=[
        'companies'=>['公司门店','boyuan.companys.get'], 'categories'=>['分类','boyuan.categorys.get'],
        'brands'=>['品牌','boyuan.brands.get'], 'products'=>['商品型号','boyuan.products.get'],
        'warehouses'=>['仓库','boyuan.warehouses.get'], 'staff'=>['业务人员','boyuan.users.get'],
        'parties'=>['客户供应商','boyuan.custsuppliers.get'], 'members'=>['会员档案','boyuan.members.get'],
        'payment_methods'=>['收银方式','boyuan.cashinfos.get'], 'capital_accounts'=>['资金账户','boyuan.account.get'],
    ];
    public function __construct(private array $profile,private string $secret,private ?\Closure $exchange=null,private string $mode='',private ?InterfaceTrace $trace=null) {}
    public function purchaseDialect(): string
    {
        return $this->canonicalUat() ? self::PURCHASE_DIALECT_UAT : self::PURCHASE_DIALECT_DOCUMENTED;
    }
    public static function sign(array $params,string $secret): string
    {
        unset($params['sign'],$params['sign_type']); $values=[];
        foreach($params as $key=>$value) {
            if($value===null || $value==='') continue;
            if(!is_scalar($value)) throw new Problem('SIGN_INVALID','签名参数必须为标量或已经编码的JSON字符串');
            $values[(string)$key]=is_bool($value)?($value?'1':'0'):(string)$value;
        }
        ksort($values,SORT_STRING); $parts=[];
        foreach($values as $key=>$value) $parts[]=$key.'='.$value;
        return md5(implode('&',$parts).$secret);
    }
    public function call(string $service,array $params=[]): array
    {
        if(!in_array($service,array_column(self::RESOURCES,1),true)) throw new Problem('READ_ONLY_ONLY','通用入口仅用于查基础资料；业务单据须走对应的核对流程');
        return $this->signedForm($service,$params);
    }
    public function accountId(): string { return Contract::id($this->profile['account_id']??null); }
    /** One existing staff record, not a paged directory import or a buyer-role override. */
    public function staffArchiveById(string $id,string $company): array
    {
        Contract::id($id); Contract::id($company);
        $data=self::fields($this->call('boyuan.users.get',['id'=>$id,'page_no'=>1,'page_size'=>1]));
        $rows=$data['result_list']??null;
        if(!in_array($data['total_results']??null,[1,'1'],true) || !is_array($rows) || array_keys($rows)!==[0] || !is_array($rows[0]))
            throw new Problem('STAFF_REFRESH_AMBIGUOUS','博远未返回唯一员工，保留原档案');
        $row=self::fields($rows[0]);
        if(($row['id']??null)!==$id || ($row['company_id']??null)!==$company ||
            (array_key_exists('big_gsdm',$row) && $row['big_gsdm']!==$this->accountId()))
            throw new Problem('STAFF_REFRESH_IDENTITY','博远返回的员工、所属公司或账套不匹配，保留原档案');
        if(!in_array($row['is_effective']??null,[0,'0',1,'1'],true) || !in_array($row['is_buyer']??null,[0,'0',1,'1'],true))
            throw new Problem('STAFF_REFRESH_INCOMPLETE','博远未明确返回员工状态或采购员资格，保留原档案');
        $item=self::normalize('staff',$row); Contract::id($item['code']);
        $item['archive_view']=ArchiveView::safe('staff',$row);
        return $item;
    }
    public function productionPilotReadback(): bool
    {
        return $this->mode==='production' && ($this->profile['endpoint']??null)===\addon\hsx_boyuan\app\support\ProductionVoucher::ENDPOINT
            && ($this->profile['gateway_path']??null)==='/gateway.do' && $this->accountId()===\addon\hsx_boyuan\app\support\ProductionPurchasePilot::ACCOUNT;
    }
    /** Beta: the documented WSA on-credit transaction only, in the exact UAT profile. */
    public function wholesaleCreditCreate(array $request): array
    {
        if (!$this->canonicalUat()) throw new Problem('SALE_UAT_ONLY','本版销售对接仅允许博远测试账套');
        $biz=WholesaleGateway::validated($request)['biz'];
        return $this->signedForm('boyuan.biz.bill.sale.generate',['biz'=>Contract::json($biz)],true);
    }
    /** No arbitrary service, no account overrides, and never a create during recovery. */
    public function wholesaleDetails(string $date,int $page,int $size): array
    {
        if (!$this->canonicalUat()) throw new Problem('SALE_UAT_ONLY','本版销售核对仅允许博远测试账套');
        $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$parsed || $parsed->format('Y-m-d')!==$date || $page<1 || $page>1000 || $size<1 || $size>1000) throw new Problem('SALE_QUERY_INVALID','批发明细核对的日期或分页无效');
        return $this->signedForm('boyuan.biz.sales.query',['business_date_start'=>$date,'business_date_end'=>$date,'page_no'=>$page,'page_size'=>$size]);
    }
    public function uatPaymentEnabled(): bool
    {
        return $this->canonicalUat();
    }
    public function uatReceiptEnabled(): bool
    {
        return $this->canonicalUat();
    }
    /** Accounting only: one receipt for an existing local collection, never a payment or allocation. */
    public function receiptCreate(array $request): array
    {
        if (!$this->uatReceiptEnabled()) throw new Problem('RECEIPT_UAT_ONLY','销售收款凭证仅允许测试连接，不会发送到正式账套');
        ReceiptGateway::validate($request);
        return $this->signedForm(ReceiptGateway::SERVICE,['biz'=>Contract::json($request['biz'])],true);
    }
    private function canonicalUat(): bool
    {
        return $this->mode==='test' && ($this->profile['endpoint']??'')==='https://open-api-uat.139erp.com/api'
            && ($this->profile['gateway_path']??'')==='/gateway.do';
    }
    public function productionVoucher(array $request): bool
    {
        return $this->mode==='production' && \addon\hsx_boyuan\app\support\ProductionVoucher::frozen($request,$this->profile);
    }
    /** A bare business payload is never sufficient to submit to the production gateway. */
    public function paymentCreate(array $biz): array
    {
        if(!$this->uatPaymentEnabled()) throw new Problem('PAYMENT_UAT_ONLY','付款单登记仅允许明确的UAT测试模式、地址和网关；生产及其他连接均禁止');
        return $this->sendVoucher($biz);
    }
    public function confirmedVoucherCreate(array $request): array
    {
        if(!$this->productionVoucher($request)) throw new Problem('VOUCHER_NOT_RELEASED','正式凭证未与已启用的原账套、采购及付款记录匹配');
        PaymentGateway::validate($request);
        return $this->sendVoucher($request['biz']);
    }
    private function sendVoucher(array $biz): array
    {
        $head=$biz['head']??null; $details=$biz['payment_details']??null;
        if(array_diff(array_keys($biz),['head','payment_details']) || !is_array($head) || !is_array($details)
            || !in_array($head['scope_flag']??null,[3,'3'],true) || !in_array($head['is_auto_generate_supplier']??0,[0,'0'],true)
            || !in_array($head['is_auto_generate_subject']??0,[0,'0'],true)) throw new Problem('PAYMENT_SCOPE_DENIED','仅支持所选公司范围的UAT付款登记，禁止自动创建供应商或科目');
        $check=function(array $value) use (&$check): void {
            if(array_intersect(array_keys($value),self::RESERVED)) throw new Problem('PARAM_INVALID','付款业务JSON不能覆盖身份和签名参数');
            foreach($value as $item) if(is_array($item)) $check($item);
        };
        $check($biz);
        return $this->signedForm('boyuan.biz.bill.payment.generate',['biz'=>Contract::json($biz)],true);
    }
    /** Only the v1.5.4 purchase-create service, with one signed JSON biz value. */
    public function purchasePilotCreate(array $request): array
    {
        $p=$this->profile; $biz=$request['biz']??[]; $rows=$biz['product_details']['add_details']??[];
        if($this->mode!=='production' || empty($p['probe_at']) || empty($p['contract_confirmed']) || ($p['protocol']??'')!=='openapi_v153' ||
            ($p['endpoint']??'')!==\addon\hsx_boyuan\app\support\ProductionVoucher::ENDPOINT ||
            ($p['gateway_path']??'')!=='/gateway.do' || $this->accountId()!==\addon\hsx_boyuan\app\support\ProductionPurchasePilot::ACCOUNT ||
            !is_int($request['pilot_bridge_id']??null) || $request['pilot_bridge_id']<=0 || !is_int($request['pilot_authorized_uid']??null) ||
            $request['pilot_authorized_uid']<=0 || empty($request['original_event_id']) || ($request['stage']??'')!=='frozen' ||
            ($biz['head']['remark']??'')!==\addon\hsx_boyuan\app\support\ProductionPurchasePilot::REMARK ||
            ($biz['head']['cash_term']??null)!==0 || ($biz['head']['is_auto_generate_supplier']??null)!==0 ||
            !is_array($rows) || array_keys($rows)!==[0] || Pilot::operation($rows[0]['imei']??null)===null ||
            ($rows[0]['qty']??0)!==1 || ($rows[0]['amounts']??'')!=='1.00' || ($rows[0]['unit_price']??'')!=='1.00')
            throw new Problem('PILOT_SCOPE','正式试单目标、审批、金额、串号或挂账方式不匹配，未发送');
        if(Pilot::pricerOperation(Pilot::operation($rows[0]['imei']))) {
            if(!Pilot::pricerIntent($request,$rows[0]['imei']) || ($request['pilot_pricer_ref']??null)!=='staff:1' || ($request['lines'][0]['imei']??null)!==$rows[0]['imei'] ||
                ($request['selection']['buyer_id']??null)!==Pilot::BUYER)
                throw new Problem('PILOT_SCOPE','指定采购与该回收单、定价人或串号授权不一致，未发送');
            Pilot::requireNewSelection($biz['head'],$rows[0],$request['selection']);
            try { $r=$this->call('boyuan.users.get',['id'=>Pilot::BUYER,'page_no'=>1,'page_size'=>1]); }
            catch(\Throwable $e) { throw new Problem('PILOT_BUYER_READ_UNAVAILABLE','未能从博远核对当前采购员资格；只有员工查询，没有发送采购单，不会自动重试'); }
            $rowsFound=$r['result_list']??null; $u=is_array($rowsFound)?($rowsFound[0]??null):null;
            if(!in_array($r['total_results']??null,[1,'1'],true) || !is_array($u) || array_keys($rowsFound)!==[0] ||
                ($u['id']??null)!==Pilot::BUYER || ($u['real_name']??null)!==Pilot::BUYER_NAME || ($u['company_id']??null)!==Pilot::COMPANY ||
                !in_array($u['is_effective']??null,[1,'1'],true) || !in_array($u['is_buyer']??null,[1,'1'],true))
                throw new Problem('PILOT_BUYER_NOT_ELIGIBLE','博远未确认韩世雄是本公司启用的采购业务员，未发送采购单。请在博远核对采购业务员资格。');
        } elseif(isset($request['pilot_operation_id']) && $request['pilot_operation_id']!==Pilot::OP) {
            throw new Problem('PILOT_SCOPE','原串号与试单操作不一致，未发送');
        }
        return $this->purchaseCreate($biz);
    }

    public function purchaseCreate(array $biz): array
    {
        if(array_diff(array_keys($biz),['head','product_details']) || !is_array($biz['head']??null) || !is_array($biz['product_details']??null)
            || !in_array($biz['head']['cash_term']??0,[0,'0'],true) || !in_array($biz['head']['is_auto_generate_supplier']??0,[0,'0'],true)) throw new Problem('PURCHASE_SCOPE_DENIED','采购创建仅支持非现结商品入库，不支持资金写入或自动生成供应商');
        $check=function(array $value) use (&$check): void {
            if(array_intersect(array_keys($value),self::RESERVED)) throw new Problem('PARAM_INVALID','采购业务JSON不能覆盖身份和签名参数');
            foreach($value as $item) if(is_array($item)) $check($item);
        };
        $check($biz);
        return $this->signedForm('boyuan.biz.bill.purchase.generate',['biz'=>Contract::json($biz)],true);
    }
    /** Deliberately not an arbitrary-service escape hatch. */
    public function purchaseQuery(string $kind,array $params): array
    {
        $services=['details'=>'boyuan.biz.purchases.query','imei'=>'boyuan.biz.purchase.imeidetails.query'];
        if(!isset($services[$kind])) throw new Problem('PURCHASE_QUERY_INVALID','只支持采购明细与入库串号明细核验');
        $allowed=['business_date_start','business_date_end','create_date_start','create_date_end','is_effective','page_no','page_size'];
        $allowed=array_merge($allowed,$kind==='details'?['modify_date_start','modify_date_end','trans_type']:['gsdm','bill_type','bill_id']);
        if(array_diff(array_keys($params),$allowed)) throw new Problem('PARAM_INVALID','采购核验包含未文档化的查询参数');
        return $this->signedForm($services[$kind],$params);
    }
    /** Documented inventory reads only; no arbitrary service or bill/payment query. */
    public function inventoryQuery(string $kind,array $params): array
    {
        $services=['aggregate'=>'boyuan.biz.inventories.query','imei'=>'boyuan.biz.inventory.imeidetails.query'];
        if(!isset($services[$kind])) throw new Problem('INVENTORY_QUERY_INVALID','只支持库存汇总和库存串号只读查询');
        $allowed=['query_date','gsdm','product_ids','category_codes','warehouse_ids','page_no','page_size'];
        $allowed=array_merge($allowed,$kind==='aggregate'?['has_imei','show_zero','group_type']:['imei','imei_in_stock_status']);
        if(array_diff(array_keys($params),$allowed)) throw new Problem('PARAM_INVALID','库存核验包含未文档化的参数或身份覆盖');
        $date=$params['query_date']??null;
        if(!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || !($parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date)) || $parsed->format('Y-m-d')!==$date) throw new Problem('INVENTORY_DATE_INVALID','库存查询必须提供有效query_date，不能使用样例中的query_date_start');
        return $this->signedForm($services[$kind],$params);
    }
    private function signedForm(string $service,array $params,bool $write=false): array
    {
        if($this->secret==='' || empty($this->profile['app_id']) || empty($this->profile['account_id'])) throw new Problem('AUTH_INVALID','请先保存AppID、AppSecret和账套代码');
        if(array_intersect(array_keys($params),self::RESERVED)) throw new Problem('PARAM_INVALID','业务参数不能覆盖身份和签名参数');
        $params+=['service'=>$service,'app_id'=>$this->profile['app_id'],'big_gsdm'=>$this->profile['account_id'],
            'timestamp'=>(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d H:i:s'),
            'version'=>'1.0','_input_charset'=>'utf-8','sign_type'=>'MD5'];
        $params=array_filter($params,static fn($v)=>$v!==null && $v!=='');
        $params['sign']=self::sign($params,$this->secret);
        $path=$this->profile['gateway_path']??'/gateway.do';
        try { $reply=$this->exchange ? ($this->exchange)($path,$params) : (new HttpTransport($this->profile,'',$this->trace,$this->secret))->postForm($path,$params); }
        catch(Problem $e) { throw $e; }
        catch(\Throwable $e) {
            if(in_array($service,array_column(self::RESOURCES,1),true)) throw new Problem('REFERENCE_UNAVAILABLE','未能读取博远资料。本站保留已取回的数据，可稍后再次同步。');
            throw new Problem('OPENAPI_EXCHANGE_UNKNOWN','博远请求未取得确定结果，请核对原单，不得重复创建','unknown');
        }
        if(!is_array($reply)) throw new Problem('OPENAPI_RESPONSE_INVALID','博远响应不是有效对象',$write?'unknown':'none');
        // Business normalization still needs the raw phone to create its one-way lookup hash.
        // PII masking belongs at persistence/public/log boundaries, not inside this transport.
        $reply=Contract::redactCredentials($reply); $secret=$this->secret;
        array_walk_recursive($reply,static function(&$v) use($secret): void {if(is_string($v)) $v=str_replace($secret,'[已隐藏]',$v);});
        if(($reply['return_code']??'')!=='SUCCESS') {
            $code=(string)($reply['return_code']??'INVALID_RESPONSE');
            $code=preg_match('/^[A-Za-z0-9_]{1,64}$/D',$code)?$code:'REMOTE_ERROR';
            $message=mb_substr((string)($reply['return_msg']??'未返回有效的接口结果'),0,220);
            throw new Problem('OPENAPI_'.$code,'博远返回：'.$message,$write?'unknown':'none');
        }
        if(!is_array($reply['api_response']??null)) throw new Problem('OPENAPI_RESPONSE_INVALID','博远业务响应不是有效对象',$write?'unknown':'none');
        return $reply['api_response'];
    }
    public function verify(): array
    {
        $data=$this->call('boyuan.companys.get',['gsdm'=>$this->profile['account_id'],'contain_sub'=>0,'page_no'=>1,'page_size'=>100]);
        foreach($data['result_list']??[] as $row) if((string)($row['gsdm']??'')===$this->profile['account_id'] && (int)($row['is_effective']??0)===1) return ['code'=>'OK','data'=>['account_id'=>$this->profile['account_id'],'company_name'=>(string)($row['name']??'')]];
        throw new Problem('ACCOUNT_UNVERIFIED','没有返回匹配且启用的账套公司，未通过身份核验');
    }
    public function send(string $method,string $path,array $body=[]): array
    {
        if($method!=='GET' || $path!==($this->profile['paths']['references']??'/reference-data')) throw new Problem('OPENAPI_WRITE_PENDING','通用入口仅用于查基础资料；业务单据须走对应的核对流程');
        $resource=(string)($body['resource']??'warehouses');
        if(!isset(self::RESOURCES[$resource])) throw new Problem('RESOURCE_INVALID','不支持的基础资料类型');
        $cursor=(string)($body['cursor']??'1'); if($cursor==='') $cursor='1';
        if(!preg_match('/^[1-9][0-9]{0,5}$/D',$cursor)) throw new Problem('PAGE_INVALID','分页编号无效');
        $page=(int)$cursor; $limit=min(100,max(1,(int)($body['limit']??100)));
        $params=['page_no'=>$page,'page_size'=>$limit];
        if($resource==='members') $params+=['register_date_start'=>$body['since']??$this->profile['member_since']??'2000-01-01',
            'register_date_end'=>$body['until']??(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d')];
        // These endpoints document pagination; account.get documents no pagination.
        if($resource==='capital_accounts') $params=[];
        $data=$this->call(self::RESOURCES[$resource][1],$params);
        $rows=$data['result_list']??null;
        if(!is_array($rows) || !array_is_list($rows)) throw new Problem('OPENAPI_RESPONSE_INVALID','博远没有返回有效的资料列表');
        $field=$body['party_phone_field']??$this->profile['party_phone_field']??'contact_phone';
        $items=[]; foreach($rows as $row) {
            if(!is_array($row)) throw new Problem('OPENAPI_RESPONSE_INVALID','基础资料行格式异常');
            $item=self::normalize($resource,$row,$field);
            if(!empty($body['include_archive_view'])) $item['archive_view']=ArchiveView::safe($resource,self::fields($row));
            $items[]=$item;
        }
        $total=$data['total_results']??null;
        if($total!==null && (!is_numeric($total) || (int)$total<0 || (string)(int)$total!==(string)$total)) throw new Problem('OPENAPI_RESPONSE_INVALID','基础资料总数格式异常');
        $hasMore=$resource!=='capital_accounts' && ($total!==null ? $page*$limit<(int)$total : count($rows)===$limit);
        if($hasMore && !$rows) throw new Problem('OPENAPI_PAGE_EMPTY','博远分页总数与空页矛盾，请重试核对');
        return ['code'=>'OK','data'=>['items'=>$items,'has_more'=>$hasMore,'next_cursor'=>$hasMore?(string)($page+1):null,'total'=>$total===null?null:(int)$total]];
    }
    public function archiveFieldsAt(string $kind,string $id,int $page,array $scope,bool $withPartyPhone=false):array {
        $row=$this->sourceRow($kind,$id,$page,$scope);
        $fields=ArchiveView::safe($kind,$row);
        if($withPartyPhone && $kind==='parties' && array_key_exists('help_code',$row)) $fields['help_code']=self::originalHelpCode($row);
        return $fields;
    }
    private static function originalHelpCode(array $row): ?string {
        $value=$row['help_code']??null;
        return is_string($value)||is_int($value)?(string)$value:null;
    }
    private function sourceRow(string $kind,string $id,int $page,array $scope):array
    {
        Contract::id($id);
        if(!isset(self::RESOURCES[$kind]) || $page<1 || $page>1000) throw new Problem('PAGE_INVALID','该档案没有可核对的来源页');
        $params=$kind==='capital_accounts'?[]:['page_no'=>$page,'page_size'=>100];
        if($kind==='members') {
            if(empty($scope['since']) || empty($scope['until'])) throw new Problem('ARCHIVE_SCOPE','缺少原会员同步的日期范围，请重新读取本类');
            $params+=['register_date_start'=>$scope['since'],'register_date_end'=>$scope['until']];
        }
        $rows=$this->call(self::RESOURCES[$kind][1],$params)['result_list']??null;
        if(!is_array($rows)||!array_is_list($rows)||($kind!=='capital_accounts'&&count($rows)>100))throw new Problem('OPENAPI_RESPONSE_INVALID','博远未返回有效的档案列表');
        $match=null;foreach($rows as $row){
            if(!is_array($row)) throw new Problem('OPENAPI_RESPONSE_INVALID','博远档案格式异常');
            $row=self::fields($row);
            if(($row['id']??$row['account_id']??null)!==$id)continue;
            if($match!==null)throw new Problem('ARCHIVE_ID_DUPLICATE','博远返回重复档案编号，未展示其他记录');
            $match=$row;
        }
        if($match===null) throw new Problem('ARCHIVE_SOURCE_MOVED','在原页找不到同一编号。可查看已保存摘要，或重新读取本类档案。');
        return $match;
    }
    private static function fields(array $row): array
    {
        $clean=[]; foreach($row as $key=>$value) { $key=trim((string)$key); if(array_key_exists($key,$clean)) throw new Problem('OPENAPI_RESPONSE_INVALID','资料字段重名，不能猜测身份'); $clean[$key]=$value; }
        return $clean;
    }
    public static function normalize(string $resource,array $row,string $partyPhoneField='contact_phone'): array
    {
        // Documentation samples contain trailing spaces in keys; never alter identity values.
        $row=self::fields($row);
        $id=$row['id']??($row['account_id']??null); Contract::id($id);
        $name=(string)($row['full_name']??$row['real_name']??$row['cash_name']??$row['name']??'');
        $nameMissing=trim($name)==='';
        if(mb_strlen($name)>255 || ($nameMissing && (int)($row['is_effective']??0)===1)) throw new Problem('ARCHIVE_NAME_INVALID','启用资料缺少有效名称，不能猜测档案');
        if($nameMissing) $name='（上游未提供名称）';
        $code=(string)($row['code']??($resource==='categories'?($row['category_code']??''):($resource==='staff'?($row['login_name']??''):($resource==='companies'?($row['gsdm']??''):($resource==='parties'?($row['spell_code']??''):'')))));
        if($code==='') $code=$id;
        $extra=array_intersect_key($row,array_flip(['memory','color','brand_id','brand_name','category_id','category_name','version_type_id','version_type_name','name','imei_count','imei_len','imei2_len','imei3_len','company_id','gsdm','company_name','parent_id','is_sales','is_buyer','is_cust','is_supplier','cash_type','inout_tag','account_id','account_name','account_type','warehouse_type_name','is_allow_sales']));
        $extra+=array_intersect_key($row,array_flip(\addon\hsx_boyuan\app\support\CatalogStore::sourceKeys($resource)));
        $extra['status_known']=in_array($row['is_effective']??null,[0,1,'0','1'],true);
        if($nameMissing) $extra['source_name_missing']=true;
        $phone=(string)($row['mobile_phone']??$row['contact_phone']??'');
        if($resource==='parties') {
            if(!in_array($partyPhoneField,['help_code','contact_phone'],true)) throw new Problem('PARTY_PHONE_FIELD','电话来源已变更，请重新读取客户与供应商');
            $extra['party_phone_field']=$partyPhoneField;
            if($partyPhoneField==='help_code') {
                $value=$row['help_code']??null;
                // The exact business value and the normalized phone matching key serve different purposes.
                $extra[ArchiveView::PARTY_PHONE]=self::originalHelpCode($row);
                $literal=is_string($value)||is_int($value)?trim((string)$value):'';
                $digits=preg_replace('/[^0-9]/','',$literal);
                $valid=preg_match('/^\+?[0-9 ()-]+$/D',$literal) && strlen($digits)>=6 && strlen($digits)<=20;
                $phone=$valid?$digits:'';
                $extra['party_phone_state']=$valid?'available':(($value===null || $literal==='' && is_string($value))?'empty':'check');
                // An agreed phone is not a business identifier and must not leak through code/search lists.
                $code=trim((string)($row['code']??''));
                if($code==='') {
                    $ordinary=$row['spell_code']??null; // The provider's ordinary party code, not a phone source.
                    if(is_string($ordinary) && strlen($ordinary)<=128 && !preg_match('/[\x00-\x20]/',$ordinary)
                        && !preg_match('/^(?:0086|86)?1[3-9][0-9]{9}$/D',str_replace(['+','(',')','-'],'',$ordinary))) $code=$ordinary;
                }
                if($code==='' || $code===$literal || ($phone!=='' && preg_replace('/[^0-9]/','',$code)===$phone)) $code=$id;
            }
        }
        return ['id'=>$id,'code'=>$code,'name'=>$name,'enabled'=>$extra['status_known'] && (int)$row['is_effective']===1,'extra'=>$extra,'phone'=>$phone];
    }
}
