<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support\print;

/** 将统一模板渲染为云打印文本或移动端蓝牙指令。 */
final class ErpPrintRenderer
{
    public function render(string $content, array $context, string $driver, string $printType): array
    {
        $text = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/', function (array $match) use ($context): string {
            $value = $this->value($context, $match[1]);
            return is_scalar($value) ? (string)$value : '';
        }, $content) ?? $content;

        if ($driver === 'bluetooth_tspl' || ($printType === 'label' && str_contains($content, 'SIZE '))) {
            return ['format' => 'tspl', 'content' => $text, 'encoding' => 'utf-8'];
        }
        if ($driver === 'bluetooth_escpos') {
            return ['format' => 'escpos_text', 'content' => $this->plainText($text), 'encoding' => 'utf-8'];
        }
        return ['format' => 'cloud_markup', 'content' => $text, 'encoding' => 'utf-8'];
    }

    private function value(array $context, string $path): mixed
    {
        $value = $context;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) return '';
            $value = $value[$key];
        }
        return $value;
    }

    private function plainText(string $content): string
    {
        $content = preg_replace('/<br\s*\/?\s*>/i', "\n", $content) ?? $content;
        $content = preg_replace('/<\/?(?:center|left|right|b|strong|small|big|FH\d|FS)[^>]*>/i', '', $content) ?? $content;
        return trim(html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8')) . "\n\n";
    }
}
