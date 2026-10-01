<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use GuzzleHttp\Client;

/** 仅下载顺丰打印响应中已加密保存的文件，不能当作任意 URL 代理。 */
final class SfPdfDownloader
{
    public const HOST = 'eos-scp-core-shenzhen-futian1-oss.sf-express.com';
    public const MAX_BYTES = 10485760;
    private $transport;

    public function __construct(?callable $transport = null) { $this->transport = $transport; }

    public function download(array $artifact): array
    {
        $url = (string)($artifact['url'] ?? '');
        $token = (string)($artifact['token'] ?? '');
        $parts = parse_url($url);
        if (strlen($url) > 4096 || preg_match('/[\x00-\x20]/', $url) || !is_array($parts)
            || ($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== self::HOST
            || (isset($parts['port']) && (int)$parts['port'] !== 443) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || $token === '' || strlen($token) > 4096 || preg_match('/[\r\n\x00]/', $token)) {
            throw new ProviderException('顺丰面单文件地址或下载凭据无效，请重新获取原单 PDF', false);
        }
        if ((int)($artifact['expires_at'] ?? 0) <= time()) throw new ProviderException('顺丰 PDF 下载凭据已过期，请重新获取原单面单，不要重新取号', false);
        try {
            $headers = ['X-Auth-token' => $token, 'Accept' => 'application/pdf'];
            if ($this->transport) {
                $result = ($this->transport)($url, $headers);
            } else {
                $response = (new Client())->get($url, ['headers' => $headers, 'connect_timeout' => 5, 'timeout' => 25,
                    'verify' => true, 'http_errors' => false, 'allow_redirects' => false, 'stream' => true]);
                $stream = $response->getBody();
                $body = '';
                while (!$stream->eof() && strlen($body) <= self::MAX_BYTES) {
                    $chunk = $stream->read(min(65536, self::MAX_BYTES + 1 - strlen($body)));
                    if ($chunk === '') break;
                    $body .= $chunk;
                }
                $result = ['status' => $response->getStatusCode(), 'content_type' => $response->getHeaderLine('Content-Type'), 'body' => $body];
            }
            $body = $result['body'] ?? null;
            $mime = strtolower(trim(explode(';', (string)($result['content_type'] ?? ''))[0]));
            if ((int)($result['status'] ?? 0) !== 200 || !is_string($body) || strlen($body) > self::MAX_BYTES
                || !in_array($mime, ['application/pdf', 'application/octet-stream'], true) || substr($body, 0, 5) !== '%PDF-') {
                throw new ProviderException('顺丰未返回有效 PDF 文件，原运单保留，请稍后重新获取面单', false);
            }
            return ['body' => $body, 'mime' => 'application/pdf'];
        } catch (ProviderException $e) { throw $e; }
        catch (\Throwable $e) {
            throw new ProviderException('顺丰 PDF 暂时无法下载，原运单保留，请稍后重试；未重新取号', false);
        }
    }
}
