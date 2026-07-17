<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpCapitalAccount;

/** 向外部插件暴露可选资金账户的最小只读视图，不泄露余额和账号。 */
class ErpCapitalAccountOptionService extends ErpExternalContractService
{
    public const EVENT_NAME = 'ErpCapitalAccountOptionsRequested';
    public const CONTRACT_NAME = 'erp.capital_account.options_requested.v1';
    public const CONTRACT_VERSION = 1;

    public function consume(array $event): array
    {
        $envelope = $this->normalizeEnvelope($event, self::CONTRACT_NAME, self::CONTRACT_VERSION);
        return [
            'consumer' => 'hsx_erp',
            'event_id' => (string)$envelope['event_id'],
            'status' => 'processed',
            'list' => $this->activeOptions((int)$envelope['site_id']),
        ];
    }

    protected function activeOptions(int $siteId): array
    {
        $rows = ErpCapitalAccount::where([
            ['site_id', '=', $siteId],
            ['status', '=', 1],
        ])->field('id,account_name,account_type,is_default,sort')
            ->order('is_default desc,sort asc,id asc')
            ->select()
            ->toArray();
        return array_map(static function (array $row): array {
            $type = (string)($row['account_type'] ?? 'other');
            return [
                'id' => (int)$row['id'],
                'name' => (string)$row['account_name'],
                'type' => $type,
                'type_name' => ErpCapitalAccountService::TYPE_MAP[$type] ?? '其他',
                'is_default' => (int)($row['is_default'] ?? 0),
            ];
        }, $rows);
    }
}
