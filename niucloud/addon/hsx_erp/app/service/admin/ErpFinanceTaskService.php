<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpPayable;
use addon\hsx_erp\app\model\ErpReceivable;
use core\base\BaseAdminService;
use think\facade\Log;

/** ERP 财务待办责任人。账款是业务事实，企业微信只消费分配事件。 */
class ErpFinanceTaskService extends BaseAdminService
{
    public static function forSite(int $siteId, int $operatorUid = 0, string $operatorName = '系统自动分配'): self
    {
        $service = new self();
        $service->site_id = $siteId;
        $service->uid = $operatorUid;
        $service->username = $operatorName;
        return $service;
    }

    public function syncPayable(int $id): bool
    {
        return $this->sync(ErpListingTaskService::TASK_PAYABLE, $id);
    }

    public function syncReceivable(int $id): bool
    {
        return $this->sync(ErpListingTaskService::TASK_RECEIVABLE, $id);
    }

    public function sync(string $stage, int $id): bool
    {
        $modelClass = $stage === ErpListingTaskService::TASK_RECEIVABLE ? ErpReceivable::class : ErpPayable::class;
        $row = $modelClass::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($row->isEmpty() || !$this->isOpen($row->toArray())) return false;
        if ((int)($row->task_assignee_uid ?? 0) > 0) return true;

        $assignment = ErpListingTaskService::forSite($this->site_id, (int)$this->uid, (string)$this->username);
        $users = $assignment->assignableUsers($stage);
        if ($users === []) return false;
        $defaults = $assignment->defaultAssignees();
        $uid = (int)($defaults[$stage] ?? 0);
        $candidateUids = array_map(static fn(array $user): int => (int)$user['uid'], $users);
        if (!in_array($uid, $candidateUids, true)) $uid = (int)$users[0]['uid'];
        $candidateIndex = array_search($uid, $candidateUids, true);
        $candidate = $users[$candidateIndex === false ? 0 : $candidateIndex];
        $now = time();
        $row->save([
            'task_stage_key' => $stage,
            'task_assignee_uid' => $uid,
            'task_assignee_name' => (string)$candidate['name'],
            'task_assigner_uid' => (int)$this->uid,
            'task_assigner_name' => (string)$this->username,
            'task_assigned_at' => $now,
            'update_at' => $now,
        ]);
        $this->publish($row->toArray(), $stage, $uid, (string)$candidate['name']);
        return true;
    }

    public function backfillOpenTasks(): void
    {
        foreach ([ErpPayable::class => ErpListingTaskService::TASK_PAYABLE, ErpReceivable::class => ErpListingTaskService::TASK_RECEIVABLE] as $modelClass => $stage) {
            $ids = $modelClass::where([['site_id', '=', $this->site_id], ['task_assignee_uid', '=', 0]])
                ->whereIn('status', ['pending', 'partial'])->whereRaw('amount > settled_amount')->column('id');
            foreach (array_map('intval', $ids) as $id) $this->sync($stage, $id);
        }
    }

    /** 企业微信发件前校验任务仍然有效，现结或已处理账目不会产生过期待办。 */
    public function validate(array $event): array
    {
        if ((string)($event['source_plugin'] ?? '') !== 'hsx_erp') return ['valid' => true];
        $sourceType = (string)($event['source_type'] ?? '');
        $modelClass = $sourceType === 'erp_payable' ? ErpPayable::class : ($sourceType === 'erp_receivable' ? ErpReceivable::class : null);
        if ($modelClass === null) return ['valid' => true];
        $row = $modelClass::where([['site_id', '=', (int)($event['site_id'] ?? 0)], ['id', '=', (int)($event['source_id'] ?? 0)]])->findOrEmpty();
        return $row->isEmpty() || !$this->isOpen($row->toArray())
            ? ['valid' => false, 'reason' => '账款已处理，无需继续通知']
            : ['valid' => true];
    }

    private function isOpen(array $row): bool
    {
        return in_array((string)($row['status'] ?? ''), ['pending', 'partial'], true)
            && (float)($row['amount'] ?? 0) > (float)($row['settled_amount'] ?? 0) + 0.0001;
    }

    private function publish(array $row, string $stage, int $uid, string $name): void
    {
        try {
            $payable = $stage === ErpListingTaskService::TASK_PAYABLE;
            $type = $payable ? 'payable' : 'receivable';
            $number = (string)($row[$payable ? 'payable_no' : 'receivable_no'] ?? '');
            $params = ['status' => 'pending', 'source_no' => (string)($row['source_no'] ?: $number)];
            $query = '?' . http_build_query($params);
            $asset = (int)($row['asset_id'] ?? 0) > 0
                ? ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', (int)$row['asset_id']]])->field('imei,sn')->findOrEmpty()
                : null;
            $modelClass = $payable ? ErpPayable::class : ErpReceivable::class;
            event('HsxBusinessTaskAssigned', [
                'event_id' => 'erp-finance-task-' . md5(implode(':', [$this->site_id, $type, $row['id'], $uid, microtime(true)])),
                'event_name' => 'task.assigned.v1', 'site_id' => $this->site_id,
                'source_plugin' => 'hsx_erp', 'source_type' => 'erp_' . $type, 'source_id' => (int)$row['id'],
                'stage_key' => $stage, 'assignee_uid' => $uid, 'assignee_name' => $name,
                'assigner_uid' => (int)$this->uid, 'assigner_name' => (string)$this->username,
                'title' => ($payable ? '待付款' : '待收款') . '：' . (string)($row['party_name'] ?: '未命名往来单位')
                    . ' ¥' . number_format(max(0, (float)$row['amount'] - (float)$row['settled_amount']), 2, '.', ''),
                'business_no' => $number,
                'imei' => $asset && !$asset->isEmpty() ? (string)($asset->imei ?: $asset->sn) : '',
                'pending_count' => (int)$modelClass::where([
                    ['site_id', '=', $this->site_id], ['task_assignee_uid', '=', $uid],
                ])->whereIn('status', ['pending', 'partial'])->whereRaw('amount > settled_amount')->count(),
                'target' => [
                    'plugin' => 'hsx_erp', 'route_key' => 'hsx_erp.' . $type . '.list', 'params' => $params,
                    'web_path' => 'site/hsx_erp/' . $type . $query,
                    'miniapp_path' => 'addon/hsx_erp/pages/' . $type . '/list' . $query,
                ],
                'target_path' => 'addon/hsx_erp/pages/' . $type . '/list' . $query,
                'occurred_at' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ERP财务待办通知失败', ['stage' => $stage, 'id' => (int)$row['id'], 'message' => $e->getMessage()]);
        }
    }
}
