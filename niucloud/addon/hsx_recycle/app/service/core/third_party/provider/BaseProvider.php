<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\third_party\provider;

/**
 * 第三方服务提供者抽象基类
 * Class BaseProvider
 * @package addon\hsx_recycle\app\service\core\third_party\provider
 */
abstract class BaseProvider
{
    /**
     * 配置信息
     * @var array
     */
    protected $config;

    /**
     * 站点ID
     * @var int
     */
    protected $siteId;

    /**
     * 构造函数
     * @param array $config 配置信息
     * @param int $siteId 站点ID
     */
    public function __construct(array $config, int $siteId = 0)
    {
        $this->config = $config;
        $this->siteId = $siteId;
        $this->initialize();
    }

    /**
     * 初始化方法（子类可重写）
     * @return void
     */
    protected function initialize()
    {
        // 子类可以重写此方法进行初始化操作
    }

    /**
     * 执行调用（必须实现）
     * @param string $method 方法名
     * @param array $params 参数
     * @return array
     */
    abstract public function execute(string $method, array $params): array;

    /**
     * 获取余额（必须实现）
     * @return float
     */
    abstract public function getBalance(): float;

    /**
     * 健康检查（必须实现）
     * @return bool
     */
    abstract public function healthCheck(): bool;

    /**
     * 获取服务名称（必须实现）
     * @return string
     */
    abstract public function getName(): string;

    /**
     * 获取服务类型（必须实现）
     * @return string
     */
    abstract public function getServiceType(): string;

    /**
     * 获取提供商名称（必须实现）
     * @return string
     */
    abstract public function getProviderName(): string;

    /**
     * HTTP GET 请求
     * @param string $url
     * @param array $params
     * @param array $headers
     * @return array
     */
    protected function httpGet(string $url, array $params = [], array $headers = []): array
    {
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->httpRequest('GET', $url, [], $headers);
    }

    /**
     * HTTP POST 请求
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return array
     */
    protected function httpPost(string $url, array $data = [], array $headers = []): array
    {
        return $this->httpRequest('POST', $url, $data, $headers);
    }

    /**
     * HTTP POST 请求（form-urlencoded格式）
     * @param string $url
     * @param array $data
     * @param array $headers
     * @return array
     */
    protected function httpPostForm(string $url, array $data = [], array $headers = []): array
    {
        return $this->httpRequest('POST', $url, $data, $headers, 'form');
    }

    /**
     * HTTP 请求
     * @param string $method
     * @param string $url
     * @param array $data
     * @param array $headers
     * @param string $contentType 内容类型：json 或 form
     * @return array
     */
    protected function httpRequest(string $method, string $url, array $data = [], array $headers = [], string $contentType = 'json'): array
    {
        $ch = curl_init();

        // 设置URL
        curl_setopt($ch, CURLOPT_URL, $url);

        // 设置请求方法
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        // 设置返回结果
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // 设置超时
        $timeout = $this->config['timeout'] ?? 30;
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

        // 设置请求头
        if ($contentType === 'form') {
            $defaultHeaders = [
                'Content-Type: application/x-www-form-urlencoded',
            ];
        } else {
            $defaultHeaders = [
                'Content-Type: application/json',
            ];
        }
        $headers = array_merge($defaultHeaders, $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // 设置请求数据
        if (!empty($data)) {
            if ($contentType === 'form') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        // 默认严格校验 SSL；仅本地联调可通过明确配置 verify_ssl=false 临时关闭。
        $verifySsl = !array_key_exists('verify_ssl', $this->config) || (bool)$this->config['verify_ssl'];
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifySsl ? 2 : 0);

        // 执行请求
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // 处理错误
        if ($response === false) {
            throw new \Exception("CURL Error: {$error}");
        }

        // 解析响应
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("JSON Decode Error: " . json_last_error_msg());
        }

        return $result;
    }

    /**
     * 获取配置项
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getConfig(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    /**
     * 记录日志
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log(string $message, array $context = [])
    {
        \think\facade\Log::write(
            sprintf('[%s] %s', $this->getName(), $message),
            'info',
            $context
        );
    }

    /**
     * 记录错误日志
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function logError(string $message, array $context = [])
    {
        \think\facade\Log::write(
            sprintf('[%s] %s', $this->getName(), $message),
            'error',
            $context
        );
    }
}
