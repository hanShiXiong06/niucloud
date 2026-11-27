<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\gateway;
use addon\hsx_boyuan\app\support\Contract;
use addon\hsx_boyuan\app\support\InterfaceTrace;
use addon\hsx_boyuan\app\support\Problem;
use think\facade\Log;
final class HttpTransport implements Transport
{
    public function __construct(private array $config, private string $token, private ?InterfaceTrace $trace=null, private string $signingSecret='') {}
    public static function endpoint(string $url): string
    {
        $p = parse_url($url);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])
            || ($p['port'] ?? 443) !== 443 || preg_match('/[\x00-\x20\\\\]/', $url) || filter_var($p['host'], FILTER_VALIDATE_IP)
            || !preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/iD', $p['host']) || str_contains($p['host'], '..')
            || !preg_match('#^/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]*$#D', $p['path'] ?? '/')) throw new Problem('ENDPOINT_INVALID', '服务地址需为HTTPS域名和普通路径，不得包含账号、查询参数或非443端口');
        return rtrim($url, '/');
    }
    public static function publicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        if (str_contains($ip, ':')) {
            $bytes = inet_pton($ip);
            return $bytes && (ord($bytes[0]) & 0xe0) === 0x20 && !in_array(substr($bytes, 0, 2), ["\x20\x02"], true)
                && !in_array(substr($bytes, 0, 3), ["\x20\x01\x00", "\x20\x01\x0d"], true);
        }
        $n = (int)sprintf('%u', ip2long($ip));
        foreach ([['100.64.0.0',10], ['192.0.0.0',24], ['192.0.2.0',24], ['198.18.0.0',15], ['198.51.100.0',24], ['203.0.113.0',24], ['224.0.0.0',4], ['240.0.0.0',4]] as [$network,$bits]) {
            $mask = (0xffffffff << (32-$bits)) & 0xffffffff;
            if (($n & $mask) === ((int)sprintf('%u',ip2long($network)) & $mask)) return false;
        }
        return true;
    }
    public function send(string $method, string $path, array $body = []): array
    {
        if ($this->token === '' || preg_match('/[\r\n]/', $this->token . $this->config['account_id'])) throw new Problem('AUTH_INVALID', '请保存有效博远授权');
        $headers = ['Content-Type: application/json; charset=utf-8', 'Authorization: Bearer ' . $this->token,
            'X-Account-Id: ' . $this->config['account_id'], 'X-Request-Id: ' . bin2hex(random_bytes(16))];
        if ($method === 'POST' && isset($body['operation_id'])) $headers[] = 'Idempotency-Key: ' . Contract::id($body['operation_id']);
        return $this->exchange($method,$path,$body,$headers,false);
    }
    public function postForm(string $path,array $body): array
    {
        return $this->exchange('POST',$path,$body,['Content-Type: application/x-www-form-urlencoded; charset=utf-8'],true);
    }
    /** Never interpolate a request-supplied service/path into a log. Unknown APIs get a constant label. */
    private static function operation(bool $form,$service): string
    {
        if(!$form) return 'bridge';
        return match($service) {
            'boyuan.biz.bill.purchase.generate'=>'purchase_create','boyuan.biz.purchases.query'=>'purchase_query',
            'boyuan.biz.purchase.imeidetails.query'=>'purchase_serial_query','boyuan.biz.bill.sale.generate'=>'sale_create',
            'boyuan.biz.sales.query'=>'sale_query','boyuan.biz.bill.payment.generate'=>'payment_create',
            'boyuan.biz.bill.receipt.generate'=>'receipt_create',
            'boyuan.biz.inventories.query'=>'inventory_query','boyuan.biz.inventory.imeidetails.query'=>'inventory_serial_query',
            default=>in_array($service,array_column(OpenApi::RESOURCES,1),true)?'reference_query':'openapi_other',
        };
    }
    private static function diagnostic(string $op,string $category,int|float $start,?int $errno,?int $http,?int $connectMs,?int $curlMs,bool $truncated): void
    {
        // Only closed labels, numerical metrics and a boolean. Never log curl_error/getinfo arrays, URLs, headers or payloads.
        try { Log::write('hsx_boyuan transport '.json_encode(['operation'=>$op,'category'=>$category,'curl_errno'=>$errno,
            'http_status'=>$http,'connect_ms'=>$connectMs,'curl_total_ms'=>$curlMs,'wall_ms'=>(int)round((hrtime(true)-$start)/1000000),
            'response_truncated'=>$truncated],JSON_THROW_ON_ERROR),'warning'); }
        catch(\Throwable $ignored) { /* Diagnostics cannot change a vendor request or outcome. */ }
    }
    private function exchange(string $method,string $path,array $body,array $headers,bool $form): array
    {
        $op=self::operation($form,$body['service']??null);
        if(!$form) foreach(($this->config['paths']??[]) as $label=>$basePath) {
            if(in_array($label,['documents','operations','references','parties','financial','balances','settlements'],true)
                && ($basePath===$path || str_starts_with($path,$basePath.'/'))) { $op='bridge_'.$label; break; }
        }
        $secrets=[$this->token,$this->signingSecret,$body['sign']??'',$body['app_id']??''];
        $attempt=$this->trace?->begin($method,$op,$body,$secrets);
        $info=['delivery'=>'not_sent','status'=>0,'errno'=>0,'body'=>'none','response'=>null]; $reason='';
        try { return $this->perform($method,$path,$body,$headers,$form,$info,$attempt!==null); }
        catch(\Throwable $e) { $reason=$e instanceof Problem?$e->reason:'LOCAL_ERROR'; throw $e; }
        finally {
            if($attempt!==null) $this->trace->end($attempt,$info['delivery'],$info['status'],$info['errno'],$info['response'],$info['body'],$reason,$secrets);
        }
    }
    private function perform(string $method,string $path,array $body,array $headers,bool $form,array &$info,bool $tracing): array
    {
        $base = self::endpoint($this->config['endpoint']);
        if (!preg_match('#^/[a-zA-Z0-9_/{}/.-]+$#D', $path) || str_contains($path, '..') || str_contains($path, '//')) throw new Problem('PATH_INVALID', '接口路径无效');
        $start=hrtime(true); $op=self::operation($form,$body['service']??null);
        $host = parse_url($base, PHP_URL_HOST); $ips = [];
        foreach (dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? '';
            if (!self::publicIp($ip)) {
                self::diagnostic($op,'address_denied',$start,null,null,null,null,false);
                throw new Problem('ADDRESS_DENIED', '博远域名解析到非公网地址，已阻止请求');
            }
            $ips[] = $ip;
        }
        if (!$ips) {
            self::diagnostic($op,'dns_unavailable',$start,null,null,null,null,false);
            throw new Problem('DNS_UNAVAILABLE', '暂时无法解析博远服务域名');
        }
        $ip = $ips[0]; $url = $base . $path;
        if ($method === 'GET' && $body) $url .= '?' . http_build_query($body);
        $curl = curl_init($url); $raw = ''; $truncated=false;
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROXY => '',
            CURLOPT_RESOLVE => [$host . ':443:' . (str_contains($ip, ':') ? '[' . $ip . ']' : $ip)],
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw,&$truncated): int {
                if (strlen($raw) + strlen($chunk) > 2097152) { $truncated=true; return 0; }
                $raw .= $chunk; return strlen($chunk);
            }]);
        if ($method === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, $form ? http_build_query($body,'','&',PHP_QUERY_RFC3986) : Contract::json($body));
        $info['delivery']='no_confirmed_response';
        $ok = curl_exec($curl); $status = (int)curl_getinfo($curl,CURLINFO_HTTP_CODE);
        $errno=curl_errno($curl); $connectMs=(int)round(1000*curl_getinfo($curl,CURLINFO_CONNECT_TIME)); $curlMs=(int)round(1000*curl_getinfo($curl,CURLINFO_TOTAL_TIME)); curl_close($curl);
        $info['status']=$status; $info['errno']=$errno;
        if($status>0) $info['delivery']='response_received';
        $info['body']=$truncated || ($ok===false && $raw!=='')?'incomplete':($raw===''?'none':'non_json');
        // A 429/5xx/redirect can still contain a relevant error object. Never log raw HTML or a partial body.
        if($tracing && $ok!==false && $status>=300 && ($status<400 || $status===429 || $status>=500) && !$truncated && $raw!=='') {
            $errorBody=json_decode($raw,true,32);
            if(is_array($errorBody)) { $info['response']=$errorBody; $info['body']='json'; }
        }
        $log=static fn(string $category)=>self::diagnostic($op,$category,$start,$errno,$status,$connectMs,$curlMs,$truncated);
        if($ok===false) $log($truncated?'response_size_limit':match($errno){6=>'dns_unavailable',7=>'connect_failed',28=>'timeout',35,51,58,60,77,83,90,91=>'tls_error',default=>'curl_error'});
        elseif($status>=300) $log($status===429?'rate_limited':($status>=500?'upstream_5xx':($status>=400?'upstream_4xx':'redirect_denied')));
        if ($ok === false || $status >= 500 || $status === 429) {
            if ($op==='reference_query') {
                $cause=$truncated?'返回的数据超出单页大小限制':($errno===28?'读取超时':($status===429?'请求过于频繁':($status>=500?'接口暂不可用':'连接未完成')));
                throw new Problem('REFERENCE_UNAVAILABLE','博远资料'.$cause.'。本站保留已取回的数据，可稍后再次同步。');
            }
            throw new Problem('REMOTE_UNCERTAIN', '请求未获得确定结果，将先查询原操作，不能重复建单', 'unknown');
        }
        if ($status >= 300 && $status < 400) throw new Problem('REDIRECT_DENIED', '博远地址发生跳转，已阻止携带授权跳转', 'unknown');
        try { $reply = Contract::decode($raw); } catch (\Throwable $e) { if($status<300) $log('response_invalid'); throw new Problem('RESPONSE_INVALID', '博远未返回有效JSON，请核对原操作', 'unknown'); }
        if($tracing) { $info['response']=$reply; $info['body']='json'; }
        $reply=Contract::redactCredentials($reply); $token=$this->token;
        array_walk_recursive($reply,static function (&$value) use($token): void { if ($token!=='' && is_string($value)) $value=str_replace($token,'[已隐藏]',$value); });
        return $reply;
    }
}
