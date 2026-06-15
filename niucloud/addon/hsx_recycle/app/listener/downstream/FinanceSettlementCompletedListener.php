<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\service\admin\order\RecycleDevicePaymentService;
use think\facade\Log;

/**
 * 监听 ERP 财务中心"结算完成(折账)"事件。
 * 通道: FinanceSettlementCompleted
 *
 * 当财务中心把回收产生的应付通过折账冲抵结清后, 回写对应回收设备的打款状态为"已折账结清",
 * 并把折账结算单号写入备注, 形成闭环、便于查账。
 * 解耦: 异常只记日志, 绝不回抛影响财务结算主流程。已打款设备会被跳过(幂等)。
 */
class FinanceSettlementCompletedListener
{
    public function handle($event): bool
    {
        try {
            $payload = is_array($event) ? $event : (array)$event;
            $settlementNo = (string)($payload['settlement_no'] ?? $payload['settlement_id'] ?? '');
            $deviceIds = [];
            foreach ((array)($payload['linked'] ?? []) as $l) {
                if (($l['type'] ?? '') !== 'payable') {
                    continue;
                }
                if (!in_array((string)($l['source_type'] ?? ''), ['recycle_device', 'recycle_order'], true)) {
                    continue;
                }
                $did = (int)($l['source_device_id'] ?? 0);
                if ($did > 0) {
                    $deviceIds[] = $did;
                }
            }
            // 诊断日志: 确认监听已触发 + 解析到的设备
            Log::info('[hsx_recycle] 收到折账完成事件 单号=' . $settlementNo
                . ' linked=' . json_encode($payload['linked'] ?? [], JSON_UNESCAPED_UNICODE)
                . ' 匹配设备=' . json_encode($deviceIds));
            if (empty($deviceIds)) {
                return false;
            }
            $marked = (new RecycleDevicePaymentService())->settleByOffset($deviceIds, $settlementNo, [
                'operator' => (string)($payload['operator'] ?? '财务折账'),
            ]);
            Log::info('[hsx_recycle] 折账回写完成 单号=' . $settlementNo . ' 实改设备数=' . $marked);
            return $marked > 0;
        } catch (\Throwable $e) {
            Log::error('[hsx_recycle] 折账回写打款状态失败: ' . $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine());
            return false;
        }
    }
}
