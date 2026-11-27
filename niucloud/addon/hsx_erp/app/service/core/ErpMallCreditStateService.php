<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core;

use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpPartyMember;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSaleOrder;
use addon\hsx_erp\app\model\ErpSaleReturnItem;
use addon\hsx_erp\app\model\ErpSaleReturnOrder;
use addon\hsx_erp\app\model\ErpSettlement;
use addon\hsx_erp\app\model\ErpSettlementLink;

/** 商城挂账只读契约。所有金额来自应收和已确认结算，不制造支付流水。 */
class ErpMallCreditStateService
{
    public function getStates(int $siteId, array $orders, bool $lock = false): array
    {
        if ($siteId <= 0) return [];
        $orders = array_values(array_filter(array_slice($orders, 0, 100), static fn($o) =>
            is_array($o) && (int)($o['site_id'] ?? $siteId) === $siteId && (int)($o['order_id'] ?? 0) > 0
        ));
        if ($orders === []) return [];
        $ids = array_column($orders, 'order_id');
        $saleNos = array_values(array_filter(array_column($orders, 'relate_source')));
        $sales = ErpSaleOrder::where('site_id', $siteId)->where(function ($q) use ($ids, $saleNos) {
            $q->where(function ($sub) use ($ids) { $sub->where('origin_plugin', 'phone_shop')->whereIn('origin_id', $ids); });
            if ($saleNos !== []) $q->whereOr('sale_no', 'in', $saleNos);
        })->lock($lock)->select()->toArray();
        $saleIds = array_column($sales, 'id');
        $receivables = ErpReceivable::where('site_id', $siteId)->where(function ($q) use ($ids, $saleIds) {
            $q->where(function ($sub) use ($ids) { $sub->where('origin_plugin', 'phone_shop')->whereIn('origin_id', $ids); });
            if ($saleIds !== []) $q->whereOr(function ($sub) use ($saleIds) {
                $sub->whereIn('source_id', $saleIds)->where(function ($kind) {
                    $kind->where('source_type', 'sale')->whereOr('source_type', 'like', 'phone_shop.%');
                });
            });
        })->lock($lock)->select()->toArray();
        $receivableIds = array_column($receivables, 'id');
        $links = $receivableIds === [] ? [] : ErpSettlementLink::where('site_id', $siteId)
            ->where('target_type', 'receivable')->whereIn('target_id', $receivableIds)->select()->toArray();
        $settlementIds = array_values(array_unique(array_column($links, 'settlement_id')));
        $settlements = $settlementIds === [] ? [] : ErpSettlement::where('site_id', $siteId)
            ->whereIn('id', $settlementIds)->order('confirmed_at asc,id asc')->select()->toArray();
        $accountIds = array_values(array_unique(array_filter(array_column($settlements, 'capital_account_id'))));
        $accounts = $accountIds === [] ? [] : ErpCapitalAccount::where('site_id', $siteId)
            ->whereIn('id', $accountIds)->field('id,account_name,account_type')->select()->toArray();
        $returns = $saleIds === [] ? [] : ErpSaleReturnOrder::where('site_id', $siteId)
            ->whereIn('sale_order_id', $saleIds)->where('status', 'confirmed')->select()->toArray();
        $returnIds = array_column($returns, 'id');
        $returnItems = $returnIds === [] ? [] : ErpSaleReturnItem::where('site_id', $siteId)
            ->whereIn('return_id', $returnIds)->select()->toArray();
        $partyIds = array_values(array_unique(array_filter(array_column($sales, 'party_id'))));
        $partyMembers = $partyIds === [] ? [] : ErpPartyMember::where('site_id', $siteId)
            ->whereIn('party_id', $partyIds)->where('status', 1)->select()->toArray();
        $result = [];
        foreach ($orders as $order) {
            $result[(int)$order['order_id']] = $this->summarize($siteId, $order, $sales, $receivables, $links, $settlements, $accounts, $returns, $returnItems, $partyMembers);
        }
        return $result;
    }

    /** 纯聚合供查询与回归测试复用；再次核对站点及来源，不依赖调用方提前过滤。 */
    public function summarize(int $siteId, array $order, array $sales, array $receivables, array $links, array $settlements, array $accounts, array $returns = [], array $returnItems = [], array $partyMembers = []): array
    {
        $orderId = (int)($order['order_id'] ?? 0);
        $orderNo = trim((string)($order['order_no'] ?? ''));
        $relateSource = trim((string)($order['relate_source'] ?? ''));
        $original = self::cents($order['order_money'] ?? 0);
        $state = [
            'provider' => 'hsx_erp', 'site_id' => $siteId, 'available' => true, 'matched' => false, 'can_complete' => false,
            'order_id' => $orderId, 'order_no' => $orderNo, 'original_amount' => self::money($original),
            'amount' => '0.00', 'settled_amount' => '0.00', 'remaining_amount' => self::money($original),
            'cash_received_amount' => '0.00', 'offset_amount' => '0.00', 'returned_unreceived_amount' => '0.00',
            'expected_amount' => self::money($original), 'status' => 'unmatched', 'status_name' => '未关联 ERP 应收',
            'message' => '尚未关联 ERP 应收，请先核对同步结果；未执行完成订单。',
            'payment_methods' => [], 'capital_accounts' => [], 'settlements' => [], 'receivables' => [], 'returns' => [],
            'confirmed_at' => 0, 'snapshot_at' => time(),
        ];
        if ($siteId <= 0 || $orderId <= 0 || (int)($order['site_id'] ?? $siteId) !== $siteId) return $state;
        $saleMap = [];
        $identityConflict = false;
        foreach ($sales as $sale) {
            if ((int)($sale['site_id'] ?? 0) !== $siteId) continue;
            $originMatch = ($sale['origin_plugin'] ?? '') === 'phone_shop' && (string)($sale['origin_id'] ?? '') === (string)$orderId;
            $mirrorMatch = $relateSource !== '' && ($sale['sale_no'] ?? '') === $relateSource;
            if (!$originMatch && !$mirrorMatch) continue;
            if ($originMatch && $orderNo !== '' && !empty($sale['origin_no']) && $sale['origin_no'] !== $orderNo) $identityConflict = true;
            if ($mirrorMatch && ($sale['origin_plugin'] ?? '') === 'phone_shop' && !empty($sale['origin_id']) && (string)$sale['origin_id'] !== (string)$orderId) $identityConflict = true;
            if ($mirrorMatch && !$originMatch) {
                $memberMatched = false;
                foreach ($partyMembers as $relation) {
                    if ((int)($relation['site_id'] ?? 0) === $siteId && (int)($relation['status'] ?? 0) === 1
                        && (int)($relation['party_id'] ?? 0) > 0 && (int)$relation['party_id'] === (int)($sale['party_id'] ?? 0)
                        && (int)($order['member_id'] ?? 0) > 0 && (int)$relation['member_id'] === (int)$order['member_id']) $memberMatched = true;
                }
                if (!$memberMatched) $identityConflict = true;
            }
            $saleMap[(int)$sale['id']] = $sale;
        }
        $targetMap = [];
        $amount = $settled = 0;
        $consistent = self::validMoney($order['order_money'] ?? null);
        foreach ($receivables as $row) {
            if ((int)($row['site_id'] ?? 0) !== $siteId) continue;
            $originMatch = ($row['origin_plugin'] ?? '') === 'phone_shop' && (string)($row['origin_id'] ?? '') === (string)$orderId;
            $type = (string)($row['source_type'] ?? '');
            if ($type !== 'sale' && !str_starts_with($type, 'phone_shop.')) continue;
            $saleMatch = isset($saleMap[(int)($row['source_id'] ?? 0)]) && ($type === 'sale' || str_starts_with($type, 'phone_shop.'));
            if (!$originMatch && !$saleMatch) continue;
            if ($originMatch && $orderNo !== '' && !empty($row['origin_no']) && $row['origin_no'] !== $orderNo) $identityConflict = true;
            if ($saleMatch && ($row['origin_plugin'] ?? '') === 'phone_shop' && !empty($row['origin_id']) && (string)$row['origin_id'] !== (string)$orderId) $identityConflict = true;
            if ($saleMatch && (int)($row['party_id'] ?? 0) !== (int)($saleMap[(int)$row['source_id']]['party_id'] ?? 0)) $identityConflict = true;
            if (($row['status'] ?? '') === 'void') continue;
            $id = (int)($row['id'] ?? 0);
            if ($id <= 0 || isset($targetMap[$id])) continue;
            $rowAmount = self::cents($row['amount'] ?? 0);
            $rowSettled = self::cents($row['settled_amount'] ?? 0);
            if (!self::validMoney($row['amount'] ?? null) || !self::validMoney($row['settled_amount'] ?? null)
                || !in_array((string)($row['status'] ?? ''), ['pending', 'partial', 'settled'], true)) $consistent = false;
            if ($rowSettled > $rowAmount) $consistent = false;
            $targetMap[$id] = ['amount' => $rowAmount, 'settled' => $rowSettled, 'verified' => 0];
            $amount += $rowAmount;
            $settled += $rowSettled;
            $state['receivables'][] = [
                'id' => $id, 'receivable_no' => (string)($row['receivable_no'] ?? ''),
                'amount' => self::money($rowAmount), 'settled_amount' => self::money($rowSettled),
                'remaining_amount' => self::money(max(0, $rowAmount - $rowSettled)), 'status' => (string)($row['status'] ?? ''),
            ];
        }
        // 只有已确认的销售退回明细才可降低应收覆盖总额，作废应收本身不是收款。
        $returnMap = [];
        foreach ($returns as $return) {
            if ((int)($return['site_id'] ?? 0) !== $siteId || ($return['status'] ?? '') !== 'confirmed'
                || !isset($saleMap[(int)($return['sale_order_id'] ?? 0)])) continue;
            if (!in_array((string)($return['business_type'] ?? ''), ['return_refund', 'external_goods_return'], true)) continue;
            $returnMap[(int)$return['id']] = $return;
        }
        $returned = 0;
        $seenReturnItems = [];
        foreach ($returnItems as $item) {
            $id = (int)($item['id'] ?? 0);
            if ((int)($item['site_id'] ?? 0) !== $siteId || !isset($returnMap[(int)($item['return_id'] ?? 0)]) || isset($seenReturnItems[$id])) continue;
            $seenReturnItems[$id] = true;
            if ($id <= 0 || !self::validMoney($item['return_price'] ?? null) || !self::validMoney($item['received_amount'] ?? null)) $consistent = false;
            $deduction = max(0, self::cents($item['return_price'] ?? 0) - self::cents($item['received_amount'] ?? 0));
            $returned += $deduction;
            $state['returns'][] = ['return_no' => (string)$returnMap[(int)$item['return_id']]['return_no'], 'unreceived_offset_amount' => self::money($deduction)];
        }
        $expected = max(0, $original - $returned);
        if ($returned > $original) $consistent = false;
        $accountMap = [];
        foreach ($accounts as $account) if ((int)($account['site_id'] ?? $siteId) === $siteId) $accountMap[(int)$account['id']] = $account;
        $settlementMap = [];
        foreach ($settlements as $row) {
            if ((int)($row['site_id'] ?? 0) !== $siteId || ($row['status'] ?? '') !== 'confirmed') continue;
            $type = (string)($row['settlement_type'] ?? '');
            if (!(($type === 'receipt' && ($row['cash_direction'] ?? '') === 'in') || ($type === 'offset' && ($row['cash_direction'] ?? '') === 'none'))) continue;
            $settlementMap[(int)$row['id']] = $row;
        }
        $applied = [];
        $seenLinks = [];
        foreach ($links as $link) {
            $id = (int)($link['id'] ?? 0);
            $targetId = (int)($link['target_id'] ?? 0);
            $settlementId = (int)($link['settlement_id'] ?? 0);
            if ((int)($link['site_id'] ?? 0) !== $siteId || ($link['target_type'] ?? '') !== 'receivable'
                || !isset($targetMap[$targetId], $settlementMap[$settlementId]) || isset($seenLinks[$id])) continue;
            $seenLinks[$id] = true;
            $value = self::cents($link['applied_amount'] ?? 0);
            if ($id <= 0 || !self::validMoney($link['applied_amount'] ?? null)) $consistent = false;
            $targetMap[$targetId]['verified'] += $value;
            $applied[$settlementId] = ($applied[$settlementId] ?? 0) + $value;
        }
        $cash = $offset = 0;
        $methods = $usedAccounts = [];
        foreach ($applied as $id => $value) {
            $row = $settlementMap[$id];
            if ($value <= 0) continue;
            if (!self::validMoney($row['amount'] ?? null) || $value > self::cents($row['amount'] ?? 0) || (int)($row['confirmed_at'] ?? 0) <= 0) $consistent = false;
            $isOffset = $row['settlement_type'] === 'offset';
            $accountId = (int)($row['capital_account_id'] ?? 0);
            $account = $accountMap[$accountId] ?? [];
            $accountName = (string)($row['capital_account_name'] ?? '');
            if (!$isOffset && ($accountId <= 0 || trim($accountName) === '')) $consistent = false;
            $accountType = $isOffset ? 'offset' : (string)($account['account_type'] ?? 'unknown');
            $methodName = ['cash' => '现金', 'wechat' => '微信', 'alipay' => '支付宝', 'bank' => '银行卡', 'other' => '其他账户', 'offset' => '折账'][$accountType] ?? '账户收款';
            if ($isOffset) $offset += $value;
            else $cash += $value;
            $methods[$accountType] = ['key' => $accountType, 'name' => $methodName];
            if (!$isOffset) $usedAccounts[$accountId . ':' . $accountName] = ['id' => $accountId, 'name' => $accountName, 'type' => $accountType];
            $confirmedAt = (int)($row['confirmed_at'] ?? 0);
            $state['confirmed_at'] = max($state['confirmed_at'], $confirmedAt);
            $state['settlements'][] = [
                'settlement_id' => (int)$id, 'settlement_no' => (string)($row['settlement_no'] ?? ''),
                'settlement_type' => (string)$row['settlement_type'], 'method' => $accountType, 'method_name' => $methodName,
                'capital_account_id' => $isOffset ? 0 : $accountId, 'capital_account_name' => $isOffset ? '' : $accountName,
                'amount' => self::money($value), 'confirmed_at' => $confirmedAt, 'operator_name' => (string)($row['operator_name'] ?? ''),
            ];
        }
        foreach ($targetMap as $target) if ($target['settled'] !== $target['verified']) $consistent = false;
        $remaining = max(0, $amount - $settled);
        $state = array_replace($state, [
            'matched' => $targetMap !== [] || ($returnMap !== [] && $expected === 0),
            'amount' => self::money($amount), 'settled_amount' => self::money($settled), 'remaining_amount' => self::money($remaining),
            'cash_received_amount' => self::money($cash), 'offset_amount' => self::money($offset),
            'returned_unreceived_amount' => self::money($returned), 'expected_amount' => self::money($expected),
            'payment_methods' => array_values($methods), 'capital_accounts' => array_values($usedAccounts),
        ]);
        if ($identityConflict) {
            $state['status'] = 'conflict'; $state['status_name'] = '来源关联需核对';
            $state['message'] = 'ERP 应收的商城订单标识不一致，请财务核对；未执行完成订单。';
        } elseif (!$state['matched']) {
            // 未对应不是已核实的零元账务，不能用0掩盖缺失关联。
            foreach (['amount', 'settled_amount', 'remaining_amount', 'cash_received_amount', 'offset_amount'] as $key) $state[$key] = null;
        } elseif ($amount !== $expected || !$consistent) {
            $state['status'] = 'inconsistent'; $state['status_name'] = '账务需核对';
            $state['message'] = 'ERP 应收金额或确认结算明细与订单不一致，请财务核对；未执行完成订单。';
        } elseif ($remaining > 0) {
            $state['status'] = $settled > 0 ? 'partial' : 'pending'; $state['status_name'] = $settled > 0 ? '部分收款' : '待收款';
            $state['message'] = 'ERP 尚有 ¥' . self::money($remaining) . ' 未结清，请财务收款后再完成订单。';
        } else {
            $state['can_complete'] = true; $state['status'] = 'settled'; $state['status_name'] = '已结清';
            $state['message'] = $offset > 0 ? 'ERP 已结清（含折账），可完成订单。' : 'ERP 已确认收齐，可完成订单。';
        }
        return $state;
    }

    private static function cents($value): int
    {
        return is_numeric($value) ? max(0, (int)round((float)$value * 100)) : 0;
    }

    private static function validMoney($value): bool
    {
        if (!is_numeric($value)) return false;
        $number = (float)$value;
        return is_finite($number) && $number >= 0 && abs($number * 100 - round($number * 100)) <= 0.00001;
    }

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
