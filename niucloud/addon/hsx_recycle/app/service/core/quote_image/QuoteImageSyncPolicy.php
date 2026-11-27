<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\quote_image;

use core\exception\CommonException;

/** 只管理报价图片同步，不涉及型号库、业务定价或财务。 */
class QuoteImageSyncPolicy
{
    public const MAX_MAPPINGS = 32;
    public static function defaults(): array
    {
        return ['enabled' => 0, 'source_url' => 'https://13303016666.huishoubaojia.com/',
            'run_time' => '09:30', 'mappings' => [], 'source_catalog' => [], 'revision' => 0];
    }

    public static function flag($value): int
    {
        if (!in_array($value, [true, false, 0, 1, '0', '1'], true)) throw new CommonException('开关参数不正确');
        return in_array($value, [true, 1, '1'], true) ? 1 : 0;
    }

    public static function normalize(array $input, array $catalog, array $categories): array
    {
        if (count($catalog) > 64) throw new CommonException('来源栏目超过64个，请联系管理员核对来源后再配置');
        $catalog = array_map(static function (array $item): array {
            $item['name'] = self::text((string)$item['name'], 60);
            $item['group'] = self::text((string)($item['group'] ?? ''), 60);
            return $item;
        }, $catalog);
        $result = self::defaults();
        $result['source_url'] = HuishouBaojiaSource::normalizeSourceUrl((string)($input['source_url'] ?? $result['source_url']));
        $result['enabled'] = self::flag($input['enabled'] ?? 0);
        $result['run_time'] = (string)($input['run_time'] ?? '09:30');
        if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $result['run_time'])) throw new CommonException('请选择有效的每日采集时间');
        $rows = $input['mappings'] ?? [];
        if (!is_array($rows) || count($rows) > 64) throw new CommonException('来源栏目数量异常，请重新读取');
        $sourceMap = array_column($catalog, null, 'id');
        $owned = array_column($categories, null, 'category_id');
        $sources = $targets = [];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new CommonException('分类映射格式不正确');
            $id = (string)($row['source_id'] ?? '');
            $target = $row['category_id'] ?? 0;
            if (!preg_match('/^[1-9][0-9]{0,11}$/D', $id) || isset($sources[$id])) throw new CommonException('来源栏目无效或重复，请重新读取栏目');
            if ((!is_int($target) && !is_string($target)) || !preg_match('/^[0-9]+$/D', (string)$target)) throw new CommonException('请选择有效的本站报价分类');
            $target = (int)$target;
            if (!isset($sourceMap[$id])) throw new CommonException('来源栏目已变化，请重新读取后设置映射');
            if ($target && !isset($owned[$target])) throw new CommonException('只能选择本站拥有的报价分类，不能覆盖公共库或其他站点');
            $enabled = self::flag($row['enabled'] ?? 1);
            if ($target && $enabled && isset($targets[$target])) throw new CommonException('同一个本站分类只能启用一条来源映射');
            if ($target && $enabled) $targets[$target] = true;
            $sources[$id] = true;
            if (!$target) continue;
            if (count($result['mappings']) >= self::MAX_MAPPINGS) throw new CommonException('分类映射最多配置32项');
            $source = $sourceMap[$id];
            $name = self::text(trim((string)($source['group'] ?? '') . ' / ' . (string)$source['name'], ' /'), 100);
            $result['mappings'][] = ['source_id' => $id, 'source_name' => $name, 'category_id' => $target, 'enabled' => $enabled];
        }
        if ($result['enabled'] && !$targets) throw new CommonException('请先关联并启用至少一个本站报价分类，再开启自动同步');
        $result['source_catalog'] = array_values($catalog);
        return $result;
    }

    public static function activeMappings(array $config): array
    {
        return array_values(array_filter($config['mappings'] ?? [], static fn(array $row): bool => !empty($row['enabled']) && (int)$row['category_id'] > 0));
    }

    public static function active(array $run): bool { return in_array($run['status'] ?? '', ['queued', 'running'], true); }
    public static function checkpointKey(array $mapping): string { return $mapping['source_id'] . ':' . $mapping['category_id']; }
    public static function text(string $text, int $limit): string { return preg_match('/^.{0,' . $limit . '}/us', $text, $match) ? $match[0] : ''; }
    public static function localHash(string $images): string { return hash('sha256', $images); }
    public static function day(int $now): string { return self::date($now)->format('Y-m-d'); }
    private static function date(int $now): \DateTimeImmutable { return (new \DateTimeImmutable('@' . $now))->setTimezone(new \DateTimeZone('Asia/Shanghai')); }
    public static function todayAt(array $config, int $now): int { return self::date($now)->setTime((int)substr($config['run_time'], 0, 2), (int)substr($config['run_time'], 3, 2))->getTimestamp(); }
    public static function nextAt(array $config, array $state, int $now): int
    {
        if (empty($config['enabled']) || !self::activeMappings($config)) return 0;
        $at = self::todayAt($config, $now);
        return ($state['last_auto_day'] ?? '') === self::day($now) ? $at + 86400 : $at;
    }
}
