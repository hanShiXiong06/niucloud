<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\quote_image;

use core\exception\CommonException;

/** 图片报价来源适配器。只读取公开栏目及原图，不执行页面脚本、不移除水印。 */
class HuishouBaojiaSource
{
    public const REVISION = '20261028-longpng-v2';
    public const DEFAULT_SOURCE_URL = 'https://13303016666.huishoubaojia.com/';
    public const MAX_IMAGE_BYTES = 12582912;
    public const MAX_TOTAL_BYTES = 25165824;
    public const MAX_IMAGES = 10;
    public const MAX_IMAGE_WIDTH = 20000;
    public const MAX_IMAGE_HEIGHT = 60000;
    public const MAX_IMAGE_PIXELS = 60000000;
    public const MAX_NON_PNG_PIXELS = 20000000;
    public const CONNECT_TIMEOUT_SECONDS = 15;
    public const PAGE_TIMEOUT_SECONDS = 30;
    public const IMAGE_TIMEOUT_SECONDS = 120;
    private const MAX_HTML_BYTES = 2097152;
    private const IMAGE_HOST = 'cos.huishoubaojiadan.com';
    private const VALIDATION_TAG = '[longpng-v2]';
    private const PNG_ERROR = 'PNG报价图片不完整或已损坏[longpng-v2]，原报价保持不变';

    public static function normalizeSourceUrl(string $url): string
    {
        $url = trim($url);
        $parts = self::httpsParts($url);
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.huishoubaojia\.com$/D', $parts['host'])
            || !in_array($parts['path'] ?? '', ['', '/'], true) || isset($parts['query'])) {
            throw new CommonException('请填写回收报价站的 HTTPS 根地址，例如 ' . self::DEFAULT_SOURCE_URL);
        }
        return 'https://' . $parts['host'] . '/';
    }

    public function discover(string $sourceUrl): array
    {
        $sourceUrl = self::normalizeSourceUrl($sourceUrl);
        $response = $this->request($sourceUrl, $sourceUrl, false, self::MAX_HTML_BYTES);
        return self::parseCatalog($response['body'], $sourceUrl);
    }

    public function fetchQuoteImages(string $sourceUrl, string $sourceId): array
    {
        $sourceUrl = self::normalizeSourceUrl($sourceUrl);
        if (!preg_match('/^[1-9][0-9]{0,11}$/D', $sourceId)) throw new CommonException('来源报价栏目编号无效，请重新读取栏目');
        $pageUrl = $sourceUrl . $sourceId . '.html';
        $response = $this->request($pageUrl, $sourceUrl, false, self::MAX_HTML_BYTES);
        $urls = self::parseImageUrls($response['body'], $pageUrl);
        $images = [];
        $total = 0;
        foreach ($urls as $url) {
            $remaining = self::MAX_TOTAL_BYTES - $total;
            if ($remaining <= 0) throw new CommonException('该栏目报价图片合计超过 24MB，未替换原报价');
            $response = $this->request($url, $sourceUrl, true, min(self::MAX_IMAGE_BYTES, $remaining));
            $image = self::validateImage($url, $response['body'], $response['headers']['content-type'] ?? '');
            $total += strlen($image['bytes']);
            if ($total > self::MAX_TOTAL_BYTES) throw new CommonException('该栏目报价图片合计超过 24MB，未替换原报价');
            $images[] = $image;
        }
        return $images;
    }

    /** 保留分组，避免“手机/华为”和“平板/华为”被同名误匹配。 */
    public static function parseCatalog(string $html, string $sourceUrl): array
    {
        $sourceUrl = self::normalizeSourceUrl($sourceUrl);
        $xpath = self::xpath($html);
        $items = [];
        foreach ($xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " products-area ")]') as $area) {
            $title = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " main-title ")]', $area)->item(0);
            $group = self::text($title ? $title->textContent : '');
            foreach ($xpath->query('.//a[contains(concat(" ", normalize-space(@class), " "), " item-link ")]', $area) as $link) {
                $url = self::absoluteUrl((string)$link->getAttribute('href'), $sourceUrl);
                self::validateRequestUrl($url, $sourceUrl, false);
                $path = (string)parse_url($url, PHP_URL_PATH);
                if (!preg_match('~^/([1-9][0-9]{0,11})\.html$~D', $path, $match)) continue;
                $label = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " item-text ")]', $link)->item(0);
                $name = self::text($label ? $label->textContent : $link->textContent);
                if ($name === '') continue;
                $id = $match[1];
                if (isset($items[$id])) continue;
                $items[$id] = ['id' => $id, 'name' => $name, 'group' => $group, 'url' => $sourceUrl . $id . '.html'];
                if (count($items) > 200) throw new CommonException('来源报价栏目数量异常，请核对页面后重试');
            }
        }
        if (!$items) throw new CommonException('未读取到报价栏目，来源页面可能改版或暂时不可用');
        return array_values($items);
    }

    public static function parseImageUrls(string $html, string $pageUrl): array
    {
        $parts = self::httpsParts($pageUrl);
        $sourceUrl = self::normalizeSourceUrl('https://' . $parts['host'] . '/');
        self::validateRequestUrl($pageUrl, $sourceUrl, false);
        $xpath = self::xpath($html);
        $urls = [];
        foreach ($xpath->query('//img[contains(concat(" ", normalize-space(@class), " "), " image_box ")]') as $image) {
            $raw = trim((string)$image->getAttribute('src'));
            if ($raw === '') $raw = trim((string)$image->getAttribute('data-src'));
            $url = self::absoluteUrl($raw, $pageUrl);
            self::validateRequestUrl($url, $sourceUrl, true);
            $urls[$url] = $url;
        }
        if (!$urls) throw new CommonException('来源栏目未找到报价图片，可能已撤下或页面改版；原报价保持不变');
        if (count($urls) > self::MAX_IMAGES) throw new CommonException('该栏目报价图片超过 10 张，未替换原报价');
        return array_values($urls);
    }

    public static function validateImage(string $url, string $bytes, string $contentType = ''): array
    {
        if ($bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw new CommonException('报价图片为空或超过12MB（' . self::imageDiagnostic($bytes, $contentType) . '），原报价保持不变');
        }
        $info = @getimagesizefromstring($bytes);
        $mimes = [IMAGETYPE_PNG => 'image/png', IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_WEBP => 'image/webp'];
        if (!is_array($info) || !isset($mimes[$info[2] ?? 0]) || $info[0] <= 0 || $info[1] <= 0) {
            throw new CommonException('来源未返回有效PNG/JPEG/WebP报价图片（' . self::imageDiagnostic($bytes, $contentType, is_array($info) ? $info : []) . '），原报价保持不变');
        }
        $width = (int)$info[0]; $height = (int)$info[1];
        $dimensions = $width . '×' . $height;
        if ($width > self::MAX_IMAGE_WIDTH || $height > self::MAX_IMAGE_HEIGHT) {
            throw new CommonException('报价图片尺寸' . $dimensions . '超过支持范围（最大宽20000、高60000）' . self::VALIDATION_TAG . '，原报价保持不变');
        }
        if ($width * $height > self::MAX_IMAGE_PIXELS) {
            throw new CommonException('报价图片' . $dimensions . '总像素超过6000万' . self::VALIDATION_TAG . '，原报价保持不变');
        }
        if ($info[2] !== IMAGETYPE_PNG && $width * $height > self::MAX_NON_PNG_PIXELS) {
            throw new CommonException('报价图片' . $dimensions . '为JPEG/WebP，总像素超过2000万；请使用PNG长图' . self::VALIDATION_TAG . '，原报价保持不变');
        }
        $declared = strtolower(trim(explode(';', $contentType)[0]));
        if ($declared !== '' && $declared !== 'application/octet-stream' && !in_array($declared, $mimes, true)) {
            throw new CommonException('来源响应的MIME不是图片（' . self::imageDiagnostic($bytes, $contentType, $info) . '），原报价保持不变');
        }
        if ($info[2] === IMAGETYPE_PNG) {
            // 报价长图仅原样转存。流式核实压缩数据，不创建整张GD位图，避免长图耗尽队列内存。
            self::validatePng($bytes, $width, $height);
        } elseif (function_exists('imagecreatefromstring')) {
            self::assertDecodeMemory($width, $height, strlen($bytes));
            $decoded = @imagecreatefromstring($bytes);
            if ($decoded === false) throw new CommonException('报价图片不完整或已损坏' . self::VALIDATION_TAG . '，原报价保持不变');
            imagedestroy($decoded);
        }
        return ['url' => $url, 'bytes' => $bytes, 'mime' => $mimes[$info[2]], 'hash' => hash('sha256', $bytes),
            'width' => (int)$info[0], 'height' => (int)$info[1]];
    }

    /** 只暴露安全的响应元信息，不输出正文、来源地址、签名或凭据。 */
    private static function imageDiagnostic(string $bytes, string $contentType, array $info = []): string
    {
        $mime = strtolower(trim(explode(';', $contentType, 2)[0]));
        if (strlen($mime) > 80 || !preg_match('~^[a-z0-9!#$&^_.+-]+/[a-z0-9!#$&^_.+-]+$~D', $mime)) $mime = 'unknown';
        return self::VALIDATION_TAG . ' 字节=' . strlen($bytes) . '，MIME=' . $mime
            . (isset($info[2]) ? '，图像类型=' . (int)$info[2] : '');
    }

    /** 验证PNG容器、CRC、zlib结束状态及逐扫描行长度；解压块有界，不保留全图像素。 */
    private static function validatePng(string $bytes, int $width, int $height): void
    {
        foreach (['inflate_init', 'inflate_add', 'inflate_get_status', 'inflate_get_read_len'] as $function) {
            if (!function_exists($function)) {
                throw new CommonException('服务器缺少PHP zlib流式校验支持（inflate_*）' . self::VALIDATION_TAG . '，原报价保持不变');
            }
        }
        if (substr($bytes, 0, 8) !== "\x89PNG\r\n\x1a\n") throw new CommonException(self::PNG_ERROR);
        $length = strlen($bytes); $offset = 8; $chunks = 0;
        $seenHeader = false; $seenPalette = false; $seenData = false; $dataEnded = false;
        $color = -1; $depth = 0; $passes = []; $pass = 0; $row = 0; $column = 0;
        $inflater = null; $fed = 0; $streamEnded = false;
        while ($offset < $length) {
            if (++$chunks > 10000 || $length - $offset < 12) throw new CommonException(self::PNG_ERROR);
            $size = (int)unpack('Nsize', substr($bytes, $offset, 4))['size'];
            $type = substr($bytes, $offset + 4, 4); $start = $offset + 8;
            if ($size > $length - $offset - 12 || !preg_match('/^[A-Za-z]{4}$/D', $type)
                || (ord($type[2]) & 32) !== 0 || (!$seenHeader && $type !== 'IHDR')) throw new CommonException(self::PNG_ERROR);
            $crc = hash_init('crc32b'); hash_update($crc, $type);
            for ($part = 0; $part < $size; $part += 8192) hash_update($crc, substr($bytes, $start + $part, min(8192, $size - $part)));
            if (!hash_equals(substr($bytes, $start + $size, 4), hash_final($crc, true))) throw new CommonException(self::PNG_ERROR);
            $offset += $size + 12;
            if ($seenData && $type !== 'IDAT') $dataEnded = true;
            if ($type === 'IHDR') {
                if ($seenHeader || $size !== 13) throw new CommonException(self::PNG_ERROR);
                $header = unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace', substr($bytes, $start, 13));
                $color = (int)$header['color']; $depth = (int)$header['depth'];
                $depths = [0 => [1, 2, 4, 8, 16], 2 => [8, 16], 3 => [1, 2, 4, 8], 4 => [8, 16], 6 => [8, 16]];
                if ($header['width'] !== $width || $header['height'] !== $height || !isset($depths[$color])
                    || !in_array($depth, $depths[$color], true) || $header['compression'] !== 0 || $header['filter'] !== 0
                    || !in_array($header['interlace'], [0, 1], true)) throw new CommonException(self::PNG_ERROR);
                $channels = [0 => 1, 2 => 3, 3 => 1, 4 => 2, 6 => 4];
                $steps = $header['interlace'] === 1
                    ? [[0, 0, 8, 8], [4, 0, 8, 8], [0, 4, 4, 8], [2, 0, 4, 4], [0, 2, 2, 4], [1, 0, 2, 2], [0, 1, 1, 2]]
                    : [[0, 0, 1, 1]];
                foreach ($steps as [$x, $y, $dx, $dy]) {
                    if ($width <= $x || $height <= $y) continue;
                    $passWidth = intdiv($width - $x + $dx - 1, $dx);
                    $passHeight = intdiv($height - $y + $dy - 1, $dy);
                    $passes[] = [$passHeight, 1 + intdiv($passWidth * $channels[$color] * $depth + 7, 8)];
                }
                $seenHeader = true;
            } elseif ($type === 'PLTE') {
                if ($seenPalette || $seenData || in_array($color, [0, 4], true) || $size === 0 || $size > 768
                    || $size % 3 !== 0 || ($color === 3 && intdiv($size, 3) > (1 << $depth))) throw new CommonException(self::PNG_ERROR);
                $seenPalette = true;
            } elseif ($type === 'IDAT') {
                if ($dataEnded || ($color === 3 && !$seenPalette) || ($streamEnded && $size > 0)) throw new CommonException(self::PNG_ERROR);
                $seenData = true;
                if ($inflater === null) {
                    $inflater = @inflate_init(ZLIB_ENCODING_DEFLATE);
                    if ($inflater === false) throw new CommonException(self::PNG_ERROR);
                }
                // 1KB压缩输入即使高压缩比也只产生有界临时输出，不能把完整IDAT拼起来再解压。
                for ($part = 0; $part < $size; $part += 1024) {
                    if ($streamEnded) throw new CommonException(self::PNG_ERROR);
                    $input = substr($bytes, $start + $part, min(1024, $size - $part));
                    $fed += strlen($input);
                    $output = @inflate_add($inflater, $input, ZLIB_SYNC_FLUSH);
                    if ($output === false) throw new CommonException(self::PNG_ERROR);
                    self::consumePngRows($output, $passes, $pass, $row, $column);
                    $streamEnded = inflate_get_status($inflater) === ZLIB_STREAM_END;
                    if ($streamEnded && inflate_get_read_len($inflater) !== $fed) throw new CommonException(self::PNG_ERROR);
                }
            } elseif ($type === 'IEND') {
                if ($size !== 0 || !$seenData || !$streamEnded || $pass !== count($passes) || $row !== 0 || $column !== 0
                    || $offset !== $length) throw new CommonException(self::PNG_ERROR);
                return;
            } elseif ((ord($type[0]) & 32) === 0) {
                // 未知关键chunk不能按有效PNG静默接受；辅助元数据chunk仍核对边界及CRC。
                throw new CommonException(self::PNG_ERROR);
            }
        }
        throw new CommonException(self::PNG_ERROR);
    }

    private static function consumePngRows(string $output, array $passes, int &$pass, int &$row, int &$column): void
    {
        $offset = 0; $length = strlen($output);
        while ($offset < $length) {
            if (!isset($passes[$pass])) throw new CommonException(self::PNG_ERROR);
            [$rows, $stride] = $passes[$pass];
            if ($column === 0 && ord($output[$offset]) > 4) throw new CommonException(self::PNG_ERROR);
            $take = min($length - $offset, $stride - $column);
            $offset += $take; $column += $take;
            if ($column === $stride) {
                $column = 0;
                if (++$row === $rows) { $row = 0; $pass++; }
            }
        }
    }

    /** 非PNG仍用GD复核，但不把低内存限制变成消费者致命退出。 */
    private static function assertDecodeMemory(int $width, int $height, int $bytes): void
    {
        $setting = trim((string)ini_get('memory_limit'));
        if ($setting === '-1' || $setting === '') return;
        if (!preg_match('/^(\d+(?:\.\d+)?)\s*([KMG])?$/iD', $setting, $match)) return;
        $factors = ['' => 1, 'K' => 1024, 'M' => 1048576, 'G' => 1073741824];
        $limit = (float)$match[1] * $factors[strtoupper($match[2] ?? '')];
        $estimate = $width * $height * 8 + $bytes * 2 + 16777216;
        if (memory_get_usage(true) + $estimate > $limit) {
            throw new CommonException('报价图片' . $width . '×' . $height . '校验所需内存不足；请使用PNG长图或调整PHP内存' . self::VALIDATION_TAG . '，原报价保持不变');
        }
    }

    /** 只允许已知页面路径与报价图域；每次跳转都重新执行。 */
    public static function validateRequestUrl(string $url, string $sourceUrl, bool $image): void
    {
        $parts = self::httpsParts($url);
        $source = self::httpsParts(self::normalizeSourceUrl($sourceUrl));
        $path = $parts['path'] ?? '/';
        if ($image) {
            $decoded = rawurldecode($path);
            if ($parts['host'] !== self::IMAGE_HOST || strpos($decoded, '/quotation/') !== 0
                || strpos($decoded, '\\') !== false || preg_match('~(?:^|/)\.\.?(?:/|$)~', $decoded)) {
                throw new CommonException('报价图片地址不属于已支持的来源，请核对栏目');
            }
        } elseif ($parts['host'] !== $source['host'] || ($path !== '/' && !preg_match('~^/[1-9][0-9]{0,11}\.html$~D', $path))) {
            throw new CommonException('来源页面跳转到不支持的地址，已停止采集');
        }
    }

    protected function request(string $url, string $sourceUrl, bool $image, int $maxBytes): array
    {
        // 重定向沿用同一份等待预算，避免慢图多次跳转把一个栏目拖到失联超时。
        $timeout = $image ? self::IMAGE_TIMEOUT_SECONDS : self::PAGE_TIMEOUT_SECONDS;
        $deadline = $this->requestClock() + $timeout;
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            self::validateRequestUrl($url, $sourceUrl, $image);
            $host = (string)parse_url($url, PHP_URL_HOST);
            $ip = $this->resolvePublicIp($host);
            $remaining = (int)ceil($deadline - $this->requestClock());
            if ($remaining <= 0) throw new CommonException(self::timeoutMessage($image));
            $response = $this->sendPinnedRequest($url, $ip, $maxBytes, $remaining);
            if ($this->requestClock() > $deadline) throw new CommonException(self::timeoutMessage($image));
            if (strlen((string)($response['body'] ?? '')) > $maxBytes) throw new CommonException('来源响应超过安全大小限制，未更新报价');
            $status = (int)($response['status'] ?? 0);
            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                if ($redirect === 3) throw new CommonException('来源页面跳转次数过多，已停止采集');
                $url = self::absoluteUrl((string)($response['headers']['location'] ?? ''), $url);
                continue;
            }
            if ($status !== 200) throw new CommonException('来源返回 HTTP ' . $status . '，原报价保持不变');
            return $response;
        }
        throw new CommonException('来源页面不可用');
    }

    /** 单调时钟不受服务器校时影响；也方便隔离测试模拟慢图而不真正等待。 */
    protected function requestClock(): float { return hrtime(true) / 1000000000; }

    private static function timeoutMessage(bool $image): string
    {
        return $image
            ? '报价图片连接或下载超时（连接最多15秒，下载最多120秒）；原报价保持不变，请稍后重试'
            : '报价来源页面连接或读取超时（连接最多15秒，读取最多30秒）；原报价保持不变，请稍后重试';
    }

    /** DNS 结果全部检查，再固定到一个公开地址；关闭环境代理以免绕开 IP 固定。 */
    protected function resolvePublicIp(string $host): string
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        $ips = [];
        foreach (is_array($records) ? $records : [] as $record) {
            $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
            if ($ip === '') continue;
            if (!self::isPublicIp($ip)) throw new CommonException('来源域名解析到非公开网络地址，已停止采集');
            $ips[] = $ip;
        }
        if (!$ips) throw new CommonException('暂时无法解析报价来源域名，请稍后重试');
        usort($ips, static fn(string $a, string $b): int => (int)(strpos($a, ':') !== false) <=> (int)(strpos($b, ':') !== false));
        return $ips[0];
    }

    public static function isPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
        if (strpos($ip, ':') !== false) {
            if (!self::inCidr($ip, '2000::', 3)) return false;
            foreach (['2001:db8::' => 32, '2001::' => 23, '2002::' => 16] as $network => $bits) if (self::inCidr($ip, $network, $bits)) return false;
        } else {
            foreach (['0.0.0.0' => 8, '10.0.0.0' => 8, '100.64.0.0' => 10, '127.0.0.0' => 8, '169.254.0.0' => 16,
                '172.16.0.0' => 12, '192.0.0.0' => 24, '192.0.2.0' => 24, '192.88.99.0' => 24, '192.168.0.0' => 16,
                '198.18.0.0' => 15, '198.51.100.0' => 24, '203.0.113.0' => 24, '224.0.0.0' => 4, '240.0.0.0' => 4] as $network => $bits) {
                if (self::inCidr($ip, $network, $bits)) return false;
            }
        }
        return true;
    }

    protected function sendPinnedRequest(string $url, string $ip, int $maxBytes, int $timeoutSeconds): array
    {
        if (!function_exists('curl_init')) throw new CommonException('服务器未启用 PHP cURL，无法采集报价图片');
        $host = (string)parse_url($url, PHP_URL_HOST);
        $headers = [];
        $body = '';
        $tooLarge = false;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => min(self::CONNECT_TIMEOUT_SECONDS, $timeoutSeconds),
            CURLOPT_TIMEOUT => $timeoutSeconds, CURLOPT_NOSIGNAL => true,
            CURLOPT_PROXY => '', CURLOPT_RESOLVE => [$host . ':443:' . (strpos($ip, ':') !== false ? '[' . $ip . ']' : $ip)],
            CURLOPT_USERAGENT => 'HSX-Recycle-QuoteImageSync/1.0',
            CURLOPT_HTTPHEADER => ['Accept: image/png,image/jpeg,image/webp,text/html;q=0.8', 'Accept-Encoding: identity'],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers, &$tooLarge, $maxBytes): int {
                if (preg_match('~^HTTP/~i', $line)) $headers = [];
                $position = strpos($line, ':');
                if ($position !== false) {
                    $name = strtolower(trim(substr($line, 0, $position)));
                    $headers[$name] = trim(substr($line, $position + 1));
                    if ($name === 'content-length' && (float)$headers[$name] > $maxBytes) { $tooLarge = true; return 0; }
                }
                return strlen($line);
            },
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body, &$tooLarge, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) { $tooLarge = true; return 0; }
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $success = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $errorNumber = curl_errno($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($tooLarge) throw new CommonException('来源响应超过安全大小限制，未更新报价');
        if ($success === false && $errorNumber === CURLE_OPERATION_TIMEDOUT) {
            throw new CommonException(self::timeoutMessage($host === self::IMAGE_HOST));
        }
        if ($success === false) throw new CommonException('连接报价来源失败，请检查服务器网络与 TLS 证书：' . substr($error, 0, 160));
        return ['status' => $status, 'headers' => $headers, 'body' => $body];
    }

    private static function httpsParts(string $url): array
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) throw new CommonException('来源地址格式不正确');
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || isset($parts['fragment'])) {
            throw new CommonException('来源地址须使用 HTTPS，不能包含账号、端口或锚点');
        }
        $parts['host'] = strtolower($parts['host']);
        return $parts;
    }

    private static function absoluteUrl(string $url, string $base): string
    {
        $url = html_entity_decode(trim($url), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($url === '') throw new CommonException('来源返回空的链接地址');
        if (strpos($url, '//') === 0) return 'https:' . $url;
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) return $url;
        $parts = self::httpsParts($base);
        $origin = 'https://' . $parts['host'];
        if ($url[0] === '/') return $origin . $url;
        return $origin . rtrim(str_replace('\\', '/', dirname($parts['path'] ?? '/')), '/') . '/' . $url;
    }

    private static function xpath(string $html): \DOMXPath
    {
        if ($html === '' || strlen($html) > self::MAX_HTML_BYTES) throw new CommonException('来源页面为空或内容过大');
        if (!class_exists(\DOMDocument::class)) throw new CommonException('服务器未启用 PHP DOM 扩展，无法读取报价栏目');
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try { $loaded = $document->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING); }
        finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
        if (!$loaded) throw new CommonException('报价来源页面无法解析');
        return new \DOMXPath($document);
    }

    private static function text(string $value): string
    {
        return trim((string)preg_replace('/\s+/u', ' ', $value));
    }

    private static function inCidr(string $ip, string $network, int $bits): bool
    {
        $binary = inet_pton($ip); $base = inet_pton($network);
        if ($binary === false || $base === false || strlen($binary) !== strlen($base)) return false;
        $whole = intdiv($bits, 8); $remaining = $bits % 8;
        if (substr($binary, 0, $whole) !== substr($base, 0, $whole)) return false;
        return $remaining === 0 || ((ord($binary[$whole]) & (255 << (8 - $remaining))) === (ord($base[$whole]) & (255 << (8 - $remaining))));
    }
}
