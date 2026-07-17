<?php
declare(strict_types=1);

namespace addon\hsx_member_card\app\service\admin;

use addon\hsx_member_card\app\service\core\MemberCardConfigService;
use core\base\BaseAdminService;

final class MemberCardConfigAdminService extends BaseAdminService
{
    public function info(): array
    {
        $config = (new MemberCardConfigService())->get((int)$this->site_id);
        $config['capital_account_options'] = $this->capitalAccountOptions();
        return $config;
    }

    public function save(array $data): array
    {
        $accountId = max(0, (int)($data['default_capital_account_id'] ?? 0));
        if ($accountId > 0) {
            $ids = array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $this->capitalAccountOptions());
            if (!in_array($accountId, $ids, true)) throw new \core\exception\CommonException('默认资金账户不存在或已停用');
        }
        (new MemberCardConfigService())->save((int)$this->site_id, $data);
        return $this->info();
    }

    private function capitalAccountOptions(): array
    {
        $result = event('ErpCapitalAccountOptionsRequested', [
            'event_name' => 'erp.capital_account.options_requested.v1',
            'event_version' => 1,
            'event_id' => 'hsx_member_card:account-options:' . $this->site_id . ':' . bin2hex(random_bytes(8)),
            'site_id' => (int)$this->site_id,
            'source_plugin' => 'hsx_member_card',
            'occurred_at' => time(),
        ]);
        foreach ((array)$result as $row) {
            if (is_array($row) && isset($row['list']) && is_array($row['list'])) return $row['list'];
        }
        return [];
    }
}
