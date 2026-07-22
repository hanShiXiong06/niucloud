<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support\print;

use app\service\core\printer\CorePrinterService;
use core\exception\CommonException;
use core\printer\sdk\yilianyun\api\PrintService;
use core\printer\sdk\yilianyun\config\YlyConfig;

/** ERP 打印传输层：业务服务只依赖统一 send 契约。 */
final class ErpPrintProviderManager
{
    public function send(int $siteId, array $printer, array $rendered, string $jobNo): array
    {
        $driver = (string)($printer['driver'] ?? '');
        $config = $this->decode((string)($printer['config_json'] ?? ''));
        if (str_starts_with($driver, 'bluetooth_')) {
            return ['status' => 'waiting_client', 'provider_job_no' => '', 'client_payload' => [
                'driver' => $driver, 'printer_id' => (int)$printer['id'], 'job_no' => $jobNo,
                'format' => $rendered['format'], 'encoding' => $rendered['encoding'], 'content' => $rendered['content'],
                'ble' => $config['ble'] ?? new \stdClass(),
            ]];
        }
        if ($driver === 'xpyun') return $this->xpyun($config, (string)$rendered['content'], $jobNo, (int)($printer['copies'] ?? 1));
        if ($driver === 'feie') return $this->feie($config, (string)$rendered['content'], (int)($printer['copies'] ?? 1));
        if ($driver === 'yilianyun') return $this->yilianyun($siteId, $config, (string)$rendered['content'], $jobNo);
        throw new CommonException('暂不支持该打印驱动');
    }

    private function xpyun(array $config, string $content, string $jobNo, int $copies): array
    {
        $this->required($config, ['user', 'user_key', 'sn']);
        $timestamp = time();
        $payload = ['user' => $config['user'], 'timestamp' => $timestamp, 'sign' => sha1($config['user'] . $config['user_key'] . $timestamp),
            'sn' => $config['sn'], 'content' => $content, 'copies' => max(1, $copies), 'voice' => (string)($config['voice'] ?? '')];
        $data = $this->postJson('https://open.xpyun.net/api/openapi/xprinter/print', $payload);
        if ((int)($data['code'] ?? -1) !== 0) throw new CommonException((string)($data['msg'] ?? '芯烨云打印失败'));
        return ['status' => 'success', 'provider_job_no' => (string)($data['data'] ?? $jobNo), 'client_payload' => []];
    }

    private function feie(array $config, string $content, int $copies): array
    {
        $this->required($config, ['user', 'user_key', 'sn']);
        $timestamp = time();
        $payload = ['USER' => $config['user'], 'UKEY' => $config['user_key'], 'SN' => $config['sn'], 'TIMESTAMP' => $timestamp,
            'SIG' => sha1($config['user'] . $config['user_key'] . $timestamp), 'APINAME' => 'Open_printMsg', 'CONTENT' => $content, 'TIMES' => max(1, $copies)];
        $data = $this->postForm('https://api.feieyun.cn/Api/Open/', $payload);
        if ((int)($data['ret'] ?? -1) !== 0) throw new CommonException((string)($data['msg'] ?? '飞鹅云打印失败'));
        return ['status' => 'success', 'provider_job_no' => (string)($data['data'] ?? ''), 'client_payload' => []];
    }

    private function yilianyun(int $siteId, array $config, string $content, string $jobNo): array
    {
        $this->required($config, ['open_id', 'api_key', 'machine_code']);
        $yly = new YlyConfig((string)$config['open_id'], (string)$config['api_key']);
        $token = (new CorePrinterService())->getYlyToken($siteId, $yly);
        $res = (new PrintService($token, $yly))->index((string)$config['machine_code'], $content, $jobNo);
        if ((int)($res->error ?? -1) !== 0) throw new CommonException((string)($res->error_description ?? '易联云打印失败'));
        return ['status' => 'success', 'provider_job_no' => $jobNo, 'client_payload' => []];
    }

    private function postJson(string $url, array $payload): array
    {
        return $this->request($url, json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}', ['Content-Type: application/json']);
    }

    private function postForm(string $url, array $payload): array
    {
        return $this->request($url, http_build_query($payload), ['Content-Type: application/x-www-form-urlencoded']);
    }

    private function request(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers]);
        $raw = curl_exec($ch); $error = curl_error($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($raw === false || $error !== '') throw new CommonException('打印服务连接失败：' . $error);
        $data = json_decode((string)$raw, true);
        if ($status < 200 || $status >= 300 || !is_array($data)) throw new CommonException('打印服务返回异常（HTTP ' . $status . '）');
        return $data;
    }

    private function required(array $config, array $keys): void
    {
        foreach ($keys as $key) if (trim((string)($config[$key] ?? '')) === '') throw new CommonException('打印机配置不完整：缺少 ' . $key);
    }

    private function decode(string $json): array
    {
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
