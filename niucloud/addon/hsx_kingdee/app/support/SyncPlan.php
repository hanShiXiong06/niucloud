<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\support;

/** A review plan is deliberately not a WebAPI Save payload. */
final class SyncPlan
{
    public static function fromEvent(array $event, array $mappings = []): array
    {
        $payload = $event['payload'] ?? [];
        $row = $payload['ledger'] ?? [];
        $kind = $payload['kind'] ?? '';
        $site = (int)($event['site_id'] ?? 0);
        if (($event['event_name'] ?? '') !== 'erp.ledger.recorded.v1' || ($event['event_version'] ?? 0) !== 1
            || trim((string)($event['event_id'] ?? '')) === '' || strlen((string)$event['event_id']) > 80 || (int)($event['occurred_at'] ?? 0) <= 0
            || $site <= 0 || (int)($row['site_id'] ?? 0) !== $site || (int)($row['id'] ?? 0) <= 0
            || !in_array($kind, ['asset', 'quantity', 'account', 'money'], true)
            || ($payload['fact_key'] ?? '') !== $kind . ':' . (int)$row['id']) {
            throw new \InvalidArgumentException('业务事实格式或站点不一致');
        }
        $action = (string)($row['action'] ?? $row['biz_type'] ?? $row['direction'] ?? '');
        $forms = [
            'asset:inbound' => ['采购入库', 'STK_InStock', 'supplier'],
            'asset:sold' => ['销售出库', 'SAL_OUTSTOCK', 'customer'],
            'asset:purchase_return' => ['采购退料', 'PUR_MRB', 'supplier'],
            'asset:sale_return' => ['销售退货', 'SAL_RETURNSTOCK', 'customer'],
            'quantity:purchase_standard' => ['采购入库', 'STK_InStock', 'supplier'],
            'quantity:sale_standard' => ['销售出库', 'SAL_OUTSTOCK', 'customer'],
        ];
        [$label, $form, $partyRole] = $forms[$kind . ':' . $action] ?? ['业务规则待确认', '', ''];
        $requirements = [];
        $isReverse = str_contains($action, 'cancel') || str_contains($action, 'restore') || str_contains($action, 'reverse');
        if ($isReverse) {
            $label = '撤销或恢复核对';
            $requirements[] = '核对原金蝶单据、审核状态、下游应收应付及是否跨期；禁止直接重发负数单或删除原单';
        } elseif ($kind === 'account') {
            $label = '往来账对账';
            $requirements[] = '这是内部记账流水，不是收付款；先核对金蝶业务单是否已自动生成应收应付，避免重复记账';
        } elseif ($kind === 'money') {
            $label = $action === 'in' ? '收款或退款入账核对' : '付款或退款出账核对';
            $requirements[] = '核对结算单、收付方向、币别、银行账户与核销关系；只登记账务，不触发实际支付';
        } elseif ($form === '') {
            $requirements[] = '尚未确认该业务的金蝶单据规则，请人工处理';
        }
        $amount = (string)($row['amount'] ?? $row['cost_delta'] ?? '0');
        if ($kind === 'asset' && $form !== '') {
            // Moving an unchanged-cost device still carries inventory value; cost_delta may be zero.
            $costField = in_array($form, ['SAL_OUTSTOCK', 'PUR_MRB'], true) ? 'before_total_cost' : 'after_total_cost';
            $amount = (string)($row[$costField] ?? '');
            if (!preg_match('/^\d+(?:\.\d{1,2})?$/D', $amount)) {
                $amount = '';
                $requirements[] = '业务事实缺少有效库存成本快照，需要核对来源单据；不能使用成本调整差额代替';
            }
        }
        $references = [];
        $addReference = static function (string $type, int $id, string $name) use (&$requirements, &$references, $mappings): void {
            $number = $mappings[$type . ':' . $id] ?? '';
            $references[] = ['kind' => $type, 'local_id' => $id, 'label' => $name, 'external_number' => $number];
            if ($id <= 0) $requirements[] = '业务事实缺少' . $name . '记录ID，需要核对来源单据';
            elseif ($number === '') $requirements[] = '缺少' . $name . '映射';
        };
        if ($form !== '') {
            $addReference($partyRole, (int)($payload['source_context']['party_id'] ?? $row['party_id'] ?? 0), $partyRole === 'supplier' ? '供应商' : '客户');
            // An asset mapping represents one complete model/spec combination, not just its display name.
            $addReference($kind === 'asset' ? 'asset_material' : 'product', (int)($row['asset_id'] ?? $row['product_id'] ?? 0), '物料');
            $out = in_array($form, ['SAL_OUTSTOCK', 'PUR_MRB'], true);
            $warehouse = (int)($row[$out ? 'before_warehouse_id' : 'after_warehouse_id'] ?? $row['warehouse_id'] ?? 0);
            $addReference('warehouse', $warehouse, '仓库');
            $requirements[] = '待测试账套确认：组织、单据类型、计量单位、币别、税率、货主、仓位及库存更新时点';
            if ($kind === 'asset') $requirements[] = '待确认物料序列号规则与 IMEI 明细字段';
            if (in_array($form, ['PUR_MRB', 'SAL_RETURNSTOCK'], true)) {
                $requirements[] = '必须关联原单行及剩余可退数量，不能脱离原单重复退货';
            }
            if ($form === 'SAL_OUTSTOCK') $requirements[] = '销售含税价必须来自销售明细；库存成本不能作为售价';
        }
        return [
            'version' => 1, 'label' => $label, 'action' => $action, 'form_id' => $form,
            'fact_key' => $payload['fact_key'], 'quantity' => $kind === 'asset' ? '1' : (string)($row['quantity'] ?? ''),
            'amount' => $amount,
            'amount_role' => in_array($kind, ['asset', 'quantity'], true) ? '库存成本，不是售价或实收' : '内部账务金额',
            'references' => $references, 'requirements' => array_values(array_unique($requirements)),
            'execution' => 'preview_only', 'write_allowed' => false,
        ];
    }
}
