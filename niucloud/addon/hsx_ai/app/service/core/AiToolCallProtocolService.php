<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

/**
 * 兼容部分推理模型把 function calling 作为文本协议输出的情况。
 *
 * 标准 OpenAI tool_calls 仍由 Provider 原生处理；这里只负责识别并隐藏
 * `<｜tool▁...｜>` 一类内部标记，避免协议正文进入页面或会话记录。
 */
final class AiToolCallProtocolService
{
    private const STREAM_GUARD_CHARS = 32;

    /** @return array{content:string,tool_calls:array,has_protocol:bool} */
    public function parse(string $content): array
    {
        $hasProtocol = $this->hasProtocol($content);
        if (!$hasProtocol) {
            return ['content' => $content, 'tool_calls' => [], 'has_protocol' => false];
        }

        $calls = [];
        $pattern = '/function\s*<[|｜]tool(?:▁|_)sep[|｜]>\s*([a-zA-Z0-9_.:-]{1,128})/u';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $index => $match) {
            $name = trim((string)($match[1][0] ?? ''));
            $start = (int)($match[0][1] ?? 0) + strlen((string)($match[0][0] ?? ''));
            if ($name === '' || $start <= 0) continue;

            $tail = substr($content, $start);
            $end = strlen($tail);
            if (preg_match('/<[|｜]tool(?:▁|_)call(?:▁|_)end[|｜]>/u', $tail, $endMatch, PREG_OFFSET_CAPTURE)) {
                $end = (int)$endMatch[0][1];
            }
            $arguments = $this->jsonObject(substr($tail, 0, $end));
            if ($arguments === null) continue;
            $calls[] = [
                'id' => 'call_text_' . $index . '_' . substr(hash('sha256', $name . ':' . $arguments), 0, 10),
                'type' => 'function',
                'function' => ['name' => $name, 'arguments' => $arguments],
            ];
        }

        // 工具选择阶段的文本不是最终答复。识别成功后全部隐藏，待工具执行完再输出结论。
        return [
            'content' => $calls !== [] ? '' : $this->stripProtocolTail($content),
            'tool_calls' => $calls,
            'has_protocol' => true,
        ];
    }

    /** @return array{pending:string,capturing:bool} */
    public function streamState(): array
    {
        return ['pending' => '', 'capturing' => false];
    }

    /**
     * 保留少量尾部字符用于识别被 SSE 分片拆开的协议标记。
     * 返回值可以安全地立即展示给用户。
     */
    public function push(array &$state, string $delta): string
    {
        if ($delta === '') return '';
        if (!empty($state['capturing'])) return '';

        $state['pending'] = (string)($state['pending'] ?? '') . $delta;
        $position = $this->protocolPosition($state['pending']);
        if ($position !== null) {
            $prefix = substr($state['pending'], 0, $position);
            $lineBreak = strrpos($prefix, "\n");
            $safe = $lineBreak === false ? '' : substr($prefix, 0, $lineBreak + 1);
            $state['pending'] = '';
            $state['capturing'] = true;
            return $safe;
        }

        $length = mb_strlen($state['pending']);
        if ($length <= self::STREAM_GUARD_CHARS) return '';
        $safeLength = $length - self::STREAM_GUARD_CHARS;
        $safe = mb_substr($state['pending'], 0, $safeLength);
        $state['pending'] = mb_substr($state['pending'], $safeLength);
        return $safe;
    }

    public function finish(array &$state): string
    {
        if (!empty($state['capturing'])) {
            $state['pending'] = '';
            return '';
        }
        $pending = (string)($state['pending'] ?? '');
        $state['pending'] = '';
        return $pending;
    }

    public function sanitize(string $content): string
    {
        return (string)$this->parse($content)['content'];
    }

    public function hasProtocol(string $content): bool
    {
        return $this->protocolPosition($content) !== null;
    }

    private function protocolPosition(string $content): ?int
    {
        $positions = [];
        foreach (['<｜tool', '<|tool', '<tool_call', 'function<｜tool', 'function<|tool'] as $marker) {
            $position = strpos($content, $marker);
            if ($position !== false) $positions[] = $position;
        }
        return $positions === [] ? null : min($positions);
    }

    private function stripProtocolTail(string $content): string
    {
        $position = $this->protocolPosition($content);
        if ($position === null) return $content;
        $prefix = substr($content, 0, $position);
        $lineBreak = strrpos($prefix, "\n");
        return trim($lineBreak === false ? '' : substr($prefix, 0, $lineBreak));
    }

    private function jsonObject(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) return null;
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($text);
        for ($index = $start; $index < $length; $index++) {
            $character = $text[$index];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($character === '\\') {
                    $escaped = true;
                } elseif ($character === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($character === '"') {
                $inString = true;
            } elseif ($character === '{') {
                $depth++;
            } elseif ($character === '}') {
                $depth--;
                if ($depth === 0) {
                    $json = substr($text, $start, $index - $start + 1);
                    return is_array(json_decode($json, true)) ? $json : null;
                }
            }
        }
        return null;
    }
}
