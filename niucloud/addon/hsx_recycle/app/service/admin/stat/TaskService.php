<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\stat;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\stat\RecycleStageDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\stat\RecycleTaskClaim;
use addon\hsx_recycle\app\model\stat\RecycleTaskAssignmentLog;
use addon\hsx_recycle\app\service\core\recycle_order\RecycleErpCapabilityService;
use addon\hsx_recycle\app\service\core\stat\CoreRecycleStatService;
use app\model\sys\SysRole;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use app\service\admin\auth\AuthService;
use app\service\core\sys\CoreConfigService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Log;

/**
 * 店员「我的任务」服务
 * 按"我的角色 → 负责环节"过滤工单，支持认领（责任到人）。
 */
class TaskService extends BaseAdminService
{
    private const DEFAULT_CONFIG_KEY = 'HSX_RECYCLE_TASK_DEFAULT_ASSIGNEES';
    /**
     * 为 API、队列等非管理端请求创建明确的站点上下文。
     * 责任人始终写数据库；这里仅补齐执行上下文，不承载任何缓存状态。
     */
    public static function forSite(int $siteId, int $operatorUid = 0, string $operatorName = '系统自动分配'): self
    {
        $service = new self();
        $service->site_id = $siteId;
        $service->uid = $operatorUid;
        $service->username = $operatorName;
        return $service;
    }

    /** 当前环节可接收任务的员工，按站点管理员、员工UID稳定排序。 */
    public function getAssignableUsers(string $stageKey): array
    {
        $permissions = RecycleStageDict::getStagePermissions()[$stageKey] ?? [];
        if ($stageKey === '' || $permissions === []) return [];

        $relations = SysUserRole::where([
            ['site_id', '=', $this->site_id], ['status', '=', 1],
        ])->field('uid,is_admin,role_ids')->order('is_admin desc,uid asc')->select()->toArray();
        if ($relations === []) return [];

        $roleIds = [];
        foreach ($relations as $relation) {
            foreach ((array)($relation['role_ids'] ?? []) as $roleId) $roleIds[] = (int)$roleId;
        }
        $roles = $roleIds === [] ? [] : SysRole::where([
            ['site_id', '=', $this->site_id], ['status', '=', 1],
        ])->whereIn('role_id', array_values(array_unique($roleIds)))->field('role_id,rules')->select()->toArray();
        $roleRuleMap = [];
        foreach ($roles as $role) $roleRuleMap[(int)$role['role_id']] = array_values((array)($role['rules'] ?? []));

        $roleMatched = [];
        $adminFallback = [];
        foreach ($relations as $relation) {
            $matched = false;
            foreach ((array)($relation['role_ids'] ?? []) as $roleId) {
                if (array_intersect($permissions, $roleRuleMap[(int)$roleId] ?? [])) {
                    $matched = true;
                    break;
                }
            }
            if ($matched) $roleMatched[] = (int)$relation['uid'];
            elseif ((int)($relation['is_admin'] ?? 0) === 1) $adminFallback[] = (int)$relation['uid'];
        }
        // 有岗位员工时只在岗位内自动分配，避免站点管理员因为拥有全部权限而接走所有工单。
        $eligible = $roleMatched !== [] ? $roleMatched : $adminFallback;
        $eligible = array_values(array_unique(array_filter($eligible)));
        if ($eligible === []) return [];
        $users = SysUser::whereIn('uid', $eligible)->where('status', '=', 1)
            ->field('uid,username,real_name,head_img')->select()->toArray();
        $map = [];
        foreach ($users as $user) $map[(int)$user['uid']] = $user;
        $result = [];
        foreach ($eligible as $uid) {
            if (!isset($map[$uid])) continue;
            $user = $map[$uid];
            $result[] = [
                'uid' => $uid,
                'name' => trim((string)($user['real_name'] ?? '')) ?: (string)($user['username'] ?? ('员工' . $uid)),
                'username' => (string)($user['username'] ?? ''),
                'head_img' => (string)($user['head_img'] ?? ''),
            ];
        }
        $defaultUid = (int)($this->defaultAssignees()[$stageKey] ?? 0);
        foreach ($result as &$item) {
            $item['is_default'] = $defaultUid > 0 && (int)$item['uid'] === $defaultUid ? 1 : 0;
        }
        unset($item);
        return $result;
    }

    /**
     * 当前用户负责的环节 key 列表（超管/站点管理员看全部）
     */
    public function getMyStages(): array
    {
        // 角色↔权限统一交给 ERP/核心；这里只判断"我有没有该环节动作的权限"
        $myMenuKeys = $this->getCurrentUserMenuKeys();
        $stagePerms = RecycleStageDict::getStagePermissions();
        $stages = [];
        foreach ($stagePerms as $stage => $perms) {
            if (!empty($perms) && !empty(array_intersect($perms, $myMenuKeys))) {
                $stages[] = $stage;
            }
        }
        return $stages;
    }

    /**
     * 当前用户的菜单权限key（超管/站点管理员=全部）。直接复用框架 AuthService::getAuthMenuList，不另写一套。
     */
    protected function getCurrentUserMenuKeys(): array
    {
        $menuList = (new AuthService())->getAuthMenuList();
        return array_column($menuList, 'menu_key');
    }

    /**
     * 我的工单列表（按环节，FIFO：先进先处理）
     * @param array $params stage(可选,单环节) / keyword(IMEI/SN/型号) / page / limit
     */
    public function getTaskList(array $params): array
    {
        $myStages = $this->getMyStages();
        $requestedStage = trim((string)($params['stage'] ?? ''));
        $stages = $requestedStage !== '' && in_array($requestedStage, $myStages, true)
            ? [$requestedStage]
            : ($requestedStage === '' ? $myStages : []);
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, (int)($params['limit'] ?? 15));

        if (empty($stages)) {
            return ['count' => 0, 'list' => []];
        }

        // 待取货、待签收是订单级环节，单独查询。
        if (count($stages) === 1 && RecycleStageDict::isOrderStage($stages[0])) {
            return $this->getOrderTaskList($stages[0], $params, $page, $limit);
        }
        // 设备级环节：排除订单级任务（"全部"页签保持设备任务的稳定分页）。
        $stages = array_values(array_filter($stages, static fn(string $stage): bool => !RecycleStageDict::isOrderStage($stage)));
        if (empty($stages)) {
            return ['count' => 0, 'list' => []];
        }

        $tableFields = (new RecycleDevice())->getTableFields();
        $hasPayStatus = in_array('pay_status', $tableFields);
        $recycled = RecycleStageDict::statusRecycled();
        // 打款落在 status=5 且未打款；其余环节按状态集合
        $payWanted     = in_array('pay', $stages);
        $plainStatuses = RecycleStageDict::statusesOfStages(array_values(array_diff($stages, ['pay'])));

        $query = (new RecycleDevice())->where('site_id', '=', $this->site_id);
        $assignedDeviceIds = RecycleTaskClaim::where([
            ['site_id', '=', $this->site_id], ['assignee_uid', '=', (int)$this->uid],
        ])->whereIn('stage_key', $stages)->column('device_id');
        $query->whereIn('id', $assignedDeviceIds !== [] ? array_map('intval', $assignedDeviceIds) : [0]);
        $query->where(function ($q) use ($plainStatuses, $payWanted, $recycled, $hasPayStatus) {
            $matched = false;
            if (!empty($plainStatuses)) {
                $q->whereOr('status', 'in', $plainStatuses);
                $matched = true;
            }
            if ($payWanted) {
                // 待打款：已回收且(未打款)；无 pay_status 列的老库退化为全部已回收
                $q->whereOr(function ($q2) use ($recycled, $hasPayStatus) {
                    $q2->where('status', '=', $recycled);
                    if ($hasPayStatus) $q2->where('pay_status', '=', 0);
                });
                $matched = true;
            }
            if (!$matched) {
                $q->whereOr('id', '=', 0); // 无可查环节：恒为空
            }
        });
        // 质检队列只看「订单已签收」的设备：未签收订单的待质检设备不算任务
        if (in_array('check', $stages) && in_array('order_id', $tableFields)) {
            $siteId = $this->site_id;
            $query->where('order_id', 'not in', function ($sub) use ($siteId) {
                $sub->name('recycle_order')
                    ->where('site_id', '=', $siteId)
                    ->where('status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN)
                    ->field('id');
            });
        }
        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('imei', '%' . $keyword . '%')
                    ->whereOr('sn', 'like', '%' . $keyword . '%')
                    ->whereOr('model', 'like', '%' . $keyword . '%');
            });
        }

        $count = $query->count();
        // 仅查询表中真实存在的字段，兼容历史库列名差异（成色/时间字段）
        $wantFields = ['id', 'order_id', 'imei', 'sn', 'model', 'status', 'pay_status', 'condition_grade', 'initial_price', 'final_price', 'create_at', 'update_at'];
        $fields = array_values(array_intersect($wantFields, $tableFields));
        // FIFO：按进入该环节的时间先后排，排序列存在才用，否则退回按 id
        $orderField = in_array('update_at', $tableFields) ? 'update_at' : 'id';
        $rows = $query->field($fields)
            ->order($orderField . ' asc, id asc')
            ->page($page, $limit)
            ->select()->toArray();

        // 认领信息合并
        $deviceIds = array_column($rows, 'id');
        $claims = [];
        if (!empty($deviceIds)) {
            $claimRows = (new RecycleTaskClaim())
                ->where([['site_id', '=', $this->site_id], ['device_id', 'in', $deviceIds]])
                ->select()->toArray();
            foreach ($claimRows as $c) {
                $claims[$c['device_id'] . '_' . $c['stage_key']] = $c;
            }
        }
        foreach ($rows as &$d) {
            $d['device_id'] = $d['id'];
            $d['stage_key'] = RecycleStageDict::stageOf((int)$d['status'], (int)($d['pay_status'] ?? 0));
            $claim = $claims[$d['id'] . '_' . $d['stage_key']] ?? null;
            $d['assignee_uid']  = (int)($claim['assignee_uid'] ?? 0);
            $d['assignee_name'] = (string)($claim['assignee_name'] ?? '');
            $d['is_mine']       = ($d['assignee_uid'] > 0 && $d['assignee_uid'] === (int)$this->uid);
        }
        unset($d);

        return ['count' => $count, 'list' => $rows];
    }

    /**
     * 待签收工单列表（订单级：到件待签收的包裹）
     * 工单单位是订单，item 里 device_id=0、is_order=true，前端按订单卡片渲染。
     */
    protected function getOrderTaskList(string $stageKey, array $params, int $page, int $limit): array
    {
        $query = (new RecycleOrder())->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN],
        ]);
        if ($stageKey === RecycleStageDict::STAGE_PICKUP) {
            $query->where('delivery_type', '=', RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE);
        } else {
            $query->where('delivery_type', '<>', RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE);
        }
        $assignedOrderIds = RecycleTaskClaim::where([
            ['site_id', '=', $this->site_id],
            ['stage_key', '=', $stageKey],
            ['assignee_uid', '=', (int)$this->uid],
        ])->column('device_id');
        $query->whereIn('id', $assignedOrderIds !== [] ? array_map('intval', $assignedOrderIds) : [0]);
        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('order_no', '%' . $keyword . '%')
                    ->whereOr('express_no', 'like', '%' . $keyword . '%')
                    ->whereOr('logistics_vehicle_no', 'like', '%' . $keyword . '%')
                    ->whereOr('logistics_name', 'like', '%' . $keyword . '%')
                    ->whereOr('customer_name', 'like', '%' . $keyword . '%')
                    ->whereOr('customer_phone', 'like', '%' . $keyword . '%');
            });
        }

        $count = $query->count();
        $orderTableFields = (new RecycleOrder())->getTableFields();
        $wantFields = [
            'id', 'order_no', 'customer_name', 'customer_phone', 'delivery_type', 'express_company', 'express_no',
            'logistics_name', 'logistics_vehicle_no', 'logistics_contact_name', 'logistics_contact_mobile',
            'logistics_pickup_address', 'logistics_eta_at', 'status', 'create_at', 'sign_at',
        ];
        $fields = array_values(array_intersect($wantFields, $orderTableFields));
        $orderField = in_array('create_at', $orderTableFields) ? 'create_at' : 'id';
        $rows = $query->field($fields)
            ->order($orderField . ' asc, id asc')
            ->page($page, $limit)
            ->select()->toArray();

        // 每单设备数
        $orderIds = array_column($rows, 'id');
        $countMap = [];
        if (!empty($orderIds)) {
            $cntRows = (new RecycleDevice())
                ->where([['site_id', '=', $this->site_id], ['order_id', 'in', $orderIds]])
                ->field('order_id, count(*) as cnt')->group('order_id')->select()->toArray();
            foreach ($cntRows as $c) {
                $countMap[(int)$c['order_id']] = (int)$c['cnt'];
            }
        }
        // 认领信息（订单级环节用 order_id 占 device_id 位）
        $claims = [];
        if (!empty($orderIds)) {
            $claimRows = (new RecycleTaskClaim())
                ->where([['site_id', '=', $this->site_id], ['stage_key', '=', $stageKey], ['device_id', 'in', $orderIds]])
                ->select()->toArray();
            foreach ($claimRows as $c) {
                $claims[(int)$c['device_id']] = $c;
            }
        }

        foreach ($rows as &$o) {
            $oid = (int)$o['id'];
            $o['order_id']     = $oid;
            $o['device_id']    = $oid;   // sign 环节认领以 order_id 为键
            $o['is_order']     = true;
            $o['stage_key']    = $stageKey;
            $o['device_count'] = $countMap[$oid] ?? 0;
            // 给前端通用字段兜底（设备卡片复用）
            $o['model'] = '订单 ' . ($o['order_no'] ?? $oid);
            $o['imei']  = (string)($o['express_no'] ?? '');
            $claim = $claims[$oid] ?? null;
            $o['assignee_uid']  = (int)($claim['assignee_uid'] ?? 0);
            $o['assignee_name'] = (string)($claim['assignee_name'] ?? '');
            $o['is_mine']       = ($o['assignee_uid'] > 0 && $o['assignee_uid'] === (int)$this->uid);
        }
        unset($o);

        return ['count' => $count, 'list' => $rows];
    }

    /**
     * 认领某设备在某环节的任务
     */
    public function claim(int $deviceId, string $stageKey): bool
    {
        if (RecycleStageDict::isOrderStage($stageKey)) {
            $order = (new RecycleOrder())->where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])->findOrEmpty();
            if ($order->isEmpty()) {
                throw new AdminException('订单不存在');
            }
            if ((int)$order['status'] !== RecycleOrderDict::ORDER_STATUS_PENDING_SIGN) {
                throw new AdminException('该订单已不在当前环节');
            }
            $isLogistics = (int)$order['delivery_type'] === (int)RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE;
            if (($stageKey === RecycleStageDict::STAGE_PICKUP) !== $isLogistics) throw new AdminException('订单交付方式与当前环节不匹配');
            return $this->upsertAssignment($deviceId, $stageKey, (int)$this->uid, (string)$this->username, 'claim', false);
        }
        $device = (new RecycleDevice())->where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])->findOrEmpty();
        if ($device->isEmpty()) {
            throw new AdminException('设备不存在');
        }
        if (RecycleStageDict::stageOf((int)$device['status'], (int)($device['pay_status'] ?? 0)) !== $stageKey) {
            throw new AdminException('该设备已不在此环节');
        }
        return $this->upsertAssignment($deviceId, $stageKey, (int)$this->uid, (string)$this->username, 'claim', false);
    }

    /** 管理员或上一步操作员把当前环节明确分配给指定员工。 */
    public function assign(int $deviceId, string $stageKey, int $assigneeUid): bool
    {
        if ($assigneeUid <= 0) throw new AdminException('请选择任务责任人');
        $this->assertTaskInStage($deviceId, $stageKey);
        $candidates = $this->getAssignableUsers($stageKey);
        $candidate = null;
        foreach ($candidates as $item) {
            if ((int)$item['uid'] === $assigneeUid) {
                $candidate = $item;
                break;
            }
        }
        if ($candidate === null) throw new AdminException('该员工没有处理当前环节的权限');
        $current = RecycleTaskClaim::where([
            ['site_id', '=', $this->site_id], ['device_id', '=', $deviceId], ['stage_key', '=', $stageKey],
        ])->findOrEmpty();
        $mode = !$current->isEmpty() && (int)$current->assignee_uid > 0 && (int)$current->assignee_uid !== $assigneeUid
            ? 'transfer'
            : 'assign';
        return $this->upsertAssignment($deviceId, $stageKey, $assigneeUid, (string)$candidate['name'], $mode, true);
    }

    /** 流程流转后的默认分配：优先使用表单指定人员，否则使用权限候选中的第一人。 */
    public function assignPreferredOrDefault(int $deviceId, string $stageKey, int $preferredUid = 0): bool
    {
        // 只看当前设备责任；同站点仍可能同时存在本地付款和 ERP 付款设备。
        if ($stageKey === RecycleStageDict::STAGE_PAY) {
            try {
                if ($this->isErpPaymentTask($deviceId)) return true;
            } catch (\Throwable $e) {
                Log::warning('付款任务归属未确认，已暂停自动分配', ['site_id' => $this->site_id, 'device_id' => $deviceId, 'message' => $e->getMessage()]);
                return false;
            }
        }
        $candidates = $this->getAssignableUsers($stageKey);
        if ($candidates === []) return false;
        $defaults = $this->defaultAssignees();
        $configuredUid = (int)($defaults[$stageKey] ?? 0);
        $assigneeUid = $configuredUid > 0 ? $configuredUid : (int)$candidates[0]['uid'];
        if (!in_array($assigneeUid, array_map(static fn(array $row): int => (int)$row['uid'], $candidates), true)) $assigneeUid = (int)$candidates[0]['uid'];
        if ($preferredUid > 0) {
            foreach ($candidates as $candidate) {
                if ((int)$candidate['uid'] === $preferredUid) {
                    $assigneeUid = $preferredUid;
                    break;
                }
            }
        }
        return $this->assign($deviceId, $stageKey, $assigneeUid);
    }

    public function defaultAssignees(): array
    {
        $value = (new CoreConfigService())->getConfigValue($this->site_id, self::DEFAULT_CONFIG_KEY);
        return is_array($value) ? $value : [];
    }

    public function saveDefaultAssignees(array $values): array
    {
        $stored = [];
        foreach (array_column(RecycleStageDict::getStages(), 'stage_key') as $stage) {
            $uid = (int)($values[$stage] ?? 0);
            if ($uid <= 0) continue;
            $candidateUids = array_map(static fn(array $row): int => (int)$row['uid'], $this->getAssignableUsers($stage));
            if (!in_array($uid, $candidateUids, true)) throw new AdminException('默认负责人没有处理「' . $stage . '」的权限');
            $stored[$stage] = $uid;
        }
        (new CoreConfigService())->setConfig($this->site_id, self::DEFAULT_CONFIG_KEY, $stored);
        $this->assignOpenTasksWithoutOwner();
        return $stored;
    }

    public function assignmentSettings(): array
    {
        $defaults = $this->defaultAssignees();
        $stages = RecycleStageDict::getStages();
        return array_map(function (array $stage) use ($defaults): array {
            $key = (string)$stage['stage_key'];
            return $stage + ['default_uid' => (int)($defaults[$key] ?? 0), 'users' => $this->getAssignableUsers($key)];
        }, $stages);
    }

    /** 保存默认负责人后补齐当前在途且尚未分配的任务，不覆盖已有责任人。 */
    private function assignOpenTasksWithoutOwner(): void
    {
        $openOrders = RecycleOrder::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN],
        ])->field('id,delivery_type')->select()->toArray();
        foreach ($openOrders as $order) {
            $stage = (int)$order['delivery_type'] === (int)RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE
                ? RecycleStageDict::STAGE_PICKUP
                : RecycleStageDict::STAGE_SIGN;
            $this->ensureAssigned((int)$order['id'], $stage);
        }

        $deviceRows = RecycleDevice::where([['site_id', '=', $this->site_id]])
            ->whereIn('status', array_values(array_unique(array_merge(
                RecycleStageDict::statusesOfStages(array_keys(RecycleStageDict::getStageStatuses())),
                [RecycleStageDict::statusRecycled()]
            ))))->field('id,status,pay_status')->select()->toArray();
        foreach ($deviceRows as $device) {
            $stage = RecycleStageDict::stageOf((int)$device['status'], (int)($device['pay_status'] ?? 0));
            if ($stage !== '') $this->ensureAssigned((int)$device['id'], $stage);
        }
    }

    private function ensureAssigned(int $deviceId, string $stageKey): void
    {
        $claim = RecycleTaskClaim::where([
            ['site_id', '=', $this->site_id], ['device_id', '=', $deviceId], ['stage_key', '=', $stageKey],
        ])->findOrEmpty();
        if (!$claim->isEmpty() && (int)$claim->assignee_uid > 0) return;
        $this->assignPreferredOrDefault($deviceId, $stageKey);
    }

    /**
     * 写入/更新认领记录（device_id 在 sign 环节里承载 order_id）
     */
    protected function upsertAssignment(int $deviceId, string $stageKey, int $assigneeUid, string $assigneeName, string $mode, bool $allowTransfer): bool
    {
        // 手工认领/分配也必须先确认责任，未知时不落付款任务或发送通知。
        if ($stageKey === RecycleStageDict::STAGE_PAY) $this->isErpPaymentTask($deviceId);
        $model = new RecycleTaskClaim();
        $where = [['site_id', '=', $this->site_id], ['device_id', '=', $deviceId], ['stage_key', '=', $stageKey]];
        $exists = $model->where($where)->findOrEmpty();
        if (!$allowTransfer && !$exists->isEmpty() && (int)$exists['assignee_uid'] > 0 && (int)$exists['assignee_uid'] !== $assigneeUid) {
            throw new AdminException('该任务已被「' . $exists['assignee_name'] . '」认领');
        }

        $now = time();
        $previousUid = $exists->isEmpty() ? 0 : (int)$exists['assignee_uid'];
        $previousName = $exists->isEmpty() ? '' : (string)$exists['assignee_name'];
        $eventId = 'recycle-task-' . md5(implode(':', [$this->site_id, $deviceId, $stageKey, $assigneeUid, microtime(true)]));
        $save = [
            'assignee_uid' => $assigneeUid,
            'assignee_name' => $assigneeName,
            'assigner_uid' => (int)$this->uid,
            'assigner_name' => (string)$this->username,
            'assignment_mode' => $mode,
            'claimed_at' => $now,
            'assigned_at' => $now,
            'update_time' => $now,
        ];
        if ($exists->isEmpty()) {
            $model->create(array_merge([
                'site_id'       => $this->site_id,
                'device_id'     => $deviceId,
                'stage_key'     => $stageKey,
            ], $save));
        } else {
            $model->where($where)->update($save);
        }
        RecycleTaskAssignmentLog::create([
            'site_id' => $this->site_id,
            'device_id' => $deviceId,
            'stage_key' => $stageKey,
            'from_uid' => $previousUid,
            'from_name' => $previousName,
            'to_uid' => $assigneeUid,
            'to_name' => $assigneeName,
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'assignment_mode' => $mode,
            'event_id' => $eventId,
            'create_at' => $now,
        ]);
        $this->publishTaskAssigned($deviceId, $stageKey, $assigneeUid, $assigneeName, $eventId);
        return true;
    }

    private function assertTaskInStage(int $deviceId, string $stageKey): void
    {
        if (RecycleStageDict::isOrderStage($stageKey)) {
            $order = RecycleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])->findOrEmpty();
            if ($order->isEmpty()) throw new AdminException('订单不存在');
            if ((int)$order->status !== RecycleOrderDict::ORDER_STATUS_PENDING_SIGN) throw new AdminException('该订单已不在当前环节');
            $isLogistics = (int)$order->delivery_type === (int)RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE;
            if (($stageKey === RecycleStageDict::STAGE_PICKUP) !== $isLogistics) throw new AdminException('订单交付方式与当前环节不匹配');
            return;
        }
        $device = RecycleDevice::where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])->findOrEmpty();
        if ($device->isEmpty()) throw new AdminException('设备不存在');
        if (RecycleStageDict::stageOf((int)$device->status, (int)($device->pay_status ?? 0)) !== $stageKey) {
            throw new AdminException('该设备已不在此环节');
        }
    }

    private function publishTaskAssigned(int $deviceId, string $stageKey, int $assigneeUid, string $assigneeName, string $eventId): void
    {
        try {
            $isOrder = RecycleStageDict::isOrderStage($stageKey);
            $orderId = $isOrder ? $deviceId : 0;
            $businessNo = '';
            $imei = '';
            $model = '';
            $orderContext = [];
            if ($isOrder) {
                $order = RecycleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])->findOrEmpty();
                $businessNo = $order->isEmpty() ? '' : (string)$order->order_no;
                if (!$order->isEmpty()) $orderContext = $order->toArray();
            } else {
                $device = RecycleDevice::where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])->findOrEmpty();
                if (!$device->isEmpty()) {
                    $orderId = (int)$device->order_id;
                    $imei = (string)($device->imei ?: $device->sn);
                    $model = (string)$device->model;
                }
                if ($orderId > 0) $businessNo = (string)RecycleOrder::where([['site_id', '=', $this->site_id], ['id', '=', $orderId]])->value('order_no');
            }
            $stageNames = array_column(RecycleStageDict::getStages(), 'name', 'stage_key');
            $stageName = (string)($stageNames[$stageKey] ?? '待办');
            $taskActionName = str_starts_with($stageName, '待') ? $stageName : ('待' . $stageName);
            $target = $this->taskTarget($stageKey, $orderId, $deviceId, $businessNo, $imei);
            $event = [
                'event_id' => $eventId,
                'event_name' => 'task.assigned.v1',
                'site_id' => $this->site_id,
                'source_plugin' => 'hsx_recycle',
                'source_type' => $isOrder ? 'recycle_order' : 'recycle_device',
                'source_id' => $deviceId,
                'stage_key' => $stageKey,
                'assignee_uid' => $assigneeUid,
                'assignee_name' => $assigneeName,
                'assigner_uid' => (int)$this->uid,
                'assigner_name' => (string)$this->username,
                'title' => trim(($model !== '' ? $model . ' ' : '') . $taskActionName),
                'business_no' => $businessNo,
                'imei' => $imei,
                'pending_count' => $this->countAssignedPending($assigneeUid, $stageKey),
                'target' => $target,
                // 兼容仅识别单一移动管理端路径的通知消费者。
                'target_path' => (string)$target['miniapp_path'],
                'occurred_at' => time(),
            ];
            if ($stageKey === RecycleStageDict::STAGE_PICKUP) {
                $event = array_merge($event, [
                    'notify_at' => max(time(), (int)($orderContext['logistics_eta_at'] ?? 0)),
                    'customer_name' => (string)($orderContext['customer_name'] ?? ''),
                    'customer_phone' => (string)($orderContext['customer_phone'] ?? ''),
                    'logistics_name' => (string)($orderContext['logistics_name'] ?? ''),
                    'logistics_vehicle_no' => (string)($orderContext['logistics_vehicle_no'] ?? ''),
                    'logistics_contact_name' => (string)($orderContext['logistics_contact_name'] ?? ''),
                    'logistics_contact_mobile' => (string)($orderContext['logistics_contact_mobile'] ?? ''),
                    'logistics_pickup_address' => (string)($orderContext['logistics_pickup_address'] ?? ''),
                    'logistics_eta_at' => (int)($orderContext['logistics_eta_at'] ?? 0),
                ]);
            }
            event('HsxBusinessTaskAssigned', $event);
        } catch (\Throwable $e) {
            Log::warning('回收任务分配事件发布失败', ['event_id' => $eventId, 'message' => $e->getMessage()]);
        }
    }

    /**
     * 跳转目标由当前业务环节决定，不能按事件来源插件猜测。
     * 回收仍是任务来源；ERP 接管财务后，待打款任务的处理入口属于 ERP 应付款。
     */
    private function taskTarget(string $stageKey, int $orderId, int $deviceId, string $businessNo, string $imei): array
    {
        if ($stageKey === RecycleStageDict::STAGE_PICKUP) {
            $params = ['stage' => RecycleStageDict::STAGE_PICKUP];
            return [
                'plugin' => 'hsx_recycle',
                'route_key' => 'hsx_recycle.task.list',
                'params' => $params,
                'web_path' => 'site/stat/task?' . http_build_query($params),
                'miniapp_path' => 'addon/hsx_recycle/pages/task/index?' . http_build_query($params),
            ];
        }

        if ($stageKey === RecycleStageDict::STAGE_PAY
            && $this->isErpPaymentTask($deviceId)) {
            $params = array_filter([
                'status' => 'pending',
                'source_no' => $businessNo,
                'source_device_id' => $deviceId > 0 ? $deviceId : null,
            ], static fn($value): bool => $value !== '' && $value !== null);
            $query = $params === [] ? '' : ('?' . http_build_query($params));
            return [
                'plugin' => 'hsx_erp',
                'route_key' => 'hsx_erp.payable.list',
                'params' => $params,
                'web_path' => 'site/hsx_erp/payable' . $query,
                'miniapp_path' => 'addon/hsx_erp/pages/payable/list' . $query,
            ];
        }

        // 企业微信分配的是一条具体任务，应直接进入可操作的订单详情。
        // “我的任务”列表只用于主动查看全部待办，不作为单条通知的落点。
        $params = array_filter([
            'id' => $orderId,
            'device_id' => $deviceId > 0 && !RecycleStageDict::isOrderStage($stageKey) ? $deviceId : null,
            'stage' => $stageKey,
        ], static fn($value): bool => $value !== '' && $value !== null && $value !== 0);
        $miniappParams = ['id' => $orderId];
        if ($deviceId > 0 && !RecycleStageDict::isOrderStage($stageKey)) $miniappParams['device_id'] = $deviceId;
        $miniappParams['stage'] = $stageKey;
        return [
            'plugin' => 'hsx_recycle',
            'route_key' => 'hsx_recycle.order.detail',
            'params' => $params,
            // PC 没有独立详情路由，订单列表会根据 order_id 自动定位并打开详情。
            'web_path' => 'site/recycle_order/list?' . http_build_query([
                'order_id' => $orderId,
                'device_id' => $deviceId,
                'stage' => $stageKey,
            ]),
            'miniapp_path' => 'addon/hsx_recycle/pages/order/detail?' . http_build_query($miniappParams),
        ];
    }

    /** 不允许站点开关代替具体设备责任；unknown 会由能力服务抛出明确错误。 */
    private function isErpPaymentTask(int $deviceId): bool
    {
        if ($deviceId <= 0) throw new AdminException('缺少具体设备，无法确认付款任务归属');
        return (new RecycleErpCapabilityService())->isPaymentManaged((int)$this->site_id, 0, [$deviceId]);
    }

    private function countAssignedPending(int $uid, string $stageKey): int
    {
        $claimTable = (new RecycleTaskClaim())->getTable();
        if (RecycleStageDict::isOrderStage($stageKey)) {
            $query = RecycleOrder::alias('o')->join($claimTable . ' c', 'c.device_id = o.id AND c.site_id = o.site_id')
                ->where([['o.site_id', '=', $this->site_id], ['o.status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN], ['c.stage_key', '=', $stageKey], ['c.assignee_uid', '=', $uid]]);
            if ($stageKey === RecycleStageDict::STAGE_PICKUP) $query->where('o.delivery_type', '=', RecycleOrderDict::DELIVERY_TYPE_LOGISTICS_VEHICLE);
            return (int)$query->count();
        }
        $statuses = $stageKey === RecycleStageDict::STAGE_PAY
            ? [RecycleStageDict::statusRecycled()]
            : (RecycleStageDict::getStageStatuses()[$stageKey] ?? []);
        if ($statuses === []) return 0;
        $query = RecycleDevice::alias('d')->join($claimTable . ' c', 'c.device_id = d.id AND c.site_id = d.site_id')
            ->where([['d.site_id', '=', $this->site_id], ['c.stage_key', '=', $stageKey], ['c.assignee_uid', '=', $uid]])
            ->whereIn('d.status', $statuses);
        if ($stageKey === RecycleStageDict::STAGE_PAY && in_array('pay_status', (new RecycleDevice())->getTableFields(), true)) $query->where('d.pay_status', '=', 0);
        return (int)$query->count();
    }

    /**
     * 释放自己认领的任务（设备级/订单级通用，device_id 在 sign 环节为 order_id）
     */
    public function release(int $deviceId, string $stageKey): bool
    {
        (new RecycleTaskClaim())->where([
            ['site_id', '=', $this->site_id],
            ['device_id', '=', $deviceId],
            ['stage_key', '=', $stageKey],
            ['assignee_uid', '=', $this->uid],
        ])->delete();
        return true;
    }

    /**
     * 一次性回填：按当前设备状态重算各环节在途台数
     */
    public function rebuildStat(): array
    {
        return (new CoreRecycleStatService())->rebuildCurrent($this->site_id);
    }

    /**
     * 经营看板数据（各环节在途 + 今日数字 + 趋势）
     */
    public function getBoard(int $days = 7): array
    {
        return (new CoreRecycleStatService())->getBoard($this->site_id, $days > 0 ? $days : 7);
    }

    /**
     * 维度分布 TOP（型号/分类/成色/来源），读每日维度汇总表，不扫大表全量。
     * @param string $dimType category/model/grade/source
     * @param string $startDate Y-m-d
     * @param string $endDate   Y-m-d
     */
    public function getDimBreakdown(string $dimType, string $startDate, string $endDate, int $limit = 10): array
    {
        $allow = ['category', 'model', 'grade', 'source'];
        if (!in_array($dimType, $allow, true)) {
            $dimType = 'model';
        }
        $start = (int)str_replace('-', '', $startDate);
        $end = (int)str_replace('-', '', $endDate);
        if ($start <= 0 || $end <= 0 || $start > $end) {
            $end = (int)date('Ymd');
            $start = (int)date('Ymd', strtotime('-6 days'));
        }
        $limit = max(1, min(50, $limit));
        return (new CoreRecycleStatService())->getDimBreakdown($this->site_id, $dimType, $start, $end, $limit);
    }
}
