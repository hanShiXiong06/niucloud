<?php
declare(strict_types=1);

namespace addon\hsx_device_asset\app\adminapi\controller;

use addon\hsx_device_asset\app\service\admin\DeviceAssetService;
use core\base\BaseAdminController;
use think\App;

/**
 * 设备资产中台
 */
class DeviceAsset extends BaseAdminController
{
    protected DeviceAssetService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new DeviceAssetService();
    }

    /**
     * 回收完成后的待入库设备池。
     */
    public function pool()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['imei', ''],
            ['model', ''],
            ['category_id', ''],
            ['status', ''],
            ['update_at', []],
            ['page', 1],
            ['limit', 10],
        ]);

        return success($this->service->getImportableDevices($data));
    }

    /**
     * 资产列表。
     */
    public function lists()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['category_id', ''],
            ['status', ''],
            ['photo_status', ''],
            ['price_status', ''],
            ['export_status', ''],
            ['task_type', ''],
            ['create_at', []],
            ['page', 1],
            ['limit', 10],
        ]);

        return success($this->service->getPage($data));
    }

    public function stats()
    {
        return success($this->service->getTaskStats());
    }

    public function info($id)
    {
        return success($this->service->getInfo((int)$id));
    }

    /**
     * 批量导入回收设备。
     */
    public function import()
    {
        $data = $this->request->params([
            ['device_ids', []],
        ]);

        return success($this->service->importDevices((array)$data['device_ids']));
    }

    /**
     * 扫码导入或定位资产。
     */
    public function scan()
    {
        $data = $this->request->params([
            ['keyword', ''],
        ]);

        return success($this->service->scanImport((string)$data['keyword']));
    }

    public function createPhotoTask($id)
    {
        $data = $this->request->params([
            ['source', 'pc'],
            ['station_id', ''],
            ['camera_job_id', ''],
            ['remark', ''],
        ]);

        return success($this->service->createPhotoTask((int)$id, $data));
    }

    public function saveMedia($id)
    {
        $data = $this->request->params([
            ['media', []],
            ['url', ''],
            ['oss_key', ''],
            ['media_type', 'image'],
            ['scene', 'common'],
            ['source', 'manual'],
            ['sort', 0],
            ['task_id', 0],
        ]);

        return success($this->service->saveMedia((int)$id, $data));
    }

    public function reviewMedia($media_id)
    {
        $data = $this->request->params([
            ['status', ''],
            ['reject_reason', ''],
        ]);

        return success($this->service->reviewMedia((int)$media_id, (string)$data['status'], (string)$data['reject_reason']));
    }

    public function reviewMediaBatch($id)
    {
        $data = $this->request->params([
            ['media_ids', []],
            ['status', ''],
            ['reject_reason', ''],
        ]);

        return success($this->service->reviewMediaBatch((int)$id, (array)$data['media_ids'], (string)$data['status'], (string)$data['reject_reason']));
    }

    public function confirmPhotos($id)
    {
        return success($this->service->confirmPhotos((int)$id));
    }

    public function price($id)
    {
        $data = $this->request->params([
            ['sale_price', 0],
            ['peer_price', 0],
            ['min_price', 0],
            ['remark', ''],
        ]);

        return success($this->service->completePrice((int)$id, $data));
    }

    public function export()
    {
        $data = $this->request->params([
            ['asset_ids', []],
            ['keyword', ''],
            ['status', ''],
            ['price_status', ''],
        ]);
        $filePath = $this->service->exportExcel($data);

        return download($filePath, '设备资产导出_' . date('Y-m-d') . '.xlsx');
    }
}
