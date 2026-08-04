<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

/**
 * AI 结构化渲染协议边界。业务插件只能提交受控 JSON，不能向终端注入组件或 HTML。
 */
final class AiBlockService
{
    private const TYPES = ['table', 'chart', 'quote_card', 'quote_aggregate', 'price_trend'];

    public function sanitize(array $blocks): array
    {
        $result = [];
        foreach (array_slice($blocks, 0, 12) as $block) {
            if (!is_array($block)) continue;
            $type = strtolower(trim((string)($block['type'] ?? '')));
            if (!in_array($type, self::TYPES, true)) continue;
            $data = $this->plainData($block['data'] ?? []);
            if (!is_array($data)) continue;
            $result[] = [
                'type' => $type,
                'source_plugin' => preg_replace('/[^a-z0-9_]/', '', strtolower((string)($block['source_plugin'] ?? ''))) ?: '',
                'data' => $data,
            ];
        }
        return $result;
    }

    private function plainData($data): ?array
    {
        if (!is_array($data)) return null;
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || strlen($json) > 160000) return null;
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : null;
    }
}
