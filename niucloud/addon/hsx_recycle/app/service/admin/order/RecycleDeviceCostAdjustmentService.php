<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\order;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleDeviceCostAdjustment;
use addon\hsx_recycle\app\model\order\RecycleDeviceLog;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 已回收设备成本调整
 */
class RecycleDeviceCostAdjustmentService extends BaseAdminService
{
    public const TYPE_REFUND_FROM_CUSTOMER = 'refund_from_customer';
    public const TYPE_PAY_TO_CUSTOMER = 'pay_to_customer';
    public const TYPE_COST_CORRECTION = 'cost_correction';

    private const TYPE_LABELS = [
        self::TYPE_REFUND_FROM_CUSTOMER => '客户退回差额',
        self::TYPE_PAY_TO_CUSTOMER => '补款给客户',
        self::TYPE_COST_CORRECTION => '内部成本修正',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDeviceCostAdjustment();
    }

    public function lists(int $deviceId): array
    {
        return (new RecycleDeviceCostAdjustment())
            ->where([
                ['site_id', '=', $this->site_id],
                ['device_id', '=', $deviceId],
            ])
            ->order('id desc')
            ->select()
            ->toArray();
    }

    public function ability(int $deviceId): array
    {
        $device = (new RecycleDevice())
            ->where([
                ['id', '=', $deviceId],
                ['site_id', '=', $this->site_id],
            ])
            ->findOrEmpty();

        if ($device->isEmpty()) {
            return [
                'allowed' => false,
                'reason' => '设备不存在',
            ];
        }

        if ((int)$device->pay_status !== RecycleOrderDict::PAY_STATUS_PAID) {
            return [
                'allowed' => false,
                'reason' => '只有已打款设备才允许调整成本',
            ];
        }

        if ((int)$device->status === RecycleOrderDict::DEVICE_STATUS_RETURNED) {
            return [
                'allowed' => false,
                'reason' => '已退回设备不能调整成本',
            ];
        }

        return [
            'allowed' => true,
            'reason' => '',
        ];
    }

    public function adjust(int $deviceId, array $data): array
    {
        $adjustType = (string)($data['adjust_type'] ?? '');
        if (!isset(self::TYPE_LABELS[$adjustType])) {
            throw new CommonException('请选择正确的成本调整类型');
        }

        $amount = round((float)($data['adjust_amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw new CommonException('调整金额必须大于0');
        }

        $reason = trim((string)($data['reason'] ?? ''));
        if ($reason === '') {
            throw new CommonException('请填写成本调整原因');
        }

        $inventoryTipConfirmed = (int)($data['inventory_tip_confirmed'] ?? 0);
        if ($inventoryTipConfirmed !== 1) {
            throw new CommonException('请确认已知晓需要同步修改进销存软件成本');
        }

        $customerHandled = (int)($data['customer_handled'] ?? 0);
        $images = $this->normalizeImages($data['images'] ?? '');
        $now = time();

        Db::startTrans();
        try {
            $device = (new RecycleDevice())
                ->where([
                    ['id', '=', $deviceId],
                    ['site_id', '=', $this->site_id],
                ])
                ->lock(true)
                ->findOrEmpty();

            if ($device->isEmpty()) {
                throw new CommonException('设备不存在');
            }

            if ((int)$device->pay_status !== RecycleOrderDict::PAY_STATUS_PAID) {
                throw new CommonException('只有已打款设备才允许走成本调整');
            }

            if ((int)$device->status === RecycleOrderDict::DEVICE_STATUS_RETURNED) {
                throw new CommonException('已退回设备不能调整回收成本');
            }

            $beforeCost = round((float)$device->final_price, 2);
            $delta = $this->resolveDelta($adjustType, $amount, (string)($data['direction'] ?? 'decrease'));
            $afterCost = round($beforeCost + $delta, 2);

            if ($afterCost < 0) {
                throw new CommonException('调整后成本不能小于0');
            }

            $order = (new RecycleOrder())
                ->where([
                    ['id', '=', (int)$device->order_id],
                    ['site_id', '=', $this->site_id],
                ])
                ->findOrEmpty();

            $adjustNo = $this->makeAdjustNo($deviceId);
            $record = [
                'site_id' => $this->site_id,
                'adjust_no' => $adjustNo,
                'order_id' => (int)$device->order_id,
                'order_no' => (string)($order['order_no'] ?? ''),
                'device_id' => $deviceId,
                'device_imei' => (string)($device->imei ?? ''),
                'device_model' => (string)($device->model ?? ''),
                'member_id' => (int)($device->member_id ?: ($order['member_id'] ?? 0)),
                'adjust_type' => $adjustType,
                'adjust_type_name' => self::TYPE_LABELS[$adjustType],
                'before_cost' => $beforeCost,
                'adjust_amount' => $amount,
                'adjust_delta' => $delta,
                'after_cost' => $afterCost,
                'customer_amount' => $this->resolveCustomerAmount($adjustType, $amount),
                'customer_direction' => $this->resolveCustomerDirection($adjustType),
                'customer_handled' => $customerHandled,
                'reason' => $reason,
                'images' => $images,
                'inventory_sync_tip' => '请同步修改进销存软件中的该设备库存成本',
                'inventory_tip_confirmed' => 1,
                'operator_id' => $this->uid,
                'operator_name' => $this->username ?? '',
                'create_at' => $now,
            ];

            (new RecycleDeviceCostAdjustment())->save($record);

            $adjustCount = (int)(new RecycleDeviceCostAdjustment())
                ->where([
                    ['site_id', '=', $this->site_id],
                    ['device_id', '=', $deviceId],
                ])
                ->count();

            $device->save([
                'final_price' => $afterCost,
                'cost_adjust_amount' => round((float)$device->cost_adjust_amount + $delta, 2),
                'cost_adjust_count' => $adjustCount,
                'last_cost_adjust_time' => $now,
                'last_cost_adjust_no' => $adjustNo,
                'update_at' => $now,
            ]);

            (new RecycleDeviceLog())->save([
                'site_id' => $this->site_id,
                'device_id' => $deviceId,
                'order_id' => (int)$device->order_id,
                'operator_id' => $this->uid,
                'operator_name' => $this->username ?? '',
                'operation_type' => 'cost_adjust',
                'action' => 'cost_adjust',
                'old_status' => (int)$device->status,
                'new_status' => (int)$device->status,
                'remark' => sprintf(
                    '成本调整：%s，成本 %.2f → %.2f，调整金额 %.2f。原因：%s。提醒：请同步修改进销存软件成本。',
                    self::TYPE_LABELS[$adjustType],
                    $beforeCost,
                    $afterCost,
                    $amount,
                    $reason
                ),
                'create_at' => $now,
            ]);

            Db::commit();

            $record['cost_adjust_count'] = $adjustCount;
            return $record;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    private function resolveDelta(string $type, float $amount, string $direction): float
    {
        if ($type === self::TYPE_REFUND_FROM_CUSTOMER) {
            return -$amount;
        }
        if ($type === self::TYPE_PAY_TO_CUSTOMER) {
            return $amount;
        }
        return $direction === 'increase' ? $amount : -$amount;
    }

    private function resolveCustomerAmount(string $type, float $amount): float
    {
        return $type === self::TYPE_COST_CORRECTION ? 0 : $amount;
    }

    private function resolveCustomerDirection(string $type): string
    {
        if ($type === self::TYPE_REFUND_FROM_CUSTOMER) {
            return 'customer_refund';
        }
        if ($type === self::TYPE_PAY_TO_CUSTOMER) {
            return 'merchant_pay';
        }
        return 'none';
    }

    private function normalizeImages($images): string
    {
        if (is_array($images)) {
            return implode(',', array_filter(array_map('strval', $images)));
        }
        return trim((string)$images);
    }

    private function makeAdjustNo(int $deviceId): string
    {
        return 'CA' . date('YmdHis') . $deviceId . random_int(100, 999);
    }
}
