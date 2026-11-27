<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\cooperation;

use addon\hsx_lead\app\service\Context;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;
use think\facade\{Cache,Db};

/** Contact information only. Never creates, approves or changes a payment. */
final class PaymentHelpService
{
    public const KEY = 'HSX_LEAD_PAYMENT_HELP';

    public static function defaults(): array
    {
        return ['enabled' => false, 'title' => '支付帮助', 'content' => '', 'qr_image' => '', 'remark' => ''];
    }

    public static function get(Context $context, bool $admin = false): array
    {
        if ($admin)$context->permission('hsx_lead_cooperation_config');
        else self::member($context);
        try {
            $raw = self::read($context->site);
            // A disabled contact entry must not publish retained editing data.
            if (!$admin && !self::flag($raw['enabled'] ?? null))return self::hidden();
            return self::normalize($context, $raw, false, $admin);
        } catch (CommonException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new CommonException('支付帮助配置暂不可读取，请联系管理员核对');
        }
    }

    public static function save(Context $context, array $input): array
    {
        $context->permission('hsx_lead_cooperation_config_save');
        try {
            return Db::transaction(function () use ($context, $input): array {
                $site = Db::name('site')->where('site_id', $context->site)->lock(true)->find();
                if (!$site)throw new CommonException('当前站点不存在，支付帮助配置未保存');
                // Validate media while holding the same site configuration lock.
                $next = self::normalize($context, $input, true);
                // Framework configuration caches must not turn a cached miss
                // into a duplicate row or return an older contact after saving.
                Cache::tag(CoreConfigService::$cache_tag_name.$context->site)->clear();
                (new CoreConfigService())->setConfig($context->site, self::KEY, $next);
                $saved = self::normalize($context, self::read($context->site));
                if ($saved !== $next)throw new CommonException('支付帮助配置回读不一致，未确认保存，请重新核对');
                $context->audit('payment_help_config', 0, 'saved', [
                    'enabled' => $saved['enabled'], 'has_qr_image' => $saved['qr_image'] !== '',
                    'title_length' => mb_strlen($saved['title']), 'content_length' => mb_strlen($saved['content']),
                    'remark_length' => mb_strlen($saved['remark']),
                ]);
                return $saved;
            });
        } catch (CommonException $error) {
            throw $error;
        } catch (\Throwable $error) {
            throw new CommonException('支付帮助配置未保存，请联系管理员核对服务状态');
        }
    }

    private static function member(Context $context): void
    {
        if ($context->memberId <= 0 || !Db::name('member')->where('site_id', $context->site)
            ->where('member_id', $context->memberId)->where('status', 1)->where('is_del', 0)->where('delete_time', 0)->find()) {
            throw new CommonException('请先登录本站有效会员后查看支付帮助');
        }
    }

    private static function hidden(): array
    {
        return ['enabled' => false, 'title' => '', 'content' => '', 'qr_image' => '', 'remark' => ''];
    }

    private static function read(int $site): array
    {
        $rows = Db::name('sys_config')->where('site_id', $site)->where('config_key', self::KEY)->field('value')->limit(2)->select()->toArray();
        if (!$rows)return self::defaults();
        if (count($rows) !== 1)throw new CommonException('支付帮助配置存在重复，请管理员核对');
        $raw = $rows[0]['value'];
        if (is_string($raw)) {
            try {
                $raw = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            } catch (\Throwable $error) {
                throw new CommonException('支付帮助配置格式异常，请管理员重新保存');
            }
        }
        if (!is_array($raw))throw new CommonException('支付帮助配置格式异常，请管理员重新保存');
        return $raw;
    }

    private static function normalize(Context $context, array $input, bool $lock = false, bool $editable = false): array
    {
        $defaults = self::defaults();
        if (array_diff(array_keys($input), array_keys($defaults)))throw new CommonException('支付帮助配置包含无效字段');
        $out = ['enabled' => self::flag($input['enabled'] ?? null)];
        $labels = ['title' => '标题', 'content' => '提示内容', 'remark' => '备注提示'];
        foreach (['title' => 40, 'content' => 600, 'remark' => 120] as $key => $limit) {
            $value = $input[$key] ?? $defaults[$key];
            if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $limit)throw new CommonException('支付帮助'.$labels[$key].'请填写'.$limit.'字以内的普通文字');
            $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
            $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if (preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value.$decoded)
                || ($key !== 'content' && preg_match('/[\r\n\t]/u', $value)))throw new CommonException('支付帮助仅支持普通文字，不能使用HTML或控制字符');
            $out[$key] = $value;
        }
        try {
            $out['qr_image'] = self::image($context, $input['qr_image'] ?? '', $lock);
        } catch (CommonException $error) {
            // A removed/invalid contact image must not lock administrators out
            // of replacing it or disabling this entry. Never expose the old
            // unverified path; public reads and all saves remain strict.
            if (!$editable)throw $error;
            $out['qr_image'] = '';
        }
        if ($out['enabled'] && ($out['content'] === '' || $out['qr_image'] === '') && !$editable)throw new CommonException('开启支付帮助前，请填写提示内容并上传本站客服二维码');
        // Keep a stable five-field contract for existing configuration consumers.
        return ['enabled' => $out['enabled'], 'title' => $out['title'], 'content' => $out['content'], 'qr_image' => $out['qr_image'], 'remark' => $out['remark']];
    }

    private static function flag($value): bool
    {
        if (!in_array($value, [true, false, 1, 0, '1', '0'], true))throw new CommonException('请明确设置支付帮助开关');
        return in_array($value, [true, 1, '1'], true);
    }

    /** Resolve only a registered public image belonging to this exact site. */
    private static function image(Context $context, $value, bool $lock): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > 1000)throw new CommonException('客服二维码图片地址格式或长度不正确');
        if ($value === '')return '';
        if (preg_match('/[\s\x00-\x1F\x7F<>"\x27`{}();\\\\%]/u', $value))throw new CommonException('请从本站图片素材中重新选择客服二维码');
        $parts = parse_url($value);
        if (!is_array($parts) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass']))throw new CommonException('客服二维码不支持私有、临时或带参数的图片地址');
        $reference = $value;
        if (isset($parts['scheme']) || isset($parts['host'])) {
            self::publicUrl($value);
        } else $reference = self::publicPath($value);
        $query = Db::name('sys_attachment')->where('site_id', $context->site)->where('att_type', 'image')
            ->where(function ($query) use ($value, $reference): void {$query->whereIn('path', array_values(array_unique([$value, $reference])))->whereOr('url', $value);});
        if ($lock)$query->lock(true);
        $rows = $query->field('att_id,path,url')->limit(2)->select()->toArray();
        if (count($rows) !== 1)throw new CommonException('客服二维码不存在或不属于本站，请从本站公开图片素材中重新选择');
        $path = self::publicPath((string) $rows[0]['path']);
        // Lead evidence is not a public customer-service asset. Business
        // licenses live outside public storage and are rejected by path too.
        if (Db::name('hsx_lead_audit')->where('site_id', $context->site)->where('entity_id', (int) $rows[0]['att_id'])
            ->whereIn('entity_type', ['cooperation_attachment', 'business_license'])->where('action', 'uploaded')->count()) {
            throw new CommonException('不能将业务附件或私有证照用作公开客服二维码，请重新上传');
        }
        // CoreUploadService stores the upload driver's public URL alongside
        // its object path. Cloud object paths are not files on the SaaS host;
        // use this same registered URL instead of rebuilding its domain.
        // Resolve on reads as well, so an already saved relative reference
        // works without changing configuration or attachment records.
        $url = (string) ($rows[0]['url'] ?? '');
        if ($url === '')return $path;
        $urlParts = parse_url($url);
        if (is_array($urlParts) && (isset($urlParts['scheme']) || isset($urlParts['host'])))return self::publicUrl($url);
        if (self::publicPath($url) !== $path)throw new CommonException('客服二维码素材地址不一致，请重新选择本站图片');
        return $path;
    }

    private static function publicUrl(string $url): string
    {
        if (!mb_check_encoding($url, 'UTF-8') || mb_strlen($url) > 1000 || preg_match('/[\s\x00-\x1F\x7F<>"\x27`{}();\\\\%]/u', $url))throw new CommonException('客服二维码图片地址无效');
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass']))throw new CommonException('客服二维码不支持私有、临时或带参数的图片地址');
        if (!in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true) || empty($parts['host']) || !filter_var($url, FILTER_VALIDATE_URL))throw new CommonException('客服二维码图片地址无效');
        return $url;
    }

    private static function publicPath(string $path): string
    {
        $path = ltrim($path, '/');
        if ($path === '' || !mb_check_encoding($path, 'UTF-8') || mb_strlen($path) > 1000 || preg_match('/[\s\x00-\x1F\x7F<>"\x27`{}();\\\\%?#:]/u', $path)
            || str_contains($path, '//') || preg_match('~(?:^|/)\.{1,2}(?:/|$)~D', $path)
            || preg_match('~(?:^|/)(?:private|storage|business[-_]license|license)(?:/|[._-]|$)~i', $path)
            || !preg_match('~^(?:upload|uploads|attachment)/(?:[^/]+/)*[^/]+\.(?:png|jpe?g|webp|gif)$~iD', $path)) {
            throw new CommonException('客服二维码须为本站公开的PNG、JPG、WebP或GIF图片，不能使用私有证照');
        }
        return $path;
    }
}
