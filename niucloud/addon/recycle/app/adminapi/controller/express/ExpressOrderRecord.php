<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\express;

use addon\recycle\app\service\admin\express\ExpressOrderRecordService;
use core\base\BaseAdminController;

/**
 * 快递订单记录控制器
 * Class ExpressOrderRecord
 * @package addon\recycle\app\adminapi\controller\express
 */
class ExpressOrderRecord extends BaseAdminController
{
    /**
     * 获取快递订单记录列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_no', ''],
            ['delivery_id', ''],
            ['order_status', ''],
            ['recycle_order_id', 0],
            ['recycle_device_id', 0],
            ['provider_name', ''],
            ['create_time', []],
        ]);

        $service = new ExpressOrderRecordService();
        $list = $service->getPage($data);

        return success($list);
    }

    /**
     * 获取快递订单记录详情
     * @return \think\Response
     */
    public function info()
    {
        $id = $this->request->param('id', 0);

        if (empty($id)) {
            return fail('参数错误');
        }

        $service = new ExpressOrderRecordService();
        $info = $service->getInfo($id);

        return success($info);
    }

    /**
     * 添加快递订单记录
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ['order_no', ''],
            ['recycle_order_id', 0],
            ['recycle_device_id', 0],
            ['provider_name', ''],
            ['product_code', ''],
            ['product_name', ''],
            ['delivery_id', ''],
            ['sender_name', ''],
            ['sender_mobile', ''],
            ['sender_province', ''],
            ['sender_city', ''],
            ['sender_district', ''],
            ['sender_address', ''],
            ['receiver_name', ''],
            ['receiver_mobile', ''],
            ['receiver_province', ''],
            ['receiver_city', ''],
            ['receiver_district', ''],
            ['receiver_address', ''],
            ['goods_name', ''],
            ['goods_value', 0],
            ['package_count', 1],
            ['estimated_weight', 0],
            ['estimated_cost', 0],
            ['remark', ''],
        ]);

        $service = new ExpressOrderRecordService();
        $id = $service->add($data);

        return success(['id' => $id], '添加成功');
    }

    /**
     * 编辑快递订单记录
     * @return \think\Response
     */
    public function edit()
    {
        $id = $this->request->param('id', 0);

        if (empty($id)) {
            return fail('参数错误');
        }

        $data = $this->request->params([
            ['actual_weight', 0],
            ['actual_cost', 0],
            ['order_status', ''],
            ['remark', ''],
        ]);

        $service = new ExpressOrderRecordService();
        $service->edit($id, $data);

        return success([], '编辑成功');
    }

    /**
     * 删除快递订单记录
     * @return \think\Response
     */
    public function del()
    {
        $id = $this->request->param('id', 0);

        if (empty($id)) {
            return fail('参数错误');
        }

        $service = new ExpressOrderRecordService();
        $service->del($id);

        return success([], '删除成功');
    }

    /**
     * 更新订单状态
     * @return \think\Response
     */
    public function updateStatus()
    {
        $id = $this->request->param('id', 0);
        $status = $this->request->param('status', '');
        $remark = $this->request->param('remark', '');

        if (empty($id) || empty($status)) {
            return fail('参数错误');
        }

        $service = new ExpressOrderRecordService();
        $service->updateStatus($id, $status, $remark);

        return success([], '更新成功');
    }

    /**
     * 更新实际重量和费用
     * @return \think\Response
     */
    public function updateActualInfo()
    {
        $id = $this->request->param('id', 0);
        $actualWeight = $this->request->param('actual_weight', 0);
        $actualCost = $this->request->param('actual_cost', 0);

        if (empty($id)) {
            return fail('参数错误');
        }

        $service = new ExpressOrderRecordService();
        $service->updateActualInfo($id, (float)$actualWeight, (float)$actualCost);

        return success([], '更新成功');
    }

    /**
     * 获取重量差异列表
     * @return \think\Response
     */
    public function weightDiffList()
    {
        $threshold = $this->request->param('threshold', 0.5);

        $service = new ExpressOrderRecordService();
        $list = $service->getWeightDiffList((float)$threshold);

        return success($list);
    }

    /**
     * 获取费用差异列表
     * @return \think\Response
     */
    public function costDiffList()
    {
        $threshold = $this->request->param('threshold', 5.0);

        $service = new ExpressOrderRecordService();
        $list = $service->getCostDiffList((float)$threshold);

        return success($list);
    }

    /**
     * 获取快递费用统计
     * @return \think\Response
     */
    public function statistics()
    {
        $startTime = $this->request->param('start_time', 0);
        $endTime = $this->request->param('end_time', 0);

        $service = new ExpressOrderRecordService();
        $stats = $service->getStatistics((int)$startTime, (int)$endTime);

        return success($stats);
    }

    /**
     * 根据回收订单ID获取快递记录
     * @return \think\Response
     */
    public function getByRecycleOrderId()
    {
        $recycleOrderId = $this->request->param('recycle_order_id', 0);

        if (empty($recycleOrderId)) {
            return fail('参数错误');
        }

        $service = new ExpressOrderRecordService();
        $list = $service->getByRecycleOrderId((int)$recycleOrderId);

        return success($list);
    }
}
