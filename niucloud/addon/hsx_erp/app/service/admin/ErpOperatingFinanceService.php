<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;
use addon\hsx_erp\app\support\ErpIdempotency;
use core\base\BaseAdminService;
use core\exception\CommonException;

/** 经营性收支：房租、水电、办公、工资、服务收入等，不影响任何设备成本。 */
class ErpOperatingFinanceService extends BaseAdminService
{
    public function create(array $data): array
    {
        $categoryKey = trim((string)($data['category_key'] ?? ''));
        $category = (new ErpConfigService())->findFinanceCategory($categoryKey);
        if (!$category || (string)($category['scope'] ?? '') !== 'operating' || (int)($category['affects_asset_cost'] ?? 0) !== 0) {
            throw new CommonException('请选择有效的经营收支类型');
        }
        $amount = round((float)($data['amount'] ?? 0), 2);
        if ($amount <= 0) throw new CommonException('经营收支金额必须大于0');
        $partyId = (int)($data['party_id'] ?? 0);
        $partyName = trim((string)($data['party_name'] ?? ''));
        if ($partyId <= 0 || $partyName === '') throw new CommonException('请选择本笔经营收支的往来主体');
        $settlementMode = (string)($data['settlement_mode'] ?? 'pending');
        if (!in_array($settlementMode, ['pending', 'immediate'], true)) throw new CommonException('结算方式不正确');
        if ($settlementMode === 'immediate' && (int)($data['capital_account_id'] ?? 0) <= 0) {
            throw new CommonException('立即收付必须选择资金账户');
        }
        $requestId = ErpIdempotency::normalize($data['request_id'] ?? '');
        if ($requestId === '') throw new CommonException('请求缺少幂等标识，请刷新后重试');
        $direction = (string)$category['direction'] === 'income' ? 'income' : 'expense';
        $occurredAt = (int)($data['occurred_at'] ?? 0) ?: time();
        $requestHash = hash('sha256', $requestId);
        // 完整稳定行号写入 origin_id；通用财务事实入口只在其能安全落入
        // 旧有符号 INT 关联列时才同步 source_id。
        $lineId = (string)base_convert(substr($requestHash, 0, 12), 16, 10);
        $sourceNo = ($direction === 'income' ? 'OI' : 'OE') . strtoupper(substr($requestHash, 0, 22));
        $result = (new ErpFinanceFactService())->consume([
            'event_name' => ErpFinanceFactService::CONTRACT_NAME,
            'event_version' => ErpFinanceFactService::CONTRACT_VERSION,
            'event_id' => $requestId,
            'site_id' => $this->site_id,
            'source_plugin' => 'hsx_erp',
            'source_plugin_name' => '二手机ERP',
            // 业务来源是稳定的费用类型，不得拿用户填写的“7月份宽带费”等说明冒充来源名称。
            'source_name' => (string)$category['name'],
            'source_type' => 'hsx_erp.operating_' . $direction,
            'order_no' => $sourceNo,
            'line_id' => $lineId,
            'category_key' => $categoryKey,
            'party_id' => $partyId,
            'party_name' => $partyName,
            'asset_id' => 0,
            'amount' => $amount,
            'channel' => ['code' => 'erp_manual', 'name' => 'ERP经营记账'],
            'occurred_at' => $occurredAt,
            'operator' => ['id' => (int)$this->uid, 'name' => (string)$this->username],
            'remark' => trim((string)($data['remark'] ?? '')),
        ]);
        $targetModel = $direction === 'income' ? ErpReceivable::class : ErpPayable::class;
        $persistedSourceNo = (string)$targetModel::where([
            ['site_id', '=', $this->site_id], ['id', '=', (int)$result['target_id']],
        ])->value('source_no');
        if ($persistedSourceNo !== '') $sourceNo = $persistedSourceNo;

        $settlementId = 0;
        if ($settlementMode === 'immediate') {
            $settlementData = [
                'capital_account_id' => (int)$data['capital_account_id'],
                'voucher_urls' => (string)($data['voucher_urls'] ?? ''),
                'remark' => trim((string)($data['remark'] ?? '')) ?: (string)$category['name'],
                'request_id' => ErpIdempotency::child($requestId, 'settlement'),
            ];
            if ($direction === 'income') {
                $settlementId = (new ErpFinanceService())->confirmReceipt((int)$result['target_id'], $amount, $settlementData);
            } else {
                $settlementId = (new ErpFinanceService())->confirmPayableItemsPayment($partyId, [[
                    'payable_id' => (int)$result['target_id'], 'amount' => $amount,
                ]], $settlementData);
            }
        }
        return array_merge($result, [
            'source_no' => $sourceNo,
            'category_key' => $categoryKey,
            'category_name' => (string)$category['name'],
            'settlement_mode' => $settlementMode,
            'settlement_id' => $settlementId,
        ]);
    }

    public function lists(array $where): array
    {
        $direction = (string)($where['direction'] ?? '');
        $rows = [];
        if ($direction !== 'income') {
            foreach ($this->queryRows(ErpPayable::class, 'expense', $where) as $row) $rows[] = $row;
        }
        if ($direction !== 'expense') {
            foreach ($this->queryRows(ErpReceivable::class, 'income', $where) as $row) $rows[] = $row;
        }
        usort($rows, static fn(array $a, array $b): int => ((int)$b['occurred_at'] <=> (int)$a['occurred_at']) ?: ((int)$b['id'] <=> (int)$a['id']));
        $summary = [
            'income' => round(array_sum(array_map(static fn(array $row): float => $row['direction'] === 'income' ? (float)$row['amount'] : 0, $rows)), 2),
            'expense' => round(array_sum(array_map(static fn(array $row): float => $row['direction'] === 'expense' ? (float)$row['amount'] : 0, $rows)), 2),
            'unsettled' => round(array_sum(array_map(static fn(array $row): float => (float)$row['remain_amount'], $rows)), 2),
        ];
        $page = max(1, (int)($where['page'] ?? 1));
        $limit = max(1, min(100, (int)($where['limit'] ?? 15)));
        return [
            'data' => array_slice($rows, ($page - 1) * $limit, $limit),
            'total' => count($rows), 'current_page' => $page, 'per_page' => $limit,
            'summary' => $summary,
        ];
    }

    private function queryRows(string $model, string $direction, array $where): array
    {
        $query = $model::where([['site_id', '=', $this->site_id], ['source_type', '=', 'hsx_erp.operating_' . $direction]]);
        if (!empty($where['status'])) $query->where('status', '=', (string)$where['status']);
        if (!empty($where['category_key'])) $query->where('category_key', '=', (string)$where['category_key']);
        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->whereLike('source_no|party_name|category_name|business_reason|remark', '%' . $keyword . '%');
        }
        if (!empty($where['start_at'])) $query->where('occurred_at', '>=', (int)$where['start_at']);
        if (!empty($where['end_at'])) $query->where('occurred_at', '<=', (int)$where['end_at']);
        $rows = $query->order('occurred_at desc,id desc')->limit(1000)->select()->toArray();
        foreach ($rows as &$row) {
            $row['direction'] = $direction;
            $row['finance_no'] = (string)($row[$direction === 'income' ? 'receivable_no' : 'payable_no'] ?? '');
            $row['remain_amount'] = max(0, round((float)$row['amount'] - (float)$row['settled_amount'], 2));
        }
        unset($row);
        return $rows;
    }
}
