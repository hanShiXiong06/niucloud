<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\support;

use think\facade\Log;

/**
 * UTF-8 清洗 + 调试日志工具。
 *
 * 背景：打印接口偶发 "Malformed UTF-8 characters, possibly incorrectly encoded"。
 * 现象常见为"第一次打印正常，第二次手动打印报错"——多半是第一次把云打印的 GBK
 * 返回 / 二进制标签指令落库到某字段，第二次读出来塞进 JSON 响应，json_encode 抛错。
 *
 * clean() 做两件事：
 *  1) 兜底清洗：把非法 UTF-8 字节转成合法 UTF-8(GBK 按 GB18030 救回中文；
 *     真二进制丢弃非法字节)，避免接口 500。
 *  2) 调试定位：每发现一个非法字段，就把【字段路径 / 长度 / 原始字节 hex / 转换方式 /
 *     结果预览】写到日志,reproduce 一次就能精确定位是哪个字段、什么编码坏了。
 *
 * 用法：return success(Utf8::clean($data, '场景标签'));
 * 日志在 runtime/log 里，搜 "[print-utf8]"。
 */
class Utf8
{
    /** 是否记录非法字段日志(定位完可改 false 关掉) */
    public static bool $debug = true;

    /**
     * @param mixed  $value 任意返回数据(数组/字符串/标量)
     * @param string $path  当前字段路径(顶层传个场景标签，便于区分是哪个接口)
     */
    public static function clean($value, string $path = 'root')
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? self::cleanString($k, $path . '.<key>') : $k;
                $out[$key] = self::clean($v, $path . '.' . $k);
            }
            return $out;
        }
        if (is_string($value)) {
            return self::cleanString($value, $path);
        }
        return $value;
    }

    public static function cleanString(string $s, string $path = ''): string
    {
        if ($s === '' || mb_check_encoding($s, 'UTF-8')) {
            return $s; // 合法 UTF-8：零副作用，也不记日志
        }

        // 多数是 GBK/GB2312 文本，用 GB18030 兜底转换可救回中文
        $converted = @iconv('GB18030', 'UTF-8//IGNORE', $s);
        $byGbk = ($converted !== false && mb_check_encoding($converted, 'UTF-8'));
        $result = $byGbk ? $converted : mb_convert_encoding($s, 'UTF-8', 'UTF-8');

        if (self::$debug) {
            Log::write(sprintf(
                '[print-utf8] 非法UTF-8字段 path=%s len=%d 原始前80字节hex=%s 方式=%s 结果预览=%s',
                $path,
                strlen($s),
                bin2hex(substr($s, 0, 80)),
                $byGbk ? 'GB18030->UTF8' : 'strip-invalid',
                mb_substr($result, 0, 50)
            ), 'error');
        }

        return $result;
    }
}
