<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\model\ErpSettlement;
use core\exception\CommonException;

/** 外部插件发起实际收付款的唯一公共边界。 */
class ErpFinanceSettlementRequestService extends ErpExternalContractService
{
    public const EVENT_NAME = 'ErpFinanceSettlementRequested';
    public const CONTRACT_NAME = 'erp.finance.settlement_requested.v1';
    public const CONTRACT_VERSION = 1;

    public function consume(array $event): array
    {
        $payload = $this->normalizePayload($event);
        // 结算服务自身以 request_id 保证幂等并管理事务/出站事件，这里不能再套外层事务。
        return $this->consumeOnce($payload, self::EVENT_NAME, function (array $request): array {
            return $this->settle($request);
        }, false);
    }

    protected function normalizePayload(array $event): array
    {
        $targetType = trim((string)($event['target_type'] ?? ''));
        if (!in_array($targetType, ['receivable', 'payable'], true)) {
            throw new CommonException('实际收付款目标类型必须是receivable或payable');
        }
        $amount = round((float)($event['amount'] ?? 0), 2);
        $targetId = max(0, (int)($event['target_id'] ?? 0));
        $accountId = max(0, (int)($event['capital_account_id'] ?? 0));
        if ($targetId <= 0) throw new CommonException('实际收付款请求缺少target_id');
        if ($amount <= 0) throw new CommonException('实际收付款金额必须大于0');
        if ($accountId <= 0) throw new CommonException('实际收付款必须选择ERP资金账户');

        return array_merge(
            $this->normalizeEnvelope($event, self::CONTRACT_NAME, self::CONTRACT_VERSION),
            [
                'event_name' => self::CONTRACT_NAME,
                'event_version' => self::CONTRACT_VERSION,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'amount' => number_format($amount, 2, '.', ''),
                'capital_account_id' => $accountId,
                'voucher_urls' => $this->normalizeVoucherUrls($event['voucher_urls'] ?? []),
                'remark' => mb_substr(trim((string)($event['remark'] ?? '')), 0, 255),
            ]
        );
    }

    protected function settle(array $payload): array
    {
        $target = $this->findTarget((int)$payload['site_id'], (string)$payload['target_type'], (int)$payload['target_id']);
        $this->assertTargetCanSettle($target, $payload);
        $amount = round((float)$payload['amount'], 2);
        $data = [
            'request_id' => (string)$payload['event_id'],
            'capital_account_id' => (int)$payload['capital_account_id'],
            'voucher_urls' => (array)$payload['voucher_urls'],
            'remark' => (string)$payload['remark'],
        ];
        $finance = new ErpFinanceService();
        if ((string)$payload['target_type'] === 'receivable') {
            $settlementId = $finance->confirmReceipt((int)$payload['target_id'], $amount, $data);
        } else {
            $settlementId = $finance->confirmPayableItemsPayment(
                (int)$target['party_id'],
                [['payable_id' => (int)$payload['target_id'], 'amount' => $amount]],
                $data
            );
        }

        $settlement = ErpSettlement::where([
            ['site_id', '=', (int)$payload['site_id']],
            ['id', '=', $settlementId],
        ])->field('id,settlement_no,settlement_type,amount,cash_direction,capital_account_id,capital_account_name,status,confirmed_at')->findOrEmpty();
        if ($settlement->isEmpty()) throw new CommonException('ERP结算已执行但未找到结算记录');
        $updatedTarget = $this->findTarget((int)$payload['site_id'], (string)$payload['target_type'], (int)$payload['target_id']);
        $remain = max(0, round((float)$updatedTarget['amount'] - (float)$updatedTarget['settled_amount'], 2));

        return [
            'target_type' => (string)$payload['target_type'],
            'target_id' => (int)$payload['target_id'],
            'target_no' => (string)$updatedTarget['target_no'],
            'target_status' => (string)$updatedTarget['status'],
            'settled_amount' => number_format((float)$updatedTarget['settled_amount'], 2, '.', ''),
            'remain_amount' => number_format($remain, 2, '.', ''),
            'settlement_id' => (int)$settlement->id,
            'settlement_no' => (string)$settlement->settlement_no,
            'settlement_type' => (string)$settlement->settlement_type,
            'capital_account_id' => (int)$settlement->capital_account_id,
            'capital_account_name' => (string)$settlement->capital_account_name,
            'confirmed_at' => (int)$settlement->confirmed_at,
        ];
    }

    protected function findTarget(int $siteId, string $targetType, int $targetId): array
    {
        $model = $targetType === 'receivable' ? ErpReceivable::class : ErpPayable::class;
        $numberField = $targetType === 'receivable' ? 'receivable_no' : 'payable_no';
        $row = $model::where([
            ['site_id', '=', $siteId],
            ['id', '=', $targetId],
        ])->field('id,party_id,party_name,amount,settled_amount,status,origin_plugin,' . $numberField)
            ->findOrEmpty();
        if ($row->isEmpty()) throw new CommonException('应收应付目标不存在或不属于当前站点');
        $data = $row->toArray();
        $data['target_no'] = (string)($data[$numberField] ?? '');
        return $data;
    }

    protected function assertTargetCanSettle(array $target, array $payload): void
    {
        if (!in_array((string)($target['status'] ?? ''), ['pending', 'partial'], true)) {
            throw new CommonException('只能结算待处理或部分结算的应收应付');
        }
        if ((string)($target['origin_plugin'] ?? '') !== (string)$payload['source_plugin']) {
            throw new CommonException('外部插件只能结算由自身产生的应收应付');
        }
        $remain = round((float)$target['amount'] - (float)$target['settled_amount'], 2);
        if ((float)$payload['amount'] > $remain + 0.0001) {
            throw new CommonException('实际收付款金额不能大于剩余应结金额');
        }
    }

    private function normalizeVoucherUrls($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $value)));
        }
        if (!is_array($value)) return [];
        $result = [];
        foreach ($value as $url) {
            $url = mb_substr(trim((string)$url), 0, 500);
            if ($url !== '' && !in_array($url, $result, true)) $result[] = $url;
            if (count($result) >= 9) break;
        }
        return $result;
    }
}
