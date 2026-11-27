<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\support;

/** 纯规则：不按名称/尾号建关联，不把代理货当作本站自有库存。 */
final class ErpMallDevicePolicy
{
    public static function identity(array $device): array
    {
        $imei = trim((string)($device['imei'] ?? ''));
        $sn = strtoupper(trim((string)($device['sn'] ?? '')));
        return [
            'imei' => preg_match('/^\d{15}$/D', $imei) ? $imei : '',
            'sn' => preg_match('/^(?=.*[A-Z])[A-Z0-9]{8,32}$/D', $sn) ? $sn : '',
        ];
    }

    public static function inspect(int $siteId, array $source, array $assets, bool $sold = false): array
    {
        $fail = static fn(string $message): array => ['state' => 'conflict', 'message' => $message, 'asset_id' => 0];
        $device = (array)($source['device'] ?? []);
        if (!empty($device['identity_conflict'])) return $fail('商城SKU串号与原始采集信息不一致，请先核对，不能自动选择其中一个');
        // 商城 is_proxy 为废弃字段，物权按 source 判断，不能误拦主站历史自营货。
        if (!in_array((string)($device['source'] ?? ''), ['', '0', '1', (string)$siteId], true)) {
            return $fail('代理货不建立本站自有资产，请到货主站点处理');
        }
        if ((int)($source['quantity'] ?? 1) !== 1 || (!$sold && (int)($source['stock'] ?? 0) !== 1)) {
            return $fail('不是单台单串号库存，请拆分设备后再关联；标品不走此入口');
        }
        if (!$sold && (string)($source['sale_status'] ?? '') !== 'available') {
            return $fail('商城商品已锁定或已售，请核对订单，不能作为在库期初');
        }
        $identity = self::identity($device);
        if ($identity['imei'] === '' && $identity['sn'] === '') return $fail('缺少完整IMEI/SN，不能使用尾号或商品名自动配对');
        if (count($assets) > 1) return $fail('同一串号匹配到多条ERP资产，请先核对重复记录；不会自动删除');
        $linkedId = (int)($source['erp_asset_id'] ?? $device['erp_asset_id'] ?? 0);
        if ($assets === []) {
            if ($linkedId > 0) return $fail('商品原有关联已失效或串号不符，不能覆盖原关联');
            if ((float)($source['cost_price'] ?? 0) <= 0) return $fail('ERP无此设备且商城成本未确认，请先补齐真实成本（不是销售价）');
            return ['state' => 'opening', 'asset_id' => 0, 'message' => '未找到ERP资产；确认后按商城成本建立自有期初库存，不生成采购应付'];
        }
        $asset = $assets[0];
        if ((int)($asset['site_id'] ?? 0) !== $siteId) return $fail('设备不属于当前站点');
        if ($linkedId > 0 && $linkedId !== (int)$asset['id']) return $fail('商品已有其他ERP关联，请人工核对');
        foreach (['imei', 'sn'] as $field) {
            $actual = strtoupper(trim((string)($asset[$field] ?? '')));
            if ($identity[$field] !== '' && $actual !== '' && $actual !== $identity[$field]) {
                return $fail('IMEI与SN指向不一致，不能自动关联');
            }
        }
        if ((string)($asset['ownership_type'] ?? '') !== 'owned') return $fail('ERP物权不是本公司自有，请走代卖/物权确认流程');
        if ((string)($asset['status'] ?? '') !== 'in_stock' || (int)($asset['sale_item_id'] ?? 0) > 0) return $fail('ERP设备已售、退回或作废，请核对是否重复成交');
        if ((float)($asset['total_cost'] ?? 0) <= 0) return $fail('ERP原设备成本未确认，请先核对真实成本，不能将缺失成本当作零成本利润');
        if ($sold && (int)($source['sale_at'] ?? 0) > 0 && (int)($asset['stock_in_at'] ?? 0) > (int)$source['sale_at']) return $fail('ERP入库时间晚于本次成交，请先核对是否为同串号后续回购设备');
        if (in_array((string)($asset['refurbish_status'] ?? ''), ['pending', 'processing', 'failed'], true)) return $fail('ERP设备尚未完成整备，不能跳过整备直接销售');
        return [
            'state' => $linkedId > 0 ? 'linked' : 'match', 'asset_id' => (int)$asset['id'],
            'message' => '复用ERP原资产、原成本及原采购应付；不重复入库、不改变商城售价',
        ];
    }
}
