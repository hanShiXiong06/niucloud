<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\support;

/**
 * UTF-8 清洗工具。
 *
 * 用途：打印链路返回的数据里常混入非 UTF-8 字节(云打印 SDK 的 GBK 报错、
 * 标签指令里的二进制控制字符、历史数据里被粘贴进来的 GBK 文本等)。
 * 这些字节会让 think\response\Json 在 json_encode 时抛
 * "Malformed UTF-8 characters, possibly incorrectly encoded"。
 *
 * 在 return success(...) 之前用 Utf8::clean() 兜一层，
 * 保证响应一定是合法 UTF-8，避免整个接口 500。
 *
 * 规则：
 *  - 已是合法 UTF-8 的字符串原样返回(零副作用)。
 *  - 非法的优先按 GB18030(兼容 GBK/GB2312)转 UTF-8，能救回中文。
 *  - 转换失败再用 mb_convert_encoding 丢弃非法字节。
 *  - 数组递归处理；其它标量(int/bool/null/float)原样返回。
 */
class Utf8
{
    public static function clean($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $key = is_string($k) ? self::cleanString($k) : $k;
                $out[$key] = self::clean($v);
            }
            return $out;
        }
        if (is_string($value)) {
            return self::cleanString($value);
        }
        return $value;
    }

    public static function cleanString(string $s): string
    {
        if ($s === '' || mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        // 多数是 GBK/GB2312 文本，用 GB18030 兜底转换可救回中文
        $converted = @iconv('GB18030', 'UTF-8//IGNORE', $s);
        if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }
        // 仍非法(如真二进制)→ 丢弃非法字节，保证合法 UTF-8
        return mb_convert_encoding($s, 'UTF-8', 'UTF-8');
    }
}
