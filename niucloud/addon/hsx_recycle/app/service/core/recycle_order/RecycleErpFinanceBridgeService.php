<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\recycle_order;

use core\exception\CommonException;

/** 回收插件访问ERP财务的唯一事件桥，禁止再直读ERP表或重复记资金流水。 */
class RecycleErpFinanceBridgeService
{
    public function capitalAccountOptions(int $siteId): array
    {
        if (!(new RecycleErpCapabilityService())->isEnabled($siteId)) return [];
        $eventId = $this->eventId('capital-options');
        $responses = (array)event('ErpCapitalAccountOptionsRequested', [
            'event_id' => $eventId,
            'event_name' => 'erp.capital_account.options_requested.v1',
            'event_version' => 1,
            'site_id' => $siteId,
            'source_plugin' => 'hsx_recycle',
            'occurred_at' => time(),
        ]);
        $response = $this->erpResponse($responses, 'ERP资金账户服务未响应');
        return array_values((array)($response['list'] ?? []));
    }

    public function settleSourceDevices(int $siteId, array $deviceIds, array $data): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if ($deviceIds === []) throw new CommonException('请选择需要付款的设备');
        if ((int)($data['capital_account_id'] ?? 0) <= 0) throw new CommonException('请选择ERP付款账户');
        $requestId = trim((string)($data['request_id'] ?? '')) ?: $this->eventId('device-payment');
        $responses = (array)event('ErpSourcePayableSettlementRequested', [
            'event_id' => $requestId,
            'event_name' => 'erp.source_payable.settlement_requested.v1',
            'event_version' => 1,
            'site_id' => $siteId,
            'source_plugin' => 'hsx_recycle',
            'occurred_at' => time(),
            'source_device_ids' => $deviceIds,
            'capital_account_id' => (int)$data['capital_account_id'],
            'voucher_urls' => $data['voucher_urls'] ?? $data['payment_images'] ?? [],
            'remark' => trim((string)($data['remark'] ?? $data['pay_remark'] ?? '')),
        ]);
        return $this->erpResponse($responses, 'ERP付款服务未响应，请勿在回收插件重复打款');
    }

    private function erpResponse(array $responses, string $message): array
    {
        foreach ($responses as $response) {
            if (!is_array($response) || (string)($response['consumer'] ?? '') !== 'hsx_erp') continue;
            if (!empty($response['error']) || (string)($response['status'] ?? '') === 'failed') {
                throw new CommonException((string)($response['message'] ?? $message));
            }
            return $response;
        }
        throw new CommonException($message);
    }

    private function eventId(string $scene): string
    {
        return 'recycle-' . $scene . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
    }
}
