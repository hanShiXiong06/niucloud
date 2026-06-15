<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\downstream;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleDevicePayment;
use addon\hsx_recycle\app\model\order\RecycleOrder;

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
        $ord = $this->orderMap($siteId, [(int)$d['order_id']])[(int)$d['order_id']] ?? [];

        $events = [];
        // 设备操作日志
        $logs = RecycleDeviceLog::where([['site_id', '=', $siteId], ['device_id', '=', $deviceId]])->order('id asc')->select()->toArray();
        foreach ($logs as $lg) {
            $events[] = [
                'time'          => (int)$lg['create_at'],
                'stage'         => '回收',
                'title'         => $this->logTitle((string)$lg['action'], (string)$lg['operation_type']),
                'detail'        => (string)$lg['remark'],
                'operator_name' => (string)$lg['operator_name'],
                'operator_uid'  => (int)$lg['operator_id'],
                'amount'        => 0,
                'no'            => (string)($ord['order_no'] ?? ''),
                'key'           => $this->isKeyAction((string)$lg['action']),
            ];
        }
        // 打款/折账记录
        $pays = RecycleDevicePayment::where([['site_id', '=', $siteId], ['device_id', '=', $deviceId]])->order('id asc')->select()->toArray();
        foreach ($pays as $pay) {
            $isOffset = (string)$pay['pay_type'] === '折账';
            $events[] = [
                'time'          => (int)$pay['pay_time'],
                'stage'         => '回收',
                'title'         => $isOffset ? '折账结清' : '打款',
                'detail'        => ($isOffset ? '折账核销 ' : '打款方式:' . $pay['pay_type'] . ' ') . ($pay['pay_remark'] ?? ''),
                'operator_name' => (string)($pay['pay_name'] ?? ''),
                'operator_uid'  => (int)($pay['pay_uid'] ?? 0),
                'amount'        => round((float)$pay['amount'], 2),
                'no'            => (string)$pay['pay_no'],
                'key'           => true,
            ];
        }

        $summary = [
            'order_no'       => (string)($ord['order_no'] ?? ''),
            'customer_name'  => (string)($ord['customer_name'] ?? ''),
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

    private function logTitle(string $action, string $opType): string
    {
        $map = [
            'device_payment'        => '打款',
            'device_offset_settle'  => '折账结清',
            'device_check'          => '质检',
            'device_price'          => '定价',
            'device_confirm'        => '定价确认',
            'device_return'         => '退回',
            'device_recycle'        => '回收入库',
        ];
        return $map[$action] ?? ($action !== '' ? $action : ($opType !== '' ? $opType : '操作'));
    }

    private function isKeyAction(string $action): bool
    {
        return in_array($action, ['device_payment', 'device_offset_settle', 'device_confirm', 'device_price', 'device_recycle', 'device_return'], true);
    }
}
