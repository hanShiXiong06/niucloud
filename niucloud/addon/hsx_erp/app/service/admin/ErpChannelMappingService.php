<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use think\facade\Db;

/**
 * ERP 与外部销售渠道之间的“桥”。
 *
 * 这里只保存映射、渠道商品关联和发布状态；渠道数据永远不能通过本服务
 * 覆盖 ERP 的分类、规格、图片、售价或质检报告。
 */
final class ErpChannelMappingService
{
    public function resolve(array $event): array
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $channelKey = $this->channelKey((string)($event['channel_key'] ?? 'phone_shop'));
        $context = (array)($event['erp_context'] ?? []);
        if ($siteId <= 0) return ['ready' => false, 'missing' => ['site_id']];

        $policy = (new ErpConfigService())->getMarketplaceChannel($channelKey, $siteId);
        $result = [
            'channel_key' => $channelKey,
            'policy' => $policy,
            'category_ids' => [],
            'category_name' => '',
            'specs' => [],
            'missing' => [],
        ];

        $categoryPath = $this->normalizeCategoryPath((string)($context['category_path'] ?? ''));
        if ((string)$policy['category_mode'] === 'independent') {
            if ($categoryPath === '') {
                $result['missing'][] = 'category_source';
            } else {
                $row = Db::name('erp_channel_category_mapping')->where([
                    ['site_id', '=', $siteId],
                    ['channel_key', '=', $channelKey],
                    ['erp_category_key', '=', $this->categoryKey($categoryPath)],
                    ['status', '=', 'active'],
                ])->find();
                if ($row) {
                    $result['category_ids'] = $this->decodeList($row['channel_category_path'] ?? []);
                    $result['category_name'] = (string)($row['channel_category_name'] ?? '');
                } else {
                    $result['missing'][] = 'category';
                }
            }
        }

        $specs = $this->erpSpecs($context);
        if ((string)$policy['spec_mode'] === 'independent') {
            foreach ($specs as $key => $value) {
                if ($value === '') continue;
                $row = Db::name('erp_channel_attribute_value_mapping')->where([
                    ['site_id', '=', $siteId],
                    ['channel_key', '=', $channelKey],
                    ['erp_attribute_key', '=', $key],
                    ['erp_value_key', '=', $this->valueKey($value)],
                    ['status', '=', 'active'],
                ])->find();
                if ($row) {
                    $result['specs'][$key] = (string)($row['channel_value_name'] ?? '');
                } else {
                    $result['missing'][] = 'spec:' . $key;
                }
            }
        }

        $result['missing'] = array_values(array_unique($result['missing']));
        $result['ready'] = $result['missing'] === [];
        return $result;
    }

    public function recordCategoryMapping(array $data): void
    {
        $siteId = (int)($data['site_id'] ?? 0);
        $channelKey = $this->channelKey((string)($data['channel_key'] ?? 'phone_shop'));
        $erpPath = $this->normalizeCategoryPath((string)($data['erp_category_path'] ?? ''));
        $channelPath = array_values(array_filter(array_map('strval', (array)($data['channel_category_path'] ?? [])), static fn(string $id): bool => $id !== ''));
        if ($siteId <= 0 || $erpPath === '' || $channelPath === []) return;

        $now = time();
        $where = [
            'site_id' => $siteId,
            'channel_key' => $channelKey,
            'erp_category_key' => $this->categoryKey($erpPath),
        ];
        $this->upsert('erp_channel_category_mapping', $where, [
            'erp_category_path' => $erpPath,
            'channel_category_id' => (string)end($channelPath),
            'channel_category_path' => $this->encode($channelPath),
            'channel_category_name' => trim((string)($data['channel_category_name'] ?? '')),
            'mapping_source' => in_array((string)($data['mapping_source'] ?? 'manual'), ['manual', 'projection'], true)
                ? (string)$data['mapping_source'] : 'manual',
            'status' => 'active',
            'operator_uid' => (int)($data['operator_uid'] ?? 0),
            'operator_name' => trim((string)($data['operator_name'] ?? '')),
            'update_at' => $now,
        ], $now);
    }

    public function recordAttributeValueMapping(array $data): void
    {
        $siteId = (int)($data['site_id'] ?? 0);
        $channelKey = $this->channelKey((string)($data['channel_key'] ?? 'phone_shop'));
        $attributeKey = $this->attributeKey((string)($data['erp_attribute_key'] ?? ''));
        $erpValue = $this->valueText($data['erp_value_name'] ?? '');
        $channelValue = $this->valueText($data['channel_value_name'] ?? '');
        if ($siteId <= 0 || $attributeKey === '' || $erpValue === '' || $channelValue === '') return;

        $now = time();
        $source = in_array((string)($data['mapping_source'] ?? 'manual'), ['manual', 'projection'], true)
            ? (string)$data['mapping_source'] : 'manual';
        $operator = [
            'operator_uid' => (int)($data['operator_uid'] ?? 0),
            'operator_name' => trim((string)($data['operator_name'] ?? '')),
        ];
        $channelAttributeId = trim((string)($data['channel_attribute_id'] ?? '')) ?: ($channelKey . '.' . $attributeKey);

        $this->upsert('erp_channel_attribute_mapping', [
            'site_id' => $siteId,
            'channel_key' => $channelKey,
            'erp_attribute_key' => $attributeKey,
        ], [
            'erp_attribute_id' => (int)($data['erp_attribute_id'] ?? 0),
            'erp_attribute_name' => trim((string)($data['erp_attribute_name'] ?? '')) ?: $attributeKey,
            'channel_attribute_id' => $channelAttributeId,
            'channel_attribute_name' => trim((string)($data['channel_attribute_name'] ?? '')) ?: $attributeKey,
            'mapping_source' => $source,
            'status' => 'active',
            ...$operator,
            'update_at' => $now,
        ], $now);

        $this->upsert('erp_channel_attribute_value_mapping', [
            'site_id' => $siteId,
            'channel_key' => $channelKey,
            'erp_attribute_key' => $attributeKey,
            'erp_value_key' => $this->valueKey($erpValue),
        ], [
            'erp_value_name' => $erpValue,
            'channel_attribute_id' => $channelAttributeId,
            'channel_value_id' => trim((string)($data['channel_value_id'] ?? '')) ?: $this->valueKey($channelValue),
            'channel_value_name' => $channelValue,
            'mapping_source' => $source,
            'status' => 'active',
            ...$operator,
            'update_at' => $now,
        ], $now);
    }

    public function recordListing(array $data): void
    {
        $siteId = (int)($data['site_id'] ?? 0);
        $assetId = (int)($data['erp_asset_id'] ?? 0);
        if ($siteId <= 0 || $assetId <= 0) return;
        $channelKey = $this->channelKey((string)($data['channel_key'] ?? 'phone_shop'));
        $status = in_array((string)($data['status'] ?? 'pending'), ['pending', 'published', 'offline', 'sold', 'error'], true)
            ? (string)$data['status'] : 'pending';
        $now = time();
        $snapshot = (array)($data['mapping_snapshot'] ?? []);
        $payload = (array)($data['payload'] ?? []);
        $this->upsert('erp_channel_listing', [
            'site_id' => $siteId,
            'channel_key' => $channelKey,
            'erp_asset_id' => $assetId,
        ], [
            'channel_item_id' => trim((string)($data['channel_item_id'] ?? '')),
            'channel_intake_id' => trim((string)($data['channel_intake_id'] ?? '')),
            'status' => $status,
            'publish_mode' => (string)($data['publish_mode'] ?? 'direct') === 'manual' ? 'manual' : 'direct',
            'mapping_snapshot' => $this->encode($snapshot),
            'payload_hash' => hash('sha256', $this->encode($payload)),
            'last_error' => trim((string)($data['last_error'] ?? '')),
            'published_at' => $status === 'published' ? (int)($data['published_at'] ?? $now) : 0,
            'update_at' => $now,
        ], $now);
    }

    /** 商城运营完成时只学习映射并记录渠道商品关联，不覆盖 ERP 主资料。 */
    public function recordManualCompletion(array $event, array $erpContext): void
    {
        $siteId = (int)($event['site_id'] ?? 0);
        $mapping = (array)($event['mapping'] ?? []);
        $operator = (array)($event['operator'] ?? []);
        $common = [
            'site_id' => $siteId,
            'channel_key' => 'phone_shop',
            'mapping_source' => 'manual',
            'operator_uid' => (int)($operator['uid'] ?? 0),
            'operator_name' => trim((string)($operator['name'] ?? '')),
        ];

        $this->recordCategoryMapping($common + [
            'erp_category_path' => (string)($erpContext['category_path'] ?? ''),
            'channel_category_path' => (array)($mapping['category_ids'] ?? []),
            'channel_category_name' => (string)($mapping['category_name'] ?? ''),
        ]);

        $erpSpecs = $this->erpSpecs($erpContext);
        foreach (['memory' => '容量/内存', 'condition_grade' => '成色'] as $key => $label) {
            $channelValue = $this->valueText($mapping[$key] ?? '');
            if (($erpSpecs[$key] ?? '') === '' || $channelValue === '') continue;
            $this->recordAttributeValueMapping($common + [
                'erp_attribute_key' => $key,
                'erp_attribute_name' => $label,
                'erp_value_name' => $erpSpecs[$key],
                'channel_attribute_id' => 'phone_shop.' . $key,
                'channel_attribute_name' => $label,
                'channel_value_name' => $channelValue,
            ]);
        }
        foreach ((array)($mapping['attr_format'] ?? []) as $row) {
            if (!is_array($row)) continue;
            $erpKey = $this->attributeKey((string)($row['erp_attribute_key'] ?? ''));
            $erpValue = $this->valueText($row['erp_value_name'] ?? '');
            $channelValue = $this->valueText($row['channel_value_name'] ?? $row['attr_child_value_name'] ?? $row['select_child_val'] ?? '');
            if ($erpKey === '' || $erpValue === '' || $channelValue === '') continue;
            $this->recordAttributeValueMapping($common + [
                'erp_attribute_key' => $erpKey,
                'erp_attribute_name' => (string)($row['erp_attribute_name'] ?? $erpKey),
                'erp_value_name' => $erpValue,
                'channel_attribute_id' => (string)($row['channel_attribute_id'] ?? $row['attr_value_id'] ?? ''),
                'channel_attribute_name' => (string)($row['channel_attribute_name'] ?? $row['attr_name'] ?? $erpKey),
                'channel_value_id' => (string)($row['channel_value_id'] ?? $row['attr_child_value_id'] ?? ''),
                'channel_value_name' => $channelValue,
            ]);
        }

        $this->recordListing([
            'site_id' => $siteId,
            'channel_key' => 'phone_shop',
            'erp_asset_id' => (int)($event['erp_asset_id'] ?? 0),
            'channel_item_id' => (string)($event['goods_id'] ?? ''),
            'channel_intake_id' => (string)($event['intake_id'] ?? ''),
            'status' => 'published',
            'publish_mode' => 'manual',
            'mapping_snapshot' => $mapping,
            'payload' => ['erp_context' => $erpContext, 'mapping' => $mapping],
        ]);
    }

    public function categoryKey(string $path): string
    {
        return hash('sha256', $this->normalizeCategoryPath($path));
    }

    public function normalizeCategoryPath(string $path): string
    {
        $path = preg_replace('/[\\x{00A0}\\x{3000}]+/u', ' ', trim($path)) ?? trim($path);
        $parts = preg_split('~[/\\\\／＞>,，|｜]+~u', $path) ?: [];
        $parts = array_values(array_filter(array_map(static function (string $value): string {
            return trim(preg_replace('/\\s+/u', ' ', $value) ?? $value);
        }, $parts), static fn(string $value): bool => $value !== ''));
        return implode('/', $parts);
    }

    private function erpSpecs(array $context): array
    {
        $specs = (array)($context['specs'] ?? []);
        $memory = $this->valueText($context['memory'] ?? $specs['memory'] ?? $specs['storage'] ?? $specs['capacity'] ?? '');
        $condition = $this->valueText($context['condition_grade'] ?? $specs['condition_grade'] ?? $specs['condition'] ?? $specs['grade'] ?? '');
        return ['memory' => $memory, 'condition_grade' => $condition];
    }

    private function valueText(mixed $value): string
    {
        if (is_scalar($value)) return trim((string)$value);
        if (!is_array($value)) return '';
        foreach (['label', 'name', 'text', 'title', 'value_name', 'value'] as $key) {
            if (!array_key_exists($key, $value)) continue;
            $text = $this->valueText($value[$key]);
            if ($text !== '') return $text;
        }
        $parts = array_values(array_filter(array_map(fn(mixed $item): string => $this->valueText($item), $value)));
        return implode('、', $parts);
    }

    private function valueKey(string $value): string
    {
        return hash('sha256', mb_strtolower(trim($value), 'UTF-8'));
    }

    private function attributeKey(string $key): string
    {
        return substr(preg_replace('/[^a-zA-Z0-9_.\-]/', '_', trim($key)) ?? '', 0, 100);
    }

    private function channelKey(string $key): string
    {
        return substr(preg_replace('/[^a-zA-Z0-9_\-]/', '', trim($key)) ?: 'phone_shop', 0, 40);
    }

    private function decodeList(mixed $value): array
    {
        if (is_array($value)) return array_values($value);
        $decoded = json_decode(trim((string)$value), true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function encode(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]';
    }

    private function upsert(string $table, array $where, array $data, int $now): void
    {
        $query = Db::name($table)->where($where);
        $exists = $query->field('id')->find();
        if ($exists) {
            Db::name($table)->where($where)->update($data);
            return;
        }
        Db::name($table)->insert($where + $data + ['create_at' => $now]);
    }
}
