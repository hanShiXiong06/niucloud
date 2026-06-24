<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpSettingService;
use core\base\BaseAdminController;
use think\App;
use think\Response;

/**
 * ERP / 财务 设置:总开关、现结开关
 */
class Config extends BaseAdminController
{
    protected ErpSettingService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new ErpSettingService();
    }

    /** 读取 ERP 设置 */
    public function get(): Response
    {
        return success($this->service->get());
    }

    /** 保存 ERP 设置 */
    public function save(): Response
    {
        $data = $this->request->params([
            ['allow_instant_settle', 1],
        ]);
        $this->service->save($data);
        return success();
    }
}
