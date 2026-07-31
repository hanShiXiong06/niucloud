<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpAsset;
use addon\hsx_erp\app\model\ErpWarehouse;
use addon\hsx_erp\app\support\ErpListingWorkflow;
use app\model\sys\SysRole;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Log;

/** ERP 上架待办。业务状态归 ERP，企业微信只订阅分配事件。 */
class ErpListingTaskService extends BaseAdminService
{
    private const DEFAULT_CONFIG_KEY = 'HSX_ERP_TASK_DEFAULT_ASSIGNEES';
    public const TASK_PAYABLE = 'erp_finance_payable';
    public const TASK_RECEIVABLE = 'erp_finance_receivable';
    public static function forSite(int $siteId, int $operatorUid = 0, string $operatorName = '系统自动分配'): self
    {
        $service = new self();
        $service->site_id = $siteId;
        $service->uid = $operatorUid;
        $service->username = $operatorName;
        return $service;
    }

    public function stages(): array
    {
        return [
            ['stage_key' => self::TASK_PAYABLE, 'name' => '应付待付款', 'sort' => 40],
            ['stage_key' => self::TASK_RECEIVABLE, 'name' => '应收待收款', 'sort' => 50],
            ['stage_key' => ErpListingWorkflow::TASK_PHOTO, 'name' => '商品拍摄', 'sort' => 60],
            ['stage_key' => ErpListingWorkflow::TASK_PRICE, 'name' => '销售定价', 'sort' => 70],
            ['stage_key' => ErpListingWorkflow::TASK_MEDIA_PRICE, 'name' => '拍摄与销售定价', 'sort' => 75],
            ['stage_key' => ErpListingWorkflow::TASK_PUBLISH, 'name' => '商城资料整理', 'sort' => 80],
        ];
    }

    public function assignableUsers(string $stage): array
    {
        $permissionMap = [
            self::TASK_PAYABLE => ['hsx_erp_payable', 'hsx_erp_confirm_payment'],
            self::TASK_RECEIVABLE => ['hsx_erp_receivable', 'hsx_erp_confirm_receipt'],
            ErpListingWorkflow::TASK_PHOTO => ['hsx_erp_stock_flow'],
            ErpListingWorkflow::TASK_PRICE => ['hsx_erp_stock_retail_price', 'hsx_erp_stock_flow'],
            ErpListingWorkflow::TASK_MEDIA_PRICE => ['hsx_erp_stock_retail_price', 'hsx_erp_stock_flow'],
            ErpListingWorkflow::TASK_PUBLISH => ['hsx_erp_stock_flow', 'hsx_erp_stock_sync_listing'],
        ];
        $permissions = $permissionMap[$stage] ?? [];
        if ($permissions === []) return [];
        $relations = SysUserRole::where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->field('uid,is_admin,role_ids')->order('is_admin desc,uid asc')->select()->toArray();
        $roleIds = [];
        foreach ($relations as $relation) foreach ((array)($relation['role_ids'] ?? []) as $roleId) $roleIds[] = (int)$roleId;
        $roles = $roleIds === [] ? [] : SysRole::where([['site_id', '=', $this->site_id], ['status', '=', 1]])
            ->whereIn('role_id', array_values(array_unique($roleIds)))->field('role_id,rules')->select()->toArray();
        $rules = [];
        foreach ($roles as $role) $rules[(int)$role['role_id']] = (array)($role['rules'] ?? []);
        $roleMatched = [];
        $adminFallback = [];
        foreach ($relations as $relation) {
            $matched = false;
            foreach ((array)($relation['role_ids'] ?? []) as $roleId) {
                if (array_intersect($permissions, $rules[(int)$roleId] ?? [])) {
                    $matched = true;
                    break;
                }
            }
            if ($matched) $roleMatched[] = (int)$relation['uid'];
            elseif ((int)($relation['is_admin'] ?? 0) === 1) $adminFallback[] = (int)$relation['uid'];
        }
        // 管理员仅在没有对应岗位员工时兜底，避免默认接收拍照、定价、上架的全部任务。
        $uids = $roleMatched !== [] ? $roleMatched : $adminFallback;
        $uids = array_values(array_unique(array_filter($uids)));
        if ($uids === []) return [];
        $users = SysUser::whereIn('uid', $uids)->where('status', '=', 1)
            ->field('uid,username,real_name,head_img')->select()->toArray();
        $map = [];
        foreach ($users as $user) $map[(int)$user['uid']] = $user;
        $result = [];
        foreach ($uids as $uid) {
            if (!isset($map[$uid])) continue;
            $user = $map[$uid];
            $result[] = [
                'uid' => $uid,
                'name' => trim((string)($user['real_name'] ?? '')) ?: (string)($user['username'] ?? ('员工' . $uid)),
                'username' => (string)($user['username'] ?? ''),
                'head_img' => (string)($user['head_img'] ?? ''),
            ];
        }
        return $result;
    }

    public function sync(int $assetId, int $preferredUid = 0): bool
    {
        $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->findOrEmpty();
        if ($asset->isEmpty()) return false;
        if ((string)$asset->status === 'in_stock' && (string)$asset->sale_target === 'mall'
            && !in_array((string)$asset->listing_status, ['listed', 'pending_shop'], true)) {
            $warehouse = ErpWarehouse::where([['site_id', '=', $this->site_id], ['id', '=', (int)$asset->warehouse_id], ['status', '=', 1]])->findOrEmpty();
            $policy = (new ErpWarehousePolicyService())->evaluate($asset->toArray(), $warehouse->isEmpty() ? null : $warehouse->toArray());
            $normalizedStatus = ErpListingWorkflow::statusFromAsset($asset->toArray(), $policy);
            if ($normalizedStatus !== (string)$asset->listing_status) {
                $asset->save(['listing_status' => $normalizedStatus, 'update_at' => time()]);
                $asset->listing_status = $normalizedStatus;
            }
        }
        $mode = (string)(ErpConfigService::forSite((int)$this->site_id)->getRules()['listing_workspace']['mode'] ?? 'one_stop');
        $stage = ErpListingWorkflow::taskStage($asset->toArray(), $mode);
        if ($stage === '') {
            if ((string)($asset->task_stage_key ?? '') !== '') $asset->save($this->emptyAssignment());
            return false;
        }
        if ((string)($asset->task_stage_key ?? '') === $stage && (int)($asset->task_assignee_uid ?? 0) > 0) return true;
        $users = $this->assignableUsers($stage);
        if ($users === []) {
            $asset->save(array_merge($this->emptyAssignment(), ['task_stage_key' => $stage, 'update_at' => time()]));
            return false;
        }
        $defaults = $this->defaultAssignees();
        $configuredUid = (int)($defaults[$stage] ?? 0);
        $uid = $configuredUid > 0 ? $configuredUid : (int)$users[0]['uid'];
        if (!in_array($uid, array_map(static fn(array $row): int => (int)$row['uid'], $users), true)) $uid = (int)$users[0]['uid'];
        if ($preferredUid > 0 && in_array($preferredUid, array_map(static fn(array $row): int => (int)$row['uid'], $users), true)) $uid = $preferredUid;
        return $this->assign($assetId, $stage, $uid, 'auto');
    }

    public function defaultAssignees(): array
    {
        $value = (new CoreConfigService())->getConfigValue($this->site_id, self::DEFAULT_CONFIG_KEY);
        return is_array($value) ? $value : [];
    }

    public function saveDefaultAssignees(array $values): array
    {
        $allowedStages = array_column($this->stages(), 'stage_key');
        $stored = [];
        foreach ($allowedStages as $stage) {
            $uid = (int)($values[$stage] ?? 0);
            if ($uid <= 0) continue;
            $candidateUids = array_map(static fn(array $row): int => (int)$row['uid'], $this->assignableUsers($stage));
            if (!in_array($uid, $candidateUids, true)) throw new CommonException(ErpListingWorkflow::taskName($stage) . '默认负责人没有对应权限');
            $stored[$stage] = $uid;
        }
        (new CoreConfigService())->setConfig($this->site_id, self::DEFAULT_CONFIG_KEY, $stored);
        $this->assignOpenTasksWithoutOwner();
        (new ErpFinanceTaskService())->backfillOpenTasks();
        return $stored;
    }

    public function assignmentSettings(): array
    {
        $defaults = $this->defaultAssignees();
        return array_map(function (array $stage) use ($defaults): array {
            $key = (string)$stage['stage_key'];
            return $stage + ['default_uid' => (int)($defaults[$key] ?? 0), 'users' => $this->assignableUsers($key)];
        }, $this->stages());
    }

    /** 保存默认负责人后补齐当前待上架设备，不覆盖已经落库的责任人。 */
    private function assignOpenTasksWithoutOwner(): void
    {
        $assetIds = ErpAsset::where([
            ['site_id', '=', $this->site_id], ['status', '=', 'in_stock'], ['sale_target', '=', 'mall'],
        ])->whereIn('listing_status', ['need_photo', 'need_price', 'need_material', 'ready'])
            ->where('task_assignee_uid', '=', 0)->column('id');
        foreach (array_map('intval', $assetIds) as $assetId) $this->sync($assetId);
    }

    public function assign(int $assetId, string $stage, int $assigneeUid, string $mode = 'assign'): bool
    {
        $asset = ErpAsset::where([['site_id', '=', $this->site_id], ['id', '=', $assetId]])->findOrEmpty();
        if ($asset->isEmpty()) throw new CommonException('库存设备不存在');
        $mode = (string)(ErpConfigService::forSite((int)$this->site_id)->getRules()['listing_workspace']['mode'] ?? 'one_stop');
        if (ErpListingWorkflow::taskStage($asset->toArray(), $mode) !== $stage) throw new CommonException('设备已不在该待办环节');
        $candidate = null;
        foreach ($this->assignableUsers($stage) as $user) if ((int)$user['uid'] === $assigneeUid) $candidate = $user;
        if ($candidate === null) throw new CommonException('该员工没有处理当前环节的权限');
        $asset->save([
            'task_stage_key' => $stage,
            'task_assignee_uid' => $assigneeUid,
            'task_assignee_name' => (string)$candidate['name'],
            'task_assigner_uid' => (int)$this->uid,
            'task_assigner_name' => (string)$this->username,
            'task_assigned_at' => time(),
            'update_at' => time(),
        ]);
        $this->publish($asset->toArray(), $stage, $assigneeUid, (string)$candidate['name'], $mode);
        return true;
    }

    private function emptyAssignment(): array
    {
        return ['task_stage_key' => '', 'task_assignee_uid' => 0, 'task_assignee_name' => '', 'task_assigner_uid' => 0, 'task_assigner_name' => '', 'task_assigned_at' => 0, 'update_at' => time()];
    }

    private function publish(array $asset, string $stage, int $uid, string $name, string $mode): void
    {
        try {
            $status = ErpListingWorkflow::listStatus($stage);
            $params = array_filter(['status' => 'in_stock', 'listing_status' => $status, 'keyword' => (string)($asset['imei'] ?: $asset['sn'])]);
            $query = '?' . http_build_query($params);
            event('HsxBusinessTaskAssigned', [
                'event_id' => 'erp-listing-task-' . md5(implode(':', [$this->site_id, $asset['id'], $stage, $uid, microtime(true)])),
                'event_name' => 'task.assigned.v1', 'site_id' => $this->site_id,
                'source_plugin' => 'hsx_erp', 'source_type' => 'erp_asset', 'source_id' => (int)$asset['id'],
                'stage_key' => $stage, 'assignee_uid' => $uid, 'assignee_name' => $name,
                'assigner_uid' => (int)$this->uid, 'assigner_name' => (string)$this->username,
                'assignment_mode' => $mode,
                'title' => trim((string)$asset['model'] . ' ' . ErpListingWorkflow::taskName($stage, $mode)),
                'business_no' => (string)($asset['asset_no'] ?? ''), 'imei' => (string)($asset['imei'] ?: $asset['sn']),
                'pending_count' => (int)ErpAsset::where([['site_id', '=', $this->site_id], ['task_stage_key', '=', $stage], ['task_assignee_uid', '=', $uid]])->count(),
                'target' => [
                    'plugin' => 'hsx_erp', 'route_key' => 'hsx_erp.stock.list', 'params' => $params,
                    'web_path' => 'site/hsx_erp/stock' . $query,
                    'miniapp_path' => 'addon/hsx_erp/pages/stock/list' . $query,
                ],
                'target_path' => 'addon/hsx_erp/pages/stock/list' . $query,
                'occurred_at' => time(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ERP上架待办通知失败', ['asset_id' => (int)$asset['id'], 'stage' => $stage, 'message' => $e->getMessage()]);
        }
    }
}
