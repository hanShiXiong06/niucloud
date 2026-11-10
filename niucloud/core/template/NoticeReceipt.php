<?php
declare(strict_types=1);

namespace core\template;

use core\exception\NoticeDeliveryException;

/** Explicit provider acceptance. HTTP 200 alone is never a successful notice. */
final class NoticeReceipt
{
    public static function accepted(array $receipt, string $channel): array
    {
        $name = $channel === 'weapp' ? '微信小程序' : '微信公众号';
        $raw = $receipt['errcode'] ?? null;
        if ((!is_int($raw) && !is_string($raw)) || !preg_match('/^-?[0-9]{1,10}$/D', (string)$raw)
            || !empty($receipt['error'])) {
            throw new NoticeDeliveryException($channel, 'unknown', '', '未返回可核实的'.$name.'发送回执，请先核对记录，勿重复发送');
        }
        if ((string)$raw !== '0') {
            // Provider errmsg may contain identity, IP or credentials. Keep it
            // out of generic logs and public API errors; the code is sufficient
            // for a channel/business adapter to show an approved explanation.
            throw new NoticeDeliveryException($channel, 'rejected', (string)$raw, $name.'未接受本次通知（错误码 '.(string)$raw.'）');
        }
        $id = $receipt['msgid'] ?? '';
        $id = (is_string($id) || is_int($id)) && preg_match('/^[A-Za-z0-9_-]{1,80}$/D', (string)$id) ? (string)$id : '';
        return ['accepted'=>true, 'channel'=>$channel, 'provider_code'=>'0', 'provider_id'=>$id];
    }
}
