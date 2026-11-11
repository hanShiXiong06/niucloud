<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

use addon\hsx_lead\app\support\PhpRuntime;

/** SMS-authorized account, then bounded current-stock reads. No source writes. */
final class JkbProvider extends AbstractProvider implements CredentialChallengeProviderContract
{
    public const SMS_ENDPOINT='https://api.yuanyoukeji.com/api/sms/send';
    public const LOGIN_ENDPOINT='https://api.yuanyoukeji.com/api/user/mobile_register_login';
    public const STOCK_ENDPOINT='https://api.yuanyoukeji.com/api/product_model/productStockList';
    private const AUTH_VERSION='jkb.sms.v1';
    private const VERSION='1798';
    private const LISTED_STATUS=1;
    private const SNAPSHOT_KEYS=['id','admin_id','brand_name','product_name','product_model','num','quality','storage','color','status',
        'inventory_status','serial_number','product_url','chu_price','pfs_price','cell_effciency','stock_time'];

    public function key(): string{return 'jkb';}

    public function beginAuthorization(): array
    {
        throw new ProviderException('invalid_credentials','请先填写授权手机号，再主动获取短信验证码');
    }

    public function beginAuthorizationFor(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace=PhpRuntime::guardSecrets();$mobile=$this->mobile($credentials);
        $response=$this->transport->post(self::SMS_ENDPOINT,$this->headers(),['event'=>'register_login','mobile'=>$mobile]);
        $this->success($response,'sms');
        return ['public'=>['expires_in'=>180,'retry_after'=>60],
            'state'=>['version'=>self::AUTH_VERSION,'stage'=>'sms_code','mobile'=>$mobile,'device_id'=>strtoupper(bin2hex(random_bytes(16)))]];
    }

    public function authorize(#[\SensitiveParameter] array $credentials,#[\SensitiveParameter] array $state,#[\SensitiveParameter] array $answer): array
    {
        $secretTrace=PhpRuntime::guardSecrets();$mobile=$this->mobile($credentials);$code=$answer['code']??null;
        if(($state['version']??null)!==self::AUTH_VERSION||($state['stage']??null)!=='sms_code'||($state['mobile']??null)!==$mobile
            ||!is_string($state['device_id']??null)||!preg_match('/^[A-F0-9]{32}$/D',$state['device_id']))
            throw new ProviderException('invalid_challenge','短信授权上下文已变化，请重新获取验证码');
        if(!is_string($code)||!preg_match('/^[0-9]{4}$/D',$code))throw new ProviderException('invalid_captcha','请输入收到的4位短信验证码');
        $response=$this->transport->post(self::LOGIN_ENDPOINT,$this->headers(),[
            'captcha'=>$code,'mobile'=>$mobile,'event'=>'register_login','source'=>1,
            'wx_info'=>['openid'=>'','nickname'=>'','unionid'=>'','headimgurl'=>'','sex'=>'','city'=>''],
            // Do not reuse the captured person's push-registration or device ID.
            'registration_id'=>'','device_id'=>$state['device_id'],'device_name'=>'Merchant inventory connection',
        ]);
        $this->success($response,'login');$user=$response['data']??null;
        if(!is_array($user)||PhpRuntime::isList($user)||($user['mobile']??null)!==$mobile)
            throw new ProviderException('scope_mismatch','登录返回的账号与授权手机号不一致，未保存连接');
        $owner=$this->positiveId($user['id']??null);$merchant=$user['merchant']??null;
        if(!is_array($merchant)||PhpRuntime::isList($merchant)||$this->positiveId($user['user_id']??null)!==$owner
            ||$this->positiveId($merchant['user_id']??null)!==$owner||!in_array($merchant['is_delete']??null,[0,'0'],true))
            throw new ProviderException('schema_changed','登录没有返回一致的账号和有效门店身份，未保存连接');
        $saved=['username'=>$mobile,'access_token'=>$this->accessToken(['access_token'=>$user['token']??null]),
            'user_id'=>$owner,'merchant_id'=>$this->positiveId($merchant['id']??null),
            'store_name'=>$this->storeName($merchant['shop_name']??null),'expires_at'=>(string)$this->expires($user['expiretime']??null)];
        // The authorization service admits these facts only from this server
        // response. HTTP clients cannot manufacture admin_id or merchant_id.
        $this->login($saved);
        return ['status'=>'authorized','credentials'=>$saved];
    }

    public function login(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace=PhpRuntime::guardSecrets();$facts=$this->facts($credentials);
        return ['access_token'=>$this->accessToken($credentials),'expires_at'=>$facts['expires_at'],
            'identity'=>['tenant_id'=>$facts['merchant_id'],'store_id'=>$facts['user_id'],'store_name'=>$facts['store_name'],'verified'=>true],
            'warnings'=>['使用已验证的短信授权读取库存；会话失效后需要用户重新验证，不自动发送短信']];
    }

    public function fetchPage(#[\SensitiveParameter] array $credentials,array $cursor,array $scope): array
    {
        $secretTrace=PhpRuntime::guardSecrets();$facts=$this->facts($credentials);[$page,$size]=$this->pagination($cursor,$scope);
        if(!empty($scope['query']))throw new ProviderException('invalid_scope','当前库存同步不接受未核验的额外筛选条件');
        foreach(['store_id'=>'user_id','tenant_id'=>'merchant_id']as$key=>$field)
            if(isset($scope[$key])&&$scope[$key]!==''&&(string)$scope[$key]!==$facts[$field])
                throw new ProviderException('scope_mismatch','库存范围与短信认证账号不一致，已停止同步');
        $response=$this->transport->post(self::STOCK_ENDPOINT,$this->headers($this->accessToken($credentials)),[
            'channel_id'=>'','channel_type'=>'','search_type'=>1,'search_month'=>'','sort'=>'','search'=>'',
            'min_price'=>'','max_price'=>'','page'=>$page,'page_size'=>$size,'crowd_type'=>1,'admin_id'=>(int)$facts['user_id'],'status'=>self::LISTED_STATUS,
        ]);
        $this->success($response,'stock');$data=$response['data']??null;
        if(!is_array($data)||PhpRuntime::isList($data)||!is_array($data['data']??null)||!PhpRuntime::isList($data['data'])||count($data['data'])>$size)
            throw new ProviderException('schema_changed','库存分页结构变化，本轮未发布');
        $units=$this->stockQuantity($data['goods_sum']??null);
        if($units===null||isset($cursor['goods_sum'])&&$cursor['goods_sum']!==$units)
            throw new ProviderException('schema_changed','分页期间库存台数发生变化，请重新同步；旧库存保持不变');
        $rows=[];$ids=[];
        foreach($data['data']as$row){
            if(!is_array($row)||PhpRuntime::isList($row))throw new ProviderException('schema_changed','库存记录格式变化，本轮未发布');
            $id=$this->positiveId($row['id']??null);$owner=$this->positiveId($row['admin_id']??null);
            if($owner!==$facts['user_id'])throw new ProviderException('scope_mismatch','库存混入其他账号的数据，已停止同步');
            if(isset($ids[$id]))throw new ProviderException('schema_changed','单页库存出现重复记录，本轮未发布');
            if($this->stockQuantity($row['num']??null)===null)throw new ProviderException('schema_changed','库存数量缺失或格式异常，本轮未发布');
            $ids[$id]=true;
            // Keep pagination/identity checks over the entire raw page even
            // if the upstream ignores its filter. Only confirmed listed stock
            // belongs in this generation; unlisted stock is not a sold fact.
            if(in_array($row['status']??null,[self::LISTED_STATUS,(string)self::LISTED_STATUS],true))$rows[]=$this->snapshot($row,$facts['merchant_id']);
        }
        $hash=hash('sha256',json_encode(array_keys($ids),JSON_THROW_ON_ERROR));
        if($ids&&isset($cursor['last_page_hash'])&&$cursor['last_page_hash']===$hash)
            throw new ProviderException('schema_changed','来源重复返回同一页库存，本轮未发布');
        // goods_sum is UNIT quantity, not a record count: 200 records were
        // observed summing to 204 units. Only a short/empty page ends a scan.
        return ['rows'=>$rows,'has_more'=>count($data['data'])===$size,
            'cursor'=>['page'=>$page+1,'goods_sum'=>$units,'last_page_hash'=>$hash],
            'note'=>'仅同步状态1的已上架设备；未上架设备暂不纳入，汇总台数不作为记录总数'];
    }

    public function normalize(array $row,array $context): array
    {
        $owner=$this->positiveId($row['admin_id']??null);$tenant=$this->positiveId($row['_merchant_id']??null);
        $safe=$this->snapshot($row,$tenant);$quantity=$this->stockQuantity($safe['num']??null);
        if($quantity===null)throw new ProviderException('schema_changed','库存数量无效，未发布记录');
        $images=[];if(!empty($safe['product_url']))$images[]=$safe['product_url'];
        foreach($safe['thumb_data']as$image)if((string)$image['product_url_type']==='1')$images[]=$image['product_url'];
        $brand=$this->text($safe['brand_name']??null);$name=$this->text($safe['product_model']??null)??$this->text($safe['product_name']??null);
        $title=$name===null?null:(($brand!==null&&!str_starts_with(mb_strtolower($name),mb_strtolower($brand)))?$brand.' '.$name:$name);
        // Source semantics confirmed by the operator: 1 = listed, 5 = still
        // in stock but unlisted. No other numeric/string value is inferred.
        $status=$safe['status']??null;
        $listing=in_array($status,[1,'1'],true)?'listed':(in_array($status,[5,'5'],true)?'unlisted':'unknown');
        $available=$quantity!=='0.000'&&$listing==='listed';
        return $this->common($safe,$context,[
            'id'=>$safe['id'],'store_id'=>$owner,'tenant_id'=>$tenant,'title'=>$title,'brand'=>$brand,
            'capacity'=>$safe['storage']??null,'color'=>$safe['color']??null,'retail_price'=>$safe['chu_price']??null,
            'trade_price'=>$safe['pfs_price']??null,'imei'=>$safe['serial_number']??null,'images'=>$images,
            'quantity'=>$quantity,'listing_status'=>$listing,'stock_status'=>$quantity==='0.000'?'out_of_stock':'in_stock',
            'sale_state'=>'unknown','availability'=>$available?'available':($quantity==='0.000'||$listing==='unlisted'?'unavailable':'unknown'),
            'saleable_quantity'=>$available?$quantity:null,'quantity_basis'=>$available?'source_quantity':'unknown',
            'source_status'=>['status'=>$safe['status']??null,'inventory_status'=>$safe['inventory_status']??null,'num'=>$quantity],
        ]);
    }

    private function snapshot(array $row,string $merchant): array
    {
        $safe=[];foreach(self::SNAPSHOT_KEYS as$key)if(array_key_exists($key,$row))$safe[$key]=$this->scalar($row[$key]);
        foreach(['chu_price','pfs_price']as$key)$safe[$key]=$this->money($row[$key]??null);
        $safe['id']=$this->positiveId($row['id']??null);$safe['admin_id']=$this->positiveId($row['admin_id']??null);$safe['_merchant_id']=$merchant;
        $safe['product_url']=$this->url($row['product_url']??null);$safe['thumb_data']=[];
        $thumbs=$row['thumb_data']??[];if(!is_array($thumbs)||!PhpRuntime::isList($thumbs))throw new ProviderException('schema_changed','库存图片结构变化，本轮未发布');
        foreach(array_slice($thumbs,0,20)as$image){
            if(!is_array($image))continue;$url=$this->url($image['product_url']??null);
            if($url!==null&&(string)($image['product_url_type']??'')==='1')$safe['thumb_data'][]=['product_url_type'=>1,'product_url'=>$url];
        }
        $grade=$this->text($safe['quality']??null);$safe['_grade']=$grade!==null&&preg_match('/^[1-9][0-9]{0,2}$/D',$grade)?$grade:'';
        return $safe;
    }

    private function facts(#[\SensitiveParameter] array $credentials): array
    {
        $secretTrace=PhpRuntime::guardSecrets();$this->mobile($credentials);$this->accessToken($credentials);
        return ['user_id'=>$this->positiveId($credentials['user_id']??null),'merchant_id'=>$this->positiveId($credentials['merchant_id']??null),
            'store_name'=>$this->storeName($credentials['store_name']??null),'expires_at'=>$this->expires($credentials['expires_at']??null)];
    }
    private function headers(?string $token=null): array
    {
        $headers=['PackageName'=>'uni.tongxunbang','Version'=>self::VERSION,'Content-Type'=>'application/json;charset=utf-8'];
        if($token!==null)$headers['token']=$token;return $headers;
    }
    private function mobile(array $credentials): string
    {
        $mobile=$credentials['username']??null;if(!is_string($mobile)||!preg_match('/^1[0-9]{10}$/D',$mobile))throw new ProviderException('invalid_credentials','请填写有效的授权手机号');return $mobile;
    }
    private function positiveId($value): string
    {
        if((!is_string($value)&&!is_int($value))||!preg_match('/^[1-9][0-9]{0,17}$/D',(string)$value))
            throw new ProviderException('invalid_credentials','来源账号或门店身份缺失，请重新完成短信授权');return (string)$value;
    }
    private function storeName($value): string
    {
        if(!is_string($value)||trim($value)===''||mb_strlen($value)>120||preg_match('/[<>\p{C}]/u',$value))
            throw new ProviderException('schema_changed','来源门店名称缺失或格式异常，未保存连接');return trim($value);
    }
    private function expires($value): int
    {
        if((!is_int($value)&&!is_string($value))||!preg_match('/^[1-9][0-9]{0,10}$/D',(string)$value)||(int)$value<=time())
            throw new ProviderException('auth_required','短信登录授权已失效，请重新获取验证码验证');return (int)$value;
    }
    private function success(array $response,string $operation): void
    {
        $code=$response['code']??null;if(in_array($code,[1,'1'],true))return;
        $message=is_string($response['msg']??null)?$response['msg']:'';
        if(in_array($code,[401,'401',403,'403'],true)||preg_match('/未登录|请先登录|请登录|登录.*(?:失效|过期)|(?:token|令牌).*(?:失效|过期)/iu',$message))
            throw new ProviderException('auth_required','短信登录授权已失效，请重新验证后同步');
        if($operation==='sms')throw new ProviderException('rate_limited','短信暂未发送成功，请稍后手动重试；系统不会自动重发');
        if($operation==='login')throw new ProviderException('invalid_captcha','手机号或短信验证码未通过，请核对后重新获取验证码');
        throw new ProviderException('upstream_error','库存接口未成功，本轮未更新已有库存');
    }
}
