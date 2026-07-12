<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\order;

use addon\hsx_recycle\app\service\admin\order\RecycleOrderService;
use addon\hsx_recycle\app\service\admin\order\RecycleOrderService as OrderFlowService;
use addon\hsx_recycle\app\service\admin\order\RecycleDevicePaymentService;
use addon\hsx_recycle\app\model\order\RecycleOrder as RecycleOrderModel;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\validate\RecycleOrderValidate;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService;
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
            ['remark', ''],
            ['count', 1],
            ['devices', []],
            ['order_source', 'agent'],
            ['agent_name', ''],
            ['agent_mobile', ''],
            ['sign_after_create', false],
            ['draft_device_entry', false]
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

        return success($this->flowService->sign($id, $data));
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
            ['payment_info', []]
        ]);
        $data = $this->fillPaymentInfo($data);

        // 参数验证
        $this->validate->scene('payment')->check(array_merge(['id' => $id], $data));

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
            ['capital_account_id', 0]  // 出账户头ID（来自ERP资金账户，0=未选）
        ]);
        $data = $this->fillPaymentInfo($data);

        // 参数验证
        $this->validate->scene('payment')->check(array_merge(['id' => $id], $data));

        (new RecycleDevicePaymentService())->assertOrderPaymentAllowed($id);

        // 选了出账户头则先校验余额够不够, 不够提示换户头(不动打款)
        $capId = (int)($data['capital_account_id'] ?? 0);
        if ($capId > 0) {
            $amt = (float)RecycleDevice::where([['order_id', '=', $id], ['site_id', '=', $this->request->siteId()]])->sum('final_price');
            $this->assertCapitalEnough($capId, $amt);
        }

        $result = $this->flowService->payment($id, $data);
        // 打款成功后，若选了出账户头则在ERP记一笔出账流水（整单：按设备final_price合计）
        $this->recordCapitalOutflow($id, (int)($data['capital_account_id'] ?? 0), null, '');
        // 同步核销该订单设备的应付（与资金扣减配套，形成完整账目往来），并把出账户头带给结算用于对账展示
        $this->settleErpPayables($this->orderDeviceIds($id), (int)($data['capital_account_id'] ?? 0));
        return success($result);
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
            ['capital_account_id', 0]  // 出账户头ID（来自ERP资金账户，0=未选）
        ]);
        $data = $this->fillPaymentInfo($data);

        // 选了出账户头则先校验余额(按本批次设备final_price合计), 不够提示换户头
        $capId = (int)($data['capital_account_id'] ?? 0);
        if ($capId > 0) {
            $dids = array_values(array_filter(array_map('intval', (array)($data['device_ids'] ?? []))));
            if (!empty($dids)) {
                $amt = (float)RecycleDevice::where([['site_id', '=', $this->request->siteId()]])->whereIn('id', $dids)->sum('final_price');
                $this->assertCapitalEnough($capId, $amt);
            }
        }

        $result = (new RecycleDevicePaymentService())->payDevices($id, $data);
        // 打款成功后，若选了出账户头则在ERP记一笔出账流水（设备级：本批次实付金额）
        $this->recordCapitalOutflow(
            $id,
            (int)($data['capital_account_id'] ?? 0),
            (float)($result['paid_amount'] ?? 0),
            (string)($result['pay_no'] ?? '')
        );
        // 同步核销本批次设备的应付（与资金扣减配套），并把出账户头带给结算用于对账展示
        $this->settleErpPayables(array_map('intval', (array)($data['device_ids'] ?? [])), (int)($data['capital_account_id'] ?? 0));
        return success($result);
    }

    /**
     * 出账户头候选（打款弹框用）
     * 解耦：发 GetErpCapitalAccountList 事件向 ERP 取启用资金账户；
     * ERP 未安装则无人应答 → 返回空 → 前端隐藏户头选择，不影响原打款流程。
     * @return mixed
     */
    public function capitalAccountOptions()
    {
        $capability = (new RecycleErpCapabilityService())->paymentCapability($this->request->siteId());
        $accounts = [];
        $erpConnected = (bool)$capability['erp_connected'];
        if ($erpConnected) {
            try {
                $raw = (array)event('GetErpCapitalAccountList', ['site_id' => $this->request->siteId()]);
                foreach ($raw as $r) {
                    if (is_array($r)) {
                        $accounts = array_values($r);
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $accounts = [];
            }
        }

        // 兜底：事件未应答时，若 ERP 类在场则直接取（避免依赖事件注册时机）
        if ($erpConnected && empty($accounts)) {
            $cls = '\\addon\\hsx_erp\\app\\service\\admin\\ErpCapitalAccountService';
            if (class_exists($cls)) {
                try {
                    $all = (new $cls())->getAll();
                    $accounts = array_values(array_filter($all, function ($a) {
                        return (int)($a['status'] ?? 1) === 1;
                    }));
                } catch (\Throwable $e) {
                    // ERP 在场但取数失败：保持已连接判断，账户留空
                }
            }
        }

        return success(array_merge(['accounts' => $accounts], $capability));
    }

    /**
     * 取订单下全部设备ID（整单打款核销应付用）。
     */
    private function orderDeviceIds(int $orderId): array
    {
        try {
            return array_map('intval', RecycleDevice::where([
                ['order_id', '=', $orderId],
                ['site_id', '=', $this->request->siteId()],
            ])->column('id'));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 打款成功后：核销这些设备在 ERP 财务的应付（与资金账户扣减配套，形成完整账目往来）。
     * 解耦：仅发事件，ERP 未装则无人应答；失败只吞日志，绝不影响打款主流程。
     */
    private function settleErpPayables(array $deviceIds, int $capitalAccountId = 0): void
    {
        $deviceIds = array_values(array_filter(array_map('intval', $deviceIds)));
        if (empty($deviceIds)) {
            return;
        }
        try {
            // 现金已在 recordCapitalOutflow 扣账，这里只核销+记录户头(record_cash=false 防重复扣账)。
            $results = (array)event('SettleErpPayableByDevice', [
                'site_id' => $this->request->siteId(),
                'source_device_ids' => $deviceIds,
                'capital_account_id' => $capitalAccountId,
                'record_cash' => false,
                'remark' => '回收打款核销应付',
            ]);
            $handled = false;
            foreach ($results as $r) {
                if (is_array($r) && !empty($r['ok'])) { $handled = true; break; }
            }
            if (!$handled) {
                $cls = '\\addon\\hsx_erp\\app\\service\\admin\\FinanceSettlementService';
                if (class_exists($cls)) {
                    (new $cls())->settleByDeviceIds($deviceIds, [
                        'remark' => '回收打款核销应付',
                        'capital_account_id' => $capitalAccountId,
                        'record_cash' => false,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            \think\facade\Log::warning('回收打款核销应付失败：' . $e->getMessage() . ' device_ids=' . implode(',', $deviceIds));
        }
    }

    /**
     * 打款成功后：若选了"出账户头"且已安装 ERP，则在 ERP 记一笔出账流水（扣余额）。
     * 解耦：仅发事件，ERP 未装则无人应答；记账失败也绝不影响打款主流程。
     *
     * @param int $orderId 订单ID
     * @param int $capitalAccountId 出账户头ID（0=未选，跳过）
     * @param float|null $amount 出账金额；null 时按订单设备 final_price 合计计算（整单打款）
     * @param string $sourceNo 来源单号；空时回退用订单号
     */
    /** 打款前校验所选 ERP 出账户头余额是否充足, 不足抛异常提示换户头(ERP未装则跳过) */
    private function assertCapitalEnough(int $capitalAccountId, float $amount): void
    {
        if ($capitalAccountId <= 0 || $amount <= 0) {
            return;
        }
        $cls = '\\addon\\hsx_erp\\app\\service\\admin\\ErpCapitalAccountService';
        if (class_exists($cls)) {
            (new $cls())->assertBalanceEnough($capitalAccountId, $amount);
        }
    }

    private function recordCapitalOutflow(int $orderId, int $capitalAccountId, ?float $amount, string $sourceNo): void
    {
        if ($capitalAccountId <= 0) {
            return;
        }
        try {
            $order = RecycleOrderModel::where([['id', '=', $orderId], ['site_id', '=', $this->request->siteId()]])->findOrEmpty();
            if ($amount === null) {
                $amount = (float)RecycleDevice::where([
                    ['order_id', '=', $orderId],
                    ['site_id', '=', $this->request->siteId()],
                ])->sum('final_price');
            }
            $amount = round((float)$amount, 2);
            if ($amount <= 0) {
                return;
            }
            $orderNo = $order->isEmpty() ? (string)$orderId : (string)$order->order_no;
            $entry = [
                'account_id'        => $capitalAccountId,
                'direction'         => 'out',
                'amount'            => $amount,
                'biz_type'          => 'recycle_payment',
                'counterparty_id'   => $order->isEmpty() ? 0 : (int)$order->member_id, // 客户=会员，供流水关联到人
                'counterparty_name' => $order->isEmpty() ? '' : (string)$order->customer_name,
                'source_type'       => 'recycle_order',
                'source_no'         => $sourceNo !== '' ? $sourceNo : $orderNo,
                'source_id'         => $orderId,
                'remark'            => '回收打款 - 订单：' . $orderNo,
            ];
            // 先走事件；若无人成功应答(未注册/事件缓存未刷新等)，直连 ERP 兜底记账，避免静默丢账。
            $results = (array)event('RecordErpCapitalFlow', array_merge(['site_id' => $this->request->siteId()], $entry));
            if (!in_array(true, $results, true)) {
                $cls = '\\addon\\hsx_erp\\app\\service\\admin\\ErpCapitalAccountService';
                if (class_exists($cls)) {
                    (new $cls())->recordEntry($entry);
                }
            }
        } catch (\Throwable $e) {
            // 记日志而非静默吞掉，便于排查（不影响打款主流程）
            \think\facade\Log::warning('回收打款记ERP资金流水失败：' . $e->getMessage(), [
                'order_id' => $orderId, 'capital_account_id' => $capitalAccountId,
            ]);
        }
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
            ['model', ''],
            ['initial_price', 0],
            ['category_id', 1],
            ['category_path', []],
            ['check_template_id', 0],
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
