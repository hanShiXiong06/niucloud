<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\gateway;

final class OfficialSdk
{
    public const VERSION = '8.2.0';
    public const SHA256 = '69219c56ac5797686a9edc39a312e494bcecfdf58e6813451f1831a25e738a26';
    private static bool $loaded = false;
    private const CLASSES = [
        'entity\\IdentifyInfo' => 'entity/IdentifyInfo.class.php',
        'sdk\\ApiRequester' => 'sdk/ApiRequester.class.php',
        'sdk\\HttpRequester' => 'sdk/HttpRequester.class.php',
        'sdk\\K3CloudApi' => 'sdk/K3CloudApi.class.php',
        'sdk\\WebApiClient' => 'sdk/WebApiClient.class.php',
        'util\\EncodeUtil' => 'util/encode_util.class.php',
        'util\\StringUtil' => 'util/string_util.class.php',
    ];

    public static function load(): void
    {
        if (self::$loaded) return;
        if (!extension_loaded('phar')) throw new ApiFailure('sdk_missing', '服务器缺少 PHP Phar 扩展，无法加载金蝶 SDK');
        $path = realpath(dirname(__DIR__, 2) . '/resources/sdk/kingdee-webapi-sdk-v8.2.0.phar');
        if (!$path || !is_readable($path) || !hash_equals(self::SHA256, (string)@hash_file('sha256', $path))) {
            throw new ApiFailure('sdk_integrity', '金蝶 SDK 文件缺失或校验不符，请重新安装本插件的原版 SDK');
        }
        $existing = 0;
        foreach (self::CLASSES as $class => $file) {
            if (!class_exists('kingdee_cdp_webapi_sdk\\' . $class, false)) continue;
            $existing++;
            $source = (new \ReflectionClass('kingdee_cdp_webapi_sdk\\' . $class))->getFileName();
            $suffix = '/' . $file;
            if (!$source || !str_starts_with($source, 'phar://') || !str_ends_with($source, $suffix)) self::conflict();
            $archive = substr($source, 7, -strlen($suffix));
            if (!is_readable($archive) || !hash_equals(self::SHA256, (string)@hash_file('sha256', $archive))) self::conflict();
        }
        if ($existing !== 0 && $existing !== count(self::CLASSES)) self::conflict();
        if ($existing === 0) {
            // Bypass the PHAR web stub: no file-function interception or lasting include-path changes.
            $includePath = get_include_path();
            try {
                set_include_path('phar://' . $path . '/sdk' . PATH_SEPARATOR . $includePath);
                require_once 'phar://' . $path . '/sdk/K3CloudApi.class.php';
            } catch (\Throwable $error) {
                throw new ApiFailure('sdk_load_failed', '金蝶 SDK 加载失败，请检查 PHP 运行环境及插件文件');
            } finally {
                set_include_path($includePath);
            }
        }
        self::$loaded = true;
    }

    private static function conflict(): void
    {
        throw new ApiFailure('sdk_conflict', '当前进程已加载其他版本或不完整的金蝶 SDK，请检查插件依赖冲突');
    }

    public static function query(array $data, EndpointPolicy $endpoint, array $config, string $secret, Transport $transport): array
    {
        self::load();
        $requester = new class($data, $endpoint, $config, $secret, $transport) {
            public function __construct(private array $data, private EndpointPolicy $endpoint, private array $config,
                private string $secret, private Transport $transport) {}

            public function post($service, $body = ''): string
            {
                if ($service !== ReadOnlyCurlTransport::QUERY) throw new ApiFailure('write_disabled', 'SDK 连接只允许只读单据查询');
                try { $payload = json_decode($body, true, 32, JSON_THROW_ON_ERROR); }
                catch (\Throwable $error) { throw new ApiFailure('sdk_payload', 'SDK 查询参数编码失败，未发送请求'); }
                if ($payload !== ['parameters' => [$this->data]]) throw new ApiFailure('sdk_payload', 'SDK 查询参数与预期不一致，未发送请求');
                $headers = SignedRequest::headers($this->endpoint->url . $service . '.common.kdsvc',
                    $this->config, $this->secret, time(), bin2hex(random_bytes(16)));
                return json_encode($this->transport->post($service, $payload, $headers), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        };
        // SDK methods and parameter encoding stay upstream; only its insecure transport is replaced.
        $client = new class($requester) extends \kingdee_cdp_webapi_sdk\sdk\K3CloudApi {
            public function __construct(object $requester) { $this->api_request = $requester; }
        };
        try {
            $raw = $client->execute_bill_query($data);
            $result = json_decode($raw, false, 64, JSON_THROW_ON_ERROR);
        } catch (ApiFailure $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new ApiFailure('sdk_query_failed', '金蝶 SDK 查询未完成，未记录原始响应或授权信息');
        }
        if (!is_array($result)) throw new ApiFailure('query_rejected', '金蝶未返回有效查询结果');
        return $result;
    }

    public static function command(string $method, string $form, array $data, EndpointPolicy $endpoint,
        array $config, string $secret, Transport $transport): array
    {
        $operations = ['save' => 'Save', 'submit' => 'Submit', 'audit' => 'Audit', 'view' => 'View'];
        if (!isset($operations[$method]) || !preg_match('/^[A-Za-z][A-Za-z0-9_]{0,79}$/D', $form)) {
            throw new ApiFailure('operation_invalid', '不支持的单据操作');
        }
        self::load();
        $service = 'Kingdee.BOS.WebApi.ServicesStub.DynamicFormService.' . $operations[$method];
        $requester = new class($service, [$form, $data], $endpoint, $config, $secret, $transport) {
            public function __construct(private string $service, private array $parameters, private EndpointPolicy $endpoint,
                private array $config, private string $secret, private Transport $transport) {}

            public function post($service, $body = ''): string
            {
                if ($service !== $this->service) throw new ApiFailure('operation_invalid', 'SDK 操作与授权不一致');
                try { $payload = json_decode($body, true, 64, JSON_THROW_ON_ERROR); }
                catch (\Throwable $e) { throw new ApiFailure('sdk_payload', 'SDK 单据参数编码失败'); }
                if ($payload !== ['parameters' => $this->parameters]) throw new ApiFailure('sdk_payload', 'SDK 单据参数与预期不一致');
                $headers = SignedRequest::headers($this->endpoint->url . $service . '.common.kdsvc',
                    $this->config, $this->secret, time(), bin2hex(random_bytes(16)));
                return json_encode($this->transport->post($service, $payload, $headers), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        };
        $client = new class($requester) extends \kingdee_cdp_webapi_sdk\sdk\K3CloudApi {
            public function __construct(object $requester) { $this->api_request = $requester; }
        };
        try {
            $result = json_decode($client->$method($form, $data), true, 64, JSON_THROW_ON_ERROR);
        } catch (ApiFailure $error) { throw $error; }
        catch (\Throwable $error) { throw new ApiFailure('sdk_operation_unknown', 'SDK 操作结果未确认，必须查单核对'); }
        if (!is_array($result) || !isset($result['Result'])) throw new ApiFailure('response_invalid', '金蝶操作结果不完整，必须查单核对');
        return $result;
    }
}
