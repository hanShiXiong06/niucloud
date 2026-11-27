<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use addon\hsx_lead\app\support\{Cipher,Data};
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\{App,Cache,Db};
use think\file\UploadedFile;

/** Private business evidence: never published as an attachment/CDN URL. No new table. */
final class BusinessLicenseService
{
    private const PROFILE = 'HSX_LEAD_BUSINESS_LICENSE_';
    private const UPLOAD = 'HSX_LEAD_LICENSE_UPLOAD_';
    private const MAX_BYTES = 5242880;
    private const TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    public function __construct(private Context $context)
    {
    }

    private function member(): void
    {
        if ($this->context->memberId <= 0
            || !Db::name('member')->where('site_id', $this->context->site)->where('member_id', $this->context->memberId)->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->find()) throw new CommonException('请先登录本站有效会员，才能查看或上传营业执照');
    }
    private function writable(): void
    {
        $this->member();
        $m = Data::query('merchant', $this->context->site)->where('member_id', $this->context->memberId)->find();
        if (!$m) return;
        $p = Store::query('partner', $this->context->site)->where('merchant_id', $m['id'])->find();
        if ((int) $m['status'] !== 1 || (int) ($p['blocked_at'] ?? 0) > 0) throw new CommonException('当前门店已停止合作，不能修改营业执照');
    }
    private static function token($token): string
    {
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) throw new CommonException('营业执照凭据无效，请重新上传');
        return $token;
    }
    private static function uploadKey(int $member, string $token): string
    {
        return self::UPLOAD.$member.'_'.self::token($token);
    }
    private function config(string $key): array
    {
        $value = (new CoreConfigService())->getConfigValue($this->context->site, $key);
        return is_array($value) ? $value : [];
    }
    private function metadata(int $member, string $token): array
    {
        $meta = $this->config(self::uploadKey($member, $token));
        if (($meta['schema'] ?? '') !== 'hsx.lead.license.v1'
            || (int) ($meta['member_id'] ?? 0) !== $member
            || !hash_equals((string) ($meta['token'] ?? ''), $token)
            || !in_array($meta['mime'] ?? '', self::TYPES, true)
            || (int) ($meta['bytes'] ?? 0) < 1
            || (int) $meta['bytes'] > self::MAX_BYTES) throw new CommonException('营业执照不存在或不属于当前账号，请重新上传');
        return $meta;
    }
    private function file(int $member, string $token, bool $create = false): string
    {
        self::token($token);
        if ($member <= 0) throw new CommonException('营业执照归属无效');
        // Persistent and outside public/. Runtime/cache clearing must not destroy evidence.
        $root = rtrim(App::getRootPath(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'storage';
        foreach (['private', 'hsx_lead', 'business-license', (string) $this->context->site, (string) $member] as $segment) {
            if (is_link($root)) throw new CommonException('私有证照目录不可用，请联系平台管理员');
            if ($create && !is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new CommonException('私有证照目录不可写，请联系平台管理员');
            $root .= DIRECTORY_SEPARATOR.$segment;
        }
        if (is_link($root)) throw new CommonException('私有证照目录不可用，请联系平台管理员');
        if ($create && !is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new CommonException('私有证照目录不可写，请联系平台管理员');
        return $root.DIRECTORY_SEPARATOR.$token.'.enc';
    }
    private function reserveUpload(): void
    {
        // Serialize the per-member cache reservation. Refusal precedes private-file creation.
        Db::transaction(function (): void {
            Db::name('member')->where('site_id', $this->context->site)->where('member_id', $this->context->memberId)->lock(true)->find();
            $this->writable();
            $key = 'hsx_lead:license_upload:'.$this->context->site.':'.$this->context->memberId;
            $window = (int) floor(time() / 60);
            $state = Cache::get($key, []);
            $count = is_array($state) && (int) ($state['window'] ?? 0) === $window ? (int) ($state['count'] ?? 0) : 0;
            if ($count >= 5) throw new CommonException('营业执照上传太频繁，请稍等一分钟再试，原证照不会改变');
            if (!Cache::set($key, ['window' => $window, 'count' => $count + 1], 120)) throw new CommonException('上传频率校验暂不可用，请稍后再试');
        });
    }
    public function upload(?UploadedFile $file = null): array
    {
        $this->writable();
        $file = $file ?? request()->file('file');
        if (!$file instanceof UploadedFile || !$file->isValid()) throw new CommonException('未读取到完整营业执照图片，请重新选择');
        if ($file->getSize() < 1 || $file->getSize() > self::MAX_BYTES) throw new CommonException('营业执照图片不能超过5MB');
        $bytes = file_get_contents($file->getPathname());
        if ($bytes === false || strlen($bytes) > self::MAX_BYTES) throw new CommonException('营业执照图片读取失败，请重新上传');
        $image = @getimagesizefromstring($bytes);
        $mime = $image['mime'] ?? '';
        if (!$image
            || !in_array($mime, self::TYPES, true)
            || !in_array(strtolower($file->getOriginalExtension()), ['jpg', 'jpeg', 'png', 'webp'], true)
            || (int) $image[0] * (int) $image[1] > 25000000) throw new CommonException('请选择清晰的JPG、PNG或WebP营业执照图片，像素不超过2500万');
        $this->reserveUpload();
        $member = $this->context->memberId;
        $token = bin2hex(random_bytes(32));
        $now = time();
        $meta = [
            'schema' => 'hsx.lead.license.v1',
            'member_id' => $member,
            'token' => $token,
            'mime' => $mime,
            'bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'uploaded_at' => $now
        ];
        try {
            $encrypted = Cipher::encrypt(['kind' => 'business_license', 'token' => $token, 'image' => base64_encode($bytes)], $this->context->site, $member);
        } catch (CommonException $e) {
            throw new CommonException('请平台管理员核对原app.auth_key，营业执照未安全保存，请勿随意更换部署密钥');
        }
        $path = $this->file($member, $token, true);
        $handle = @fopen($path, 'xb');
        if (!$handle) throw new CommonException('营业执照私有文件无法保存，请联系平台管理员');
        $stored = false;
        try {
            @chmod($path, 0600);
            $offset = 0;
            while ($offset < strlen($encrypted)) {
                $written = fwrite($handle, substr($encrypted, $offset));
                if ($written === false || $written === 0) throw new CommonException('营业执照保存不完整，请重试');
                $offset += $written;
            }
            fclose($handle);
            $handle = null;
            Db::transaction(function () use ($member, $token, $meta): void {
                Db::name('member')->where('site_id', $this->context->site)->where('member_id', $member)->lock(true)->find();
                $this->writable();
                (new CoreConfigService())->setConfig($this->context->site, self::uploadKey($member, $token), $meta);
                $this->context->audit('business_license', 0, 'uploaded', ['bytes' => $meta['bytes']]);
            });
            $stored = true;
            return ['token' => $token, 'uploaded_at' => $now, 'bytes' => $meta['bytes'], 'mime' => $mime];
        } finally {
            if (is_resource($handle)) fclose($handle);
            if (!$stored && is_file($path)) unlink($path);
        }
    }
    /** Called inside profile transaction. Upload is not approval and is optional. */
    public function save(int $merchantId, array $input): void
    {
        if (!array_key_exists('business_license_token', $input)) return;
        $this->writable();
        $merchant = Data::row('merchant', $this->context->site, $merchantId);
        if ((int) $merchant['member_id'] !== $this->context->memberId) throw new CommonException('只能修改本人门店营业执照');
        $token = $input['business_license_token'];
        $current = $this->config(self::PROFILE.$merchantId);
        if ($token !== '') {
            $token = self::token($token);
            $meta = $this->metadata($this->context->memberId, $token);
            if (($current['token'] ?? '') !== $token && (int) $meta['uploaded_at'] < time() - 86400) throw new CommonException('这张营业执照尚未保存且已过期，请重新上传');
            if (!is_file($this->file($this->context->memberId, $token))) throw new CommonException('营业执照私有文件缺失，请重新上传后保存');
            $current = ['token' => $token, 'member_id' => $this->context->memberId, 'uploaded_at' => (int) $meta['uploaded_at']];
        } else $current = [];
        (new CoreConfigService())->setConfig($this->context->site, self::PROFILE.$merchantId, $current);
        $this->context->audit('business_license', $merchantId, $token === '' ? 'removed' : 'profile_saved', []);
    }
    /** Only the owner bootstrap gets an opaque token. Never put it in store/team listings. */
    public function ownSummary(array $merchant): array
    {
        if ($this->context->memberId <= 0 || (int) $merchant['member_id'] !== $this->context->memberId) return [];
        $value = $this->config(self::PROFILE.(int) $merchant['id']);
        if ($value
            && (int) ($value['member_id'] ?? 0) !== $this->context->memberId) return ['business_license_token' => '', 'business_license_uploaded_at' => 0];
        return ['business_license_token' => (string) ($value['token'] ?? ''), 'business_license_uploaded_at' => (int) ($value['uploaded_at'] ?? 0)];
    }
    public function preview(string $token): array
    {
        $this->member();
        self::token($token);
        $member = $this->context->memberId;
        $meta = $this->metadata($member, $token);
        $m = Data::query('merchant', $this->context->site)->where('member_id', $member)->find();
        $current = $m ? $this->config(self::PROFILE.(int) $m['id']) : [];
        if (($current['token'] ?? '') !== $token && (int) $meta['uploaded_at'] < time() - 86400) throw new CommonException('未保存的营业执照预览已过期，请重新上传');
        return $this->read($member, $token, $meta);
    }
    public function adminPreview(int $merchantId): array
    {
        Store::permission($this->context, 'partners');
        Store::permission($this->context, 'license_read');
        $m = Data::row('merchant', $this->context->site, $merchantId);
        $value = $this->config(self::PROFILE.$merchantId);
        if (empty($value['token'])) return ['has_image' => false];
        if ((int) ($value['member_id'] ?? 0) !== (int) $m['member_id']) throw new CommonException('营业执照归属已变化，请人工核对原门店资料');
        $token = self::token($value['token']);
        $result = $this->read((int) $m['member_id'], $token, $this->metadata((int) $m['member_id'], $token));
        $this->context->audit('business_license', $merchantId, 'viewed', []);
        return $result;
    }
    private function read(int $member, string $token, array $meta): array
    {
        $path = $this->file($member, $token);
        if (is_link($path) || !is_file($path) || filesize($path) > self::MAX_BYTES * 2) throw new CommonException('营业执照私有文件暂不可读取，请联系平台核对备份');
        $raw = file_get_contents($path);
        if ($raw === false) throw new CommonException('营业执照读取失败，请重试');
        try {
            $value = Cipher::decrypt($raw, $this->context->site, $member);
        } catch (CommonException $e) {
            throw new CommonException('营业执照解密未完成，请平台核对原部署密钥及文件备份');
        }
        $bytes = base64_decode((string) ($value['image'] ?? ''), true);
        if (($value['kind'] ?? '') !== 'business_license'
            || ($value['token'] ?? '') !== $token
            || $bytes === false
            || strlen($bytes) !== (int) $meta['bytes']
            || !hash_equals((string) $meta['sha256'], hash('sha256', $bytes))) throw new CommonException('营业执照文件校验失败，请平台核对原件');
        return [
            'has_image' => true,
            'data_url' => 'data:'.$meta['mime'].';base64,'.base64_encode($bytes),
            'mime' => $meta['mime'],
            'uploaded_at' => (int) $meta['uploaded_at']
        ];
    }
}
