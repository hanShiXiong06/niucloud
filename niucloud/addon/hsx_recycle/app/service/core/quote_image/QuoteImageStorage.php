<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\quote_image;

use app\service\core\upload\CoreFileService;
use core\exception\CommonException;
use think\facade\Log;

/** 只把已验证的原始图片交给本站已有存储驱动；每次使用不可变新路径。 */
class QuoteImageStorage extends CoreFileService
{
    public function store(int $siteId, array $image): string
    {
        if ($siteId <= 0) throw new CommonException('报价图片只能保存到有效站点');
        $validated = HuishouBaojiaSource::validateImage((string)($image['url'] ?? ''), (string)($image['bytes'] ?? ''), (string)($image['mime'] ?? ''));
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        $key = 'upload/hsx_recycle/quote_images/' . $siteId . '/' . date('Ymd') . '/'
            . $validated['hash'] . '_' . bin2hex(random_bytes(8)) . '.' . $extensions[$validated['mime']];
        $stage = 'driver';
        try {
            $driver = $this->driver($siteId);
            if (self::$storage_type === 'local') {
                $stage = 'directory';
                $this->prepareLocalDirectory($key);
            }
            $stage = 'write';
            if ($driver->base64(base64_encode($validated['bytes']), $key) !== true) {
                throw new \RuntimeException('Storage driver did not confirm write');
            }
            // 框架本地驱动写入失败时也可能返回 true，额外核对落盘字节，避免保存不存在的图片。
            if (self::$storage_type === 'local') {
                $stage = 'verify';
                $local = url_to_path($key);
                if (!is_file($local) || !hash_equals($validated['hash'], (string)@hash_file('sha256', $local))) {
                    throw new \RuntimeException('Local image bytes were not persisted completely');
                }
            }
            $stage = 'address';
            $url = (string)$driver->getUrl($key);
            if ($url === '' || preg_match('/[\x00-\x20,]/', $url)) throw new \RuntimeException('Storage returned an invalid image address');
            return $url;
        } catch (\Throwable $e) {
            // 不把 SDK 凭据、签名 URL 或 file_put_contents 的服务器绝对路径返回给客户。
            try { Log::warning('报价图片转存失败', ['site_id' => $siteId, 'stage' => $stage, 'exception_class' => get_class($e)]); }
            catch (\Throwable $ignored) {}
            throw new CommonException('本站图片转存失败，请检查写入权限或存储配置；原报价保持不变');
        }
    }

    /** Local 驱动建目录使用相对 cwd，写文件却使用 public 实际路径；在插件内补齐实际目标目录。 */
    protected function prepareLocalDirectory(string $key): void
    {
        $directory = dirname(url_to_path($key));
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Unable to prepare local image directory');
        }
        clearstatcache(true, $directory);
        if (!is_writable($directory)) throw new \RuntimeException('Local image directory is not writable');
    }
}
