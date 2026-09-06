<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use addon\hsx_recycle\app\support\RecycleErpOwnershipPolicy;
use core\exception\CommonException;
use think\facade\Db;
use think\facade\Log;

/**
 * 每台设备只有一个付款责任方。使用现有设备日志保存归属，不增加业务表或字段。
 * 配置决定新业务，实际 ERP 记录和已冻结归属决定旧业务；查询失败绝不开放本地付款。
 */
class RecyclePaymentOwnershipService
{
    public const LOCAL_LOG = 'recycle_payment_owner';
    public const ERP_LOG = 'erp_payment_owner';

    public static function deviceIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if ((!is_int($id) && !(is_string($id) && preg_match('/^[1-9][0-9]*$/D', $id)))
                || (int)$id <= 0 || (string)(int)$id !== (string)$id) {
                throw new CommonException('设备编号不正确，请刷新后重新选择，未执行付款');
            }
            $out[(int)$id] = (int)$id;
        }
        sort($out, SORT_NUMERIC);
        return array_values($out);
    }

    public function inspect(int $siteId, int $orderId = 0, array $deviceIds = [], bool $lock = false): array
    {
        if ($siteId <= 0 || ($orderId <= 0 && $deviceIds === [])) {
            throw new CommonException('缺少具体订单或设备，无法确认付款归属');
        }
        $deviceIds = self::deviceIds($deviceIds);
        if ($orderId > 0 && !Db::name('recycle_order')->where('site_id', $siteId)->where('id', $orderId)->find()) {
            throw new CommonException('订单不存在或不属于当前站点');
        }
        $query = Db::name('recycle_device')->where('site_id', $siteId);
        if ($orderId > 0) $query->where('order_id', $orderId);
        if ($deviceIds !== []) $query->whereIn('id', $deviceIds);
        $rows = $query->order('id')->lock($lock)->select()->toArray();
        if ($rows === [] || ($deviceIds !== [] && count($rows) !== count($deviceIds))) {
            throw new CommonException('部分设备不存在或不属于当前订单，未执行付款');
        }
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
        $config = (new RecycleErpIntegrationService())->history($siteId);
        $logs = Db::name('recycle_device_log')->where('site_id', $siteId)->whereIn('device_id', $ids)
            ->whereIn('operation_type', [self::LOCAL_LOG, self::ERP_LOG])->order('id')->lock($lock)->select()->toArray();
        $recorded = [];
        foreach ($logs as $log) {
            $id = (int)$log['device_id'];
            $owner = $log['operation_type'] === self::ERP_LOG ? 'self_erp' : 'local';
            // 两种相反责任记录不是“最新一条覆盖”，必须人工核对。
            $recorded[$id] = isset($recorded[$id]) && $recorded[$id] !== $owner ? 'unknown' : $owner;
        }
        $lookupAvailable = true;
        $evidence = [];
        if ($config['installed']) {
            try {
                $responses = (array)event('RecycleErpPaymentOwnershipRequested', ['site_id' => $siteId, 'device_ids' => $ids]);
                $matched = false;
                foreach ($responses as $response) {
                    if (!is_array($response) || ($response['consumer'] ?? '') !== 'hsx_erp') continue;
                    if ($matched || !empty($response['error']) || ($response['status'] ?? '') !== 'processed' || !is_array($response['devices'] ?? null)) {
                        throw new CommonException('ERP 付款归属查询未完整确认');
                    }
                    $matched = true;
                    $evidence = $response['devices'];
                }
                if (!$matched) throw new CommonException('ERP 付款归属服务未响应');
                foreach ($ids as $id) {
                    $entry = $evidence[$id] ?? null;
                    if (!is_array($entry) || !is_bool($entry['has_asset'] ?? null) || !is_bool($entry['has_payable'] ?? null)
                        || !is_bool($entry['ambiguous'] ?? null) || !is_array($entry['asset_ids'] ?? null)
                        || !is_numeric($entry['paid_amount'] ?? null) || !is_numeric($entry['remaining_amount'] ?? null)
                        || !is_finite((float)$entry['paid_amount']) || !is_finite((float)$entry['remaining_amount'])
                        || (float)$entry['paid_amount'] < 0 || (float)$entry['remaining_amount'] < 0) {
                        throw new CommonException('ERP 付款归属查询缺少部分设备');
                    }
                    $assetIds = self::deviceIds($entry['asset_ids']);
                    if (count($assetIds) !== count($entry['asset_ids']) || $entry['has_asset'] !== ($assetIds !== [])
                        || (!$entry['has_payable'] && ((float)$entry['paid_amount'] > 0 || (float)$entry['remaining_amount'] > 0))) {
                        throw new CommonException('ERP 付款归属回执内容不一致');
                    }
                }
            } catch (\Throwable $e) {
                $lookupAvailable = false;
                Log::error('回收付款归属核对失败，已暂停付款', ['site_id' => $siteId, 'device_ids' => $ids, 'error' => $e->getMessage()]);
            }
        }
        $devices = [];
        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $fact = (array)($evidence[$id] ?? []);
            if ((int)($row['downstream_erp_asset_id'] ?? 0) > 0) $fact['has_erp_record'] = true;
            // 历史“未付”状态却已有付款金额/时间，以及回收已付而 ERP 仍有应付，均先核账。
            $paid = (int)($row['pay_status'] ?? 0) === 1;
            if ((!$paid && ((float)($row['pay_amount'] ?? 0) > 0 || (int)($row['pay_time'] ?? 0) > 0)
                    && ((float)($row['pay_amount'] ?? 0) <= 0 || (float)($fact['paid_amount'] ?? 0) <= 0
                        || abs(round((float)$row['pay_amount'], 2) - round((float)$fact['paid_amount'], 2)) > 0.0001))
                || ($paid && (float)($fact['remaining_amount'] ?? 0) > 0)) {
                $fact['ambiguous'] = true;
            }
            $owner = RecycleErpOwnershipPolicy::resolve($config, (int)($row['create_at'] ?? 0), $recorded[$id] ?? '', $fact, $lookupAvailable);
            $devices[$id] = ['device_id' => $id, 'order_id' => (int)$row['order_id'], 'owner' => $owner,
                'recorded_owner' => $recorded[$id] ?? '', 'status' => (int)($row['status'] ?? 0),
                'pay_status' => (int)($row['pay_status'] ?? 0), 'pay_amount' => (float)($row['pay_amount'] ?? 0),
                'pay_time' => (int)($row['pay_time'] ?? 0), 'erp' => $fact];
        }
        $owners = array_values(array_unique(array_column($devices, 'owner')));
        $owner = in_array('unknown', $owners, true) ? 'unknown' : (count($owners) === 1 ? $owners[0] : 'mixed');
        return ['owner' => $owner, 'devices' => $devices, 'installed' => (bool)$config['installed'],
            'local_allowed' => $owner === 'local', 'message' => self::message($owner, (bool)$config['installed'])];
    }

    /** 在付款事务内部调用；入库派发则先提交责任日志，再请求 ERP，失败也不释放责任。 */
    public function claim(int $siteId, int $orderId, array $deviceIds, string $expectedOwner): array
    {
        if (!in_array($expectedOwner, ['local', 'self_erp'], true)) throw new CommonException('付款责任方不正确');
        return Db::transaction(function () use ($siteId, $orderId, $deviceIds, $expectedOwner): array {
            $result = $this->inspect($siteId, $orderId, $deviceIds, true);
            if ($result['owner'] !== $expectedOwner || ($expectedOwner === 'self_erp' && !$result['installed'])) {
                throw new CommonException($result['message']);
            }
            foreach ($result['devices'] as $device) {
                if ($device['recorded_owner'] !== '') continue;
                Db::name('recycle_device_log')->insert([
                    'site_id' => $siteId, 'device_id' => $device['device_id'], 'order_id' => $device['order_id'],
                    'operator_id' => 0, 'operator_name' => '系统',
                    'operation_type' => $expectedOwner === 'self_erp' ? self::ERP_LOG : self::LOCAL_LOG,
                    'action' => '付款归属确认', 'old_status' => $device['status'], 'new_status' => $device['status'],
                    'remark' => $expectedOwner === 'self_erp'
                        ? '该设备由 ERP 负责入库与结算；关闭联动也不会改为回收本地付款。'
                        : '该设备由回收系统独立负责付款；开启 ERP 不会自动转移此设备的付款责任。',
                    'create_at' => time(),
                ]);
            }
            return $result;
        });
    }

    public static function message(string $owner, bool $installed = true): string
    {
        return match ($owner) {
            'local' => '该设备由回收系统负责付款。请核对实际付款结果，勿重复操作。',
            'self_erp' => $installed
                ? '该设备由 ERP 负责结算，请到“二手机 ERP - 应付款”处理；关闭联动不会转移旧设备的付款责任。'
                : '该设备已有 ERP 付款责任，但 ERP 当前不可用。请恢复 ERP 并核对旧账，不可在回收中重复付款。',
            'mixed' => '该订单含回收与 ERP 两种付款责任，不能整单付款。请按设备分别核对并在对应系统处理。',
            default => '付款归属尚未核对清楚，已暂停付款。请检查 ERP 服务及设备关联、既有付款记录后重试，切勿两边重复付款。',
        };
    }
}
