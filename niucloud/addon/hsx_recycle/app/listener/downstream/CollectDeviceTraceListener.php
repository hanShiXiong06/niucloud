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
            // 关键：原来这里静默吞异常 → 出错时什么都查不到、也看不到原因。现在记下来。
            \think\facade\Log::error('[CollectDeviceTrace] 处理失败: ' . $e->getMessage()
                . ' @ ' . $e->getFile() . ':' . $e->getLine());
            return [];
        }
    }

    /** 串号(IMEI)追踪：按 IMEI 找回收设备(每条=一次回收生命周期)，再由 device_id 去日志表拉详情 */
    private function search(int $siteId, string $keyword): array
    {
        $imei = trim($keyword);
        \think\facade\Log::info('[CollectDeviceTrace] search imei=' . $imei . ' site_id=' . $siteId);
        if ($imei === '') {
            return [];
        }
        // 串号追踪：只按 IMEI 检索；IMEI 只存在主表 recycle_device(日志表无 imei 列)。
        // 不死卡 site_id：回收设备写库 site_id 常为 0(与 trace() 同坑)，IMEI 本身唯一，按 IMEI 取最稳。
        $devices = RecycleDevice::where('imei', 'like', '%' . $imei . '%')
            ->order('id desc')->limit(50)->select()->toArray();
        \think\facade\Log::info('[CollectDeviceTrace] imei命中设备数=' . count($devices));
        if (empty($devices)) {
            \think\facade\Log::info('[CollectDeviceTrace] 按IMEI未命中(确认该 imei 在 recycle_device 主表存在)');
            return [];
        }
        $orderMap = $this->orderMap($siteId, array_column($devices, 'order_id'));
        $memberNameMap = $this->memberNames($siteId, array_column($orderMap, 'member_id'));
        $rows = [];
        foreach ($devices as $d) {
            $ord = $orderMap[(int)$d['order_id']] ?? [];
            // 提交人(从谁收的): 订单 customer_name 空则按订单 member_id 取会员
            $customer = (string)($ord['customer_name'] ?? '');
            if ($customer === '' && !empty($ord['member_id'])) {
                $customer = $memberNameMap[(int)$ord['member_id']] ?? '';
            }
            // 回收时间: 优先订单提交时间
            $rtime = (int)($ord['create_at'] ?? 0) ?: (int)($ord['create_time'] ?? 0) ?: (int)($d['create_at'] ?? 0);
            $rows[] = [
                'device_id'     => (int)$d['id'],
                'erp_asset_id'  => (int)($d['downstream_erp_asset_id'] ?? 0),
                'imei'          => (string)$d['imei'],
                'sn'            => (string)$d['sn'],
                'model'         => (string)$d['model'],
                'order_id'      => (int)$d['order_id'],
                'order_no'      => (string)($ord['order_no'] ?? ''),
                'recycle_time'  => $rtime,
                'recycle_price' => round((float)($d['final_price'] ?: $d['initial_price'] ?: 0), 2),
                'sale_price'    => round((float)($d['downstream_sale_price'] ?: $d['sell_price'] ?: 0), 2),
                'customer_name' => $customer,
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
        // 注意: 设备/日志数据写库时 site_id 常为0, 按 id 唯一主键取, 不卡 site_id
        // 即使设备主表记录已不在(id对不齐), 也照样按 device_id 拉日志, 不提前return
        $device = RecycleDevice::where([['id', '=', $deviceId]])->findOrEmpty();
        $d = $device->isEmpty() ? [] : $device->toArray();
        $orderId = (int)($d['order_id'] ?? 0);
        if ($orderId === 0) {
            $orderId = (int)(RecycleDeviceLog::where([['device_id', '=', $deviceId]])->order('id asc')->value('order_id') ?: 0);
        }
        $ord = $orderId > 0 ? ($this->orderMap($siteId, [$orderId])[$orderId] ?? []) : [];

        $orderNo = (string)($ord['order_no'] ?? '');
        $events = [];
        // 设备操作日志: 复用官方 getDeviceLogList(只按 device_id 查, 不带 site_id; 写日志时未存 site_id)
        // 自带 status_name(中文) + operator_name(关联系统用户)
        $deviceLogs = [];
        try {
            $deviceLogs = (new RecycleDeviceLog())->getDeviceLogList(['device_id' => $deviceId], 1, 200, 'id asc')['list'] ?? [];
        } catch (\Throwable $e) {
            \think\facade\Log::warning('[trace] getDeviceLogList失败回退裸查: ' . $e->getMessage());
            $deviceLogs = RecycleDeviceLog::where([['device_id', '=', $deviceId]])->order('id asc')->select()->toArray();
        }
        \think\facade\Log::info('[trace] device_id=' . $deviceId . ' order_id=' . $orderId . ' 设备日志=' . count($deviceLogs));
        foreach ($deviceLogs as $lg) {
            $op = (string)($lg['operation_type'] ?? '');
            $ac = (string)($lg['action'] ?? '');
            if (in_array($op, ['device_payment', 'device_offset_settle'], true) || in_array($ac, ['device_payment', 'device_offset_settle'], true)) {
                continue; // 打款/折账由打款记录覆盖
            }
            $events[] = [
                'time'          => (int)($lg['create_at'] ?? 0),
                'stage'         => '回收',
                'title'         => (string)($lg['status_name'] ?? RecycleOrderDict::getDeviceLogOperationName($lg)),
                'detail'        => (string)($lg['remark'] ?? ''),
                'operator_name' => (string)($lg['operator_name'] ?? ''),
                'operator_uid'  => (int)($lg['operator_id'] ?? 0),
                'amount'        => 0,
                'no'            => $orderNo,
                'key'           => true,
            ];
        }
        // 订单操作日志(签收/质检/定价/确认 状态流转, 同样不按 site_id 过滤)
        if ($orderId > 0) {
            foreach (RecycleOrderLog::where([['order_id', '=', $orderId]])->order('id asc')->select()->toArray() as $lg) {
                // 认识的 action 给中文名; 不认识的用"订单·{目标状态}"兜底, 不丢弃
                $title = $this->orderActionName((string)($lg['action'] ?? ''));
                if ($title === '') {
                    $sn = RecycleOrderDict::ORDER_STATUS_TEXT[(int)($lg['new_status'] ?? 0)] ?? '';
                    $title = $sn !== '' ? ('订单·' . $sn) : '';
                    if ($title === '' && (string)($lg['remark'] ?? '') !== '') {
                        $title = '订单操作';
                    }
                    if ($title === '') {
                        continue;
                    }
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
        // 打款/折账记录(时间准确; 按 device_id 取, 不卡 site_id)
        foreach (RecycleDevicePayment::where([['device_id', '=', $deviceId]])->order('id asc')->select()->toArray() as $pay) {
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
                $m = Member::where([['member_id', '=', (int)$ord['member_id']]])->field('nickname,username,mobile')->findOrEmpty();
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
            'recycle_price'  => round((float)(($d['final_price'] ?? 0) ?: ($d['initial_price'] ?? 0) ?: 0), 2),
            'pay_status'     => (int)($d['pay_status'] ?? 0),
            'pay_amount'     => round((float)($d['pay_amount'] ?? 0), 2),
            'recycle_time'   => (int)($ord['create_at'] ?? 0) ?: (int)($ord['create_time'] ?? 0) ?: (int)($d['create_at'] ?? 0),
        ];
        \think\facade\Log::info('[trace] device_id=' . $deviceId . ' 回收段事件合计=' . count($events));
        return ['summary' => $summary, 'events' => $events];
    }

    private function orderMap(int $siteId, array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (empty($orderIds)) {
            return [];
        }
        // 按订单ID(唯一)取, 不卡 site_id(历史数据 site_id 可能为0)
        $rows = RecycleOrder::whereIn('id', $orderIds)
            ->field('id,order_no,customer_name,customer_phone,member_id,create_at,create_time')->select()->toArray();
        return array_column($rows, null, 'id');
    }

    /** 批量按 member_id 取会员名(昵称/用户名/手机) */
    private function memberNames(int $siteId, array $memberIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $memberIds))));
        if (empty($ids)) {
            return [];
        }
        $map = [];
        try {
            foreach (Member::where([['site_id', '=', $siteId]])->whereIn('member_id', $ids)->field('member_id,nickname,username,mobile')->select()->toArray() as $m) {
                $map[(int)$m['member_id']] = (string)($m['nickname'] ?: $m['username'] ?: $m['mobile'] ?: '');
            }
        } catch (\Throwable $e) {
        }
        return $map;
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
