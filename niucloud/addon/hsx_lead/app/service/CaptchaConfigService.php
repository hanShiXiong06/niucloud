<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

use addon\hsx_lead\app\auth\{CaptchaRecognizerContract,TwoCaptchaRecognizer};
use addon\hsx_lead\app\provider\ProviderException;
use addon\hsx_lead\app\support\{Cipher,Data};
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\{Cache,Db};

/** Site-owned OCR credentials and conservative attempt budget, never a login service. */
final class CaptchaConfigService
{
    public const CONFIG_KEY='HSX_LEAD_CAPTCHA_2CAPTCHA';
    public const USAGE_KEY='HSX_LEAD_CAPTCHA_USAGE';
    private const CIPHER_SCOPE=-2147483000;
    private const DEFAULT_LIMIT=20;
    private const NOTICE='仅将验证码图片发送给 2Captcha，不发送 ERP 账号、密码或授权 Token。识别可能按次计费；每日上限统计提交尝试次数，不是实际扣费金额，失败不返还本站次数。';
    private const DOCS_URL='https://2captcha.com/api-docs/normal-captcha';

    public function __construct(private Context $context,private ?CaptchaRecognizerContract $recognizer=null) {}

    /** Public configuration only. Reading does not initialize config or reset usage. */
    public function get(): array
    {
        $this->context->manager();
        $this->assertSite();
        $config=$this->stored();
        $configured=false; $notice=self::NOTICE;
        try { $configured=$this->key($config)!==''; }
        catch(\Throwable $ignored) { $notice='已保存的识别密钥不可读取，请重新填写或明确清除；当前不能自动识别。'.self::NOTICE; }
        $used=$this->usage()['count'];
        return ['provider'=>'2captcha','enabled'=>$config['enabled'] && $configured && $config['consent'],
            'configured'=>$configured,'key_hint'=>$configured?'已配置（••••）':'未配置',
            'daily_limit'=>$config['daily_limit'],'used_today'=>$used,
            'remaining_today'=>max(0,$config['daily_limit']-$used),'revision'=>$config['revision'],
            'notice'=>$notice,'docs_url'=>self::DOCS_URL];
    }

    public function save(#[\SensitiveParameter] array $input): array
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        $this->context->manager();
        if (isset($input['provider']) && $input['provider']!=='2captcha') throw new CommonException('当前仅支持 2Captcha 图片识别配置');
        $newKey=Data::secret($input,'api_key',32);
        if ($newKey!=='' && preg_match('/^[a-f0-9]{32}$/iD',$newKey)!==1) throw new CommonException('2Captcha API Key 应为32位十六进制字符');
        $newKey=strtolower($newKey);
        $clear=$this->boolean($input['clear_key']??false);
        if ($clear && $newKey!=='') throw new CommonException('清除密钥时请勿同时填写新密钥');
        Db::transaction(function()use($input,$newKey,$clear){
            $this->assertSite(true);
            $old=$this->stored(true);
            if (array_key_exists('revision',$input) && (!is_string($input['revision']) || !hash_equals($old['revision'],$input['revision']))) {
                throw new CommonException('识别配置已被修改，请刷新后重新保存');
            }
            $oldKey=null;
            try { $oldKey=$this->key($old); }
            catch(\Throwable $ignored) {
                if (!$clear && $newKey==='') throw new CommonException('现有识别密钥不可读取，请重新填写或明确清除');
            }
            $key=$clear?'':($newKey!==''?$newKey:($oldKey??''));
            $enabled=$clear?false:$this->boolean(array_key_exists('enabled',$input)?$input['enabled']:$old['enabled']);
            $limit=$this->limit(array_key_exists('daily_limit',$input)?$input['daily_limit']:$old['daily_limit']);
            if ($enabled && $key==='') throw new CommonException('请先填写 2Captcha API Key，再启用自动识别');
            if ($enabled && ($input['consent']??null)!==true) {
                throw new CommonException('请明确同意仅将验证码图片发送给 2Captcha，并知悉识别可能按次计费');
            }
            $consent=$enabled;
            $changed=$key!==$oldKey || $enabled!==$old['enabled'] || $limit!==$old['daily_limit'] || $consent!==$old['consent'];
            if (!$changed) return;
            $cipher=$key===''?'':($key===$oldKey?$old['credentials_cipher']:Cipher::encrypt(['api_key'=>$key],$this->context->site,self::CIPHER_SCOPE));
            $this->write(self::CONFIG_KEY,['provider'=>'2captcha','enabled'=>$enabled,'credentials_cipher'=>$cipher,
                'daily_limit'=>$limit,'consent'=>$consent,'revision'=>bin2hex(random_bytes(16)),'updated_at'=>time()]);
            Data::audit($this->context->site,$this->context->uid,0,'captcha_config',0,'updated',
                ['provider'=>'2captcha','enabled'=>$enabled,'configured'=>$key!=='','daily_limit'=>$limit]);
        });
        $this->clearConfigCache();
        return $this->get();
    }

    /** Explicit read-only upstream action: no create(), no local attempt reservation. */
    public function balance(): array
    {
        $credentials=$this->credentials();
        if ($credentials['api_key']==='') throw new CommonException('请先保存 2Captcha API Key，再查询余额');
        try { $balance=($this->recognizer??new TwoCaptchaRecognizer())->balance($credentials['api_key']); }
        catch(ProviderException $error) {
            throw new CommonException(match($error->reason()) {
                'captcha_invalid_key'=>'2Captcha API Key 无效，请核对后重新配置',
                'captcha_ip_not_allowed'=>'2Captcha 未允许当前服务器出口IP，请核对识别服务的白名单',
                'captcha_temporary'=>'2Captcha 暂时无法查询余额，请稍后重试；未提交识别任务',
                default=>'识别服务余额查询失败，请核对密钥或稍后再试',
            });
        }
        catch(\Throwable $ignored) { throw new CommonException('识别服务余额查询失败，请核对密钥或稍后再试'); }
        if (!is_string($balance) || preg_match('/^[0-9]{1,12}(?:\.[0-9]{1,8})?$/D',$balance)!==1) {
            throw new CommonException('识别服务余额格式异常，未返回原始响应');
        }
        if (!hash_equals($credentials['revision'],$this->stored()['revision'])) {
            throw new CommonException('识别配置已变化，余额结果已忽略，请重新查询');
        }
        return ['provider'=>'2captcha','balance'=>$balance,'currency'=>null,'message'=>'余额单位请以2Captcha账户为准；只查余额未创建识别任务'];
    }

    /** Internal only: never return this result from a controller, audit or cache. */
    public function credentials(): array
    {
        $this->context->manager();
        $this->assertSite();
        $config=$this->stored();
        try { $key=$this->key($config); }
        catch(\Throwable $ignored) { throw new CommonException('识别密钥不可读取，请核对部署密钥后重新配置'); }
        return ['enabled'=>$config['enabled'] && $config['consent'] && $key!=='','api_key'=>$key,
            'daily_limit'=>$config['daily_limit'],'revision'=>$config['revision']];
    }

    /** Reserve once immediately before paid submission. Failures conservatively keep their reservation. */
    public function reserveAttempt(string $expectedRevision): void
    {
        $this->context->manager();
        Db::transaction(function()use($expectedRevision){
            // sys_config has no site/key unique constraint. The existing site row
            // serializes both first insertion and all budget increments.
            $this->assertSite(true);
            $config=$this->stored(true);
            if ($expectedRevision==='' || !hash_equals($config['revision'],$expectedRevision)) {
                throw new CommonException('识别配置已变化，本次未提交，请重新授权');
            }
            if (!$config['enabled'] || !$config['consent']) throw new CommonException('本站尚未启用自动识别，本次未提交');
            try { $key=$this->key($config); }
            catch(\Throwable $ignored) { throw new CommonException('识别密钥不可读取，本次未提交，请重新配置'); }
            if ($key==='') throw new CommonException('本站尚未配置识别密钥，本次未提交');
            $usage=$this->usage(true);
            if ($usage['count']>=$config['daily_limit']) throw new CommonException('本站今日自动识别次数已用完，请手动填写验证码或明日再试');
            $this->write(self::USAGE_KEY,['date'=>$usage['date'],'count'=>$usage['count']+1]);
        });
        $this->clearConfigCache();
    }

    private function assertSite(bool $lock=false): void
    {
        $query=Db::name('site')->where('site_id',$this->context->site)->field('site_id');
        if ($lock) $query->lock(true);
        if (!$query->find()) throw new CommonException('当前站点不存在');
    }

    /** Read the database, not a possibly stale framework cache, under the owning site lock for writes. */
    private function raw(string $key,bool $lock=false): array
    {
        $query=Db::name('sys_config')->where('site_id',$this->context->site)->where('config_key',$key)->field('value')->limit(2);
        if ($lock) $query->lock(true);
        $rows=$query->select()->toArray();
        if (count($rows)>1) throw new CommonException('本站识别配置存在重复记录，请管理员核对后再试');
        if (!$rows) return [];
        try { $value=json_decode((string)($rows[0]['value']??''),true,32,JSON_THROW_ON_ERROR); }
        catch(\Throwable $ignored) { throw new CommonException('本站识别配置格式异常，请管理员核对'); }
        if (!is_array($value) || ($value!==[] && \addon\hsx_lead\app\support\PhpRuntime::isList($value))) throw new CommonException('本站识别配置格式异常，请管理员核对');
        return $value;
    }

    private function stored(bool $lock=false): array
    {
        $value=$this->raw(self::CONFIG_KEY,$lock);
        $cipher=$value['credentials_cipher']??'';
        $revision=$value['revision']??'0';
        if (!is_string($cipher) || strlen($cipher)>8192 || !is_string($revision) || preg_match('/^(?:0|[a-f0-9]{32})$/D',$revision)!==1) {
            throw new CommonException('本站识别配置格式异常，请管理员核对');
        }
        return ['enabled'=>$this->boolean($value['enabled']??false),'consent'=>($value['consent']??false)===true,
            'daily_limit'=>$this->limit($value['daily_limit']??self::DEFAULT_LIMIT),'credentials_cipher'=>$cipher,'revision'=>$revision];
    }

    private function key(#[\SensitiveParameter] array $config): string
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        if ($config['credentials_cipher']==='') return '';
        $plain=Cipher::decrypt($config['credentials_cipher'],$this->context->site,self::CIPHER_SCOPE);
        $key=$plain['api_key']??null;
        if (!is_string($key) || preg_match('/^[a-f0-9]{32}$/iD',$key)!==1) throw new CommonException('识别密钥格式异常，请重新配置');
        return strtolower($key);
    }

    private function usage(bool $lock=false): array
    {
        $date=(new \DateTimeImmutable('now',new \DateTimeZone('Asia/Shanghai')))->format('Y-m-d');
        $value=$this->raw(self::USAGE_KEY,$lock);
        if (!$value) return ['date'=>$date,'count'=>0];
        $storedDate=$value['date']??null; $count=$value['count']??null;
        if (!is_string($storedDate) || preg_match('/^\d{4}-\d{2}-\d{2}$/D',$storedDate)!==1
            || !checkdate((int)substr($storedDate,5,2),(int)substr($storedDate,8,2),(int)substr($storedDate,0,4))
            || !is_int($count) || $count<0 || $count>1000000000 || $storedDate>$date) {
            throw new CommonException('本站识别次数记录异常，请管理员核对；本次未提交');
        }
        return ['date'=>$date,'count'=>$storedDate===$date?$count:0];
    }

    private function limit(mixed $value): int
    {
        if ((!is_int($value) && !is_string($value)) || preg_match('/^[1-9][0-9]{0,3}$/D',(string)$value)!==1 || (int)$value>1000) {
            throw new CommonException('每日识别上限应为1—1000次');
        }
        return (int)$value;
    }

    private function boolean(mixed $value): bool
    {
        if (!in_array($value,[true,false,0,1,'0','1'],true)) throw new CommonException('请明确选择识别开关或清除操作');
        return in_array($value,[true,1,'1'],true);
    }

    private function write(string $key,#[\SensitiveParameter] array $value): void
    {
        $secretTrace = \addon\hsx_lead\app\support\PhpRuntime::guardSecrets();
        // CoreConfigService::setConfig decides insert/update via its cached
        // getConfig(). A concurrent cached negative must not insert a second
        // row or reset this durable budget. The caller already owns the site
        // row lock; use a current locking read, then invalidate the same tag.
        $rows=Db::name('sys_config')->where('site_id',$this->context->site)->where('config_key',$key)
            ->field('id')->lock(true)->limit(2)->select()->toArray();
        if (count($rows)>1) throw new CommonException('本站识别配置存在重复记录，请管理员核对后再试');
        $values=['value'=>Data::json($value),'update_time'=>time()];
        if ($rows) Db::name('sys_config')->where('site_id',$this->context->site)->where('id',$rows[0]['id'])->update($values);
        else Db::name('sys_config')->insert($values+['site_id'=>$this->context->site,'config_key'=>$key,
            'addon'=>'hsx_lead','create_time'=>time()]);
        $this->clearConfigCache();
    }

    private function clearConfigCache(): void { Cache::tag(CoreConfigService::$cache_tag_name.$this->context->site)->clear(); }
}
