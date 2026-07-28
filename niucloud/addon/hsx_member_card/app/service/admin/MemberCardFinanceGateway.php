<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\support\MemberCardHookResult;
use core\base\BaseAdminService;
use core\exception\CommonException;

/** 会员卡插件访问 ERP 财务的唯一网关；禁止在其它服务中直接引用 ERP 类或表。 */
final class MemberCardFinanceGateway extends BaseAdminService
{
    private ?array $erpAccountResponse = null;
    private bool $erpAccountResponseLoaded = false;

    public function usesErp(): bool
    {
        return $this->erpAccountResponse() !== null;
    }

    public function capitalAccountOptions(): array
    {
        $result = $this->erpAccountResponse();
        if ($result !== null) return array_values((array)($result['list'] ?? []));

        $config = (new \addon\hsx_member_card\app\service\core\MemberCardConfigService())->get((int)$this->site_id);
        $typeNames = ['wechat' => '微信', 'alipay' => '支付宝', 'bank' => '银行卡', 'cash' => '现金', 'other' => '其他'];
        $list = array_values(array_filter((array)($config['local_capital_accounts'] ?? []), static fn(array $row): bool => (int)($row['status'] ?? 0) === 1));
        return array_map(static function (array $row) use ($typeNames): array {
            $type = (string)($row['type'] ?? 'other');
            return [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'type' => $type,
                'type_name' => $typeNames[$type] ?? '其他',
                'is_default' => (int)($row['is_default'] ?? 0),
                'provider' => 'local',
            ];
        }, $list);
    }

    private function erpAccountResponse(): ?array
    {
        if ($this->erpAccountResponseLoaded) return $this->erpAccountResponse;
        $this->erpAccountResponseLoaded = true;
        $this->erpAccountResponse = MemberCardHookResult::firstOrNull(event('ErpCapitalAccountOptionsRequested', [
            'event_name' => 'erp.capital_account.options_requested.v1',
            'event_version' => 1,
            'event_id' => 'hsx_member_card:account-options:' . $this->site_id . ':' . bin2hex(random_bytes(8)),
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'occurred_at' => time(),
        ]));
        return $this->erpAccountResponse;
    }

    public function requireCapitalAccount(int $accountId): array
    {
        foreach ($this->capitalAccountOptions() as $row) {
            if ((int)($row['id'] ?? 0) === $accountId) return $row;
        }
        throw new CommonException($this->usesErp() ? '所选ERP资金账户不存在或已停用' : '所选会员卡收款账户不存在或已停用');
    }

    public function createSaleFact(array $order): array
    {
        return MemberCardHookResult::first(event('ErpFinanceFactRequested', [
            'event_name' => 'finance.fact.requested.v1',
            'event_version' => 1,
            'event_id' => $this->saleFactEventId((int)$order['id']),
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'source_plugin_name' => '会员服务卡',
            'source_name' => '会员卡开卡订单',
            'source_type' => 'hsx_member_card.card_order',
            'order_no' => (string)$order['order_no'],
            'line_id' => 'order:' . (int)$order['id'],
            'category_key' => 'hsx_member_card.card_sale',
            'party_id' => (int)$order['party_id'],
            'party_name' => (string)$order['party_name'],
            'asset_id' => 0,
            'amount' => (string)$order['order_amount'],
            'channel' => ['code' => 'erp_store', 'name' => '门店开卡'],
            'settlement' => [
                'mode' => (string)$order['settlement_mode'],
                'name' => (string)$order['settlement_mode'] === 'immediate' ? '现结' : '挂账',
            ],
            'occurred_at' => (int)$order['create_at'],
            'operator' => ['id' => (int)$order['issuer_uid'], 'name' => (string)$order['issuer_name']],
            'remark' => (string)$order['product_name'] . ((string)$order['remark'] !== '' ? '；' . (string)$order['remark'] : ''),
        ]), '财务事实创建');
    }

    public function settleReceivable(array $order, array $financeLink, array $data): array
    {
        return MemberCardHookResult::first(event('ErpFinanceSettlementRequested', [
            'event_name' => 'erp.finance.settlement_requested.v1',
            'event_version' => 1,
            'event_id' => $this->saleSettlementEventId((int)$order['id']),
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'target_type' => 'receivable',
            'target_id' => (int)$financeLink['target_id'],
            'amount' => (string)$order['order_amount'],
            'capital_account_id' => (int)$data['capital_account_id'],
            'voucher_urls' => (array)($data['voucher_urls'] ?? []),
            'remark' => '会员卡现场收款：' . (string)$order['order_no'],
            'occurred_at' => time(),
        ]), '实际收款');
    }

    public function voidFact(array $order, array $financeLink, string $reason): array
    {
        return MemberCardHookResult::first(event('ErpFinanceFactVoidRequested', [
            'event_name' => 'erp.finance.fact_void_requested.v1',
            'event_version' => 1,
            'event_id' => 'hsx_member_card:order:' . (int)$order['id'] . ':sale:void',
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'target_type' => (string)$financeLink['target_type'],
            'target_id' => (int)$financeLink['target_id'],
            'reason' => $reason,
            'occurred_at' => time(),
        ]), '未结财务事实作废');
    }

    public function createRefundFact(array $refund): array
    {
        return MemberCardHookResult::first(event('ErpFinanceFactRequested', [
            'event_name' => 'finance.fact.requested.v1',
            'event_version' => 1,
            'event_id' => $this->refundFactEventId((int)$refund['id']),
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'source_plugin_name' => '会员服务卡',
            'source_name' => '会员卡整卡退款',
            'source_type' => 'hsx_member_card.card_refund',
            'order_no' => (string)$refund['refund_no'],
            'line_id' => 'refund:' . (int)$refund['id'],
            'category_key' => 'hsx_member_card.card_refund',
            'party_id' => (int)$refund['party_id'],
            'party_name' => (string)$refund['party_name'],
            'asset_id' => 0,
            'amount' => (string)$refund['refund_amount'],
            'channel' => ['code' => 'erp_store', 'name' => '门店会员卡退款'],
            'settlement' => [
                'mode' => (string)$refund['refund_mode'],
                'name' => (string)$refund['refund_mode'] === 'immediate' ? '现退' : '财务退款',
            ],
            'occurred_at' => (int)$refund['create_at'],
            'operator' => ['id' => (int)$refund['apply_uid'], 'name' => (string)$refund['apply_name']],
            'remark' => '原开卡单 ' . (string)$refund['order_no'] . '；' . (string)$refund['reason'],
        ]), '退款财务事实创建');
    }

    public function settlePayable(array $refund, array $financeLink, array $data): array
    {
        return MemberCardHookResult::first(event('ErpFinanceSettlementRequested', [
            'event_name' => 'erp.finance.settlement_requested.v1',
            'event_version' => 1,
            'event_id' => $this->refundSettlementEventId((int)$refund['id']),
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'target_type' => 'payable',
            'target_id' => (int)$financeLink['target_id'],
            'amount' => (string)$refund['refund_amount'],
            'capital_account_id' => (int)$data['capital_account_id'],
            'voucher_urls' => (array)($data['voucher_urls'] ?? []),
            'remark' => '会员卡现场退款：' . (string)$refund['refund_no'],
            'occurred_at' => time(),
        ]), '实际退款出账');
    }

    public function saleFactEventId(int $orderId): string
    {
        return 'hsx_member_card:order:' . $orderId . ':sale';
    }

    public function saleSettlementEventId(int $orderId): string
    {
        return 'hsx_member_card:order:' . $orderId . ':sale:receipt';
    }

    public function refundFactEventId(int $refundId): string
    {
        return 'hsx_member_card:refund:' . $refundId . ':payable';
    }

    public function refundSettlementEventId(int $refundId): string
    {
        return 'hsx_member_card:refund:' . $refundId . ':payment';
    }
}
