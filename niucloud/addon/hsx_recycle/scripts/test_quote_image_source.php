<?php
declare(strict_types=1);

namespace core\exception { class CommonException extends \RuntimeException {} }
namespace think\facade { class Log { public static array $warnings = []; public static function warning(...$args): void { self::$warnings[] = $args; } } }
namespace app\service\core\upload {
    class CoreFileService {
        protected static $storage_type = 'tencent';
        public static $testDriver;
        public static string $testType = 'tencent';
        public function driver(int $siteId, string $storageType = '') { self::$storage_type = self::$testType; return self::$testDriver; }
    }
}
namespace {
    function url_to_path(string $path): string { return '/does-not-exist/' . $path; }
    require __DIR__ . '/../app/service/core/quote_image/HuishouBaojiaSource.php';
    require __DIR__ . '/../app/service/core/quote_image/QuoteImageStorage.php';

    use addon\hsx_recycle\app\service\core\quote_image\HuishouBaojiaSource as Source;
    use addon\hsx_recycle\app\service\core\quote_image\QuoteImageStorage as Storage;
    use app\service\core\upload\CoreFileService;

    $checks = 0;
    function check(string $label, callable $test): void { global $checks; $test(); $checks++; echo 'PASS ' . $label . PHP_EOL; }
    function same($actual, $expected): void { if ($actual !== $expected) throw new \RuntimeException('Expected ' . var_export($expected, true) . '; got ' . var_export($actual, true)); }
    function rejects(callable $test): void { try { $test(); } catch (\core\exception\CommonException $e) { return; } throw new \RuntimeException('Expected a safe rejection'); }
    function rejectionMessage(callable $test): string {
        try { $test(); } catch (\core\exception\CommonException $e) { return $e->getMessage(); }
        throw new \RuntimeException('Expected a safe rejection');
    }
    function timesOut(callable $test): void {
        try { $test(); } catch (\core\exception\CommonException $e) {
            same(strpos($e->getMessage(), '超时') !== false, true);
            same(strpos($e->getMessage(), '原报价保持不变') !== false, true);
            return;
        }
        throw new \RuntimeException('Expected an explicit timeout rejection');
    }
    function png(): string {
        $chunk = static fn(string $type, string $bytes): string => pack('N', strlen($bytes)) . $type . $bytes . pack('N', crc32($type . $bytes));
        return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', 1, 1, 8, 2, 0, 0, 0))
            . $chunk('IDAT', gzcompress("\x00\xff\xff\xff")) . $chunk('IEND', '');
    }
    function pngChunk(string $type, string $data): string {
        return pack('N', strlen($data)) . $type . $data . hash('crc32b', $type . $data, true);
    }
    function pngContainer(int $width, int $height, string $compressed, int $interlace = 0): string {
        return "\x89PNG\r\n\x1a\n" . pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, $interlace))
            . pngChunk('IDAT', $compressed) . pngChunk('IEND', '');
    }
    /** 逐行压缩生成真实长图，不创建GD位图或整张未压缩扫描行字符串。 */
    function pngSized(int $width, int $height, int $interlace = 0): string {
        $stream = deflate_init(ZLIB_ENCODING_DEFLATE, ['level' => 6]);
        if ($stream === false) throw new \RuntimeException('Fixture deflate init failed');
        $compressed = '';
        $passes = $interlace ? [[0, 0, 8, 8], [4, 0, 8, 8], [0, 4, 4, 8], [2, 0, 4, 4], [0, 2, 2, 4], [1, 0, 2, 2], [0, 1, 1, 2]] : [[0, 0, 1, 1]];
        foreach ($passes as [$x, $y, $dx, $dy]) {
            $passWidth = $width > $x ? intdiv($width - $x + $dx - 1, $dx) : 0;
            $passHeight = $height > $y ? intdiv($height - $y + $dy - 1, $dy) : 0;
            if (!$passWidth || !$passHeight) continue;
            $row = "\0" . str_repeat("\xff", $passWidth * 3);
            for ($i = 0; $i < $passHeight; $i++) $compressed .= deflate_add($stream, $row, ZLIB_NO_FLUSH);
        }
        $compressed .= deflate_add($stream, '', ZLIB_FINISH);
        return pngContainer($width, $height, $compressed, $interlace);
    }
    if (in_array('--long-png-memory', $argv ?? [], true)) {
        $bytes = pngSized(2000, 15000);
        $image = Source::validateImage('https://cos.huishoubaojiadan.com/quotation/long.png', $bytes, 'image/png');
        same($image['width'], 2000); same($image['height'], 15000); same($image['bytes'], $bytes);
        echo json_encode(['width' => $image['width'], 'height' => $image['height'], 'peak_bytes' => memory_get_peak_usage(true)]), PHP_EOL;
        exit(0);
    }
    class FakeSource extends Source {
        public array $responses = [];
        public array $requests = [];
        public float $clock = 0.0;
        public array $dnsElapsed = [];
        protected function requestClock(): float { return $this->clock; }
        protected function resolvePublicIp(string $host): string {
            $this->clock += (float)(array_shift($this->dnsElapsed) ?? 0);
            return '8.8.8.8';
        }
        protected function sendPinnedRequest(string $url, string $ip, int $maxBytes, int $timeoutSeconds): array {
            $this->requests[] = [$url, $ip, $maxBytes, $timeoutSeconds];
            if (!array_key_exists($url, $this->responses)) throw new \RuntimeException('Unexpected test HTTP request: ' . $url);
            $this->clock += (float)($this->responses[$url]['elapsed'] ?? 0);
            return $this->responses[$url];
        }
    }
    class FakeDriver {
        public bool $success = true;
        public array $writes = [];
        public string $prefix = 'https://storage.example/';
        public $beforeWrite;
        public ?string $error = null;
        public function base64(string $bytes, string $key): bool {
            if ($this->beforeWrite) ($this->beforeWrite)($key);
            if ($this->error !== null) throw new \RuntimeException($this->error);
            $this->writes[] = [$key, base64_decode($bytes, true)]; return $this->success;
        }
        public function getUrl(string $key): string { return $this->prefix . $key; }
    }
    class IsolatedStorage extends Storage {
        public array $prepared = [];
        public bool $denyDirectory = false;
        protected function prepareLocalDirectory(string $key): void {
            $this->prepared[] = $key;
            if ($this->denyDirectory) throw new \RuntimeException('/private/server/path secret-key=fixture');
        }
    }

    $sourceUrl = Source::DEFAULT_SOURCE_URL;
    $imageUrl = 'https://cos.huishoubaojiadan.com/quotation/webdocking/web_watermark/794/1.png?ts=2026102812';
    $catalogHtml = file_get_contents(__DIR__ . '/fixtures/quote_image/catalog.html');
    $detailHtml = file_get_contents(__DIR__ . '/fixtures/quote_image/detail.html');

    check('来源根地址规范化', static function () use ($sourceUrl): void { same(Source::normalizeSourceUrl(' HTTPS://13303016666.HUISHOUBAOJIA.COM '), $sourceUrl); });
    foreach (['http://13303016666.huishoubaojia.com/', 'https://127.0.0.1/', 'https://a.huishoubaojia.com.evil.test/',
        'https://a.huishoubaojia.com:443/', 'https://u:p@a.huishoubaojia.com/', 'https://a.huishoubaojia.com/path',
        'https://a.huishoubaojia.com/?x=1', "https://a.huishoubaojia.com/\n", 'https://a.huishoubaojia.com/#hash',
        'https://a.huishoubaojia.com\\@evil.test/'] as $url) {
        // 输入框首尾空白会规范化；URL 中的控制字符、路径和凭据不会被接受。
        if (str_ends_with($url, "/\n")) $url = "https://a.huishoubaojia.com/\npath";
        check('拒绝非根地址或非安全来源 ' . json_encode($url), static fn() => rejects(static fn() => Source::normalizeSourceUrl($url)));
    }
    check('栏目分组保留、名称不混淆且重复 ID 去重', static function () use ($catalogHtml, $sourceUrl): void {
        $items = Source::parseCatalog($catalogHtml, $sourceUrl);
        same(count($items), 4); same($items[0]['id'], '16576'); same($items[0]['name'], '苹果有保');
        same($items[2]['name'], '华为'); same($items[3]['name'], '华为');
        same($items[2]['group'], '二手手机回收报价'); same($items[3]['group'], '二手平板/电脑回收报价');
        same($items[3]['url'], $sourceUrl . '16591.html');
    });
    check('空栏目不是成功', static fn() => rejects(static fn() => Source::parseCatalog('<html>维护中</html>', $sourceUrl)));
    check('栏目外链阻断', static fn() => rejects(static fn() => Source::parseCatalog(str_replace('/16576.html', 'https://evil.test/16576.html', $catalogHtml), $sourceUrl)));
    check('只读取报价图且去重，不采图标', static function () use ($detailHtml, $sourceUrl, $imageUrl): void { same(Source::parseImageUrls($detailHtml, $sourceUrl . '16576.html'), [$imageUrl]); });
    check('报价图超过 10 张整组失败', static function () use ($sourceUrl): void {
        $html = '';
        for ($i = 0; $i < 11; $i++) $html .= '<img class="image_box" src="https://cos.huishoubaojiadan.com/quotation/' . $i . '.png">';
        rejects(static fn() => Source::parseImageUrls($html, $sourceUrl . '16576.html'));
    });
    check('空图片组不清空报价', static fn() => rejects(static fn() => Source::parseImageUrls('<img src="logo.png">', $sourceUrl . '16576.html')));
    foreach (['http://cos.huishoubaojiadan.com/quotation/a.png', 'https://127.0.0.1/quotation/a.png',
        'https://cos.huishoubaojiadan.com:443/quotation/a.png', 'https://cos.huishoubaojiadan.com/other/a.png',
        'https://cos.huishoubaojiadan.com/quotation/../other/a.png', 'https://cos.huishoubaojiadan.com/quotation/%2e%2e/other/a.png',
        'https://evil.test/quotation/a.png'] as $url) {
        check('报价图片地址限制 ' . $url, static fn() => rejects(static fn() => Source::validateRequestUrl($url, $sourceUrl, true)));
    }
    foreach (['127.0.0.1', '10.1.1.1', '172.16.2.3', '192.168.1.1', '169.254.169.254', '100.64.1.1',
        '192.0.2.2', '192.88.99.1', '198.18.1.1', '203.0.113.1', '224.1.1.1', '::1', 'fe80::1', 'fd00::1',
        '::ffff:127.0.0.1', '2001:db8::1', '2001:20::1', '2002:7f00:1::1'] as $ip) {
        check('拒绝非公网 IP ' . $ip, static fn() => same(Source::isPublicIp($ip), false));
    }
    check('公网 IPv4 与 IPv6 可用', static function (): void { same(Source::isPublicIp('8.8.8.8'), true); same(Source::isPublicIp('2606:4700:4700::1111'), true); });
    check('有效原图保留原始字节与内容哈希', static function () use ($imageUrl): void {
        $bytes = png(); $image = Source::validateImage($imageUrl, $bytes, 'image/png; charset=binary');
        same($image['bytes'], $bytes); same($image['mime'], 'image/png'); same($image['hash'], hash('sha256', $bytes)); same($image['width'], 1); same($image['height'], 1);
    });
    check('合法3000万像素长PNG不再被旧2000万像素门槛误拒，保留原图字节', static function () use ($imageUrl): void {
        $bytes = pngSized(2000, 15000); $image = Source::validateImage($imageUrl, $bytes, 'image/png');
        same($image['width'], 2000); same($image['height'], 15000); same($image['bytes'], $bytes);
        same($image['hash'], hash('sha256', $bytes)); same(strlen($bytes) < Source::MAX_IMAGE_BYTES, true);
    });
    check('合法Adam7交错PNG仍可读取，不把正常扫描行误判为损坏', static function () use ($imageUrl): void {
        foreach ([[1, 1], [3, 5], [8, 8], [13, 17]] as [$width, $height]) {
            $bytes = pngSized($width, $height, 1); $image = Source::validateImage($imageUrl, $bytes, 'image/png');
            same($image['width'], $width); same($image['height'], $height); same($image['bytes'], $bytes);
        }
    });
    check('PNG上限6000万像素，非PNG保留2000万，宽高边界不无限放开', static function (): void {
        same(Source::MAX_IMAGE_PIXELS, 60000000); same(Source::MAX_NON_PNG_PIXELS, 20000000);
        same(Source::MAX_IMAGE_WIDTH, 20000); same(Source::MAX_IMAGE_HEIGHT, 60000);
    });
    check('超6000万像素明确提示实际宽高，不再冒充图片格式错误', static function () use ($imageUrl): void {
        $bytes = pngContainer(2000, 30001, gzcompress("\0\xff\xff\xff"));
        $message = rejectionMessage(static fn() => Source::validateImage($imageUrl, $bytes, 'image/png'));
        same(strpos($message, '2000') !== false, true); same(strpos($message, '30001') !== false, true);
        same(strpos($message, '像素') !== false, true);
    });
    check('超宽或超高的PNG单独提示尺寸与实际宽高', static function () use ($imageUrl): void {
        foreach ([[20001, 1], [1, 60001]] as [$width, $height]) {
            $bytes = pngContainer($width, $height, gzcompress("\0\xff\xff\xff"));
            $message = rejectionMessage(static fn() => Source::validateImage($imageUrl, $bytes, 'image/png'));
            same(strpos($message, (string)$width) !== false, true); same(strpos($message, (string)$height) !== false, true);
            same(strpos($message, '尺寸') !== false, true);
        }
    });
    check('截断IDAT或缺少IEND的PNG不会作为长图放行', static function () use ($imageUrl): void {
        rejects(static fn() => Source::validateImage($imageUrl, substr(png(), 0, -20), 'image/png'));
        rejects(static fn() => Source::validateImage($imageUrl, substr(png(), 0, -12), 'image/png'));
    });
    check('PNG的CRC损坏与IEND后额外内容被拒绝', static function () use ($imageUrl): void {
        $bytes = png(); $position = strlen($bytes) - 13; $bytes[$position] = chr(ord($bytes[$position]) ^ 1);
        rejects(static fn() => Source::validateImage($imageUrl, $bytes, 'image/png'));
        rejects(static fn() => Source::validateImage($imageUrl, png() . 'extra', 'image/png'));
    });
    check('即使CRC正确，错误或截断的zlib压缩数据也不能上传', static function () use ($imageUrl): void {
        rejects(static fn() => Source::validateImage($imageUrl, pngContainer(1, 1, 'not-zlib'), 'image/png'));
        $compressed = gzcompress("\0\xff\xff\xff");
        rejects(static fn() => Source::validateImage($imageUrl, pngContainer(1, 1, substr($compressed, 0, -1)), 'image/png'));
        rejects(static fn() => Source::validateImage($imageUrl, pngContainer(1, 1, $compressed . 'extra'), 'image/png'));
    });
    check('PNG扫描行长度与过滤器错误被拒绝，不只检查文件头', static function () use ($imageUrl): void {
        rejects(static fn() => Source::validateImage($imageUrl, pngContainer(1, 1, gzcompress("\0\xff\xff")), 'image/png'));
        rejects(static fn() => Source::validateImage($imageUrl, pngContainer(1, 1, gzcompress("\0\xff\xff\xff\0")), 'image/png'));
        rejects(static fn() => Source::validateImage($imageUrl, pngContainer(1, 1, gzcompress("\x05\xff\xff\xff")), 'image/png'));
    });
    check('PNG的IHDR重复与IDAT被其他块分隔时拒绝', static function () use ($imageUrl): void {
        $bytes = png();
        rejects(static fn() => Source::validateImage($imageUrl, substr($bytes, 0, 33) . substr($bytes, 8, 25) . substr($bytes, 33), 'image/png'));
        $compressed = gzcompress("\0\xff\xff\xff");
        $separated = substr($bytes, 0, 33) . pngChunk('IDAT', substr($compressed, 0, 3)) . pngChunk('tEXt', "a\0b")
            . pngChunk('IDAT', substr($compressed, 3)) . pngChunk('IEND', '');
        rejects(static fn() => Source::validateImage($imageUrl, $separated, 'image/png'));
    });
    check('64MB限制的独立PHP8进程可以校验3000万像素PNG，不分配整张GD位图', static function (): void {
        $command = [PHP_BINARY, '-n', '-d', 'memory_limit=64M', __FILE__, '--long-png-memory'];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new \RuntimeException('Unable to start isolated memory check');
        fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
        if ($code !== 0) throw new \RuntimeException('Low-memory fixture failed: ' . $error . $out);
        $result = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        same($result['width'], 2000); same($result['height'], 15000); same($result['peak_bytes'] < 64 * 1048576, true);
    });
    check('HTML 冒充图片被拒绝', static fn() => rejects(static fn() => Source::validateImage($imageUrl, '<html>错误页面</html>', 'image/png')));
    check('非图片 MIME 被拒绝', static fn() => rejects(static fn() => Source::validateImage($imageUrl, png(), 'text/html')));
    check('超过单图大小被拒绝', static fn() => rejects(static fn() => Source::validateImage($imageUrl, str_repeat('x', Source::MAX_IMAGE_BYTES + 1), 'image/png')));

    $client = new FakeSource();
    $client->responses[$sourceUrl] = ['status' => 200, 'headers' => [], 'body' => $catalogHtml];
    $client->responses[$sourceUrl . '16576.html'] = ['status' => 200, 'headers' => [], 'body' => $detailHtml];
    $client->responses[$imageUrl] = ['status' => 200, 'headers' => ['content-type' => 'image/png'], 'body' => png()];
    check('完整读取栏目与图片契约', static function () use ($client, $sourceUrl): void {
        same(count($client->discover($sourceUrl)), 4); $images = $client->fetchQuoteImages($sourceUrl, '16576');
        same(count($images), 1); same($images[0]['mime'], 'image/png'); same(count($client->requests), 3);
    });
    check('页面30秒、图片120秒且连接15秒，不共用旧的25秒上限', static function () use ($client): void {
        same(Source::CONNECT_TIMEOUT_SECONDS, 15); same(Source::PAGE_TIMEOUT_SECONDS, 30); same(Source::IMAGE_TIMEOUT_SECONDS, 120);
        same(array_column($client->requests, 3), [30, 30, 120]);
    });
    check('模拟35秒慢图可以完整采集，不因原25秒阈值误判失败', static function () use ($sourceUrl, $detailHtml, $imageUrl): void {
        $fake = new FakeSource();
        $fake->responses[$sourceUrl . '16576.html'] = ['status' => 200, 'headers' => [], 'body' => $detailHtml];
        $fake->responses[$imageUrl] = ['status' => 200, 'headers' => ['content-type' => 'image/png'], 'body' => png(), 'elapsed' => 35];
        $images = $fake->fetchQuoteImages($sourceUrl, '16576');
        same(count($images), 1); same($images[0]['bytes'], png()); same($fake->clock, 35.0);
    });
    check('页面重定向共用30秒预算，后续请求只获得剩余时间', static function () use ($sourceUrl, $catalogHtml): void {
        $fake = new FakeSource();
        $fake->responses[$sourceUrl] = ['status' => 302, 'headers' => ['location' => '/16576.html'], 'body' => '', 'elapsed' => 12];
        $fake->responses[$sourceUrl . '16576.html'] = ['status' => 200, 'headers' => [], 'body' => $catalogHtml, 'elapsed' => 17];
        same(count($fake->discover($sourceUrl)), 4);
        same(array_column($fake->requests, 3), [30, 18]); same($fake->clock, 29.0);
    });
    check('图片重定向共用120秒预算，不给每次跳转重新计时', static function () use ($sourceUrl, $detailHtml, $imageUrl): void {
        $fake = new FakeSource(); $nextImage = 'https://cos.huishoubaojiadan.com/quotation/redirected.png';
        $fake->responses[$sourceUrl . '16576.html'] = ['status' => 200, 'headers' => [], 'body' => $detailHtml];
        $fake->responses[$imageUrl] = ['status' => 302, 'headers' => ['location' => $nextImage], 'body' => '', 'elapsed' => 35];
        $fake->responses[$nextImage] = ['status' => 200, 'headers' => ['content-type' => 'image/png'], 'body' => png(), 'elapsed' => 80];
        same(count($fake->fetchQuoteImages($sourceUrl, '16576')), 1);
        same(array_column($fake->requests, 3), [30, 120, 85]); same($fake->clock, 115.0);
    });
    check('首次DNS耗尽页面预算时不继续发请求，明确提示超时并保留原报价', static function () use ($sourceUrl): void {
        $fake = new FakeSource(); $fake->dnsElapsed = [31];
        timesOut(static fn() => $fake->discover($sourceUrl)); same(count($fake->requests), 0);
    });
    check('重定向后的DNS耗尽剩余预算时不再发请求', static function () use ($sourceUrl): void {
        $fake = new FakeSource(); $fake->dnsElapsed = [0, 11];
        $fake->responses[$sourceUrl] = ['status' => 302, 'headers' => ['location' => '/16576.html'], 'body' => '', 'elapsed' => 20];
        timesOut(static fn() => $fake->discover($sourceUrl)); same(count($fake->requests), 1);
    });
    check('页面请求耗尽30秒预算时不接受迟到成功响应', static function () use ($sourceUrl, $catalogHtml): void {
        $fake = new FakeSource();
        $fake->responses[$sourceUrl] = ['status' => 200, 'headers' => [], 'body' => $catalogHtml, 'elapsed' => 31];
        timesOut(static fn() => $fake->discover($sourceUrl));
    });
    check('图片请求耗尽120秒预算时不接受迟到图片', static function () use ($sourceUrl, $detailHtml, $imageUrl): void {
        $fake = new FakeSource();
        $fake->responses[$sourceUrl . '16576.html'] = ['status' => 200, 'headers' => [], 'body' => $detailHtml];
        $fake->responses[$imageUrl] = ['status' => 200, 'headers' => ['content-type' => 'image/png'], 'body' => png(), 'elapsed' => 121];
        timesOut(static fn() => $fake->fetchQuoteImages($sourceUrl, '16576'));
    });
    check('放宽等待不放开图片大小、栏目总量或张数限制', static function (): void {
        same(Source::MAX_IMAGE_BYTES, 12582912); same(Source::MAX_TOTAL_BYTES, 25165824); same(Source::MAX_IMAGES, 10);
    });
    check('非法栏目编号不发起 HTTP', static function () use ($client, $sourceUrl): void {
        $before = count($client->requests); rejects(static fn() => $client->fetchQuoteImages($sourceUrl, '../16576')); same(count($client->requests), $before);
    });
    check('重定向到其他域在下一请求前拦截', static function () use ($sourceUrl): void {
        $fake = new FakeSource(); $fake->responses[$sourceUrl] = ['status' => 302, 'headers' => ['location' => 'https://127.0.0.1/'], 'body' => ''];
        rejects(static fn() => $fake->discover($sourceUrl)); same(count($fake->requests), 1);
    });
    check('图片重定向不能跳到源站或私网', static function () use ($sourceUrl, $detailHtml, $imageUrl): void {
        $fake = new FakeSource(); $fake->responses[$sourceUrl . '16576.html'] = ['status' => 200, 'headers' => [], 'body' => $detailHtml];
        $fake->responses[$imageUrl] = ['status' => 302, 'headers' => ['location' => 'https://127.0.0.1/quotation/a.png'], 'body' => ''];
        rejects(static fn() => $fake->fetchQuoteImages($sourceUrl, '16576')); same(count($fake->requests), 2);
    });
    check('404 与超大响应不会伪装成功', static function () use ($sourceUrl): void {
        $fake = new FakeSource(); $fake->responses[$sourceUrl] = ['status' => 404, 'headers' => [], 'body' => 'missing'];
        rejects(static fn() => $fake->discover($sourceUrl));
        $fake->responses[$sourceUrl] = ['status' => 200, 'headers' => [], 'body' => str_repeat('x', 2097153)];
        rejects(static fn() => $fake->discover($sourceUrl));
    });
    check('网络实现启用证书校验、DNS 固定且关闭自动跳转和代理', static function (): void {
        $code = file_get_contents(__DIR__ . '/../app/service/core/quote_image/HuishouBaojiaSource.php');
        foreach (['CURLOPT_SSL_VERIFYPEER => true', 'CURLOPT_SSL_VERIFYHOST => 2', 'CURLOPT_RESOLVE =>', "CURLOPT_PROXY => ''", 'CURLOPT_FOLLOWLOCATION => false'] as $guard) {
            if (strpos($code, $guard) === false) throw new \RuntimeException('Missing transport guard: ' . $guard);
        }
    });
    check('实际cURL使用剩余总预算，连接不超过15秒，时钟采用单调时间', static function (): void {
        $code = file_get_contents(__DIR__ . '/../app/service/core/quote_image/HuishouBaojiaSource.php');
        foreach (['CURLOPT_CONNECTTIMEOUT => min(self::CONNECT_TIMEOUT_SECONDS, $timeoutSeconds)',
            'CURLOPT_TIMEOUT => $timeoutSeconds', 'return hrtime(true) / 1000000000;'] as $guard) {
            if (strpos($code, $guard) === false) throw new \RuntimeException('Missing timeout guard: ' . $guard);
        }
    });

    $driver = new FakeDriver(); CoreFileService::$testDriver = $driver;
    $storage = new IsolatedStorage();
    $image = Source::validateImage($imageUrl, png(), 'image/png');
    check('同一图片每次使用新路径，原始字节不变', static function () use ($storage, $image, $driver): void {
        $a = $storage->store(100005, $image); $b = $storage->store(100005, $image);
        if ($a === $b) throw new \RuntimeException('Old image would be overwritten');
        same($driver->writes[0][1], $image['bytes']); same($driver->writes[1][1], $image['bytes']);
        if (strpos($a, '/100005/') === false || !str_ends_with($a, '.png')) throw new \RuntimeException('Wrong tenant path or extension');
    });
    check('存储返回失败时不返回伪地址', static function () use ($storage, $image, $driver): void {
        $driver->success = false; rejects(static fn() => $storage->store(100005, $image)); $driver->success = true;
    });
    check('本地驱动虚报写入成功仍会核对文件', static function () use ($storage, $image): void {
        CoreFileService::$testType = 'local'; rejects(static fn() => $storage->store(100005, $image)); CoreFileService::$testType = 'tencent';
    });
    check('本地驱动写入前先准备实际目标目录，不创建测试根目录', static function () use ($storage, $image, $driver): void {
        CoreFileService::$testType = 'local'; $storage->prepared = [];
        $driver->beforeWrite = static function (string $key) use ($storage): void { same($storage->prepared, [$key]); };
        rejects(static fn() => $storage->store(100005, $image));
        $driver->beforeWrite = null; CoreFileService::$testType = 'tencent';
    });
    check('目标目录不可写时不调用驱动，也不暴露路径或凭据', static function () use ($storage, $image, $driver): void {
        CoreFileService::$testType = 'local'; $storage->denyDirectory = true; $before = count($driver->writes);
        try { $storage->store(100005, $image); throw new \RuntimeException('Expected directory failure'); }
        catch (\core\exception\CommonException $e) {
            same(strpos($e->getMessage(), '本站图片转存失败') !== false, true);
            same(strpos($e->getMessage(), '/private/'), false); same(strpos($e->getMessage(), 'secret-key'), false);
        }
        same(count($driver->writes), $before); $storage->denyDirectory = false; CoreFileService::$testType = 'tencent';
    });
    check('驱动原始异常转换为简明提示，日志不包含原始凭据', static function () use ($storage, $image, $driver): void {
        $driver->error = 'file_put_contents(/private/server/path): secret-key=fixture';
        try { $storage->store(100005, $image); throw new \RuntimeException('Expected driver failure'); }
        catch (\core\exception\CommonException $e) {
            same(strpos($e->getMessage(), '写入权限或存储配置') !== false, true);
            same(strpos($e->getMessage(), 'file_put_contents'), false); same(strpos($e->getMessage(), 'secret-key'), false);
        }
        same(strpos(json_encode(\think\facade\Log::$warnings), 'secret-key'), false); $driver->error = null;
    });
    check('本地目录实现基于url_to_path且不切换进程cwd', static function (): void {
        $code = file_get_contents(__DIR__ . '/../app/service/core/quote_image/QuoteImageStorage.php');
        same(strpos($code, 'dirname(url_to_path($key))') !== false, true);
        same(strpos($code, 'chdir('), false);
    });
    check('非站点不能保存图片', static fn() => rejects(static fn() => $storage->store(0, $image)));
    echo '完成：' . $checks . ' 项图片来源/转存检查；全部为静态 fixture 和内存 HTTP/存储替身，未访问网络或数据库。' . PHP_EOL;
}
