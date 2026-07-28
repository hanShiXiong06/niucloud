<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpCapitalAccount;
use addon\hsx_erp\app\model\ErpSettlement;
use addon\hsx_erp\app\support\ErpIdempotency;

/**
 * 商城等外部销售渠道的财务公共能力。
 *
 * “微信支付待结算”是资金清算账户，不是伪造的供应商或往来主体：
 * 客户付款时增加，渠道手续费与退款时减少，后续提现属于账户间划转。
 */
abstract class ErpExternalSaleAccountingService extends ErpExternalContractService
{
    protected function clearingAccount(int $siteId, int $now): ErpCapitalAccount
    {
        return ErpCapitalAccountService::ensureWechatClearingAccount($siteId);
    }

    protected function settlement(
        array $payload,
        ErpCapitalAccount $account,
        string $type,
        float $amount,
        string $direction,
        string $remark
    ): ErpSettlement {
        return ErpSettlement::create([
            'site_id' => (int)$payload['site_id'],
            'request_id' => ErpIdempotency::nullable(ErpIdempotency::child((string)$payload['event_id'], 'settlement')),
            'settlement_no' => ErpLedgerService::makeNo('ST'),
            'party_id' => 0,
            'party_name' => (string)($payload['party_name'] ?? '商城客户'),
            'settlement_type' => $type,
            'amount' => round($amount, 2),
            'cash_direction' => $direction,
            'capital_account_id' => (int)$account->id,
            'capital_account_name' => (string)$account->account_name,
            'status' => 'confirmed',
            'operator_uid' => (int)($payload['operator_id'] ?? 0),
            'operator_name' => trim((string)($payload['operator_name'] ?? '')) ?: '商城自动入账',
            'confirmed_at' => (int)$payload['occurred_at'],
            'voucher_urls' => '',
            'remark' => mb_substr($remark, 0, 255),
            'create_at' => time(),
        ]);
    }

    protected function ledger(int $siteId): ErpLedgerService
    {
        return ErpLedgerService::forSite($siteId, 0, '商城自动入账');
    }
}
