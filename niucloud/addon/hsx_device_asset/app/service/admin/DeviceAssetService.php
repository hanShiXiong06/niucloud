<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\service\admin;

use addon\hsx_device_asset\app\dict\DeviceAssetDict;
use addon\hsx_device_asset\app\model\DeviceAssetItem;
use addon\hsx_device_asset\app\model\DeviceAssetMedia;
use addon\hsx_device_asset\app\model\DeviceAssetOperationLog;
use addon\hsx_device_asset\app\model\DeviceAssetPhotoTask;
use addon\hsx_device_asset\app\model\DeviceAssetPriceOrder;
use addon\hsx_device_asset\app\model\DeviceAssetLocationAssign;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\model\order\RecycleDevice;
use app\model\sys\SysUserRole;
use core\base\BaseAdminService;
use core\exception\CommonException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\facade\Db;

/**
 * 设备资产中台服务
 */
class DeviceAssetService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new DeviceAssetItem();
    }

    /**
     * 回收完成后可进入资产中台的设备池。
     */
    /**
     * 是否可查看全部（管理员）。属 is_admin 组的用户看全部，其余员工只看自己负责库位。
     * 说明：v1 以「is_admin 组」为闸门；如需更细粒度（按角色/权限节点放行）后续可扩展。
     */
    protected function canViewAll(): bool
    {
        return SysUserRole::where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
            ['is_admin', '=', 1],
        ])->count() > 0;
    }

    /**
     * 当前用户的库位过滤范围。
     * null = 不限制（管理员看全部）；[-1] = 无任何负责库位（看不到任何设备）；否则为负责的库位ID集合。
     */
    protected function scopedLocationIds(): ?array
    {
        if ($this->canViewAll()) {
            return null;
        }
        // 库位责任以 ERP 的 erp_location_assign 为唯一来源（中台不再自管，避免两套配置冲突）。
        // ERP 未安装/无此表时不限制（看全部）。
        try {
            $ids = Db::name('erp_location_assign')->where([
                ['site_id', '=', $this->site_id],
                ['uid', '=', $this->uid],
            ])->column('location_id');
        } catch (\Throwable $e) {
            return null;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return empty($ids) ? [-1] : $ids;
    }

    public function getImportableDevices(array $where = []): array
    {
        $query = (new RecycleDevice())
            ->where([['site_id', '=', $this->site_id]])
            ->whereIn('status', $this->allowedRecycleStatuses($where))
            ->with([
                'order' => function ($query) {
                    $query->field('id,order_no,member_id,status,create_at');
                },
                'priceUser' => function ($query) {
                    $query->field('uid,username,real_name');
                },
            ])
            ->field('id,site_id,order_id,imei,imei2,sn,model,category_id,capacity,color,status,initial_price,final_price,sell_price,check_result,check_result_seller,check_result_buyer,check_images,check_images_seller,check_images_buyer,check_at,price_uid,create_at,update_at')
            ->order('update_at desc,id desc')
            ->append(['status_name', 'category_name']);

        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($query) use ($keyword) {
                $query->whereLike('imei|imei2|sn|model', '%' . $keyword . '%')
                    ->whereOr('id', '=', is_numeric($keyword) ? (int)$keyword : 0);
            });
        }
        if (!empty($where['imei'])) {
            $query->whereLike('imei|imei2|sn', '%' . trim((string)$where['imei']) . '%');
        }
        if (!empty($where['model'])) {
            $query->whereLike('model', '%' . trim((string)$where['model']) . '%');
        }
        if (!empty($where['category_id'])) {
            $query->where('category_id', '=', (int)$where['category_id']);
        }
        if (!empty($where['update_at']) && is_array($where['update_at']) && count($where['update_at']) === 2) {
            $query->whereBetweenTime('update_at', $where['update_at'][0], $where['update_at'][1]);
        }

        $importedDeviceIds = (new DeviceAssetItem())
            ->where([['site_id', '=', $this->site_id]])
            ->column('device_id');
        if (!empty($importedDeviceIds)) {
            $query->whereNotIn('id', $importedDeviceIds);
        }

        // 员工只看自己负责库位的待入库设备（按回收定价时选的目标库位）；管理员看全部
        $scope = $this->scopedLocationIds();
        if ($scope !== null) {
            $query->whereIn('target_location_id', $scope);
        }

        return $this->pageQuery($query);
    }

    /**
     * 资产列表。
     */
    public function getPage(array $where = []): array
    {
        $query = (new DeviceAssetItem())
            ->where([['site_id', '=', $this->site_id]])
            ->with([
                'recycleDevice' => function ($query) {
                    $query->field('id,site_id,order_id,imei,imei2,sn,model,capacity,color,initial_price,final_price,sell_price,check_result,check_result_seller,check_result_buyer,check_images,check_images_seller,check_images_buyer,check_at');
                },
            ])
            ->field('*')
            ->order('id desc');

        if (!empty($where['keyword'])) {
            $keyword = trim((string)$where['keyword']);
            $query->where(function ($query) use ($keyword) {
                $query->whereLike('asset_no|imei|imei2|sn|model', '%' . $keyword . '%')
                    ->whereOr('device_id', '=', is_numeric($keyword) ? (int)$keyword : 0);
            });
        }
        foreach (['status', 'photo_status', 'price_status', 'export_status'] as $field) {
            if (!empty($where[$field])) {
                $query->where($field, '=', (string)$where[$field]);
            }
        }
        $this->applyTaskType($query, (string)($where['task_type'] ?? ''));
        if (!empty($where['category_id'])) {
            $query->where('category_id', '=', (int)$where['category_id']);
        }
        if (!empty($where['create_at']) && is_array($where['create_at']) && count($where['create_at']) === 2) {
            $query->whereBetweenTime('create_at', $where['create_at'][0], $where['create_at'][1]);
        }

        // 员工只看自己负责库位的资产；管理员看全部
        $scope = $this->scopedLocationIds();
        if ($scope !== null) {
            $query->whereIn('location_id', $scope);
        }

        $page = $this->pageQuery($query);
        // 修复存量：库位为空但有 ERP 资产关联的，从 ERP 解析仓库/库位并回填（设库位/调拨弹框即可正确反显）
        if (!empty($page['data']) && is_array($page['data'])) {
            $this->backfillLocationsFromErp($page['data']);
        }
        return $page;
    }

    /**
     * 把库位为空、但有 ERP 资产关联(ext_json.erp_asset_id)的资产，
     * 从 ERP 资产 + 仓库/库位表批量解析名称并回填到中台资产(持久化一次，修存量0数据)。
     * 故障隔离：ERP 不可用/异常时静默跳过，不影响列表返回。
     * @param array $rows 引用：页数据行，会就地补上 warehouse 与 location 相关字段
     */
    private function backfillLocationsFromErp(array &$rows): void
    {
        try {
            $erpAssetIds = [];
            foreach ($rows as $row) {
                if ($this->locationComplete($row)) {
                    continue;
                }
                $ext = is_array($row['ext_json'] ?? null) ? $row['ext_json'] : [];
                $eid = (int)($ext['erp_asset_id'] ?? 0);
                if ($eid > 0) {
                    $erpAssetIds[$eid] = $eid;
                }
            }
            if (empty($erpAssetIds)) {
                return;
            }

            $erpAssetCls = '\\addon\\hsx_erp\\app\\model\\ErpAsset';
            if (!class_exists($erpAssetCls)) {
                return; // ERP 未安装
            }
            $erpAssets = $erpAssetCls::where([['site_id', '=', $this->site_id]])
                ->whereIn('id', array_values($erpAssetIds))
                ->field('id,warehouse_id,location_id')
                ->select()->toArray();
            if (empty($erpAssets)) {
                return;
            }
            $whIds = array_values(array_unique(array_filter(array_column($erpAssets, 'warehouse_id'))));
            $locIds = array_values(array_unique(array_filter(array_column($erpAssets, 'location_id'))));
            $whNames = $this->idNameMap('\\addon\\hsx_erp\\app\\model\\ErpWarehouse', $whIds, 'warehouse_name');
            $locNames = $this->idNameMap('\\addon\\hsx_erp\\app\\model\\ErpWarehouseLocation', $locIds, 'location_name');

            $erpById = [];
            foreach ($erpAssets as $a) {
                $erpById[(int)$a['id']] = $a;
            }

            $now = time();
            foreach ($rows as &$row) {
                if ($this->locationComplete($row)) {
                    continue;
                }
                $ext = is_array($row['ext_json'] ?? null) ? $row['ext_json'] : [];
                $eid = (int)($ext['erp_asset_id'] ?? 0);
                $erp = $erpById[$eid] ?? null;
                if (!$erp) {
                    continue;
                }
                $wid = (int)$erp['warehouse_id'];
                $lid = (int)$erp['location_id'];
                if ($wid <= 0 && $lid <= 0) {
                    continue;
                }
                $update = ['update_at' => $now];
                if ($wid > 0) {
                    $update['warehouse_id'] = $wid;
                    $update['warehouse_name'] = (string)($whNames[$wid] ?? '');
                }
                if ($lid > 0) {
                    $update['location_id'] = $lid;
                    $update['location_name'] = (string)($locNames[$lid] ?? '');
                }
                // 回填响应行
                $row = array_merge($row, $update);
                // 持久化(一次性修存量)
                DeviceAssetItem::where([['site_id', '=', $this->site_id], ['id', '=', (int)$row['id']]])->update($update);
            }
            unset($row);
        } catch (\Throwable $e) {
            // 静默跳过
        }
    }

    private function locationComplete(array $row): bool
    {
        return (int)($row['warehouse_id'] ?? 0) > 0
            && (string)($row['warehouse_name'] ?? '') !== ''
            && (int)($row['location_id'] ?? 0) > 0
            && (string)($row['location_name'] ?? '') !== '';
    }

    private function idNameMap(string $modelClass, array $ids, string $nameField): array
    {
        $map = [];
        if (empty($ids) || !class_exists($modelClass)) {
            return $map;
        }
        $rows = $modelClass::where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $ids)->field('id,' . $nameField)->select()->toArray();
        foreach ($rows as $r) {
            $map[(int)$r['id']] = (string)($r[$nameField] ?? '');
        }
        return $map;
    }

    public function getTaskStats(): array
    {
        $scope = $this->scopedLocationIds();

        $assetQuery = (new DeviceAssetItem())->where([["site_id", "=", $this->site_id]]);
        if ($scope !== null) {
            $assetQuery->whereIn('location_id', $scope);
        }
        // 池子排除「已导入」用全量资产的 device_id（不受库位范围影响），避免已入库设备漏排
        $importedDeviceIds = (new DeviceAssetItem())
            ->where([["site_id", "=", $this->site_id]])
            ->column("device_id");
        $poolQuery = (new RecycleDevice())
            ->where([["site_id", "=", $this->site_id]])
            ->whereIn("status", $this->allowedRecycleStatuses([]));
        if (!empty($importedDeviceIds)) {
            $poolQuery->whereNotIn("id", $importedDeviceIds);
        }
        if ($scope !== null) {
            $poolQuery->whereIn('target_location_id', $scope);
        }

        return [
            "pending" => $this->taskTypeCount(clone $assetQuery, "pending"),
            "pool" => (int)$poolQuery->count(),
            "photo" => $this->taskTypeCount(clone $assetQuery, "photo"),
            "price" => $this->taskTypeCount(clone $assetQuery, "price"),
            "completed" => $this->taskTypeCount(clone $assetQuery, "completed"),
            "total" => (int)(clone $assetQuery)->count(),
        ];
    }

    public function getInfo(int $id): array
    {
        $asset = (new DeviceAssetItem())
            ->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $id],
            ])
            ->with(['media', 'photoTasks', 'priceOrders', 'logs', 'recycleDevice'])
            ->find();
        if (empty($asset)) {
            throw new CommonException('资产不存在');
        }
        return $this->enrichAssetQc($asset->toArray());
    }

    /**
     * 质检数据增强(读时, 不改库):
     *  1) recycleDevice.info.check_meta.result_items 逐项打级别 severity(按选项文本实时查 recycle 字典),
     *     并备好 abnormal_items(异常在前)/ severity_summary 计数 —— 供 CheckResultPanel 直接渲染;
     *  2) check_summary 的 容量/颜色 由选项 ID 解析成 label(查 recycle 该模板的选项)。
     * 级别字典/模板选项均属 hsx_recycle, 同库直查(中台本就深读回收数据)。
     * @param array $data
     * @return array
     */
    private function enrichAssetQc(array $data): array
    {
        $rd = $this->daToArr($data['recycleDevice'] ?? null);
        $templateId = (int)($rd['check_template_id'] ?? 0);

        // 1) result_items 级别
        $info = $this->daToArr($rd['info'] ?? null);
        $checkMeta = $this->daToArr($info['check_meta'] ?? null);
        $items = is_array($checkMeta['result_items'] ?? null) ? $checkMeta['result_items'] : [];
        if (!empty($items)) {
            $sevMap = $this->recycleSeverityMap();
            $sevOrder = ['abnormal' => 0, 'general' => 1, 'normal' => 2];
            $flagged = [];
            $count = ['abnormal' => 0, 'general' => 0, 'normal' => 0];
            foreach ($items as &$it) {
                if (!is_array($it)) {
                    continue;
                }
                $labels = is_array($it['labels'] ?? null) ? $it['labels'] : [];
                $sev = $sevMap[mb_strtolower(trim((string)($labels[0] ?? '')))] ?? 'normal';
                $it['severity'] = $sev;
                if (is_array($it['option_items'] ?? null)) {
                    foreach ($it['option_items'] as &$oi) {
                        if (is_array($oi)) {
                            $oi['severity'] = $sevMap[mb_strtolower(trim((string)($oi['label'] ?? '')))] ?? 'normal';
                        }
                    }
                    unset($oi);
                }
                $count[$sev] = ($count[$sev] ?? 0) + 1;
                if ($sev !== 'normal') {
                    $flagged[] = $it;
                }
            }
            unset($it);
            usort($flagged, static fn($a, $b) => ($sevOrder[$a['severity'] ?? 'normal'] ?? 9) <=> ($sevOrder[$b['severity'] ?? 'normal'] ?? 9));
            $checkMeta['result_items'] = $items;
            $checkMeta['abnormal_items'] = $flagged;
            $checkMeta['severity_summary'] = $count;
            $checkMeta['summary_fields'] = $this->buildAssetSummaryFields($templateId, $items, $rd);
            $info['check_meta'] = $checkMeta;
            $rd['info'] = $info;
            $data['recycleDevice'] = $rd;
        }

        // 2) check_summary 容量/颜色 ID → label
        $cs = $this->daToArr($data['check_summary'] ?? null);
        if ($templateId > 0 && (isset($cs['容量']) || isset($cs['颜色']))) {
            $labelMap = $this->recycleBasicLabelMap($templateId);
            if (isset($cs['容量'])) {
                $cs['容量'] = $labelMap['capacity'][(string)$cs['容量']] ?? $cs['容量'];
            }
            if (isset($cs['颜色'])) {
                $cs['颜色'] = $labelMap['color'][(string)$cs['颜色']] ?? $cs['颜色'];
            }
            $data['check_summary'] = $cs;
        }
        return $data;
    }

    /**
     * 质检模板"设备摘要"字段(extra_config.summary_visible=1, ≤5)→ 突出项,匹配质检值+级别。
     * @param int $templateId
     * @param array $resultItems 已带 severity 的 result_items
     * @param array $rd recycleDevice(含 capacity/color)
     * @return array
     */
    private function buildAssetSummaryFields(int $templateId, array $resultItems, array $rd): array
    {
        if ($templateId <= 0) {
            return [];
        }
        $fields = Db::name('recycle_check_field')
            ->where('site_id', '=', $this->site_id)
            ->where('template_id', '=', $templateId)
            ->where('is_show', '=', 1)
            ->field('field_key,field_name,sort,extra_config')
            ->order('sort asc,id asc')->select()->toArray();
        $picked = [];
        foreach ($fields as $f) {
            $ec = $this->daToArr($f['extra_config'] ?? null);
            if ((int)($ec['summary_visible'] ?? ($ec['show_in_summary'] ?? 0)) === 1) {
                $picked[] = $f;
                if (count($picked) >= 5) {
                    break;
                }
            }
        }
        if (empty($picked)) {
            return [];
        }
        $lookup = [];
        foreach ($resultItems as $it) {
            if (!is_array($it)) {
                continue;
            }
            $fk = (string)($it['field_key'] ?? '');
            if ($fk === '') {
                continue;
            }
            $labels = is_array($it['labels'] ?? null) ? $it['labels'] : [];
            $lookup[$fk] = ['label' => (string)($labels[0] ?? ($it['text'] ?? '')), 'severity' => (string)($it['severity'] ?? 'normal')];
        }
        // capacity/color 不在 result_items, 取设备值 + 模板选项解析
        $basic = $this->recycleBasicLabelMap($templateId);
        foreach (['capacity', 'color'] as $bk) {
            if (isset($lookup[$bk])) {
                continue;
            }
            $val = (string)($rd[$bk] ?? '');
            if ($val !== '') {
                $lookup[$bk] = ['label' => $basic[$bk][$val] ?? $val, 'severity' => 'normal'];
            }
        }
        $out = [];
        foreach ($picked as $f) {
            $fk = (string)$f['field_key'];
            $v = $lookup[$fk] ?? ['label' => '', 'severity' => 'normal'];
            $out[] = [
                'field_key'  => $fk,
                'field_name' => (string)$f['field_name'],
                'label'      => $v['label'],
                'severity'   => $v['severity'],
            ];
        }
        return $out;
    }

    /** 模型 $json 字段(stdClass/json字符串/数组)统一转深层数组 */
    private function daToArr($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $d = json_decode($value, true);
            return is_array($d) ? $d : [];
        }
        if (is_object($value)) {
            $d = json_decode(json_encode($value), true);
            return is_array($d) ? $d : [];
        }
        return [];
    }

    /** recycle 选项级别字典: lower(text) => severity */
    private function recycleSeverityMap(): array
    {
        $rows = Db::name('recycle_check_dict')
            ->where('site_id', '=', $this->site_id)
            ->where('dict_type', '=', 'option')
            ->field('text,severity')->select()->toArray();
        $map = [];
        foreach ($rows as $r) {
            $t = mb_strtolower(trim((string)($r['text'] ?? '')));
            if ($t !== '') {
                $map[$t] = (string)($r['severity'] ?? 'normal');
            }
        }
        return $map;
    }

    /** recycle 某模板的 capacity/color 选项: [field_key => [option_value => option_label]] */
    private function recycleBasicLabelMap(int $templateId): array
    {
        $fields = Db::name('recycle_check_field')
            ->where('site_id', '=', $this->site_id)
            ->where('template_id', '=', $templateId)
            ->whereIn('field_key', ['capacity', 'color'])
            ->field('id,field_key')->select()->toArray();
        if (empty($fields)) {
            return [];
        }
        $fkById = [];
        foreach ($fields as $f) {
            $fkById[(int)$f['id']] = (string)$f['field_key'];
        }
        $opts = Db::name('recycle_check_option')
            ->where('site_id', '=', $this->site_id)
            ->whereIn('field_id', array_keys($fkById))
            ->field('field_id,option_value,option_label')->select()->toArray();
        $map = [];
        foreach ($opts as $o) {
            $fk = $fkById[(int)$o['field_id']] ?? '';
            if ($fk !== '') {
                $map[$fk][(string)$o['option_value']] = (string)$o['option_label'];
            }
        }
        return $map;
    }

    /**
     * 从回收设备批量导入资产。
     */
    public function importDevices(array $deviceIds): array
    {
        $deviceIds = array_values(array_unique(array_filter(array_map('intval', $deviceIds))));
        if (empty($deviceIds)) {
            throw new CommonException('请选择需要入库的设备');
        }

        $devices = (new RecycleDevice())
            ->where([['site_id', '=', $this->site_id]])
            ->whereIn('id', $deviceIds)
            ->whereIn('status', [RecycleOrderDict::DEVICE_STATUS_RECYCLED, RecycleOrderDict::DEVICE_STATUS_CONSIGNED])
            ->select()
            ->toArray();

        if (count($devices) !== count($deviceIds)) {
            throw new CommonException('部分设备不存在或状态不允许入库');
        }

        $exists = (new DeviceAssetItem())
            ->where([['site_id', '=', $this->site_id]])
            ->whereIn('device_id', $deviceIds)
            ->column('device_id');
        if (!empty($exists)) {
            throw new CommonException('存在已入库设备，请勿重复导入：' . implode(',', $exists));
        }

        $completedEvent = [];
        Db::startTrans();
        try {
            $items = [];
            foreach ($devices as $device) {
                $asset = DeviceAssetItem::create($this->buildAssetData($device));
                $asset->save(['asset_no' => $this->makeAssetNo((int)$asset->id)]);
                $this->writeLog((int)$asset->id, (int)$device['id'], DeviceAssetDict::ACTION_IMPORT, [
                    'source_status' => $device['status'] ?? '',
                    'recycle_order_id' => $device['order_id'] ?? 0,
                ]);
                $items[] = $asset->refresh()->toArray();
            }
            Db::commit();
            return ['count' => count($items), 'items' => $items];
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 扫码导入。二维码优先按 device_id，其次按 IMEI/SN。
     */
    public function scanImport(string $keyword): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            throw new CommonException('请输入设备码、IMEI 或 SN');
        }

        $device = (new RecycleDevice())
            ->where([['site_id', '=', $this->site_id]])
            ->whereIn('status', [RecycleOrderDict::DEVICE_STATUS_RECYCLED, RecycleOrderDict::DEVICE_STATUS_CONSIGNED])
            ->where(function ($query) use ($keyword) {
                if (is_numeric($keyword)) {
                    $query->where('id', '=', (int)$keyword)
                        ->whereOr('imei', '=', $keyword)
                        ->whereOr('imei2', '=', $keyword)
                        ->whereOr('sn', '=', $keyword);
                    return;
                }
                $query->where('imei', '=', $keyword)
                    ->whereOr('imei2', '=', $keyword)
                    ->whereOr('sn', '=', $keyword);
            })
            ->find();

        if (empty($device)) {
            throw new CommonException('未找到可入库设备');
        }

        $asset = (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
            ['device_id', '=', (int)$device->id],
        ])->find();

        if (!empty($asset)) {
            return ['created' => false, 'asset' => $asset->toArray()];
        }

        $result = $this->importDevices([(int)$device->id]);
        return ['created' => true, 'asset' => $result['items'][0] ?? []];
    }

    public function createPhotoTask(int $assetId, array $data = []): array
    {
        $asset = $this->getAsset($assetId);
        $task = DeviceAssetPhotoTask::create([
            'site_id' => $this->site_id,
            'task_no' => $this->makeBizNo('PT'),
            'asset_id' => $assetId,
            'device_id' => (int)$asset->device_id,
            'status' => DeviceAssetDict::TASK_STATUS_PROCESSING,
            'source' => (string)($data['source'] ?? 'pc'),
            'station_id' => (string)($data['station_id'] ?? ''),
            'camera_job_id' => (string)($data['camera_job_id'] ?? ''),
            'operator_uid' => $this->uid,
            'started_at' => time(),
            'remark' => (string)($data['remark'] ?? ''),
        ]);

        $asset->save([
            'status' => DeviceAssetDict::STATUS_PHOTOING,
            'photo_status' => DeviceAssetDict::PHOTO_STATUS_PHOTOING,
        ]);

        $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_PHOTO_TASK_CREATE, [
            'task_id' => (int)$task->id,
            'source' => $task->source,
            'station_id' => $task->station_id,
        ]);

        return $task->toArray();
    }

    public function saveMedia(int $assetId, array $data): array
    {
        $asset = $this->getAsset($assetId);
        $mediaList = $data['media'] ?? [];
        if (!is_array($mediaList) || empty($mediaList)) {
            $mediaList = [[
                'url' => (string)($data['url'] ?? ''),
                'oss_key' => (string)($data['oss_key'] ?? ''),
                'media_type' => (string)($data['media_type'] ?? 'image'),
                'scene' => (string)($data['scene'] ?? 'common'),
                'source' => (string)($data['source'] ?? 'manual'),
                'sort' => (int)($data['sort'] ?? 0),
            ]];
        }

        $rows = [];
        foreach ($mediaList as $item) {
            $url = trim((string)($item['url'] ?? ''));
            if ($url === '') {
                continue;
            }
            $rows[] = [
                'site_id' => $this->site_id,
                'asset_id' => $assetId,
                'device_id' => (int)$asset->device_id,
                'task_id' => (int)($item['task_id'] ?? $data['task_id'] ?? 0),
                'media_type' => in_array(($item['media_type'] ?? 'image'), ['image', 'video'], true) ? (string)$item['media_type'] : 'image',
                'scene' => (string)($item['scene'] ?? 'common'),
                'url' => $url,
                'oss_key' => (string)($item['oss_key'] ?? ''),
                'source' => (string)($item['source'] ?? $data['source'] ?? 'manual'),
                'status' => DeviceAssetDict::MEDIA_STATUS_APPROVED, // 取消复检：上传即视为通过，不再有"待复检"
                'sort' => (int)($item['sort'] ?? 0),
                'operator_uid' => $this->uid,
            ];
        }

        if (empty($rows)) {
            throw new CommonException('请上传有效的图片或视频');
        }

        (new DeviceAssetMedia())->saveAll($rows);
        if (!empty($data['task_id'])) {
            (new DeviceAssetPhotoTask())->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$data['task_id']],
            ])->update(['status' => DeviceAssetDict::TASK_STATUS_REVIEW]);
        }

        $this->refreshMediaCount($assetId);
        $asset->save([
            'status' => DeviceAssetDict::STATUS_PHOTO_REVIEW,
            'photo_status' => DeviceAssetDict::PHOTO_STATUS_REVIEW,
        ]);

        $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_MEDIA_SAVE, [
            'count' => count($rows),
            'task_id' => (int)($data['task_id'] ?? 0),
        ]);

        return $this->getInfo($assetId);
    }

    public function reviewMedia(int $mediaId, string $status, string $reason = ''): array
    {
        if (!in_array($status, [DeviceAssetDict::MEDIA_STATUS_APPROVED, DeviceAssetDict::MEDIA_STATUS_REJECTED], true)) {
            throw new CommonException('媒体复检状态不正确');
        }

        $media = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $mediaId],
        ])->find();
        if (empty($media)) {
            throw new CommonException('图片或视频不存在');
        }

        $media->save([
            'status' => $status,
            'reject_reason' => $status === DeviceAssetDict::MEDIA_STATUS_REJECTED ? $reason : '',
            'operator_uid' => $this->uid,
        ]);

        $this->refreshMediaCount((int)$media->asset_id);
        $rejectedCount = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', (int)$media->asset_id],
            ['status', '=', DeviceAssetDict::MEDIA_STATUS_REJECTED],
        ])->count();
        if ($rejectedCount > 0) {
            (new DeviceAssetItem())->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', (int)$media->asset_id],
            ])->update([
                'status' => DeviceAssetDict::STATUS_PHOTO_REJECTED,
                'photo_status' => DeviceAssetDict::PHOTO_STATUS_REJECTED,
            ]);
        }

        $this->writeLog((int)$media->asset_id, (int)$media->device_id, DeviceAssetDict::ACTION_MEDIA_REVIEW, [
            'media_id' => $mediaId,
            'status' => $status,
            'reason' => $reason,
        ]);

        return $this->getInfo((int)$media->asset_id);
    }

    public function reviewMediaBatch(int $assetId, array $mediaIds, string $status, string $reason = ''): array
    {
        if (!in_array($status, [DeviceAssetDict::MEDIA_STATUS_APPROVED, DeviceAssetDict::MEDIA_STATUS_REJECTED], true)) {
            throw new CommonException('媒体复检状态不正确');
        }
        $mediaIds = array_values(array_unique(array_filter(array_map('intval', $mediaIds))));
        if (empty($mediaIds)) {
            throw new CommonException('请选择需要处理的图片');
        }

        $asset = $this->getAsset($assetId);
        $query = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
        ])->whereIn('id', $mediaIds);
        $count = (int)(clone $query)->count();
        if ($count <= 0) {
            throw new CommonException('未找到可处理图片');
        }

        Db::startTrans();
        try {
            $query->update([
                'status' => $status,
                'reject_reason' => $status === DeviceAssetDict::MEDIA_STATUS_REJECTED ? $reason : '',
                'operator_uid' => $this->uid,
                'update_at' => time(),
            ]);
            $this->refreshMediaCount($assetId);

            $activeCount = (new DeviceAssetMedia())->where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $assetId],
                ['media_type', '=', 'image'],
                ['status', '<>', DeviceAssetDict::MEDIA_STATUS_REJECTED],
            ])->count();
            $assetUpdate = [];
            if ($activeCount <= 0) {
                $assetUpdate = [
                    'status' => DeviceAssetDict::STATUS_PHOTO_REJECTED,
                    'photo_status' => DeviceAssetDict::PHOTO_STATUS_REJECTED,
                ];
            } elseif ($status === DeviceAssetDict::MEDIA_STATUS_APPROVED && $asset->photo_status === DeviceAssetDict::PHOTO_STATUS_REJECTED) {
                $assetUpdate = [
                    'status' => DeviceAssetDict::STATUS_PHOTO_REVIEW,
                    'photo_status' => DeviceAssetDict::PHOTO_STATUS_REVIEW,
                ];
            }
            if (!empty($assetUpdate)) {
                (new DeviceAssetItem())->where([
                    ['site_id', '=', $this->site_id],
                    ['id', '=', $assetId],
                ])->update($assetUpdate);
            }

            $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_MEDIA_REVIEW, [
                'media_ids' => $mediaIds,
                'count' => $count,
                'status' => $status,
                'reason' => $reason,
                'batch' => true,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }

        return $this->getInfo($assetId);
    }

    public function confirmPhotos(int $assetId): array
    {
        $asset = $this->getAsset($assetId);
        // 不再依赖「复检通过」：拍照员当场删掉糊图，留下的(未删除)图片即视为可用，至少一张即可完成拍照
        $imageCount = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
            ['media_type', '=', 'image'],
            ['status', '<>', DeviceAssetDict::MEDIA_STATUS_REJECTED],
        ])->count();
        if ($imageCount <= 0) {
            throw new CommonException('请至少拍并保留一张图片');
        }

        Db::startTrans();
        try {
            $asset->save([
                'status' => DeviceAssetDict::STATUS_WAIT_PRICE,
                'photo_status' => DeviceAssetDict::PHOTO_STATUS_APPROVED,
                'photo_confirm_uid' => $this->uid,
                'photo_confirmed_at' => time(),
            ]);
            (new DeviceAssetPhotoTask())->where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $assetId],
                ['status', '<>', DeviceAssetDict::TASK_STATUS_CANCELLED],
            ])->update([
                'status' => DeviceAssetDict::TASK_STATUS_COMPLETED,
                'completed_at' => time(),
            ]);
            $this->ensurePriceOrder($asset);
            $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_PHOTO_CONFIRM, [
                'image_count' => $imageCount,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }

        return $this->getInfo($assetId);
    }

    public function completePrice(int $assetId, array $data): array
    {
        $asset = $this->getAsset($assetId);
        $salePrice = round((float)($data['sale_price'] ?? 0), 2);
        if ($salePrice <= 0) {
            throw new CommonException('请输入有效的销售价格');
        }

        Db::startTrans();
        try {
            $priceData = [
                'sale_price' => $salePrice,
                'peer_price' => round((float)($data['peer_price'] ?? 0), 2),
                'min_price' => round((float)($data['min_price'] ?? 0), 2),
                'price_remark' => (string)($data['remark'] ?? ''),
                'price_uid' => $this->uid,
                'priced_at' => time(),
                'price_status' => DeviceAssetDict::PRICE_STATUS_COMPLETED,
                'status' => DeviceAssetDict::STATUS_READY_EXPORT,
            ];
            // 定价员隐藏的质检项(买家版不展示);仅当本次提交携带时才更新,避免重新定价误清空
            if (array_key_exists('hidden_check_keys', $data)) {
                $priceData['hidden_check_keys'] = json_encode($this->normalizeHiddenCheckKeys($data['hidden_check_keys']), JSON_UNESCAPED_UNICODE);
            }
            $asset->save($priceData);

            $order = (new DeviceAssetPriceOrder())->where([
                ['site_id', '=', $this->site_id],
                ['asset_id', '=', $assetId],
            ])->order('id desc')->find();

            $orderData = [
                'status' => DeviceAssetDict::PRICE_STATUS_COMPLETED,
                'sale_price' => $priceData['sale_price'],
                'peer_price' => $priceData['peer_price'],
                'min_price' => $priceData['min_price'],
                'cost_price' => (float)$asset->recycle_final_price,
                'remark' => $priceData['price_remark'],
                'priced_uid' => $this->uid,
                'priced_at' => time(),
            ];
            if (empty($order)) {
                DeviceAssetPriceOrder::create(array_merge($orderData, [
                    'site_id' => $this->site_id,
                    'order_no' => $this->makeBizNo('PO'),
                    'asset_id' => $assetId,
                    'device_id' => (int)$asset->device_id,
                    'assign_uid' => 0,
                ]));
            } else {
                $order->save($orderData);
            }

            $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_PRICE_COMPLETE, $orderData);
            $completedEvent = $this->buildPriceCompletedEvent($asset, (int)$assetId);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
        event('DeviceAssetPriceCompleted', $completedEvent);

        return $this->getInfo($assetId);
    }

    /**
     * 构建"定价完成"事件（price 与 重新推送 共用）。
     * 价格/型号/质检/图片均从资产当前数据取，保证重推也是最新。
     */
    private function buildPriceCompletedEvent(DeviceAssetItem $asset, int $assetId): array
    {
        // 本资产可用图片：与中台导出口径一致——排除已退回(rejected)，待复检(pending)与已通过(approved)都带
        $mediaRows = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
            ['media_type', '=', 'image'],
            ['status', '<>', DeviceAssetDict::MEDIA_STATUS_REJECTED],
        ])->order('sort asc, id asc')->field('url,scene')->select()->toArray();
        // images: 平铺 url(封面/列表用,兼容旧逻辑); media: 带分类(scene)的结构,供商品详情按 正面/反面/侧面/瑕疵 分组
        $images = array_values(array_filter(array_map(static fn($m) => (string)($m['url'] ?? ''), $mediaRows)));
        $media = array_map(static fn($m) => [
            'url'   => (string)($m['url'] ?? ''),
            'scene' => (string)($m['scene'] ?? ''),
        ], array_values(array_filter($mediaRows, static fn($m) => (string)($m['url'] ?? '') !== '')));

        return [
            'event_name' => 'device_asset.price.completed.v1',
            'site_id'    => $this->site_id,
            'asset_id'   => $assetId,
            'device_id'  => (int)$asset->device_id,
            'operator'   => ['id' => $this->uid, 'name' => $this->username ?: ''],
            'payload'    => [
                'erp_asset_id'    => (int)(((array)$asset->ext_json)['erp_asset_id'] ?? 0),
                'site_id'         => $this->site_id,
                'sale_price'      => (float)$asset->sale_price,
                'peer_price'      => (float)$asset->peer_price,
                'min_price'       => (float)$asset->min_price,
                'cost_price'      => (float)$asset->recycle_final_price,
                'remark'          => (string)$asset->price_remark,
                'model_name'      => (string)$asset->model,
                'imei'            => (string)$asset->imei,
                'qc_info'         => $this->buyerFacingCheckSummary($asset), // 买家版:剔除定价员隐藏项(原始 check_summary 不动)
                'hidden_check_keys' => $this->normalizeHiddenCheckKeys($asset->hidden_check_keys ?? null), // 定价员隐藏的质检字段名,商城据此过滤 qc_report
                'images'          => $images,
                'media'           => $media, // 带分类(scene=正面/反面/侧面/瑕疵)的图片,供商品详情分组展示
            ],
        ];
    }

    /**
     * 重新推送：把已定价资产的最新数据再发一次事件，商城据此幂等更新货源。
     */
    public function rePushPriceCompleted(int $assetId): array
    {
        $asset = $this->getAsset($assetId);
        if ((string)$asset->price_status !== DeviceAssetDict::PRICE_STATUS_COMPLETED) {
            throw new CommonException('该设备尚未完成定价，无法推送');
        }
        $event = $this->buildPriceCompletedEvent($asset, $assetId);
        event('DeviceAssetPriceCompleted', $event);
        return [
            'pushed'       => true,
            'erp_asset_id' => $event['payload']['erp_asset_id'],
        ];
    }

    /**
     * 设置 / 修改资产的库位（物理库位入库才定，中台可随时归位与改位）。
     * 库位是责任过滤的依据：归到某库位后，负责该库位的员工即可在「我的待办」看到。
     */
    public function setLocation(int $assetId, array $data): array
    {
        $asset = $this->getAsset($assetId);
        $locationId = (int)($data['location_id'] ?? 0);
        if ($locationId <= 0) {
            throw new CommonException('请选择库位');
        }
        $warehouseId = (int)($data['warehouse_id'] ?? 0);
        // 与调拨一致：目标仓「允许调入」开关关闭则禁止放入（仓库管理里配置）。
        // ERP 未装/查不到时不拦截（兜底放行）。
        $this->assertWarehouseAllowInbound($warehouseId);

        $payload = [
            'warehouse_id' => $warehouseId,
            'warehouse_name' => (string)($data['warehouse_name'] ?? ''),
            'location_id' => $locationId,
            'location_name' => (string)($data['location_name'] ?? ''),
        ];
        $asset->save($payload);

        $this->writeLog($assetId, (int)$asset->device_id, DeviceAssetDict::ACTION_SET_LOCATION, $payload);

        return $this->getInfo($assetId);
    }

    /**
     * 校验目标仓库「允许调入」（与调拨一致，由仓库管理配置）。
     * allow_inbound=0 拒绝；ERP 未装或查不到则放行（兜底）。
     */
    private function assertWarehouseAllowInbound(int $warehouseId): void
    {
        if ($warehouseId <= 0) {
            return;
        }
        $cls = '\\addon\\hsx_erp\\app\\model\\ErpWarehouse';
        if (!class_exists($cls)) {
            return;
        }
        try {
            $row = $cls::where([['site_id', '=', $this->site_id], ['id', '=', $warehouseId]])->findOrEmpty();
        } catch (\Throwable $e) {
            return;
        }
        if (!$row->isEmpty() && (int)($row->allow_inbound ?? 1) !== 1) {
            throw new CommonException('该仓库不允许调入（可在仓库管理中开启「允许调入」）');
        }
    }

    public function exportExcel(array $where = []): string
    {
        $query = (new DeviceAssetItem())->where([['site_id', '=', $this->site_id]]);
        if (!empty($where['asset_ids']) && is_array($where['asset_ids'])) {
            $query->whereIn('id', array_filter(array_map('intval', $where['asset_ids'])));
        } else {
            if (!empty($where['status'])) {
                $query->where('status', '=', (string)$where['status']);
            }
            if (!empty($where['price_status'])) {
                $query->where('price_status', '=', (string)$where['price_status']);
            }
            if (!empty($where['keyword'])) {
                $keyword = trim((string)$where['keyword']);
                $query->whereLike('asset_no|imei|imei2|sn|model', '%' . $keyword . '%');
            }
        }

        $items = $query->with(['media'])->order('id desc')->select();
        if (count($items) === 0) {
            throw new CommonException('暂无可导出的资产数据');
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['资产编号', '回收设备ID', '回收订单ID', 'IMEI', 'IMEI2', 'SN', '型号', '分类ID', '回收价', '销售价', '同行价', '最低价', '资产状态', '照片状态', '定价状态', '图片数量', '视频数量', '图片链接', '视频链接', '定价备注'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValueByColumnAndRow($index + 1, 1, $header);
        }

        $row = 2;
        $assetIds = [];
        foreach ($items as $asset) {
            $assetIds[] = (int)$asset->id;
            $imageUrls = [];
            $videoUrls = [];
            foreach (($asset->media ?? []) as $media) {
                if (($media['status'] ?? '') === DeviceAssetDict::MEDIA_STATUS_REJECTED) {
                    continue;
                }
                if (($media['media_type'] ?? '') === 'video') {
                    $videoUrls[] = $media['url'] ?? '';
                } else {
                    $imageUrls[] = $media['url'] ?? '';
                }
            }

            $values = [
                $asset->asset_no,
                $asset->device_id,
                $asset->recycle_order_id,
                $asset->imei,
                $asset->imei2,
                $asset->sn,
                $asset->model,
                $asset->category_id,
                $asset->recycle_final_price,
                $asset->sale_price,
                $asset->peer_price,
                $asset->min_price,
                $asset->status_name,
                $asset->photo_status_name,
                $asset->price_status_name,
                $asset->image_count,
                $asset->video_count,
                implode("\n", array_filter($imageUrls)),
                implode("\n", array_filter($videoUrls)),
                $asset->price_remark,
            ];
            foreach ($values as $index => $value) {
                $sheet->setCellValueByColumnAndRow($index + 1, $row, $value);
            }
            $row++;
        }

        foreach (range(1, count($headers)) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $dir = runtime_path() . 'device_asset_export/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filePath = $dir . 'device_asset_' . date('Ymd_His') . '.xlsx';
        (new Xlsx($spreadsheet))->save($filePath);

        (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
        ])->whereIn('id', $assetIds)->update([
            'status' => DeviceAssetDict::STATUS_EXPORTED,
            'export_status' => DeviceAssetDict::EXPORT_STATUS_EXPORTED,
            'exported_at' => time(),
        ]);

        foreach ($assetIds as $assetId) {
            $this->writeLog($assetId, 0, DeviceAssetDict::ACTION_EXPORT, ['file' => basename($filePath)]);
        }

        return $filePath;
    }

    protected function allowedRecycleStatuses(array $where): array
    {
        if (!empty($where['status'])) {
            return [(int)$where['status']];
        }
        return [RecycleOrderDict::DEVICE_STATUS_RECYCLED, RecycleOrderDict::DEVICE_STATUS_CONSIGNED];
    }

    protected function buildAssetData(array $device): array
    {
        return [
            'site_id' => $this->site_id,
            'asset_no' => '',
            'device_id' => (int)$device['id'],
            'recycle_order_id' => (int)($device['order_id'] ?? 0),
            'imei' => (string)($device['imei'] ?? ''),
            'imei2' => (string)($device['imei2'] ?? ''),
            'sn' => (string)($device['sn'] ?? ''),
            'model' => (string)($device['model'] ?? ''),
            'category_id' => (int)($device['category_id'] ?? 0),
            // 目标仓库/库位来自回收定价时的选择，带过来作为中台的库位归属与责任过滤依据
            'warehouse_id' => (int)($device['target_warehouse_id'] ?? 0),
            'warehouse_name' => (string)($device['target_warehouse_name'] ?? ''),
            'location_id' => (int)($device['target_location_id'] ?? 0),
            'location_name' => (string)($device['target_location_name'] ?? ''),
            'source_status' => (string)($device['status'] ?? ''),
            'recycle_final_price' => round((float)($device['final_price'] ?? 0), 2),
            'check_summary' => $this->buildCheckSummary($device),
            'ext_json' => [
                'capacity' => (string)($device['capacity'] ?? ''),
                'color' => (string)($device['color'] ?? ''),
                'initial_price' => round((float)($device['initial_price'] ?? 0), 2),
                'recycle_sell_price' => round((float)($device['sell_price'] ?? 0), 2),
                'check_at' => (int)($device['check_at'] ?? 0),
                'check_images' => (string)($device['check_images'] ?? ''),
                'check_images_seller' => (string)($device['check_images_seller'] ?? ''),
                'check_images_buyer' => (string)($device['check_images_buyer'] ?? ''),
            ],
            'status' => DeviceAssetDict::STATUS_WAIT_PHOTO,
            'photo_status' => DeviceAssetDict::PHOTO_STATUS_WAIT,
            'price_status' => DeviceAssetDict::PRICE_STATUS_WAIT,
            'export_status' => DeviceAssetDict::EXPORT_STATUS_PENDING,
            'imported_by' => $this->uid,
            'imported_at' => time(),
        ];
    }

    protected function normalizeJson($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $json = json_decode($value, true);
            return is_array($json) ? $json : ['raw' => $value];
        }
        return [];
    }

    /** 归一化"隐藏质检项"为字段名字符串数组 */
    protected function normalizeHiddenCheckKeys($value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($value)) {
            return [];
        }
        $keys = [];
        foreach ($value as $k) {
            $k = trim((string)$k);
            if ($k !== '') {
                $keys[$k] = true;
            }
        }
        return array_keys($keys);
    }

    /**
     * 买家版质检报告:在原始 check_summary 基础上剔除定价员隐藏的字段项。
     * 原始 check_summary 不改动(中台/回收/质检员仍看完整);仅上架给商城的 qc_info 用这份精简版。
     * @param object $asset 设备资产模型
     * @return array 过滤后的质检摘要(字段名→值)
     */
    protected function buyerFacingCheckSummary($asset): array
    {
        $summary = $this->daToArr($asset->check_summary ?? null);
        $hidden = $this->normalizeHiddenCheckKeys($asset->hidden_check_keys ?? null);
        if (empty($hidden) || empty($summary)) {
            return $summary;
        }
        $hiddenSet = array_flip($hidden);
        $out = [];
        foreach ($summary as $key => $val) {
            // check_summary 既可能是 {字段名:值} 关联数组, 也可能是 [{label/field_name,...}] 列表; 两种都按字段名剔除
            if (is_array($val)) {
                $label = (string)($val['label'] ?? $val['field_name'] ?? $val['name'] ?? $key);
                if (isset($hiddenSet[$label])) {
                    continue;
                }
                $out[$key] = $val;
            } else {
                if (isset($hiddenSet[(string)$key])) {
                    continue;
                }
                $out[$key] = $val;
            }
        }
        return $out;
    }

    protected function buildCheckSummary(array $device): array
    {
        $summary = [];
        foreach ([
            'check_result_seller' => '卖家质检',
            'check_result_buyer' => '买家质检',
        ] as $field => $label) {
            $value = trim((string)($device[$field] ?? ''));
            if ($value === '') {
                continue;
            }
            $decoded = $this->normalizeJson($value);
            if (!empty($decoded) && !isset($decoded['raw'])) {
                foreach ($decoded as $key => $item) {
                    // 保存时显式剔除「内部质检」，不写入 check_summary
                    if ((string)$key === '内部质检') {
                        continue;
                    }
                    if ($item !== '' && $item !== null) {
                        $summary[(string)$key] = is_array($item) ? json_encode($item, JSON_UNESCAPED_UNICODE) : (string)$item;
                    }
                }
                continue;
            }
            $summary[$label] = $value;
        }
        if (!empty($device['capacity'])) {
            $summary['容量'] = (string)$device['capacity'];
        }
        if (!empty($device['color'])) {
            $summary['颜色'] = (string)$device['color'];
        }
        return $summary;
    }

    protected function taskTypeCount($query, string $taskType): int
    {
        $this->applyTaskType($query, $taskType);
        return (int)$query->count();
    }

    protected function applyTaskType($query, string $taskType): void
    {
        $statusMap = [
            // 中台只做两件事：拍照、定价。完成定价即置 ready_export（待导出=完成）。
            // priced 属"已定价完成"，归入 completed，不再混进待办/待定价。
            'pending' => [
                DeviceAssetDict::STATUS_WAIT_PHOTO,
                DeviceAssetDict::STATUS_PHOTOING,
                DeviceAssetDict::STATUS_PHOTO_REVIEW,
                DeviceAssetDict::STATUS_PHOTO_REJECTED,
                DeviceAssetDict::STATUS_WAIT_PRICE,
            ],
            'photo' => [
                DeviceAssetDict::STATUS_WAIT_PHOTO,
                DeviceAssetDict::STATUS_PHOTOING,
                DeviceAssetDict::STATUS_PHOTO_REVIEW,
                DeviceAssetDict::STATUS_PHOTO_REJECTED,
            ],
            'price' => [
                DeviceAssetDict::STATUS_WAIT_PRICE,
            ],
            'completed' => [
                DeviceAssetDict::STATUS_PRICED,
                DeviceAssetDict::STATUS_READY_EXPORT,
                DeviceAssetDict::STATUS_EXPORTED,
                DeviceAssetDict::STATUS_ARCHIVED,
            ],
        ];
        if (isset($statusMap[$taskType])) {
            $query->whereIn('status', $statusMap[$taskType]);
        }
    }

    protected function getAsset(int $assetId): DeviceAssetItem
    {
        $asset = (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $assetId],
        ])->find();
        if (empty($asset)) {
            throw new CommonException('资产不存在');
        }
        return $asset;
    }

    protected function ensurePriceOrder(DeviceAssetItem $asset): void
    {
        $exists = (new DeviceAssetPriceOrder())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', (int)$asset->id],
            ['status', '<>', DeviceAssetDict::PRICE_STATUS_COMPLETED],
        ])->find();
        if (!empty($exists)) {
            return;
        }

        DeviceAssetPriceOrder::create([
            'site_id' => $this->site_id,
            'order_no' => $this->makeBizNo('PO'),
            'asset_id' => (int)$asset->id,
            'device_id' => (int)$asset->device_id,
            'status' => DeviceAssetDict::PRICE_STATUS_PENDING,
            'assign_uid' => 0,
            'cost_price' => (float)$asset->recycle_final_price,
        ]);
        (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', (int)$asset->id],
        ])->update(['price_status' => DeviceAssetDict::PRICE_STATUS_PENDING]);

        $this->writeLog((int)$asset->id, (int)$asset->device_id, DeviceAssetDict::ACTION_PRICE_ORDER_CREATE, []);
    }

    protected function refreshMediaCount(int $assetId): void
    {
        $imageCount = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
            ['media_type', '=', 'image'],
            ['status', '<>', DeviceAssetDict::MEDIA_STATUS_REJECTED],
        ])->count();
        $videoCount = (new DeviceAssetMedia())->where([
            ['site_id', '=', $this->site_id],
            ['asset_id', '=', $assetId],
            ['media_type', '=', 'video'],
            ['status', '<>', DeviceAssetDict::MEDIA_STATUS_REJECTED],
        ])->count();
        (new DeviceAssetItem())->where([
            ['site_id', '=', $this->site_id],
            ['id', '=', $assetId],
        ])->update([
            'image_count' => $imageCount,
            'video_count' => $videoCount,
        ]);
    }

    protected function writeLog(int $assetId, int $deviceId, string $action, array $payload = []): void
    {
        if ($deviceId <= 0 && $assetId > 0) {
            $deviceId = (int)((new DeviceAssetItem())->where([
                ['site_id', '=', $this->site_id],
                ['id', '=', $assetId],
            ])->value('device_id') ?: 0);
        }

        DeviceAssetOperationLog::create([
            'site_id' => $this->site_id,
            'asset_id' => $assetId,
            'device_id' => $deviceId,
            'action' => $action,
            'action_name' => DeviceAssetDict::actionName($action),
            'operator_uid' => $this->uid,
            'operator_name' => $this->username ?: '',
            'payload' => $payload,
            'ip' => $this->request->ip(),
        ]);
    }

    protected function makeAssetNo(int $id): string
    {
        return 'DA' . date('Ymd') . str_pad((string)$this->site_id, 3, '0', STR_PAD_LEFT) . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
    }

    protected function makeBizNo(string $prefix): string
    {
        return $prefix . date('YmdHis') . mt_rand(1000, 9999);
    }
}
