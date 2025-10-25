<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\recycle_order;

use addon\recycle\app\service\admin\recycle_order\RecycleOrderService;
use addon\recycle\app\service\admin\order\RecycleOrderSignService;
use addon\recycle\app\service\admin\order\RecycleOrderCheckService;
use addon\recycle\app\service\admin\order\RecycleOrderPriceService;
use addon\recycle\app\service\admin\order\RecycleOrderPaymentService;
use addon\recycle\app\service\admin\order\RecycleOrderCloseService;
use addon\recycle\app\validate\RecycleOrderValidate;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\App;

/**
 * 回收订单管理控制器
 * Class RecycleOrder
 * @package addon\recycle\app\adminapi\controller\recycle_order
 */
class RecycleOrder extends BaseAdminController
{
    /**
     * @var RecycleOrderService
     */
    protected $service;

    /**
     * @var RecycleOrderValidate
     */
    protected $validate;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleOrderService();
        $this->validate = new RecycleOrderValidate();
    }

    /**
     * 获取回收订单列表
     * @return mixed
     */
    public function lists()
    {
        $data = $this->request->params([
            ['order_no', ''],
            ['order_id', ''],
            ['express_no', ''],
            ['customer_name', ''],
            ['customer_phone', ''],
            ['status', ''],
            ['delivery_type', ''],
            ['create_at', []],
            ['create_time_start', ''],
            ['create_time_end', ''],
            ['remark', ''],
            ['page', 1],
            ['limit', 10],
            ['search', ''],
            ['keyword', ''],
            ['imei', ''],
            ['device_imei', ''],
            ['device_model', ''],
            ['user_nickname', ''],
            ['user_mobile', ''],
            ['device_count_min', ''],
            ['device_count_max', ''],
            ['amount_min', ''],
            ['amount_max', ''],
            ['member_id', ''],
        ]);


        // 参数验证
        $this->validate->scene('list')->check($data);

        return success($this->service->getPage($data));
    }

    /**
     * 获取回收订单详情
     * @param int $id
     * @return mixed
     */
    public function detail(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);

        return success($this->service->getInfo($id));
    }

    /**
     * 代下单
     * @return mixed
     */
    public function create()
    {
        $data = $this->request->params([
            ['member_id', 0],
            ['customer_name', ''],
            ['customer_phone', ''],
            ['express_no', ''],
            ['express_company', ''],
            ['delivery_type', 1],
            ['remark', ''],
            ['devices', []]
        ]);

        // 参数验证
        $this->validate->scene('create')->check($data);

        return success($this->service->create($data));
    }

    /**
     * 签收订单
     * @param int $id
     * @return mixed
     */
    public function sign(int $id)
    {
        $data = $this->request->params([
            ['devices', []],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('sign')->check(array_merge(['id' => $id], $data));

        $signService = new RecycleOrderSignService();
        return success($signService->sign($id, $data));
    }

    /**
     * 开始质检
     * @param int $id
     * @return mixed
     */
    public function startCheck(int $id)
    {
        $data = $this->request->params([
            ['devices', []],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('check')->check(array_merge(['id' => $id], $data));

        $checkService = new RecycleOrderCheckService();
        return success($checkService->startCheck($id, $data));
    }

    /**
     * 完成质检
     * @param int $id
     * @return mixed
     */
    public function completeCheck(int $id)
    {
        $data = $this->request->params([
            ['devices', []],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('check')->check(array_merge(['id' => $id], $data));

        $checkService = new RecycleOrderCheckService();
        return success($checkService->completeCheck($id, $data));
    }

    /**
     * 确认价格
     * @param int $id
     * @return mixed
     */
    public function confirmPrice(int $id)
    {
        $data = $this->request->params([
            ['devices', []],
            ['pay_account', ''],
            ['pay_type', ''],
            ['pay_name', ''],
            ['pay_remark', ''],
            ['pay_url', ''],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('price')->check(array_merge(['id' => $id], $data));

        $priceService = new RecycleOrderPriceService();
        return success($priceService->confirmPrice($id, $data));
    }

    /**
     * 支付订单
     * @param int $id
     * @return mixed
     */
    public function payment(int $id)
    {
        $data = $this->request->params([
            ['pay_account', ''],
            ['pay_type', ''],
            ['pay_name', ''],
            ['pay_remark', ''],
            ['pay_url', ''],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('payment')->check(array_merge(['id' => $id], $data));

        $paymentService = new RecycleOrderPaymentService();
        return success($paymentService->payment($id, $data));
    }

    /**
     * 确认打款
     * @param int $id
     * @return mixed
     */
    public function paymentConfirm(int $id)
    {
        $data = $this->request->params([
            ['pay_account', ''],
            ['pay_type', ''],
            ['pay_name', ''],
            ['pay_remark', ''],
            ['pay_url', ''],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('payment')->check(array_merge(['id' => $id], $data));

        $paymentService = new RecycleOrderPaymentService();
        return success($paymentService->confirmPayment($id, $data));
    }

    /**
     * 关闭订单
     * @param int $id
     * @return mixed
     */
    public function close(int $id)
    {
        $data = $this->request->params([
            ['close_reason', ''],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('close')->check(array_merge(['id' => $id], $data));

        $closeService = new RecycleOrderCloseService();
        return success($closeService->close($id, $data));
    }

    /**
     * 取消订单
     * @param int $id
     * @return mixed
     */
    public function cancel(int $id)
    {
        $data = $this->request->params([
            ['cancel_reason', ''],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('cancel')->check(array_merge(['id' => $id], $data));

        return success($this->service->cancel($id, $data));
    }

    /**
     * 删除订单
     * @param int $id
     * @return mixed
     */
    public function delete(int $id)
    {
        // 参数验证
        $this->validate->scene('delete')->check(['id' => $id]);

        return success($this->service->delete($id));
    }

    /**
     * 更新订单
     * @param int $id
     * @return mixed
     */
    public function update(int $id)
    {
        $data = $this->request->params([
            ['action', ''],
            ['devices', []],
            ['remark', ''],
            ['cancel_reason', ''],
            ['close_reason', ''],
            ['device_ids', []]
        ]);

        // 参数验证
        $this->validate->scene('update')->check(array_merge(['id' => $id], $data));

        return success($this->service->update($id, $data));
    }

    /**
     * 获取订单设备列表
     * @param int $id
     * @return mixed
     */
    public function devices(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);

        return success($this->service->devices($id));
    }

    /**
     * 获取订单状态列表
     * @return mixed
     */
    public function getStatus()
    {
        return success($this->service->getStatus());
    }

    /**
     * 获取商户收款信息
     * @param int $id
     * @return mixed
     */
    public function getMerchantPayInfo(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);

        return success($this->service->getMerchantPayInfo($id));
    }

    /**
     * 向订单添加设备
     * @param int $id
     * @return mixed
     */
    public function addDevice(int $id)
    {
        $data = $this->request->params([
            ['imei', ''],
            ['model', ''],
            ['initial_price', 0],
            ['category_id', 1],
            ['remark', '']
        ]);

        // 参数验证
        $this->validate->scene('addDevice')->check(array_merge(['id' => $id], $data));

        // 使用专门的设备服务
        $deviceService = new \addon\recycle\app\service\admin\order\RecycleOrderDeviceService();
        $deviceId = $deviceService->addDeviceToOrder($id, $data);

        return success(['device_id' => $deviceId]);
    }

    /**
     * 批量向订单添加设备
     * @param int $id
     * @return mixed
     */
    public function batchAddDevices(int $id)
    {
        $data = $this->request->params([
            ['devices', []]
        ]);

        // 参数验证
        if (empty($data['devices'])) {
            return error('请提供设备数据');
        }

        foreach ($data['devices'] as $device) {
            $this->validate->scene('addDevice')->check(array_merge(['id' => $id], $device));
        }

        // 使用专门的设备服务
        $deviceService = new \addon\recycle\app\service\admin\order\RecycleOrderDeviceService();
        $deviceIds = $deviceService->batchAddDevicesToOrder($id, $data['devices']);

        return success(['device_ids' => $deviceIds]);
    }

    /**
     * 推送订单确认通知
     * @param int $id
     * @return mixed
     */
    public function pushNotify(int $id)
    {
        // 参数验证
        $this->validate->scene('detail')->check(['id' => $id]);

        // 调用推送通知服务
        $result = $this->service->pushOrderNotify($id);
        
        return success($result);
    }

    /**
     * 从订单中移除设备
     * @param int $id 订单ID
     * @param int $deviceId 设备ID
     * @return mixed
     */
    public function removeDevice(int $id, int $deviceId)
    {
        $data = $this->request->params([
            ['reason', '']
        ]);

        // 参数验证
        $this->validate->scene('removeDevice')->check(['order_id' => $id, 'device_id' => $deviceId]);

        // 使用专门的设备服务
        $deviceService = new \addon\recycle\app\service\admin\order\RecycleOrderDeviceService();
        $result = $deviceService->removeDeviceFromOrder($deviceId, $data['reason']);

        return success($result);
    }
}
