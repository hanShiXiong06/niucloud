<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use GuzzleHttp\Client;

/** 标准 MD5 协议；不重试、不记录凭据与原始地址。 */
class SfClient
{
    public const CREATE_ORDER = 'EXP_RECE_CREATE_ORDER';
    public const SEARCH_ORDER = 'EXP_RECE_SEARCH_ORDER_RESP';
    public const CANCEL_ORDER = 'EXP_RECE_UPDATE_ORDER';
    public const PRINT_PDF = 'COM_RECE_CLOUD_PRINT_WAYBILLS';
    public const PRODUCTION_URL = 'https://bspgw.sf-express.com/std/service';
    public const SANDBOX_URL = 'https://sfapi-sbox.sf-express.com/std/service';
    private $transport;
    public function __construct(?callable $transport = null) { $this->transport = $transport; }
    public static function digest(string $json, string $timestamp, string $checkWord): string
    {
        // 与 Java URLEncoder 一致：空格转 +，星号保留，波浪号编码。
        return base64_encode(md5(str_replace('%2A', '*', urlencode($json . $timestamp . $checkWord)), true));
    }
    public function request(string $serviceCode, array $msgData, array $config): array
    {
        if (!in_array($serviceCode, [self::CREATE_ORDER, self::SEARCH_ORDER, self::CANCEL_ORDER, self::PRINT_PDF], true)) throw new ProviderException('未支持的顺丰接口', false);
        if (!in_array($config['environment'] ?? '', ['sandbox', 'production'], true)) throw new ProviderException('顺丰接口环境未确认，本次未提交', false);
        $partner = trim((string)($config['client_code'] ?? '')); $secret = trim((string)($config['check_word'] ?? ''));
        if ($partner === '' || $secret === '' || $secret === SfConfigService::MASK) throw new ProviderException('顺丰顾客编码或当前环境校验码未配置，本次未提交', false);
        $url = $config['environment'] === 'production' ? self::PRODUCTION_URL : self::SANDBOX_URL;
        $json = json_encode($msgData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = (string)(int)floor(microtime(true) * 1000);
        $uuid = bin2hex(random_bytes(16));
        $requestId = substr($uuid,0,8).'-'.substr($uuid,8,4).'-'.substr($uuid,12,4).'-'.substr($uuid,16,4).'-'.substr($uuid,20);
        $form = ['partnerID' => $partner, 'requestID' => $requestId, 'serviceCode' => $serviceCode, 'timestamp' => $timestamp,
            'msgData' => $json, 'msgDigest' => self::digest($json, $timestamp, $secret)];
        try {
            if ($this->transport) $result = ($this->transport)($url, $form);
            else {
                $response = (new Client())->post($url, ['form_params' => $form, 'connect_timeout' => 5, 'timeout' => 25,
                    'verify' => true, 'allow_redirects' => false, 'http_errors' => false]);
                if ($response->getStatusCode() !== 200) throw new ProviderException('顺丰网络响应异常，结果待核实，请勿重复提交');
                $body = $response->getBody()->read(2097153);
                if (strlen($body) > 2097152) throw new ProviderException('顺丰响应过长，结果待核实');
                $result = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
            }
            if (!is_array($result)) throw new ProviderException('顺丰响应格式异常，结果待核实');
            $code = (string)($result['apiResultCode'] ?? '');
            if ($code !== 'A1000') {
                $known = ['A1001' => '请求参数缺失', 'A1002' => '请求时间已过期，请校准服务器时间', 'A1003' => '请求IP不在顺丰白名单内',
                    'A1004' => '当前应用未开通此接口权限', 'A1005' => '接口流量受限', 'A1006' => '签名校验失败，请核对环境和校验码', 'A1008' => '接口数据解密失败'];
                throw new ProviderException(isset($known[$code]) ? '顺丰拒绝请求（'.$code.'）：'.$known[$code] : '顺丰未返回确定结果，请核实原单，勿重复下单', !isset($known[$code]));
            }
            $business = $result['apiResultData'] ?? null;
            if (is_string($business)) $business = json_decode($business, true, 64, JSON_THROW_ON_ERROR);
            if (!is_array($business) || !array_key_exists('success', $business)) throw new ProviderException('顺丰业务响应不完整，结果待核实');
            $business['_response_id'] = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($result['apiResponseID'] ?? ''));
            return $business;
        } catch (ProviderException $e) { throw $e; }
        catch (\Throwable $e) { throw new ProviderException('顺丰未返回可确认结果，请核实原订单；不要重复下单或切换服务商'); }
    }
}
