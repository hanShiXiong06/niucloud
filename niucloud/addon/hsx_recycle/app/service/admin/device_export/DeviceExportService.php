<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\device_export;

use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use addon\hsx_recycle\app\job\device\DeviceBarcodeExportJob;
use app\dict\sys\ExportDict;
use app\model\sys\SysExport;
use app\service\core\sys\CoreExportService;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * 设备导出服务
 */
class DeviceExportService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleDevice();
    }

    /**
     * 获取已回收设备分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = []): array
    {

        $field = 'id, imei, imei2, sn, model, member_id, category_id, status, update_at, final_price, sell_price, create_at, order_id, check_result_buyer, check_images_buyer,price_uid,export_time,dispose_type,dispose_status,settlement_mode,consignment_order_id,sale_destination,target_warehouse_id,target_warehouse_name,target_location_id,target_location_name';

        $search_model = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->withSearch(['imei', 'model', 'status', 'update_at','export_status', 'warehouse_type'], $where)
            ->whereIn('status', !empty($where['status']) ? [(int)$where['status']] : [
                RecycleOrderDict::DEVICE_STATUS_RECYCLED,
                RecycleOrderDict::DEVICE_STATUS_CONSIGNED,
            ])
            ->with([
                'order' => function($query) {
                    $query->with(['member' => function($q) {
                        $q->field('member_id, username, nickname, mobile, headimg');
                    }]);
                },
                'priceUser' => function($query) {
                    $query->field('uid, username, real_name');
                },
                'consignmentOrder' => function($query) {
                    $query->field('id,consignment_no,source_device_id,status,listing_price,sold_price,settlement_amount');
                }
            ])
            ->field($field)
            // 导出时间 和 更新时间 都排序
            ->order('export_time asc, update_at desc')
            ->append(['status_name', 'category_name' , 'nickname', 'dispose_type_name', 'dispose_status_name']);

        // 筛选分类
        if (!empty($where['category_id'])) {
            $search_model->where('category_id', $where['category_id']);
        }

        $result = $this->pageQuery($search_model);
        $deviceIds = array_map('intval', array_column($result['data'] ?? [], 'id'));
        if (!empty($deviceIds)) {
            $listeners = array_values(array_filter(event('GetErpDeviceSyncStatus', [
                'site_id' => $this->site_id,
                'device_ids' => $deviceIds,
            ])));
            $statusMap = $listeners[0] ?? [];
            foreach ($result['data'] as &$device) {
                $device['erp_sync'] = $statusMap[(int)$device['id']] ?? null;
            }
            unset($device);
        }

        return $result;
    }

    /**
     * 导出已回收设备
     * @param array $where
     * @return bool
     */
    public function export(array $where = []): bool
    {
        if ((int)$this->site_id <= 0) throw new CommonException('站点信息无效，请重新登录后重试');
        DeviceBarcodeWorkbook::assertSupported();
        $ids = $where['device_ids'] ?? [];
        if (!is_array($ids)) throw new CommonException('导出设备参数不正确，请刷新列表后重新选择');
        foreach ($ids as $id) {
            if ((!is_int($id) && !is_string($id)) || !ctype_digit((string)$id) || (int)$id <= 0) {
                throw new CommonException('导出设备参数不正确，请刷新列表后重新选择');
            }
        }
        $where['device_ids'] = array_values(array_unique(array_map('intval', $ids)));
        $exportId = (int)(new CoreExportService())->add([
            'site_id' => $this->site_id, 'export_key' => 'recycle_device', 'export_num' => 0,
            'export_status' => ExportDict::EXPORTING, 'create_time' => time(),
        ]);
        try {
            DeviceBarcodeExportJob::dispatch(['site_id' => (int)$this->site_id, 'export_id' => $exportId, 'where' => $where]);
        } catch (\Throwable $e) {
            SysExport::where([['id', '=', $exportId], ['site_id', '=', $this->site_id]])->where('export_status', '<>', ExportDict::SUCCESS)->update([
                'export_status' => ExportDict::FAIL, 'fail_reason' => mb_substr($e->getMessage(), 0, 250),
            ]);
            throw $e;
        }

        return true;
    }
}
