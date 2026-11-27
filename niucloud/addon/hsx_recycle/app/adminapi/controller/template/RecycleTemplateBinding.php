<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\template;

use addon\hsx_recycle\app\service\admin\template\RecycleTemplateBindingService;
use core\base\BaseAdminController;
use think\App;

/**
 * 回收模板绑定
 */
class RecycleTemplateBinding extends BaseAdminController
{
    protected RecycleTemplateBindingService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleTemplateBindingService();
    }

    public function info()
    {
        $data = $this->request->params([
            ['target_type', 'model_dict'],
            ['target_id', 0],
            ['scene_key', 'manual_device_label'],
        ]);

        return success($this->service->getInfo((string)$data['target_type'], (int)$data['target_id'], (string)$data['scene_key']));
    }

    public function save()
    {
        $data = $this->request->params([
            ['target_type', 'model_dict'],
            ['target_id', 0],
            ['scene_key', 'manual_device_label'],
            ['check_template_id', 0],
            ['print_template_id', 0],
            ['inherit_enabled', 1],
            ['status', 1],
            ['remark', ''],
            ['sort', 0],
        ]);

        return success($this->service->save($data));
    }

    public function reset()
    {
        $data = $this->request->params([
            ['target_type', 'model_dict'],
            ['target_id', 0],
            ['scene_key', 'manual_device_label'],
        ]);

        return success($this->service->reset((string)$data['target_type'], (int)$data['target_id'], (string)$data['scene_key']));
    }

    public function resolveDevice()
    {
        $data = $this->request->params([
            ['device_id', 0],
            ['scene_key', 'manual_device_label'],
        ]);

        return success($this->service->resolveByDeviceId((int)$data['device_id'], (string)$data['scene_key']));
    }
}
