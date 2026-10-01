<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\goods;

use addon\phone_shop\app\support\GoodsChangeDiff;
use core\exception\CommonException;
use think\facade\Db;

/** 与商品修改共用事务：失败不留成功日志，无实际变更不刷新调价时间。 */
final class CoreGoodsChangeLogService
{
    public const SOURCES = [
        'create' => '创建商品', 'edit' => '编辑商品', 'price' => '列表调价', 'member_price' => '会员价调整',
        'stock' => '手动调整库存', 'status' => '上下架', 'sort' => '调整排序', 'batch' => '批量设置',
        'material' => '完善商城资料', 'erp_price' => 'ERP 同步价格', 'agent_sync' => '主站同步',
        'intake' => 'ERP 交接建品', 'delete' => '移入回收站', 'restore' => '恢复商品', 'category' => '分类调整',
    ];

    public function capture(int $siteId, int $goodsId): array
    {
        if ($siteId <= 0 || $goodsId <= 0) throw new CommonException('商品所属站点或商品编号无效');
        $goods = Db::name('phone_shop_goods')->where('site_id', $siteId)->where('goods_id', $goodsId)->lock(true)->find();
        if (!$goods) throw new CommonException('商品不存在或不属于当前站点');
        $skus = Db::name('phone_shop_goods_sku')->where('site_id', $siteId)->where('goods_id', $goodsId)->order('sku_id asc')->lock(true)->select()->toArray();
        foreach ($skus as &$sku) {
            $snapshot = json_decode((string)($sku['device_snapshot'] ?? ''), true) ?: [];
            $sku['pricing_base_price'] = $snapshot['_tier_pricing']['base_price'] ?? null;
        }
        unset($sku);
        return ['goods' => $goods, 'skus' => array_column($skus, null, 'sku_id')];
    }

    public function record(int $siteId, int $goodsId, array $before, string $source, ?array $actor = null): void
    {
        if (!Db::connect()->getPdo()->inTransaction()) throw new \LogicException('商品修改日志必须和修改操作在同一事务中保存');
        $after = $this->capture($siteId, $goodsId);
        $changes = GoodsChangeDiff::between($before, $after);
        if ($changes === []) return;
        $now = time();
        $update = ['update_time' => $now];
        if (!empty($before) && (GoodsChangeDiff::hasPriceChange($changes) || GoodsChangeDiff::priceSignature($before) !== GoodsChangeDiff::priceSignature($after))) {
            $update['price_changed_at'] = $now;
        }
        Db::name('phone_shop_goods')->where('site_id', $siteId)->where('goods_id', $goodsId)->update($update);
        foreach ($changes as &$change) {
            $change['before_text'] = $this->display($siteId, $change['field'], $change['before']);
            $change['after_text'] = $this->display($siteId, $change['field'], $change['after']);
        }
        unset($change);
        if ($actor === null) {
            $uid = (int)request()->uid();
            $actor = ['uid' => $uid, 'name' => $uid > 0 ? (trim((string)request()->username()) ?: '管理员') : '系统任务'];
        }
        try {
            Db::name('phone_shop_goods_change_log')->insert([
                'site_id' => $siteId, 'goods_id' => $goodsId,
                'operator_uid' => (int)($actor['uid'] ?? 0), 'operator_name' => mb_substr((string)($actor['name'] ?? '系统任务'), 0, 100),
                'source' => $source, 'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'create_time' => $now,
            ]);
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'phone_shop_goods_change_log') && str_contains($e->getMessage(), '1146')) {
                throw new CommonException('商品修改日志表未初始化，请执行 phone_shop/sql/upgrade_goods_change_log.sql；本次修改未保存');
            }
            throw $e;
        }
    }

    /** 批量调用方按最多 500 件分块，避免把全站资料一次载入内存。 */
    public function mutate(int $siteId, array $goodsIds, string $source, callable $write, ?array $actor = null)
    {
        $goodsIds = array_values(array_unique(array_map('intval', $goodsIds)));
        sort($goodsIds, SORT_NUMERIC);
        return Db::transaction(function () use ($siteId, $goodsIds, $source, $write, $actor) {
            $before = [];
            foreach ($goodsIds as $id) $before[$id] = $this->capture($siteId, $id);
            $result = $write();
            foreach ($goodsIds as $id) $this->record($siteId, $id, $before[$id], $source, $actor);
            return $result;
        });
    }

    private function display(int $siteId, string $field, $value): string
    {
        if ($value === null || $value === '' || $value === []) return '未设置';
        $maps = [
            'status' => ['0' => '下架', '1' => '上架'], 'delete_time' => ['0' => '正常', '1' => '已移入回收站'],
            'is_online_sellable' => ['0' => '关闭', '1' => '开启'], 'is_free_shipping' => ['0' => '不包邮', '1' => '包邮'],
            'member_discount' => ['fixed_price' => '固定会员价', 'discount' => '会员等级折扣'],
            'delivery_type' => ['express' => '物流配送', 'local_delivery' => '同城配送', 'store' => '门店自提'],
        ];
        if (isset($maps[$field])) {
            if (is_array($value)) return implode('、', array_map(static fn($v) => $maps[$field][(string)$v] ?? (string)$v, $value));
            return $maps[$field][(string)$value] ?? (string)$value;
        }
        $refs = ['goods_category' => ['category', 'category_id', 'category_full_name'], 'brand_id' => ['brand', 'brand_id', 'brand_name'],
            'label_ids' => ['label', 'label_id', 'label_name'], 'service_ids' => ['service', 'service_id', 'service_name']];
        if (isset($refs[$field])) {
            [$table, $idField, $nameField] = $refs[$field];
            $ids = array_filter(array_map('intval', (array)$value));
            if (!$ids) return '未设置';
            $names = Db::name('phone_shop_goods_' . $table)->where('site_id', $siteId)->whereIn($idField, $ids)->column($nameField, $idField);
            return implode('、', array_map(static fn($id) => trim((string)($names[$id] ?? '')) ?: '编号 ' . $id, $ids));
        }
        if ($field === 'member_price' && is_array($value)) {
            $parts = [];
            foreach ($value as $level => $price) $parts[] = str_replace('level_', '等级 V', (string)$level) . '：' . ($price === null ? '未设置' : '¥' . $price);
            return implode('；', $parts);
        }
        if ($field === 'warranty_expire_time') return (int)$value > 0 ? date('Y-m-d', (int)$value) : '未设置';
        if ($field === 'battery_health') return (int)$value >= 0 ? $value . '%' : '未确认';
        if ($field === 'goods_desc') {
            preg_match_all('/<img\b[^>]*\bsrc=["\']([^"\']*)["\']/i', (string)$value, $images);
            $text = trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES, 'UTF-8'));
            return $text . ($images[1] ? "\n图片：" . implode("\n", $images[1]) : '');
        }
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string)$value;
    }
}
