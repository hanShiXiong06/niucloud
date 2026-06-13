<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\service\admin;

use addon\hsx_device_asset\app\model\DeviceAssetLocationAssign;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 库位责任分配服务（管理员侧）
 *
 * 管理员把 ERP 库位分配给员工（人↔库位 多对多）；员工端「我的待办」据此过滤。
 * ERP 仓库/库位通过标准事件向 ERP 查询，解耦：ERP 未装则无人应答 → 库位为空，前端给提示。
 */
class DeviceAssetAssignService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new DeviceAssetLocationAssign();
    }

    /**
     * ERP 仓库/库位树（与回收侧 saleDestinationOptions 同一套取数）
     */
    public function getWarehouseTree(): array
    {
        $warehouses = [];
        $erpConnected = false;
        try {
            $raw = (array)event('GetErpWarehouseList', ['site_id' => $this->site_id]);
            foreach ($raw as $r) {
                if (is_array($r)) {
                    $erpConnected = true;
                    $warehouses = array_values($r);
                    break;
                }
            }
        } catch (\Throwable $e) {
            $erpConnected = false;
            $warehouses = [];
        }

        if (empty($warehouses)) {
            $cls = '\\addon\\hsx_erp\\app\\service\\admin\\ErpWarehouseService';
            if (class_exists($cls)) {
                try {
                    $list = (new $cls())->getOptions();
                    if (is_array($list)) {
                        $erpConnected = true;
                        $warehouses = array_values($list);
                    }
                } catch (\Throwable $e) {
                    // ERP 在场但取数失败：保持已连接判断，仓库留空
                }
            }
        }

        return ['warehouses' => $warehouses, 'erp_connected' => $erpConnected];
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
        $query = (new DeviceAssetLocationAssign())->where([['site_id', '=', $this->site_id]]);
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

        // 员工名映射
        $uids = array_values(array_unique(array_map(static fn($r) => (int)$r['uid'], $rows)));
        $userMap = [];
        if (!empty($uids)) {
            foreach (SysUser::whereIn('uid', $uids)->field('uid,username,real_name')->select()->toArray() as $u) {
                $userMap[(int)$u['uid']] = (string)($u['real_name'] ?: $u['username'] ?: ('员工#' . $u['uid']));
            }
        }
        // 库位名映射（从 ERP 树补全，避免快照过期）
        $locationNameMap = $this->buildLocationNameMap();

        return array_map(function ($r) use ($userMap, $locationNameMap) {
            $lid = (int)$r['location_id'];
            return [
                'id' => (int)$r['id'],
                'warehouse_id' => (int)$r['warehouse_id'],
                'location_id' => $lid,
                'location_name' => $locationNameMap[$lid] ?? '',
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

        (new DeviceAssetLocationAssign())->where([
            ['site_id', '=', $this->site_id],
            ['location_id', '=', $locationId],
        ])->delete();

        foreach ($uids as $uid) {
            DeviceAssetLocationAssign::create([
                'site_id' => $this->site_id,
                'warehouse_id' => $warehouseId,
                'location_id' => $locationId,
                'uid' => $uid,
            ]);
        }
        return true;
    }

    /**
     * 给某员工设置负责库位（整组替换：传入的 location_ids 即该员工的全部库位）
     * locations 形如 [['warehouse_id'=>1,'location_id'=>10], ...]
     */
    public function setStaffLocations(int $uid, array $locations): bool
    {
        if ($uid <= 0) {
            throw new CommonException('请选择员工');
        }
        (new DeviceAssetLocationAssign())->where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $uid],
        ])->delete();

        foreach ($locations as $loc) {
            $locationId = (int)($loc['location_id'] ?? 0);
            if ($locationId <= 0) {
                continue;
            }
            DeviceAssetLocationAssign::create([
                'site_id' => $this->site_id,
                'warehouse_id' => (int)($loc['warehouse_id'] ?? 0),
                'location_id' => $locationId,
                'uid' => $uid,
            ]);
        }
        return true;
    }

    /**
     * 库位ID → 名称 映射（取自 ERP 仓库树）
     */
    protected function buildLocationNameMap(): array
    {
        $map = [];
        $tree = $this->getWarehouseTree();
        foreach ($tree['warehouses'] as $w) {
            foreach ((array)($w['locations'] ?? []) as $loc) {
                $map[(int)($loc['id'] ?? 0)] = (string)($loc['location_name'] ?? '');
            }
        }
        return $map;
    }
}
