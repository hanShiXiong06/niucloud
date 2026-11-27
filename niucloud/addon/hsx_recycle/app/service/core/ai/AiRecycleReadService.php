<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\ai;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\stat\RecycleStageDict;
use addon\hsx_recycle\app\model\RecycleOrder;
use addon\hsx_recycle\app\model\stat\RecycleTaskClaim;
use think\facade\Db;

/** AI 只读查询口径。所有查询强制绑定站点，不返回完整手机号和支付账号。 */
final class AiRecycleReadService
{
    public function workflowSummary(int $siteId, array $arguments, array $actor): array
    {
        $mine = !empty($arguments['mine']);
        $uid = $mine ? (int)($actor['id'] ?? 0) : 0;
        $range = $this->dateRange((string)($arguments['period'] ?? 'today'));

        $pending = [
            'pickup' => $this->orderStageCount($siteId, RecycleStageDict::STAGE_PICKUP, $uid),
            'sign' => $this->orderStageCount($siteId, RecycleStageDict::STAGE_SIGN, $uid),
            'check' => $this->deviceStageCount($siteId, RecycleStageDict::STAGE_CHECK, $uid),
            'price' => $this->deviceStageCount($siteId, RecycleStageDict::STAGE_PRICE, $uid),
            'confirm' => $this->deviceStageCount($siteId, RecycleStageDict::STAGE_CONFIRM, $uid),
            'pay' => $this->deviceStageCount($siteId, RecycleStageDict::STAGE_PAY, $uid),
            'abnormal' => $this->deviceStageCount($siteId, RecycleStageDict::STAGE_ABNORMAL, $uid),
        ];

        $result = [
            'scope' => $mine ? 'mine' : 'site',
            'period' => (string)($arguments['period'] ?? 'today'),
            'range' => $range,
            'pending' => $pending,
            'completed' => [
                'signed_orders' => $mine ? null : $this->rangeCount('recycle_order', $siteId, 'sign_at', $range),
                'checked_devices' => $this->rangeCount('recycle_device', $siteId, 'check_at', $range, $uid, 'check_uid'),
                'priced_devices' => $this->rangeCount('recycle_device', $siteId, 'price_at', $range, $uid, 'price_uid'),
                'paid_devices' => $this->rangeCount('recycle_device', $siteId, 'pay_time', $range, $uid, 'pay_uid'),
            ],
            'notes' => $mine ? ['签收订单未固化实际签收人，个人完成量不猜测；全店口径可查询。'] : [],
            'entry' => [
                'web_path' => 'site/stat/task',
                'miniapp_path' => 'addon/hsx_recycle/pages/task/index',
            ],
            'generated_at' => time(),
        ];
        $periodLabel = ['today' => '今日', 'week' => '本周', 'month' => '本月'][$result['period']] ?? '今日';
        $stageLabels = [
            'pickup' => '待取货', 'sign' => '待签收', 'check' => '待质检', 'price' => '待定价',
            'confirm' => '待客户确认', 'pay' => '待打款', 'abnormal' => '异常处理',
        ];
        $bottleneckStage = '';
        $bottleneckCount = 0;
        foreach ($pending as $stage => $count) {
            if ((int)$count > $bottleneckCount) {
                $bottleneckStage = (string)$stage;
                $bottleneckCount = (int)$count;
            }
        }
        $bottleneckText = $bottleneckCount > 0
            ? sprintf('当前最大堵点是%s %d %s。', $stageLabels[$bottleneckStage] ?? '待处理', $bottleneckCount, in_array($bottleneckStage, ['pickup', 'sign'], true) ? '单' : '台')
            : '当前各环节没有积压。';
        $result['assistant_summary'] = sprintf(
            '%s %s当前待取货 %d 单、待签收 %d 单、待质检 %d 台、待定价 %d 台、待客户确认 %d 台、待打款 %d 台、异常 %d 台；%s已质检 %d 台、已定价 %d 台、已打款 %d 台。',
            $bottleneckText,
            $mine ? '我的任务中' : '全店',
            $pending['pickup'],
            $pending['sign'],
            $pending['check'],
            $pending['price'],
            $pending['confirm'],
            $pending['pay'],
            $pending['abnormal'],
            $periodLabel,
            (int)$result['completed']['checked_devices'],
            (int)$result['completed']['priced_devices'],
            (int)$result['completed']['paid_devices']
        );
        $result['_presentation'] = ['blocks' => [
            ['type' => 'stat_grid', 'source_plugin' => 'hsx_recycle', 'data' => ['items' => [
                ['title' => '待取货', 'value' => $pending['pickup'], 'unit' => '单', 'tone' => 'info'],
                ['title' => '待签收', 'value' => $pending['sign'], 'unit' => '单', 'tone' => 'primary'],
                ['title' => '待质检', 'value' => $pending['check'], 'unit' => '台', 'tone' => 'warning'],
                ['title' => '待定价', 'value' => $pending['price'], 'unit' => '台', 'tone' => 'danger'],
                ['title' => '待客户确认', 'value' => $pending['confirm'], 'unit' => '台', 'tone' => 'info'],
                ['title' => '待打款', 'value' => $pending['pay'], 'unit' => '台', 'tone' => 'success'],
                ['title' => '异常处理', 'value' => $pending['abnormal'], 'unit' => '台', 'tone' => 'danger'],
            ]]],
            $this->routeActionBlock('进入回收任务列表', '/site/stat/task'),
        ]];
        return $result;
    }

    public function searchOrders(int $siteId, array $arguments): array
    {
        $keyword = mb_substr(trim((string)($arguments['keyword'] ?? '')), 0, 100);
        $statusKey = trim((string)($arguments['status'] ?? ''));
        $limit = max(1, min(20, (int)($arguments['limit'] ?? 10)));
        $statusMap = [
            'pending_sign' => RecycleOrderDict::ORDER_STATUS_PENDING_SIGN,
            'checking' => RecycleOrderDict::ORDER_STATUS_CHECKING,
            'checked' => RecycleOrderDict::ORDER_STATUS_CHECKED,
            'pending_confirm' => RecycleOrderDict::ORDER_STATUS_PENDING_CONFIRM,
            'pending_payment' => RecycleOrderDict::ORDER_STATUS_PENDING_PAYMENT,
            'completed' => RecycleOrderDict::ORDER_STATUS_COMPLETED,
            'closed' => RecycleOrderDict::ORDER_STATUS_CLOSED,
            'cancelled' => RecycleOrderDict::ORDER_STATUS_CANCELLED,
        ];

        $query = Db::name('recycle_order')->alias('o')
            ->where('o.site_id', '=', $siteId)
            ->where('o.delete_at', '=', 0);
        if (isset($statusMap[$statusKey])) $query->where('o.status', '=', $statusMap[$statusKey]);
        if ($keyword !== '') {
            $deviceOrderIds = Db::name('recycle_device')->where('site_id', '=', $siteId)
                ->whereLike('imei|sn|model', '%' . $keyword . '%')->limit(200)->column('order_id');
            $orderSearchFields = ['o.order_no', 'o.customer_name', 'o.customer_phone', 'o.express_no'];
            if (in_array('logistics_vehicle_no', $this->orderFields(), true)) $orderSearchFields[] = 'o.logistics_vehicle_no';
            $query->where(function ($scope) use ($keyword, $deviceOrderIds, $orderSearchFields): void {
                $scope->whereLike(implode('|', $orderSearchFields), '%' . $keyword . '%');
                if ($deviceOrderIds !== []) $scope->whereOr('o.id', 'in', array_map('intval', $deviceOrderIds));
            });
        }
        $this->applyCustomRange($query, 'o.create_at', $arguments);
        $rows = $query->field('o.id,o.order_no,o.customer_name,o.customer_phone,o.delivery_type,o.status,o.pay_status,o.total_amount,o.device_count,o.count,o.create_at,o.sign_at,o.update_at')
            ->order('o.id desc')->limit($limit)->select()->toArray();

        $orderIds = array_map('intval', array_column($rows, 'id'));
        $devicesByOrder = [];
        if ($orderIds !== []) {
            $devices = Db::name('recycle_device')->where('site_id', '=', $siteId)->whereIn('order_id', $orderIds)
                ->field('id,order_id,imei,sn,model,capacity,color,status,final_price,pay_status')->order('id asc')->select()->toArray();
            foreach ($devices as $device) {
                $device['status_name'] = (string)RecycleOrderDict::getDeviceStatus((int)$device['status']);
                $devicesByOrder[(int)$device['order_id']][] = $device;
            }
        }

        foreach ($rows as &$row) {
            $row['customer_phone'] = $this->maskMobile((string)$row['customer_phone']);
            $row['status_name'] = (string)(RecycleOrderDict::ORDER_STATUS_TEXT[(int)$row['status']] ?? '未知状态');
            $row['pay_status_name'] = (string)RecycleOrderDict::getPayStatus((int)$row['pay_status']);
            $row['devices'] = array_slice($devicesByOrder[(int)$row['id']] ?? [], 0, 10);
            $row['web_path'] = 'site/recycle_order/list?order_id=' . (int)$row['id'];
            $row['miniapp_path'] = 'addon/hsx_recycle/pages/order/detail?id=' . (int)$row['id'];
        }
        unset($row);
        $result = [
            'total_returned' => count($rows),
            'orders' => $rows,
            'generated_at' => time(),
            '_presentation' => ['blocks' => [
                ['type' => 'table', 'source_plugin' => 'hsx_recycle', 'data' => [
                    'title' => '回收订单',
                    'columns' => [
                        ['prop' => 'order_no', 'label' => '订单号', 'min_width' => 160],
                        ['prop' => 'customer_name', 'label' => '客户', 'min_width' => 120],
                        ['prop' => 'customer_phone', 'label' => '手机号'],
                        ['prop' => 'device_count', 'label' => '设备', 'align' => 'right'],
                        ['prop' => 'total_amount', 'label' => '金额', 'align' => 'right'],
                        ['prop' => 'status_name', 'label' => '状态'],
                        ['prop' => 'pay_status_name', 'label' => '打款状态'],
                    ],
                    'rows' => $rows,
                ]],
                $this->routeActionBlock('进入回收订单列表', '/site/recycle_order/list'),
            ]],
        ];
        $result['assistant_summary'] = count($rows) > 0
            ? sprintf('共查到 %d 个符合条件的回收订单，明细已经列出，可进入回收订单列表继续处理。', count($rows))
            : '当前没有查到符合这些条件的回收订单。';
        return $result;
    }

    private function orderStageCount(int $siteId, string $stage, int $uid): int
    {
        $claimTable = (new RecycleTaskClaim())->getTable();
        $joinedClaim = false;
        $query = Db::name('recycle_order')->alias('o')->where([
            ['o.site_id', '=', $siteId],
            ['o.status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN],
            ['o.delete_at', '=', 0],
        ]);
        if ($stage === RecycleStageDict::STAGE_PICKUP) {
            $fields = $this->orderFields();
            if (in_array('logistics_eta_at', $fields, true) && in_array('logistics_vehicle_no', $fields, true)) {
                $query->where('o.delivery_type', '=', RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE)
                    ->where('o.logistics_eta_at', '>', 0)->where('o.logistics_eta_at', '<=', time());
            } else {
                $query->join($claimTable . ' c', 'c.device_id = o.id AND c.site_id = o.site_id')
                    ->where('c.stage_key', '=', $stage);
                $joinedClaim = true;
            }
        }
        if ($uid > 0) {
            if (!$joinedClaim) {
                $query->join($claimTable . ' c', 'c.device_id = o.id AND c.site_id = o.site_id')
                    ->where('c.stage_key', '=', $stage);
            }
            $query->where('c.assignee_uid', '=', $uid);
        }
        return (int)$query->count();
    }

    private function deviceStageCount(int $siteId, string $stage, int $uid): int
    {
        $statuses = RecycleStageDict::getStageStatuses()[$stage] ?? [];
        if ($statuses === []) return 0;
        $query = Db::name('recycle_device')->alias('d')->where('d.site_id', '=', $siteId)->whereIn('d.status', $statuses);
        if ($stage === RecycleStageDict::STAGE_PAY) $query->where('d.pay_status', '=', RecycleOrderDict::PAY_STATUS_UNPAID);
        if ($uid > 0) {
            $query->join((new RecycleTaskClaim())->getTable() . ' c', 'c.device_id = d.id AND c.site_id = d.site_id')
                ->where('c.stage_key', '=', $stage)->where('c.assignee_uid', '=', $uid);
        }
        return (int)$query->count();
    }

    private function rangeCount(string $table, int $siteId, string $field, array $range, int $uid = 0, string $operatorField = ''): int
    {
        $query = Db::name($table)->where('site_id', '=', $siteId)
            ->where($field, '>=', (int)$range['start_at'])->where($field, '<=', (int)$range['end_at']);
        if ($uid > 0 && $operatorField !== '') $query->where($operatorField, '=', $uid);
        return (int)$query->count();
    }

    private function dateRange(string $period): array
    {
        $end = time();
        $start = match ($period) {
            'week' => strtotime('monday this week 00:00:00'),
            'month' => strtotime(date('Y-m-01 00:00:00')),
            default => strtotime(date('Y-m-d 00:00:00')),
        };
        return ['start_at' => $start, 'end_at' => $end, 'start' => date('Y-m-d H:i:s', $start), 'end' => date('Y-m-d H:i:s', $end)];
    }

    private function applyCustomRange($query, string $field, array $arguments): void
    {
        $start = trim((string)($arguments['start_date'] ?? ''));
        $end = trim((string)($arguments['end_date'] ?? ''));
        if ($start !== '' && strtotime($start . ' 00:00:00') !== false) $query->where($field, '>=', strtotime($start . ' 00:00:00'));
        if ($end !== '' && strtotime($end . ' 23:59:59') !== false) $query->where($field, '<=', strtotime($end . ' 23:59:59'));
    }

    private function maskMobile(string $mobile): string
    {
        return preg_match('/^(.{3}).*(.{4})$/u', $mobile, $matches) ? $matches[1] . '****' . $matches[2] : $mobile;
    }

    private function orderFields(): array
    {
        static $fields = null;
        if ($fields === null) $fields = (new RecycleOrder())->getTableFields();
        return (array)$fields;
    }

    private function routeActionBlock(string $label, string $route, array $params = []): array
    {
        return ['type' => 'action_group', 'source_plugin' => 'hsx_recycle', 'data' => ['actions' => [[
            'id' => 'navigate', 'label' => $label, 'type' => 'route', 'route' => $route,
            'params' => $params, 'tone' => 'primary', 'approved' => true,
        ]]]];
    }

}
