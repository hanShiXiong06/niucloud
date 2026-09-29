<?php
declare(strict_types=1);

namespace addon\hsx_wecom\app\support;

/** 员工无需后台菜单权限即可阅读绑定结果，不输出 OAuth code/state 或任何凭证。 */
final class WecomBindingResultPage
{
    public static function render(array $result): string
    {
        $status = (string)($result['status'] ?? 'failed');
        $title = $status === 'bound' ? '绑定已完成' : ($status === 'scanned' ? '扫码完成，等待管理员确认' : '本次绑定未完成');
        $message = htmlspecialchars((string)($result['message'] ?? '请联系管理员重新生成二维码后扫码'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $hint = in_array($status, ['scanned', 'bound'], true)
            ? '请返回与管理员的会话，核对下方企业及身份标识。扫码不会赋予后台操作权限，系统账号权限仍由管理员单独分配。'
            : '请使用正确企业的企业微信扫码。不要将员工专属二维码转发给其他人。';
        $identity = '';
        if (in_array($status, ['scanned', 'bound'], true) && is_array($result['candidate'] ?? null)) {
            $corp = htmlspecialchars((string)($result['candidate']['corp_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $label = htmlspecialchars((string)($result['candidate']['identity_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $identity = '<p>企业：' . $corp . '<br>' . $label . '</p>';
        }
        return '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="referrer" content="no-referrer"><title>' . $title . '</title><style>'
            . 'body{margin:0;padding:32px 20px;background:#f5f7fa;color:#253041;font:16px/1.65 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}'
            . 'main{max-width:440px;margin:10vh auto;background:white;border-radius:16px;padding:28px;box-shadow:0 8px 30px #24324b0a}'
            . 'h1{font-size:22px;line-height:1.4;margin:0 0 20px}p{overflow-wrap:anywhere}small{display:block;color:#657085;margin-top:24px}'
            . '</style></head><body><main><h1>' . $title . '</h1><p>' . $message . '</p>' . $identity . '<small>' . $hint . '</small></main></body></html>';
    }

    public static function headers(): array
    {
        return [
            'Content-Type' => 'text/html;charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0', 'Pragma' => 'no-cache',
            'Referrer-Policy' => 'no-referrer', 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'",
        ];
    }
}
