<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\stat;

use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\dict\stat\RecycleStageDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\stat\RecycleTaskClaim;
use addon\hsx_recycle\app\service\core\stat\CoreRecycleStatService;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminService;
use core\exception\AdminException;

/**
 * 店员「我的任务」服务
 * 按"我的角色 → 负责环节"过滤工单，支持认领（责任到人）。
 */
class TaskService extends BaseAdminService
{
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
        $stages = !empty($params['stage']) ? [(string)$params['stage']] : $this->getMyStages();
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, (int)($params['limit'] ?? 15));

        if (empty($stages)) {
            return ['count' => 0, 'list' => []];
        }

        // 待签收是订单级环节，单独查询（显式选中 sign 时）
        if (count($stages) === 1 && $stages[0] === RecycleStageDict::STAGE_SIGN) {
            return $this->getSignTaskList($params, $page, $limit);
        }
        // 设备级环节：排除订单级的 sign（"全部"页签只汇总设备任务，待签收走它自己的页签）
        $stages = array_values(array_diff($stages, [RecycleStageDict::STAGE_SIGN]));
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
    protected function getSignTaskList(array $params, int $page, int $limit): array
    {
        $query = (new RecycleOrder())->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', RecycleOrderDict::ORDER_STATUS_PENDING_SIGN],
        ]);
        $keyword = trim((string)($params['keyword'] ?? ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('order_no', '%' . $keyword . '%')
                    ->whereOr('express_no', 'like', '%' . $keyword . '%')
                    ->whereOr('customer_name', 'like', '%' . $keyword . '%')
                    ->whereOr('customer_phone', 'like', '%' . $keyword . '%');
            });
        }

        $count = $query->count();
        $orderTableFields = (new RecycleOrder())->getTableFields();
        $wantFields = ['id', 'order_no', 'customer_name', 'customer_phone', 'express_company', 'express_no', 'status', 'create_at', 'sign_at'];
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
        // 认领信息（sign 环节用 order_id 占 device_id 位）
        $claims = [];
        if (!empty($orderIds)) {
            $claimRows = (new RecycleTaskClaim())
                ->where([['site_id', '=', $this->site_id], ['stage_key', '=', RecycleStageDict::STAGE_SIGN], ['device_id', 'in', $orderIds]])
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
            $o['stage_key']    = RecycleStageDict::STAGE_SIGN;
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
        // 待签收：订单级，按订单校验
        if ($stageKey === RecycleStageDict::STAGE_SIGN) {
            $order = (new RecycleOrder())->where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])->findOrEmpty();
            if ($order->isEmpty()) {
                throw new AdminException('订单不存在');
            }
            if ((int)$order['status'] !== RecycleOrderDict::ORDER_STATUS_PENDING_SIGN) {
                throw new AdminException('该订单已不在待签收环节');
            }
            return $this->upsertClaim($deviceId, $stageKey);
        }
        $device = (new RecycleDevice())->where([['site_id', '=', $this->site_id], ['id', '=', $deviceId]])->findOrEmpty();
        if ($device->isEmpty()) {
            throw new AdminException('设备不存在');
        }
        if (RecycleStageDict::stageOf((int)$device['status'], (int)($device['pay_status'] ?? 0)) !== $stageKey) {
            throw new AdminException('该设备已不在此环节');
        }
        return $this->upsertClaim($deviceId, $stageKey);
    }

    /**
     * 写入/更新认领记录（device_id 在 sign 环节里承载 order_id）
     */
    protected function upsertClaim(int $deviceId, string $stageKey): bool
    {
        $model = new RecycleTaskClaim();
        $where = [['site_id', '=', $this->site_id], ['device_id', '=', $deviceId], ['stage_key', '=', $stageKey]];
        $exists = $model->where($where)->findOrEmpty();
        if (!$exists->isEmpty() && (int)$exists['assignee_uid'] > 0 && (int)$exists['assignee_uid'] !== (int)$this->uid) {
            throw new AdminException('该任务已被「' . $exists['assignee_name'] . '」认领');
        }

        $now = time();
        if ($exists->isEmpty()) {
            $model->create([
                'site_id'       => $this->site_id,
                'device_id'     => $deviceId,
                'stage_key'     => $stageKey,
                'assignee_uid'  => $this->uid,
                'assignee_name' => $this->username,
                'claimed_at'    => $now,
                'update_time'   => $now,
            ]);
        } else {
            $model->where($where)->update([
                'assignee_uid'  => $this->uid,
                'assignee_name' => $this->username,
                'claimed_at'    => $now,
                'update_time'   => $now,
            ]);
        }
        return true;
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
