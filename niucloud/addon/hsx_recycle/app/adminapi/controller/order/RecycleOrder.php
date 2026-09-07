<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\order;

use addon\hsx_recycle\app\service\admin\order\RecycleOrderService;
use addon\hsx_recycle\app\service\admin\order\RecycleOrderService as OrderFlowService;
use addon\hsx_recycle\app\service\admin\order\RecycleDevicePaymentService;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\validate\RecycleOrderValidate;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpFinanceBridgeService;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpIntegrationService;
use addon\hsx_recycle\app\service\core\recycle_order\RecyclePaymentOwnershipService;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminController;
use core\exception\CommonException;
use think\App;

/**
 * 回收订单管理控制器
 * Class RecycleOrder
 * @package addon\hsx_recycle\app\adminapi\controller\recycle_order
 */
class RecycleOrder extends BaseAdminController
{
    /**
     * @var RecycleOrderService
     */
    protected $service;

    /**
     * @var OrderFlowService
     */
    protected $flowService;

    /**
     * @var RecycleOrderValidate
     */
    protected $validate;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleOrderService();
        $this->flowService = new OrderFlowService();
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
            ['logistics_vehicle_no', ''],
            ['order_source', ''],
            ['agent_name', ''],
            ['create_at', []],
            ['create_time_start', ''],
            ['create_time_end', ''],
            ['update_time_start', ''],
            ['update_time_end', ''],
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
            ['sign_at', []],
            ['complete_at', []],
            ['pay_time', []],
            ['start_time', ''],
            ['end_time', ''],
            ['filter_key', ''],
            ['view_mode', ''],
            ['dashboard_title', ''],
            ['check_timeout_hours', 24],
            ['quote_timeout_hours', 2],
            ['pay_timeout_hours', 2],
            ['high_cost_amount', 3000],
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
            ['logistics_name', ''],
            ['logistics_vehicle_no', ''],
            ['logistics_contact_name', ''],
            ['logistics_contact_mobile', ''],
            ['logistics_pickup_address', ''],
            ['remark', ''],
            ['count', 1],
            ['devices', []],
            ['order_source', 'agent'],
            ['agent_name', ''],
            ['agent_mobile', ''],
            ['sign_after_create', false],
            ['draft_device_entry', false],
            ['next_assignee_uid', 0]
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
            ['remark', ''],
            ['next_assignee_uid', 0]
        ]);

        // 参数验证
        $this->validate->scene('sign')->check(array_merge(['id' => $id], $data));

        return success($this->service->sign($id, $data));
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

        return success($this->flowService->startCheck($id, $data));
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

        return success($this->flowService->completeCheck($id, $data));
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

        return success($this->flowService->setPrice($id, $data));
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
            ['remark', ''],
            ['account', ''],
            ['payment_images', ''],
            ['payment_info', []],
            ['capital_account_id', 0],
            ['request_id', '']
        ]);
        $data = $this->fillPaymentInfo($data);

        // 参数验证
        $this->validate->scene('payment')->check(array_merge(['id' => $id], $data));

        if ((new RecycleErpCapabilityService())->isPaymentManaged($this->request->siteId(), $id)) {
            return success($this->settleByErp($id, $this->orderDeviceIds($id), $data));
        }
        (new RecycleDevicePaymentService())->assertOrderPaymentAllowed($id);

        return success($this->flowService->payment($id, $data));
    }

    /**
     * 确认打款
     * @param int $id
     * @return mixed
     */
    public function paymentConfirm($id)
    {
        $id = intval($id);
        $data = $this->request->params([
            ['pay_account', ''],
            ['pay_type', ''],
            ['pay_name', ''],
            ['pay_remark', ''],
            ['pay_url', ''],
            ['remark', ''],
            ['account', ''],           // 收款账号
            ['payment_images', ''],    // 打款凭证图片
            ['payment_info', []],
            ['capital_account_id', 0], // 出账户头ID（来自ERP资金账户，0=未选）
            ['request_id', '']
        ]);
        $data = $this->fillPaymentInfo($data);

        // 参数验证
        $this->validate->scene('payment')->check(array_merge(['id' => $id], $data));

        if ((new RecycleErpCapabilityService())->isPaymentManaged($this->request->siteId(), $id)) {
            return success($this->settleByErp($id, $this->orderDeviceIds($id), $data));
        }
        (new RecycleDevicePaymentService())->assertOrderPaymentAllowed($id);

        return success($this->flowService->payment($id, $data));
    }

    /**
     * 按设备确认打款
     * @param int $id
     * @return mixed
     */
    public function devicePaymentConfirm($id)
    {
        $id = intval($id);
        $data = $this->request->params([
            ['device_ids', []],
            ['pay_account', ''],
            ['pay_type', ''],
            ['pay_name', ''],
            ['pay_remark', ''],
            ['pay_url', ''],
            ['remark', ''],
            ['account', ''],
            ['payment_images', ''],
            ['payment_info', []],
            ['capital_account_id', 0], // 出账户头ID（来自ERP资金账户，0=未选）
            ['request_id', '']
        ]);
        $data = $this->fillPaymentInfo($data);

        if (!is_array($data['device_ids']) || $data['device_ids'] === []) throw new CommonException('请选择需要付款的设备');
        $deviceIds = RecyclePaymentOwnershipService::deviceIds($data['device_ids']);
        if ((new RecycleErpCapabilityService())->isPaymentManaged($this->request->siteId(), $id, $deviceIds)) {
            return success($this->settleByErp($id, $deviceIds, $data));
        }

        // 该批设备确由回收负责时，保留本地付款入口；不是仅凭插件安装状态分流。
        return success((new RecycleDevicePaymentService())->payDevices($id, $data));
    }

    /**
     * 出账户头候选（打款弹框用）
     * 统一通过只读契约向 ERP 获取，不再直连 ERP 服务或使用旧事件。
     * @return mixed
     */
    public function capitalAccountOptions()
    {
        $params = $this->request->params([['order_id', 0], ['device_ids', []]]);
        $capability = (new RecycleErpCapabilityService())->paymentCapability(
            $this->request->siteId(), (int)$params['order_id'], (array)$params['device_ids']
        );
        $accounts = [];
        if ($capability['payment_managed_by_erp'] && $capability['erp_connected']) {
            $accounts = (new RecycleErpFinanceBridgeService())->capitalAccountOptions($this->request->siteId());
        }

        return success(array_merge(['accounts' => $accounts], $capability));
    }

    public function erpIntegration()
    {
        $this->assertIntegrationPermission(false);
        return success((new RecycleErpIntegrationService())->get($this->request->siteId()));
    }

    public function saveErpIntegration()
    {
        $this->assertIntegrationPermission(true);
        $data = $this->request->params([['mode', ''], ['confirm', false]]);
        return success((new RecycleErpIntegrationService())->save(
            $this->request->siteId(), (string)$data['mode'], in_array($data['confirm'], [true, 1, '1'], true)
        ));
    }

    /** 复用已有下单设置权限，手工升级无需重置菜单；未注册的新接口也不能越权。 */
    private function assertIntegrationPermission(bool $write): void
    {
        if (AuthService::isSuperAdmin()) return;
        $keys = array_column((new AuthService())->getAuthMenuList(), 'menu_key');
        $key = $write ? 'recycle_order_submit_config_save' : 'recycle_order_submit_config';
        if (!in_array($key, $keys, true)) throw new CommonException('没有修改或查看回收联动设置的权限');
    }

    /**
     * 取订单下全部设备ID（整单打款核销应付用）。
     */
    private function orderDeviceIds(int $orderId): array
    {
        return array_map('intval', RecycleDevice::where([
                ['order_id', '=', $orderId],
                ['site_id', '=', $this->request->siteId()],
        ])->column('id'));
    }

    private function settleByErp(int $orderId, array $deviceIds, array $data): array
    {
        $result = (new RecycleErpFinanceBridgeService())->settleSourceDevices(
            $this->request->siteId(),
            $deviceIds,
            [
                'capital_account_id' => (int)($data['capital_account_id'] ?? 0),
                'payment_images' => $data['payment_images'] ?? [],
                'pay_remark' => trim((string)($data['pay_remark'] ?? $data['remark'] ?? '')) ?: '回收设备付款',
                'request_id' => trim((string)($data['request_id'] ?? '')),
            ]
        );
        return array_merge((new RecycleDevicePaymentService())->getPaymentSummary($orderId), [
            'erp_settlement' => $result,
            'payment_managed_by_erp' => true,
        ]);
    }

    /**
     * 设备打款记录
     * @param int $id
     * @return mixed
     */
    public function devicePaymentLogs(int $id)
    {
        return success((new RecycleDevicePaymentService())->getPaymentLogs($id));
    }

    /**
     * 订单通知记录
     * @param int $id
     * @return mixed
     */
    public function noticeLogs(int $id)
    {
        $this->validate->scene('detail')->check(['id' => $id]);

        return success($this->service->getNoticeLogs($id));
    }

    /**
     * 按设备确认报价
     * @param int $id
     * @return mixed
     */
    public function deviceConfirm(int $id)
    {
        $data = $this->request->params([
            ['device_ids', []],
            ['confirm_status', 1],
            ['remark', '']
        ]);

        return success($this->service->confirmDevices($id, $data));
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

        // 将close_reason映射为reason，因为flow service期望reason字段
        $data['reason'] = $data['close_reason'];
        unset($data['close_reason']);

        return success($this->flowService->close($id, $data));
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

        // 将cancel_reason映射为reason，因为flow service期望reason字段
        $data['reason'] = $data['cancel_reason'];
        unset($data['cancel_reason']);

        return success($this->flowService->cancel($id, $data));
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
            ['device_ids', []],
            ['next_assignee_uid', 0]
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
     * 获取移动端订单业务阶段选项
     * @return mixed
     */
    public function getBusinessStageOptions()
    {
        return success($this->service->getBusinessStageOptions());
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
            ['imei2', ''],
            ['serial_number', ''],
            ['capacity', ''],
            ['color', ''],
            ['system_version', ''],
            ['warranty_info', ''],
            ['battery_health', ''],
            ['battery_cycle', ''],
            ['device_readings', []],
            ['model', ''],
            ['initial_price', 0],
            ['category_id', 0],
            ['category_path', []],
            ['check_template_id', 0],
            ['check_images_buyer', ''],
            ['summary', []],
            ['remark', '']
        ]);

        // 参数验证(开启 failException:校验失败抛 ValidateException 拦截,而不是只返回 false)
        $this->validate->scene('addDevice')->failException()->check(array_merge(['id' => $id], $data));

        // 使用专门的设备服务
        $deviceService = new \addon\hsx_recycle\app\service\admin\order\RecycleOrderDeviceService();
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
            $this->validate->scene('addDevice')->failException()->check(array_merge(['id' => $id], $device));
        }

        // 使用专门的设备服务
        $deviceService = new \addon\hsx_recycle\app\service\admin\order\RecycleOrderDeviceService();
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
        $deviceService = new \addon\hsx_recycle\app\service\admin\order\RecycleOrderDeviceService();
        $result = $deviceService->removeDeviceFromOrder($deviceId, $data['reason']);

        return success($result);
    }

    /**
     * 兼容旧的扁平打款参数，流程引擎校验需要 payment_info。
     * @param array $data
     * @return array
     */
    private function fillPaymentInfo(array $data): array
    {
        if (empty($data['payment_info']) || !is_array($data['payment_info'])) {
            $data['payment_info'] = [
                'pay_type' => $data['pay_type'] ?? '',
                'account' => $data['account'] ?? '',
                'payment_images' => $data['payment_images'] ?? '',
                'remark' => $data['remark'] ?? ''
            ];
        }

        return $data;
    }
}
