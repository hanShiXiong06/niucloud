<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpAssetService;
use addon\hsx_erp\app\service\admin\ErpStandaloneInboundService;
use core\base\BaseAdminController;
use think\App;

class Asset extends BaseAdminController
{
    protected ErpAssetService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpAssetService();
    }

    public function lists()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['inventory_status', ''],
            ['warehouse_id', 0],
            ['page', 1],
            ['limit', 20],
        ]);
        return success($this->service->getPage($data));
    }

    /**
     * 集成状态：探测中台(数据中台)是否接入。
     * 接入后拍照与销售定价由中台负责，ERP 隐藏自身定价、改显示"已交中台"。
     * class_exists 守卫：中台未安装则类不存在，返回 false，ERP 独立运行不受影响。
     */
    public function integrationStatus()
    {
        $deviceAsset = class_exists('\\addon\\hsx_device_asset\\app\\service\\admin\\DeviceAssetService');
        $recycle = class_exists('\\addon\\hsx_recycle\\app\\service\\admin\\order\\RecycleDeviceService');
        return success([
            'device_asset_connected' => $deviceAsset,
            'recycle_connected'      => $recycle,
        ]);
    }

    public function info(int $id)
    {
        return success($this->service->getInfo($id));
    }

    /** 实时调整在库设备成本(写成本流水) */
    public function adjustCost(int $id)
    {
        $p = $this->request->params([['cost', 0], ['reason', '']]);
        return success($this->service->adjustCost($id, (float)$p['cost'], (string)$p['reason']));
    }

    public function confirmInbound(int $id)
    {
        $data = $this->request->params([
            ['warehouse_id', 0],
            ['location_id', 0],
            ['remark', ''],
        ]);
        return success($this->service->confirmInbound($id, $data));
    }

    public function confirmAssetInbound(int $id)
    {
        $data = $this->request->params([
            ['warehouse_id', 0],
            ['location_id', 0],
            ['remark', ''],
        ]);
        return success($this->service->confirmInboundByAsset($id, $data));
    }

    public function batchConfirmInbound()
    {
        $data = $this->request->params([
            ['asset_ids', []],
            ['warehouse_id', 0],
            ['location_id', 0],
            ['remark', ''],
        ]);
        return success($this->service->confirmAssetsInbound((array)$data['asset_ids'], $data));
    }

    public function manualInbound()
    {
        $data = $this->request->params([
            ['imei', ''],
            ['imei2', ''],
            ['sn', ''],
            ['model', ''],
            ['category_id', 0],
            ['capacity', ''],
            ['color', ''],
            ['business_type', 'recycle'],
            ['counterparty_id', 0],
            ['purchase_cost', 0],
            ['paid_amount', 0],
            ['suggested_sale_price', 0],
            ['warehouse_id', 0],
            ['location_id', 0],
            ['remark', ''],
        ]);
        return success((new ErpStandaloneInboundService())->create($data));
    }
}
