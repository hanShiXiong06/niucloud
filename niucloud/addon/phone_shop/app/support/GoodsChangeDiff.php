<?php
declare(strict_types=1);

namespace addon\phone_shop\app\support;

/** 只比较业务字段，忽略时间戳、销量及采集快照等自动变化。 */
final class GoodsChangeDiff
{
    public const GOODS_FIELDS = [
        'goods_name' => '商品标题', 'sub_title' => '展示摘要', 'goods_type' => '商品类型',
        'goods_cover' => '商品封面', 'goods_image' => '商品图片', 'goods_video' => '商品视频', 'goods_desc' => '商品详情',
        'goods_category' => '商品分类', 'brand_id' => '品牌', 'memory_group' => '容量 / 规格',
        'condition_grade' => '成色', 'device_color' => '颜色', 'battery_health' => '电池健康度',
        'warranty_expire_time' => '保修到期日', 'status' => '上下架', 'stock' => '库存',
        'is_online_sellable' => '允许线上销售', 'label_ids' => '标签', 'service_ids' => '商品服务',
        'unit' => '单位', 'sort' => '排序', 'virtual_sale_num' => '虚拟销量', 'member_discount' => '会员价规则',
        'is_gift' => '赠品', 'attr_ids' => '参数模板', 'attr_format' => '商品参数', 'qc_report' => '质检报告',
        'delivery_type' => '配送方式', 'is_free_shipping' => '包邮', 'fee_type' => '运费规则',
        'delivery_money' => '运费', 'delivery_template_id' => '运费模板', 'supplier_id' => '供应商',
        'is_limit' => '限购', 'limit_type' => '限购方式', 'max_buy' => '限购数量', 'min_buy' => '起购数量',
        'poster_id' => '海报模板', 'form_id' => '表单模板', 'diy_detail_id' => '详情模板', 'delete_time' => '回收站状态',
    ];
    public const SKU_FIELDS = [
        'sku_name' => '规格名称', 'sku_no' => 'IMEI / 串号', 'sku_image' => '规格图片',
        'price' => '普通售价', 'sale_price' => '销售价', 'member_price' => '会员价',
        'pricing_base_price' => '同行基准价', 'market_price' => '划线价', 'cost_price' => '成本价',
        'stock' => '规格库存', 'weight' => '重量', 'volume' => '体积', 'condition_grade' => '规格成色',
    ];
    private const JSON_FIELDS = ['goods_category', 'label_ids', 'service_ids', 'attr_ids', 'attr_format', 'delivery_type', 'qc_report', 'member_price'];
    private const SET_FIELDS = ['goods_category', 'label_ids', 'service_ids', 'attr_ids', 'delivery_type'];
    private const MONEY_FIELDS = ['price', 'sale_price', 'market_price', 'cost_price', 'delivery_money', 'pricing_base_price'];
    private const NUMBER_FIELDS = ['brand_id', 'battery_health', 'warranty_expire_time', 'status', 'stock', 'is_online_sellable', 'sort', 'virtual_sale_num', 'is_gift', 'is_free_shipping', 'delivery_template_id', 'supplier_id', 'is_limit', 'limit_type', 'max_buy', 'min_buy', 'poster_id', 'form_id', 'diy_detail_id', 'weight', 'volume'];

    public static function normalize(string $field, $value)
    {
        if ($field === 'delete_time') return (int)$value > 0 ? 1 : 0;
        if (in_array($field, self::MONEY_FIELDS, true)) return $value === null || $value === '' ? null : number_format((float)$value, 2, '.', '');
        if (in_array($field, self::NUMBER_FIELDS, true)) return (float)$value;
        if (in_array($field, self::JSON_FIELDS, true)) {
            $value = is_array($value) ? $value : (json_decode((string)$value, true) ?: []);
            if (in_array($field, self::SET_FIELDS, true)) {
                $value = array_values(array_unique(array_map('strval', $value)));
                sort($value, SORT_STRING);
            } elseif ($field === 'member_price') {
                foreach ($value as &$price) $price = $price === '' || $price === null ? null : number_format((float)$price, 2, '.', '');
                unset($price);
                ksort($value);
            }
            return $value;
        }
        return (string)($value ?? '');
    }

    public static function between(array $before, array $after): array
    {
        $changes = [];
        foreach (self::GOODS_FIELDS as $field => $label) {
            self::compare($changes, $field, $label, $before['goods'][$field] ?? null, $after['goods'][$field] ?? null, '', !empty($before['goods']));
        }
        $ids = array_unique(array_merge(array_keys($before['skus'] ?? []), array_keys($after['skus'] ?? [])));
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $id) {
            $old = $before['skus'][$id] ?? [];
            $new = $after['skus'][$id] ?? [];
            $identity = trim((string)($new['sku_no'] ?? $old['sku_no'] ?? '')) ?: (trim((string)($new['sku_name'] ?? $old['sku_name'] ?? '')) ?: '默认规格');
            if (!$old || !$new) {
                $changes[] = ['field' => 'sku', 'label' => '规格', 'sku' => $identity, 'before' => $old ? '存在' : null, 'after' => $new ? '存在' : null];
            }
            foreach (self::SKU_FIELDS as $field => $label) {
                self::compare($changes, $field, $label, $old[$field] ?? null, $new[$field] ?? null, $identity, !empty($old) && !empty($new));
            }
        }
        return $changes;
    }

    private static function compare(array &$changes, string $field, string $label, $old, $new, string $sku = '', bool $existingSku = false): void
    {
        $old = self::normalize($field, $old);
        $new = self::normalize($field, $new);
        if ($old === $new) return;
        $changes[] = ['field' => $field, 'label' => $label, 'sku' => $sku, 'before' => $old, 'after' => $new,
            'selling_price' => $existingSku && in_array($field, ['price', 'sale_price', 'member_price', 'pricing_base_price', 'member_discount'], true)];
    }

    public static function hasPriceChange(array $changes): bool
    {
        foreach ($changes as $change) if (!empty($change['selling_price'])) return true;
        return false;
    }

    /** 删除/重建规格时也比较实际价格，不因 SKU 内部 ID 变化误标调价。 */
    public static function priceSignature(array $snapshot): array
    {
        $prices = [];
        foreach ($snapshot['skus'] ?? [] as $sku) {
            $one = [];
            foreach (['price', 'sale_price', 'member_price', 'pricing_base_price'] as $field) $one[$field] = self::normalize($field, $sku[$field] ?? null);
            $prices[] = json_encode($one, JSON_UNESCAPED_UNICODE);
        }
        sort($prices, SORT_STRING);
        return $prices;
    }
}
