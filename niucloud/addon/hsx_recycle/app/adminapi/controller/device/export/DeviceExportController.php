<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\device\export;

use addon\hsx_recycle\app\service\admin\device_export\DeviceExportService;
use addon\hsx_recycle\app\service\admin\order\RecycleDeviceErpSyncService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 设备导出控制器
 */
class DeviceExportController extends BaseAdminController
{
    /**
     * 获取已回收设备列表
     * @return Response
     */
    public function list()
    {
        $data = $this->request->params([
            ['imei', ''],
            ['model', ''],
            ['category_id', ''],
            ['update_at', []],
            ['status', ''], // 默认导出已回收和已转代卖设备
            ['warehouse_type', ''],
            ['export_status', ''],
        ]);

        return success('SUCCESS', (new DeviceExportService())->getPage($data));
    }

    /**
     * 导出已回收设备
     * @return Response
     */
    public function export()
    {
        $data = $this->request->params([
            ['imei', ''],
            ['model', ''],
            ['category_id', ''],
            ['update_at', []],
            ['status', ''],
            ['warehouse_type', ''],
            ['export_status', ''],
             ['device_ids', []],
        ]);

        (new DeviceExportService())->export($data);
        return success('EXPORT_SUCCESS');
    }

    /**
     * 批量同步已回收/已代卖设备到 ERP。
     * @return Response
     */
    public function syncErp()
    {
        $data = $this->request->params([
            ['device_ids', []],
            ['targets', ['self_erp']],
        ]);

        return success((new RecycleDeviceErpSyncService())->dispatch(
            (array)$data['device_ids'],
            (array)$data['targets']
        ));
    }

    /**
     * 批量查询设备下游同步健康度（前端据此只在"卡住"的设备上显示「重新同步」）。
     * @return Response
     */
    public function syncHealth()
    {
        $data = $this->request->params([
            ['device_ids', []],
        ]);

        return success((new RecycleDeviceErpSyncService())->syncHealth((array)$data['device_ids']));
    }

    /**
     * 重新同步单台设备（兜底：事件失效时手动补齐下游步骤，如中台待拍照）。
     * @param int $id
     * @return Response
     */
    public function resync(int $id)
    {
        return success((new RecycleDeviceErpSyncService())->resync($id));
    }
}
