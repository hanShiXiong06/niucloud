<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\FinanceSettlementService;
use think\facade\Log;

/**
 * 折账结算指令 — 回收"打款"确认折账时触发。
 * 事件通道: RequestFinanceSettlement
 * payload: ['counterparty_id'=>int, 'remark'=>string]
 * 行为: 把该往来单位全部未结应付/应收折账冲抵, 余额走现金净额, 经唯一结算服务执行(避免重复记账)。
 * 返回: ['ok'=>bool, 'summary'=>..., 'message'=>...]
 * 运行于回收的 admin 请求上下文, 站点/操作人取当前请求; 故障不回抛, 由回收据 ok 决定提示。
 */
class FinanceSettlementRequestListener
{
    public function handle($event)
    {
        $payload = is_array($event) ? $event : (array)$event;
        $cpId = (int)($payload['counterparty_id'] ?? 0);
        if ($cpId <= 0) {
            return ['ok' => false, 'message' => '缺少往来单位'];
        }
        try {
            $result = (new FinanceSettlementService())->settleAllByCounterparty($cpId, [
                'remark' => (string)($payload['remark'] ?? '回收打款折账'),
            ]);
            return ['ok' => true, 'summary' => $result['summary'], 'settlement_no' => $result['settlement_no']];
        } catch (\Throwable $e) {
            Log::warning('[erp_finance] 折账结算指令失败: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
