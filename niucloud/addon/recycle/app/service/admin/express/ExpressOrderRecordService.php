<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\express;

use addon\recycle\app\dict\express\ExpressOrderDict;
use addon\recycle\app\model\express\ExpressOrderRecord;
use core\base\BaseAdminService;

/**
 * 快递订单记录服务类
 * Class ExpressOrderRecordService
 * @package addon\recycle\app\service\admin\express
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
        $field = 'id, site_id, order_no, recycle_order_id, recycle_device_id, provider_name, product_code, product_name, delivery_id,
                  sender_name, sender_mobile, sender_address, receiver_name, receiver_mobile, receiver_address,
                  goods_name, package_count, estimated_weight, actual_weight, weight_diff,
                  estimated_cost, actual_cost, cost_diff, payment_status, order_status,
                  pickup_time, delivery_time, create_at, update_at';

        $order = 'create_at desc';

        $searchModel = $this->model->withSearch(['site_id', 'order_no', 'delivery_id', 'order_status',
                                                  'recycle_order_id', 'recycle_device_id', 'provider_name', 'create_time'], $where)
            ->field($field)
            ->order($order)
            ->append(['status_text', 'payment_status_text']);

        $list = $this->pageQuery($searchModel);

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

        $info = $this->model->field($field)->where([['id', '=', $id]])->findOrEmpty()->toArray();

        if (!empty($info)) {
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
        return ExpressOrderRecord::getWeightDiffOrders($this->site_id, $threshold);
    }

    /**
     * 获取费用差异列表
     * @param float $threshold 差异阈值（元）
     * @return array
     */
    public function getCostDiffList(float $threshold = 5.0): array
    {
        return ExpressOrderRecord::getCostDiffOrders($this->site_id, $threshold);
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
        $records = ExpressOrderRecord::getByRecycleOrderId($recycleOrderId);

        // 添加状态文本
        foreach ($records as &$record) {
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

        return ExpressOrderRecord::updateActualInfo($record->order_no, $actualWeight, $actualCost);
    }
}

