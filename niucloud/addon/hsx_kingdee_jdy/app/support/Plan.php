<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\support;

final class Plan
{
    private const FLOWS = [
        'asset:inbound' => ['purchase', '购货入库', 'supplier'],
        'asset:sold' => ['sale', '销货出库', 'customer'],
        'asset:purchase_return' => ['purchase_return', '采购退货', 'supplier'],
        'asset:sale_return' => ['sale_return', '销售退货', 'customer'],
        'quantity:purchase_standard' => ['purchase', '购货入库', 'supplier'],
        'quantity:sale_standard' => ['sale', '销货出库', 'customer'],
    ];

    public static function validate(array $event): void
    {
        $p = $event['payload'] ?? [];
        $r = $p['ledger'] ?? [];
        if (($event['event_name'] ?? '') !== 'erp.ledger.recorded.v1' || (int)($event['event_version'] ?? 0) !== 1
            || !is_array($r) || (int)($event['site_id'] ?? 0) <= 0 || (int)($r['site_id'] ?? 0) !== (int)$event['site_id']
            || (int)($r['id'] ?? 0) <= 0 || !in_array($p['kind'] ?? '', ['asset', 'quantity', 'account', 'money'], true)
            || ($p['fact_key'] ?? '') !== $p['kind'] . ':' . (int)$r['id'] || empty($event['event_id'])
            || strlen((string)$event['event_id']) > 80 || (int)($event['occurred_at'] ?? 0) <= 0) {
            throw new Problem('FACT_INVALID', 'ERP业务事实格式或站点不一致');
        }
    }

    public static function fromEvent(array $event, array $mappings = []): array
    {
        self::validate($event);
        $p = $event['payload'];
        $r = $p['ledger'];
        $context = $p['source_context'] ?? [];
        $action = (string)($r['action'] ?? $r['biz_type'] ?? $r['direction'] ?? '');
        [$flow, $label, $role] = self::FLOWS[$p['kind'] . ':' . $action] ?? ['', '待人工核对', ''];
        $requirements = [];
        $references = [];
        $draft = null;
        $number = 'HSXJ' . $event['site_id'] . strtoupper(substr($p['kind'], 0, 1)) . $r['id'];
        if ($flow === '') {
            $requirements[] = match ($p['kind']) {
                'account' => '内部往来流水仅用于对账，不能再生成一笔应收应付；需核对业务单自动记账结果',
                'money' => '需关联原应收应付、账户和收付款核销关系；不触发实际支付',
                default => '撤销、调拨、维修或成本调整需核对原单状态和下游关系，不自动删除、反审核或写负数库存',
            };
        } else {
            $reference = static function (string $kind, int $id, string $name) use ($mappings, &$requirements, &$references): array {
                $map = $mappings[$kind . ':' . $id] ?? [];
                $number = (string)($map['external_number'] ?? '');
                $references[] = ['kind' => $kind, 'local_id' => $id, 'label' => $name, 'external_number' => $number];
                if ($id <= 0) $requirements[] = '业务事实缺少' . $name . 'ID，请核对来源单据';
                elseif ($number === '') $requirements[] = '请补充' . $name . '映射';
                return $map;
            };
            $party = $reference($role, (int)($context['party_id'] ?? $r['party_id'] ?? 0), $role === 'supplier' ? '供应商' : '客户');
            $material = $reference($p['kind'] === 'asset' ? 'asset_material' : 'product', (int)($r['asset_id'] ?? $r['product_id'] ?? 0), '商品');
            $out = in_array($flow, ['sale', 'purchase_return'], true);
            $warehouse = $reference('warehouse', (int)($r[$out ? 'before_warehouse_id' : 'after_warehouse_id'] ?? $r['warehouse_id'] ?? 0), '仓库');
            $unit = (string)($material['unit_id'] ?? '');
            if ($unit === '') $requirements[] = '请填写精斗云商品计量单位ID，不能用本地单位名称替代';
            $qty = $p['kind'] === 'asset' ? '1' : ltrim((string)($r['quantity'] ?? ''), '-');
            $amount = (string)($context['business_amount'] ?? '');
            if (!preg_match('/^\d+(?:\.\d{1,6})?$/D', $qty) || (float)$qty <= 0) $requirements[] = '来源数量无效';
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) $requirements[] = '缺少来源单据交易金额，库存成本不能代替采购价、售价或退货金额';
            $serial = trim((string)($r['imei'] ?? '')) ?: trim((string)($context['serial'] ?? $r['sn'] ?? ''));
            if ($p['kind'] === 'asset' && $serial === '') $requirements[] = '缺少设备串号，请核对IMEI或SN快照';
            if (($context['ownership_type'] ?? 'owned') !== 'owned') $requirements[] = '代卖或非自有设备需核对货权及供应商结算，不按自有采购销售同步';
            $isReturn = in_array($flow, ['purchase_return', 'sale_return'], true);
            if ($isReturn) $requirements[] = '待核对已同步原单、原分录ID、剩余可退数量和退款核销；不得作为独立入出库重发';
            if (!$requirements) {
                $entry = ['productNumber' => $material['external_number'], 'unit' => $unit,
                    'location' => $warehouse['external_number'], 'qty' => (float)$qty,
                    'price' => round((float)$amount / (float)$qty, 6), 'amount' => (float)$amount];
                if ($serial !== '') $entry['serNumList'] = [['serNum' => $serial]];
                $bill = ['number' => $number, 'date' => date('Y-m-d', (int)($r['occurred_at'] ?? $event['occurred_at'])),
                    $role . 'Number' => $party['external_number'], 'reverse_condition_calculate' => false,
                    'originNo' => (string)($r['source_no'] ?? $r['biz_no'] ?? ''), 'entries' => [$entry]];
                $draft = ['items' => [$bill]];
            }
        }
        $paths = ['purchase' => '/jdyscm/purchase/add', 'sale' => '/jdyscm/delivery/add',
            'purchase_return' => '/jdyscm/purchaseReturn/add', 'sale_return' => '/jdyscm/saleReturn/add'];
        return ['version' => 1, 'label' => $label, 'flow' => $flow, 'action' => $action, 'fact_key' => $p['fact_key'],
            'proposed_number' => $number, 'endpoint' => $paths[$flow] ?? '', 'references' => $references,
            'requirements' => array_values(array_unique($requirements)), 'draft' => $draft,
            'remote_acceptance' => ['计量单位、税金与金额精度', '序列号和库存更新时点', '审核权限与实际应收应付结果'],
            'write_enabled' => false, 'execution' => 'local_preflight'];
    }
}
