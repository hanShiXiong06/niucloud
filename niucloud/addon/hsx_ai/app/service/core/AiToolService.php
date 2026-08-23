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
        $allowedToolKeys = array_values(array_filter(array_map('strval', (array)($context['allowed_tool_keys'] ?? []))));
        foreach ((array)event('HsxAiToolRegistryRequested', $context) as $response) {
            foreach ($this->rows($response) as $row) {
                $key = trim((string)($row['key'] ?? ''));
                if ($allowedToolKeys !== [] && !in_array($key, $allowedToolKeys, true)) continue;
                if ($key === '' || isset($definitions[$key]) || !$this->visible($row, $context)) continue;
                $readOnly = !empty($row['read_only']);
                $definitions[$key] = [
                    'key' => $key,
                    'name' => trim((string)($row['name'] ?? $key)),
                    'description' => trim((string)($row['description'] ?? '')),
                    'input_schema' => (array)($row['input_schema'] ?? ['type' => 'object', 'properties' => []]),
                    'read_only' => $readOnly,
                    'source_plugin' => trim((string)($row['source_plugin'] ?? '')),
                    'auth' => trim((string)($row['auth'] ?? 'admin')) ?: 'admin',
                    'permissions' => array_values(array_filter((array)($row['permissions'] ?? []), 'is_string')),
                    'risk_level' => trim((string)($row['risk_level'] ?? ($readOnly ? 'read' : 'high'))),
                    'requires_confirmation' => !$readOnly || !empty($row['requires_confirmation']),
                    'max_result_chars' => max(1000, min(50000, (int)($row['max_result_chars'] ?? 20000))),
                ];
            }
        }
        return array_values($definitions);
    }

    public function execute(string $toolKey, array $arguments, array $context): array
    {
        $definition = $this->definition($toolKey, $context);
        if ($definition === null) throw new CommonException('当前身份无权使用该 AI 工具');
        if (!empty($definition['requires_confirmation']) && !$this->isApproved($toolKey, $arguments, $context)) {
            throw new CommonException('该操作需要用户确认后才能执行');
        }
        $this->validateArguments($arguments, (array)$definition['input_schema']);
        $event = [
            'tool_key' => $toolKey,
            'arguments' => $arguments,
            'site_id' => (int)($context['site_id'] ?? 0),
            'scene' => (string)($context['scene'] ?? ''),
            'actor' => (array)($context['actor'] ?? []),
            'source_plugin' => (string)$definition['source_plugin'],
        ];
        foreach ((array)event('HsxAiToolExecuteRequested', $event) as $response) {
            foreach ($this->rows($response) as $row) {
                if (!empty($row['handled'])
                    && (string)($row['tool_key'] ?? '') === $toolKey
                    && (string)($row['source_plugin'] ?? '') === (string)$definition['source_plugin']) {
                    return (array)($row['data'] ?? []);
                }
            }
        }
        throw new CommonException('AI 工具暂时不可用：' . $toolKey);
    }

    public function definition(string $toolKey, array $context): ?array
    {
        foreach ($this->definitions($context) as $row) {
            if ((string)$row['key'] === $toolKey) return $row;
        }
        return null;
    }

    public function approvalFingerprint(string $toolKey, array $arguments): string
    {
        $this->sortRecursive($arguments);
        return hash('sha256', $toolKey . ':' . json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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
        if (!in_array($auth, ['public', 'member', 'admin', 'system', 'authenticated'], true)) return false;
        if ($auth === 'member' && ($actorType !== 'member' || (int)($actor['id'] ?? 0) <= 0)) return false;
        if ($auth === 'admin' && ($actorType !== 'admin' || (int)($actor['id'] ?? 0) <= 0)) return false;
        if ($auth === 'system' && $actorType !== 'system') return false;
        if ($auth === 'authenticated' && !in_array($actorType, ['member', 'admin', 'system'], true)) return false;
        $required = array_values(array_filter((array)($definition['permissions'] ?? []), 'is_string'));
        $granted = array_values(array_filter((array)($actor['permissions'] ?? []), 'is_string'));
        return $required === [] || array_diff($required, $granted) === [];
    }

    private function validateArguments(array $arguments, array $schema): void
    {
        $properties = (array)($schema['properties'] ?? []);
        if (($schema['additionalProperties'] ?? true) === false) {
            $unknown = array_diff(array_keys($arguments), array_keys($properties));
            if ($unknown !== []) throw new CommonException('AI 工具包含未允许参数：' . reset($unknown));
        }
        foreach ((array)($schema['required'] ?? []) as $field) {
            if (!array_key_exists((string)$field, $arguments)) throw new CommonException('AI 工具缺少参数：' . $field);
        }
        foreach ($properties as $field => $rule) {
            if (!array_key_exists($field, $arguments)) continue;
            $type = (string)($rule['type'] ?? 'string');
            if ($type === 'string' && !is_string($arguments[$field])) throw new CommonException('AI 工具参数类型错误：' . $field);
            if ($type === 'integer' && !is_int($arguments[$field])) throw new CommonException('AI 工具参数类型错误：' . $field);
            if ($type === 'number' && !is_int($arguments[$field]) && !is_float($arguments[$field])) throw new CommonException('AI 工具参数类型错误：' . $field);
            if ($type === 'boolean' && !is_bool($arguments[$field])) throw new CommonException('AI 工具参数类型错误：' . $field);
            if ($type === 'array' && !is_array($arguments[$field])) throw new CommonException('AI 工具参数类型错误：' . $field);
            if (is_string($arguments[$field]) && mb_strlen($arguments[$field]) > (int)($rule['maxLength'] ?? 2000)) {
                throw new CommonException('AI 工具参数过长：' . $field);
            }
            if (isset($rule['enum']) && !in_array($arguments[$field], (array)$rule['enum'], true)) {
                throw new CommonException('AI 工具参数不在允许范围：' . $field);
            }
            if (is_numeric($arguments[$field]) && isset($rule['minimum']) && $arguments[$field] < $rule['minimum']) {
                throw new CommonException('AI 工具参数小于最小值：' . $field);
            }
            if (is_numeric($arguments[$field]) && isset($rule['maximum']) && $arguments[$field] > $rule['maximum']) {
                throw new CommonException('AI 工具参数大于最大值：' . $field);
            }
        }
    }

    private function isApproved(string $toolKey, array $arguments, array $context): bool
    {
        $fingerprint = $this->approvalFingerprint($toolKey, $arguments);
        return in_array($fingerprint, array_values(array_filter(
            (array)($context['approved_tool_calls'] ?? []),
            'is_string'
        )), true);
    }

    private function sortRecursive(array &$value): void
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) $this->sortRecursive($item);
        }
        unset($item);
    }

    private function rows($response): array
    {
        if (!is_array($response) || $response === []) return [];
        return array_keys($response) === range(0, count($response) - 1) ? $response : [$response];
    }
}
