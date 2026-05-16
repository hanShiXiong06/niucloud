<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller;

use addon\recycle\app\service\admin\device_export\DeviceExportService;
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
            ['status', 5], // 固定为已回收状态
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
            ['status', 5], // 固定为已回收状态
        ]);

        (new DeviceExportService())->export($data);
        return success('EXPORT_SUCCESS');
    }
}
