<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\listener;

use addon\hsx_erp\app\service\admin\FinanceSettlementService;
use think\facade\Log;

/**
 * 按来源设备核销应付（事件 SettleErpPayableByDevice 的应答方）。
 *
 * 回收"确认打款"成功后发 event('SettleErpPayableByDevice', ['source_device_ids'=>[...]])，
 * 把这些设备对应的待结应付精确结清（与打款的资金账户扣减配套，形成完整账目往来）。
 * 故障隔离：异常只记日志、返回安全值，绝不影响回收打款主流程。
 */
class SettlePayableByDeviceListener
{
    public function handle($event): array
    {
        $payload = is_array($event) ? $event : (array)$event;
        try {
            $deviceIds = (array)($payload['source_device_ids'] ?? []);
            $result = (new FinanceSettlementService())->settleByDeviceIds($deviceIds, [
                'remark'             => (string)($payload['remark'] ?? '回收打款核销应付'),
                'capital_account_id' => (int)($payload['capital_account_id'] ?? 0),
                'record_cash'        => ($payload['record_cash'] ?? true) !== false,
            ]);
            return ['ok' => true, 'settled' => (int)($result['settled'] ?? 0)];
        } catch (\Throwable $e) {
            Log::warning('[erp_finance] 按设备核销应付失败: ' . $e->getMessage());
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
