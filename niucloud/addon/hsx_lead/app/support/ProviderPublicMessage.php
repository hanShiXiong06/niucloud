<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\support;

/** User guidance only. Keep adapter diagnostics and source business data unchanged. */
final class ProviderPublicMessage
{
    private const REASONS = [
        'auth_required' => 'ERP 登录已失效，请重新验证账号后再同步',
        'invalid_credentials' => 'ERP 登录资料未通过校验，请核对所选平台的账号、密码与授权',
        'invalid_captcha' => '验证码不正确或已失效，请刷新验证码后重新验证',
        'invalid_challenge' => '本次授权已失效，请重新获取验证码并核对账号后再验证',
        'auth_challenge' => 'ERP 需要完成验证码或人工确认，请按授权页面提示重新验证',
        'captcha_invalid_request' => '验证码请求未通过校验，请重新获取验证码或联系平台核对',
        'channel_disabled' => '当前 ERP 渠道已暂停，授权和同步已停止；账号与历史库存保留，请联系平台或选择其他开放渠道',
        'http_error' => 'ERP 暂时没有正常响应，请稍后重试；尚未保存新的授权或更新库存',
        'upstream_error' => 'ERP 服务本次未成功，请稍后重试；原授权与完整库存仍保留',
        'rate_limited' => 'ERP 请求过于频繁，请稍后再试；系统不会连续重试',
        'scope_mismatch' => 'ERP 返回的门店与已选范围不一致，请重新核对账号和来源门店；本次未更新库存',
        'invalid_scope' => 'ERP 查询范围未通过校验，请核对来源门店与范围后重试',
        'schema_changed' => 'ERP 返回的资料暂时无法完整识别，请联系平台核对；本次未更新库存',
        'client_version_changed' => 'ERP 接口版本已变化，请联系平台更新接入后再同步',
        'response_too_large' => 'ERP 单次返回资料超过处理上限，请联系平台核对；本次未更新库存',
        'unsupported_environment' => '当前 ERP 同步服务缺少必要能力，请联系平台处理',
        'unsupported_transport' => '当前 ERP 接入服务需要更新，请联系平台处理',
        'unsupported_provider' => '所选 ERP 平台暂未开放，请重新选择开放的平台',
        'forbidden_endpoint' => 'ERP 接口配置未通过校验，请联系平台处理',
        'generation_expired' => '本轮同步间隔过久，暂存资料已失效，请重新完整同步',
        'sync_failed' => '本轮 ERP 同步未完成，未更新库存，请重试或联系平台核对',
        'authorization_expired' => 'ERP 授权已到期，请重新验证；已有完整库存快照仍保留',
        'credential_error' => 'ERP 授权资料无法读取，请联系平台核对配置后重新验证；已有完整库存快照仍保留',
        'sync_interrupted' => '本轮 ERP 同步已中断，请重新同步；已有完整库存快照仍保留',
    ];

    /** Provider diagnostics can be arbitrary strings. Only local reason keys
     * may cross the public API boundary; never echo a stored error as a code. */
    public static function safeReason(mixed $reason, string $fallback = ''): string
    {
        if (is_string($reason) && isset(self::REASONS[$reason])) return $reason;
        return isset(self::REASONS[$fallback]) ? $fallback : '';
    }

    public static function forReason(string $reason): string
    {
        return self::REASONS[$reason] ?? 'ERP 处理未完成，请核对授权与同步状态后重试，或联系平台处理';
    }

    public static function syncStatus(string $status, string $reason = ''): string
    {
        if ($status === 'failed') return self::forReason($reason !== '' ? $reason : 'sync_failed');
        return [
            'idle' => '尚未同步 ERP 库存，请先验证账号并开启同步',
            'pending' => 'ERP 库存同步正在等待执行，完整完成后更新货盘',
            'running' => '正在同步 ERP 库存，完整完成后更新货盘',
            'completed' => '完整库存同步已完成',
            'cancelled' => '本轮同步已取消，已有完整库存保留',
            'interrupted' => '上一轮同步未完整完成，请重新同步；已有完整库存保留',
            'paused' => '当前 ERP 渠道或同步已暂停，账号与历史库存保留',
        ][$status] ?? 'ERP 同步状态尚未确认，请刷新核对';
    }
}
