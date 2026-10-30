<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;
use addon\hsx_boyuan\app\support\{Contract,Problem};

/** The vendor's v1.5.3 gateway. Only the documented reference reads are enabled. */
final class OpenApi implements Transport
{
    public const RESOURCES=[
        'companies'=>['公司门店','boyuan.companys.get'], 'categories'=>['分类','boyuan.categorys.get'],
        'brands'=>['品牌','boyuan.brands.get'], 'products'=>['商品型号','boyuan.products.get'],
        'warehouses'=>['仓库','boyuan.warehouses.get'], 'staff'=>['业务人员','boyuan.users.get'],
        'parties'=>['客户供应商','boyuan.custsuppliers.get'], 'members'=>['会员档案','boyuan.members.get'],
        'payment_methods'=>['收银方式','boyuan.cashinfos.get'], 'capital_accounts'=>['资金账户','boyuan.account.get'],
    ];
    public function __construct(private array $profile,private string $secret,private ?\Closure $exchange=null) {}
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
        if(!in_array($service,array_column(self::RESOURCES,1),true)) throw new Problem('READ_ONLY_ONLY','当前只开放基础资料查询，单据及支付接口未启用');
        if($this->secret==='' || empty($this->profile['app_id']) || empty($this->profile['account_id'])) throw new Problem('AUTH_INVALID','请先保存AppID、AppSecret和账套代码');
        $reserved=['service','app_id','timestamp','version','_input_charset','sign_type','sign','big_gsdm'];
        if(array_intersect(array_keys($params),$reserved)) throw new Problem('PARAM_INVALID','业务参数不能覆盖身份和签名参数');
        $params+=['service'=>$service,'app_id'=>$this->profile['app_id'],'big_gsdm'=>$this->profile['account_id'],
            'timestamp'=>(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d H:i:s'),
            'version'=>'1.0','_input_charset'=>'utf-8','sign_type'=>'MD5'];
        $params=array_filter($params,static fn($v)=>$v!==null && $v!=='');
        $params['sign']=self::sign($params,$this->secret);
        $path=$this->profile['gateway_path']??'/gateway.do';
        $reply=$this->exchange ? ($this->exchange)($path,$params) : (new HttpTransport($this->profile,''))->postForm($path,$params);
        $reply=Contract::redact($reply); $secret=$this->secret;
        array_walk_recursive($reply,static function(&$v) use($secret): void {if(is_string($v)) $v=str_replace($secret,'[已隐藏]',$v);});
        if(($reply['return_code']??'')!=='SUCCESS') {
            $code=(string)($reply['return_code']??'INVALID_RESPONSE');
            $code=preg_match('/^[A-Za-z0-9_]{1,64}$/D',$code)?$code:'REMOTE_ERROR';
            $message=mb_substr((string)($reply['return_msg']??'未返回有效的接口结果'),0,220);
            throw new Problem('OPENAPI_'.$code,'博远返回：'.$message);
        }
        if(!is_array($reply['api_response']??null)) throw new Problem('OPENAPI_RESPONSE_INVALID','博远业务响应不是有效对象');
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
        if($method!=='GET' || $path!==($this->profile['paths']['references']??'/reference-data')) throw new Problem('OPENAPI_WRITE_PENDING','当前仅支持博远基础资料读取，单据接口尚未接入');
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
        $items=[]; foreach($rows as $row) { if(!is_array($row)) throw new Problem('OPENAPI_RESPONSE_INVALID','基础资料行格式异常'); $items[]=self::normalize($resource,$row); }
        $total=$data['total_results']??null;
        if($total!==null && (!is_numeric($total) || (int)$total<0 || (string)(int)$total!==(string)$total)) throw new Problem('OPENAPI_RESPONSE_INVALID','基础资料总数格式异常');
        $hasMore=$resource!=='capital_accounts' && ($total!==null ? $page*$limit<(int)$total : count($rows)===$limit);
        if($hasMore && !$rows) throw new Problem('OPENAPI_PAGE_EMPTY','博远分页总数与空页矛盾，请重试核对');
        return ['code'=>'OK','data'=>['items'=>$items,'has_more'=>$hasMore,'next_cursor'=>$hasMore?(string)($page+1):null,'total'=>$total===null?null:(int)$total]];
    }
    public static function normalize(string $resource,array $row): array
    {
        // Documentation samples contain trailing spaces in keys; never alter identity values.
        $clean=[]; foreach($row as $key=>$value) { $key=trim((string)$key); if(array_key_exists($key,$clean)) throw new Problem('OPENAPI_RESPONSE_INVALID','资料字段重名，不能猜测身份'); $clean[$key]=$value; } $row=$clean;
        $id=$row['id']??($row['account_id']??null); Contract::id($id);
        $name=(string)($row['full_name']??$row['real_name']??$row['cash_name']??$row['name']??'');
        $nameMissing=trim($name)==='';
        if(mb_strlen($name)>255 || ($nameMissing && (int)($row['is_effective']??0)===1)) throw new Problem('ARCHIVE_NAME_INVALID','启用资料缺少有效名称，不能猜测档案');
        if($nameMissing) $name='（上游未提供名称）';
        $code=(string)($row['code']??($resource==='categories'?($row['category_code']??''):($resource==='staff'?($row['login_name']??''):($resource==='companies'?($row['gsdm']??''):($resource==='parties'?($row['spell_code']??''):'')))));
        if($code==='') $code=$id;
        $extra=array_intersect_key($row,array_flip(['memory','color','brand_id','brand_name','category_id','category_name','version_type_id','version_type_name','name','imei_count','imei_len','imei2_len','imei3_len','company_id','gsdm','company_name','parent_id','is_sales','is_buyer','is_cust','is_supplier','cash_type','inout_tag','account_id','account_name','account_type','warehouse_type_name','is_allow_sales']));
        $extra['status_known']=in_array($row['is_effective']??null,[0,1,'0','1'],true);
        if($nameMissing) $extra['source_name_missing']=true;
        $phone=(string)($row['mobile_phone']??$row['contact_phone']??'');
        return ['id'=>$id,'code'=>$code,'name'=>$name,'enabled'=>$extra['status_known'] && (int)$row['is_effective']===1,'extra'=>$extra,'phone'=>$phone];
    }
}
