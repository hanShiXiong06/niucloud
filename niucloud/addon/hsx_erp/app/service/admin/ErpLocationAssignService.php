<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpLocationAssign;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 库位责任分配服务（管理员侧）
 *
 * 管理员把 ERP 库位分配给员工（人↔库位 多对多）；出库/调拨/资产列表据此过滤。
 * 责任精确到「库位/分类」（如二手机仓的 苹果区/安卓区/手表区），不同分区可由不同人负责。
 * 整仓授权 = 把该仓所有库位一次性指给某人（前端"选整仓"快捷）。
 */
class ErpLocationAssignService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new ErpLocationAssign();
    }

    /**
     * 仓库/库位树（直接取 ERP 仓库，含每仓库位）
     */
    public function getWarehouseTree(): array
    {
        $warehouses = (new ErpWarehouseService())->getAll();
        return ['warehouses' => $warehouses];
    }

    /**
     * 当前站点可分配的员工列表
     */
    public function getStaffOptions(): array
    {
        $uids = SysUserRole::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1],
        ])->column('uid');
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));
        if (empty($uids)) {
            return [];
        }

        $users = SysUser::whereIn('uid', $uids)->field('uid,username,real_name')->select()->toArray();
        return array_map(static function ($u) {
            return [
                'uid' => (int)$u['uid'],
                'label' => (string)($u['real_name'] ?: $u['username'] ?: ('员工#' . $u['uid'])),
            ];
        }, $users);
    }

    /**
     * 分配列表。可按员工(uid)或库位(location_id)过滤，带库位/员工名快照。
     */
    public function getAssignments(array $where = []): array
    {
        $query = (new ErpLocationAssign())->where([['site_id', '=', $this->site_id]]);
        if (!empty($where['uid'])) {
            $query->where('uid', '=', (int)$where['uid']);
        }
        if (!empty($where['location_id'])) {
            $query->where('location_id', '=', (int)$where['location_id']);
        }
        $rows = $query->order('id desc')->select()->toArray();
        if (empty($rows)) {
            return [];
        }

        $uids = array_values(array_unique(array_map(static fn($r) => (int)$r['uid'], $rows)));
        $userMap = [];
        if (!empty($uids)) {
            foreach (SysUser::whereIn('uid', $uids)->field('uid,username,real_name')->select()->toArray() as $u) {
                $userMap[(int)$u['uid']] = (string)($u['real_name'] ?: $u['username'] ?: ('员工#' . $u['uid']));
            }
        }
        $nameMap = $this->buildLocationNameMap();

        return array_map(function ($r) use ($userMap, $nameMap) {
            $lid = (int)$r['location_id'];
            return [
                'id' => (int)$r['id'],
                'warehouse_id' => (int)$r['warehouse_id'],
                'warehouse_name' => $nameMap['warehouse'][(int)$r['warehouse_id']] ?? '',
                'location_id' => $lid,
                'location_name' => $nameMap['location'][$lid] ?? '',
                'uid' => (int)$r['uid'],
                'staff_name' => $userMap[(int)$r['uid']] ?? ('员工#' . $r['uid']),
            ];
        }, $rows);
    }

    /**
     * 给某库位设置负责人（整组替换：传入的 uids 即该库位的全部负责人）
     */
    public function setLocationStaff(int $warehouseId, int $locationId, array $uids): bool
    {
        if ($locationId <= 0) {
            throw new CommonException('请选择库位');
        }
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));

        (new ErpLocationAssign())->where([
            ['site_id', '=', $this->site_id],
            ['location_id', '=', $locationId],
        ])->delete();

        foreach ($uids as $uid) {
            ErpLocationAssign::create([
                'site_id' => $this->site_id,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'uid' => $uid,
            ]);
        }
        return true;
    }

    /**
     * 给某员工设置负责库位（整组替换：传入的 locations 即该员工的全部库位）
     * locations 形如 [['warehouse_id'=>1,'location_id'=>10], ...]
     */
    public function setStaffLocations(int $uid, array $locations): bool
    {
        if ($uid <= 0) {
            throw new CommonException('请选择员工');
        }
        (new ErpLocationAssign())->where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $uid],
        ])->delete();

        foreach ($locations as $loc) {
            $locationId = (int)($loc['location_id'] ?? 0);
            if ($locationId <= 0) {
                continue;
            }
            ErpLocationAssign::create([
                'site_id' => $this->site_id,
                'warehouse_id' => (int)($loc['warehouse_id'] ?? 0),
                'location_id' => $locationId,
                'uid' => $uid,
            ]);
        }
        return true;
    }

    /**
     * 仓库ID→名、库位ID→名 映射
     */
    protected function buildLocationNameMap(): array
    {
        $map = ['warehouse' => [], 'location' => []];
        foreach ($this->getWarehouseTree()['warehouses'] as $w) {
            $map['warehouse'][(int)($w['id'] ?? 0)] = (string)($w['warehouse_name'] ?? '');
            foreach ((array)($w['locations'] ?? []) as $loc) {
                $map['location'][(int)($loc['id'] ?? 0)] = (string)($loc['location_name'] ?? '');
            }
        }
        return $map;
    }
}
