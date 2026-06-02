<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\device;

use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelDictService;
use core\base\BaseAdminController;
use think\App;

/**
 * 回收设备型号字典
 */
class RecycleDeviceModelDict extends BaseAdminController
{
    protected RecycleDeviceModelDictService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleDeviceModelDictService();
    }

    public function lists()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);

        return success($this->service->getPage($data));
    }

    public function options()
    {
        $data = $this->request->params([
            ['keyword', ''],
        ]);

        return success($this->service->options($data));
    }

    public function tree()
    {
        return success($this->service->tree());
    }

    public function add()
    {
        $data = $this->request->params([
            ['brand_name', ''],
            ['series_name', ''],
            ['model_name', ''],
            ['status', 1],
            ['sort', 0],
        ]);

        return success($this->service->add($data));
    }

    public function edit(int $id)
    {
        $data = $this->request->params([
            ['brand_name', ''],
            ['series_name', ''],
            ['model_name', ''],
            ['status', 1],
            ['sort', 0],
        ]);

        return success($this->service->edit($id, $data));
    }

    public function del(int $id)
    {
        return success($this->service->delete($id));
    }

    public function quickAdd()
    {
        $data = $this->request->params([
            ['content', ''],
        ]);

        return success($this->service->quickAdd((string)$data['content']));
    }
}
