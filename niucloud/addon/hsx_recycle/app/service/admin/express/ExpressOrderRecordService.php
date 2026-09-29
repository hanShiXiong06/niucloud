<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\express;

use addon\hsx_recycle\app\dict\express\ExpressOrderDict;
use addon\hsx_recycle\app\model\express\ExpressOrderRecord;
use core\base\BaseAdminService;

/**
 * 快递订单记录服务类
 * Class ExpressOrderRecordService
 * @package addon\hsx_recycle\app\service\admin\express
 */
class ExpressOrderRecordService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ExpressOrderRecord();
    }

    /**
     * 获取快递订单记录分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = []): array
    {
        $field = 'id, site_id, order_no, third_order_no, recycle_order_id, recycle_device_id, provider_name, product_code, product_name, delivery_id,
                  sender_name, sender_mobile, sender_province, sender_city, sender_district, sender_address,
                  receiver_name, receiver_mobile, receiver_province, receiver_city, receiver_district, receiver_address,
                  goods_name, package_count, estimated_weight, actual_weight, weight_diff,
                  estimated_cost, actual_cost, cost_diff, payment_status, order_status,
                  pickup_time, delivery_time, create_at, update_at, api_response';

        $order = 'create_at desc';
        $where['site_id'] = $this->site_id;

        $searchModel = $this->model->withSearch(['site_id', 'keyword', 'order_no', 'third_order_no', 'delivery_id', 'order_status',
                                                  'recycle_order_id', 'recycle_device_id', 'provider_name',
                                                  'product_code', 'sender_mobile', 'receiver_mobile', 'create_time'], $where)
            ->field($field)
            ->order($order)
            ->append(['status_text', 'payment_status_text']);

        $list = $this->pageQuery($searchModel);
        foreach ($list['data'] as &$row) {
            $raw = is_array($row['api_response'] ?? null) ? $row['api_response'] : [];
            $row['pickup'] = array_intersect_key($raw, array_flip(['provider', 'booking_state', 'carrier_name', 'pickup_time',
                'courier_name', 'courier_phone', 'courier_mobile', 'failure_reason', 'conflict', 'conflict_state', 'reported_freight', 'fee_verification_state', 'manual_review']));
            $row['can_resolve_unbooked'] = $this->canResolveUnbooked($row, $raw);
            unset($row['api_response']);
        }
        unset($row);

        return $list;
    }

    /**
     * 获取快递订单记录详情
     * @param int $id
     * @return array
     */
    public function getInfo(int $id): array
    {
        $field = '*';

        $info = $this->model->field($field)->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->findOrEmpty()->toArray();

        if (!empty($info)) {
            $info['can_resolve_unbooked'] = $this->canResolveUnbooked($info, (array)($info['api_response'] ?? []));
            if (is_array($info['api_response'] ?? null)) {
                unset($info['api_response']['callback_salt']);
            }
            $info['status_text'] = ExpressOrderDict::getStatusText($info['order_status']);
            $info['payment_status_text'] = ExpressOrderDict::getPaymentStatusText($info['payment_status']);
        }

        return $info;
    }

    /**
     * 添加快递订单记录
     * @param array $data
     * @return mixed
     */
    public function add(array $data)
    {
        $data['site_id'] = $this->site_id;
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 编辑快递订单记录
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function edit(int $id, array $data): bool
    {
        $record = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        $this->assertNotManagedPickup($record);
        $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->update($data);
        return true;
    }

    /**
     * 删除快递订单记录
     * @param int $id
     * @return bool
     */
    public function del(int $id): bool
    {
        $model = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        $this->assertNotManagedPickup($model);
        if (!$model) return false;
        $res = $model->delete();
        return $res;
    }

    /**
     * 获取重量差异列表
     * @param float $threshold 差异阈值（kg）
     * @return array
     */
    public function getWeightDiffList(float $threshold = 0.5): array
    {
        return array_map([$this, 'safeRecord'], ExpressOrderRecord::getWeightDiffOrders($this->site_id, $threshold));
    }

    /**
     * 获取费用差异列表
     * @param float $threshold 差异阈值（元）
     * @return array
     */
    public function getCostDiffList(float $threshold = 5.0): array
    {
        return array_map([$this, 'safeRecord'], ExpressOrderRecord::getCostDiffOrders($this->site_id, $threshold));
    }

    /**
     * 获取快递费用统计
     * @param int $startTime
     * @param int $endTime
     * @return array
     */
    public function getStatistics(int $startTime = 0, int $endTime = 0): array
    {
        if (!$startTime) {
            $startTime = strtotime(date('Y-m-01')); // 本月第一天
        }
        if (!$endTime) {
            $endTime = time();
        }

        return ExpressOrderRecord::getStatistics($this->site_id, $startTime, $endTime);
    }

    /**
     * 根据回收订单ID获取快递记录
     * @param int $recycleOrderId
     * @return array
     */
    public function getByRecycleOrderId(int $recycleOrderId): array
    {
        $records = $this->model->where('site_id', $this->site_id)->where('recycle_order_id', $recycleOrderId)->order('id desc')->select()->toArray();

        // 添加状态文本
        foreach ($records as &$record) {
            if (is_array($record['api_response'] ?? null)) unset($record['api_response']['callback_salt']);
            $record['status_text'] = ExpressOrderDict::getStatusText($record['order_status']);
            $record['payment_status_text'] = ExpressOrderDict::getPaymentStatusText($record['payment_status']);
        }

        return $records;
    }

    /**
     * 更新订单状态
     * @param int $id
     * @param string $status
     * @param string $remark
     * @return bool
     */
    public function updateStatus(int $id, string $status, string $remark = ''): bool
    {
        $record = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (!$record) {
            return false;
        }
        $this->assertNotManagedPickup($record);

        return ExpressOrderRecord::updateStatus($record->order_no, $status, $remark);
    }

    /**
     * 更新实际重量和费用
     * @param int $id
     * @param float $actualWeight
     * @param float $actualCost
     * @return bool
     */
    public function updateActualInfo(int $id, float $actualWeight, float $actualCost): bool
    {
        $record = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (!$record) {
            return false;
        }

        if (!is_finite($actualWeight) || !is_finite($actualCost) || $actualWeight < 0 || $actualCost < 0) {
            throw new \core\exception\CommonException('实际重量与费用须为有效的非负数');
        }
        // 占位记录尚无渠道单号，不能按空 order_no 更新所有站点的待确认记录。
        return \addon\hsx_recycle\app\service\core\express\ExpressOperationLock::run((int)$this->site_id, (string)$record->third_order_no, function () use ($record, $actualWeight, $actualCost) {
            $record->refresh();
            $raw = (array)$record->api_response;
            $raw['fee_verification_state'] = 'manual_confirmed';
            $raw['fee_verified_by'] = (int)$this->uid;
            $raw['fee_verified_at'] = time();
            $record->save(['actual_weight' => $actualWeight, 'actual_cost' => $actualCost,
                'weight_diff' => $actualWeight - (float)$record->estimated_weight,
                'cost_diff' => $actualCost - (float)$record->estimated_cost, 'api_response' => $raw]);
            return true;
        });
    }

    private function assertNotManagedPickup($record): void
    {
        if ($record && isset($record->api_response['booking_state'])) {
            throw new \core\exception\CommonException('这是渠道预约记录，不能直接改状态或删除。请查询原渠道结果或取消预约，避免重复叫件');
        }
    }

    private function safeRecord(array $record): array
    {
        if (is_array($record['api_response'] ?? null)) unset($record['api_response']['callback_salt']);
        return $record;
    }

    private function canResolveUnbooked(array $row, array $raw): bool
    {
        return in_array($raw['booking_state'] ?? '', ['submitting', 'unknown'], true)
            && empty($row['order_no']) && empty($row['delivery_id'])
            && empty($raw['provider_task_id']) && empty($raw['conflict']);
    }

    /** 仅核实没有任何有效预约的未知单；不调用外部下单/取消，也不允许改已返回单号的订单。 */
    public function resolveUnbooked(int $id, string $remark): bool
    {
        $remark = trim($remark);
        if (mb_strlen($remark) < 5 || mb_strlen($remark) > 500) {
            throw new \core\exception\CommonException('请填写5-500字的渠道核实依据，例如联系时间、渠道客服与确认结果');
        }
        $record = $this->model->where('site_id', $this->site_id)->find($id);
        if (!$record) throw new \core\exception\CommonException('运单记录不存在');
        return \addon\hsx_recycle\app\service\core\express\ExpressOperationLock::run((int)$this->site_id, (string)$record->third_order_no, function () use ($record, $remark) {
            $record->refresh();
            $raw = (array)$record->api_response;
            if (!in_array($raw['booking_state'] ?? '', ['submitting', 'unknown'], true)
                || $record->order_no || $record->delivery_id || !empty($raw['provider_task_id']) || !empty($raw['conflict'])) {
                throw new \core\exception\CommonException('该记录已有渠道结果或存在冲突，不能按“未预约”关闭；请查询原渠道并处理');
            }
            $raw['manual_review'] = ['uid' => (int)$this->uid, 'remark' => $remark, 'time' => time(), 'result' => 'no_active_booking'];
            $record->save(['api_response' => $raw]);
            (new \addon\hsx_recycle\app\service\core\express\RecyclePickupService())->applyResult($record, ['booking_state' => 'failed']);
            return true;
        });
    }
}

