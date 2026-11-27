<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\listener\notice;
use addon\hsx_lead\app\service\notice\{NoticeEventCatalog,FinancialNoticeService};
final class AccountNoticeData
{
    public function handle(array $params): ?array
    {
        if (!isset(NoticeEventCatalog::definitions()[$params['key'] ?? '']))return null;
        $data = $params['data'] ?? null;
        if (!is_array($data) || !isset($data['financial_task_id'],$data['financial_attempt_id'],$data['lease_token']))return null;
        return FinancialNoticeService::templateData((int) ($params['site_id'] ?? 0), (int) $data['financial_task_id'], (int) $data['financial_attempt_id'], (string) ($params['channel'] ?? ''), (string) $params['key'], (string) $data['lease_token']);
    }
}
