<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

/**
 * 明确业务意图的快速路由器。
 *
 * 业务插件只返回自己能识别的工具与参数；AI 中台负责授权裁剪和置信度竞争。
 * 无高置信结果时仍交给模型编排，避免中台硬编码业务词汇。
 */
final class AiToolIntentService
{
    /** @return array{tool_key:string,arguments:array,confidence:float} */
    public function resolve(string $prompt, array $context, array $definitions, string $previousToolKey = '', array $previousToolResult = []): array
    {
        $rows = $this->resolveAll($prompt, $context, $definitions, $previousToolKey, $previousToolResult);
        return $rows[0] ?? [];
    }

    /** @return array<int,array{tool_key:string,arguments:array,confidence:float,aggregate:bool}> */
    public function resolveAll(string $prompt, array $context, array $definitions, string $previousToolKey = '', array $previousToolResult = []): array
    {
        $prompt = mb_substr(trim($prompt), 0, 2000);
        if ($prompt === '') return [];
        $available = array_column($definitions, null, 'key');
        $candidates = [];
        foreach ((array)event('HsxAiToolIntentRequested', [
            'prompt' => $prompt,
            'previous_tool_key' => $previousToolKey,
            'previous_tool_result' => $previousToolResult,
            'available_tool_keys' => array_keys($available),
            'site_id' => (int)($context['site_id'] ?? 0),
            'scene' => (string)($context['scene'] ?? ''),
            'agent_key' => (string)($context['agent_key'] ?? ''),
            'actor' => (array)($context['actor'] ?? []),
        ]) as $response) {
            foreach ($this->rows($response) as $row) {
                if (!is_array($row) || empty($row['handled'])) continue;
                $toolKey = trim((string)($row['tool_key'] ?? ''));
                $confidence = max(0.0, min(1.0, (float)($row['confidence'] ?? 0)));
                if (!isset($available[$toolKey]) || $confidence < 0.8) continue;
                $candidates[] = [
                    'tool_key' => $toolKey,
                    'arguments' => (array)($row['arguments'] ?? []),
                    'confidence' => $confidence,
                    'aggregate' => !empty($row['aggregate']),
                ];
            }
        }
        if ($candidates === []) return [];
        usort($candidates, static fn(array $left, array $right): int => $right['confidence'] <=> $left['confidence']);
        $aggregate = array_values(array_filter($candidates, static fn(array $row): bool => !empty($row['aggregate'])));
        if (count($aggregate) > 1) {
            $unique = [];
            foreach ($aggregate as $row) $unique[$row['tool_key']] = $row;
            return array_values($unique);
        }
        if (isset($candidates[1])
            && $candidates[0]['tool_key'] !== $candidates[1]['tool_key']
            && abs($candidates[0]['confidence'] - $candidates[1]['confidence']) < 0.05) return [];
        return [$candidates[0]];
    }

    private function rows($response): array
    {
        if (!is_array($response) || $response === []) return [];
        return array_keys($response) === range(0, count($response) - 1) ? $response : [$response];
    }
}
