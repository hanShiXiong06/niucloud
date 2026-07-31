<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\core;

use core\exception\CommonException;

/**
 * AI 工具白名单。模型只能选择注册工具；站点、身份和权限始终由服务端注入。
 */
final class AiToolService
{
    public function definitions(array $context): array
    {
        $definitions = [];
        foreach ((array)event('HsxAiToolRegistryRequested', $context) as $response) {
            foreach ($this->rows($response) as $row) {
                $key = trim((string)($row['key'] ?? ''));
                if ($key === '' || !$this->visible($row, $context)) continue;
                $definitions[$key] = [
                    'key' => $key,
                    'name' => trim((string)($row['name'] ?? $key)),
                    'description' => trim((string)($row['description'] ?? '')),
                    'input_schema' => (array)($row['input_schema'] ?? ['type' => 'object', 'properties' => []]),
                    'read_only' => !empty($row['read_only']),
                    'source_plugin' => trim((string)($row['source_plugin'] ?? '')),
                ];
            }
        }
        return array_values($definitions);
    }

    public function execute(string $toolKey, array $arguments, array $context): array
    {
        $definition = null;
        foreach ($this->definitions($context) as $row) if ($row['key'] === $toolKey) $definition = $row;
        if ($definition === null) throw new CommonException('当前身份无权使用该 AI 工具');
        $this->validateArguments($arguments, (array)$definition['input_schema']);
        $event = [
            'tool_key' => $toolKey,
            'arguments' => $arguments,
            'site_id' => (int)($context['site_id'] ?? 0),
            'scene' => (string)($context['scene'] ?? ''),
            'actor' => (array)($context['actor'] ?? []),
        ];
        foreach ((array)event('HsxAiToolExecuteRequested', $event) as $response) {
            foreach ($this->rows($response) as $row) {
                if (!empty($row['handled']) && (string)($row['tool_key'] ?? '') === $toolKey) {
                    return (array)($row['data'] ?? []);
                }
            }
        }
        throw new CommonException('AI 工具暂时不可用：' . $toolKey);
    }

    private function visible(array $definition, array $context): bool
    {
        $siteId = (int)($context['site_id'] ?? 0);
        if ($siteId <= 0) return false;
        $integrationKey = trim((string)($definition['integration_key'] ?? $definition['source_plugin'] ?? ''));
        if ($integrationKey !== '' && $integrationKey !== 'hsx_ai'
            && !(new AiIntegrationService())->isEnabled($siteId, $integrationKey)) return false;
        $scene = (string)($context['scene'] ?? '');
        $scenes = array_values(array_filter((array)($definition['scenes'] ?? []), 'is_string'));
        if ($scenes !== [] && !in_array($scene, $scenes, true)) return false;
        $actor = (array)($context['actor'] ?? []);
        $actorType = (string)($actor['type'] ?? 'guest');
        $auth = (string)($definition['auth'] ?? 'admin');
        if ($auth === 'member' && ($actorType !== 'member' || (int)($actor['id'] ?? 0) <= 0)) return false;
        if ($auth === 'admin' && ($actorType !== 'admin' || (int)($actor['id'] ?? 0) <= 0)) return false;
        $required = array_values(array_filter((array)($definition['permissions'] ?? []), 'is_string'));
        $granted = array_values(array_filter((array)($actor['permissions'] ?? []), 'is_string'));
        return $required === [] || array_diff($required, $granted) === [];
    }

    private function validateArguments(array $arguments, array $schema): void
    {
        foreach ((array)($schema['required'] ?? []) as $field) {
            if (!array_key_exists((string)$field, $arguments)) throw new CommonException('AI 工具缺少参数：' . $field);
        }
        foreach ((array)($schema['properties'] ?? []) as $field => $rule) {
            if (!array_key_exists($field, $arguments)) continue;
            $type = (string)($rule['type'] ?? 'string');
            if ($type === 'string' && !is_string($arguments[$field])) throw new CommonException('AI 工具参数类型错误：' . $field);
            if ($type === 'integer' && !is_int($arguments[$field])) throw new CommonException('AI 工具参数类型错误：' . $field);
            if (is_string($arguments[$field]) && mb_strlen($arguments[$field]) > (int)($rule['maxLength'] ?? 2000)) {
                throw new CommonException('AI 工具参数过长：' . $field);
            }
        }
    }

    private function rows($response): array
    {
        if (!is_array($response) || $response === []) return [];
        return array_keys($response) === range(0, count($response) - 1) ? $response : [$response];
    }
}
