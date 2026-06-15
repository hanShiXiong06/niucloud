<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleDevicePayment;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\order\RecycleOrderLog;
use app\model\member\Member;

/**
 * 设备全链路追溯 — 回收段数据采集(应答 ERP 的 CollectRecycleDeviceTrace 事件)。
 *
 * mode=search: 按关键词(IMEI/SN/回收单号)返回匹配的回收设备列表(每条=一次回收生命周期)。
 * mode=trace : 按回收设备ID返回该设备的回收段时间线 + 回收概要(客户/回收价/打款折账)。
 * 解耦: 异常只吞并返回空, 不影响 ERP 主流程。
 */
class CollectDeviceTraceListener
{
    public function handle($event): array
    {
        try {
            $p = is_array($event) ? $event : (array)$event;
            $siteId = (int)($p['site_id'] ?? 0);
            $mode = (string)($p['mode'] ?? 'search');
            if ($mode === 'trace') {
                return $this->trace($siteId, (int)($p['device_id'] ?? 0));
            }
            return ['list' => $this->search($siteId, (string)($p['keyword'] ?? ''))];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** 按关键词搜回收设备(生命周期列表) */
    private function search(int $siteId, string $keyword): array
    {
        $keyword = trim($keyword);
        if ($siteId <= 0 || $keyword === '') {
            return [];
        }
        $query = RecycleDevice::where([['site_id', '=', $siteId]]);
        // IMEI/SN 直配; 回收单号则先查订单再取其设备
        $orderIds = RecycleOrder::where([['site_id', '=', $siteId]])->whereLike('order_no', '%' . $keyword . '%')->column('id');
        $query->where(function ($q) use ($keyword, $orderIds) {
            $q->whereLike('imei', '%' . $keyword . '%')->whereOr('sn', 'like', '%' . $keyword . '%');
            if (!empty($orderIds)) {
                $q->whereOr('order_id', 'in', $orderIds);
            }
        });
        $devices = $query->order('id desc')->limit(50)->select()->toArray();
        if (empty($devices)) {
            return [];
        }
        $orderMap = $this->orderMap($siteId, array_column($devices, 'order_id'));
        $rows = [];
        foreach ($devices as $d) {
            $ord = $orderMap[(int)$d['order_id']] ?? [];
            $rows[] = [
                'device_id'     => (int)$d['id'],
                'erp_asset_id'  => (int)($d['downstream_erp_asset_id'] ?? 0),
                'imei'          => (string)$d['imei'],
                'sn'            => (string)$d['sn'],
                'model'         => (string)$d['model'],
                'order_id'      => (int)$d['order_id'],
                'order_no'      => (string)($ord['order_no'] ?? ''),
                'recycle_time'  => (int)$d['create_at'],
                'recycle_price' => round((float)($d['final_price'] ?: $d['initial_price'] ?: 0), 2),
                'sale_price'    => round((float)($d['downstream_sale_price'] ?: $d['sell_price'] ?: 0), 2),
                'customer_name' => (string)($ord['customer_name'] ?? ''),
                'downstream_stage' => (int)($d['downstream_stage'] ?? 0),
                'recycle_status_text' => RecycleOrderDict::DEVICE_STATUS_TEXT[(int)$d['status']] ?? (string)$d['status'],
            ];
        }
        return $rows;
    }

    /** 某回收设备的回收段时间线 + 概要 */
    private function trace(int $siteId, int $deviceId): array
    {
        if ($siteId <= 0 || $deviceId <= 0) {
            return ['summary' => [], 'events' => []];
        }
        $device = RecycleDevice::where([['site_id', '=', $siteId], ['id', '=', $deviceId]])->findOrEmpty();
        if ($device->isEmpty()) {
            return ['summary' => [], 'events' => []];
        }
        $d = $device->toArray();
        $orderId = (int)$d['order_id'];
        $ord = $this->orderMap($siteId, [$orderId])[$orderId] ?? [];

        $orderNo = (string)($ord['order_no'] ?? '');
        $events = [];
        // 设备操作日志(跳过打款/折账, 由打款记录覆盖, 避免重复+脏时间)
        foreach (RecycleDeviceLog::where([['site_id', '=', $siteId], ['device_id', '=', $deviceId]])->order('id asc')->select()->toArray() as $lg) {
            $op = (string)($lg['operation_type'] ?? '');
            $ac = (string)($lg['action'] ?? '');
            if (in_array($op, ['device_payment', 'device_offset_settle'], true) || in_array($ac, ['device_payment', 'device_offset_settle'], true)) {
                continue;
            }
            $events[] = [
                'time'          => (int)$lg['create_at'],
                'stage'         => '回收',
                'title'         => RecycleOrderDict::getDeviceLogOperationName($lg),
                'detail'        => (string)$lg['remark'],
                'operator_name' => (string)$lg['operator_name'],
                'operator_uid'  => (int)$lg['operator_id'],
                'amount'        => 0,
                'no'            => $orderNo,
                'key'           => true,
            ];
        }
        // 订单操作日志(签收/质检/定价/确认 多记在订单层)
        if ($orderId > 0) {
            foreach (RecycleOrderLog::where([['site_id', '=', $siteId], ['order_id', '=', $orderId]])->order('id asc')->select()->toArray() as $lg) {
                $title = $this->orderActionName((string)($lg['action'] ?? ''));
                if ($title === '') {
                    continue;
                }
                $events[] = [
                    'time'          => (int)$lg['create_at'],
                    'stage'         => '回收',
                    'title'         => $title,
                    'detail'        => (string)$lg['remark'],
                    'operator_name' => (string)$lg['operator_name'],
                    'operator_uid'  => (int)$lg['operator_id'],
                    'amount'        => 0,
                    'no'            => $orderNo,
                    'key'           => true,
                ];
            }
        }
        // 打款/折账记录(时间准确)
        foreach (RecycleDevicePayment::where([['site_id', '=', $siteId], ['device_id', '=', $deviceId]])->order('id asc')->select()->toArray() as $pay) {
            $isOffset = (string)$pay['pay_type'] === '折账';
            $events[] = [
                'time'          => (int)$pay['pay_time'],
                'stage'         => '回收',
                'title'         => $isOffset ? '折账结清(回收应付)' : '打款',
                'detail'        => ($isOffset ? '折账核销 ' : '打款方式:' . $pay['pay_type'] . ' ') . ($pay['pay_remark'] ?? ''),
                'operator_name' => (string)($pay['pay_name'] ?? ''),
                'operator_uid'  => (int)($pay['pay_uid'] ?? 0),
                'amount'        => round((float)$pay['amount'], 2),
                'no'            => (string)$pay['pay_no'],
                'key'           => true,
            ];
        }

        // 回收客户(从谁收的): 订单 customer_name 空则取会员
        $customer = (string)($ord['customer_name'] ?? '');
        if ($customer === '' && !empty($ord['member_id'])) {
            try {
                $m = Member::where([['site_id', '=', $siteId], ['member_id', '=', (int)$ord['member_id']]])->field('nickname,username,mobile')->findOrEmpty();
                if (!$m->isEmpty()) {
                    $customer = (string)($m->nickname ?: $m->username ?: $m->mobile ?: '');
                }
            } catch (\Throwable $e) {
            }
        }
        $summary = [
            'order_no'       => $orderNo,
            'customer_name'  => $customer,
            'customer_phone' => (string)($ord['customer_phone'] ?? ''),
            'recycle_price'  => round((float)($d['final_price'] ?: $d['initial_price'] ?: 0), 2),
            'pay_status'     => (int)$d['pay_status'],
            'pay_amount'     => round((float)($d['pay_amount'] ?? 0), 2),
            'recycle_time'   => (int)$d['create_at'],
        ];
        return ['summary' => $summary, 'events' => $events];
    }

    private function orderMap(int $siteId, array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (empty($orderIds)) {
            return [];
        }
        $rows = RecycleOrder::where([['site_id', '=', $siteId]])->whereIn('id', $orderIds)
            ->field('id,order_no,customer_name,customer_phone,member_id')->select()->toArray();
        return array_column($rows, null, 'id');
    }

    /** 订单层日志 action → 中文(空=不展示, 避免噪音) */
    private function orderActionName(string $action): string
    {
        $map = [
            'sign'             => '订单签收',
            'signed'           => '订单签收',
            'start_check'      => '开始质检',
            'complete_check'   => '质检完成',
            'check'            => '质检',
            'price'            => '定价',
            'confirm_price'    => '价格确认',
            'confirm'          => '用户确认',
            'confirm_payment'  => '确认打款',
            'payment'          => '打款',
            'order_cancel_auto_return' => '取消·自动退回',
            'return'           => '退回',
        ];
        return $map[$action] ?? '';
    }
}
