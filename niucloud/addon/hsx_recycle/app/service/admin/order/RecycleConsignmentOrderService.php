<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleConsignmentDict;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleConsignmentLog;
use addon\hsx_recycle\app\model\order\RecycleConsignmentOrder;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleDevicePayment;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\service\admin\printer\RecyclePrintTriggerService;
use addon\hsx_recycle\app\service\core\order\OrderSubmitConfigService;
use addon\hsx_recycle\app\service\core\recycle_order\CoreRecycleOrderNotifyService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 代卖订单服务
 */
class RecycleConsignmentOrderService extends BaseAdminService
{
    private OrderSubmitConfigService $configService;

    public function __construct()
    {
        parent::__construct();
        $this->configService = new OrderSubmitConfigService();
    }

    public function getPage(array $where = []): array
    {
        $model = (new RecycleConsignmentOrder())
            ->where([['site_id', '=', $this->site_id]])
            ->with([
                'sourceOrder' => function ($query) {
                    $query->field('id,order_no,status,member_id');
                },
                'sourceDevice' => function ($query) {
                    $query->field('id,imei,imei2,sn,model,status,final_price,sell_price,category_id,capacity,color');
                },
                'member' => function ($query) {
                    $query->field('member_id,username,nickname,mobile,headimg');
                },
            ])
            ->append(['status_name', 'pay_status_name'])
            ->order('id desc');

        if (($where['keyword'] ?? '') !== '') {
            $keyword = trim((string)$where['keyword']);
            $model->where(function ($query) use ($keyword) {
                $query->whereLike('consignment_no', "%{$keyword}%")
                    ->whereOrLike('source_order_no', "%{$keyword}%")
                    ->whereOrLike('device_imei', "%{$keyword}%")
                    ->whereOrLike('device_model', "%{$keyword}%")
                    ->whereOrLike('customer_phone', "%{$keyword}%");
            });
        }
        if (($where['status'] ?? '') !== '') {
            $model->where('status', '=', (int)$where['status']);
        }
        if (($where['source_order_id'] ?? '') !== '') {
            $model->where('source_order_id', '=', (int)$where['source_order_id']);
        }
        if (!empty($where['create_time']) && is_array($where['create_time']) && count($where['create_time']) === 2) {
            $model->where('create_time', 'between', [strtotime($where['create_time'][0]), strtotime($where['create_time'][1])]);
        }

        return $this->pageQuery($model);
    }

    public function getInfo(int $id): array
    {
        $info = (new RecycleConsignmentOrder())
            ->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $id],
            ])
            ->with([
                'sourceOrder',
                'sourceDevice',
                'member' => function ($query) {
                    $query->field('member_id,username,nickname,mobile,headimg');
                },
            ])
            ->append(['status_name', 'pay_status_name'])
            ->findOrEmpty()
            ->toArray();

        if (empty($info)) {
            throw new CommonException('代卖订单不存在');
        }

        $info['logs'] = $this->getLogs($id);
        return $info;
    }

    public function getLogs(int $id): array
    {
        return (new RecycleConsignmentLog())
            ->where([
                ['site_id', '=', $this->site_id],
                ['consignment_id', '=', $id],
            ])
            ->order('id desc')
            ->select()
            ->toArray();
    }

    public function transferFromDevice(int $deviceId, array $data): array
    {
        if (!$this->configService->isConsignmentEnabled((int)$this->site_id)) {
            throw new CommonException('代卖业务未开启，请先到下单设置中启用代卖业务');
        }

        Db::startTrans();
        try {
            $device = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $deviceId],
            ])->findOrEmpty();
            if ($device->isEmpty()) {
                throw new CommonException('设备不存在');
            }
            if ((int)($device->status ?? 0) === RecycleOrderDict::DEVICE_STATUS_CONSIGNED || !empty($device->consignment_order_id)) {
                throw new CommonException('该设备已转入代卖，请勿重复操作');
            }
            if ((int)($device->pay_status ?? 0) === RecycleOrderDict::PAY_STATUS_PAID) {
                throw new CommonException('设备已打款，不能转入代卖');
            }
            if (in_array((int)$device->status, [RecycleOrderDict::DEVICE_STATUS_PENDING_CHECK, RecycleOrderDict::DEVICE_STATUS_CHECKING], true)) {
                throw new CommonException('设备未完成质检，不能转入代卖');
            }
            if ((int)$device->status === RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                throw new CommonException('设备已退回，不能转入代卖');
            }

            $order = RecycleOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$device->order_id],
                ['delete_at', '=', 0],
            ])->findOrEmpty();
            if ($order->isEmpty()) {
                throw new CommonException('来源回收订单不存在');
            }

            $now = time();
            $quotePrice = round((float)($device->final_price ?: $device->initial_price ?: 0), 2);
            $expectedPrice = round((float)($data['expected_price'] ?? 0), 2);
            $minSettlementPrice = round((float)($data['min_settlement_price'] ?? 0), 2);
            $listingPrice = round((float)($data['listing_price'] ?? $device->sell_price ?? 0), 2);
            $remark = trim((string)($data['remark'] ?? ''));
            $status = $listingPrice > 0 ? RecycleConsignmentDict::STATUS_SELLING : RecycleConsignmentDict::STATUS_PENDING;

            $consignment = RecycleConsignmentOrder::create([
                'site_id' => $this->site_id,
                'consignment_no' => $this->buildConsignmentNo(),
                'source_order_id' => (int)$order->id,
                'source_order_no' => (string)$order->order_no,
                'source_device_id' => (int)$device->id,
                'member_id' => (int)$order->member_id,
                'customer_name' => (string)($order->customer_name ?? ''),
                'customer_phone' => (string)($order->customer_phone ?? ''),
                'device_imei' => (string)$device->imei,
                'device_model' => (string)$device->model,
                'quote_price' => $quotePrice,
                'expected_price' => $expectedPrice,
                'min_settlement_price' => $minSettlementPrice,
                'listing_price' => $listingPrice,
                'status' => $status,
                'pay_status' => RecycleConsignmentDict::PAY_STATUS_UNPAID,
                'listed_time' => $status === RecycleConsignmentDict::STATUS_SELLING ? $now : 0,
                'operator_id' => $this->uid,
                'remark' => $remark,
                'create_time' => $now,
                'update_time' => $now,
            ]);

            $oldStatus = (int)$device->status;
            $device->save([
                'status' => RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
                'confirm_status' => RecycleOrderDict::CONFIRM_STATUS_CONFIRMED,
                'confirm_time' => $now,
                'confirm_member_id' => (int)$order->member_id,
                'confirm_remark' => $remark,
                'settlement_mode' => RecycleOrderDict::DISPOSE_TYPE_CONSIGN,
                'dispose_type' => RecycleOrderDict::DISPOSE_TYPE_CONSIGN,
                'dispose_status' => RecycleOrderDict::DISPOSE_STATUS_CONSIGNED,
                'consignment_order_id' => (int)$consignment->id,
                'sell_price' => $listingPrice,
                'update_at' => $now,
            ]);

            $this->recordLog((int)$consignment->id, 'create', 0, $status, [], $consignment->toArray(), $remark);
            RecycleDeviceLog::create([
                'site_id' => $this->site_id,
                'device_id' => (int)$device->id,
                'order_id' => (int)$order->id,
                'operator_id' => $this->uid,
                'operator_name' => $this->username,
                'operation_type' => 'device_consignment',
                'action' => 'transfer_consignment',
                'old_status' => $oldStatus,
                'new_status' => RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
                'remark' => '设备转入代卖 | 代卖单号: ' . $consignment->consignment_no . ($remark !== '' ? ' | 备注: ' . $remark : ''),
                'create_at' => $now,
            ]);

            (new RecycleOrderFlowModeService())->syncOrderProgress((int)$order->id);

            Db::commit();
            $this->afterConsignmentChanged((int)$consignment->id, 'create');
            return $this->getInfo((int)$consignment->id);
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    public function updateListing(int $id, array $data): bool
    {
        $order = $this->getModel($id);
        $before = $order->toArray();
        $listingPrice = round((float)($data['listing_price'] ?? $order->listing_price ?? 0), 2);
        $status = (int)$order->status === RecycleConsignmentDict::STATUS_PENDING && $listingPrice > 0
            ? RecycleConsignmentDict::STATUS_SELLING
            : (int)$order->status;
        $order->save([
            'listing_price' => $listingPrice,
            'status' => $status,
            'listed_time' => $order->listed_time ?: time(),
            'operator_id' => $this->uid,
            'remark' => trim((string)($data['remark'] ?? $order->remark ?? '')),
            'update_time' => time(),
        ]);
        RecycleDevice::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$order->source_device_id],
        ])->update(['sell_price' => $listingPrice, 'update_at' => time()]);
        $this->recordLog($id, 'listing', (int)$before['status'], $status, $before, $order->toArray(), trim((string)($data['remark'] ?? '')));
        $this->afterConsignmentChanged($id, 'listing');
        return true;
    }

    public function markSold(int $id, array $data): bool
    {
        $order = $this->getModel($id);
        $before = $order->toArray();
        $soldPrice = round((float)($data['sold_price'] ?? 0), 2);
        $settlementAmount = round((float)($data['settlement_amount'] ?? 0), 2);
        if ($soldPrice <= 0) {
            throw new CommonException('请输入实际成交价');
        }
        if ($settlementAmount <= 0) {
            throw new CommonException('请输入客户结算金额');
        }
        if ($settlementAmount > $soldPrice) {
            throw new CommonException('客户结算金额不能大于实际成交价');
        }
        $order->save([
            'sold_price' => $soldPrice,
            'settlement_amount' => $settlementAmount,
            'service_fee' => round($soldPrice - $settlementAmount, 2),
            'status' => RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT,
            'sold_time' => time(),
            'operator_id' => $this->uid,
            'remark' => trim((string)($data['remark'] ?? $order->remark ?? '')),
            'update_time' => time(),
        ]);
        RecycleDevice::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$order->source_device_id],
        ])->update([
            'sell_price' => $soldPrice,
            'final_price' => $settlementAmount,
            'update_at' => time(),
        ]);
        $this->recordLog($id, 'sold', (int)$before['status'], RecycleConsignmentDict::STATUS_PENDING_SETTLEMENT, $before, $order->toArray(), trim((string)($data['remark'] ?? '')));
        $this->afterConsignmentChanged($id, 'sold');
        return true;
    }

    public function settle(int $id, array $data): bool
    {
        Db::startTrans();
        try {
            $order = $this->getModel($id);
            if ((int)$order->pay_status === RecycleConsignmentDict::PAY_STATUS_PAID || (int)$order->status === RecycleConsignmentDict::STATUS_SETTLED) {
                throw new CommonException('该代卖订单已结算，请勿重复操作');
            }

            $before = $order->toArray();
            $amount = round((float)($data['settlement_amount'] ?? $order->settlement_amount ?? 0), 2);
            if ($amount <= 0) {
                throw new CommonException('请输入结算金额');
            }
            if ((float)$order->sold_price > 0 && $amount > (float)$order->sold_price) {
                throw new CommonException('结算金额不能大于成交价');
            }

            $now = time();
            $remark = trim((string)($data['remark'] ?? $order->remark ?? ''));
            $payNo = $this->buildSettlementPayNo($id);
            $serviceFee = round((float)$order->sold_price - $amount, 2);

            $order->save([
                'settlement_amount' => $amount,
                'service_fee' => $serviceFee,
                'status' => RecycleConsignmentDict::STATUS_SETTLED,
                'pay_status' => RecycleConsignmentDict::PAY_STATUS_PAID,
                'pay_time' => $now,
                'pay_uid' => $this->uid,
                'settle_time' => $now,
                'operator_id' => $this->uid,
                'remark' => $remark,
                'update_time' => $now,
            ]);

            $device = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$order->source_device_id],
            ])->findOrEmpty();
            if ($device->isEmpty()) {
                throw new CommonException('来源设备不存在，无法完成结算');
            }

            $sourceOrder = RecycleOrder::where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$order->source_order_id],
                ['delete_at', '=', 0],
            ])->findOrEmpty();

            $device->save([
                'final_price' => $amount,
                'pay_status' => RecycleOrderDict::PAY_STATUS_PAID,
                'pay_amount' => $amount,
                'pay_time' => $now,
                'pay_uid' => $this->uid,
                'pay_no' => $payNo,
                'update_at' => $now,
            ]);

            RecycleDevicePayment::create([
                'site_id' => $this->site_id,
                'pay_no' => $payNo,
                'order_id' => (int)$order->source_order_id,
                'device_id' => (int)$order->source_device_id,
                'member_id' => (int)$order->member_id,
                'order_no' => (string)$order->source_order_no,
                'device_imei' => (string)$order->device_imei,
                'device_model' => (string)$order->device_model,
                'amount' => $amount,
                'pay_type' => 'consignment',
                'pay_account' => '',
                'pay_name' => (string)($order->customer_name ?: ($sourceOrder->customer_name ?? '')),
                'pay_remark' => $remark !== '' ? $remark : '代卖订单结算',
                'payment_images' => '',
                'pay_uid' => $this->uid,
                'pay_time' => $now,
                'create_at' => $now,
            ]);

            RecycleDeviceLog::create([
                'site_id' => $this->site_id,
                'device_id' => (int)$order->source_device_id,
                'order_id' => (int)$order->source_order_id,
                'operator_id' => $this->uid,
                'operator_name' => $this->username,
                'operation_type' => 'consignment_payment',
                'action' => 'consignment_settle',
                'old_status' => (int)$device->status,
                'new_status' => RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
                'remark' => sprintf('代卖结算 | 代卖单号: %s | 金额: %.2f | 批次: %s | 备注: %s', $order->consignment_no, $amount, $payNo, $remark),
                'create_at' => $now,
            ]);

            (new RecycleOrderFlowModeService())->syncOrderProgress((int)$order->source_order_id);
            $this->recordLog($id, 'settle', (int)$before['status'], RecycleConsignmentDict::STATUS_SETTLED, $before, $order->toArray(), $remark);

            Db::commit();
            $this->afterConsignmentChanged($id, 'settle');
            return true;
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    public function close(int $id, int $status, string $remark = ''): bool
    {
        if (!in_array($status, [RecycleConsignmentDict::STATUS_CANCELLED, RecycleConsignmentDict::STATUS_RETURNED], true)) {
            throw new CommonException('代卖关闭状态不正确');
        }
        $order = $this->getModel($id);
        $before = $order->toArray();
        $order->save([
            'status' => $status,
            'cancel_time' => time(),
            'operator_id' => $this->uid,
            'remark' => $remark,
            'update_time' => time(),
        ]);
        $this->recordLog($id, $status === RecycleConsignmentDict::STATUS_RETURNED ? 'return' : 'cancel', (int)$before['status'], $status, $before, $order->toArray(), $remark);
        $this->afterConsignmentChanged($id, $status === RecycleConsignmentDict::STATUS_RETURNED ? 'return' : 'cancel');
        return true;
    }

    public function pushNotify(int $id): array
    {
        $order = $this->getModel($id);
        $result = (new CoreRecycleOrderNotifyService())->consignmentStatusNotify([
            'site_id' => $this->site_id,
            'consignment_id' => $id,
            'scene' => 'manual_consignment',
        ]);

        $this->recordLog(
            $id,
            'notify',
            (int)$order->status,
            (int)$order->status,
            $order->toArray(),
            $order->toArray(),
            !empty($result['success']) ? '手动推送代卖进度通知' : ('手动推送代卖进度通知失败：' . ($result['message'] ?? '未知错误'))
        );

        return $result;
    }

    private function getModel(int $id): RecycleConsignmentOrder
    {
        $order = RecycleConsignmentOrder::where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $id],
        ])->findOrEmpty();
        if ($order->isEmpty()) {
            throw new CommonException('代卖订单不存在');
        }
        return $order;
    }

    private function recordLog(int $consignmentId, string $action, int $oldStatus, int $newStatus, array $before, array $after, string $remark = ''): void
    {
        RecycleConsignmentLog::create([
            'site_id' => $this->site_id,
            'consignment_id' => $consignmentId,
            'source_order_id' => (int)($after['source_order_id'] ?? $before['source_order_id'] ?? 0),
            'source_device_id' => (int)($after['source_device_id'] ?? $before['source_device_id'] ?? 0),
            'operator_id' => $this->uid,
            'operator_name' => $this->username,
            'action' => $action,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'before_data' => $before,
            'after_data' => $after,
            'remark' => $remark,
            'create_time' => time(),
        ]);
    }

    private function afterConsignmentChanged(int $id, string $action): void
    {
        $triggerKey = $this->getTriggerKey($action);
        if ($triggerKey === '') {
            return;
        }

        if ($this->configService->isConsignmentNoticeEnabled((int)$this->site_id)) {
            try {
                (new CoreRecycleOrderNotifyService())->consignmentStatusNotify([
                    'site_id' => $this->site_id,
                    'consignment_id' => $id,
                    'scene' => $triggerKey,
                ]);
            } catch (\Throwable $e) {
                Log::error('【代卖通知】发送失败：' . $e->getMessage(), [
                    'site_id' => $this->site_id,
                    'consignment_id' => $id,
                    'action' => $action,
                ]);
            }
        }

        if ($this->configService->isConsignmentPrintEnabled((int)$this->site_id)) {
            try {
                (new RecyclePrintTriggerService())->auto($triggerKey, [
                    'consignment_id' => $id,
                    'biz_id' => $id,
                ]);
            } catch (\Throwable $e) {
                Log::error('【代卖打印】自动触发失败：' . $e->getMessage(), [
                    'site_id' => $this->site_id,
                    'consignment_id' => $id,
                    'action' => $action,
                    'trigger_key' => $triggerKey,
                ]);
            }
        }
    }

    private function getTriggerKey(string $action): string
    {
        return [
            'create' => 'consignment.created',
            'listing' => 'consignment.listed',
            'sold' => 'consignment.sold',
            'settle' => 'consignment.settled',
            'cancel' => 'consignment.cancelled',
            'return' => 'consignment.returned',
        ][$action] ?? '';
    }

    private function buildConsignmentNo(): string
    {
        return 'C' . date('YmdHis') . random_int(1000, 9999);
    }

    private function buildSettlementPayNo(int $consignmentId): string
    {
        return 'CP' . date('YmdHis') . $consignmentId . random_int(1000, 9999);
    }
}
