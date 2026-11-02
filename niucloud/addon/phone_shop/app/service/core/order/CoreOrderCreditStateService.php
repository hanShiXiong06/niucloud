<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\core\order;

use core\exception\CommonException;
use think\facade\Log;

/** 挂账进度以 ERP 的实时只读契约为准；不依赖本地支付金额，也不生成 Pay 流水。 */
class CoreOrderCreditStateService
{
    public function getState(array $order, bool $lock = false): array
    {
        $rows = $this->enrichOrders([$order], (int)($order['site_id'] ?? 0), $lock);
        return (array)($rows[0]['finance_snapshot'] ?? $this->unavailable($order));
    }

    public function assertCanComplete(array $order): void
    {
        if (($order['payment_mode'] ?? '') !== 'offline_credit') return;
        $state = $this->getState($order, true);
        if (empty($state['available']) || empty($state['matched']) || empty($state['can_complete'])) {
            throw new CommonException((string)($state['message'] ?? 'ERP 收款状态尚未核实，未完成订单。'));
        }
    }

    /** 一页最多100条，一个站点一次事件；保留原行字段，补只读 finance_snapshot。 */
    public function enrichOrders(array $orders, int $siteId = 0, bool $lock = false): array
    {
        $groups = [];
        foreach ($orders as $index => $order) {
            if (!is_array($order) || ($order['payment_mode'] ?? '') !== 'offline_credit') continue;
            $rowSite = (int)($order['site_id'] ?? $siteId);
            $orders[$index]['finance_snapshot'] = $this->unavailable(array_replace($order, ['site_id' => $rowSite]));
            if ($rowSite <= 0 || ($siteId > 0 && $rowSite !== $siteId) || (int)($order['order_id'] ?? 0) <= 0) continue;
            $groups[$rowSite][$index] = array_replace($order, ['site_id' => $rowSite]);
        }
        foreach ($groups as $rowSite => $group) {
            foreach (array_chunk($group, 100, true) as $chunk) {
                try {
                    $responses = $this->request((int)$rowSite, array_values($chunk), $lock);
                    $providers = array_values(array_filter($responses, static fn($r) =>
                        is_array($r) && ($r['provider'] ?? '') === 'hsx_erp' && (int)($r['site_id'] ?? 0) === (int)$rowSite
                    ));
                    // 多个响应也不能随意选，避免扩展冲突时默认放行。
                    if (count($providers) !== 1 || !is_array($providers[0]['orders'] ?? null)) continue;
                    $states = $providers[0]['orders'];
                    foreach ($chunk as $index => $order) {
                        $state = $states[(int)$order['order_id']] ?? null;
                        if (!is_array($state) || (int)($state['order_id'] ?? 0) !== (int)$order['order_id']
                            || (int)($state['site_id'] ?? 0) !== (int)$rowSite
                            || (string)($state['order_no'] ?? '') !== (string)($order['order_no'] ?? '')) continue;
                        $orders[$index]['finance_snapshot'] = $this->validateSnapshot($state, $order);
                    }
                } catch (\Throwable $e) {
                    // 读取失败只阻止本次完成，不重新发起收款、不回退真实结算。
                    Log::error('[phone_shop] ERP收款状态只读核对失败，site_id=' . $rowSite . ': ' . $e->getMessage());
                }
            }
        }
        return $orders;
    }

    protected function request(int $siteId, array $orders, bool $lock): array
    {
        return (array)event('PhoneShopOrderCreditStateRequested', [
            'site_id' => $siteId, 'orders' => $orders, 'lock' => $lock,
        ]);
    }

    /** 契约防御：服务端布尔值与金额自相矛盾时，不能凭 can_complete 放行。 */
    private function validateSnapshot(array $state, array $order): array
    {
        $valid = ($state['provider'] ?? '') === 'hsx_erp' && is_bool($state['can_complete'] ?? null)
            && is_bool($state['available'] ?? null) && is_bool($state['matched'] ?? null);
        // 尚未取得或尚未关联的账务不是“零元应收”。保留提供方的明确原因和未知值；
        // 只有明确不可完成才可短路，任何 can_complete=true 仍执行下面的完整校验。
        if ($valid && $state['can_complete'] === false && ($state['available'] === false || $state['matched'] === false)) return $state;
        $amounts = [];
        foreach (['amount', 'settled_amount', 'remaining_amount', 'cash_received_amount', 'offset_amount', 'original_amount', 'expected_amount', 'returned_unreceived_amount'] as $key) {
            $amounts[$key] = $this->cents($state[$key] ?? null);
            if ($amounts[$key] === null) $valid = false;
        }
        if ($valid) {
            $valid = $amounts['settled_amount'] <= $amounts['amount']
                && $amounts['remaining_amount'] === $amounts['amount'] - $amounts['settled_amount']
                && $amounts['cash_received_amount'] + $amounts['offset_amount'] === $amounts['settled_amount']
                && $amounts['original_amount'] === $this->cents($order['order_money'] ?? null)
                && $amounts['returned_unreceived_amount'] <= $amounts['original_amount']
                && $amounts['expected_amount'] === $amounts['original_amount'] - $amounts['returned_unreceived_amount'];
        }
        if (!empty($state['can_complete'])) {
            $valid = $valid && ($state['available'] ?? false) === true && ($state['matched'] ?? false) === true
                && ($state['status'] ?? '') === 'settled' && $amounts['remaining_amount'] === 0
                && $amounts['amount'] === $amounts['expected_amount'];
        }
        if (!$valid) {
            $state['can_complete'] = false;
            $state['status'] = 'inconsistent'; $state['status_name'] = '账务需核对';
            $state['message'] = 'ERP 收款核对结果不一致，请财务核对；未执行完成订单，请勿重复收款。';
        }
        return $state;
    }

    private function cents($value): ?int
    {
        if (!is_numeric($value)) return null;
        $number = (float)$value;
        if (!is_finite($number) || $number < 0 || abs($number * 100 - round($number * 100)) > 0.00001) return null;
        return (int)round($number * 100);
    }

    private function unavailable(array $order): array
    {
        return [
            'provider' => 'hsx_erp', 'available' => false, 'matched' => false, 'can_complete' => false,
            'site_id' => (int)($order['site_id'] ?? 0), 'order_id' => (int)($order['order_id'] ?? 0),
            'order_no' => (string)($order['order_no'] ?? ''), 'status' => 'unavailable', 'status_name' => '收款状态待核实',
            'amount' => null, 'settled_amount' => null, 'remaining_amount' => null,
            'cash_received_amount' => null, 'offset_amount' => null, 'confirmed_at' => 0,
            'payment_methods' => [], 'capital_accounts' => [], 'settlements' => [], 'receivables' => [],
            'message' => 'ERP 收款核对服务未响应，请刷新或联系管理员；未完成订单，请勿重复收款。',
        ];
    }
}
