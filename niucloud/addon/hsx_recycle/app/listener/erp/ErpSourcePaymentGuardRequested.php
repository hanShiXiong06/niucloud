<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\listener\erp;

use addon\hsx_recycle\app\service\core\recycle_order\RecyclePaymentOwnershipService;
use core\exception\CommonException;
use think\facade\Db;

/** ERP 原生付款也核对来源旧账，不能只拦截回收页面入口。调用方必须在结算事务内调用。 */
class ErpSourcePaymentGuardRequested
{
    public function handle($event): array
    {
        if (!is_array($event) || ($event['source_plugin'] ?? '') !== 'hsx_recycle'
            || ($event['event_name'] ?? '') !== 'erp.source_payment.guard_requested.v1'
            || ($event['event_version'] ?? 0) !== 1 || (int)($event['site_id'] ?? 0) <= 0
            || !is_array($event['source_device_ids'] ?? null) || $event['source_device_ids'] === []) {
            throw new CommonException('回收来源付款核对参数不完整，未执行结算');
        }
        $ids = RecyclePaymentOwnershipService::deviceIds($event['source_device_ids']);
        $purpose = $event['purpose'] ?? 'payment';
        if ($purpose === 'historical_paid_reconciliation') {
            return $this->reconcileHistoricalPaid((int)$event['site_id'], $ids, $event);
        }
        if ($purpose !== 'payment') {
            throw new CommonException('回收来源付款核对用途不正确，未执行结算');
        }
        $scope = $this->ownershipService()->claim((int)$event['site_id'], 0, $ids, 'self_erp');
        foreach ($scope['devices'] as $device) {
            if ((int)$device['pay_status'] === 1) {
                throw new CommonException('回收来源设备已有付款完成记录，请先核对旧付款与 ERP 应付，不可重复结算');
            }
        }
        return ['consumer' => 'hsx_recycle', 'status' => 'processed', 'source_device_ids' => $ids];
    }

    /** 补录历史付款事实，不执行新付款，也不将原本的本地付款责任迁移给 ERP。 */
    protected function reconcileHistoricalPaid(int $siteId, array $ids, array $event): array
    {
        if (count($event['source_device_ids']) !== count($ids)) {
            throw new CommonException('历史付款核销设备不能重复，未执行结算');
        }
        $amounts = ErpHistoricalPaidReconciliation::requestedAmounts($ids, $event['device_amounts'] ?? null);
        $priorPaid = ErpHistoricalPaidReconciliation::priorPaidAmounts($ids, $event['prior_erp_paid_amounts'] ?? null);
        // 必须沿用财务事务，不能在这里自行提交、提前释放来源设备锁。
        $this->assertHistoricalTransaction();
        $devices = $this->lockHistoricalDevices($siteId, $ids);
        $responses = $this->requestHistoricalOwnership($siteId, $ids);
        ErpHistoricalPaidReconciliation::assertAllowed($siteId, $amounts, $devices, $responses, $priorPaid);
        return ['consumer' => 'hsx_recycle', 'status' => 'processed', 'source_device_ids' => $ids,
            'purpose' => 'historical_paid_reconciliation'];
    }

    protected function assertHistoricalTransaction(): void
    {
        $pdo = Db::connect()->getPdo();
        if (!$pdo || !$pdo->inTransaction()) {
            throw new CommonException('历史付款核销必须在财务事务内执行');
        }
    }

    protected function lockHistoricalDevices(int $siteId, array $ids): array
    {
        return Db::name('recycle_device')->where('site_id', $siteId)->whereIn('id', $ids)
            ->field('id,site_id,order_id,pay_status,pay_amount,pay_time,final_price')
            ->order('id')->lock(true)->select()->toArray();
    }

    protected function requestHistoricalOwnership(int $siteId, array $ids): array
    {
        // ERP 应付已由调用方锁定；这里必须是既有只读查询，不能反向锁 ERP 表。
        return (array)event('RecycleErpPaymentOwnershipRequested', ['site_id' => $siteId, 'device_ids' => $ids]);
    }

    protected function ownershipService(): RecyclePaymentOwnershipService
    {
        return new RecyclePaymentOwnershipService();
    }
}
