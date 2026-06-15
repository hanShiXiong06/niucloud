<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\DeviceTraceService;
use core\base\BaseAdminController;
use think\App;

/**
 * 设备全链路追溯
 */
class DeviceTrace extends BaseAdminController
{
    protected DeviceTraceService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new DeviceTraceService();
    }

    /** 搜索 → 生命周期列表 */
    public function search()
    {
        return success($this->service->searchList((string)$this->request->param('keyword', '')));
    }

    /** 某生命周期详情(概览+时间线) */
    public function detail()
    {
        $p = $this->request->params([['asset_id', 0], ['device_id', 0]]);
        return success($this->service->detail((int)$p['asset_id'], (int)$p['device_id']));
    }
}
