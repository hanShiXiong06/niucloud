<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\printer\template;

use core\base\BaseAdminService;

/**
 * 变量替换服务类
 * 负责模板变量的替换处理
 * Class VariableReplaceService
 * @package addon\recycle\app\service\admin\printer\template
 */
class VariableReplaceService extends BaseAdminService
{
    /**
     * 替换模板变量
     * @param string $content 模板内容
     * @param array $data 替换数据
     * @return string
     */
    public function replaceVariables(string $content, array $data): string
    {
        // 查找所有模板变量
        preg_match_all('/\{\{([^}]+)\}\}/', $content, $matches);
        $template_variables = $matches[1] ?? [];
        
        foreach ($data as $key => $value) {
            // 确保value是字符串类型，处理各种数据类型
            $valueStr = $this->convertValueToString($value);
            
            // 处理需要换行的长文本字段
            if ($this->needsLineWrap($key)) {
                $valueStr = $this->wrapLongText($valueStr, 20);
            }
            
            $variable_pattern = '{{' . $key . '}}';
            if (strpos($content, $variable_pattern) !== false) {
                // 如果是需要换行的字段且包含换行符，需要特殊处理
                if ($this->needsLineWrap($key) && strpos($valueStr, "\n") !== false) {
                    $content = $this->replaceWithMultiLineText($content, $variable_pattern, $valueStr);
                } else {
                    $content = str_replace($variable_pattern, $valueStr, $content);
                }
            }
        }
        
        return $content;
    }

    /**
     * 将值转换为字符串
     * @param mixed $value
     * @return string
     */
    private function convertValueToString($value): string
    {
        if ($value === null) {
            return '';
        }
        
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        
        if (is_numeric($value)) {
            // 数字类型需要特殊处理
            if (is_float($value) || strpos((string)$value, '.') !== false) {
                return number_format((float)$value, 2, '.', '');
            }
            return (string)$value;
        }
        
        return (string)$value;
    }

    /**
     * 判断字段是否需要换行处理
     * @param string $fieldName
     * @return bool
     */
    private function needsLineWrap(string $fieldName): bool
    {
        // 定义需要换行处理的字段
        $wrapFields = [
            'check_result',    // 质检结果
            'remark',          // 备注
            'price_remark',    // 定价备注
            'description',     // 描述
            'note',           // 说明
            'comment'         // 评论
        ];
        
        return in_array($fieldName, $wrapFields);
    }

    /**
     * 将长文本按指定长度换行
     * @param string $text
     * @param int $maxLength
     * @return string
     */
    private function wrapLongText(string $text, int $maxLength = 20): string
    {
        if (empty($text)) {
            return $text;
        }

        // 先按已有换行符拆分，再对每一行单独做长度截断
        $existingLines = explode("\n", $text);
        $resultLines = [];

        foreach ($existingLines as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }

            // 如果该行不超过限制，直接保留
            if (mb_strlen($segment, 'UTF-8') <= $maxLength) {
                $resultLines[] = $segment;
                continue;
            }

            // 超长行按 maxLength 截断
            $currentLine = '';
            $length = mb_strlen($segment, 'UTF-8');

            for ($i = 0; $i < $length; $i++) {
                $char = mb_substr($segment, $i, 1, 'UTF-8');

                if (mb_strlen($currentLine . $char, 'UTF-8') > $maxLength) {
                    if (!empty($currentLine)) {
                        $resultLines[] = $currentLine;
                        $currentLine = $char;
                    } else {
                        $resultLines[] = $char;
                    }
                } else {
                    $currentLine .= $char;
                }
            }

            if (!empty($currentLine)) {
                $resultLines[] = $currentLine;
            }
        }

        return implode("\n", $resultLines);
    }

    /**
     * 将包含换行符的文本替换为多个TEXT标签
     * @param string $content
     * @param string $variable_pattern
     * @param string $multiLineText
     * @return string
     */
    private function replaceWithMultiLineText(string $content, string $variable_pattern, string $multiLineText): string
    {
        // 查找包含该变量的TEXT标签
        $pattern = '/<TEXT([^>]*?)>' . preg_quote($variable_pattern, '/') . '<\/TEXT>/';
        
        if (preg_match($pattern, $content, $matches)) {
            $fullTextTag = $matches[0];
            $attributes = $matches[1];
            
            // 解析原始TEXT标签的属性
            $x = $this->extractAttributeFromString($attributes, 'x', '0');
            $y = $this->extractAttributeFromString($attributes, 'y', '0');
            $w = $this->extractAttributeFromString($attributes, 'w', '1');
            $h = $this->extractAttributeFromString($attributes, 'h', '1');
            $r = $this->extractAttributeFromString($attributes, 'r', '0');
            
            // 分割文本行（过滤空行）
            $lines = array_values(array_filter(
                explode("\n", $multiLineText),
                function ($line) { return trim($line) !== ''; }
            ));
            $newTextTags = [];

            // 为每一行创建一个TEXT标签
            foreach ($lines as $index => $line) {
                // 计算新的y坐标（每行间距约24dots）
                $newY = intval($y) + ($index * 24);
                $escapedLine = htmlspecialchars(trim($line), ENT_QUOTES, 'UTF-8', false);
                $newTextTags[] = "<TEXT x=\"{$x}\" y=\"{$newY}\" w=\"{$w}\" h=\"{$h}\" r=\"{$r}\">{$escapedLine}</TEXT>";
            }
            
            // 替换原始的TEXT标签
            $content = str_replace($fullTextTag, implode('', $newTextTags), $content);
        } else {
            // 如果没有找到TEXT标签，按普通方式替换
            $content = str_replace($variable_pattern, str_replace("\n", " ", $multiLineText), $content);
        }
        
        return $content;
    }

    /**
     * 从属性字符串中提取指定属性值
     * @param string $attributes
     * @param string $name
     * @param string $default
     * @return string
     */
    private function extractAttributeFromString(string $attributes, string $name, string $default = ''): string
    {
        if (preg_match("/{$name}=\"([^\"]*)\"/", $attributes, $matches)) {
            return $matches[1];
        }
        return $default;
    }
}

