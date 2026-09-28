<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\order\RecycleOrderLog;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 管理端刷新、客户确认/拒绝共用。仅写父订单进度，不触碰付款、ERP、设备和退货单。
 * 显式传站点，避免客户 API / ERP 回调 / 管理端使用不同的请求身份。
 */
class RecycleOrderProgressService
{
    public function sync(int $siteId, int $orderId, int $operatorId = 0): array
    {
        if ($siteId <= 0 || $orderId <= 0) throw new CommonException('订单参数不完整');
        return Db::transaction(function () use ($siteId, $orderId, $operatorId): array {
            $order = RecycleOrder::where([
                ['site_id', '=', $siteId], ['id', '=', $orderId], ['delete_at', '=', 0],
            ])->lock(true)->findOrEmpty();
            if ($order->isEmpty()) throw new CommonException('订单不存在或已删除');
            $before = $order->toArray();
            // 当前读：与 ERP 付款的“先锁订单、再锁设备”顺序一致，避免读到旧事务快照。
            $devices = RecycleDevice::where([
                ['site_id', '=', $siteId], ['order_id', '=', $orderId],
            ])->field('id,status,confirm_status,pay_status,final_price,initial_price,pay_amount,dispose_type')
                ->lock(true)->select()->toArray();
            $summary = RecycleOrderProgressPolicy::summarize($devices);
            $previous = (int)$before['status'];
            $status = RecycleOrderProgressPolicy::nextStatus($before, $summary);
            $changed = $status !== $previous;
            if ($changed) {
                $now = time();
                $update = ['status' => $status, 'update_at' => $now];
                if ($status === RecycleOrderDict::ORDER_STATUS_COMPLETED && empty($before['complete_at'])) {
                    $update['complete_at'] = $now;
                }
                $order->save($update);
                RecycleOrderLog::create([
                    'site_id' => $siteId, 'order_id' => $orderId,
                    'operator_id' => $operatorId, 'operator_name' => $operatorId > 0 ? '管理员' : '系统',
                    'old_status' => $previous, 'new_status' => $status,
                    'remark' => sprintf('按设备核对订单进度：共%d台，已打款%d台，拒绝/退回%d台，转代卖%d台；未执行付款或退货签收',
                        $summary['total'], $summary['paid'], $summary['returned'], $summary['consigned']),
                    'create_at' => $now,
                ]);
            }
            $statusName = RecycleOrderDict::ORDER_STATUS_TEXT[$status] ?? '未知状态';
            $message = ($changed ? '已更新为' : '已核对，当前为') . '“' . $statusName . '”';
            if ($status !== RecycleOrderDict::ORDER_STATUS_COMPLETED) {
                $pending = [];
                foreach (['pending_check' => '待质检', 'checking' => '质检中', 'pending_confirm' => '待确认', 'pending_pay' => '待打款'] as $key => $label) {
                    if ($summary[$key] > 0) $pending[] = $label . $summary[$key] . '台';
                }
                if ($pending) $message .= '：' . implode('、', $pending);
                elseif (!$summary['total']) $message .= '，尚无设备，未自动完成';
            }
            if ($summary['returned'] > 0) $message .= '。拒绝/退回设备的交还进度请在退货单继续处理';
            return [
                'order_id' => $orderId, 'previous_status' => $previous, 'status' => $status,
                'status_name' => $statusName, 'changed' => $changed,
                'flow_summary' => $summary, 'message' => $message,
            ];
        });
    }
}
