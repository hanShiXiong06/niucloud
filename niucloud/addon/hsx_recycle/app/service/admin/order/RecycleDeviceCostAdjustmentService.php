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

        // 自动同步到 ERP(进销存)成本的开关:
        //  - 开启(默认): 调整后自动发事件, 由 ERP 承接同步资产成本(未装 ERP 时事件无人应答, 自动空转, 无副作用);
        //  - 关闭: 仅本地调整, 不发同步事件, 并在记录里提醒需手动同步进销存。
        // 兼容旧前端: 老版本传 inventory_tip_confirmed(必勾确认), 现作为同步开关解读。
        $autoSyncErp = array_key_exists('auto_sync_erp', $data)
            ? (int)$data['auto_sync_erp']
            : (int)($data['inventory_tip_confirmed'] ?? 1);
        $autoSyncErp = $autoSyncErp === 1 ? 1 : 0;

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
                'inventory_sync_tip' => $autoSyncErp === 1 ? '已自动同步至 ERP(进销存)成本' : '请手动同步修改进销存软件中的该设备库存成本',
                'inventory_tip_confirmed' => $autoSyncErp,
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
                    '成本调整：%s，成本 %.2f → %.2f，调整金额 %.2f。原因：%s。%s',
                    self::TYPE_LABELS[$adjustType],
                    $beforeCost,
                    $afterCost,
                    $amount,
                    $reason,
                    $autoSyncErp === 1 ? '已自动同步至 ERP(进销存)成本。' : '提醒：请手动同步修改进销存软件成本。'
                ),
                'create_at' => $now,
            ]);

            Db::commit();

            // 仅当"自动同步"开关开启时, 通知 ERP 同步资产库存成本(按增量)。
            // 关闭时只本地调整不发事件; 未装 ERP 时即便开启也会因无监听者而空转。故障隔离: 失败只记日志。
            if ($autoSyncErp === 1) {
              try {
                event('RecycleDeviceCostAdjusted', [
                    'site_id'          => (int)$this->site_id,
                    'source_device_id' => $deviceId,
                    'before_cost'      => $beforeCost,
                    'after_cost'       => $afterCost,
                    'delta'            => $delta,
                    'reason'           => $reason,
                    'operator'         => (string)($this->username ?? ''),
                    'occurred_at'      => $now,
                ]);
              } catch (\Throwable $ev) {
                \think\facade\Log::warning('[recycle] 发成本调整事件给ERP失败: ' . $ev->getMessage());
              }
            }

            $record['cost_adjust_count'] = $adjustCount;
            return $record;
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }

    /**
     * 承接"ERP 侧成本调整"：按增量同步回收设备成本(final_price / cost_adjust_amount) + 落一条调整记录 + 设备日志。
     * 只改数据、不再回发 RecycleDeviceCostAdjusted(只有用户在回收主动调成本才发那个事件)，防死循环。
     * 不按 site_id 过滤设备(按 PK 精确命中)，兼容历史 site_id 不一致数据。
     */
    public function applyExternalDelta(int $deviceId, float $delta, string $reason, string $operator): void
    {
        $delta = round($delta, 2);
        if ($deviceId <= 0 || abs($delta) < 0.001) {
            return;
        }
        $now = time();
        Db::startTrans();
        try {
            $device = (new RecycleDevice())->where([['id', '=', $deviceId]])->lock(true)->findOrEmpty();
            if ($device->isEmpty()) {
                Db::rollback();
                return;
            }
            $beforeCost = round((float)$device->final_price, 2);
            $afterCost = round($beforeCost + $delta, 2);
            if ($afterCost < 0) {
                $afterCost = 0.00;
                $delta = round($afterCost - $beforeCost, 2);
            }

            $order = (new RecycleOrder())->where([['id', '=', (int)$device->order_id]])->findOrEmpty();
            $adjustNo = $this->makeAdjustNo($deviceId);
            $opName = $operator !== '' ? $operator : 'ERP同步';

            (new RecycleDeviceCostAdjustment())->save([
                'site_id'              => (int)$device->site_id,
                'adjust_no'            => $adjustNo,
                'order_id'             => (int)$device->order_id,
                'order_no'             => (string)($order['order_no'] ?? ''),
                'device_id'            => $deviceId,
                'device_imei'          => (string)($device->imei ?? ''),
                'device_model'         => (string)($device->model ?? ''),
                'member_id'            => (int)($device->member_id ?: ($order['member_id'] ?? 0)),
                'adjust_type'          => self::TYPE_COST_CORRECTION,
                'adjust_type_name'     => 'ERP同步成本调整',
                'before_cost'          => $beforeCost,
                'adjust_amount'        => abs($delta),
                'adjust_delta'         => $delta,
                'after_cost'           => $afterCost,
                'customer_amount'      => 0,
                'customer_direction'   => '',
                'customer_handled'     => 1,
                'reason'               => 'ERP 侧调成本同步' . ($reason !== '' ? '：' . $reason : ''),
                'images'               => '',
                'inventory_sync_tip'   => '',
                'inventory_tip_confirmed' => 1,
                'operator_id'          => 0,
                'operator_name'        => $opName,
                'create_at'            => $now,
            ]);

            $adjustCount = (int)(new RecycleDeviceCostAdjustment())
                ->where([['device_id', '=', $deviceId]])
                ->count();

            $device->save([
                'final_price'          => $afterCost,
                'cost_adjust_amount'   => round((float)$device->cost_adjust_amount + $delta, 2),
                'cost_adjust_count'    => $adjustCount,
                'last_cost_adjust_time' => $now,
                'last_cost_adjust_no'  => $adjustNo,
                'update_at'            => $now,
            ]);

            (new RecycleDeviceLog())->save([
                'site_id'        => (int)$device->site_id,
                'device_id'      => $deviceId,
                'order_id'       => (int)$device->order_id,
                'operator_id'    => 0,
                'operator_name'  => $opName,
                'operation_type' => 'erp_cost_sync',
                'action'         => 'erp_cost_sync',
                'old_status'     => (int)$device->status,
                'new_status'     => (int)$device->status,
                'remark'         => sprintf('ERP 调成本同步：成本 %.2f → %.2f，增量 %.2f。%s', $beforeCost, $afterCost, $delta, $reason !== '' ? '原因：' . $reason : ''),
                'create_at'      => $now,
            ]);

            Db::commit();
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
