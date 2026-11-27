<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

/** 资料待办独立于商品上下架，复用货源 raw_payload 的命名空间，不修改表结构。 */
final class IntakeMaterialTask
{
    public const KEY = '_material_task';

    public static function payload(mixed $value): array
    {
        if (is_array($value)) return $value;
        $decoded = is_string($value) ? json_decode($value, true) : [];
        return is_array($decoded) ? $decoded : [];
    }

    public static function read(mixed $payload): array
    {
        $raw = self::payload($payload);
        $task = (array)($raw[self::KEY] ?? []);
        $status = (string)($task['status'] ?? '');
        if (!in_array($status, ['pending', 'processing', 'completed'], true)) $status = 'none';
        return array_replace($task, [
            'status' => $status,
            'status_name' => ['none' => '无资料待办', 'pending' => '待完善', 'processing' => '完善中', 'completed' => '已完善'][$status],
            'revision' => max(0, (int)($task['revision'] ?? 0)),
            'operator_name' => (string)($task['operator_name'] ?? ''),
            'completed_at' => (int)($task['completed_at'] ?? 0),
        ]);
    }

    /** ERP 再次推送不能重置商城运营已保存的待办和处理记录。 */
    public static function mergeSnapshot(mixed $existing, array $incoming, int $now): array
    {
        $old = self::payload($existing);
        unset($incoming[self::KEY]);
        unset($incoming['_agent_distribution']);
        if (isset($old['_agent_distribution'])) $incoming['_agent_distribution'] = $old['_agent_distribution'];
        if (isset($old[self::KEY]) && is_array($old[self::KEY])) {
            $incoming[self::KEY] = $old[self::KEY];
            if (!empty($old['basic_first'])) $incoming['basic_first'] = 1;
        } elseif ((int)($incoming['basic_first'] ?? 0) === 1 || (int)($incoming['erp_asset_id'] ?? 0) > 0) {
            $incoming[self::KEY] = ['status' => 'pending', 'revision' => 0, 'created_at' => $now, 'operator_uid' => 0, 'operator_name' => '', 'completed_at' => 0];
        }
        return $incoming;
    }

    public static function transition(array $raw, string $action, int $uid, string $name, int $now): array
    {
        $task = self::read($raw);
        if ($task['status'] === 'none') throw new \InvalidArgumentException('当前货源没有资料完善待办');
        if (!in_array($action, ['save', 'complete'], true)) throw new \InvalidArgumentException('资料处理动作无效');
        $history = array_values((array)($task['history'] ?? []));
        $history[] = ['action' => $action, 'operator_uid' => $uid, 'operator_name' => $name, 'at' => $now];
        $raw[self::KEY] = array_replace($task, [
            'status' => $action === 'complete' ? 'completed' : 'processing',
            'revision' => $task['revision'] + 1,
            'operator_uid' => $uid,
            'operator_name' => $name,
            'updated_at' => $now,
            'completed_at' => $action === 'complete' ? $now : 0,
            'history' => array_slice($history, -20),
        ]);
        return $raw;
    }

    public static function unknownFields(array $goods): array
    {
        $missing = [];
        foreach (['memory_group' => '容量/规格', 'device_color' => '颜色', 'condition_grade' => '成色'] as $key => $label) {
            if (trim((string)($goods[$key] ?? '')) === '') $missing[] = $label;
        }
        if ((int)($goods['battery_health'] ?? -1) < 0) $missing[] = '电池健康度';
        if ((int)($goods['warranty_expire_time'] ?? 0) <= 0) $missing[] = '保修到期日';
        return $missing;
    }
}
